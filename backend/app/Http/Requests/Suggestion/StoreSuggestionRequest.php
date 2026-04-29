<?php

declare(strict_types=1);

namespace App\Http\Requests\Suggestion;

use Illuminate\Foundation\Http\FormRequest;

final class StoreSuggestionRequest extends FormRequest
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
            'title' => ['required', 'string', 'min:5', 'max:120'],
            'body' => ['required', 'string', 'min:10', 'max:2000'],
        ];
    }
}
