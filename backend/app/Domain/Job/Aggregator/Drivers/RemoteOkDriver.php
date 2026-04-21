<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Drivers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Enums\Modality;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

/**
 * RemoteOK — API JSON pública em https://remoteok.com/api
 *
 * Vagas majoritariamente remotas internacionais. Rate limit generoso, sem autenticação.
 */
final class RemoteOkDriver implements JobSourceDriver
{
    private const ENDPOINT = 'https://remoteok.com/api';

    public function name(): string
    {
        return 'remote_ok';
    }

    public function fetch(): iterable
    {
        $response = Http::acceptJson()
            ->withUserAgent('open-to-work/1.0 (https://opentowork.app)')
            ->timeout(30)
            ->retry(2, 1000)
            ->get(self::ENDPOINT);

        $response->throw();

        /** @var list<array<string, mixed>> $items */
        $items = $response->json();

        foreach ($items as $item) {
            // primeiro item é meta; pular
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
        return new JobDTO(
            source: $this->name(),
            externalId: (string) $item['id'],
            externalUrl: (string) ($item['url'] ?? $item['apply_url'] ?? ''),
            title: (string) ($item['position'] ?? ''),
            companyName: (string) ($item['company'] ?? 'Unknown'),
            companyLogoUrl: $item['company_logo'] ?? $item['logo'] ?? null,
            descriptionHtml: (string) ($item['description'] ?? ''),
            location: $item['location'] ?? 'Remote',
            modality: Modality::Remote,
            seniority: null,
            stack: is_array($item['tags'] ?? null) ? array_values(array_filter($item['tags'])) : [],
            salaryMin: isset($item['salary_min']) ? (int) $item['salary_min'] : null,
            salaryMax: isset($item['salary_max']) ? (int) $item['salary_max'] : null,
            salaryCurrency: 'USD',
            postedAt: isset($item['date']) ? new DateTimeImmutable($item['date']) : null,
            expiresAt: null,
            language: 'en',
        );
    }
}
