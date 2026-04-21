<?php

declare(strict_types=1);

namespace App\Enums;

enum Seniority: string
{
    case Intern = 'intern';
    case Junior = 'junior';
    case Mid = 'mid';
    case Senior = 'senior';
    case Staff = 'staff';
    case Principal = 'principal';

    public function label(): string
    {
        return match ($this) {
            self::Intern => 'Estagiário',
            self::Junior => 'Júnior',
            self::Mid => 'Pleno',
            self::Senior => 'Sênior',
            self::Staff => 'Staff',
            self::Principal => 'Principal',
        };
    }
}
