<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\Order;
use App\Services\OrderPreviewChanged;
use App\Services\OrderPreviewSession;
use App\Validation\ReviewedOrderValidator;
use DomainException;

final class OrderController
{
    private function model(): Order
    {
        return new Order((new Database(require dirname(__DIR__, 2) . '/config/database.php'))->connection());
    }

    public function index(): void
    {
        $search = is_string($_GET['q'] ?? '') ? substr(trim($_GET['q'] ?? ''), 0, 200) : '';
        $filter = is_string($_GET['status'] ?? '') ? ($_GET['status'] ?? '') : '';
        $page = ReviewedOrderValidator::integer($_GET['page'] ?? '1', 1000000) ?? 1;
        $result = $this->model()->paginate($search, $filter, $page);
        View::render('orders/index', [...$result, 'title' => 'Pedidos', 'active' => 'pedidos', 'search' => $search, 'statusFilter' => $filter]);
    }

    public function show(): void
    {
        $id = ReviewedOrderValidator::integer($_GET['id'] ?? null, 4294967295);
        $order = $id === null ? null : $this->model()->find($id);
        if ($order === null) { $this->error(404, 'Pedido não encontrado.'); return; }
        View::render('orders/show', ['title' => 'Detalhes do pedido', 'active' => 'pedidos', 'order' => $order, 'flash' => Session::takeFlash()]);
    }

    public function store(): void
    {
        try {
            if (!Session::validToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) { $this->jsonError(403, 'Sua sessão expirou. Reabra a página.'); return; }
            if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') { $this->jsonError(415, 'Envie os dados em JSON.'); return; }
            $raw = file_get_contents('php://input', false, null, 0, 4097);
            if (strlen($raw) > 4096) { $this->jsonError(413, 'Requisição muito grande.'); return; }
            try { $input = json_decode($raw, true, 8, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { $this->jsonError(400, 'JSON inválido.'); return; }
            $key = is_array($input) ? ($input['idempotency_key'] ?? null) : null;
            if (!is_string($key) || !preg_match('/^[a-f0-9]{64}$/D', $key)) { $this->jsonError(422, 'Chave de confirmação inválida.'); return; }
            $preview = $_SESSION['confirmed_order_preview'] ?? null;
            if (($preview['idempotency_key'] ?? null) !== $key && !isset($_SESSION['saved_order_keys'][$key])) {
                $this->jsonError(409, 'Esta confirmação não está disponível. Reabra a página e confirme a prévia novamente.'); return;
            }
            // Nenhum item, preço ou total recebido do navegador participa da gravação.
            $order = $this->model()->create($preview, $key);
            $_SESSION['saved_order_keys'][$key] = (int) $order['id'];
            if (($preview['idempotency_key'] ?? null) === $key) OrderPreviewSession::invalidate($_SESSION);
            Session::flash('Pedido ' . $order['order_number'] . ' salvo com sucesso.');
            $this->json(['success' => true, 'id' => (int) $order['id'], 'order_number' => $order['order_number'], 'redirect' => url('pedidos/detalhes?id=' . $order['id'])]);
        } catch (OrderPreviewChanged $exception) {
            $revision = OrderPreviewSession::invalidate($_SESSION);
            $this->json(['success' => false, 'error' => ['message' => $exception->getMessage()], 'preview' => $exception->result, 'revision' => $revision], 409);
        } catch (DomainException $exception) {
            $this->jsonError(409, $exception->getMessage());
        } catch (\Throwable $exception) {
            error_log('AI Order: falha ao salvar pedido (' . $exception::class . ').');
            $this->jsonError(500, 'Não foi possível salvar o pedido. Tente novamente; a mesma confirmação não será gravada duas vezes.');
        }
    }

    public function status(): void
    {
        if (!Session::validToken($_POST['_token'] ?? null)) { $this->error(403, 'Sua sessão expirou. Reabra a página.'); return; }
        $id = ReviewedOrderValidator::integer($_POST['id'] ?? null, 4294967295);
        $target = $_POST['status'] ?? null;
        if ($id === null || !is_string($target) || !isset(Order::STATUSES[$target])) { $this->error(422, 'Pedido ou status inválido.'); return; }
        if ($target === 'cancelled' && ($_POST['confirm'] ?? null) !== '1') { $this->error(422, 'Confirme o cancelamento do pedido.'); return; }
        try {
            $this->model()->changeStatus($id, $target);
            Session::flash($target === 'cancelled' ? 'Pedido cancelado. O estoque foi restaurado uma única vez.' : 'Status atualizado com sucesso.');
            header('Location: ' . url('pedidos/detalhes?id=' . $id), true, 303);
        } catch (DomainException $exception) { $this->error(409, $exception->getMessage()); }
        catch (\Throwable $exception) {
            error_log('AI Order: falha ao alterar pedido (' . $exception::class . ').');
            $this->error(500, 'Não foi possível alterar o pedido. Nenhuma alteração parcial foi mantida. Tente novamente.');
        }
    }

    private function error(int $status, string $message): void
    {
        http_response_code($status);
        View::render('errors/http', ['title' => 'Não foi possível concluir', 'active' => 'pedidos', 'status' => $status, 'message' => $message]);
    }

    private function jsonError(int $status, string $message): void
    {
        $this->json(['success' => false, 'error' => ['message' => $message]], $status);
    }

    private function json(array $data, int $status = 200): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
