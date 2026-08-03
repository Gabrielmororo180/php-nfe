<?php

namespace App\Core\Domain\Entities;

class Emitente
{
    public function __construct(
        public readonly string $cnpj,
        public readonly string $razaoSocial,
        public readonly string $nomeFantasia,
        public readonly string $inscricaoEstadual,
        public readonly string $crt, // 1: Simples Nacional, 3: Regime Normal
        public readonly Endereco $endereco
    ) {}
}
