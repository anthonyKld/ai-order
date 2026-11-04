<?php
declare(strict_types=1);

use App\Core\Database;
use App\Models\Order;
use App\Models\Product;
use App\Services\OrderPreviewChanged;
use App\Services\OrderPreviewSession;
use App\Validation\ReviewedOrderValidator;

require dirname(__DIR__) . '/vendor/autoload.php';
$config = require __DIR__ . '/database_config.php';
$admin = (new Database($config))->connection();
$name = 'ai_order_test_' . bin2hex(random_bytes(6));
$config['database'] = $name;
$checks = 0; $server = null; $children = [];
$check = static function (bool $condition, string $label) use (&$checks): void {
    if (!$condition) throw new RuntimeException($label);
    $checks++; echo "PASS $checks: $label\n";
};
$reject = static function (callable $action, string $label, string $class = DomainException::class) use ($check): void {
    try { $action(); } catch (Throwable $e) { $check($e instanceof $class, "$label (" . $e::class . ')'); return; }
    $check(false, $label);
};
$admin->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
try {
    $db = (new Database($config))->connection();
    echo 'PHP ' . PHP_VERSION . ' / SQL ' . $db->query('SELECT VERSION()')->fetchColumn() . "\n";
    $db->exec(file_get_contents(dirname(__DIR__) . '/database/migrations/002_products.sql'));
    $db->exec("INSERT INTO products (name,price,stock,active) VALUES ('Camiseta',39.90,0,1),('Caneca',24.90,1000,1),('Inativo',1.00,10,0),('Escasso',2.00,5,1)");
    $db->exec("INSERT INTO product_variants (product_id,name,stock) VALUES (1,'Preta / M',1000),(1,'Branca / G',8)");
    $before = $db->query('SELECT * FROM products')->fetchAll();
    $migration = file_get_contents(dirname(__DIR__) . '/database/migrations/005_orders.sql');
    $db->exec($migration); $db->exec($migration);
    $check($before === $db->query('SELECT * FROM products')->fetchAll(), 'Migration reaplicável preserva catálogo');
    $orders = new Order($db); $products = new Product($db);
    $confirm = static function (array $items, array $extra = []) use ($products): array {
        $result = (new ReviewedOrderValidator())->validate(['items'=>$items, ...$extra], $products->find(...));
        $session = []; OrderPreviewSession::confirm($session, $result); return $session['confirmed_order_preview'];
    };
    $save = static fn (array $p): array => $orders->create($p, $p['idempotency_key']);
    $simple = ['product_id'=>2, 'variant_id'=>null, 'quantity'=>2];
    $variant = ['product_id'=>1, 'variant_id'=>1, 'quantity'=>3];
    $preview = $confirm([$variant, $simple], ['notes'=>'<script>danger()</script>','requested_deadline'=>'sexta-feira']);
    $created = $save($preview); $id = (int) $created['id']; $detail = $orders->find($id);
    $check($detail['total'] === '169.50' && (int) $detail['total_items'] === 5 && count($detail['items']) === 2, 'Pedido com múltiplos produtos e variações');
    $check($detail['items'][0]['unit_price'] === '39.90' && $detail['items'][0]['subtotal'] === '119.70' && $detail['items'][1]['subtotal'] === '49.80', 'Preços e subtotais históricos exatos');
    $check((int)$products->find(1)['variants'][0]['stock'] === 997 && (int)$products->find(2)['stock'] === 998 && (int)$products->find(1)['stock'] === 0, 'Baixa nas fontes corretas de estoque');
    $check($detail['notes'] === '<script>danger()</script>' && $detail['requested_deadline'] === 'sexta-feira', 'Textos preservados para escape na apresentação');
    $check($save($preview)['id'] === $created['id'] && $orders->create(null, $preview['idempotency_key'])['id'] === $created['id'], 'Idempotência com e sem prévia após sucesso');
    $check((int)$db->query('SELECT COUNT(*) FROM orders')->fetchColumn() === 1 && (int)$products->find(2)['stock'] === 998, 'Reenvios não duplicam pedido nem baixa');
    $other = $save($confirm([$simple]));
    $check($other['order_number'] !== $created['order_number'], 'Números públicos únicos');
    $db->exec("UPDATE products SET name='Novo nome', price=50.00 WHERE id=1");
    $db->exec("UPDATE product_variants SET name='Novo tamanho' WHERE id=1");
    $history = $orders->find($id);
    $check($history['items'][0]['product_name'] === 'Camiseta' && $history['items'][0]['variant_name'] === 'Preta / M' && $history['total'] === '169.50', 'Histórico independente do catálogo atual');
    $pending = $confirm([$variant]);
    $db->exec('UPDATE products SET price=51.00 WHERE id=1');
    try { $save($pending); $check(false, 'Preço mudou'); }
    catch (OrderPreviewChanged $e) { $check($e->result['estimated_total'] === '153.00' && !$e->result['confirmed'], 'Mudança de preço devolve nova prévia não confirmada'); }
    $pending = $confirm([$variant]);
    $db->exec("UPDATE product_variants SET name='Renomeada' WHERE id=1");
    $reject(fn () => $save($pending), 'Mudança de nome exige reconfirmação', OrderPreviewChanged::class);
    $check($save($confirm([$variant]))['total'] === '153.00', 'Nova confirmação aceita catálogo atualizado');
    $pending = $confirm([$simple]);
    $db->exec('UPDATE products SET stock=1 WHERE id=2');
    $reject(fn () => $save($pending), 'Estoque revalidado antes de salvar', OrderPreviewChanged::class);
    $check((int)$products->find(2)['stock'] === 1, 'Estoque insuficiente não fica negativo');
    $db->exec('UPDATE products SET stock=1000 WHERE id=2');
    $reject(fn () => $orders->create(null, bin2hex(random_bytes(32))), 'Prévia não confirmada rejeitada');
    $reject(fn () => $orders->create($pending, 'x'), 'Chave inválida rejeitada');
    foreach ([[], [['product_id'=>999,'quantity'=>1]], [['product_id'=>3,'quantity'=>1]], [[...$simple,'variant_id'=>1]], [[...$variant,'variant_id'=>null]], [[...$simple,'quantity'=>-1]], [[...$simple,'quantity'=>0]], [[...$simple,'quantity'=>1.5]], [[...$simple,'product_id'=>[]]]] as $items) {
        $invalid = [...$confirm([$simple]), 'items'=>$items];
        $reject(fn () => $save($invalid), 'Pedido inválido rejeitado: ' . json_encode($items));
    }
    $pending = $confirm([$simple]);
    $db->exec('UPDATE products SET active=0 WHERE id=2');
    $reject(fn () => $save($pending), 'Inativação posterior rejeitada');
    $db->exec('UPDATE products SET active=1 WHERE id=2');
    $duplicate = $confirm([$simple, $simple]);
    $db->exec('UPDATE products SET stock=3 WHERE id=2');
    $reject(fn () => $save($duplicate), 'Quantidade agregada de linhas duplicadas rejeita excesso');
    $db->exec('UPDATE products SET stock=1000 WHERE id=2');
    $dupOrder = $save($confirm([$simple, $simple]));
    $check((int)$products->find(2)['stock'] === 996 && (int)$dupOrder['total_items'] === 4, 'Linhas duplicadas válidas baixam quantidade somada');
    $orders->changeStatus((int)$dupOrder['id'], 'cancelled');
    $check((int)$products->find(2)['stock'] === 1000, 'Cancelamento agrega reposição de linhas duplicadas');

    $snapshot = static fn (): array => [
        $db->query('SELECT * FROM orders ORDER BY id')->fetchAll(), $db->query('SELECT * FROM order_items ORDER BY id')->fetchAll(),
        $db->query('SELECT * FROM products ORDER BY id')->fetchAll(), $db->query('SELECT * FROM product_variants ORDER BY id')->fetchAll(),
    ];
    foreach ([
        "BEFORE INSERT ON orders FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test create'",
        "BEFORE INSERT ON order_items FOR EACH ROW BEGIN IF NEW.product_id=2 THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test item'; END IF; END",
        "BEFORE UPDATE ON products FOR EACH ROW BEGIN IF NEW.stock < OLD.stock THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test stock'; END IF; END",
    ] as $index => $trigger) {
        $pending = $confirm([$variant, $simple]); $before = $snapshot();
        $db->exec('CREATE TRIGGER fail_order ' . $trigger);
        try { $reject(fn () => $save($pending), 'Falha real injetada no estágio ' . $index, PDOException::class); }
        finally { $db->exec('DROP TRIGGER fail_order'); }
        $check($before === $snapshot(), 'ROLLBACK integral de pedido, itens e estoques no estágio ' . $index);
    }
    $reject(fn () => $products->delete(1), 'Produto vinculado não pode ser excluído');
    $reject(fn () => $db->exec('DELETE FROM product_variants WHERE id=1'), 'FK preserva variação vendida', PDOException::class);
    $reject(fn () => $db->exec("DELETE FROM orders WHERE id=$id"), 'FK preserva pedido com itens', PDOException::class);
    $reject(fn () => $db->exec("INSERT INTO order_items (order_id,product_id,product_name,quantity,unit_price,subtotal) VALUES (999999,2,'X',1,1,1)"), 'FK rejeita item órfão', PDOException::class);
    $reject(fn () => $db->exec('UPDATE products SET stock=-1 WHERE id=2'), 'CHECK impede estoque negativo', PDOException::class);
    $data = $products->find(2); $data['variants'] = [['id'=>null,'name'=>'Nova','stock'=>1]];
    $reject(fn () => $products->save($data, 2), 'Histórico impede troca da fonte de estoque');
    $check($products->find(2)['variants'] === [], 'Mudança inválida de catálogo sofre rollback');

    $before = $snapshot();
    $db->exec("CREATE TRIGGER fail_cancel BEFORE UPDATE ON products FOR EACH ROW BEGIN IF NEW.stock > OLD.stock THEN SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='test cancel'; END IF; END");
    try { $reject(fn () => $orders->changeStatus($id, 'cancelled'), 'Falha real na reposição', PDOException::class); }
    finally { $db->exec('DROP TRIGGER fail_cancel'); }
    $check($before === $snapshot(), 'ROLLBACK do cancelamento preserva status e ambos os estoques');
    $stockBefore = [(int)$products->find(1)['variants'][0]['stock'], (int)$products->find(2)['stock']];
    $orders->changeStatus($id, 'processing');
    $cancelled = $orders->changeStatus($id, 'cancelled');
    $orders->changeStatus($id, 'cancelled');
    $check($cancelled['cancelled_at'] !== null && $cancelled['status'] === 'cancelled', 'Cancelamento registra status e data');
    $check((int)$products->find(1)['variants'][0]['stock'] === $stockBefore[0]+3 && (int)$products->find(2)['stock'] === $stockBefore[1]+2, 'Reposição de produto e variação exatamente uma vez');
    foreach (array_keys(Order::STATUSES) as $from) foreach (array_keys(Order::STATUSES) as $to) {
        $entry = $save($confirm([$simple])); $entryId = (int)$entry['id'];
        if (in_array($from, ['processing','completed'], true)) $orders->changeStatus($entryId,'processing');
        if ($from !== 'pending' && $from !== 'processing') $orders->changeStatus($entryId,$from);
        if ($from === $to || in_array($to, Order::TRANSITIONS[$from], true)) {
            $check($orders->changeStatus($entryId,$to)['status'] === $to, "Transição $from -> $to aceita/idempotente");
        } else $reject(fn () => $orders->changeStatus($entryId,$to), "Transição $from -> $to rejeitada");
    }
    $check($orders->find(999999) === null, 'Detalhe inexistente');
    $list = $orders->paginate('', '', 1); $page2 = $orders->paginate('', '', 2);
    $check(count($list['orders']) === 10 && $page2['page'] === 2 && !array_intersect(array_column($list['orders'],'id'), array_column($page2['orders'],'id')), 'Paginação SQL sem sobreposição');
    $check((int)$list['orders'][0]['id'] > (int)$list['orders'][1]['id'], 'Ordenação estável mais recente primeiro');
    $check($orders->paginate($created['order_number'],'',1)['total'] === 1, 'Pesquisa por número');
    $check($orders->paginate("%' OR 1=1 --",'',1)['total'] === 0, 'Pesquisa preparada escapa SQL e curingas');
    $filtered = $orders->paginate('', 'cancelled', 1);
    $check($filtered['total'] > 0 && array_unique(array_column($filtered['orders'],'status')) === ['cancelled'], 'Filtro por status');
    $check($orders->paginate('inexistente','',1)['total'] === 0, 'Lista vazia');
    $db->exec("INSERT INTO products (name,price,stock,active) VALUES ('Limite monetário',99999999.99,1000000,1)");
    $limitId = (int)$db->lastInsertId();
    $maximum = $save($confirm(array_fill(0, 100, ['product_id'=>$limitId,'variant_id'=>null,'quantity'=>10000])));
    $check($maximum['total'] === '99999999990000.00' && (int)$products->find($limitId)['stock'] === 0, 'DECIMAL preserva total máximo de 100 linhas sem truncamento');
    $orders->changeStatus((int)$maximum['id'], 'cancelled');
    $check((int)$products->find($limitId)['stock'] === 1000000, 'Reposição do total máximo de unidades');

    // Dois processos PHP/PDO independentes, ambos comprovadamente bloqueados antes da liberação.
    $parallel = static function (array $tasks) use ($config, $db, $admin, &$children, $check): array {
        $db->beginTransaction(); $db->query('SELECT * FROM products WHERE id=4 FOR UPDATE')->fetchAll();
        $workers = []; $ids = [];
        try {
            foreach ($tasks as $task) {
                $process = proc_open([PHP_BINARY, __DIR__ . '/order_worker.php'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
                if (!is_resource($process)) throw new RuntimeException('Worker indisponível');
                $children[] = $process;
                fwrite($pipes[0], json_encode(['config'=>$config,...$task]) . "\n"); fflush($pipes[0]);
                $ids[] = (int)trim(fgets($pipes[1]));
                $workers[] = [$process,$pipes];
            }
            foreach ($workers as [, $pipes]) { fwrite($pipes[0], "GO\n"); fflush($pipes[0]); }
            $waiting = 0; $deadline = microtime(true)+10;
            do {
                $waiting = (int)$admin->query('SELECT COUNT(*) FROM information_schema.innodb_trx WHERE trx_state=\'LOCK WAIT\' AND trx_mysql_thread_id IN (' . implode(',', $ids) . ')')->fetchColumn();
                if ($waiting === 2) break;
                usleep(100000);
            } while (microtime(true) < $deadline);
            $check($waiting === 2, 'Duas conexões simultâneas observadas em LOCK WAIT');
            $db->commit();
            $results = [];
            foreach ($workers as [$process,$pipes]) {
                fclose($pipes[0]); $out = stream_get_contents($pipes[1]); $error = stream_get_contents($pipes[2]);
                fclose($pipes[1]); fclose($pipes[2]);
                $code = proc_close($process);
                if ($code !== 0) throw new RuntimeException('Worker falhou: ' . $error . $out);
                $results[] = json_decode(trim($out), true, 16, JSON_THROW_ON_ERROR);
            }
            return $results;
        } finally { if ($db->inTransaction()) $db->rollBack(); }
    };
    $scarce = ['product_id'=>4,'variant_id'=>null,'quantity'=>4];
    $results = $parallel([['action'=>'create','preview'=>$confirm([$scarce])],['action'=>'create','preview'=>$confirm([$scarce])]]);
    $check(count(array_filter($results, fn ($r) => $r['success'])) === 1 && (int)$products->find(4)['stock'] === 1, 'Concorrência obrigatória: estoque 5, dois pedidos de 4, somente um salva');
    $db->exec('UPDATE products SET stock=5 WHERE id=4');
    $pending = $confirm([$scarce]);
    $results = $parallel([['action'=>'create','preview'=>$pending],['action'=>'create','preview'=>$pending]]);
    $check($results[0]['success'] && $results[1]['success'] && $results[0]['id'] === $results[1]['id'] && (int)$products->find(4)['stock'] === 1, 'Idempotência concorrente retorna o mesmo pedido e baixa uma vez');
    $results = $parallel([['action'=>'cancel','id'=>$results[0]['id']],['action'=>'cancel','id'=>$results[0]['id']]]);
    $check($results[0]['success'] && $results[1]['success'] && (int)$products->find(4)['stock'] === 5, 'Cancelamentos concorrentes repõem uma única vez');

    if (in_array('--browser', $argv, true)) {
        $socket = stream_socket_server('tcp://127.0.0.1:0', $errno, $error);
        if (!$socket) throw new RuntimeException('Porta de teste indisponível.');
        $port = (int)substr(strrchr(stream_socket_get_name($socket, false), ':'), 1); fclose($socket);
        $base = "http://127.0.0.1:$port";
        $sessionDir = sys_get_temp_dir() . '/ai_order_sessions_' . bin2hex(random_bytes(8));
        if (!mkdir($sessionDir, 0700)) throw new RuntimeException('Diretório de sessão indisponível.');
        if (!is_dir(dirname(__DIR__) . '/.qa')) mkdir(dirname(__DIR__) . '/.qa');
        $environment = [...getenv(), 'APP_URL'=>$base,'DB_DATABASE'=>$name,'DB_HOST'=>$config['host'],'DB_PORT'=>(string)$config['port'],'DB_USERNAME'=>$config['username'],'DB_PASSWORD'=>$config['password']];
        $log = tempnam(sys_get_temp_dir(), 'ai_order_server_');
        // Aspas no valor INI preservam o til (~) dos caminhos curtos do Windows.
        $server = proc_open([PHP_BINARY,'-d','variables_order=EGPCS','-d','session.save_path="' . str_replace('\\', '/', $sessionDir) . '"','-S',"127.0.0.1:$port",'-t','public','server.php'],
            [0=>['pipe','r'],1=>['file',$log,'a'],2=>['file',$log,'a']],$pipes,dirname(__DIR__),$environment);
        if (!is_resource($server)) throw new RuntimeException('Servidor indisponível');
        fclose($pipes[0]);
        for ($i=0;$i<50;$i++) { $socket = @fsockopen('127.0.0.1',$port,$errno,$error,0.1); if ($socket) { fclose($socket); break; } usleep(100000); }
        $browser = proc_open(['node','tests/order_persistence_browser.cjs'],[0=>STDIN,1=>STDOUT,2=>STDERR],$pipes,dirname(__DIR__),[...$environment,'AI_ORDER_TEST_URL'=>$base,'AI_ORDER_TEST_PHP'=>PHP_BINARY]);
        $check(is_resource($browser) && proc_close($browser) === 0, 'Fluxo HTTP e navegador completo');
    }
    echo "OK: $checks verificações de integração da Etapa 05.\n";
} finally {
    foreach ($children as $child) if (is_resource($child)) { proc_terminate($child); proc_close($child); }
    if (is_resource($server)) { proc_terminate($server); proc_close($server); }
    if (isset($log) && is_file($log)) unlink($log);
    if (isset($sessionDir) && is_dir($sessionDir)) {
        foreach (glob($sessionDir . '/sess_*') as $file) unlink($file);
        rmdir($sessionDir);
    }
    $db = null;
    $admin->exec("DROP DATABASE `$name`");
    echo "Banco descartável removido.\n";
}
