<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class AIException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, public readonly int $httpStatus = 502)
    {
        parent::__construct($message);
    }
}
