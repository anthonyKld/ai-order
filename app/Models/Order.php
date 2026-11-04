<?php
declare(strict_types=1);

namespace App\Models;

use App\Services\OrderPreviewChanged;
use App\Validation\ReviewedOrderValidator as Validator;
use DomainException;
use PDO;
use PDOException;
use Throwable;

final class Order
{
    public const PAGE_SIZE = 10;
    public const STATUSES = ['pending' => 'Pendente', 'processing' => 'Em processamento', 'completed' => 'Concluído', 'cancelled' => 'Cancelado'];
    public const TRANSITIONS = ['pending' => ['processing', 'cancelled'], 'processing' => ['completed', 'cancelled'], 'completed' => [], 'cancelled' => []];
    public const COLORS = ['pending' => 'warning', 'processing' => 'primary', 'completed' => 'success', 'cancelled' => 'secondary'];

    public function __construct(private readonly PDO $db) {}

    public function findByKey(string $key): ?array
    {
        $query = $this->db->prepare('SELECT * FROM orders WHERE idempotency_key = ?');
        $query->execute([$key]);
        return $query->fetch() ?: null;
    }

    public function find(int $id): ?array
    {
        $query = $this->db->prepare('SELECT * FROM orders WHERE id = ?');
        $query->execute([$id]);
        $order = $query->fetch();
        if (!$order) return null;
        $query = $this->db->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id');
        $query->execute([$id]);
        return [...$order, 'items' => $query->fetchAll()];
    }

