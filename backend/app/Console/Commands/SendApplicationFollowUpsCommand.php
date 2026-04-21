<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Notifications\ApplicationFollowUpNotification;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

final class SendApplicationFollowUpsCommand extends Command
{
    protected $signature = 'applications:send-followups {--days=7 : Idle days threshold}';

    protected $description = 'Notifies users about applications with no activity in the last N days (non-terminal statuses only).';

    public function handle(): int
    {
        $days = (int) $this->option('days');
        $threshold = now()->subDays($days);

        $terminalValues = collect([
            ApplicationStatus::Accepted,
            ApplicationStatus::Rejected,
            ApplicationStatus::Withdrawn,
        ])->map(fn (ApplicationStatus $s): string => $s->value)->all();

        $candidates = Application::query()
            ->with(['user', 'job.company'])
            ->whereNotIn('status', $terminalValues)
            ->where('applied_at', '<=', $threshold)
            ->whereDoesntHave('events', fn ($q) => $q
                ->where('event_type', 'followup_sent')
                ->where('occurred_at', '>=', $threshold))
            ->whereDoesntHave('events', fn ($q) => $q
                ->where('event_type', 'status_changed')
                ->where('occurred_at', '>=', $threshold))
            ->get();

        foreach ($candidates as $application) {
            if ($application->user === null) {
                continue;
            }

            Notification::send($application->user, new ApplicationFollowUpNotification($application));

            $application->events()->create([
                'event_type' => 'followup_sent',
                'payload' => ['days_since_applied' => (int) $application->applied_at->diffInDays(now())],
                'occurred_at' => now(),
            ]);
        }

        $this->info("Sent {$candidates->count()} follow-up notifications.");

        return self::SUCCESS;
    }
}
