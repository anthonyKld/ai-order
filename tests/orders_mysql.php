<?php
declare(strict_types=1);

use App\Core\Database;
use App\Models\Product;
use App\Validation\ReviewedOrderValidator;
use Dotenv\Dotenv;

require dirname(__DIR__) . '/vendor/autoload.php';
Dotenv::createImmutable(dirname(__DIR__))->safeLoad();
$config = require dirname(__DIR__) . '/config/database.php';
$admin = (new Database($config))->connection();
$name = 'ai_order_test_' . bin2hex(random_bytes(6));
$server = null; $checks = 0;
$check = static function (bool $condition, string $message) use (&$checks): void { $checks++; if (!$condition) throw new RuntimeException($message); };
$admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $db = (new Database([...$config,'database'=>$name]))->connection();
    $db->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/002_products.sql'));
    $db->exec("INSERT INTO products (name,price,stock,active) VALUES ('Camiseta',39.90,0,1),('Caneca',24.90,20,1),('Inativo',1.00,10,0)");
    $db->exec("INSERT INTO product_variants (product_id,name,stock) VALUES (1,'Preta / M',10),(1,'Branca / G',8)");
    $model = new Product($db); $validator = new ReviewedOrderValidator();
    $input = ['items'=>[['product_id'=>1,'variant_id'=>1,'quantity'=>3]]];
    $check($validator->validate($input,$model->find(...))['estimated_total'] === '119.70','Preço real.');
    $db->exec('UPDATE products SET price=40.01 WHERE id=1');
    $check($validator->validate($input,$model->find(...))['estimated_total'] === '120.03','Preço alterado no MySQL.');
    $db->exec('UPDATE products SET active=0 WHERE id=1');
    $check(!$validator->validate($input,$model->find(...))['success'],'Inativação posterior.');
    $db->exec('UPDATE products SET active=1,price=39.90 WHERE id=1');
    $snapshot = $db->query('SELECT * FROM products ORDER BY id')->fetchAll();
    $variants = $db->query('SELECT * FROM product_variants ORDER BY id')->fetchAll();
    if (in_array('--browser', $argv, true)) {
        $port = 8094;
        $environment = [...getenv(), 'APP_URL'=>"http://127.0.0.1:$port", 'DB_DATABASE'=>$name,
            'DB_HOST'=>(string)$config['host'],'DB_PORT'=>(string)$config['port'],'DB_USERNAME'=>$config['username'],'DB_PASSWORD'=>$config['password']];
        $server = proc_open([PHP_BINARY,'-d','variables_order=EGPCS','-S',"127.0.0.1:$port",'-t','public','server.php'],
            [0=>['pipe','r'],1=>['file',dirname(__DIR__).'/.qa/orders-server.log','a'],2=>['file',dirname(__DIR__).'/.qa/orders-server.log','a']],
            $pipes, dirname(__DIR__), $environment);
        if (!is_resource($server)) throw new RuntimeException('Não foi possível iniciar PHP.');
        fclose($pipes[0]);
        $ready = false;
        for ($i=0;$i<30;$i++) { $socket = @fsockopen('127.0.0.1',$port,$errno,$error,0.2); if ($socket) { fclose($socket); $ready=true; break; } usleep(100000); }
        if (!$ready) throw new RuntimeException('Servidor de teste indisponível.');
        foreach (['tests/ai_browser.cjs', 'tests/orders_browser.cjs'] as $test) {
            $browser = proc_open(['node',$test], [0=>STDIN,1=>STDOUT,2=>STDERR], $pipes, dirname(__DIR__), [...$environment,'AI_ORDER_TEST_URL'=>"http://127.0.0.1:$port"]);
            $check(is_resource($browser) && proc_close($browser) === 0,'Testes HTTP e navegador: ' . $test);
        }
    }
    $check($snapshot === $db->query('SELECT * FROM products ORDER BY id')->fetchAll(), 'Nenhuma escrita em produtos.');
    $check($variants === $db->query('SELECT * FROM product_variants ORDER BY id')->fetchAll(), 'Nenhuma baixa ou reserva de estoque.');
    $check(count($db->query('SHOW TABLES')->fetchAll()) === 2,'Nenhuma tabela de pedidos.');
    echo "OK: $checks verificações MySQL da Etapa 04 em banco isolado.\n";
} finally {
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    $admin->exec("DROP DATABASE `$name`");
}
