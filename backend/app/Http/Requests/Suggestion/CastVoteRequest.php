<?php

declare(strict_types=1);

namespace App\Http\Requests\Suggestion;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CastVoteRequest extends FormRequest
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
            'value' => ['required', Rule::in(['up', 'down'])],
        ];
    }
}
