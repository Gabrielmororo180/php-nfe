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

    /**
     * Magic property accessor for backwards compatibility.
     */
    public function __get(string $name): mixed
    {
        return match ($name) {
            'icmsCst' => $this->icms->cst,
            'icmsAliquota' => $this->icms->aliquota,
            'pisCst' => $this->pis->cst,
            'pisAliquota' => $this->pis->aliquota,
            'cofinsCst' => $this->cofins->cst,
            'cofinsAliquota' => $this->cofins->aliquota,
            default => null,
        };
    }

    public function __isset(string $name): bool
    {
        return in_array($name, ['icmsCst', 'icmsAliquota', 'pisCst', 'pisAliquota', 'cofinsCst', 'cofinsAliquota'], true);
    }
}
