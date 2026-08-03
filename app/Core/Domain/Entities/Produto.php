<?php

namespace App\Core\Domain\Entities;

class Produto
{
    public function __construct(
        public readonly string $codigo,
        public readonly string $descricao,
        public readonly string $ncm,
        public readonly string $cfop,
        public readonly string $unidadeComercial,
        public readonly float $quantidadeComercial,
        public readonly float $valorUnitarioComercial,
        public readonly float $valorTotalBruto,
        public readonly Impostos $impostos
    ) {}
}
