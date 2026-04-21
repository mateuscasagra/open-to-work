<?php

declare(strict_types=1);

namespace App\Domain\Application\DTOs;

use Spatie\LaravelData\Data;

final class ApplicationData extends Data
{
    public function __construct(
        public int $jobId,
        public ?int $resumeId = null,
        public ?string $source = null,
        public ?string $notes = null,
        public ?int $expectedSalary = null,
        public ?string $emailMessageOverride = null,
        public ?int $emailResumeIdOverride = null,
    ) {}
}
