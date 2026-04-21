<?php

declare(strict_types=1);

use App\Console\Commands\AggregateJobsCommand;
use Illuminate\Support\Facades\Schedule;

// Agregação de vagas a cada 6h
Schedule::command(AggregateJobsCommand::class)
    ->cron(config('aggregator.cron', '0 */6 * * *'))
    ->onOneServer()
    ->withoutOverlapping()
    ->runInBackground();

// Rollup diário de métricas — 03:00 UTC
Schedule::command('metrics:rollup-daily')
    ->dailyAt('03:00')
    ->onOneServer();

// Limpar jobs expirados semanalmente
Schedule::command('jobs:deactivate-expired')
    ->weekly()
    ->sundays()
    ->at('02:00');

// Follow-up de candidaturas paradas há 7 dias — 09:00 UTC
Schedule::command('applications:send-followups')
    ->dailyAt('09:00')
    ->onOneServer();
