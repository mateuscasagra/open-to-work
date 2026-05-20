<?php

declare(strict_types=1);

namespace App\Domain\Application\Actions;

use App\Domain\Application\DTOs\ApplicationData;
use App\Domain\Application\Exceptions\DuplicateApplicationException;
use App\Domain\Subscription\DTOs\QuotaData;
use App\Domain\Subscription\Exceptions\QuotaExceededException;
use App\Enums\ApplicationStatus;
use App\Events\ApplicationCreated;
use App\Models\Application;
use App\Models\Job;
use App\Models\User;

final class CreateApplication
{
    private const NOTES_MAX_LENGTH = 5000;

    public function __construct(
        private readonly SendApplicationEmail $sendEmail,
    ) {}

    public function execute(User $user, ApplicationData $data): Application
    {
        $this->enforceQuota($user);

        $job = null;

        if ($data->jobId !== null) {
            $job = Job::with('sources')->find($data->jobId);

            $exists = Application::query()
                ->where('user_id', $user->id)
                ->where('job_id', $data->jobId)
                ->exists();

            if ($exists) {
                throw new DuplicateApplicationException;
            }
        }

        $application = Application::query()->create([
            'user_id' => $user->id,
            'job_id' => $data->jobId,
            'manual_title' => $data->manualTitle,
            'manual_company' => $data->manualCompany,
            'job_url' => $data->jobUrl ?? $job?->sources->first()?->external_url,
            'resume_id' => $data->resumeId,
            'status' => ApplicationStatus::Applied->value,
            'applied_at' => now(),
            'source' => $data->source ?? ($data->jobId === null ? 'manual' : null),
            'notes' => $data->notes ?? ($job !== null ? $this->extractTextFromHtml($job->description_html) : null),
            'expected_salary' => $data->expectedSalary,
        ]);

        event(new ApplicationCreated($application));

        if ($data->jobId !== null) {
            $this->sendEmail->execute($application, $data);
        }

        return $application;
    }

    /**
     * Bloqueia criação se user free passou de QuotaData::FREE_MONTHLY_LIMIT no
     * mês calendário (America/Sao_Paulo). Pro = sem limite.
     *
     * Decisão: regra de negócio fica na Action (não em Middleware/Policy) pelo
     * mesmo motivo de `CreateSuggestion` + `WeeklyQuotaExceededException`:
     * domínio + atomicidade lado-a-lado com o INSERT.
     */
    private function enforceQuota(User $user): void
    {
        $user->loadMissing('subscription');

        if ($user->isPro()) {
            return;
        }

        $used = $user->applications()
            ->where('applied_at', '>=', QuotaData::monthStart())
            ->count();

        if ($used >= QuotaData::FREE_MONTHLY_LIMIT) {
            throw new QuotaExceededException(
                used: $used,
                limit: QuotaData::FREE_MONTHLY_LIMIT,
                resetAt: QuotaData::nextMonthStart()->toIso8601String(),
                plan: 'free',
            );
        }
    }

    private function extractTextFromHtml(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        $text = preg_replace('#<br\s*/?>#i', "\n", $html) ?? $html;
        $text = preg_replace('#</p>|</div>|</h[1-6]>|</li>#i', "\n", $text) ?? $text;
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;
        $text = trim($text);

        if ($text === '') {
            return null;
        }

        if (mb_strlen($text) > self::NOTES_MAX_LENGTH) {
            $text = mb_substr($text, 0, self::NOTES_MAX_LENGTH - 1) . '…';
        }

        return $text;
    }
}
