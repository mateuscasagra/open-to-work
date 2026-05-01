<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Drivers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Enums\Modality;
use App\Enums\Seniority;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GitHub Vagas — agrega vagas brasileiras publicadas como Issues em comunidades
 * GitHub (`frontendbr/vagas`, `backend-br/vagas`, etc.).
 *
 * Padrão de título nos issues: `[Local/Modalidade] Empresa - Cargo`. O body
 * (markdown) vai como description e NormalizeJob extrai contact_email/stack.
 *
 * Sem token: 60 req/h. Com `GITHUB_TOKEN` no .env: 5000 req/h.
 */
final class GitHubVagasDriver implements JobSourceDriver
{
    private const ENDPOINT_TEMPLATE = 'https://api.github.com/repos/%s/%s/issues';

    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 5;

    private const STACK_KEYWORDS = [
        'php', 'laravel', 'symfony', 'ruby', 'rails', 'python', 'django', 'flask',
        'javascript', 'typescript', 'node', 'nodejs', 'react', 'vue', 'angular',
        'nextjs', 'nuxt', 'svelte', 'go', 'golang', 'rust', 'java', 'kotlin',
        'swift', 'c#', 'dotnet', '.net', 'elixir', 'phoenix', 'scala', 'clojure',
        'postgresql', 'postgres', 'mysql', 'mongodb', 'redis', 'aws', 'gcp',
        'azure', 'docker', 'kubernetes', 'terraform', 'devops', 'flutter',
    ];

    public function name(): string
    {
        return 'github_vagas';
    }

