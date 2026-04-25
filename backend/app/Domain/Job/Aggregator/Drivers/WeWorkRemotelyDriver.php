<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Drivers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Enums\Modality;
use App\Enums\Seniority;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

/**
 * WeWorkRemotely — feed RSS em https://weworkremotely.com/categories/remote-programming-jobs.rss
 *
 * Título vem no formato "Company Name: Job Title". O <region> indica localização geral.
 * Stack é inferida do título (heurística leve — títulos mencionam tecnologias comuns).
 */
final class WeWorkRemotelyDriver implements JobSourceDriver
{
    private const ENDPOINT = 'https://weworkremotely.com/categories/remote-programming-jobs.rss';

    private const STACK_KEYWORDS = [
        'php', 'laravel', 'symfony', 'ruby', 'rails', 'python', 'django', 'flask',
        'javascript', 'typescript', 'node', 'nodejs', 'react', 'vue', 'angular',
        'nextjs', 'nuxt', 'svelte', 'go', 'golang', 'rust', 'java', 'kotlin',
        'swift', 'objective-c', 'c#', 'dotnet', '.net', 'elixir', 'phoenix',
        'scala', 'clojure', 'haskell', 'postgresql', 'postgres', 'mysql',
        'mongodb', 'redis', 'aws', 'gcp', 'azure', 'docker', 'kubernetes',
        'terraform', 'devops', 'sre',
    ];

    public function name(): string
    {
        return 'we_work_remotely';
    }

    public function fetch(): iterable
    {
        $response = Http::accept('application/rss+xml')
            ->withUserAgent('open-to-work/1.0 (https://opentowork.app.br)')
            ->timeout(30)
            ->retry(2, 1000)
            ->get(self::ENDPOINT);

        $response->throw();

        $xml = $this->parseXml($response->body());

        if ($xml === null) {
            return;
        }

        foreach ($xml->channel->item ?? [] as $item) {
            $dto = $this->toDto($item);
            if ($dto === null) {
                continue;
            }

            yield $dto;
        }
    }

    private function parseXml(string $body): ?SimpleXMLElement
    {
        try {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($body);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);

            return $xml === false ? null : $xml;
        } catch (Throwable) {
            return null;
        }
    }

    private function toDto(SimpleXMLElement $item): ?JobDTO
    {
        $link = trim((string) $item->link);
        $rawTitle = trim((string) $item->title);
        if ($link === '' || $rawTitle === '') {
            return null;
        }

        [$company, $title] = $this->splitTitle($rawTitle);
        $guid = trim((string) $item->guid) ?: $link;
        $description = (string) $item->description;
        $region = trim((string) ($item->region ?? ''));
        $pubDate = trim((string) $item->pubDate);

        return new JobDTO(
            source: $this->name(),
            externalId: $guid,
            externalUrl: $link,
            title: $title,
            companyName: $company,
            companyLogoUrl: null,
            descriptionHtml: $description,
            location: $region !== '' ? $region : 'Remote',
            modality: Modality::Remote,
            seniority: $this->guessSeniority($title),
            stack: $this->guessStack($title . ' ' . $description),
            salaryMin: null,
            salaryMax: null,
            salaryCurrency: null,
            postedAt: $this->parseDate($pubDate),
            expiresAt: null,
            language: 'en',
        );
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function splitTitle(string $raw): array
    {
        if (str_contains($raw, ':')) {
            [$company, $title] = array_map('trim', explode(':', $raw, 2));
            if ($company !== '' && $title !== '') {
                return [$company, $title];
            }
        }

        return ['Unknown', $raw];
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
