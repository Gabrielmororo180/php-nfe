<?php

namespace App\Core\Domain\Entities;

/**
 * Value Object representing individual tax calculation details (CST, Base, Aliquot, Total Value).
 */
class ImpostoDetalhe
{
    public function __construct(
        public readonly string $cst,
        public readonly float $baseCalculo = 0.0,
        public readonly float $aliquota = 0.0,
        public readonly float $valor = 0.0
    ) {}
}
