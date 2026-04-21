<?php

declare(strict_types=1);

namespace App\Http\Requests\Resume;

use App\Enums\SupportedLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UploadResumePdfRequest extends FormRequest
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
            'title' => ['required', 'string', 'max:120'],
            'language' => ['sometimes', Rule::enum(SupportedLocale::class)],
            'file' => ['required', 'file', 'mimes:pdf', 'max:5120'], // 5MB
        ];
    }
}
