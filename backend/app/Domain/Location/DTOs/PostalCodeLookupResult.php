<?php

declare(strict_types=1);

namespace App\Domain\Location\DTOs;

use JsonSerializable;

/**
 * Resultado normalizado de uma busca por CEP/ZIP, vindo do ViaCEP
 * (BR) ou zippopotam.us (demais países com lookup).
 */
final class PostalCodeLookupResult implements JsonSerializable
{
    public function __construct(
        public readonly string $countryCode,
        public readonly string $postalCode,
        public readonly ?string $stateCode,
        public readonly ?string $stateName,
        public readonly ?string $city,
        public readonly string $source,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return [
            'country_code' => $this->countryCode,
            'postal_code' => $this->postalCode,
            'state_code' => $this->stateCode,
            'state_name' => $this->stateName,
            'city' => $this->city,
            'source' => $this->source,
        ];
    }
}
