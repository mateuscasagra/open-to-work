<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        /** @var array<string, class-string<JobSourceDriver>> $drivers */
        $drivers = config('aggregator.drivers', []);

        foreach ($drivers as $key => $class) {
            $this->app->bind("job.driver.{$key}", $class);
            $this->app->tag($class, 'job.drivers');
        }

        $this->app->instance('job.driver_keys', array_keys($drivers));
    }

    public function boot(): void
    {
        $this->configureRateLimiters();
    }

    private function configureRateLimiters(): void
    {
        // Login/register: por IP e por e-mail para mitigar credential stuffing.
        RateLimiter::for('auth', static function (Request $request): array {
            $email = (string) $request->input('email', '');

            return [
                Limit::perMinute(10)->by('ip:' . $request->ip()),
                Limit::perMinute(5)->by('email:' . mb_strtolower($email)),
            ];
        });

        // Uploads (PDFs, anexos): caro em disco/MinIO, limita por usuário.
        RateLimiter::for('uploads', static function (Request $request): Limit {
            $user = $request->user();

            return $user !== null
                ? Limit::perMinute(20)->by('user:' . $user->getAuthIdentifier())
                : Limit::perMinute(5)->by('ip:' . $request->ip());
        });

        // Endpoints sensíveis de conta (export LGPD, delete): baixa taxa.
        RateLimiter::for('account-sensitive', static function (Request $request): Limit {
            $user = $request->user();

            return $user !== null
                ? Limit::perMinute(5)->by('user:' . $user->getAuthIdentifier())
                : Limit::perMinute(2)->by('ip:' . $request->ip());
        });

        // Autocomplete / leitura de skills: pode ser consultado frequentemente.
        RateLimiter::for('search', static function (Request $request): Limit {
            $user = $request->user();

            return $user !== null
                ? Limit::perMinute(120)->by('user:' . $user->getAuthIdentifier())
                : Limit::perMinute(30)->by('ip:' . $request->ip());
        });
    }
}
