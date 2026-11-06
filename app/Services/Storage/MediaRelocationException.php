<?php

namespace App\Services\Storage;

use RuntimeException;
use Throwable;

/**
 * Falla al trasladar un archivo. `retryable` indica si vale la pena volver
 * a intentar (red, NAS ocupado) o si es un problema del archivo mismo.
 */
class MediaRelocationException extends RuntimeException
{
    public function __construct(string $message, public readonly bool $retryable = true, ?Throwable $previous = null)
    {
        parent::__construct($message, 0, $previous);
    }
}
