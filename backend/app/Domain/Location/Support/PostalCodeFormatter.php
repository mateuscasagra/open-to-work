<?php

declare(strict_types=1);

namespace App\Domain\Location\Support;

use App\Domain\Location\Exceptions\InvalidPostalCodeException;

final class PostalCodeFormatter
{
    /**
     * Normaliza CEP/ZIP removendo separadores e validando o formato declarado
     * pelo país no enum. Retorna a forma canônica que vai pra API externa.
     *
     * @throws InvalidPostalCodeException
     */
    public static function normalize(SupportedCountry $country, string $postalCode): string
    {
        $trimmed = trim($postalCode);
        $pattern = '/' . $country->meta()['postal_pattern'] . '/';

        if (preg_match($pattern, $trimmed) !== 1) {
            throw new InvalidPostalCodeException;
        }

        $upper = mb_strtoupper($trimmed);

        return match ($country) {
            SupportedCountry::BR => preg_replace('/\D/', '', $upper) ?? '',
            SupportedCountry::US => str_replace(' ', '', $upper),
            SupportedCountry::ES => $upper,
            default => preg_replace('/[\s-]/', '', $upper) ?? '',
        };
    }
}
