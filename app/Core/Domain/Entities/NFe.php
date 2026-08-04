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
        string|IdentificacaoNFe $modeloOrIdentificacao,
        int|Emitente $serieOrEmitente,
        int|Destinatario $numeroOrDestinatario,
        string|array $naturezaOperacaoOrProdutos,
        Emitente|float|TotalNFe $emitenteOrValorTotal = 0.0,
        ?Destinatario $destinatario = null,
        array $produtos = [],
        float $valorTotal = 0.0,
        public readonly ?string $chaveAcesso = null,
        public readonly string $status = 'RASCUNHO'
    ) {
        if ($modeloOrIdentificacao instanceof IdentificacaoNFe) {
            $this->identificacao = $modeloOrIdentificacao;
            $this->emitente = $serieOrEmitente;
            $this->destinatario = $numeroOrDestinatario;
            $this->produtos = $naturezaOperacaoOrProdutos;
            if ($emitenteOrValorTotal instanceof TotalNFe) {
                $this->total = $emitenteOrValorTotal;
            } else {
                $this->total = new TotalNFe(valorNota: (float) $emitenteOrValorTotal);
            }
        } else {
            $this->identificacao = new IdentificacaoNFe(
                naturezaOperacao: (string) $naturezaOperacaoOrProdutos,
                serie: (string) $serieOrEmitente,
                numero: (string) $numeroOrDestinatario,
                dataEmissao: date('Y-m-d\TH:i:sP'),
                tipoDocumento: (string) $modeloOrIdentificacao
            );
            $this->emitente = $emitenteOrValorTotal;
            $this->destinatario = $destinatario;
            $this->produtos = $produtos;
            $this->total = new TotalNFe(valorNota: $valorTotal);
        }

        $this->validar();
    }

    public readonly Emitente $emitente;
    public readonly Destinatario $destinatario;
    public readonly array $produtos;

    public function getModelo(): string
    {
        return $this->identificacao->tipoDocumento;
    }

    public function getSerie(): int
    {
        return (int) $this->identificacao->serie;
    }

    public function getNumero(): int
    {
        return (int) $this->identificacao->numero;
    }

    public function getNaturezaOperacao(): string
    {
        return $this->identificacao->naturezaOperacao;
    }

    public function getValorTotal(): float
    {
        return $this->total->valorNota;
    }

    /**
     * Validates basic domain invariants for NFe entity.
     */
    private function validar(): void
    {
        if (empty($this->produtos)) {
            throw new DomainException("NFe must contain at least one product.");
        }

        if ($this->total->valorNota <= 0) {
            throw new DomainException("NFe total amount must be greater than zero.");
        }
    }
}
