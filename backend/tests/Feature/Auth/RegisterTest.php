<?php

declare(strict_types=1);

use App\Mail\VerifyEmailCode;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

it('registers a user without logging them in and sends a verification code', function (): void {
    Mail::fake();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Diego',
        'email' => 'diego@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]);

    $response
        ->assertStatus(202)
        ->assertJson(['status' => 'verification_required', 'email' => 'diego@example.com']);

    $user = User::where('email', 'diego@example.com')->first();
    expect($user)->not->toBeNull();
    expect($user->email_verified_at)->toBeNull();
    expect($user->email_verification_code)->not->toBeNull();
    expect($user->email_verification_code_expires_at)->not->toBeNull();
    expect($user->email_verification_code_sent_at)->not->toBeNull();

    $this->assertGuest();

    Mail::assertSent(VerifyEmailCode::class, fn (VerifyEmailCode $mail) => $mail->hasTo('diego@example.com'));
});

it('requires valid email and password', function (): void {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'X',
        'email' => 'not-an-email',
        'password' => 'short',
        'password_confirmation' => 'short',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('blocks register when the email is already verified', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'New',
        'email' => 'taken@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
});

it('treats register as a retry when the email exists but is not verified (reissues if older than threshold)', function (): void {
    Mail::fake();

    $original = User::factory()->unverified()->create([
        'email' => 'pending@example.com',
        'name' => 'Old name',
        'password' => Hash::make('OldPass1!'),
    ]);
    $original->forceFill([
        'email_verification_code' => Hash::make('111111'),
        'email_verification_code_expires_at' => now()->addMinutes(10),
        'email_verification_code_sent_at' => now()->subMinutes(6),
        'email_verification_attempts' => 3,
    ])->save();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'New name',
        'email' => 'pending@example.com',
        'password' => 'NewPass456!',
        'password_confirmation' => 'NewPass456!',
    ]);

    $response->assertStatus(202)
        ->assertJson(['status' => 'verification_required', 'email' => 'pending@example.com']);

    $fresh = $original->fresh();
    expect($fresh->name)->toBe('New name');
    expect(Hash::check('NewPass456!', $fresh->password))->toBeTrue();
    expect($fresh->email_verification_attempts)->toBe(0);
    expect(User::where('email', 'pending@example.com')->count())->toBe(1);

    Mail::assertSent(VerifyEmailCode::class);
});

it('does not reissue a verification email when the previous one is within the threshold', function (): void {
    Mail::fake();

    $original = User::factory()->unverified()->create([
        'email' => 'pending@example.com',
        'password' => Hash::make('OldPass1!'),
    ]);
    $previousCode = Hash::make('111111');
    $original->forceFill([
        'email_verification_code' => $previousCode,
        'email_verification_code_expires_at' => now()->addMinutes(10),
        'email_verification_code_sent_at' => now()->subMinutes(2),
        'email_verification_attempts' => 2,
    ])->save();

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Same User',
        'email' => 'pending@example.com',
        'password' => 'NewPass456!',
        'password_confirmation' => 'NewPass456!',
    ]);

    $response->assertStatus(202);

    $fresh = $original->fresh();
    expect($fresh->email_verification_code)->toBe($previousCode);
    expect($fresh->email_verification_attempts)->toBe(0);

    Mail::assertNothingSent();
});

it('auto-logs in when email verification is disabled', function (): void {
    config(['auth.email_verification_enabled' => false]);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'Skip',
        'email' => 'skip@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]);

    $response->assertCreated()->assertJsonPath('user.email', 'skip@example.com');
    $this->assertAuthenticated();

    $user = User::where('email', 'skip@example.com')->first();
    expect($user->email_verified_at)->not->toBeNull();
});
