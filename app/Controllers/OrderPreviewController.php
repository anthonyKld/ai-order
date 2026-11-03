<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Models\Product;
use App\Services\OrderPreviewSession;
use App\Validation\ReviewedOrderValidator;

final class OrderPreviewController
{
    private function model(): Product
    {
        return new Product((new Database(require dirname(__DIR__, 2) . '/config/database.php'))->connection());
    }

    public function catalog(): void
    {
        try {
            $model = $this->model();
            if (isset($_GET['id'])) {
                $id = ReviewedOrderValidator::integer($_GET['id'], 4294967295);
                $product = $id === null ? null : $model->find($id);
                $products = $product !== null && (int) $product['active'] === 1 ? [$product] : [];
                $pages = 1;
            } else {
                $search = $_GET['search'] ?? '';
                if (!is_string($search) || strlen($search) > 600) { $this->error('Pesquisa inválida.', 422); return; }
                $page = ReviewedOrderValidator::integer($_GET['page'] ?? '1', 1000000) ?? 1;
                $list = $model->paginate(trim($search), '1', $page);
                $pages = $list['pages'];
                $products = [];
                foreach ($list['products'] as $row) {
                    $product = $model->find((int) $row['id']);
                    if ($product !== null && (int) $product['active'] === 1) $products[] = $product;
                }
            }
            $products = array_map(static fn (array $p): array => [
                'id' => (int) $p['id'], 'name' => $p['name'], 'price' => $p['price'], 'stock' => (int) $p['stock'],
                'variants' => array_map(static fn (array $v): array => ['id' => (int) $v['id'], 'name' => $v['name'], 'stock' => (int) $v['stock']], $p['variants']),
            ], $products);
            $this->respond(['success' => true, 'products' => $products, 'pages' => $pages, 'page' => $list['page'] ?? 1]);
        } catch (\Throwable $exception) { $this->failure($exception); }
    }

    public function validate(): void
    {
        try {
            if (!Session::validToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) { $this->error('Sua sessão expirou. Reabra a página e tente novamente.', 403); return; }
            if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') { $this->error('Envie os dados em JSON.', 415); return; }
            $raw = file_get_contents('php://input', false, null, 0, 65537);
            if (strlen($raw) > 65536) { $this->error('A prévia excede o tamanho permitido.', 413); return; }
            try { $input = json_decode($raw, true, 12, JSON_THROW_ON_ERROR); }
            catch (\JsonException) { $this->error('JSON inválido.', 400); return; }
            if (!is_array($input) || !in_array($input['action'] ?? null, ['validate', 'confirm', 'invalidate'], true)) { $this->error('Ação inválida.', 422); return; }
            if (($input['revision'] ?? null) !== OrderPreviewSession::revision($_SESSION)) {
                $this->error('Esta prévia mudou em outra aba ou requisição. Reabra a página para continuar.', 409); return;
            }
            $revision = OrderPreviewSession::invalidate($_SESSION);
            if ($input['action'] === 'invalidate') {
                $this->respond(['success' => true, 'confirmed' => false, 'revision' => $revision]); return;
            }
            $model = $this->model();
            $result = (new ReviewedOrderValidator())->validate($input, $model->find(...));
            if ($result['success'] && $input['action'] === 'confirm') {
                OrderPreviewSession::confirm($_SESSION, $result);
                $result['confirmed'] = true;
                $result['message'] = 'Dados confirmados com sucesso. O pedido está pronto para ser salvo.';
            }
            $this->respond([...$result, 'revision' => $revision], $result['success'] ? 200 : 422);
        } catch (\Throwable $exception) { $this->failure($exception); }
    }

    private function error(string $message, int $status): void
    {
        $this->respond(['success' => false, 'error' => ['message' => $message], 'validated_items' => [],
            'errors' => ['request' => $message], 'warnings' => [], 'estimated_total' => null, 'confirmed' => false,
            'revision' => OrderPreviewSession::revision($_SESSION ?? [])], $status);
    }

    private function failure(\Throwable $exception): void
    {
        error_log('AI Order: falha na conferência (' . $exception::class . ').');
        $this->error('Não foi possível conferir o catálogo. Seus dados foram mantidos na tela. Tente novamente.', 500);
    }

    private function respond(array $data, int $status = 200): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
