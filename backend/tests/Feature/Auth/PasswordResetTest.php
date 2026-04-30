<?php

declare(strict_types=1);

use App\Mail\ResetPasswordEmail;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Password;

it('sends reset email when user exists', function (): void {
    Mail::fake();
    $user = User::factory()->create(['email' => 'someone@example.com']);

    $this->postJson('/api/auth/forgot-password', ['email' => 'someone@example.com'])
        ->assertOk()
        ->assertJsonPath('status', 'sent');

    Mail::assertSent(ResetPasswordEmail::class, fn ($mail) => $mail->hasTo($user->email));
});

it('returns 200 even when email is not registered (no enumeration leak)', function (): void {
    Mail::fake();

    $this->postJson('/api/auth/forgot-password', ['email' => 'nobody@example.com'])
        ->assertOk()
        ->assertJsonPath('status', 'sent');

    Mail::assertNothingSent();
});

it('validates email format on forgot-password', function (): void {
    $this->postJson('/api/auth/forgot-password', ['email' => 'not-an-email'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('resets password with valid token and logs the user in', function (): void {
    $user = User::factory()->create([
        'email' => 'reset@example.com',
        'password' => bcrypt('OldPassword1'),
        'email_verified_at' => now(),
    ]);

    $token = Password::broker()->createToken($user);

    $response = $this->postJson('/api/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => $token,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ])->assertOk();

    expect($response->json('user.id'))->toBe($user->id);
    $this->assertAuthenticatedAs($user->fresh());
    expect(Hash::check('NewPassword1', $user->fresh()->password))->toBeTrue();
});

it('rejects reset with invalid token', function (): void {
    User::factory()->create(['email' => 'reset@example.com']);

    $this->postJson('/api/auth/reset-password', [
        'email' => 'reset@example.com',
        'token' => 'totally-bogus-token',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

it('rejects reset for non-existent email', function (): void {
    $this->postJson('/api/auth/reset-password', [
        'email' => 'ghost@example.com',
        'token' => 'whatever',
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['token']);
});

it('validates password complexity on reset', function (): void {
    $this->postJson('/api/auth/reset-password', [
        'email' => 'a@a.com',
        'token' => 'x',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['password']);
});

it('marks email as verified when resetting password of unverified account', function (): void {
    $user = User::factory()->create([
        'email' => 'unverified@example.com',
        'password' => bcrypt('OldPassword1'),
        'email_verified_at' => null,
    ]);

    $token = Password::broker()->createToken($user);

    $this->postJson('/api/auth/reset-password', [
        'email' => 'unverified@example.com',
        'token' => $token,
        'password' => 'NewPassword1',
        'password_confirmation' => 'NewPassword1',
    ])->assertOk();

    expect($user->fresh()->email_verified_at)->not->toBeNull();
});
