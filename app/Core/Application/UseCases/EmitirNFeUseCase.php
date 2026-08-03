<?php

namespace App\Core\Application\UseCases;

use App\Core\Application\DTOs\EmitirNFeInputDto;
use App\Core\Application\DTOs\EmitirNFeOutputDto;
use App\Core\Application\Ports\Outbound\FileStorageServiceInterface;
use App\Core\Application\Ports\Outbound\NFeFiscalGatewayInterface;
use App\Core\Domain\Entities\NFe;
use Throwable;

/**
 * Use case responsible for issuing an NFe document.
 */
class EmitirNFeUseCase
{
    public function __construct(
        private readonly NFeFiscalGatewayInterface $fiscalGateway,
        private readonly FileStorageServiceInterface $storageService
    ) {}

    /**
     * Executes the NFe issuance flow.
     */
    public function execute(EmitirNFeInputDto $input): EmitirNFeOutputDto
    {
        try {
            // 1. Create and validate the domain entity
            $nfe = new NFe(
                modelo: $input->modelo,
                serie: $input->serie,
                numero: $input->numero,
                naturezaOperacao: $input->naturezaOperacao,
                emitente: $input->emitente,
                destinatario: $input->destinatario,
                produtos: $input->produtos,
                valorTotal: $input->valorTotal
            );

            // 2. Transmit to fiscal gateway (SEFAZ)
            $resultado = $this->fiscalGateway->emitir($nfe);

            if (!$resultado->sucesso) {
                return new EmitirNFeOutputDto(
                    sucesso: false,
                    erro: $resultado->erro ?? 'Unknown error during NFe transmission.'
                );
            }

            // 3. Save generated XML file if present
            $xmlPath = null;
            if ($resultado->xml && $resultado->chaveNFe) {
                $xmlPath = $this->storageService->salvarXml($resultado->chaveNFe, $resultado->xml);
            }

            // 4. Save generated PDF file if present
            $pdfPath = null;
            if ($resultado->pdfPath && $resultado->chaveNFe) {
                $pdfPath = $this->storageService->salvarPdf($resultado->chaveNFe, $resultado->pdfPath);
            }

            return new EmitirNFeOutputDto(
                sucesso: true,
                chaveNFe: $resultado->chaveNFe,
                xmlPath: $xmlPath,
                pdfPath: $pdfPath
            );
        } catch (Throwable $e) {
            return new EmitirNFeOutputDto(
                sucesso: false,
                erro: $e->getMessage()
            );
        }
    }
}
