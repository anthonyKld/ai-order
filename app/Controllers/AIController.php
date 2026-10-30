<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Database;
use App\Core\Session;
use App\Core\View;
use App\Models\Product;
use App\Services\AIException;
use App\Services\AIRequestGuard;
use App\Services\AIService;
use App\Validation\OrderPreviewValidator;

final class AIController
{
    public function index(): void
    {
        header('Cache-Control: no-store');
        View::render('ai/index', ['title' => 'Assistente Inteligente', 'active' => 'assistente']);
    }

    public function interpret(): void
    {
        header('Content-Type: application/json; charset=UTF-8');
        header('Cache-Control: no-store');
        try {
            if (!Session::validToken($_SERVER['HTTP_X_CSRF_TOKEN'] ?? null)) {
                throw new AIException('csrf', 'Sua sessão expirou. Reabra a página e tente novamente.', 403);
            }
            if (strtolower(trim(explode(';', $_SERVER['CONTENT_TYPE'] ?? '')[0])) !== 'application/json') {
                throw new AIException('content_type', 'Envie a mensagem no formato JSON.', 415);
            }
            $raw = file_get_contents('php://input', false, null, 0, 16001);
            if (strlen($raw) > 16000) {
                throw new AIException('request_too_large', 'A mensagem enviada ultrapassa o tamanho permitido.', 413);
            }
            try {
                $input = json_decode($raw, false, 8, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw new AIException('invalid_json', 'O corpo da requisição deve ser um JSON válido.', 400);
            }
            $message = AIRequestGuard::message($input instanceof \stdClass ? ($input->message ?? null) : null);
            AIRequestGuard::consume($_SESSION, $message, time());
            // Mantém o lock da sessão durante a chamada para serializar envios concorrentes.
            $model = new Product((new Database(require dirname(__DIR__, 2) . '/config/database.php'))->connection());
            $catalog = $model->aiCatalog();
            if ($catalog === []) {
                throw new AIException('empty_catalog', 'Cadastre e ative produtos antes de interpretar um pedido.', 422);
            }
            $interpretation = (new AIService(require dirname(__DIR__, 2) . '/config/ai.php'))->interpret($message, $catalog);
            $result = (new OrderPreviewValidator())->validate($interpretation, $catalog, $model->find(...));
            $this->respond($result, 200);
        } catch (AIException $exception) {
            if ($exception->httpStatus === 429) header('Retry-After: 60');
            $this->error($exception->errorCode, $exception->getMessage(), $exception->httpStatus);
        } catch (\Throwable $exception) {
            error_log('AI Order: falha na interpretação (' . $exception::class . ').');
            $this->error('internal_error', 'Não foi possível interpretar o pedido. Verifique a disponibilidade do sistema e tente novamente.', 500);
        }
    }

    private function error(string $code, string $message, int $status): void
    {
        $this->respond(['success' => false, 'error' => ['code' => $code, 'message' => $message],
            'items' => [], 'warnings' => [], 'notes' => null, 'requested_deadline' => null, 'estimated_total' => null], $status);
    }

    private function respond(array $data, int $status): void
    {
        http_response_code($status);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }
}
