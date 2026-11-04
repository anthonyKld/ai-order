<?php
declare(strict_types=1);
// Usado apenas pelo processo CLI do teste, nunca servido como endpoint público.
require dirname(__DIR__) . '/vendor/autoload.php';
$name = getenv('DB_DATABASE');
if (!is_string($name) || !preg_match('/^ai_order_test_[a-f0-9]{12}$/D', $name)) exit(2);
$db = (new App\Core\Database(['host'=>getenv('DB_HOST'),'port'=>getenv('DB_PORT'),'database'=>$name,'username'=>getenv('DB_USERNAME'),'password'=>(string)getenv('DB_PASSWORD')]))->connection();
if (($argv[1] ?? '') === 'fail') {
    $db->exec("CREATE TRIGGER browser_failure BEFORE INSERT ON orders FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='credential-sentinel-do-not-expose'");
} elseif (($argv[1] ?? '') === 'recover') {
    $db->exec('DROP TRIGGER browser_failure');
} else exit(3);
