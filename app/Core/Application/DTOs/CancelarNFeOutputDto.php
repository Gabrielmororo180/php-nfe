<?php

namespace App\Core\Application\DTOs;

/**
 * Data Transfer Object for NFe cancellation result.
 */
class CancelarNFeOutputDto
{
    public function __construct(
        public readonly bool $sucesso,
        public readonly ?string $xmlPath = null,
        public readonly ?string $erro = null
    ) {}
}
