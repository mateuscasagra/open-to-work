<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Filters;

/**
 * Decide se uma vaga, vinda de uma fonte generalista, é vaga de programação/tech.
 *
 * Usado pelos drivers internacionais (RemoteOk, Remotive, Arbeitnow,
 * WeWorkRemotely) para descartar vagas de marketing/design/sales/HR/etc. antes
 * mesmo de chegarem à pipeline de normalização. O `GitHubVagasDriver` lê
 * comunidades de programação BR por definição e não usa o filtro.
 *
 * Heurística: faz match case-insensitive de qualquer padrão da whitelist no
 * conjunto `título + tags`. A whitelist mistura cargos consolidados (developer,
 * engineer, devops, sre, ...) com linguagens e frameworks. Match em qualquer
 * lugar é suficiente — a precisão nas fontes é alta porque tags tecnológicas
 * raramente aparecem em vagas não-tech.
 */
final class ProgrammingJobFilter
{
    /**
     * Padrões que descartam o título imediatamente, mesmo que ele tenha
     * keyword tech (ex.: `Mechanical Engineer` casa com `engineer` mas é
     * engenharia mecânica). Avaliados antes da whitelist.
     */
    private const NON_PROGRAMMING_PATTERNS = [
        'mechanical engineer', 'civil engineer', 'electrical engineer',
        'chemical engineer', 'industrial engineer', 'biomedical engineer',
        'aerospace engineer', 'environmental engineer', 'petroleum engineer',
        'structural engineer', 'sales engineer',
    ];

    /**
     * Padrões case-insensitive que indicam vaga de programação. Match por
     * substring (`str_contains`) — entradas curtas como `php` ou `go` precisam
     * ter contexto suficiente pra evitar falsos positivos (ex.: `go ` com espaço
     * pra não casar com palavras que contêm `go`).
     */
    private const PROGRAMMING_PATTERNS = [
        // Cargos / funções
        'developer', 'engineer', 'programmer', 'coder',
        'devops', 'devsecops', 'sre', 'site reliability',
        'fullstack', 'full stack', 'full-stack',
        'backend', 'back-end', 'back end',
        'frontend', 'front-end', 'front end',
        'software',
        'web developer', 'web dev',
        'mobile developer', 'mobile dev',
        'data engineer', 'data scientist',
        'machine learning', 'ml engineer', 'mlops',
        'ai engineer',
        'cloud engineer', 'platform engineer',
        'security engineer', 'cybersecurity',
        'qa engineer', 'qa automation', 'sdet', 'test automation',
        'tech lead', 'staff engineer', 'principal engineer',
        'embedded', 'firmware',
        'blockchain', 'smart contract',
        // Linguagens
        'php', 'python', 'javascript', 'typescript', 'ruby on rails', 'ruby',
        'kotlin', 'swift', 'golang', 'rust', 'scala', 'elixir',
        'clojure', 'haskell', 'c#', 'c++', 'objective-c', '.net', 'dotnet',
        // Frameworks
        'laravel', 'symfony', 'rails', 'django', 'flask', 'fastapi',
        'react', 'vue', 'angular', 'next.js', 'nextjs', 'nuxt', 'svelte',
        'phoenix', 'spring boot', 'flutter',
        'node.js', 'nodejs',
        // Infra / DevOps
        'kubernetes', 'docker', 'terraform', 'ansible',
        'graphql', 'microservices',
    ];

    /**
     * @param  iterable<int, mixed>  $tags
     */
    public function isProgrammingJob(string $title, iterable $tags = []): bool
    {
        $tagList = [];
        foreach ($tags as $tag) {
            if (is_string($tag) && $tag !== '') {
                $tagList[] = $tag;
            }
        }

        $haystack = mb_strtolower($title . ' ' . implode(' ', $tagList));

        foreach (self::NON_PROGRAMMING_PATTERNS as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return false;
            }
        }

        foreach (self::PROGRAMMING_PATTERNS as $pattern) {
            if (str_contains($haystack, $pattern)) {
                return true;
            }
        }

        return false;
    }
}
