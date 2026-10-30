<?php

declare(strict_types=1);

namespace App\Validation;

final class OrderPreviewValidator
{
    public function validate(array $interpretation, array $catalog, callable $find): array
    {
        $items = [];
        $totals = [];
        $cache = [];
        $catalogById = array_column($catalog, null, 'id');
        foreach ($interpretation['items'] as $source) {
            $issues = [];
            $add = static function (string $code, string $message) use (&$issues): void {
                $issues[$code . ':' . $message] = ['code' => $code, 'message' => $message];
            };
            foreach (array_unique($source['issues']) as $code) {
                $add($code, match ($code) {
                    'ambiguous' => 'A interpretação contém uma correspondência incerta. Confirme o item.',
                    'not_found' => 'A IA não encontrou uma correspondência segura no catálogo.',
                    default => 'A mensagem precisa de informações adicionais para este item.',
                });
            }
            $quantity = $source['quantity'];
            if ($quantity === null || $quantity < 1 || $quantity > 10000) {
                $add('missing_information', 'Informe uma quantidade inteira entre 1 e 10.000.');
                $quantity = null;
            }
            $id = $source['product_id'];
            if ($id !== null && !array_key_exists($id, $cache)) $cache[$id] = $find($id);
            $product = $id === null ? null : $cache[$id];
            $productValid = false;
            if ($product === null || !(bool) $product['active'] || !isset($catalogById[$id])) {
                $add('not_found', 'Produto não encontrado no catálogo ativo. Confira o nome informado.');
            } else {
                $candidates = array_filter($catalog, fn (array $p): bool => $this->corresponds($source['product_name'], $p['name']));
                if (count($candidates) > 1) {
                    $add('ambiguous', 'Há mais de um produto compatível. Informe o nome completo.');
                } elseif (count($candidates) !== 1 || (int) reset($candidates)['id'] !== $id
                    || !$this->corresponds($source['product_name'], $product['name'])) {
                    $add('ambiguous', 'O produto sugerido precisa ser confirmado com o nome do catálogo.');
                } else {
                    $productValid = true;
                }
            }
            $variant = null;
            $stock = null;
            $key = null;
            if ($productValid) {
                $requestedVariant = trim(implode(' ', array_filter([
                    $source['variant_name'], $source['color'], $source['size'], $source['characteristics'],
                ], static fn ($v): bool => $v !== null && $v !== '')));
                if ($product['variants'] !== []) {
                    $candidates = array_filter($product['variants'], fn (array $v): bool => $this->corresponds($requestedVariant, $v['name']));
                    if ($requestedVariant === '') {
                        $add('missing_information', 'Escolha uma variação: ' . implode(', ', array_column($product['variants'], 'name')) . '.');
                    } elseif ($candidates === []) {
                        $add('not_found', 'A variação ou característica solicitada não existe neste produto.');
                    } elseif (count($candidates) > 1) {
                        $add('ambiguous', 'Mais de uma variação corresponde. Informe cor, tamanho ou nome completo.');
                    } else {
                        $candidate = reset($candidates);
                        $sentVariants = array_column($catalogById[$id]['variants'], 'id');
                        if ((int) $candidate['id'] !== $source['variant_id'] || !in_array($source['variant_id'], $sentVariants, true)) {
                            $add('ambiguous', 'O ID da variação não corresponde à descrição solicitada. Confirme a variação.');
                        } else {
                            $variant = $candidate;
                            $stock = (int) $variant['stock'];
                            $key = 'v:' . $variant['id'];
                        }
                    }
                } elseif ($source['variant_id'] !== null || $requestedVariant !== '') {
                    $add('not_found', 'Este produto não possui a variação ou característica solicitada no catálogo.');
                } else {
                    $stock = (int) $product['stock'];
                    $key = 'p:' . $id;
                }
            }
            if ($key !== null && $quantity !== null) $totals[$key] = ($totals[$key] ?? 0) + $quantity;
            $items[] = [
                'product_id' => $productValid ? $id : null,
                'product_name' => $productValid ? $product['name'] : $source['product_name'],
                'requested_product' => $source['product_name'],
                'variant_id' => $variant === null ? null : (int) $variant['id'],
                'variant' => $variant['name'] ?? null, 'quantity' => $quantity,
                'color' => $source['color'], 'size' => $source['size'], 'characteristics' => $source['characteristics'],
                'unit_price' => $productValid ? $product['price'] : null, 'available_stock' => $stock,
                'subtotal' => null, 'issues' => array_values($issues), '_key' => $key,
            ];
        }
        $total = 0;
        $hasIssues = $items === [];
        foreach ($items as &$item) {
            if ($item['_key'] !== null && ($totals[$item['_key']] ?? 0) > $item['available_stock']) {
                $item['issues'][] = ['code' => 'insufficient_stock', 'message' => 'A quantidade somada (' . $totals[$item['_key']]
                    . ') excede o estoque disponível (' . $item['available_stock'] . ').'];
            }
            $item['statuses'] = array_values(array_unique(array_column($item['issues'], 'code')));
            $item['status'] = $item['statuses'][0] ?? 'matched';
            $item['ready'] = $item['issues'] === [];
            if ($item['ready']) {
                $cents = (int) str_replace('.', '', $item['unit_price']) * $item['quantity'];
                $item['subtotal'] = self::decimal($cents);
                $total += $cents;
                $item['statuses'] = ['matched'];
            } else {
                $hasIssues = true;
            }
            unset($item['_key']);
        }
        unset($item);
        return ['success' => true, 'items' => $items,
            'warnings' => $items === [] ? ['Nenhum item foi identificado. Descreva produtos e quantidades.']
                : ($hasIssues ? ['Existem pendências. O total parcial inclui somente os itens sem pendências.'] : []),
            'notes' => $interpretation['notes'], 'requested_deadline' => $interpretation['requested_deadline'],
            'estimated_total' => self::decimal($total), 'has_pending_items' => $hasIssues,
            'preview_only' => true,
        ];
    }

    private static function decimal(int $cents): string
    {
        return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
    }

    private function corresponds(string $requested, string $actual): bool
    {
        $tokens = static function (string $text): array {
            $text = strtr($text, ['á'=>'a','à'=>'a','ã'=>'a','â'=>'a','é'=>'e','ê'=>'e','í'=>'i','ó'=>'o','ô'=>'o','õ'=>'o','ú'=>'u','ç'=>'c',
                'Á'=>'a','À'=>'a','Ã'=>'a','Â'=>'a','É'=>'e','Ê'=>'e','Í'=>'i','Ó'=>'o','Ô'=>'o','Õ'=>'o','Ú'=>'u','Ç'=>'c']);
            $parts = preg_split('/[^a-z0-9]+/', strtolower($text), -1, PREG_SPLIT_NO_EMPTY);
            $parts = array_diff($parts, ['de', 'da', 'do', 'com', 'cor', 'tamanho']);
            return array_values(array_unique(array_map(static function (string $word): string {
                if (strlen($word) > 3 && str_ends_with($word, 's')) $word = substr($word, 0, -1);
                return ['preta'=>'preto', 'branca'=>'branco', 'vermelha'=>'vermelho', 'amarela'=>'amarelo', 'roxa'=>'roxo', 'azui'=>'azul'][$word] ?? $word;
            }, $parts)));
        };
        $wanted = $tokens($requested);
        return $wanted !== [] && array_diff($wanted, $tokens($actual)) === [];
    }
}
