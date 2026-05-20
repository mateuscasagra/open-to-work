<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Subscription\Actions\DowngradeExpiredSubscriptions;
use Illuminate\Console\Command;

final class DowngradeExpiredSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:downgrade-expired';

    protected $description = 'Downgrade Pro users whose paid period ended (status canceled|past_due).';

    public function handle(DowngradeExpiredSubscriptions $action): int
    {
        $count = $action->execute();
        $this->info("Downgraded {$count} subscription(s).");

        return self::SUCCESS;
    }
}
