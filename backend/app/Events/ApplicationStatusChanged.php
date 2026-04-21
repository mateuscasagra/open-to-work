<?php

declare(strict_types=1);

namespace App\Events;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use Illuminate\Foundation\Events\Dispatchable;

final class ApplicationStatusChanged
{
    use Dispatchable;

    public function __construct(
        public readonly Application $application,
        public readonly ApplicationStatus $from,
        public readonly ApplicationStatus $to,
    ) {}
}
