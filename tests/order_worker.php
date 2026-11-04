<?php
declare(strict_types=1);
require dirname(__DIR__) . '/vendor/autoload.php';
$task = json_decode(trim(fgets(STDIN)), true, 16, JSON_THROW_ON_ERROR);
if (!preg_match('/^ai_order_test_[a-f0-9]{12}$/D', $task['config']['database'])) exit(2);
$db = (new App\Core\Database($task['config']))->connection();
$db->exec('SET innodb_lock_wait_timeout = 15');
echo $db->query('SELECT CONNECTION_ID()')->fetchColumn() . "\n"; fflush(STDOUT);
if (trim(fgets(STDIN)) !== 'GO') exit(3);
try {
    $model = new App\Models\Order($db);
    $result = $task['action'] === 'cancel' ? $model->changeStatus($task['id'], 'cancelled') : $model->create($task['preview'], $task['preview']['idempotency_key']);
    echo json_encode(['success'=>true, 'id'=>$result['id']]) . "\n";
} catch (DomainException $e) {
    echo json_encode(['success'=>false, 'error'=>$e->getMessage()]) . "\n";
} catch (Throwable $e) {
    echo json_encode(['success'=>false, 'internal'=>$e::class]) . "\n";
    exit(1);
}
