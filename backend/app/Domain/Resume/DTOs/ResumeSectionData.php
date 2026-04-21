<?php

declare(strict_types=1);

namespace App\Domain\Resume\DTOs;

use Spatie\LaravelData\Data;

final class ResumeSectionData extends Data
{
    /**
     * @param  array<string, mixed>  $content
     */
    public function __construct(
        public string $type,
        public int $order,
        public array $content,
    ) {}
}