    public function paginate(string $search, string $status, int $page): array
    {
        $where = []; $params = [];
        if ($search !== '') {
            $where[] = "order_number LIKE ? ESCAPE '!'";
            $params[] = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }
        if (isset(self::STATUSES[$status])) { $where[] = 'status = ?'; $params[] = $status; }
        $clause = $where ? ' WHERE ' . implode(' AND ', $where) : '';
        $query = $this->db->prepare('SELECT COUNT(*) FROM orders' . $clause);
        $query->execute($params);
        $total = (int) $query->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min($page, $pages));
        $query = $this->db->prepare('SELECT id, order_number, created_at, total_items, total, status FROM orders' . $clause . ' ORDER BY created_at DESC, id DESC LIMIT ? OFFSET ?');
        foreach ($params as $i => $param) $query->bindValue($i + 1, $param, PDO::PARAM_STR);
        $query->bindValue(count($params) + 1, self::PAGE_SIZE, PDO::PARAM_INT);
        $query->bindValue(count($params) + 2, ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT);
        $query->execute();
        return ['orders' => $query->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public function create(?array $preview, string $key): array
    {
        if (!preg_match('/^[a-f0-9]{64}$/D', $key)) throw new DomainException('Chave de confirmação inválida.');
        if ($existing = $this->findByKey($key)) return $existing;
        if ($preview === null || empty($preview['confirmed_at']) || ($preview['idempotency_key'] ?? null) !== $key) {
            throw new DomainException('Confirme a prévia antes de salvar o pedido.');
        }
        if (!is_array($preview['items'] ?? null) || !array_is_list($preview['items']) || !$preview['items'] || count($preview['items']) > Validator::MAX_ITEMS) {
            throw new DomainException('Informe entre 1 e 100 itens.');
        }
        $this->db->beginTransaction();
        try {
            // A reserva da chave UNIQUE vem primeiro: reenvios aguardam o commit original.
            $number = 'AO-' . gmdate('Ymd') . '-' . strtoupper(bin2hex(random_bytes(16)));
            $this->db->prepare('INSERT INTO orders (order_number, idempotency_key, total, total_items) VALUES (?, ?, 0, 1)')->execute([$number, $key]);
            $id = (int) $this->db->lastInsertId();
            $catalog = $this->lockCatalog($preview['items']);
            $result = (new Validator())->validate($preview, static fn (int $id): ?array => $catalog[$id] ?? null);
            if (!$result['success']) throw new OrderPreviewChanged($result);
            foreach ($result['validated_items'] as $i => $item) {
                foreach (['unit_price', 'product_name', 'variant'] as $field) {
                    if (!array_key_exists($field, $preview['items'][$i]) || $preview['items'][$i][$field] !== $item[$field]) {
                        throw new OrderPreviewChanged($result);
                    }
                }
            }
            $insert = $this->db->prepare('INSERT INTO order_items (order_id, product_id, variant_id, product_name, variant_name, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
            foreach ($result['validated_items'] as $item) {
                $insert->execute([$id, $item['product_id'], $item['variant_id'], $item['product_name'], $item['variant'], $item['quantity'], $item['unit_price'], $item['subtotal']]);
            }
            $this->adjustStock($result['validated_items'], false);
            $this->db->prepare('UPDATE orders SET total = ?, total_items = ?, notes = ?, requested_deadline = ? WHERE id = ?')->execute([
                $result['estimated_total'], $result['total_units'], $result['notes'], $result['requested_deadline'], $id,
            ]);
            $this->db->commit();
            return $this->findByKey($key);
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            if ($exception instanceof PDOException && ($exception->errorInfo[1] ?? null) === 1062 && ($existing = $this->findByKey($key))) return $existing;
            $this->rethrow($exception);
        }
    }

    public function changeStatus(int $id, string $target): array
    {
        if (!isset(self::STATUSES[$target])) throw new DomainException('Status inválido.');
        $this->db->beginTransaction();
        try {
            $query = $this->db->prepare('SELECT * FROM orders WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $order = $query->fetch();
            if (!$order) throw new DomainException('Pedido não encontrado.');
            // Repetições da mesma ação são inofensivas, inclusive cancelamentos simultâneos.
            if ($order['status'] !== $target) {
                if (!in_array($target, self::TRANSITIONS[$order['status']], true)) throw new DomainException('Esta alteração de status não é permitida.');
                if ($target === 'cancelled') {
                    $query = $this->db->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY product_id, variant_id, id');
                    $query->execute([$id]);
                    $items = $query->fetchAll();
                    $this->lockCatalog($items);
                    $this->adjustStock($items, true);
                }
                $this->db->prepare('UPDATE orders SET status = ?, cancelled_at = CASE WHEN ? = \'cancelled\' THEN CURRENT_TIMESTAMP ELSE NULL END, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([$target, $target, $id]);
            }
            $this->db->commit();
            return $this->find($id);
        } catch (Throwable $exception) {
            if ($this->db->inTransaction()) $this->db->rollBack();
            $this->rethrow($exception);
        }
    }

    private function lockCatalog(array $items): array
    {
        $ids = [];
        foreach ($items as $item) {
            $id = is_array($item) ? Validator::integer($item['product_id'] ?? null, 4294967295) : null;
            if ($id === null) throw new DomainException('Produto inválido.');
            $ids[$id] = $id;
        }
        sort($ids, SORT_NUMERIC);
        $catalog = [];
        // Mesma hierarquia do cadastro: produto, suas variações; produtos em ordem crescente.
        foreach ($ids as $id) {
            $query = $this->db->prepare('SELECT * FROM products WHERE id = ? FOR UPDATE');
            $query->execute([$id]);
            $product = $query->fetch();
            if (!$product) continue;
            $query = $this->db->prepare('SELECT id, name, stock FROM product_variants WHERE product_id = ? ORDER BY id FOR UPDATE');
            $query->execute([$id]);
            $catalog[$id] = [...$product, 'variants' => $query->fetchAll()];
        }
        return $catalog;
    }

    private function adjustStock(array $items, bool $restore): void
    {
        $quantities = [];
        foreach ($items as $item) {
            $table = $item['variant_id'] === null ? 'products' : 'product_variants';
            $id = $item['variant_id'] ?? $item['product_id'];
            $quantities[$table][$id] = ($quantities[$table][$id] ?? 0) + $item['quantity'];
        }
        foreach ($quantities as $table => $rows) {
            ksort($rows, SORT_NUMERIC);
            foreach ($rows as $id => $quantity) {
                $sql = $restore ? "UPDATE $table SET stock = stock + ? WHERE id = ? AND stock <= ?" : "UPDATE $table SET stock = stock - ? WHERE id = ? AND stock >= ?";
                $query = $this->db->prepare($sql);
                $query->execute([$quantity, $id, $restore ? 2147483647 - $quantity : $quantity]);
                if ($query->rowCount() !== 1) throw new DomainException($restore ? 'O estoque excederia o limite permitido. Ajuste o cadastro antes de cancelar.' : 'Estoque insuficiente. Confira novamente o pedido.');
            }
        }
    }

    private function rethrow(Throwable $exception): never
    {
        if ($exception instanceof PDOException && in_array($exception->errorInfo[1] ?? null, [1205, 1213], true)) {
            throw new DomainException('Outra operação alterou estes registros. Tente novamente.');
        }
        throw $exception;
    }
}
