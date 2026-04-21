<?php

declare(strict_types=1);

use App\Models\Job;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function (): void {
    RateLimiter::clear('auth');
});

it('responds 200 on the health endpoint', function (): void {
    $this->getJson('/api/health')->assertOk();
});

it('runs the critical path: register → me → apply → status → export', function (): void {
    // 1. Register
    $this->postJson('/api/auth/register', [
        'name' => 'Smoke Tester',
        'email' => 'smoke@example.com',
        'password' => 'Abc12345',
        'password_confirmation' => 'Abc12345',
    ])->assertCreated();

    $user = User::whereEmail('smoke@example.com')->firstOrFail();
    $this->actingAs($user);

    // 2. /me
    $this->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('user.email', 'smoke@example.com');

    // 3. Criar vaga e aplicar
    $job = Job::factory()->create();
    $apply = $this->postJson('/api/applications', ['jobId' => $job->id]);
    $apply->assertCreated();
    $applicationId = $apply->json('id');

    // 4. Avançar status
    $this->patchJson("/api/applications/{$applicationId}/status", [
        'status' => 'screening',
    ])->assertOk()->assertJsonPath('status', 'screening');

    // 5. Dashboard de métricas
    $this->getJson('/api/metrics')
        ->assertOk()
        ->assertJsonStructure(['kpis', 'channels', 'funnel', 'heatmap', 'insights']);

    // 6. Export LGPD
    $export = $this->get('/api/account/export');
    $export->assertOk();
    $payload = json_decode($export->streamedContent(), true);
    expect($payload['user']['email'])->toBe('smoke@example.com');
    expect($payload['applications'])->toHaveCount(1);
});
