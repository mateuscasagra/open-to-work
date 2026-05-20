<?php

declare(strict_types=1);

namespace App\Http\Requests\Support;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSupportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // endpoint público — qualquer visitante da landing
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['nullable', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'title' => ['required', 'string', 'min:3', 'max:200'],
            'description' => ['required', 'string', 'min:10', 'max:5000'],
        ];
    }
}
