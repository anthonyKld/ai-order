<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Router;
use App\Core\View;

require dirname(__DIR__) . '/vendor/autoload.php';
View::configure(dirname(__DIR__) . '/app/Views', ['name' => 'AI Order', 'base_path' => '/ai-order/public']);

$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) {
        throw new RuntimeException($message);
    }
};
$dispatch = static function (Router $router, string $method, string $uri, string $base = ''): array {
    http_response_code(200);
    ob_start();
    $router->dispatch($method, $uri, $base);
    return [http_response_code(), ob_get_clean()];
};

$router = new Router();
$router->get('/probe', static function (): void { echo 'get-ok'; });
$router->post('/probe', static function (): void { echo 'post-ok'; });
$router->get('/only-get', static function (): void { echo 'get-only'; });
$check($dispatch($router, 'GET', '/probe?filter=1') === [200, 'get-ok'], 'GET deve ignorar a query string.');
$check($dispatch($router, 'POST', '/probe') === [200, 'post-ok'], 'POST deve executar seu próprio handler.');
$check($dispatch($router, 'GET', '/ai-order/public/probe/', '/ai-order/public') === [200, 'get-ok'], 'Subdiretórios e barra final devem funcionar.');
$check($dispatch($router, 'GET', '/ai-order/publicity/probe', '/ai-order/public')[0] === 404, 'Base deve respeitar limites de segmento.');
$check($dispatch($router, 'GET', '/missing')[0] === 404, 'Rota desconhecida deve retornar 404.');
$check($dispatch($router, 'POST', '/only-get')[0] === 405, 'Método não registrado deve retornar 405.');
$check($dispatch($router, 'DELETE', '/probe')[0] === 405, 'DELETE não pode executar handlers GET/POST.');
$check(e('<script>"&\'</script>') === '&lt;script&gt;&quot;&amp;&#039;&lt;/script&gt;', 'HTML e atributos devem ser escapados.');
$check(url('produtos') === '/ai-order/public/produtos', 'URL deve preservar subdiretório.');
View::configure(dirname(__DIR__) . '/app/Views', ['name' => '<script>alert(1)</script>', 'base_path' => '/']);
$check(url('produtos') === '/produtos', 'URL deve funcionar na raiz.');
$router = new Router();
require dirname(__DIR__) . '/routes/web.php';
foreach (['/', '/produtos', '/pedidos', '/assistente'] as $path) {
    [$status, $html] = $dispatch($router, 'GET', $path);
    $check($status === 200 && str_contains($html, '<html lang="pt-BR">'), 'Página deve renderizar: ' . $path);
    $check(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Nome configurável deve ser escapado.');
}
$database = new Database(['host' => 'localhost;dbname=invalid', 'port' => '3306', 'database' => 'test', 'username' => '', 'password' => 'test-secret']);
try {
    $database->connection();
    $check(false, 'DSN inválido deve ser rejeitado.');
} catch (RuntimeException $exception) {
    $check(!str_contains($exception->getMessage(), 'test-secret'), 'Erro não deve expor senha.');
}

echo "OK: {$checks} verificações concluídas.\n";
