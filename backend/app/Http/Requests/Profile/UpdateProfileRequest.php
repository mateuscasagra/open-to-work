<?php

declare(strict_types=1);

namespace App\Http\Requests\Profile;

use App\Domain\Location\Support\SupportedCountry;
use App\Enums\EmailApplyMode;
use App\Enums\Modality;
use App\Enums\Seniority;
use App\Enums\SupportedLocale;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateProfileRequest extends FormRequest
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
        $userId = $this->user()?->id;

        return [
            'desired_role' => ['nullable', 'string', 'max:255'],
            'seniority' => ['nullable', Rule::enum(Seniority::class)],
            'modality' => ['nullable', Rule::enum(Modality::class)],
            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'gte:salary_min'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'country_code' => ['required', 'string', Rule::enum(SupportedCountry::class)],
            'postal_code' => [
                'nullable',
                'string',
                'max:20',
                Rule::requiredIf(fn (): bool => $this->countryRequiresPostalCode()),
            ],
            'state_code' => ['nullable', 'string', 'max:10'],
            'state_name' => ['required', 'string', 'max:120'],
            'city' => ['required', 'string', 'max:120'],
            'languages' => ['nullable', 'array'],
            'languages.*' => ['string', Rule::enum(SupportedLocale::class)],
            'bio' => ['nullable', 'string', 'max:2000'],
            'skills' => ['nullable', 'array'],
            'skills.*' => ['integer', 'exists:skills,id'],
            'email_apply_enabled' => ['nullable', 'boolean'],
            'email_apply_message_mode' => ['nullable', Rule::enum(EmailApplyMode::class)],
            'email_apply_message_template' => [
                'nullable',
                'string',
                'max:5000',
                Rule::requiredIf(
                    fn (): bool => $this->boolean('email_apply_enabled')
                        && $this->input('email_apply_message_mode') === 'fixed'
                ),
            ],
            'email_apply_resume_mode' => ['nullable', Rule::enum(EmailApplyMode::class)],
            'email_apply_resume_id' => [
                'nullable',
                'integer',
                Rule::exists('resumes', 'id')->where('user_id', $userId),
                Rule::requiredIf(
                    fn (): bool => $this->boolean('email_apply_enabled')
                        && $this->input('email_apply_resume_mode') === 'fixed'
                ),
            ],
        ];
    }

    private function countryRequiresPostalCode(): bool
    {
        $code = $this->input('country_code');
        if (! is_string($code) || $code === '') {
            return false;
        }

        $country = SupportedCountry::tryFrom(mb_strtoupper($code));

        return $country !== null && $country->supportsLookup();
    }
}
