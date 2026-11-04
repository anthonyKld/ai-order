<?php
declare(strict_types=1);

// Independente do .env e do banco de desenvolvimento. O runner escolhe o schema aleatório.
Dotenv\Dotenv::createImmutable(dirname(__DIR__), '.env.test')->safeLoad();
return [
    'host' => env('TEST_DB_HOST', '127.0.0.1'),
    'port' => env('TEST_DB_PORT', '3306'),
    'username' => env('TEST_DB_USERNAME', 'root'),
    'password' => env('TEST_DB_PASSWORD', ''),
    'database' => 'information_schema',
];
