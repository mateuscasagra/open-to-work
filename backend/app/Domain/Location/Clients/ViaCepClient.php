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

final class ViaCepClient implements PostalCodeLookupClient
{
    private const ENDPOINT_TEMPLATE = 'https://viacep.com.br/ws/%s/json/';

    /**
     * Mapeamento UF → nome completo do estado. ViaCEP só devolve a sigla.
     *
     * @var array<string, string>
     */
    private const UF_TO_STATE = [
        'AC' => 'Acre',
        'AL' => 'Alagoas',
        'AP' => 'Amapá',
        'AM' => 'Amazonas',
        'BA' => 'Bahia',
        'CE' => 'Ceará',
        'DF' => 'Distrito Federal',
        'ES' => 'Espírito Santo',
        'GO' => 'Goiás',
        'MA' => 'Maranhão',
        'MT' => 'Mato Grosso',
        'MS' => 'Mato Grosso do Sul',
        'MG' => 'Minas Gerais',
        'PA' => 'Pará',
        'PB' => 'Paraíba',
        'PR' => 'Paraná',
        'PE' => 'Pernambuco',
        'PI' => 'Piauí',
        'RJ' => 'Rio de Janeiro',
        'RN' => 'Rio Grande do Norte',
        'RS' => 'Rio Grande do Sul',
        'RO' => 'Rondônia',
        'RR' => 'Roraima',
        'SC' => 'Santa Catarina',
        'SP' => 'São Paulo',
        'SE' => 'Sergipe',
        'TO' => 'Tocantins',
    ];

    public function supports(SupportedCountry $country): bool
    {
        return $country === SupportedCountry::BR;
    }

    public function lookup(SupportedCountry $country, string $normalizedPostalCode): PostalCodeLookupResult
    {
        if (! $this->supports($country)) {
            throw new PostalCodeLookupException('ViaCepClient não suporta este país.');
        }

        $url = sprintf(self::ENDPOINT_TEMPLATE, $normalizedPostalCode);

        try {
            $response = Http::acceptJson()
                ->withUserAgent('open-to-work/1.0 (https://opentowork.app.br)')
                ->timeout(10)
                ->retry(2, 500)
                ->get($url);

            $response->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new PostalCodeLookupException('Falha ao consultar ViaCEP.', previous: $e);
        } catch (Throwable $e) {
            throw new PostalCodeLookupException('Falha ao consultar ViaCEP.', previous: $e);
        }

        /** @var array<string, mixed> $payload */
        $payload = $response->json() ?? [];

        if (($payload['erro'] ?? false) === true) {
            throw new PostalCodeNotFoundException;
        }

        $uf = isset($payload['uf']) ? (string) $payload['uf'] : null;
        $stateName = $uf !== null ? (self::UF_TO_STATE[$uf] ?? null) : null;

        return new PostalCodeLookupResult(
            countryCode: $country->value,
            postalCode: $normalizedPostalCode,
            stateCode: $uf,
            stateName: $stateName,
            city: isset($payload['localidade']) ? (string) $payload['localidade'] : null,
            source: 'viacep',
        );
    }
}
