<?php

namespace App\Core\Application\UseCases;

use App\Core\Application\DTOs\CancelarNFeInputDto;
use App\Core\Application\DTOs\CancelarNFeOutputDto;
use App\Core\Application\Ports\Outbound\FileStorageServiceInterface;
use App\Core\Application\Ports\Outbound\NFeFiscalGatewayInterface;
use App\Core\Domain\Entities\EventoCancelamento;
use Throwable;

/**
 * Use case responsible for cancelling an NFe document.
 */
class CancelarNFeUseCase
{
    public function __construct(
        private readonly NFeFiscalGatewayInterface $fiscalGateway,
        private readonly FileStorageServiceInterface $storageService
    ) {}

    /**
     * Executes the NFe cancellation flow.
     */
    public function execute(CancelarNFeInputDto $input): CancelarNFeOutputDto
    {
        try {
            // 1. Create and validate domain event entity
            $evento = new EventoCancelamento(
                idLote: $input->idLote,
                cOrgao: $input->cOrgao,
                cnpj: $input->cnpj,
                chaveNFe: $input->chaveNFe,
                dataHoraEvento: $input->dataHoraEvento,
                numeroProtocolo: $input->numeroProtocolo,
                justificativa: $input->justificativa
            );

            // 2. Transmit cancellation event to fiscal gateway
            $resultado = $this->fiscalGateway->cancelar($evento);

            if (!$resultado->sucesso) {
                return new CancelarNFeOutputDto(
                    sucesso: false,
                    erro: $resultado->erro ?? 'Unknown error during NFe cancellation.'
                );
            }

            // 3. Store cancellation XML if returned
            $xmlPath = null;
            if ($resultado->xml) {
                $xmlPath = $this->storageService->salvarXml($input->chaveNFe . '-canc', $resultado->xml);
            }

            return new CancelarNFeOutputDto(
                sucesso: true,
                xmlPath: $xmlPath
            );
        } catch (Throwable $e) {
            return new CancelarNFeOutputDto(
                sucesso: false,
                erro: $e->getMessage()
            );
        }
    }
}
