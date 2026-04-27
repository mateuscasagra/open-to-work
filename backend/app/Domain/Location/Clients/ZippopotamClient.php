<?php

declare(strict_types=1);

namespace App\Domain\Location\Clients;

use App\Domain\Location\Contracts\PostalCodeLookupClient;
use App\Domain\Location\DTOs\PostalCodeLookupResult;
use App\Domain\Location\Exceptions\PostalCodeLookupException;
use App\Domain\Location\Exceptions\PostalCodeNotFoundException;
use App\Domain\Location\Support\SupportedCountry;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class ZippopotamClient implements PostalCodeLookupClient
{
    private const ENDPOINT_TEMPLATE = 'https://api.zippopotam.us/%s/%s';

    /**
     * Países cobertos pelo zippopotam.us neste projeto.
     */
    private const SUPPORTED = [
        SupportedCountry::US,
        SupportedCountry::ES,
    ];

    public function supports(SupportedCountry $country): bool
    {
        return in_array($country, self::SUPPORTED, true);
    }

    public function lookup(SupportedCountry $country, string $normalizedPostalCode): PostalCodeLookupResult
    {
        if (! $this->supports($country)) {
            throw new PostalCodeLookupException('ZippopotamClient não suporta este país.');
        }

        $url = sprintf(
            self::ENDPOINT_TEMPLATE,
            mb_strtolower($country->value),
            $normalizedPostalCode,
        );

        try {
            $response = Http::acceptJson()
                ->withUserAgent('open-to-work/1.0 (https://opentowork.app.br)')
                ->timeout(10)
                ->retry(2, 500, throw: false)
                ->get($url);

            if ($response->status() === 404) {
                throw new PostalCodeNotFoundException();
            }

            $response->throw();
        } catch (PostalCodeNotFoundException $e) {
            throw $e;
        } catch (ConnectionException|RequestException $e) {
            throw new PostalCodeLookupException('Falha ao consultar zippopotam.us.', previous: $e);
        } catch (Throwable $e) {
            throw new PostalCodeLookupException('Falha ao consultar zippopotam.us.', previous: $e);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];
        $places = is_array($payload['places'] ?? null) ? $payload['places'] : [];

        if ($places === []) {
            throw new PostalCodeNotFoundException();
        }

        /** @var array<string, mixed> $place */
        $place = $places[0];

        $stateCode = isset($place['state abbreviation']) && $place['state abbreviation'] !== ''
            ? (string) $place['state abbreviation']
            : null;
        $stateName = isset($place['state']) && $place['state'] !== ''
            ? (string) $place['state']
            : null;
        $city = isset($place['place name']) && $place['place name'] !== ''
            ? (string) $place['place name']
            : null;

        return new PostalCodeLookupResult(
            countryCode: $country->value,
            postalCode: $normalizedPostalCode,
            stateCode: $stateCode,
            stateName: $stateName,
            city: $city,
            source: 'zippopotam',
        );
    }
}
