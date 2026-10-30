<?php

declare(strict_types=1);

namespace App\Validation;

final class ProductValidator
{
    public const MAX_VARIANTS = 100;
    public const MAX_STOCK = 2147483647;

    public static function text(mixed $value): string
    {
        return is_string($value) ? trim($value) : '';
    }

    public static function integer(mixed $value, int $min = 0, int $max = self::MAX_STOCK): ?int
    {
        if (!is_string($value) && !is_int($value)) {
            return null;
        }
        $text = trim((string) $value);
        if (!preg_match('/^[0-9]+$/D', $text)) {
            return null;
        }
        $number = filter_var(ltrim($text, '0') ?: '0', FILTER_VALIDATE_INT, ['options' => ['min_range' => $min, 'max_range' => $max]]);
        return $number === false ? null : $number;
    }

    private static function validText(string $value, int $max): bool
    {
        return preg_match('//u', $value) === 1 && !preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)
            && preg_match_all('/./us', $value) <= $max;
    }

    /** Retorna valores seguros para reexibição, dados normalizados e erros. */
    public function validate(array $input): array
    {
        $values = [
            'name' => self::text($input['name'] ?? ''),
            'description' => self::text($input['description'] ?? ''),
            'price' => self::text($input['price'] ?? ''),
            'stock' => self::text($input['stock'] ?? '0'),
            'active' => self::text($input['active'] ?? ''),
            'variants' => [],
        ];
        $errors = [];
        if ($values['name'] === '' || !self::validText($values['name'], 150)) {
            $errors['name'] = 'Informe um nome válido com até 150 caracteres.';
        }
        if (!self::validText($values['description'], 5000) || (isset($input['description']) && !is_string($input['description']))) {
            $errors['description'] = 'A descrição deve ser um texto com até 5.000 caracteres.';
        }
        $price = str_replace(',', '.', $values['price']);
        if (!preg_match('/^[0-9]{1,8}(?:\.[0-9]{1,2})?$/D', $price)) {
            $errors['price'] = 'Informe um preço entre 0 e 99.999.999,99, com até duas casas decimais e sem separador de milhar.';
        } else {
            [$whole, $cents] = array_pad(explode('.', $price), 2, '');
            $price = (ltrim($whole, '0') ?: '0') . '.' . str_pad($cents, 2, '0');
        }
        $stock = self::integer($input['stock'] ?? '0');
        if ($stock === null) {
            $errors['stock'] = 'O estoque deve ser inteiro entre 0 e 2.147.483.647.';
        }
        if (!in_array($values['active'], ['0', '1'], true)) {
            $errors['active'] = 'Selecione Ativo ou Inativo.';
        }
        $rows = $input['variants'] ?? [];
        if (!is_array($rows) || count($rows) > self::MAX_VARIANTS) {
            $errors['variants'] = 'Informe no máximo 100 variações válidas.';
            $rows = is_array($rows) ? array_slice($rows, 0, self::MAX_VARIANTS) : [];
        }
        $variants = [];
        $ids = [];
        $total = 0;
        foreach (array_values($rows) as $index => $row) {
            $row = is_array($row) ? $row : [];
            $name = self::text($row['name'] ?? '');
            $idText = self::text($row['id'] ?? '');
            $id = $idText === '' ? null : self::integer($idText, 1, 4294967295);
            $quantity = self::integer($row['stock'] ?? null);
            $values['variants'][] = ['id' => $idText, 'name' => $name, 'stock' => self::text($row['stock'] ?? '')];
            $key = 'variant_' . $index;
            if ($name === '' || !self::validText($name, 100)) {
                $errors[$key] = 'Variação ' . ($index + 1) . ': informe um nome válido com até 100 caracteres.';
            } elseif ($quantity === null) {
                $errors[$key] = 'Variação ' . ($index + 1) . ': o estoque deve ser inteiro entre 0 e 2.147.483.647.';
            }
            foreach ($variants as $previous) {
                if ($name !== '' && preg_match('/^' . preg_quote($previous['name'], '/') . '$/iuD', $name) === 1) {
                    $errors[$key] = 'As variações não podem ter nomes duplicados no mesmo produto.';
                }
            }
            if ((isset($row['id']) && !is_string($row['id'])) || ($idText !== '' && ($id === null || isset($ids[$id])))) {
                $errors[$key] = 'Identificador de variação inválido. Reabra o produto e tente novamente.';
            }
            if ($id !== null) {
                $ids[$id] = true;
            }
            $total += $quantity ?? 0;
            $variants[] = ['id' => $id, 'name' => $name, 'stock' => $quantity ?? 0];
        }
        if ($total > self::MAX_STOCK) {
            $errors['variants'] = 'A soma dos estoques das variações não pode ultrapassar 2.147.483.647.';
        }
        return ['values' => $values, 'errors' => $errors, 'data' => [
            'name' => $values['name'], 'description' => $values['description'] === '' ? null : $values['description'],
            'price' => $price, 'stock' => $variants === [] ? ($stock ?? 0) : 0,
            'active' => (int) $values['active'], 'variants' => $variants,
        ]];
    }
}
