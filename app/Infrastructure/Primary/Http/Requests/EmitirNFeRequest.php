<?php

namespace App\Infrastructure\Primary\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request validating HTTP payload for NFe issuance.
 */
class EmitirNFeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'modelo' => 'required|string|in:55,65',
            'serie' => 'required|integer|min:1',
            'numero' => 'required|integer|min:1',
            'natureza_operacao' => 'required|string|max:60',
            'emitente' => 'required|array',
            'emitente.cnpj' => 'required|string',
            'emitente.razao_social' => 'required|string',
            'emitente.nome_fantasia' => 'required|string',
            'emitente.inscricao_estadual' => 'required|string',
            'emitente.crt' => 'required|string',
            'emitente.endereco' => 'required|array',
            'destinatario' => 'required|array',
            'destinatario.cnpj_cpf' => 'required|string',
            'destinatario.razao_social' => 'required|string',
            'destinatario.endereco' => 'required|array',
            'produtos' => 'required|array|min:1',
            'produtos.*.codigo' => 'required|string',
            'produtos.*.descricao' => 'required|string',
            'produtos.*.ncm' => 'required|string',
            'produtos.*.cfop' => 'required|string',
            'produtos.*.unidade_comercial' => 'required|string',
            'produtos.*.quantidade_comercial' => 'required|numeric|gt:0',
            'produtos.*.valor_unitario_comercial' => 'required|numeric|gt:0',
            'produtos.*.valor_total_bruto' => 'required|numeric|gt:0',
            'produtos.*.icms_cst' => 'nullable|string',
            'produtos.*.pis_cst' => 'nullable|string',
            'produtos.*.cofins_cst' => 'nullable|string',
            'produtos.*.imposto' => 'nullable|array',
            'produtos.*.impostos' => 'nullable|array',
            'valor_total' => 'required|numeric|gt:0',
        ];
    }
}
