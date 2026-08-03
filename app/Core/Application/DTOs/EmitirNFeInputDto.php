<?php

namespace App\Core\Application\DTOs;

use App\Core\Domain\Entities\Emitente;
use App\Core\Domain\Entities\Destinatario;
use App\Core\Domain\Entities\Produto;

/**
 * Data Transfer Object for NFe issuance input parameters.
 */
class EmitirNFeInputDto
{
    /**
     * @param Produto[] $produtos
     */
    public function __construct(
        public readonly string $modelo,
        public readonly int $serie,
        public readonly int $numero,
        public readonly string $naturezaOperacao,
        public readonly Emitente $emitente,
        public readonly Destinatario $destinatario,
        public readonly array $produtos,
        public readonly float $valorTotal
    ) {}
}
