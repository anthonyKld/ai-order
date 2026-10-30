<?php

declare(strict_types=1);

namespace App\Validation;

use App\Services\AIException;

final class AIResponseValidator
{
    public static function schema(): array
    {
        $text = ['type' => ['string', 'null']];
        $id = ['type' => ['integer', 'null']];
        $object = static fn (array $properties): array => ['type' => 'object', 'properties' => $properties,
            'required' => array_keys($properties), 'additionalProperties' => false];
        return $object([
            'items' => ['type' => 'array', 'items' => $object([
                'product_id' => $id, 'product_name' => ['type' => 'string'], 'quantity' => $id,
                'variant_id' => $id, 'variant_name' => $text, 'color' => $text, 'size' => $text,
                'characteristics' => $text,
                'issues' => ['type' => 'array', 'items' => ['type' => 'string',
                    'enum' => ['ambiguous', 'not_found', 'missing_information']]],
            ])],
            'notes' => $text, 'requested_deadline' => $text,
        ]);
    }

    public function decode(string $json): array
    {
        try {
            $value = json_decode($json, false, 32, JSON_THROW_ON_ERROR);
            $this->validate($value, self::schema());
            if (count($value->items) > 50) {
                throw new \UnexpectedValueException();
            }
            return json_decode($json, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException | \UnexpectedValueException) {
            throw new AIException('invalid_response', 'A IA retornou dados incompatíveis. Tente descrever o pedido novamente.');
        }
    }

    private function validate(mixed $value, array $schema): void
    {
        $type = match (true) {
            $value === null => 'null', is_int($value) => 'integer', is_string($value) => 'string',
            is_array($value) => 'array', $value instanceof \stdClass => 'object', default => 'invalid',
        };
        if (!in_array($type, (array) $schema['type'], true)
            || (isset($schema['enum']) && !in_array($value, $schema['enum'], true))) {
            throw new \UnexpectedValueException();
        }
        if ($type === 'string' && (strlen($value) > 8000 || preg_match('//u', $value) !== 1)) {
            throw new \UnexpectedValueException();
        }
        if ($type === 'object') {
            $keys = array_keys(get_object_vars($value));
            if (array_diff($keys, $schema['required']) || array_diff($schema['required'], $keys)) {
                throw new \UnexpectedValueException();
            }
            foreach ($schema['properties'] as $name => $child) {
                $this->validate($value->$name, $child);
            }
        }
        if ($type === 'array') {
            if (count($value) > 50) {
                throw new \UnexpectedValueException();
            }
            foreach ($value as $child) {
                $this->validate($child, $schema['items']);
            }
        }
    }
}
