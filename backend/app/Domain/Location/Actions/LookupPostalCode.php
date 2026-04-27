<?php

declare(strict_types=1);

namespace App\Domain\Location\Actions;

use App\Domain\Location\Contracts\PostalCodeLookupClient;
use App\Domain\Location\DTOs\PostalCodeLookupResult;
use App\Domain\Location\Exceptions\InvalidPostalCodeException;
use App\Domain\Location\Exceptions\PostalCodeNotFoundException;
use App\Domain\Location\Exceptions\UnsupportedCountryException;
use App\Domain\Location\Support\PostalCodeFormatter;
use App\Domain\Location\Support\SupportedCountry;
use Illuminate\Contracts\Cache\Repository as CacheRepository;

/**
 * Orquestra a busca de CEP/ZIP: valida o país, normaliza o postal code,
 * consulta cache e despacha pro client apropriado.
 *
 * Cache:
 * - Resultados positivos: TTL 30 dias (CEP/ZIP são imutáveis na prática).
 * - Resultados "not found": TTL curto (10 min) para evitar floods em APIs
 *   externas quando alguém digita CEPs inválidos repetidamente. TTL curto
 *   pra dar chance se um CEP novo for cadastrado upstream.
 * - Exceptions de transporte (5xx, timeout) NÃO são cacheadas — falhas
 *   transitórias devem permitir retry na próxima tentativa.
 */
final class LookupPostalCode
{
    private const CACHE_TTL_DAYS = 30;

    private const NOT_FOUND_CACHE_TTL_MINUTES = 10;

    /**
     * @param  iterable<PostalCodeLookupClient>  $clients
     */
    public function __construct(
        private readonly iterable $clients,
        private readonly CacheRepository $cache,
    ) {}

    /**
     * @throws UnsupportedCountryException
     * @throws InvalidPostalCodeException
     * @throws PostalCodeNotFoundException
     */
    public function execute(string $countryCode, string $postalCode): PostalCodeLookupResult
    {
        $country = SupportedCountry::tryFrom(mb_strtoupper($countryCode));

        if ($country === null || ! $country->supportsLookup()) {
            throw new UnsupportedCountryException();
        }

        $normalized = PostalCodeFormatter::normalize($country, $postalCode);
        $hitKey = "loc:{$country->value}:{$normalized}";
        $missKey = "loc:nf:{$country->value}:{$normalized}";

        $cached = $this->cache->get($hitKey);
        if ($cached instanceof PostalCodeLookupResult) {
            return $cached;
        }

        if ($this->cache->get($missKey) === true) {
            throw new PostalCodeNotFoundException();
        }

        foreach ($this->clients as $client) {
            if (! $client->supports($country)) {
                continue;
            }

            try {
                $result = $client->lookup($country, $normalized);
            } catch (PostalCodeNotFoundException $e) {
                $this->cache->put($missKey, true, now()->addMinutes(self::NOT_FOUND_CACHE_TTL_MINUTES));
                throw $e;
            }

            $this->cache->put($hitKey, $result, now()->addDays(self::CACHE_TTL_DAYS));

            return $result;
        }

        throw new UnsupportedCountryException();
    }
}
