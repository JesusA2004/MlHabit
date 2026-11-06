<?php

namespace App\Services\Storage;

/**
 * Resultado de trasladar un archivo: `moved` es falso si ya estaba todo en
 * el destino (nada que hacer).
 */
final readonly class RelocationResult
{
    public function __construct(
        public bool $moved,
        public int $files,
        public int $bytes,
        public int $transferred,
    ) {}
}
