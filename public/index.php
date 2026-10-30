<?php

declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
ini_set('error_log', dirname(__DIR__) . '/storage/logs/php.log');
error_reporting(E_ALL);

header('Content-Type: text/html; charset=UTF-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; object-src 'none'; base-uri 'self'; frame-ancestors 'none'; form-action 'self'");

try {
    $app = require dirname(__DIR__) . '/bootstrap.php';
    $app['router']->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'], $app['base_path']);
} catch (Throwable $exception) {
    http_response_code(500);
    // Nunca registrar mensagens que possam conter DSN, senha ou chave de API.
    error_log('AI Order: falha interna (' . $exception::class . ').');
    require dirname(__DIR__) . '/app/Views/errors/500.php';
}
