<?php

declare(strict_types=1);

namespace App\Providers;

use App\Domain\Job\Aggregator\Contracts\JobSourceDriver;
use App\Domain\Location\Actions\LookupPostalCode;
use App\Domain\Location\Clients\ViaCepClient;
use App\Domain\Location\Clients\ZippopotamClient;
use App\Domain\Subscription\Clients\AsaasHttpClient;
use App\Domain\Subscription\Contracts\AsaasGateway;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
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

        $this->app->tag([ViaCepClient::class, ZippopotamClient::class], 'location.clients');
        $this->app->bind(
            LookupPostalCode::class,
            static fn (Application $app): LookupPostalCode => new LookupPostalCode(
                clients: $app->tagged('location.clients'),
                cache: $app->make('cache.store'),
            ),
        );

        $this->app->bind(AsaasGateway::class, AsaasHttpClient::class);
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

        // Lookup de CEP/ZIP: barato porém bate em APIs externas, evitar abuso.
        RateLimiter::for('location-lookup', static function (Request $request): Limit {
            $user = $request->user();

            return $user !== null
                ? Limit::perMinute(30)->by('user:' . $user->getAuthIdentifier())
                : Limit::perMinute(10)->by('ip:' . $request->ip());
        });

        // Sugestões — burst protection no POST de criação (quota 5/semana fica na Action).
        RateLimiter::for('suggestions-write', static function (Request $request): Limit {
            $user = $request->user();

            return $user !== null
                ? Limit::perMinute(10)->by('user:' . $user->getAuthIdentifier())
                : Limit::perMinute(3)->by('ip:' . $request->ip());
        });

        // Votos podem ser rápidos (toggle/replace), liberar bastante.
        RateLimiter::for('suggestions-vote', static function (Request $request): Limit {
            $user = $request->user();

            return $user !== null
                ? Limit::perMinute(60)->by('user:' . $user->getAuthIdentifier())
                : Limit::perMinute(10)->by('ip:' . $request->ip());
        });

        // Subscribe/cancel: burst protection. Quota real fica nas Actions.
        RateLimiter::for('subscribe-write', static function (Request $request): Limit {
            $user = $request->user();

            return $user !== null
                ? Limit::perMinute(10)->by('user:' . $user->getAuthIdentifier())
                : Limit::perMinute(3)->by('ip:' . $request->ip());
        });

        // Webhook Asaas — público (sem auth). Limita por IP pra evitar replay flood.
        RateLimiter::for('asaas-webhook', static fn (Request $request): Limit
            => Limit::perMinute(120)->by('ip:' . $request->ip()));

        // Suporte público (formulário "Preciso de ajuda"). 5/hora por IP — gera
        // e-mail, então spam aqui custa caro. UX: usuário legítimo dificilmente
        // bate isso.
        RateLimiter::for('support', static fn (Request $request): Limit
            => Limit::perHour(5)->by('ip:' . $request->ip()));
    }
}
