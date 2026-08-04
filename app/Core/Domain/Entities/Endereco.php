<?php

namespace App\Core\Domain\Entities;

/**
 * Domain entity representing physical address details for Emitente and Destinatario.
 */
class Endereco
{
    public function __construct(
        public readonly string $logradouro,
        public readonly string $numero,
        public readonly ?string $complemento,
        public readonly string $bairro,
        public readonly string $codigoMunicipio,
        public readonly string $nomeMunicipio,
        public readonly string $uf,
        public readonly string $cep,
        public readonly string $codigoPais = '1058',
        public readonly string $nomePais = 'BRASIL',
        public readonly ?string $fone = null
    ) {}
}
