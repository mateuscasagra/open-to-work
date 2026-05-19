<?php

declare(strict_types=1);

namespace App\Domain\Application\DTOs;

use Spatie\LaravelData\Data;

final class ApplicationData extends Data
{
    public function __construct(
        public ?int $jobId = null,
        public ?string $manualTitle = null,
        public ?string $manualCompany = null,
        public ?string $jobUrl = null,
        public ?int $resumeId = null,
        public ?string $source = null,
        public ?string $notes = null,
        public ?int $expectedSalary = null,
        public ?string $emailMessageOverride = null,
        public ?int $emailResumeIdOverride = null,
    ) {}
}
