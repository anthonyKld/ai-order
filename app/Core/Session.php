<?php

declare(strict_types=1);

namespace App\Core;

final class Session
{
    public static function start(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start([
                'use_strict_mode' => 1,
                'use_only_cookies' => 1,
                'cookie_httponly' => 1,
                'cookie_samesite' => 'Lax',
                'cookie_secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            ]);
        }
    }

    public static function token(): string
    {
        self::start();
        return $_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
    }

    public static function validToken(mixed $token): bool
    {
        self::start();
        return is_string($token) && isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    public static function flash(string $message): void
    {
        self::start();
        $_SESSION['flash'] = $message;
    }

    public static function takeFlash(): ?string
    {
        self::start();
        $message = $_SESSION['flash'] ?? null;
        unset($_SESSION['flash']);
        return $message;
    }
}
