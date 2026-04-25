<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Drivers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Enums\Modality;
use App\Enums\Seniority;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Gupy — listagem pública em https://portal.api.gupy.io/api/job
 *
 * A Gupy é o principal ATS BR; o "portal.api" expõe uma listagem pública de vagas
 * agregadas de todas as empresas clientes. Não exige autenticação e retorna JSON.
 */
final class GupyDriver implements JobSourceDriver
{
    private const ENDPOINT = 'https://portal.api.gupy.io/api/job';

    private const PAGE_SIZE = 50;

    private const MAX_PAGES = 10;

    private const STACK_KEYWORDS = [
        'php', 'laravel', 'symfony', 'ruby', 'rails', 'python', 'django', 'flask',
        'javascript', 'typescript', 'node', 'nodejs', 'react', 'vue', 'angular',
        'nextjs', 'nuxt', 'svelte', 'go', 'golang', 'rust', 'java', 'kotlin',
        'swift', 'c#', 'dotnet', '.net', 'elixir', 'phoenix', 'scala', 'clojure',
        'postgresql', 'postgres', 'mysql', 'mongodb', 'redis', 'aws', 'gcp',
        'azure', 'docker', 'kubernetes', 'terraform', 'devops',
    ];

    public function name(): string
    {
        return 'gupy';
    }

    public function fetch(): iterable
    {
        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $response = Http::acceptJson()
                ->withUserAgent('open-to-work/1.0 (https://opentowork.app.br)')
                ->timeout(30)
                ->retry(2, 1000)
                ->get(self::ENDPOINT, [
                    'limit' => self::PAGE_SIZE,
                    'offset' => ($page - 1) * self::PAGE_SIZE,
                ]);

            $response->throw();

            /** @var array{data?: list<array<string, mixed>>} $payload */
            $payload = $response->json() ?? [];
            $items = $payload['data'] ?? [];

            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                if (! isset($item['id'])) {
                    continue;
                }

                yield $this->toDto($item);
            }

            if (count($items) < self::PAGE_SIZE) {
                break;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toDto(array $item): JobDTO
    {
        $title = (string) ($item['name'] ?? '');
        $description = (string) ($item['description'] ?? '');

        return new JobDTO(
            source: $this->name(),
            externalId: (string) $item['id'],
            externalUrl: (string) ($item['jobUrl'] ?? ''),
            title: $title,
            companyName: (string) ($item['careerPageName'] ?? 'Unknown'),
            companyLogoUrl: isset($item['careerPageLogo']) ? (string) $item['careerPageLogo'] : null,
            descriptionHtml: $description,
            location: $this->formatLocation($item),
            modality: $this->modality($item),
            seniority: $this->guessSeniority($title),
            stack: $this->guessStack($title . ' ' . $description),
            salaryMin: null,
            salaryMax: null,
            salaryCurrency: null,
            postedAt: $this->parseDate((string) ($item['publishedDate'] ?? '')),
            expiresAt: null,
            language: 'pt',
        );
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function formatLocation(array $item): ?string
    {
        $parts = array_filter([
            isset($item['city']) ? (string) $item['city'] : null,
            isset($item['state']) ? (string) $item['state'] : null,
            isset($item['country']) ? (string) $item['country'] : null,
        ], static fn (?string $v): bool => $v !== null && $v !== '');

        return $parts === [] ? null : implode(', ', $parts);
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function modality(array $item): ?Modality
    {
        if (($item['isRemoteWork'] ?? false) === true) {
            return Modality::Remote;
        }

        $workplaceType = mb_strtolower((string) ($item['workplaceType'] ?? ''));

        return match ($workplaceType) {
            'remote' => Modality::Remote,
            'hybrid' => Modality::Hybrid,
            'on-site', 'onsite', 'presencial' => Modality::Onsite,
            default => null,
        };
    }

    private function guessSeniority(string $title): ?Seniority
    {
        $t = mb_strtolower($title);

        return match (true) {
            str_contains($t, 'estagi') || str_contains($t, 'intern') => Seniority::Intern,
            str_contains($t, 'junior') || str_contains($t, 'júnior') || str_contains($t, 'jr.') => Seniority::Junior,
            str_contains($t, 'principal') => Seniority::Principal,
            str_contains($t, 'staff') => Seniority::Staff,
            str_contains($t, 'senior') || str_contains($t, 'sênior') || str_contains($t, 'sr.') || str_contains($t, 'lead') => Seniority::Senior,
            str_contains($t, 'pleno') || str_contains($t, 'mid') => Seniority::Mid,
            default => null,
        };
    }

    /**
     * @return list<string>
     */
    private function guessStack(string $text): array
    {
        $lower = mb_strtolower($text);
        $found = [];
        foreach (self::STACK_KEYWORDS as $kw) {
            if (str_contains($lower, $kw)) {
                $found[] = $kw;
            }
        }

        return array_values(array_unique($found));
    }

    private function parseDate(string $raw): ?DateTimeImmutable
    {
        if ($raw === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($raw);
        } catch (Throwable) {
            return null;
        }
    }
}
