<?php

declare(strict_types=1);

namespace App\Enums;

enum SupportedLocale: string
{
    case PtBr = 'pt_BR';
    case En = 'en';
    case Es = 'es';

    public function label(): string
    {
        return match ($this) {
            self::PtBr => 'Português (Brasil)',
            self::En => 'English',
            self::Es => 'Español',
        };
    }
}
