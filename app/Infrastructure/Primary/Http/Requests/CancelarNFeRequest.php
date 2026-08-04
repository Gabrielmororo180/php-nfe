<?php

namespace App\Infrastructure\Primary\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Form Request validating HTTP payload for NFe cancellation.
 */
class CancelarNFeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'id_lote' => 'required|string',
            'c_orgao' => 'required|string',
            'cnpj' => 'required|string',
            'chave_nfe' => 'required|string|size:44',
            'data_hora_evento' => 'required|string',
            'numero_protocolo' => 'required|string',
            'justificativa' => 'required|string|min:15',
        ];
    }
}
