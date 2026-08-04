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
use OpenApi\Attributes as OA;
use Throwable;

/**
 * Primary HTTP Adapter handling NFe requests (Driving Adapter).
 */
class NFeController extends Controller
{
    #[OA\Post(
        path: '/api/nfe/emitir',
        summary: 'Emitir NFe / NFCe',
        description: 'Generates NFe XML, signs with Certificate A1, transmits to SEFAZ, and returns DANFE PDF path',
        tags: ['NFe']
    )]
    #[OA\RequestBody(
        required: true,
        description: 'NFe issuance payload with generic example data',
        content: new OA\JsonContent(
            required: ['modelo', 'serie', 'numero', 'natureza_operacao', 'valor_total', 'emitente', 'destinatario', 'produtos'],
            properties: [
                new OA\Property(property: 'modelo', type: 'string', example: '55'),
                new OA\Property(property: 'serie', type: 'integer', example: 1),
                new OA\Property(property: 'numero', type: 'integer', example: 101),
                new OA\Property(property: 'natureza_operacao', type: 'string', example: 'Venda de Mercadoria'),
                new OA\Property(property: 'valor_total', type: 'number', format: 'float', example: 150.00),
                new OA\Property(property: 'emitente', type: 'object', properties: [
                    new OA\Property(property: 'cnpj', type: 'string', example: '00000000000000'),
                    new OA\Property(property: 'razao_social', type: 'string', example: 'EMPRESA EMITENTE EXEMPLO LTDA'),
                    new OA\Property(property: 'nome_fantasia', type: 'string', example: 'EMPRESA TESTE'),
                    new OA\Property(property: 'inscricao_estadual', type: 'string', example: '000000000'),
                    new OA\Property(property: 'crt', type: 'string', example: '1'),
                    new OA\Property(property: 'endereco', type: 'object', properties: [
                        new OA\Property(property: 'logradouro', type: 'string', example: 'RUA EXEMPLO'),
                        new OA\Property(property: 'numero', type: 'string', example: '100'),
                        new OA\Property(property: 'bairro', type: 'string', example: 'CENTRO'),
                        new OA\Property(property: 'codigo_municipio', type: 'string', example: '3550308'),
                        new OA\Property(property: 'nome_municipio', type: 'string', example: 'SAO PAULO'),
                        new OA\Property(property: 'uf', type: 'string', example: 'SP'),
                        new OA\Property(property: 'cep', type: 'string', example: '01001000'),
                    ]),
                ]),
                new OA\Property(property: 'destinatario', type: 'object', properties: [
                    new OA\Property(property: 'cnpj_cpf', type: 'string', example: '11111111000111'),
                    new OA\Property(property: 'razao_social', type: 'string', example: 'CLIENTE DESTINATARIO EXEMPLO LTDA'),
                    new OA\Property(property: 'indicador_ie', type: 'string', example: '9'),
                    new OA\Property(property: 'endereco', type: 'object', properties: [
                        new OA\Property(property: 'logradouro', type: 'string', example: 'AVENIDA EXEMPLO'),
                        new OA\Property(property: 'numero', type: 'string', example: '200'),
                        new OA\Property(property: 'bairro', type: 'string', example: 'BELA VISTA'),
                        new OA\Property(property: 'codigo_municipio', type: 'string', example: '3550308'),
                        new OA\Property(property: 'nome_municipio', type: 'string', example: 'SAO PAULO'),
                        new OA\Property(property: 'uf', type: 'string', example: 'SP'),
                        new OA\Property(property: 'cep', type: 'string', example: '01310000'),
                    ]),
                ]),
                new OA\Property(property: 'produtos', type: 'array', items: new OA\Items(
                    type: 'object',
                    properties: [
                        new OA\Property(property: 'codigo', type: 'string', example: 'PROD-001'),
                        new OA\Property(property: 'descricao', type: 'string', example: 'PRODUTO TESTE EXEMPLO'),
                        new OA\Property(property: 'ncm', type: 'string', example: '84713012'),
                        new OA\Property(property: 'cfop', type: 'string', example: '5102'),
                        new OA\Property(property: 'unidade_comercial', type: 'string', example: 'UN'),
                        new OA\Property(property: 'quantidade_comercial', type: 'number', example: 1.0),
                        new OA\Property(property: 'valor_unitario_comercial', type: 'number', example: 150.00),
                        new OA\Property(property: 'valor_total_bruto', type: 'number', example: 150.00),
                        new OA\Property(property: 'imposto', type: 'object', properties: [
                            new OA\Property(property: 'icms', type: 'object', properties: [new OA\Property(property: 'cst', type: 'string', example: '40')]),
                            new OA\Property(property: 'pis', type: 'object', properties: [new OA\Property(property: 'cst', type: 'string', example: '09')]),
                            new OA\Property(property: 'cofins', type: 'object', properties: [new OA\Property(property: 'cst', type: 'string', example: '09')]),
                        ]),
                    ]
                )),
            ]
        )
    )]
    #[OA\Response(
        response: 201,
        description: 'NFe issued successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'sucesso', type: 'boolean', example: true),
                new OA\Property(property: 'chave_nfe', type: 'string', example: '35240800000000000000550010000001011000001010'),
                new OA\Property(property: 'xml_path', type: 'string', example: 'nfe/xml/35240800000000000000550010000001011000001010.xml'),
                new OA\Property(property: 'pdf_path', type: 'string', example: 'nfe/pdf/35240800000000000000550010000001011000001010.pdf'),
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'SEFAZ Rejection or Validation Error',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'sucesso', type: 'boolean', example: false),
                new OA\Property(property: 'erro', type: 'string', example: 'Rejeição SEFAZ [cStat 999]: Motivo da rejeição'),
            ]
        )
    )]
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

    #[OA\Post(
        path: '/api/nfe/cancelar',
        summary: 'Cancelar NFe',
        description: 'Transmits cancellation event for an authorized NFe to SEFAZ',
        tags: ['NFe']
    )]
    #[OA\RequestBody(
        required: true,
        description: 'Cancellation payload with generic example data',
        content: new OA\JsonContent(
            required: ['id_lote', 'c_orgao', 'cnpj', 'chave_nfe', 'data_hora_evento', 'numero_protocolo', 'justificativa'],
            properties: [
                new OA\Property(property: 'chave_nfe', type: 'string', example: '35240800000000000000550010000001011000001010'),
                new OA\Property(property: 'numero_protocolo', type: 'string', example: '135240001234567'),
                new OA\Property(property: 'justificativa', type: 'string', example: 'Cancelamento solicitado pelo cliente devido a erro no pedido'),
            ]
        )
    )]
    #[OA\Response(
        response: 200,
        description: 'NFe cancelled successfully',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'sucesso', type: 'boolean', example: true),
                new OA\Property(property: 'xml_path', type: 'string', example: 'nfe/cancelamento/evento.xml'),
            ]
        )
    )]
    public function cancelar(CancelarNFeRequest $request, CancelarNFeUseCase $useCase): JsonResponse
    {
        try {
            $validated = $request->validated();

            $inputDto = new CancelarNFeInputDto(
                idLote: $validated['id_lote'] ?? '1',
                cOrgao: $validated['c_orgao'] ?? '35',
                cnpj: $validated['cnpj'] ?? '00000000000000',
                chaveNFe: $validated['chave_nfe'],
                dataHoraEvento: $validated['data_hora_evento'] ?? date('Y-m-d\TH:i:sP'),
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
