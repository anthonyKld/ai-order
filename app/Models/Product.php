<?php

declare(strict_types=1);

namespace App\Models;

use DomainException;
use PDO;
use PDOException;
use Throwable;

final class Product
{
    public const PAGE_SIZE = 10;

    public function __construct(private readonly PDO $db)
    {
    }

    public function paginate(string $search, string $status, int $page): array
    {
        $where = [];
        $params = [];
        if ($search !== '') {
            $where[] = "p.name LIKE :search ESCAPE '!'";
            $params['search'] = '%' . strtr($search, ['!' => '!!', '%' => '!%', '_' => '!_']) . '%';
        }
        if (in_array($status, ['0', '1'], true)) {
            $where[] = 'p.active = :active';
            $params['active'] = $status;
        }
        $clause = $where === [] ? '' : ' WHERE ' . implode(' AND ', $where);
        $count = $this->db->prepare('SELECT COUNT(*) FROM products p' . $clause);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $pages = max(1, (int) ceil($total / self::PAGE_SIZE));
        $page = max(1, min($page, $pages));
        $query = $this->db->prepare('SELECT p.*, COALESCE((SELECT SUM(v.stock) FROM product_variants v WHERE v.product_id = p.id), p.stock) AS available_stock,
            (SELECT COUNT(*) FROM product_variants v WHERE v.product_id = p.id) AS variant_count
            FROM products p' . $clause . ' ORDER BY p.id DESC LIMIT :limit OFFSET :offset');
        foreach ($params as $key => $value) {
            $query->bindValue(':' . $key, $value, PDO::PARAM_STR);
        }
        $query->bindValue(':limit', self::PAGE_SIZE, PDO::PARAM_INT);
        $query->bindValue(':offset', ($page - 1) * self::PAGE_SIZE, PDO::PARAM_INT);
        $query->execute();
        return ['products' => $query->fetchAll(), 'total' => $total, 'pages' => $pages, 'page' => $page];
    }

    public function find(int $id): ?array
    {
        $query = $this->db->prepare('SELECT * FROM products WHERE id = ?');
        $query->execute([$id]);
        $product = $query->fetch();
        if (!$product) {
            return null;
        }
        $query = $this->db->prepare('SELECT id, name, stock FROM product_variants WHERE product_id = ? ORDER BY id');
        $query->execute([$id]);
        $product['variants'] = $query->fetchAll();
        return $product;
    }

    public function save(array $data, ?int $id = null): ?int
    {
        $this->db->beginTransaction();
        try {
            $existing = [];
            if ($id !== null) {
                $lock = $this->db->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE');
                $lock->execute([$id]);
                if (!$lock->fetch()) {
                    $this->db->rollBack();
                    return null;
                }
                $query = $this->db->prepare('SELECT id FROM product_variants WHERE product_id = ? FOR UPDATE');
                $query->execute([$id]);
                $existing = array_map('intval', $query->fetchAll(PDO::FETCH_COLUMN));
            }
            foreach ($data['variants'] as $variant) {
                if ($variant['id'] !== null && !in_array($variant['id'], $existing, true)) {
                    throw new DomainException('Uma variação não pertence a este produto. Reabra o cadastro e tente novamente.');
                }
            }
            $params = [$data['name'], $data['description'], $data['price'], $data['variants'] === [] ? $data['stock'] : 0, $data['active']];
            if ($id === null) {
                $this->db->prepare('INSERT INTO products (name, description, price, stock, active) VALUES (?, ?, ?, ?, ?)')->execute($params);
                $id = (int) $this->db->lastInsertId();
            } else {
                $this->db->prepare('UPDATE products SET name = ?, description = ?, price = ?, stock = ?, active = ?, updated_at = CURRENT_TIMESTAMP WHERE id = ?')->execute([...$params, $id]);
            }
            $kept = array_column($data['variants'], 'id');
            $delete = $this->db->prepare('DELETE FROM product_variants WHERE product_id = ? AND id = ?');
            $rename = $this->db->prepare('UPDATE product_variants SET name = ? WHERE product_id = ? AND id = ?');
            // Nomes temporários dentro da transação permitem trocar nomes entre IDs existentes.
            $prefix = '__' . bin2hex(random_bytes(16)) . '_';
            foreach ($existing as $variantId) {
                if (!in_array($variantId, $kept, true)) {
                    $delete->execute([$id, $variantId]);
                } else {
                    $rename->execute([$prefix . $variantId, $id, $variantId]);
                }
            }
            $insert = $this->db->prepare('INSERT INTO product_variants (product_id, name, stock) VALUES (?, ?, ?)');
            $update = $this->db->prepare('UPDATE product_variants SET name = ?, stock = ? WHERE product_id = ? AND id = ?');
            foreach ($data['variants'] as $variant) {
                if ($variant['id'] === null) {
                    $insert->execute([$id, $variant['name'], $variant['stock']]);
                } else {
                    $update->execute([$variant['name'], $variant['stock'], $id, $variant['id']]);
                }
            }
            $this->db->commit();
            return $id;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            $this->rethrow($exception);
        }
    }

    public function delete(int $id): bool
    {
        $this->db->beginTransaction();
        try {
            $lock = $this->db->prepare('SELECT id FROM products WHERE id = ? FOR UPDATE');
            $lock->execute([$id]);
            if (!$lock->fetch()) {
                $this->db->rollBack();
                return false;
            }
            $this->db->prepare('DELETE FROM product_variants WHERE product_id = ?')->execute([$id]);
            $this->db->prepare('DELETE FROM products WHERE id = ?')->execute([$id]);
            $this->db->commit();
            return true;
        } catch (Throwable $exception) {
            $this->db->rollBack();
            $this->rethrow($exception);
        }
    }

    private function rethrow(Throwable $exception): never
    {
        if ($exception instanceof PDOException) {
            if (($exception->errorInfo[1] ?? null) === 1062) {
                throw new DomainException('As variações não podem ter nomes duplicados, inclusive diferenças apenas de maiúsculas ou acentos.');
            }
            if (($exception->errorInfo[1] ?? null) === 1451) {
                throw new DomainException('Não é possível remover um produto ou variação com histórico comercial. Você pode inativar o produto.');
            }
        }
        throw $exception;
    }
}
