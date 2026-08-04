<?php

namespace App\Core\Domain\Entities;

use DomainException;

/**
 * Core Domain Entity representing a complete NFe (Nota Fiscal Eletrônica).
 */
class NFe
{
    public readonly IdentificacaoNFe $identificacao;
    public readonly TotalNFe $total;

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
        ?IdentificacaoNFe $identificacao = null,
        ?TotalNFe $total = null,
        public readonly ?string $chaveAcesso = null,
        public readonly string $status = 'RASCUNHO'
    ) {
        $this->identificacao = $identificacao ?? new IdentificacaoNFe(
            naturezaOperacao: $this->naturezaOperacao,
            serie: (string) $this->serie,
            numero: (string) $this->numero,
            dataEmissao: date('Y-m-d\TH:i:sP'),
            tipoDocumento: $this->modelo
        );

        $this->total = $total ?? new TotalNFe(
            valorProdutos: $this->calcularValorProdutos(),
            valorNota: $this->valorTotal
        );

        $this->validar();
    }

    private function calcularValorProdutos(): float
    {
        $total = 0.0;
        foreach ($this->produtos as $produto) {
            if ($produto instanceof Produto) {
                $total += $produto->valorTotalBruto;
            }
        }
        return $total;
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
