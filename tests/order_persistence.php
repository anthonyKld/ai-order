<?php
declare(strict_types=1);

use App\Core\Session;
use App\Core\View;
use App\Models\Order;
use App\Services\OrderPreviewSession;
use App\Validation\ReviewedOrderValidator;

require dirname(__DIR__) . '/vendor/autoload.php';
$checks = 0;
$check = static function (bool $value, string $label) use (&$checks): void { if (!$value) throw new RuntimeException($label); $checks++; };
$product = ['name'=>'Caneca', 'active'=>1, 'price'=>'0.10', 'stock'=>10, 'variants'=>[]];
$result = (new ReviewedOrderValidator())->validate(['items'=>[['product_id'=>1,'quantity'=>3]]], fn () => $product);
$session = [];
OrderPreviewSession::confirm($session, $result);
$preview = $session['confirmed_order_preview'];
$check((bool) preg_match('/^[a-f0-9]{64}$/D', $preview['idempotency_key']), 'Chave criptográfica gerada no servidor.');
$check($preview['items'][0]['product_name'] === 'Caneca' && $preview['items'][0]['variant'] === null, 'Snapshot de nomes.');
$check($preview['items'][0]['subtotal'] === '0.30', 'Cálculo decimal exato.');
OrderPreviewSession::confirm($session, $result);
$check($session['confirmed_order_preview']['idempotency_key'] !== $preview['idempotency_key'], 'Cada confirmação tem chave própria.');
OrderPreviewSession::invalidate($session);
$check(!isset($session['confirmed_order_preview']), 'Edição revoga chave e confirmação.');
View::configure(dirname(__DIR__) . '/app/Views', ['name'=>'AI Order','base_path'=>'']);
$router = new App\Core\Router();
require dirname(__DIR__) . '/routes/web.php';
$request = static function (string $method, string $uri) use ($router): array {
    http_response_code(200); ob_start(); $router->dispatch($method, $uri, ''); return [http_response_code(), ob_get_clean()];
};
foreach (['/api/orders/save', '/pedidos/status'] as $uri) {
    $_POST = []; unset($_SERVER['HTTP_X_CSRF_TOKEN']);
    $check($request('POST', $uri)[0] === 403, 'CSRF ausente.');
    $check($request('GET', $uri)[0] === 405, 'GET não altera pedidos.');
}
$token = Session::token();
foreach (['0', '-1', '1e2', '1.5', [], '4294967296'] as $id) {
    $_POST = ['_token'=>$token, 'id'=>$id, 'status'=>'processing'];
    $check($request('POST', '/pedidos/status')[0] === 422, 'ID inválido antes de acessar banco.');
}
$_POST = ['_token'=>$token,'id'=>'1','status'=>'cancelled'];
$check($request('POST', '/pedidos/status')[0] === 422, 'Cancelamento exige confirmação explícita.');
$_POST['status'] = 'unknown';
$check($request('POST', '/pedidos/status')[0] === 422, 'Status inválido.');
$check(Order::TRANSITIONS['completed'] === [] && Order::TRANSITIONS['cancelled'] === [], 'Estados terminais.');
$order = ['id'=>1,'order_number'=>'<script>x</script>','status'=>'pending','created_at'=>'2026-11-04 10:00:00','cancelled_at'=>null,
    'total'=>'0.30','total_items'=>3,'notes'=>'<img src=x onerror=alert(1)>','requested_deadline'=>'<script>y</script>',
    'items'=>[['product_name'=>'<svg onload=alert(1)>','variant_name'=>'<b>M</b>','quantity'=>3,'unit_price'=>'0.10','subtotal'=>'0.30']]];
ob_start(); View::render('orders/show', ['title'=>'Pedido','active'=>'pedidos','order'=>$order,'flash'=>null]); $html = ob_get_clean();
$check(!str_contains($html, '<img src=x') && !str_contains($html, '<svg onload=') && str_contains($html, '&lt;script&gt;'), 'Escape de snapshots e textos.');
echo "OK: $checks verificações unitárias da Etapa 05.\n";
