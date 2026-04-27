<?php

declare(strict_types=1);

namespace App\Domain\Account\Actions;

use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\OauthAccount;
use App\Models\Resume;
use App\Models\ResumeSection;
use App\Models\Skill;
use App\Models\User;

/**
 * Monta o bundle LGPD: todos os dados pessoais do usuário num único JSON.
 *
 * Inclui perfil, skills, currículos (sem binário — apenas metadados + path),
 * candidaturas com eventos, tokens OAuth vinculados e métricas materializadas.
 */
final class ExportUserData
{
    /**
     * @return array<string, mixed>
     */
    public function execute(User $user): array
    {
        $user->loadMissing([
            'profile.skills',
            'resumes.sections',
            'applications.job.company',
            'applications.events',
            'applications.resume',
            'oauthAccounts',
        ]);

        $profile = $user->profile;

        $profilePayload = null;
        if ($profile !== null) {
            $skills = [];
            foreach ($profile->skills as $skill) {
                /** @var Skill $skill */
                $skills[] = [
                    'name' => $skill->name,
                    'category' => $skill->category,
                    'proficiency' => $skill->pivot->proficiency ?? null,
                ];
            }

            $profilePayload = [
                'desired_role' => $profile->desired_role,
                'seniority' => $profile->seniority,
                'modality' => $profile->modality,
                'salary_min' => $profile->salary_min,
                'salary_max' => $profile->salary_max,
                'salary_currency' => $profile->salary_currency,
                'country_code' => $profile->country_code,
                'postal_code' => $profile->postal_code,
                'state_code' => $profile->state_code,
                'state_name' => $profile->state_name,
                'city' => $profile->city,
                'languages' => $profile->languages,
                'bio' => $profile->bio,
                'skills' => $skills,
            ];
        }

        $resumes = [];
        foreach ($user->resumes as $resume) {
            /** @var Resume $resume */
            $sections = [];
            foreach ($resume->sections as $section) {
                /** @var ResumeSection $section */
                $sections[] = [
                    'type' => $section->type,
                    'order' => $section->order,
                    'content' => $section->content,
                ];
            }

            $resumes[] = [
                'id' => $resume->id,
                'title' => $resume->title,
                'language' => $resume->language,
                'is_pdf_upload' => (bool) $resume->is_pdf_upload,
                'file_path' => $resume->file_path,
                'metadata' => $resume->metadata,
                'sections' => $sections,
                'created_at' => $resume->created_at?->toIso8601String(),
            ];
        }

        $applications = [];
        foreach ($user->applications as $application) {
            /** @var Application $application */
            $events = [];
            foreach ($application->events as $event) {
                /** @var ApplicationEvent $event */
                $events[] = [
                    'event_type' => $event->event_type,
                    'payload' => $event->payload,
                    'occurred_at' => $event->occurred_at?->toIso8601String(),
                ];
            }

            $applications[] = [
                'id' => $application->id,
                'status' => (string) $application->getRawOriginal('status'),
                'applied_at' => $application->applied_at?->toIso8601String(),
                'source' => $application->source,
                'notes' => $application->notes,
                'expected_salary' => $application->expected_salary,
                'job' => $application->job === null ? null : [
                    'title' => $application->job->title,
                    'company' => $application->job->company?->name,
                    'location' => $application->job->location,
                ],
                'resume_id' => $application->resume_id,
                'events' => $events,
            ];
        }

        $oauthAccounts = [];
        foreach ($user->oauthAccounts as $oauth) {
            /** @var OauthAccount $oauth */
            $oauthAccounts[] = [
                'provider' => $oauth->provider,
                'provider_id' => $oauth->provider_id,
                'linked_at' => $oauth->created_at?->toIso8601String(),
            ];
        }

        return [
            'exported_at' => now()->toIso8601String(),
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'locale' => $user->locale,
                'created_at' => $user->created_at?->toIso8601String(),
                'updated_at' => $user->updated_at?->toIso8601String(),
            ],
            'profile' => $profilePayload,
            'resumes' => $resumes,
            'applications' => $applications,
            'oauth_accounts' => $oauthAccounts,
        ];
    }
}
