<?php

declare(strict_types=1);

use App\Models\OauthAccount;
use App\Models\User;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;

function fakeSocialiteUser(string $providerId, string $email, string $name = 'External User'): SocialiteUserContract
{
    $u = new SocialiteUser;
    $u->id = $providerId;
    $u->email = $email;
    $u->name = $name;
    $u->token = 'access-token';
    $u->refreshToken = 'refresh-token';
    $u->expiresIn = 3600;

    return $u;
}

it('redirects to the OAuth provider', function (): void {
    Socialite::shouldReceive('driver->stateless->redirect')
        ->once()
        ->andReturn(redirect('https://accounts.google.com/fake'));

    $this->get('/api/auth/google/redirect')->assertRedirect();
});

it('creates a new user on first OAuth login', function (): void {
    Socialite::shouldReceive('driver->stateless->user')
        ->andReturn(fakeSocialiteUser('google-123', 'new@example.com', 'New User'));

    $response = $this->get('/api/auth/google/callback');

    $response->assertRedirect();

    expect(User::where('email', 'new@example.com')->exists())->toBeTrue();
    expect(OauthAccount::where('provider', 'google')->where('provider_id', 'google-123')->exists())->toBeTrue();
});

it('links OAuth account to existing user with same email', function (): void {
    $existing = User::factory()->create(['email' => 'existing@example.com']);

    Socialite::shouldReceive('driver->stateless->user')
        ->andReturn(fakeSocialiteUser('linkedin-999', 'existing@example.com'));

    $this->get('/api/auth/linkedin/callback')->assertRedirect();

    expect(User::where('email', 'existing@example.com')->count())->toBe(1);
    expect(OauthAccount::where('user_id', $existing->id)->where('provider', 'linkedin')->exists())->toBeTrue();
});

it('reuses existing OAuth account on repeated login', function (): void {
    $user = User::factory()->create();
    OauthAccount::create([
        'user_id' => $user->id,
        'provider' => 'github',
        'provider_id' => 'gh-42',
    ]);

    Socialite::shouldReceive('driver->stateless->user')
        ->andReturn(fakeSocialiteUser('gh-42', $user->email));

    $this->get('/api/auth/github/callback')->assertRedirect();

    expect(OauthAccount::where('provider', 'github')->count())->toBe(1);
});

it('rejects unknown provider', function (): void {
    $this->get('/api/auth/twitter/redirect')->assertNotFound();
});
