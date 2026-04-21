<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Pipeline;

use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Models\Company;
use App\Models\Job;
use App\Models\JobSource;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Estágio 3: persiste a vaga e seu vínculo com a fonte.
 */
final class PersistJob
{
    /**
     * @param  array{0: JobDTO, 1: Job|null}  $payload
     */
    public function handle(array $payload, Closure $next): Job
    {
        [$dto, $existing] = $payload;

        return DB::transaction(function () use ($dto, $existing, $next): Job {
            $job = $existing ?? $this->createJob($dto);

            JobSource::query()->updateOrCreate(
                [
                    'job_id' => $job->id,
                    'source' => $dto->source,
                ],
                [
                    'external_id' => $dto->externalId,
                    'external_url' => $dto->externalUrl,
                    'fetched_at' => now(),
                ]
            );

            return $next($job);
        });
    }

    private function createJob(JobDTO $dto): Job
    {
        $company = Company::query()->firstOrCreate(
            ['name' => $dto->companyName],
            ['logo_url' => $dto->companyLogoUrl]
        );

        return Job::query()->create([
            'canonical_hash' => $dto->canonicalHash(),
            'title' => $dto->title,
            'company_id' => $company->id,
            'description_html' => $dto->descriptionHtml,
            'location' => $dto->location,
            'modality' => $dto->modality?->value,
            'seniority' => $dto->seniority?->value,
            'stack' => $dto->stack,
            'salary_min' => $dto->salaryMin,
            'salary_max' => $dto->salaryMax,
            'salary_currency' => $dto->salaryCurrency,
            'language' => $dto->language,
            'posted_at' => $dto->postedAt,
            'expires_at' => $dto->expiresAt,
            'active' => true,
        ]);
    }
}
