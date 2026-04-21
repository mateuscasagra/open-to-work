<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OauthAccount;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;

final class OauthController extends Controller
{
    public function redirect(string $provider): SymfonyRedirect
    {
        return Socialite::driver($provider)->stateless()->redirect();
    }

    public function callback(string $provider, Request $request): RedirectResponse
    {
        $oauthUser = Socialite::driver($provider)->stateless()->user();

        $user = DB::transaction(function () use ($provider, $oauthUser): User {
            $account = OauthAccount::query()
                ->where('provider', $provider)
                ->where('provider_id', $oauthUser->getId())
                ->first();

            if ($account) {
                return $account->user;
            }

            $user = User::query()->firstOrCreate(
                ['email' => $oauthUser->getEmail()],
                [
                    'name' => $oauthUser->getName() ?? $oauthUser->getNickname() ?? 'Usuário',
                    'email_verified_at' => now(),
                ]
            );

            OauthAccount::query()->create([
                'user_id' => $user->id,
                'provider' => $provider,
                'provider_id' => $oauthUser->getId(),
                'access_token' => $oauthUser->token,
                'refresh_token' => $oauthUser->refreshToken ?? null,
                'expires_at' => isset($oauthUser->expiresIn) ? now()->addSeconds((int) $oauthUser->expiresIn) : null,
            ]);

            return $user;
        });

        Auth::login($user, remember: true);

        return redirect()->to(config('app.frontend_url', '/') . '/auth/callback?success=1');
    }
}