    public function fetch(): iterable
    {
        /** @var list<array{owner: string, repo: string}> $repos */
        $repos = config('aggregator.github_repos', []);

        foreach ($repos as $entry) {
            $owner = (string) ($entry['owner'] ?? '');
            $repo = (string) ($entry['repo'] ?? '');
            if ($owner === '' || $repo === '') {
                continue;
            }

            try {
                yield from $this->fetchRepo($owner, $repo);
            } catch (Throwable $e) {
                Log::warning('github_vagas: falha ao buscar repositório', [
                    'owner' => $owner,
                    'repo' => $repo,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    /**
     * @return iterable<JobDTO>
     */
    private function fetchRepo(string $owner, string $repo): iterable
    {
        $endpoint = sprintf(self::ENDPOINT_TEMPLATE, $owner, $repo);

        for ($page = 1; $page <= self::MAX_PAGES; $page++) {
            $request = Http::acceptJson()
                ->withUserAgent('open-to-work/1.0 (https://opentowork.app.br)')
                ->withHeaders(['X-GitHub-Api-Version' => '2022-11-28'])
                ->timeout(30)
                ->retry(2, 1000);

            $token = (string) env('GITHUB_TOKEN', '');
            if ($token !== '') {
                $request = $request->withToken($token);
            }

            $response = $request->get($endpoint, [
                'state' => 'open',
                'per_page' => self::PAGE_SIZE,
                'page' => $page,
            ]);

            $response->throw();

            /** @var list<array<string, mixed>> $items */
            $items = $response->json() ?? [];
            if ($items === []) {
                break;
            }

            foreach ($items as $item) {
                // GitHub mistura PRs em /issues — filtra.
                if (isset($item['pull_request'])) {
                    continue;
                }
                if (! isset($item['number'], $item['title'], $item['html_url'])) {
                    continue;
                }
                yield $this->toDto($item, $owner, $repo);
            }

            if (count($items) < self::PAGE_SIZE) {
                break;
            }
        }
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function toDto(array $item, string $owner, string $repo): JobDTO
    {
        $title = (string) $item['title'];
        $body = (string) ($item['body'] ?? '');

        [$tags, $remainder] = $this->splitTitle($title);
        [$companyName, $jobTitle] = $this->splitCompanyAndRole($remainder);

        $modality = $this->modalityFromTags($tags);
        $location = $this->locationFromTags($tags);
        $haystack = $title . ' ' . $body;

        return new JobDTO(
            source: $this->name(),
            externalId: sprintf('%s/%s#%s', $owner, $repo, (string) $item['number']),
            externalUrl: (string) $item['html_url'],
            title: $jobTitle !== '' ? $jobTitle : $title,
            companyName: $companyName !== '' ? $companyName : 'Comunidade GitHub',
            companyLogoUrl: null,
            descriptionHtml: $body,
            location: $location,
            modality: $modality,
            seniority: $this->guessSeniority($title),
            stack: $this->guessStack($haystack),
            salaryMin: null,
            salaryMax: null,
            salaryCurrency: null,
            postedAt: $this->parseDate((string) ($item['created_at'] ?? '')),
            expiresAt: null,
            language: 'pt_BR',
        );
    }

    /**
     * Extrai tags entre colchetes do início do título e devolve o restante.
     *
     * @return array{0: list<string>, 1: string}
     */
    private function splitTitle(string $title): array
    {
        $tags = [];
        $rest = trim($title);
        while (preg_match('/^\[([^\]]+)\]\s*/u', $rest, $m) === 1) {
            $tags[] = trim($m[1]);
            $rest = (string) preg_replace('/^\[[^\]]+\]\s*/u', '', $rest, 1);
        }

        return [$tags, trim($rest)];
    }

    /**
     * Separa a parte após os colchetes em (empresa, cargo) usando ` - ` ou variantes.
     *
     * @return array{0: string, 1: string}
     */
    private function splitCompanyAndRole(string $remainder): array
    {
        if ($remainder === '') {
            return ['', ''];
        }

        $parts = preg_split('/\s+[-–—]\s+/u', $remainder, 2) ?: [];
        if (count($parts) === 2) {
            return [trim($parts[0]), trim($parts[1])];
        }

        return ['', trim($remainder)];
    }

    /**
     * @param  list<string>  $tags
     */
    private function modalityFromTags(array $tags): ?Modality
    {
        foreach ($tags as $tag) {
            $low = mb_strtolower($tag);
            if (str_contains($low, 'remoto') || str_contains($low, 'remote') || str_contains($low, 'home office') || str_contains($low, 'homeoffice')) {
                return Modality::Remote;
            }
            if (str_contains($low, 'híbrid') || str_contains($low, 'hibrid') || str_contains($low, 'hybrid')) {
                return Modality::Hybrid;
            }
            if (str_contains($low, 'presencial') || str_contains($low, 'on-site') || str_contains($low, 'onsite') || str_contains($low, 'on site')) {
                return Modality::Onsite;
            }
        }

        return null;
    }

    /**
     * @param  list<string>  $tags
     */
    private function locationFromTags(array $tags): ?string
    {
        $skipKeywords = [
            'remoto', 'remote', 'híbrid', 'hibrid', 'hybrid', 'presencial',
            'onsite', 'on-site', 'on site', 'home office', 'homeoffice',
            'clt', 'pj', 'pjs', 'estágio', 'estagio', 'internship', 'intern',
            'freelance', 'freela', 'temporário', 'temporario',
        ];

        foreach ($tags as $tag) {
            $low = mb_strtolower($tag);
            $isSkip = false;
            foreach ($skipKeywords as $kw) {
                if (str_contains($low, $kw)) {
                    $isSkip = true;
                    break;
                }
            }
            if (! $isSkip) {
                return $tag;
            }
        }

        return null;
    }

    private function guessSeniority(string $title): ?Seniority
    {
        $t = mb_strtolower($title);

        return match (true) {
            str_contains($t, 'estagi') || str_contains($t, 'estági') || str_contains($t, 'intern') => Seniority::Intern,
            str_contains($t, 'junior') || str_contains($t, 'júnior') || str_contains($t, 'jr.') || str_contains($t, 'jr ') => Seniority::Junior,
            str_contains($t, 'principal') => Seniority::Principal,
            str_contains($t, 'staff') => Seniority::Staff,
            str_contains($t, 'senior') || str_contains($t, 'sênior') || str_contains($t, 'sr.') || str_contains($t, 'sr ') || str_contains($t, 'lead') => Seniority::Senior,
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
