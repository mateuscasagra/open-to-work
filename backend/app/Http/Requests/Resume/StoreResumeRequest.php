<?php

declare(strict_types=1);

namespace App\Http\Requests\Resume;

use App\Enums\ResumeSectionType;
use App\Enums\SupportedLocale;
use App\Models\Resume;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreResumeRequest extends FormRequest
{
    public const MAX_RESUMES_PER_USER = UploadResumePdfRequest::MAX_RESUMES_PER_USER;

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
            'sections' => ['sometimes', 'array'],
            'sections.*.type' => ['required', Rule::enum(ResumeSectionType::class)],
            'sections.*.order' => ['required', 'integer', 'min:0'],
            'sections.*.content' => ['required', 'array'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $userId = $this->user()?->getAuthIdentifier();
            if ($userId === null) {
                return;
            }

            $count = Resume::query()->where('user_id', $userId)->count();
            if ($count >= self::MAX_RESUMES_PER_USER) {
                $validator->errors()->add(
                    'title',
                    __('resumes.quota_reached', ['max' => self::MAX_RESUMES_PER_USER])
                );
            }
        });
    }
}
