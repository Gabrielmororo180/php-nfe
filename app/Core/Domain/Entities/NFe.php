<?php

namespace App\Core\Domain\Entities;

use DomainException;

class NFe
{
    /**
     * @param Produto[] $produtos
     */
    public function __construct(
        public readonly string $modelo,
        public readonly int $serie,
        public readonly int $numero,
        public readonly string $naturezaOperacao,
        public readonly Emitente $emitente,
        public readonly Destinatario $destinatario,
        public readonly array $produtos,
        public readonly float $valorTotal,
        public readonly ?string $chaveAcesso = null,
        public readonly string $status = 'RASCUNHO'
    ) {
        $this->validar();
    }

    /**
     * Validates basic domain invariants for NFe entity.
     */
    private function validar(): void
    {
        if (empty($this->produtos)) {
            throw new DomainException("NFe must contain at least one product.");
        }

        if ($this->valorTotal <= 0) {
            throw new DomainException("NFe total amount must be greater than zero.");
        }
    }
}
