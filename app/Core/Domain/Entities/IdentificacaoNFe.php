<?php

namespace App\Core\Domain\Entities;

/**
 * Domain entity representing NFe identification header data (SEFAZ <ide> tag).
 */
class IdentificacaoNFe
{
    public readonly string $dataEntradaSaida;

    public function __construct(
        public readonly string $naturezaOperacao,
        public readonly string $serie,
        public readonly string $numero,
        public readonly string $dataEmissao,
        ?string $dataEntradaSaida = null,
        public readonly string $tipoDocumento = '55', // 55 = NFe, 65 = NFCe
        public readonly string $identificadorDestino = '1', // 1 = Internal, 2 = Interestate, 3 = Foreign
        public readonly string $codigoMunicipio = '3550308',
        public readonly string $tipoImpressao = '1', // 1 = Portrait, 2 = Landscape
        public readonly string $tipoEmissao = '1', // 1 = Normal
        public readonly string $ambiente = '2', // 1 = Production, 2 = Homologation
        public readonly string $finalidade = '1', // 1 = Normal NFe
        public readonly string $operacaoConsumidorFinal = '1', // 0 = No, 1 = Yes
        public readonly string $indicadorPresenca = '1' // 1 = In-person
    ) {
        $this->dataEntradaSaida = $dataEntradaSaida ?? $this->dataEmissao;
    }
}
