<?php

namespace App\Exceptions;

use RuntimeException;

class OrganizationSyncException extends RuntimeException
{
    public static function fromFailure(string $message, ?\Throwable $previous = null): self
    {
        return new self($message, previous: $previous);
    }
}
