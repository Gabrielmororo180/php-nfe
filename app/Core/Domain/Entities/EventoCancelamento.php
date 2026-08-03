<?php

namespace App\Core\Domain\Entities;

use DomainException;

class EventoCancelamento
{
    public readonly string $cnpj;
    public readonly string $chaveNFe;
    public readonly string $tipoEvento;
    public readonly string $numeroSequencialEvento;
    public readonly string $versaoEvento;

    public function __construct(
        public readonly string $idLote,
        public readonly string $cOrgao,
        string $cnpj,
        string $chaveNFe,
        public readonly string $dataHoraEvento,
        public readonly string $numeroProtocolo,
        public readonly string $justificativa,
        ?string $tipoEvento = null,
        ?string $numeroSequencialEvento = null,
        ?string $versaoEvento = null
    ) {
        $this->cnpj = preg_replace('/\D/', '', $cnpj);
        $this->chaveNFe = preg_replace('/\D/', '', $chaveNFe);
        $this->tipoEvento = $tipoEvento ?? '110111'; // Default SEFAZ event code for cancellation
        $this->numeroSequencialEvento = $numeroSequencialEvento ?? '1';
        $this->versaoEvento = $versaoEvento ?? '1.00';

        $this->validar();
    }

    /**
     * Validates domain requirements for NFe cancellation event.
     */
    private function validar(): void
    {
        if (strlen($this->chaveNFe) !== 44) {
            throw new DomainException('The NFe access key for cancellation must contain exactly 44 digits.');
        }

        if (mb_strlen($this->justificativa) < 15) {
            throw new DomainException('The cancellation justification must be at least 15 characters long.');
        }

        if (empty($this->numeroProtocolo)) {
            throw new DomainException('The authorization protocol number is required.');
        }
    }
}
