<?php

declare(strict_types=1);

namespace App\Services;

final class OrderPreviewSession
{
    public static function revision(array $session): int
    {
        return $session['order_preview_revision'] ?? 0;
    }

    public static function invalidate(array &$session): int
    {
        unset($session['confirmed_order_preview']);
        return $session['order_preview_revision'] = self::revision($session) + 1;
    }

    public static function confirm(array &$session, array $result): void
    {
        if (!$result['success']) throw new \LogicException('Prévia inválida.');
        $session['confirmed_order_preview'] = [
            'items' => array_map(static fn (array $item): array => array_intersect_key($item, array_flip([
                'product_id', 'variant_id', 'quantity', 'unit_price', 'subtotal', 'product_name', 'variant',
            ])), $result['validated_items']),
            'notes' => $result['notes'], 'requested_deadline' => $result['requested_deadline'],
            'estimated_total' => $result['estimated_total'], 'total_units' => $result['total_units'],
            'confirmed_at' => gmdate('c'), 'requires_revalidation' => true,
            'idempotency_key' => bin2hex(random_bytes(32)),
        ];
    }
}
