<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Drivers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Enums\Modality;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * GitHub Vagas — agrega vagas brasileiras publicadas como Issues em comunidades
 * GitHub (`frontendbr/vagas`, `backend-br/vagas`, etc.).
 *
 * Padrão de título nos issues: `[Local/Modalidade] Empresa - Cargo`. O body
 * (markdown) vai como description. Modality/location vêm dos `[brackets]` do
 * título; `stack` vem das `labels` do issue (filtrando categóricas como
 * senioridade/modalidade/contrato/locais BR comuns). `backend-br/vagas` taggeia
 * stack tecnológico nas labels; `frontendbr/vagas` não — vagas desse repo
 * costumam vir com `stack: []`, conforme esperado.
 *
 * Sem token: 60 req/h. Com `GITHUB_TOKEN` no .env: 5000 req/h.
 */
final class GitHubVagasDriver implements JobSourceDriver
{
    private const ENDPOINT_TEMPLATE = 'https://api.github.com/repos/%s/%s/issues';

    private const PAGE_SIZE = 100;

    private const MAX_PAGES = 5;

    /**
     * Labels que são categóricas (senioridade/modalidade/contrato/local) e não
     * representam stack tecnológico. Comparadas case-insensitive depois de trim.
     */
    private const NON_STACK_LABELS = [
        // Senioridade
        'estágio', 'estagio', 'intern', 'internship',
        'júnior', 'junior', 'jr', 'jr.',
        'pleno', 'mid',
        'sênior', 'senior', 'sr', 'sr.',
        'especialista', 'lead', 'tech lead',
        'staff', 'principal',
        // Modalidade
        'remoto', 'remote',
        'híbrido', 'hibrido', 'hybrid',
        'presencial', 'onsite', 'on-site', 'on site', 'home office',
        // Contrato
        'clt', 'pj', 'pjs', 'freelance', 'freela', 'temporário', 'temporario',
        // Localização (estados/capitais BR mais usados como label)
        'são paulo', 'sao paulo', 'rio de janeiro', 'minas gerais',
        'brasília', 'brasilia', 'curitiba', 'porto alegre', 'salvador',
        'fortaleza', 'recife', 'belo horizonte', 'florianópolis', 'florianopolis',
        'campinas', 'goiânia', 'goiania', 'manaus', 'natal',
        'exterior', 'brasil', 'brazil',
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
            $owner = $entry['owner'];
            $repo = $entry['repo'];
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

            $token = (string) config('aggregator.github_token', '');
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
        /** @var list<array<string, mixed>> $rawLabels */
        $rawLabels = is_array($item['labels'] ?? null) ? $item['labels'] : [];
        $stack = $this->stackFromLabels($rawLabels);

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
            seniority: null,
            stack: $stack,
            salaryMin: null,
            salaryMax: null,
            salaryCurrency: null,
            postedAt: $this->parseDate((string) ($item['created_at'] ?? '')),
            expiresAt: null,
            language: 'pt_BR',
        );
    }

    /**
     * Extrai stack das labels do issue, descartando as categóricas.
     *
     * @param  list<array<string, mixed>>  $labels
     * @return list<string>
     */
    private function stackFromLabels(array $labels): array
    {
        $stack = [];
        foreach ($labels as $label) {
            $name = trim((string) ($label['name'] ?? ''));
            if ($name === '') {
                continue;
            }
            $low = mb_strtolower($name);
            if (in_array($low, self::NON_STACK_LABELS, true)) {
                continue;
            }
            $stack[] = $low;
        }

        return array_values(array_unique($stack));
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
