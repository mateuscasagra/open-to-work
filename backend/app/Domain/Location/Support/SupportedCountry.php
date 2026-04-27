<?php

declare(strict_types=1);

namespace App\Domain\Location\Support;

/**
 * Lista curada de países suportados pelo sistema. Apenas BR, US e ES têm
 * lookup automático via API externa; os demais são selecionáveis para que
 * o usuário preencha estado e cidade manualmente.
 */
enum SupportedCountry: string
{
    case BR = 'BR';
    case US = 'US';
    case ES = 'ES';
    case CA = 'CA';
    case MX = 'MX';
    case AR = 'AR';
    case CL = 'CL';
    case CO = 'CO';
    case PE = 'PE';
    case PT = 'PT';
    case GB = 'GB';
    case IE = 'IE';
    case FR = 'FR';
    case DE = 'DE';
    case NL = 'NL';
    case IT = 'IT';
    case AU = 'AU';
    case NZ = 'NZ';
    case IN = 'IN';
    case JP = 'JP';
    case KR = 'KR';
    case IL = 'IL';
    case ZA = 'ZA';

    /**
     * @return array{name_pt: string, name_en: string, name_es: string, postal_pattern: string, postal_example: string, supports_lookup: bool}
     */
    public function meta(): array
    {
        return match ($this) {
            self::BR => [
                'name_pt' => 'Brasil',
                'name_en' => 'Brazil',
                'name_es' => 'Brasil',
                'postal_pattern' => '^\d{5}-?\d{3}$',
                'postal_example' => '01310-100',
                'supports_lookup' => true,
            ],
            self::US => [
                'name_pt' => 'Estados Unidos',
                'name_en' => 'United States',
                'name_es' => 'Estados Unidos',
                'postal_pattern' => '^\d{5}(-\d{4})?$',
                'postal_example' => '90210',
                'supports_lookup' => true,
            ],
            self::ES => [
                'name_pt' => 'Espanha',
                'name_en' => 'Spain',
                'name_es' => 'España',
                'postal_pattern' => '^\d{5}$',
                'postal_example' => '28013',
                'supports_lookup' => true,
            ],
            self::CA => [
                'name_pt' => 'Canadá',
                'name_en' => 'Canada',
                'name_es' => 'Canadá',
                'postal_pattern' => '^[A-Za-z]\d[A-Za-z][\s-]?\d[A-Za-z]\d$',
                'postal_example' => 'K1A 0B1',
                'supports_lookup' => false,
            ],
            self::MX => [
                'name_pt' => 'México',
                'name_en' => 'Mexico',
                'name_es' => 'México',
                'postal_pattern' => '^\d{5}$',
                'postal_example' => '01000',
                'supports_lookup' => false,
            ],
            self::AR => [
                'name_pt' => 'Argentina',
                'name_en' => 'Argentina',
                'name_es' => 'Argentina',
                'postal_pattern' => '^[A-Za-z]?\d{4}[A-Za-z]{0,3}$',
                'postal_example' => 'C1407',
                'supports_lookup' => false,
            ],
            self::CL => [
                'name_pt' => 'Chile',
                'name_en' => 'Chile',
                'name_es' => 'Chile',
                'postal_pattern' => '^\d{7}$',
                'postal_example' => '8320000',
                'supports_lookup' => false,
            ],
            self::CO => [
                'name_pt' => 'Colômbia',
                'name_en' => 'Colombia',
                'name_es' => 'Colombia',
                'postal_pattern' => '^\d{6}$',
                'postal_example' => '110111',
                'supports_lookup' => false,
            ],
            self::PE => [
                'name_pt' => 'Peru',
                'name_en' => 'Peru',
                'name_es' => 'Perú',
                'postal_pattern' => '^\d{5}$',
                'postal_example' => '15001',
                'supports_lookup' => false,
            ],
            self::PT => [
                'name_pt' => 'Portugal',
                'name_en' => 'Portugal',
                'name_es' => 'Portugal',
                'postal_pattern' => '^\d{4}-?\d{3}$',
                'postal_example' => '1100-148',
                'supports_lookup' => false,
            ],
            self::GB => [
                'name_pt' => 'Reino Unido',
                'name_en' => 'United Kingdom',
                'name_es' => 'Reino Unido',
                'postal_pattern' => '^[A-Za-z]{1,2}\d[A-Za-z\d]?[\s-]?\d[A-Za-z]{2}$',
                'postal_example' => 'SW1A 1AA',
                'supports_lookup' => false,
            ],
            self::IE => [
                'name_pt' => 'Irlanda',
                'name_en' => 'Ireland',
                'name_es' => 'Irlanda',
                'postal_pattern' => '^[A-Za-z]\d{2}[\s-]?\w{4}$',
                'postal_example' => 'D02 XH98',
                'supports_lookup' => false,
            ],
            self::FR => [
                'name_pt' => 'França',
                'name_en' => 'France',
                'name_es' => 'Francia',
                'postal_pattern' => '^\d{5}$',
                'postal_example' => '75001',
                'supports_lookup' => false,
            ],
            self::DE => [
                'name_pt' => 'Alemanha',
                'name_en' => 'Germany',
                'name_es' => 'Alemania',
                'postal_pattern' => '^\d{5}$',
                'postal_example' => '10115',
                'supports_lookup' => false,
            ],
            self::NL => [
                'name_pt' => 'Holanda',
                'name_en' => 'Netherlands',
                'name_es' => 'Países Bajos',
                'postal_pattern' => '^\d{4}\s?[A-Za-z]{2}$',
                'postal_example' => '1011 AB',
                'supports_lookup' => false,
            ],
            self::IT => [
                'name_pt' => 'Itália',
                'name_en' => 'Italy',
                'name_es' => 'Italia',
                'postal_pattern' => '^\d{5}$',
                'postal_example' => '00184',
                'supports_lookup' => false,
            ],
            self::AU => [
                'name_pt' => 'Austrália',
                'name_en' => 'Australia',
                'name_es' => 'Australia',
                'postal_pattern' => '^\d{4}$',
                'postal_example' => '2000',
                'supports_lookup' => false,
            ],
            self::NZ => [
                'name_pt' => 'Nova Zelândia',
                'name_en' => 'New Zealand',
                'name_es' => 'Nueva Zelanda',
                'postal_pattern' => '^\d{4}$',
                'postal_example' => '6011',
                'supports_lookup' => false,
            ],
            self::IN => [
                'name_pt' => 'Índia',
                'name_en' => 'India',
                'name_es' => 'India',
                'postal_pattern' => '^\d{6}$',
                'postal_example' => '110001',
                'supports_lookup' => false,
            ],
            self::JP => [
                'name_pt' => 'Japão',
                'name_en' => 'Japan',
                'name_es' => 'Japón',
                'postal_pattern' => '^\d{3}-?\d{4}$',
                'postal_example' => '100-0001',
                'supports_lookup' => false,
            ],
            self::KR => [
                'name_pt' => 'Coreia do Sul',
                'name_en' => 'South Korea',
                'name_es' => 'Corea del Sur',
                'postal_pattern' => '^\d{5}$',
                'postal_example' => '03048',
                'supports_lookup' => false,
            ],
            self::IL => [
                'name_pt' => 'Israel',
                'name_en' => 'Israel',
                'name_es' => 'Israel',
                'postal_pattern' => '^\d{5}(\d{2})?$',
                'postal_example' => '9100000',
                'supports_lookup' => false,
            ],
            self::ZA => [
                'name_pt' => 'África do Sul',
                'name_en' => 'South Africa',
                'name_es' => 'Sudáfrica',
                'postal_pattern' => '^\d{4}$',
                'postal_example' => '8001',
                'supports_lookup' => false,
            ],
        };
    }

    public function supportsLookup(): bool
    {
        return $this->meta()['supports_lookup'];
    }
}
