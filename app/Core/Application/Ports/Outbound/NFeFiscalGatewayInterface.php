<?php

namespace App\Core\Application\Ports\Outbound;

use App\Core\Domain\Entities\NFe;
use App\Core\Domain\Entities\EventoCancelamento;

/**
 * Output DTO for NFe issuance response.
 */
class RespostaEmissaoGateway
{
    public function __construct(
        public readonly bool $sucesso,
        public readonly ?string $xml = null,
        public readonly ?string $pdfPath = null,
        public readonly ?string $chaveNFe = null,
        public readonly ?string $erro = null
    ) {}
}

/**
 * Output DTO for NFe cancellation response.
 */
class RespostaCancelamentoGateway
{
    public function __construct(
        public readonly bool $sucesso,
        public readonly ?string $xml = null,
        public readonly ?string $erro = null
    ) {}
}

/**
 * Port interface for fiscal gateway interactions (SEFAZ).
 */
interface NFeFiscalGatewayInterface
{
    public function emitir(NFe $nfe): RespostaEmissaoGateway;

    public function cancelar(EventoCancelamento $evento): RespostaCancelamentoGateway;
}
