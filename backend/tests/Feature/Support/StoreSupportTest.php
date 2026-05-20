<?php

declare(strict_types=1);

use App\Mail\SupportRequestMail;
use Illuminate\Support\Facades\Mail;

beforeEach(function (): void {
    config()->set('services.support.recipient', 'admin@example.com');
});

it('sends a support email and returns ok', function (): void {
    Mail::fake();

    $this->postJson('/api/support', [
        'name' => 'Diego',
        'email' => 'diego@example.com',
        'title' => 'Erro ao fazer login',
        'description' => 'Quando clico em entrar aparece tela branca por uns segundos.',
    ])
        ->assertOk()
        ->assertJsonPath('ok', true);

    Mail::assertSent(SupportRequestMail::class, function (SupportRequestMail $mail): bool {
        return $mail->hasTo('admin@example.com')
            && $mail->senderName === 'Diego'
            && $mail->senderEmail === 'diego@example.com'
            && $mail->subjectLine === 'Erro ao fazer login'
            && str_contains($mail->messageBody, 'tela branca');
    });
});

it('uses anonymous_name fallback when name is missing', function (): void {
    Mail::fake();

    $this->postJson('/api/support', [
        'email' => 'visitor@example.com',
        'title' => 'Sugestão',
        'description' => 'Seria legal ter modo escuro.',
    ])->assertOk();

    Mail::assertSent(SupportRequestMail::class, fn (SupportRequestMail $mail): bool => $mail->senderName === __('support.anonymous_name'));
});

it('rejects missing email with 422', function (): void {
    $this->postJson('/api/support', [
        'title' => 'Algo',
        'description' => 'Texto suficiente longo aqui sim.',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects invalid email format', function (): void {
    $this->postJson('/api/support', [
        'email' => 'not-an-email',
        'title' => 'Algo',
        'description' => 'Texto suficiente longo aqui sim.',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['email']);
});

it('rejects too short title', function (): void {
    $this->postJson('/api/support', [
        'email' => 'a@b.com',
        'title' => 'oi',
        'description' => 'Texto suficiente longo aqui sim.',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['title']);
});

it('rejects too short description', function (): void {
    $this->postJson('/api/support', [
        'email' => 'a@b.com',
        'title' => 'Tudo OK',
        'description' => 'curto',
    ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['description']);
});

it('still returns ok when SUPPORT_EMAIL is not configured (silent fail with log)', function (): void {
    Mail::fake();
    config()->set('services.support.recipient', '');

    $this->postJson('/api/support', [
        'email' => 'a@b.com',
        'title' => 'Tudo OK',
        'description' => 'Mensagem suficientemente longa aqui.',
    ])->assertOk();

    Mail::assertNothingSent();
});
