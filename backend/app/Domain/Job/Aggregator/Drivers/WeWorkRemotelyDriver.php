<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\Drivers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Job\Aggregator\DTOs\JobDTO;
use App\Domain\Job\Aggregator\Filters\ProgrammingJobFilter;
use App\Enums\Modality;
use DateTimeImmutable;
use Illuminate\Support\Facades\Http;
use SimpleXMLElement;
use Throwable;

/**
 * WeWorkRemotely — feed RSS em https://weworkremotely.com/categories/remote-programming-jobs.rss
 *
 * Título vem no formato "Company Name: Job Title". O <region> indica localização geral.
 * Stack/seniority não são fornecidos pelo feed RSS e não são inferidos.
 *
 * O feed já é a categoria `remote-programming-jobs`, mas aplicamos
 * `ProgrammingJobFilter` por defesa em profundidade (ocasionalmente aparecem
 * cargos não-tech como "Designer at Programming Co." misturados pelo título).
 */
final class WeWorkRemotelyDriver implements JobSourceDriver
{
    private const ENDPOINT = 'https://weworkremotely.com/categories/remote-programming-jobs.rss';

    public function __construct(private readonly ProgrammingJobFilter $filter = new ProgrammingJobFilter) {}

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
        if (! $this->filter->isProgrammingJob($title)) {
            return null;
        }

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
            seniority: null,
            stack: [],
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
