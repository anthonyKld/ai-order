<?php

declare(strict_types=1);

use App\Core\Database;
use App\Core\Router;
use App\Core\View;
use App\Core\Session;
use App\Validation\ProductValidator;

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
// Pedidos agora depende de MySQL; sua renderização é coberta na integração isolada.
foreach (['/', '/produtos/novo', '/assistente'] as $path) {
    [$status, $html] = $dispatch($router, 'GET', $path);
    $check($status === 200 && str_contains($html, '<html lang="pt-BR">'), 'Página deve renderizar: ' . $path);
    $check(!str_contains($html, '<script>alert(1)</script>') && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;'), 'Nome configurável deve ser escapado.');
}
$validator = new ProductValidator();
$valid = ['name' => 'Camiseta', 'description' => '', 'price' => '39,90', 'stock' => '12', 'active' => '1'];
$result = $validator->validate($valid);
$check($result['errors'] === [] && $result['data']['price'] === '39.90' && $result['data']['stock'] === 12, 'Cadastro válido deve normalizar decimal sem float.');
foreach (['', '-1', '1e3', 'NaN', '3.999', '100000000', '1.000,00', ['1']] as $price) {
    $check(isset($validator->validate([...$valid, 'price' => $price])['errors']['price']), 'Preço inválido deve ser rejeitado.');
}
foreach (['0', '0,00', '99999999.99', '01.5'] as $price) {
    $check($validator->validate([...$valid, 'price' => $price])['errors'] === [], 'Preço válido deve ser aceito.');
}
foreach (['', '   ', [], str_repeat('a', 151)] as $name) {
    $check(isset($validator->validate([...$valid, 'name' => $name])['errors']['name']), 'Nome inválido deve ser rejeitado.');
}
foreach (['-1', '2.5', '1e3', '2147483648', [], ''] as $stock) {
    $check(isset($validator->validate([...$valid, 'stock' => $stock])['errors']['stock']), 'Estoque inválido deve ser rejeitado.');
}
$variants = [['name' => 'Preta / P', 'stock' => '10'], ['name' => 'Preta / M', 'stock' => '15'], ['name' => 'Branca / G', 'stock' => '8']];
$result = $validator->validate([...$valid, 'variants' => $variants]);
$check($result['errors'] === [] && $result['data']['stock'] === 0 && array_sum(array_column($result['data']['variants'], 'stock')) === 33, 'Variações devem ser a única fonte de estoque.');
$check($validator->validate([...$valid, 'variants' => [...$variants, ['name' => ' preta / p ', 'stock' => '0']]])['errors'] !== [], 'Duplicatas devem ignorar caixa e espaços externos.');
foreach (['invalid', [[]], [['name' => 'M', 'stock' => '-1']], array_fill(0, 101, $variants[0]), [['name' => 'M', 'stock' => '2147483647'], ['name' => 'G', 'stock' => '1']]] as $rows) {
    $check($validator->validate([...$valid, 'variants' => $rows])['errors'] !== [], 'Variações malformadas ou limites excedidos devem ser rejeitados.');
}
$check($validator->validate([...$valid, 'active' => '2'])['errors'] !== [], 'Status deve ser validado.');
$check(money('99999999.99') === 'R$ 99.999.999,99', 'Formatação monetária deve preservar centavos.');
$token = Session::token();
$check(strlen($token) === 64 && Session::validToken($token), 'Token CSRF deve ser válido na sessão.');
foreach ([null, '', 'invalid', []] as $tokenInput) {
    $check(!Session::validToken($tokenInput), 'Token CSRF inválido deve ser rejeitado.');
    foreach (['/produtos/criar', '/produtos/atualizar', '/produtos/excluir'] as $path) {
        $_POST = ['_token' => $tokenInput];
        $check($dispatch($router, 'POST', $path)[0] === 403, 'Escrita sem CSRF deve falhar antes de acessar o banco.');
    }
}
$_POST = [...$valid, '_token' => $token, 'name' => ''];
$check($dispatch($router, 'POST', '/produtos/criar')[0] === 422, 'POST inválido deve devolver formulário com 422.');
$_POST = ['_token' => $token, 'id' => '1'];
$check($dispatch($router, 'POST', '/produtos/excluir')[0] === 422, 'Exclusão exige confirmação.');
foreach (['/produtos/criar', '/produtos/atualizar', '/produtos/excluir'] as $path) {
    $check($dispatch($router, 'GET', $path)[0] === 405, 'GET não pode gravar ou excluir.');
}
$_POST = [];
$database = new Database(['host' => 'localhost;dbname=invalid', 'port' => '3306', 'database' => 'test', 'username' => '', 'password' => 'test-secret']);
try {
    $database->connection();
    $check(false, 'DSN inválido deve ser rejeitado.');
} catch (RuntimeException $exception) {
    $check(!str_contains($exception->getMessage(), 'test-secret'), 'Erro não deve expor senha.');
}

echo "OK: {$checks} verificações concluídas.\n";
