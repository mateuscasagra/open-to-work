<?php

declare(strict_types=1);

use App\Mail\VerifyEmailCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

function pendingUser(string $email = 'pending@example.com', string $code = '123456', ?\Illuminate\Support\Carbon $expiresAt = null, int $attempts = 0): User
{
    $user = User::factory()->unverified()->create(['email' => $email]);
    $user->forceFill([
        'email_verification_code' => Hash::make($code),
        'email_verification_code_expires_at' => $expiresAt ?? now()->addMinutes(10),
        'email_verification_attempts' => $attempts,
    ])->save();

    return $user->fresh();
}

it('verifies an email with a valid code and logs the user in', function (): void {
    $user = pendingUser();

    $response = $this->postJson('/api/auth/verify-email', [
        'email' => $user->email,
        'code' => '123456',
    ]);

    $response->assertOk()->assertJsonPath('user.email', $user->email);
    $this->assertAuthenticatedAs($user->fresh());

    $user->refresh();
    expect($user->email_verified_at)->not->toBeNull();
    expect($user->email_verification_code)->toBeNull();
    expect($user->email_verification_attempts)->toBe(0);
});

it('rejects an invalid code and increments attempts', function (): void {
    $user = pendingUser();

    $this->postJson('/api/auth/verify-email', [
        'email' => $user->email,
        'code' => '999999',
    ])->assertUnprocessable()->assertJsonValidationErrors(['code']);

    expect($user->fresh()->email_verification_attempts)->toBe(1);
    $this->assertGuest();
});

it('rejects an expired code', function (): void {
    $user = pendingUser(expiresAt: now()->subMinutes(1));

    $this->postJson('/api/auth/verify-email', [
        'email' => $user->email,
        'code' => '123456',
    ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

it('locks verification after too many attempts', function (): void {
    $user = pendingUser(attempts: 5);

    $this->postJson('/api/auth/verify-email', [
        'email' => $user->email,
        'code' => '123456',
    ])->assertUnprocessable()->assertJsonValidationErrors(['code']);
});

it('resends a verification code', function (): void {
    Mail::fake();

    $user = pendingUser();

    $this->postJson('/api/auth/resend-code', [
        'email' => $user->email,
    ])->assertOk()->assertJson(['status' => 'sent']);

    Mail::assertSent(VerifyEmailCode::class, fn (VerifyEmailCode $mail) => $mail->hasTo($user->email));
});

it('does not send a code for already-verified accounts but still returns 200', function (): void {
    Mail::fake();

    User::factory()->create(['email' => 'done@example.com']); // factory verifies by default

    $this->postJson('/api/auth/resend-code', [
        'email' => 'done@example.com',
    ])->assertOk();

    Mail::assertNothingSent();
});

it('blocks login for unverified accounts and resends the code', function (): void {
    Mail::fake();

    User::factory()->unverified()->create([
        'email' => 'pending@example.com',
        'password' => bcrypt('Secret123!'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'pending@example.com',
        'password' => 'Secret123!',
    ])->assertStatus(403)->assertJsonValidationErrors(['email']);

    $this->assertGuest();

    Mail::assertSent(VerifyEmailCode::class);
});

it('allows login for unverified accounts when verification is disabled', function (): void {
    config(['auth.email_verification_enabled' => false]);

    $user = User::factory()->unverified()->create([
        'email' => 'pending@example.com',
        'password' => bcrypt('Secret123!'),
    ]);

    $this->postJson('/api/auth/login', [
        'email' => 'pending@example.com',
        'password' => 'Secret123!',
    ])->assertOk()->assertJsonPath('user.id', $user->id);

    $this->assertAuthenticatedAs($user->fresh());
});
