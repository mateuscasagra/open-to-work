<?php

declare(strict_types=1);

namespace App\Domain\Location\Contracts;

use App\Domain\Location\DTOs\PostalCodeLookupResult;
use App\Domain\Location\Exceptions\PostalCodeLookupException;
use App\Domain\Location\Exceptions\PostalCodeNotFoundException;
use App\Domain\Location\Support\SupportedCountry;

interface PostalCodeLookupClient
{
    public function supports(SupportedCountry $country): bool;

    /**
     * @throws PostalCodeNotFoundException
     * @throws PostalCodeLookupException
     */
    public function lookup(SupportedCountry $country, string $normalizedPostalCode): PostalCodeLookupResult;
}
