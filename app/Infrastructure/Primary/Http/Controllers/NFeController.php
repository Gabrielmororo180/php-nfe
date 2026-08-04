<?php

namespace App\Infrastructure\Primary\Http\Controllers;

use App\Core\Application\DTOs\CancelarNFeInputDto;
use App\Core\Application\DTOs\EmitirNFeInputDto;
use App\Core\Application\UseCases\CancelarNFeUseCase;
use App\Core\Application\UseCases\EmitirNFeUseCase;
use App\Core\Domain\Entities\Destinatario;
use App\Core\Domain\Entities\Emitente;
use App\Core\Domain\Entities\Endereco;
use App\Core\Domain\Entities\ImpostoDetalhe;
use App\Core\Domain\Entities\Impostos;
use App\Core\Domain\Entities\Produto;
use App\Infrastructure\Primary\Http\Requests\CancelarNFeRequest;
use App\Infrastructure\Primary\Http\Requests\EmitirNFeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Throwable;

/**
 * Primary HTTP Adapter handling NFe requests (Driving Adapter).
 */
class NFeController extends Controller
{
    /**
     * Endpoint for issuing an NFe document.
     */
    public function emitir(EmitirNFeRequest $request, EmitirNFeUseCase $useCase): JsonResponse
    {
        try {
            $validated = $request->validated();

            $enderecoEmit = new Endereco(
                logradouro: $validated['emitente']['endereco']['logradouro'],
                numero: $validated['emitente']['endereco']['numero'],
                complemento: $validated['emitente']['endereco']['complemento'] ?? null,
                bairro: $validated['emitente']['endereco']['bairro'],
                codigoMunicipio: $validated['emitente']['endereco']['codigo_municipio'],
                nomeMunicipio: $validated['emitente']['endereco']['nome_municipio'],
                uf: $validated['emitente']['endereco']['uf'],
                cep: $validated['emitente']['endereco']['cep']
            );

            $emitente = new Emitente(
                cnpj: $validated['emitente']['cnpj'],
                razaoSocial: $validated['emitente']['razao_social'],
                nomeFantasia: $validated['emitente']['nome_fantasia'],
                inscricaoEstadual: $validated['emitente']['inscricao_estadual'],
                crt: $validated['emitente']['crt'],
                endereco: $enderecoEmit
            );

            $enderecoDest = new Endereco(
                logradouro: $validated['destinatario']['endereco']['logradouro'],
                numero: $validated['destinatario']['endereco']['numero'],
                complemento: $validated['destinatario']['endereco']['complemento'] ?? null,
                bairro: $validated['destinatario']['endereco']['bairro'],
                codigoMunicipio: $validated['destinatario']['endereco']['codigo_municipio'],
                nomeMunicipio: $validated['destinatario']['endereco']['nome_municipio'],
                uf: $validated['destinatario']['endereco']['uf'],
                cep: $validated['destinatario']['endereco']['cep']
            );

            $destinatario = new Destinatario(
                cnpjCpf: $validated['destinatario']['cnpj_cpf'],
                razaoSocial: $validated['destinatario']['razao_social'],
                endereco: $enderecoDest,
                indicadorIEDestinatario: $validated['destinatario']['indicador_ie'] ?? '9',
                inscricaoEstadual: $validated['destinatario']['inscricao_estadual'] ?? null,
                email: $validated['destinatario']['email'] ?? null
            );

            $produtos = [];
            foreach ($validated['produtos'] as $item) {
                $icmsCst = $item['imposto']['icms']['cst']
                    ?? $item['imposto']['ICMS']['CST']
                    ?? $item['impostos']['icms']['cst']
                    ?? $item['impostos']['ICMS']['CST']
                    ?? $item['icms_cst']
                    ?? '102';

                $pisCst = $item['imposto']['pis']['cst']
                    ?? $item['imposto']['PIS']['CST']
                    ?? $item['impostos']['pis']['cst']
                    ?? $item['impostos']['PIS']['CST']
                    ?? $item['pis_cst']
                    ?? '09';

                $cofinsCst = $item['imposto']['cofins']['cst']
                    ?? $item['imposto']['COFINS']['CST']
                    ?? $item['impostos']['cofins']['cst']
                    ?? $item['impostos']['COFINS']['CST']
                    ?? $item['cofins_cst']
                    ?? '09';

                $icms = new ImpostoDetalhe(
                    cst: (string) $icmsCst,
                    baseCalculo: (float) ($item['icms_base'] ?? $item['valor_total_bruto']),
                    aliquota: (float) ($item['icms_aliquota'] ?? 0),
                    valor: (float) ($item['icms_valor'] ?? 0)
                );

                $pis = new ImpostoDetalhe(
                    cst: (string) $pisCst,
                    baseCalculo: (float) ($item['pis_base'] ?? 0),
                    aliquota: (float) ($item['pis_aliquota'] ?? 0),
                    valor: (float) ($item['pis_valor'] ?? 0)
                );

                $cofins = new ImpostoDetalhe(
                    cst: (string) $cofinsCst,
                    baseCalculo: (float) ($item['cofins_base'] ?? 0),
                    aliquota: (float) ($item['cofins_aliquota'] ?? 0),
                    valor: (float) ($item['cofins_valor'] ?? 0)
                );

                $impostos = new Impostos(
                    icmsOrCst: $icms,
                    pisOrIcmsAliquota: $pis,
                    cofinsOrPisCst: $cofins
                );

                $produtos[] = new Produto(
                    codigo: $item['codigo'],
                    descricao: $item['descricao'],
                    ncm: $item['ncm'],
                    cfop: $item['cfop'],
                    unidadeComercial: $item['unidade_comercial'],
                    quantidadeComercial: (float) $item['quantidade_comercial'],
                    valorUnitarioComercial: (float) $item['valor_unitario_comercial'],
                    valorTotalBruto: (float) $item['valor_total_bruto'],
                    impostos: $impostos
                );
            }

            $inputDto = new EmitirNFeInputDto(
                modelo: $validated['modelo'],
                serie: (int) $validated['serie'],
                numero: (int) $validated['numero'],
                naturezaOperacao: $validated['natureza_operacao'],
                emitente: $emitente,
                destinatario: $destinatario,
                produtos: $produtos,
                valorTotal: (float) $validated['valor_total']
            );

            $outputDto = $useCase->execute($inputDto);

            if (!$outputDto->sucesso) {
                return response()->json([
                    'sucesso' => false,
                    'erro' => $outputDto->erro,
                ], 400);
            }

            return response()->json([
                'sucesso' => true,
                'chave_nfe' => $outputDto->chaveNFe,
                'xml_path' => $outputDto->xmlPath,
                'pdf_path' => $outputDto->pdfPath,
            ], 201);
        } catch (Throwable $e) {
            return response()->json([
                'sucesso' => false,
                'erro' => $e->getMessage() ?: 'Internal server error during NFe issuance.',
            ], 500);
        }
    }

    /**
     * Endpoint for cancelling an NFe document.
     */
    public function cancelar(CancelarNFeRequest $request, CancelarNFeUseCase $useCase): JsonResponse
    {
        try {
            $validated = $request->validated();

            $inputDto = new CancelarNFeInputDto(
                idLote: $validated['id_lote'],
                cOrgao: $validated['c_orgao'],
                cnpj: $validated['cnpj'],
                chaveNFe: $validated['chave_nfe'],
                dataHoraEvento: $validated['data_hora_evento'],
                numeroProtocolo: $validated['numero_protocolo'],
                justificativa: $validated['justificativa']
            );

            $outputDto = $useCase->execute($inputDto);

            if (!$outputDto->sucesso) {
                return response()->json([
                    'sucesso' => false,
                    'erro' => $outputDto->erro,
                ], 400);
            }

            return response()->json([
                'sucesso' => true,
                'xml_path' => $outputDto->xmlPath,
            ], 200);
        } catch (Throwable $e) {
            return response()->json([
                'sucesso' => false,
                'erro' => $e->getMessage() ?: 'Internal server error during NFe cancellation.',
            ], 500);
        }
    }
}
