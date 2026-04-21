<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Pipeline;

use App\Domain\Job\Aggregator\DTOs\JobDTO;
use Closure;

/**
 * Estágio 1 do pipeline: limpa strings, remove HTML perigoso, normaliza stack.
 */
final class NormalizeJob
{
    public function handle(JobDTO $job, Closure $next): mixed
    {
        $normalized = new JobDTO(
            source: $job->source,
            externalId: trim($job->externalId),
            externalUrl: trim($job->externalUrl),
            title: $this->normalizeTitle($job->title),
            companyName: trim($job->companyName),
            companyLogoUrl: $job->companyLogoUrl,
            descriptionHtml: strip_tags($job->descriptionHtml, '<p><br><ul><ol><li><strong><em><a>'),
            location: $job->location !== null ? trim($job->location) : null,
            modality: $job->modality,
            seniority: $job->seniority,
            stack: $this->normalizeStack($job->stack),
            salaryMin: $job->salaryMin,
            salaryMax: $job->salaryMax,
            salaryCurrency: $job->salaryCurrency,
            postedAt: $job->postedAt,
            expiresAt: $job->expiresAt,
            language: $job->language,
        );

        return $next($normalized);
    }

    private function normalizeTitle(string $title): string
    {
        $title = trim($title);

        return preg_replace('/\s+/', ' ', $title) ?? $title;
    }

    /**
     * @param  list<string>  $stack
     * @return list<string>
     */
    private function normalizeStack(array $stack): array
    {
        $clean = array_map(
            static fn (string $tag): string => mb_strtolower(trim($tag)),
            $stack
        );

        return array_values(array_unique(array_filter($clean)));
    }
}
