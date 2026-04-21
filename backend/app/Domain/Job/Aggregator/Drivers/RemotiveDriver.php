<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Drivers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Enums\Modality;
use App\Enums\Seniority;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Remotive — API JSON pública em https://remotive.com/api/remote-jobs
 *
 * Payload: {jobs: [...]}. Todas as vagas são remotas por definição.
 * Parseia senioridade do título e salário do formato "USD 100k - 150k".
 */
final class RemotiveDriver implements JobSourceDriver
{
    private const ENDPOINT = 'https://remotive.com/api/remote-jobs';

    public function name(): string
    {
        return 'remotive';
    }

    public function fetch(): iterable
    {
        $response = Http::acceptJson()
            ->withUserAgent('open-to-work/1.0 (https://opentowork.app)')
            ->timeout(30)
            ->retry(2, 1000)
            ->get(self::ENDPOINT);

        $response->throw();

        /** @var array{jobs?: list<array<string, mixed>>} $payload */
        $payload = $response->json() ?? [];
        $items = $payload['jobs'] ?? [];

        foreach ($items as $item) {
            if (! isset($item['id'])) {
                continue;
            }

            yield $this->toDto($item);
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toDto(array $item): JobDTO
    {
        [$min, $max, $currency] = $this->parseSalary((string) ($item['salary'] ?? ''));

        return new JobDTO(
            source: $this->name(),
            externalId: (string) $item['id'],
            externalUrl: (string) ($item['url'] ?? ''),
            title: (string) ($item['title'] ?? ''),
            companyName: (string) ($item['company_name'] ?? 'Unknown'),
            companyLogoUrl: $item['company_logo'] ?? null,
            descriptionHtml: (string) ($item['description'] ?? ''),
            location: $item['candidate_required_location'] ?? 'Remote',
            modality: Modality::Remote,
            seniority: $this->guessSeniority((string) ($item['title'] ?? '')),
            stack: is_array($item['tags'] ?? null) ? array_values(array_filter($item['tags'])) : [],
            salaryMin: $min,
            salaryMax: $max,
            salaryCurrency: $currency,
            postedAt: isset($item['publication_date']) ? new DateTimeImmutable((string) $item['publication_date']) : null,
            expiresAt: null,
            language: 'en',
        );
    }

    private function guessSeniority(string $title): ?Seniority
    {
        $t = mb_strtolower($title);

        return match (true) {
            str_contains($t, 'intern') => Seniority::Intern,
            str_contains($t, 'junior') || str_contains($t, 'jr.') => Seniority::Junior,
            str_contains($t, 'principal') => Seniority::Principal,
            str_contains($t, 'staff') => Seniority::Staff,
            str_contains($t, 'senior') || str_contains($t, 'sr.') || str_contains($t, 'lead') => Seniority::Senior,
            str_contains($t, 'mid') || str_contains($t, 'pleno') => Seniority::Mid,
            default => null,
        };
    }

    /**
     * @return array{0: ?int, 1: ?int, 2: ?string}
     */
    private function parseSalary(string $raw): array
    {
        if ($raw === '') {
            return [null, null, null];
        }

        // Matches "USD 100k - 150k", "USD $100,000 - $150,000", "EUR 60000-80000"
        if (preg_match('/([A-Z]{3})[\s$]*([\d,.]+)\s*(k|K)?\s*[-–]\s*[$]?([\d,.]+)\s*(k|K)?/u', $raw, $m)) {
            $min = (int) str_replace([',', '.'], '', $m[2]);
            $max = (int) str_replace([',', '.'], '', $m[4]);
            if (! empty($m[3])) {
                $min *= 1000;
            }
            if (! empty($m[5])) {
                $max *= 1000;
            }

            return [$min, $max, $m[1]];
        }

        return [null, null, null];
    }
}
