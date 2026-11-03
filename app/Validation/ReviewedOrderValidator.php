<?php

declare(strict_types=1);

namespace App\Validation;

final class ReviewedOrderValidator
{
    public const MAX_ITEMS = 100;
    public const MAX_QUANTITY = 10000;

    public function validate(array $input, callable $find): array
    {
        $errors = [];
        $items = [];
        $cache = [];
        $totals = [];
        $notes = $this->text($input['notes'] ?? null, 2000, 'notes', $errors);
        $deadline = $this->text($input['requested_deadline'] ?? null, 200, 'requested_deadline', $errors);
        $source = $input['items'] ?? null;
        if (!is_array($source) || !array_is_list($source) || count($source) < 1 || count($source) > self::MAX_ITEMS) {
            $errors['items'] = 'Informe entre 1 e 100 itens.';
            $source = [];
        }
        foreach ($source as $index => $row) {
            $issues = [];
            $row = is_array($row) ? $row : [];
            $id = self::integer($row['product_id'] ?? null, 4294967295);
            $quantity = self::integer($row['quantity'] ?? null, self::MAX_QUANTITY);
            $variantInput = $row['variant_id'] ?? null;
            $variantId = $variantInput === null ? null : self::integer($variantInput, 4294967295);
            if ($quantity === null) $issues['quantity'] = 'Informe uma quantidade inteira entre 1 e 10.000.';
            if ($id !== null && !array_key_exists($id, $cache)) $cache[$id] = $find($id);
            $product = $id === null ? null : $cache[$id];
            $variant = null;
            $stock = null;
            $price = null;
            $key = null;
            if ($product === null || (int) $product['active'] !== 1) {
                $issues['product_id'] = 'Selecione um produto existente e ativo.';
            } else {
                $price = $product['price'];
                if ($variantInput !== null && $variantId === null) {
                    $issues['variant_id'] = 'Selecione uma variação válida deste produto.';
                } elseif ($product['variants'] !== []) {
                    foreach ($product['variants'] as $candidate) {
                        if ((int) $candidate['id'] === $variantId) $variant = $candidate;
                    }
                    if ($variant === null) $issues['variant_id'] = 'Selecione uma variação pertencente a este produto.';
                    else { $stock = (int) $variant['stock']; $key = 'v:' . $variantId; }
                } elseif ($variantId !== null) {
                    $issues['variant_id'] = 'Este produto não possui variações.';
                } else { $stock = (int) $product['stock']; $key = 'p:' . $id; }
            }
            if ($key !== null && $quantity !== null) $totals[$key] = ($totals[$key] ?? 0) + $quantity;
            $items[] = ['product_id' => $id, 'variant_id' => $variantId, 'quantity' => $quantity,
                'product_name' => $product['name'] ?? null, 'variant' => $variant['name'] ?? null,
                'unit_price' => $price, 'available_stock' => $stock, 'subtotal' => null,
                'errors' => $issues, '_key' => $key];
        }
        $total = 0;
        $units = 0;
        foreach ($items as $index => &$item) {
            $key = $item['_key'];
            if ($key !== null && ($totals[$key] ?? 0) > $item['available_stock']) {
                $item['errors']['quantity'] = 'A quantidade somada (' . $totals[$key] . ') excede o estoque disponível (' . $item['available_stock'] . ').';
            }
            $units += $item['quantity'] ?? 0;
            $item['ready'] = $item['errors'] === [];
            if ($item['ready']) {
                $subtotal = self::cents($item['unit_price']) * $item['quantity'];
                $item['subtotal'] = self::decimal($subtotal);
                $total += $subtotal;
            }
            foreach ($item['errors'] as $field => $message) $errors["items.$index.$field"] = $message;
            unset($item['_key']);
        }
        unset($item);
        return ['success' => $errors === [], 'validated_items' => $items, 'errors' => $errors,
            'warnings' => $errors === [] ? [] : ['O total parcial inclui somente os itens sem pendências.'],
            'estimated_total' => self::decimal($total), 'total_units' => $units,
            'notes' => $notes, 'requested_deadline' => $deadline, 'confirmed' => false];
    }

    public static function integer(mixed $value, int $max): ?int
    {
        if (!is_int($value) && !is_string($value)) return null;
        if (!preg_match('/^[1-9][0-9]*$/D', (string) $value)) return null;
        $number = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => $max]]);
        return $number === false ? null : $number;
    }

    public static function cents(string $price): int
    {
        if (!preg_match('/^[0-9]{1,8}\.[0-9]{2}$/D', $price)) throw new \UnexpectedValueException('Preço inválido no catálogo.');
        return (int) str_replace('.', '', $price);
    }

    public static function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function text(mixed $value, int $max, string $field, array &$errors): ?string
    {
        if ($value === null) return null;
        if (!is_string($value) || preg_match_all('/./us', $value) > $max) {
            $errors[$field] = "Use texto com até $max caracteres.";
            return null;
        }
        return trim($value) === '' ? null : trim($value);
    }
}
