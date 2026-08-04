<?php

namespace App\Core\Domain\Entities;

/**
 * Value Object representing NFe totals summary (ICMS base, ICMS value, products value, total note value).
 */
class TotalNFe
{
    public function __construct(
        public readonly float $icmsBase = 0.0,
        public readonly float $icmsValor = 0.0,
        public readonly float $valorProdutos = 0.0,
        public readonly float $valorNota = 0.0
    ) {}
}
