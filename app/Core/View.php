<?php

declare(strict_types=1);

namespace App\Core;

final class View
{
    private static string $directory;
    private static array $config = [];

    public static function configure(string $directory, array $config): void
    {
        self::$directory = $directory;
        self::$config = $config;
    }

    public static function config(string $key): string
    {
        return (string) (self::$config[$key] ?? '');
    }

    public static function render(string $template, array $data = []): void
    {
        // Templates são definidos pelo código, nunca por parâmetros da requisição.
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            require self::$directory . '/' . $template . '.php';
            $content = ob_get_clean();
        } catch (\Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }
        require self::$directory . '/layouts/app.php';
    }
}
