<?php

declare(strict_types=1);

use App\Core\Database;
use App\Models\Product;
use App\Services\AIException;
use App\Services\AIService;
use App\Validation\OrderPreviewValidator;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/database.php';
$admin = (new Database($config))->connection();
$databaseName = 'ai_order_test_' . bin2hex(random_bytes(6));
$checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void {
    $checks++;
    if (!$condition) throw new RuntimeException($message);
};
$admin->exec("CREATE DATABASE `$databaseName` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $db = (new Database([...$config, 'database'=>$databaseName]))->connection();
    $db->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/002_products.sql'));
    $model = new Product($db);
    $check($model->aiCatalog() === [], 'Catálogo vazio.');
    $db->exec("INSERT INTO products (name,price,stock,active) VALUES ('Camiseta',39.90,0,1),('Caneca',24.90,20,1),('Boné',49.90,10,0)");
    $db->exec("INSERT INTO product_variants (product_id,name,stock) VALUES (1,'Preta / M',5),(1,'Branca / G',8)");
    $catalog = $model->aiCatalog();
    $check(count($catalog) === 2 && count($catalog[0]['variants']) === 2, 'Somente produtos ativos e suas variações.');
    $check(array_keys($catalog[0]) === ['id','name','variants'] && array_keys($catalog[0]['variants'][0]) === ['id','name'], 'Contexto mínimo.');
    $item = ['product_id'=>1,'product_name'=>'camisetas','quantity'=>3,'variant_id'=>1,'variant_name'=>'pretas M',
        'color'=>'preta','size'=>'M','characteristics'=>null,'issues'=>[]];
    $snapshot = $db->query('SELECT * FROM products ORDER BY id')->fetchAll();
    $variantSnapshot = $db->query('SELECT * FROM product_variants ORDER BY id')->fetchAll();
    // TODO(Gemini): adaptar este envelope simulado ao selecionar o novo provedor.
    // Manter MySQL isolado e conferir preços/estoque sem chamar uma API real.
    $service = new AIService(['key'=>'fake','model'=>'fake'], static fn ($payload) => ['status'=>200,'body'=>json_encode([
        'status'=>'completed','output'=>[['type'=>'message','content'=>[['type'=>'output_text','text'=>json_encode([
            'items'=>[$item], 'notes'=>null, 'requested_deadline'=>'sexta-feira',
        ])]]]],
    ])]);
    $decoded = $service->interpret('Quero três camisetas pretas M para sexta-feira.', $catalog);
    $validator = new OrderPreviewValidator();
    $preview = $validator->validate($decoded, $catalog, $model->find(...));
    $check($preview['estimated_total'] === '119.70' && $preview['items'][0]['ready'], 'Serviço simulado + consulta real + cálculo.');
    $preview = $validator->validate([...$decoded,'items'=>[$item,$item]], $catalog, $model->find(...));
    $check($preview['estimated_total'] === '0.00' && $preview['items'][0]['status'] === 'insufficient_stock', 'Estoque somado no MySQL.');
    $check($snapshot === $db->query('SELECT * FROM products ORDER BY id')->fetchAll()
        && $variantSnapshot === $db->query('SELECT * FROM product_variants ORDER BY id')->fetchAll(), 'Interpretação não escreve produtos nem estoque.');
    $check(count($db->query('SHOW TABLES')->fetchAll()) === 2, 'Nenhuma tabela de pedidos.');
    $db->exec('UPDATE products SET active=0 WHERE id=1');
    $preview = $validator->validate($decoded, $catalog, $model->find(...));
    $check($preview['items'][0]['status'] === 'not_found', 'Revalidação identifica inativação posterior.');
    $db->exec('UPDATE products SET active=1 WHERE id=1');
    $insert = $db->prepare('INSERT INTO products (name,price,stock,active) VALUES (?,1,1,1)');
    for ($i=0;$i<99;$i++) $insert->execute(['Produto '.$i]);
    try { $model->aiCatalog(); $check(false, 'Limite de produtos deveria falhar.'); }
    catch (AIException $e) { $check($e->errorCode === 'catalog_limit', 'Catálogo não pode ser truncado silenciosamente.'); }
    $db->exec('UPDATE products SET active=0 WHERE id > 3');
    $insert = $db->prepare('INSERT INTO product_variants (product_id,name,stock) VALUES (1,?,1)');
    for ($i=0;$i<499;$i++) $insert->execute(['Variação '.$i]);
    try { $model->aiCatalog(); $check(false, 'Limite de linhas deveria falhar.'); }
    catch (AIException $e) { $check($e->errorCode === 'catalog_limit', 'Limite de linhas com variações.'); }
    echo "OK: {$checks} verificações IA/MySQL em banco isolado, sem API real.\n";
} finally {
    $admin->exec("DROP DATABASE `$databaseName`");
}
