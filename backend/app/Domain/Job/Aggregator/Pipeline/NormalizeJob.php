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
    /** Domains that should never be used as contact email. */
    private const BLOCKED_DOMAINS = [
        '@gupy.io',
        '@greenhouse.io',
        '@lever.co',
        '@workday.com',
        '@smartrecruiters.com',
        '@breezy.hr',
        '@recruitee.com',
    ];

    public function handle(JobDTO $job, Closure $next): mixed
    {
        $contactEmail = $this->extractContactEmail($job->descriptionHtml);

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
            contactEmail: $contactEmail,
        );

        return $next($normalized);
    }

    private function normalizeTitle(string $title): string
    {
        $title = trim($title);

        return preg_replace('/\s+/', ' ', $title) ?? $title;
    }

    private function extractContactEmail(string $html): ?string
    {
        $email = null;

        // Strategy 1: try mailto: links in original HTML
        if (preg_match('/mailto:([^"\'\s>]+)/i', $html, $matches)) {
            $email = strtolower(trim($matches[1]));
        }

        // Strategy 2: fallback to generic email regex on stripped text
        if ($email === null) {
            $text = strip_tags($html);
            if (preg_match('/[\w.+\-]+@[\w.\-]+\.[a-z]{2,}/i', $text, $matches)) {
                $email = strtolower(trim($matches[0]));
            }
        }

        if ($email === null) {
            return null;
        }

        // Exclude blocked domains / prefixes
        if (str_starts_with($email, 'noreply@') || str_starts_with($email, 'no-reply@')) {
            return null;
        }

        foreach (self::BLOCKED_DOMAINS as $domain) {
            if (str_ends_with($email, $domain)) {
                return null;
            }
        }

        return $email;
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
