<?php

declare(strict_types=1);

use App\Core\Router;
use App\Core\View;
use Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';
Dotenv::createImmutable(__DIR__)->safeLoad();
$config = require __DIR__ . '/config/app.php';
date_default_timezone_set($config['timezone']);
View::configure(__DIR__ . '/app/Views', $config);
$router = new Router();
require __DIR__ . '/routes/web.php';

return ['router' => $router, 'base_path' => $config['base_path']];
