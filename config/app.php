<?php

declare(strict_types=1);

$configuredUrl = rtrim((string) env('APP_URL', ''), '/');
$basePath = $configuredUrl !== ''
    ? (string) parse_url($configuredUrl, PHP_URL_PATH)
    : str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/index.php'));

return [
    'name' => env('APP_NAME', 'AI Order'),
    'environment' => env('APP_ENV', 'local'),
    'timezone' => env('APP_TIMEZONE', 'America/Manaus'),
    'base_path' => '/' . trim($basePath, '/.'),
];
