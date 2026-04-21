<?php

declare(strict_types=1);

namespace App\Domain\Job\Aggregator\DTOs;

use App\Enums\Modality;
use App\Enums\Seniority;
use DateTimeImmutable;
use Spatie\LaravelData\Data;

/**
 * Schema canônico de uma vaga. Drivers convertem payloads heterogêneos neste formato.
 */
final class JobDTO extends Data
{
    public function __construct(
        public string $source,
        public string $externalId,
        public string $externalUrl,
        public string $title,
        public string $companyName,
        public ?string $companyLogoUrl,
        public string $descriptionHtml,
        public ?string $location,
        public ?Modality $modality,
        public ?Seniority $seniority,
        /** @var list<string> */
        public array $stack,
        public ?int $salaryMin,
        public ?int $salaryMax,
        public ?string $salaryCurrency,
        public ?DateTimeImmutable $postedAt,
        public ?DateTimeImmutable $expiresAt,
        public ?string $language = null,
    ) {}

    /**
     * Hash canônico usado para deduplicação entre fontes.
     */
    public function canonicalHash(): string
    {
        $normalized = implode('|', [
            $this->normalize($this->title),
            $this->normalize($this->companyName),
            $this->normalize($this->location ?? ''),
        ]);

        return hash('sha256', $normalized);
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        return preg_replace('/[^\p{L}\p{N} ]/u', '', $value) ?? $value;
    }
}
