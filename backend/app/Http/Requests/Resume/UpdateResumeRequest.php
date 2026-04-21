<?php

declare(strict_types=1);

namespace App\Http\Requests\Resume;

use App\Enums\ResumeSectionType;
use App\Enums\SupportedLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateResumeRequest extends FormRequest
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
            'title' => ['sometimes', 'string', 'max:120'],
            'language' => ['sometimes', Rule::enum(SupportedLocale::class)],
            'sections' => ['sometimes', 'array'],
            'sections.*.type' => ['required', Rule::enum(ResumeSectionType::class)],
            'sections.*.order' => ['required', 'integer', 'min:0'],
            'sections.*.content' => ['required', 'array'],
        ];
    }
}
