<?php

namespace App\Core\Application\DTOs;

/**
 * Data Transfer Object for NFe issuance result.
 */
class EmitirNFeOutputDto
{
    public function __construct(
        public readonly bool $sucesso,
        public readonly ?string $chaveNFe = null,
        public readonly ?string $xmlPath = null,
        public readonly ?string $pdfPath = null,
        public readonly ?string $erro = null
    ) {}
}
