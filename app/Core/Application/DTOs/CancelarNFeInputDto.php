<?php

namespace App\Core\Application\DTOs;

/**
 * Data Transfer Object for NFe cancellation input parameters.
 */
class CancelarNFeInputDto
{
    public function __construct(
        public readonly string $idLote,
        public readonly string $cOrgao,
        public readonly string $cnpj,
        public readonly string $chaveNFe,
        public readonly string $dataHoraEvento,
        public readonly string $numeroProtocolo,
        public readonly string $justificativa
    ) {}
}
