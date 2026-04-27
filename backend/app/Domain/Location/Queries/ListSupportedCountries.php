<?php

declare(strict_types=1);

namespace App\Domain\Location\Queries;

use App\Domain\Location\DTOs\SupportedCountryData;
use App\Domain\Location\Support\SupportedCountry;

/**
 * Expõe a lista de países suportados (com metadados pro frontend) — fonte
 * única é o enum SupportedCountry.
 */
final class ListSupportedCountries
{
    /**
     * @return list<SupportedCountryData>
     */
    public function execute(): array
    {
        return array_map(
            static function (SupportedCountry $country): SupportedCountryData {
                $meta = $country->meta();

                return new SupportedCountryData(
                    code: $country->value,
                    namePt: $meta['name_pt'],
                    nameEn: $meta['name_en'],
                    nameEs: $meta['name_es'],
                    postalPattern: $meta['postal_pattern'],
                    postalExample: $meta['postal_example'],
                    supportsLookup: $meta['supports_lookup'],
                );
            },
            SupportedCountry::cases(),
        );
    }
}
