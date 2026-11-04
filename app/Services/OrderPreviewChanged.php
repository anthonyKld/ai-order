<?php
declare(strict_types=1);

namespace App\Services;

final class OrderPreviewChanged extends \DomainException
{
    public function __construct(public readonly array $result)
    {
        parent::__construct('O catálogo ou o estoque mudou. Revise a prévia atualizada e confirme os dados novamente.');
    }
}
