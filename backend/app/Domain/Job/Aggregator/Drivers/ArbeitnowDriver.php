<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Drivers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Enums\Modality;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Arbeitnow — API JSON pública em https://www.arbeitnow.com/api/job-board-api
 *
 * Vagas europeias (BE/DE em peso). Payload: {data: [{slug, title, company_name, remote, tags, created_at (unix)}]}.
 */
final class ArbeitnowDriver implements JobSourceDriver
{
    private const ENDPOINT = 'https://www.arbeitnow.com/api/job-board-api';

    public function name(): string
    {
        return 'arbeitnow';
    }

    public function fetch(): iterable
    {
        $response = Http::acceptJson()
            ->withUserAgent('open-to-work/1.0 (https://opentowork.app.br)')
            ->timeout(30)
            ->retry(2, 1000)
            ->get(self::ENDPOINT);

        $response->throw();

        /** @var array{data?: list<array<string, mixed>>} $payload */
        $payload = $response->json() ?? [];
        $items = $payload['data'] ?? [];

        foreach ($items as $item) {
            if (! isset($item['slug'])) {
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
        $remote = (bool) ($item['remote'] ?? false);

        return new JobDTO(
            source: $this->name(),
            externalId: (string) $item['slug'],
            externalUrl: (string) ($item['url'] ?? ''),
            title: (string) ($item['title'] ?? ''),
            companyName: (string) ($item['company_name'] ?? 'Unknown'),
            companyLogoUrl: null,
            descriptionHtml: (string) ($item['description'] ?? ''),
            location: $item['location'] ?? null,
            modality: $remote ? Modality::Remote : Modality::Onsite,
            seniority: null,
            stack: is_array($item['tags'] ?? null) ? array_values(array_filter($item['tags'])) : [],
            salaryMin: null,
            salaryMax: null,
            salaryCurrency: null,
            postedAt: isset($item['created_at']) ? (new DateTimeImmutable)->setTimestamp((int) $item['created_at']) : null,
            expiresAt: null,
            language: 'en',
        );
    }
}
