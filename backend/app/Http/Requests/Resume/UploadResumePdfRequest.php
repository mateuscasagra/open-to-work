<?php

declare(strict_types=1);

namespace App\Http\Requests\Resume;

use App\Enums\SupportedLocale;
use App\Models\Resume;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UploadResumePdfRequest extends FormRequest
{
    public const MAX_FILE_KB = 2048;

    public const MAX_RESUMES_PER_USER = 5;

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
            'file' => ['required', 'file', 'mimes:pdf', 'max:' . self::MAX_FILE_KB],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $maxMb = self::MAX_FILE_KB / 1024;

        return [
            'file.required' => __('resumes.upload.file_required'),
            'file.mimes' => __('resumes.upload.file_pdf'),
            'file.max' => __('resumes.upload.file_too_large', ['max' => $maxMb]),
            'file.uploaded' => __('resumes.upload.file_too_large', ['max' => $maxMb]),
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
                    'file',
                    __('resumes.quota_reached', ['max' => self::MAX_RESUMES_PER_USER])
                );
            }
        });
    }
}
