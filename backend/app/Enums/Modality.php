<?php

declare(strict_types=1);

namespace App\Enums;

enum Modality: string
{
    case Remote = 'remote';
    case Hybrid = 'hybrid';
    case Onsite = 'onsite';

    public function label(): string
    {
        return match ($this) {
            self::Remote => 'Remoto',
            self::Hybrid => 'Híbrido',
            self::Onsite => 'Presencial',
        };
    }
}
