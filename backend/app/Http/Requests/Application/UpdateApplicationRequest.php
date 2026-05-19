<?php

declare(strict_types=1);

namespace App\Http\Requests\Application;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateApplicationRequest extends FormRequest
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
            'notes' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'job_url' => ['sometimes', 'nullable', 'string', 'url', 'max:500'],
            'manual_title' => ['sometimes', 'nullable', 'string', 'max:255'],
            'expected_salary' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'resume_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('resumes', 'id')->where('user_id', $this->user()?->id),
            ],
        ];
    }
}
