<?php
declare(strict_types=1);

use App\Validation\ReviewedOrderValidator;
use App\Services\OrderPreviewSession;

require dirname(__DIR__) . '/vendor/autoload.php';
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks++; if (!$condition) throw new RuntimeException($message);
};
$products = [
    1 => ['name'=>'Camiseta', 'active'=>1, 'price'=>'39.90', 'stock'=>0, 'variants'=>[['id'=>11,'name'=>'Preta / M','stock'=>10],['id'=>12,'name'=>'Branca / G','stock'=>8]]],
    2 => ['name'=>'Caneca', 'active'=>1, 'price'=>'24.90', 'stock'=>20, 'variants'=>[]],
    3 => ['name'=>'Inativo', 'active'=>0, 'price'=>'1.00', 'stock'=>10, 'variants'=>[]],
];
$find = static function (int $id) use (&$products): ?array { return $products[$id] ?? null; };
$validator = new ReviewedOrderValidator();
$row = ['product_id'=>1,'variant_id'=>11,'quantity'=>3];
$input = ['items'=>[$row], 'notes'=>'Dobrar', 'requested_deadline'=>'sexta-feira'];
$validate = static fn (array $data): array => $validator->validate($data, $find);
$result = $validate($input);
$check($result['success'] && $result['estimated_total'] === '119.70' && $result['total_units'] === 3, 'Prévia válida em centavos.');
$check($result['requested_deadline'] === 'sexta-feira', 'Prazo relativo preservado.');
foreach ([0,-1,1.5,1.0,'1.0','1e2','0','-3','',null,true,[],10001,'99999999999999999999'] as $q) {
    $check(!$validate(['items'=>[[...$row,'quantity'=>$q]]])['success'], 'Quantidade inválida.');
}
foreach ([['product_id'=>999],['product_id'=>3],['product_id'=>[]],['variant_id'=>99],['variant_id'=>null],['variant_id'=>[]],['product_id'=>2],['quantity'=>11]] as $override) {
    $check(!$validate(['items'=>[[...$row,...$override]]])['success'], 'IDs, variações, atividade e estoque.');
}
$check($validate(['items'=>[[...$row,'variant_id'=>12]]])['success'], 'Troca de variação.');
$check($validate(['items'=>[['product_id'=>2,'variant_id'=>null,'quantity'=>'2']]])['estimated_total'] === '49.80', 'Correção por produto sem variação.');
$check(!$validate(['items'=>[[...$row,'quantity'=>6],[...$row,'quantity'=>5]]])['success'], 'Estoque somado de variações repetidas.');
$check(!$validate(['items'=>[['product_id'=>2,'quantity'=>11],['product_id'=>2,'quantity'=>10]]])['success'], 'Estoque somado de produtos sem variação.');
$check($validate(['items'=>[[...$row,'quantity'=>6],[...$row,'variant_id'=>12,'quantity'=>5]]])['success'], 'Estoques diferentes não são somados.');
foreach ([[],array_fill(0,101,$row),'invalid',null,['x'=>$row]] as $rows) $check(!$validate(['items'=>$rows])['success'], 'Lista inválida.');
$check($validate(['items'=>array_fill(0,100,['product_id'=>2,'quantity'=>1])])['validated_items'] !== [], 'Limite de 100 linhas aceito.');
$check(!$validate([...$input,'notes'=>str_repeat('á',2001)])['success'], 'Limite Unicode de observações.');
$check($validate([...$input,'notes'=>str_repeat('á',2000)])['success'], 'Limite exato de observações.');
$check(!$validate([...$input,'requested_deadline'=>str_repeat('x',201)])['success'], 'Limite de prazo.');
$check(!$validate([...$input,'notes'=>[]])['success'], 'Tipo das observações.');
$check($validate(['items'=>[[...$row,'unit_price'=>'0.01','subtotal'=>'0.03']], 'estimated_total'=>'0.03'])['estimated_total'] === '119.70', 'Preços do navegador ignorados.');
$products[1]['price'] = '40.01';
$check($validate($input)['estimated_total'] === '120.03', 'Preço reconsultado.');
$session = [];
OrderPreviewSession::invalidate($session);
OrderPreviewSession::confirm($session, $validate($input));
$check(isset($session['confirmed_order_preview']) && $session['confirmed_order_preview']['requires_revalidation'], 'Confirmação apenas na sessão.');
$check(!isset($session['confirmed_order_preview']['items'][0]['errors']), 'Sessão guarda somente dados necessários.');
OrderPreviewSession::invalidate($session);
$check(!isset($session['confirmed_order_preview']) && OrderPreviewSession::revision($session) === 2, 'Edição invalida e incrementa revisão.');
try { OrderPreviewSession::confirm($session, $validate(['items'=>[]])); $check(false,'Não confirmar inválido.'); }
catch (LogicException) { $check(true,'Inválido rejeitado.'); }
$products[2]['price'] = '99999999.99'; $products[2]['stock'] = 1000000;
$check($validate(['items'=>array_fill(0,100,['product_id'=>2,'quantity'=>10000])])['estimated_total'] === '99999999990000.00', 'Total máximo sem float.');
echo "OK: $checks verificações da Etapa 04 sem rede ou banco.\n";
