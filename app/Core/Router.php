<?php

declare(strict_types=1);

namespace App\Core;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->routes['GET'][$this->normalize($path)] = $handler;
    }

    public function post(string $path, callable $handler): void
    {
        $this->routes['POST'][$this->normalize($path)] = $handler;
    }

    public function dispatch(string $method, string $uri, string $basePath = ''): void
    {
        $path = rawurldecode(explode('?', $uri, 2)[0]);
        $base = rtrim($basePath, '/');
        if ($base !== '' && $path !== $base && !str_starts_with($path, $base . '/')) {
            $this->error(404);
            return;
        }
        $path = $this->normalize(substr($path, strlen($base)));
        $handler = $this->routes[strtoupper($method)][$path] ?? null;
        if ($handler !== null) {
            $handler();
            return;
        }
        $allowed = [];
        foreach ($this->routes as $verb => $routes) {
            if (isset($routes[$path])) {
                $allowed[] = $verb;
            }
        }
        if ($allowed !== []) {
            header('Allow: ' . implode(', ', $allowed));
            $this->error(405);
            return;
        }
        $this->error(404);
    }

    private function normalize(string $path): string
    {
        return '/' . trim($path, '/');
    }

    private function error(int $status): void
    {
        http_response_code($status);
        View::render('errors/http', [
            'title' => $status === 404 ? 'Página não encontrada' : 'Método não permitido',
            'active' => '',
            'status' => $status,
        ]);
    }
}
