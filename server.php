<?php

declare(strict_types=1);

// Roteador exclusivo para o servidor local embutido do PHP.
$public = realpath(__DIR__ . '/public');
$path = rawurldecode(explode('?', $_SERVER['REQUEST_URI'], 2)[0]);
$file = realpath($public . $path);
if (str_starts_with($path, '/assets/') && $file !== false
    && str_starts_with($file, $public . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR)
    && is_file($file)) {
    return false;
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $public . '/index.php';
