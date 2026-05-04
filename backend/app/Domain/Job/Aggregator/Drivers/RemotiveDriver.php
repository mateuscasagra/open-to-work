<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Drivers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Domain\Job\Aggregator\Filters\ProgrammingJobFilter;
use App\Enums\Modality;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;

/**
 * Remotive — API JSON pública em https://remotive.com/api/remote-jobs
 *
 * Payload: {jobs: [...]}. Todas as vagas são remotas por definição.
 * Stack vem de `tags` da API; seniority não é fornecida pelo payload.
 * Parseia salário do formato "USD 100k - 150k".
 *
 * A request usa `?category=software-dev` (filtro nativo da API), e ainda assim
 * aplicamos `ProgrammingJobFilter` como defesa em profundidade caso a categoria
 * mude ou venha vaga híbrida no payload.
 */
final class RemotiveDriver implements JobSourceDriver
{
    private const ENDPOINT = 'https://remotive.com/api/remote-jobs';

    private const CATEGORY = 'software-dev';

    public function __construct(private readonly ProgrammingJobFilter $filter = new ProgrammingJobFilter) {}

    public function name(): string
    {
        return 'remotive';
    }

    public function fetch(): iterable
    {
        $response = Http::acceptJson()
            ->withUserAgent('open-to-work/1.0 (https://opentowork.app.br)')
            ->timeout(30)
            ->retry(2, 1000)
            ->get(self::ENDPOINT, ['category' => self::CATEGORY]);

        $response->throw();

        /** @var array{jobs?: list<array<string, mixed>>} $payload */
        $payload = $response->json() ?? [];
        $items = $payload['jobs'] ?? [];

        foreach ($items as $item) {
            if (! isset($item['id'])) {
                continue;
            }

            $title = (string) ($item['title'] ?? '');
            $tags = is_array($item['tags'] ?? null) ? array_values(array_filter($item['tags'])) : [];
            if (! $this->filter->isProgrammingJob($title, $tags)) {
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
            seniority: null,
            stack: is_array($item['tags'] ?? null) ? array_values(array_filter($item['tags'])) : [],
            salaryMin: $min,
            salaryMax: $max,
            salaryCurrency: $currency,
            postedAt: isset($item['publication_date']) ? new DateTimeImmutable((string) $item['publication_date']) : null,
            expiresAt: null,
            language: 'en',
        );
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
