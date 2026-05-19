<?php

declare(strict_types=1);

namespace App\Domain\Application\Actions;

use App\Enums\ApplicationStatus;
use App\Events\ApplicationStatusChanged;
use App\Models\Application;
use Illuminate\Support\Facades\DB;

final class ChangeApplicationStatus
{
    public function execute(Application $application, ApplicationStatus $target, ?string $note = null): Application
    {
        $current = $application->status;

        if ($current === $target) {
            return $application;
        }

        return DB::transaction(function () use ($application, $current, $target, $note): Application {
            $application->status = $target;
            $application->save();

            $application->events()->create([
                'event_type' => 'status_changed',
                'payload' => [
                    'from' => $current->value,
                    'to' => $target->value,
                    'note' => $note,
                ],
                'occurred_at' => now(),
            ]);

            event(new ApplicationStatusChanged($application, $current, $target));

            return $application;
        });
    }
}
