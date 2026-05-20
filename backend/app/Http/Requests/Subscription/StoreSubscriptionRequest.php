<?php

declare(strict_types=1);

namespace App\Http\Requests\Subscription;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSubscriptionRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Aceita CPF (11) ou CNPJ (14). Permite máscara — normaliza no
            // accessor abaixo. Validação real de checksum fica a cargo do
            // Asaas (rejeita 400 se inválido); aqui só formato.
            'cpf' => ['required', 'string', 'regex:/^[\d.\-\/\s]{11,18}$/'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'cpf.required' => __('subscription.errors.cpf_required'),
            'cpf.regex' => __('subscription.errors.cpf_invalid'),
        ];
    }

    /**
     * Retorna apenas dígitos do CPF/CNPJ (sem pontos/traços/barras).
     * Valida que o resultado tem 11 ou 14 dígitos.
     */
    public function normalizedCpf(): string
    {
        $digits = preg_replace('/\D/', '', (string) $this->validated('cpf')) ?? '';
        abort_if(! in_array(mb_strlen($digits), [11, 14], true), 422, __('subscription.errors.cpf_invalid'));

        return $digits;
    }
}
