<?php

declare(strict_types=1);

namespace App\Services;

final class AIRequestGuard
{
    public static function message(mixed $value): string
    {
        if (!is_string($value) || preg_match('//u', $value) !== 1) {
            throw new AIException('invalid_message', 'Envie uma mensagem de texto válida.', 422);
        }
        $message = preg_replace('/^\s+|\s+$/u', '', $value);
        if ($message === '' || preg_match_all('/./us', $message) > 2000) {
            throw new AIException('invalid_message', 'Descreva o pedido usando entre 1 e 2.000 caracteres.', 422);
        }
        return $message;
    }

    public static function consume(array &$session, string $message, int $now): void
    {
        $recent = array_values(array_filter($session['ai_requests'] ?? [], static fn (array $r): bool => $r['time'] > $now - 60));
        $hash = hash('sha256', $message);
        foreach ($recent as $request) {
            if ($request['hash'] === $hash && $request['time'] > $now - 15) {
                throw new AIException('duplicate_request', 'Este pedido já foi enviado há poucos segundos. Aguarde antes de repetir.', 429);
            }
        }
        if (count($recent) >= 5) {
            throw new AIException('rate_limit', 'Limite de cinco interpretações por minuto atingido. Aguarde um minuto.', 429);
        }
        $session['ai_requests'] = [...$recent, ['hash' => $hash, 'time' => $now]];
    }
}
