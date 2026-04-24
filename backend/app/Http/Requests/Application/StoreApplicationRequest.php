<?php

declare(strict_types=1);

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class StoreApplicationRequest extends FormRequest
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
            'jobId' => ['nullable', 'integer', 'exists:jobs,id'],
            'manualTitle' => ['required_without:jobId', 'nullable', 'string', 'max:255'],
            'manualCompany' => ['nullable', 'string', 'max:255'],
            'resumeId' => [
                'nullable',
                'integer',
                Rule::exists('resumes', 'id')->where('user_id', $this->user()?->id),
            ],
            'source' => ['nullable', 'string', 'max:50'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'expectedSalary' => ['nullable', 'integer', 'min:0'],
            'emailMessageOverride' => ['nullable', 'string', 'max:5000'],
            'emailResumeIdOverride' => [
                'nullable',
                'integer',
                Rule::exists('resumes', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
