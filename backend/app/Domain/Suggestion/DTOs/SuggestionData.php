<?php

declare(strict_types=1);

namespace App\Domain\Suggestion\DTOs;

use Spatie\LaravelData\Data;

final class SuggestionData extends Data
{
    public function __construct(
        public string $title,
        public string $body,
    ) {}
}
