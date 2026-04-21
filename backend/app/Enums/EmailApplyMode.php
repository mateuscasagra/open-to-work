<?php

declare(strict_types=1);

namespace App\Enums;

enum EmailApplyMode: string
{
    case Fixed = 'fixed';
    case Variable = 'variable';
}
