<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\EmailApplyMode;
use App\Enums\Modality;
use App\Enums\Seniority;
use App\Enums\SupportedLocale;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        $profile = $request->user()->profile()->with('skills')->firstOrCreate([]);

        return response()->json($profile);
    }

    public function update(Request $request): JsonResponse
    {
        $data = $request->validate([
            'desired_role' => ['nullable', 'string', 'max:255'],
            'seniority' => ['nullable', Rule::enum(Seniority::class)],
            'modality' => ['nullable', Rule::enum(Modality::class)],
            'salary_min' => ['nullable', 'integer', 'min:0'],
            'salary_max' => ['nullable', 'integer', 'gte:salary_min'],
            'salary_currency' => ['nullable', 'string', 'size:3'],
            'location' => ['nullable', 'string', 'max:255'],
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
                    fn () => $request->boolean('email_apply_enabled')
                        && $request->input('email_apply_message_mode') === 'fixed'
                ),
            ],
            'email_apply_resume_mode' => ['nullable', Rule::enum(EmailApplyMode::class)],
            'email_apply_resume_id' => [
                'nullable',
                'integer',
                Rule::exists('resumes', 'id')->where('user_id', $request->user()?->id),
                Rule::requiredIf(
                    fn () => $request->boolean('email_apply_enabled')
                        && $request->input('email_apply_resume_mode') === 'fixed'
                ),
            ],
        ]);

        $profile = $request->user()->profile()->firstOrCreate([]);
        $profile->fill($data)->save();

        if (isset($data['skills'])) {
            $profile->skills()->sync($data['skills']);
        }

        return response()->json($profile->load('skills'));
    }
}
