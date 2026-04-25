<?php

declare(strict_types=1);

use App\Mail\VerifyEmailCode;
use App\Models\User;
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

it('blocks duplicate emails', function (): void {
    User::factory()->create(['email' => 'taken@example.com']);

    $response = $this->postJson('/api/auth/register', [
        'name' => 'New',
        'email' => 'taken@example.com',
        'password' => 'Secret123!',
        'password_confirmation' => 'Secret123!',
    ]);

    $response->assertUnprocessable()->assertJsonValidationErrors(['email']);
});
