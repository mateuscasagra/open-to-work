<?php

declare(strict_types=1);

namespace App\Domain\Application\Actions;

use App\Domain\Application\DTOs\ApplicationData;
use App\Enums\EmailApplyMode;
use App\Mail\ApplicationEmail;
use App\Models\Application;
use App\Models\Resume;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

final class SendApplicationEmail
{
    public function execute(Application $application, ApplicationData $data): void
    {
        try {
            $application->load(['job.company', 'user']);
            $user = $application->user;
            $job = $application->job;

            // Precondition: job must have contact_email
            if (! $job->contact_email) {
                return;
            }

            $profile = $user->profile()->with('emailApplyResume')->first();

            // Precondition: email apply must be enabled
            if (! $profile || ! $profile->email_apply_enabled) {
                return;
            }

            // Resolve message
            $message = $this->resolveMessage($profile, $data, $job->title, $job->company?->name);

            if ($message === null) {
                return;
            }

            // Resolve resume
            $resume = $this->resolveResume($profile, $data);

            Mail::to($job->contact_email)
                ->replyTo($user->email)
                ->queue(new ApplicationEmail(
                    body: $message,
                    candidateName: $user->name,
                    jobTitle: $job->title,
                    resumePath: $resume?->file_path,
                    resumeTitle: $resume?->title,
                ));

            $application->update(['sent_via_email_at' => now()]);
        } catch (\Throwable $e) {
            Log::warning('SendApplicationEmail failed', [
                'application_id' => $application->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function resolveMessage(
        mixed $profile,
        ApplicationData $data,
        string $jobTitle,
        ?string $companyName,
    ): ?string {
        $message = null;

        // Override takes priority
        if ($data->emailMessageOverride !== null) {
            $message = $data->emailMessageOverride;
        } elseif ($profile->email_apply_message_mode === EmailApplyMode::Fixed) {
            $message = $profile->email_apply_message_template;
        }

        if ($message === null) {
            return null;
        }

        // Replace placeholders
        $message = str_replace('{cargo}', $jobTitle, $message);
        $message = str_replace('{empresa}', $companyName ?? '', $message);

        return $message;
    }

    private function resolveResume(mixed $profile, ApplicationData $data): ?Resume
    {
        // Override takes priority
        if ($data->emailResumeIdOverride !== null) {
            return Resume::find($data->emailResumeIdOverride);
        }

        if ($profile->email_apply_resume_mode === EmailApplyMode::Fixed && $profile->emailApplyResume) {
            return $profile->emailApplyResume;
        }

        return null;
    }
}
