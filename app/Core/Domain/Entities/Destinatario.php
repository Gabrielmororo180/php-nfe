<?php

namespace App\Core\Domain\Entities;

class Destinatario
{
    public function __construct(
        public readonly string $cnpjCpf,
        public readonly string $razaoSocial,
        public readonly Endereco $endereco,
        public readonly string $indicadorIEDestinatario = '9', // 1: Taxpayer ICMS, 2: Exempt, 9: Non-Taxpayer
        public readonly ?string $inscricaoEstadual = null,
        public readonly ?string $email = null
    ) {}
}
