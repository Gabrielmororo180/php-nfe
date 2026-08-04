<?php

namespace App\Infrastructure\Primary\Http\Controllers;

use App\Core\Application\DTOs\CancelarNFeInputDto;
use App\Core\Application\DTOs\EmitirNFeInputDto;
use App\Core\Application\UseCases\CancelarNFeUseCase;
use App\Core\Application\UseCases\EmitirNFeUseCase;
use App\Core\Domain\Entities\Destinatario;
use App\Core\Domain\Entities\Emitente;
use App\Core\Domain\Entities\Endereco;
use App\Core\Domain\Entities\Impostos;
use App\Core\Domain\Entities\Produto;
use App\Infrastructure\Primary\Http\Requests\CancelarNFeRequest;
use App\Infrastructure\Primary\Http\Requests\EmitirNFeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;

/**
 * Primary HTTP Adapter handling NFe requests.
 */
class NFeController extends Controller
{
    /**
     * Endpoint for issuing an NFe document.
     */
    public function emitir(EmitirNFeRequest $request, EmitirNFeUseCase $useCase): JsonResponse
    {
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
            $impostos = new Impostos(
                icmsCst: $item['icms_cst'],
                icmsAliquota: (float) ($item['icms_aliquota'] ?? 0),
                pisCst: $item['pis_cst'] ?? '07',
                pisAliquota: (float) ($item['pis_aliquota'] ?? 0),
                cofinsCst: $item['cofins_cst'] ?? '07',
                cofinsAliquota: (float) ($item['cofins_aliquota'] ?? 0)
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
    }

    /**
     * Endpoint for cancelling an NFe document.
     */
    public function cancelar(CancelarNFeRequest $request, CancelarNFeUseCase $useCase): JsonResponse
    {
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
    }
}
