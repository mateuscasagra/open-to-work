<?php

declare(strict_types=1);

namespace App\Domain\Location\DTOs;

use JsonSerializable;

final class SupportedCountryData implements JsonSerializable
{
    public function __construct(
        public readonly string $code,
        public readonly string $namePt,
        public readonly string $nameEn,
        public readonly string $nameEs,
        public readonly string $postalPattern,
        public readonly string $postalExample,
        public readonly bool $supportsLookup,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'code' => $this->code,
            'name_pt' => $this->namePt,
            'name_en' => $this->nameEn,
            'name_es' => $this->nameEs,
            'postal_pattern' => $this->postalPattern,
            'postal_example' => $this->postalExample,
            'supports_lookup' => $this->supportsLookup,
        ];
    }
}
