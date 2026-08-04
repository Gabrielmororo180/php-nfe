<?php

namespace App\Core\Domain\Entities;

/**
 * Domain entity representing NFe tax details (ICMS, PIS, COFINS).
 */
class Impostos
{
    public readonly ImpostoDetalhe $icms;
    public readonly ImpostoDetalhe $pis;
    public readonly ImpostoDetalhe $cofins;

    public function __construct(
        ImpostoDetalhe|string $icmsOrCst = '102',
        ImpostoDetalhe|float $pisOrIcmsAliquota = 0.0,
        ImpostoDetalhe|string $cofinsOrPisCst = '07',
        float $pisAliquota = 0.0,
        string $cofinsCst = '07',
        float $cofinsAliquota = 0.0,
        float $icmsBaseCalculo = 0.0,
        float $icmsValor = 0.0,
        float $pisBaseCalculo = 0.0,
        float $pisValor = 0.0,
        float $cofinsBaseCalculo = 0.0,
        float $cofinsValor = 0.0
    ) {
        if ($icmsOrCst instanceof ImpostoDetalhe) {
            $this->icms = $icmsOrCst;
            $this->pis = $pisOrIcmsAliquota instanceof ImpostoDetalhe
                ? $pisOrIcmsAliquota
                : new ImpostoDetalhe(cst: (string) $cofinsOrPisCst, baseCalculo: $pisBaseCalculo, aliquota: $pisAliquota, valor: $pisValor);
            $this->cofins = $cofinsOrPisCst instanceof ImpostoDetalhe
                ? $cofinsOrPisCst
                : new ImpostoDetalhe(cst: $cofinsCst, baseCalculo: $cofinsBaseCalculo, aliquota: $cofinsAliquota, valor: $cofinsValor);
        } else {
            $this->icms = new ImpostoDetalhe(
                cst: (string) $icmsOrCst,
                baseCalculo: $icmsBaseCalculo,
                aliquota: (float) $pisOrIcmsAliquota,
                valor: $icmsValor
            );

            $this->pis = new ImpostoDetalhe(
                cst: (string) $cofinsOrPisCst,
                baseCalculo: $pisBaseCalculo,
                aliquota: $pisAliquota,
                valor: $pisValor
            );

            $this->cofins = new ImpostoDetalhe(
                cst: $cofinsCst,
                baseCalculo: $cofinsBaseCalculo,
                aliquota: $cofinsAliquota,
                valor: $cofinsValor
            );
        }
    }
}
