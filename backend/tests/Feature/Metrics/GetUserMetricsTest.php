<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\User;
use Illuminate\Support\Carbon;

beforeEach(function (): void {
    Carbon::setTestNow('2026-04-18 12:00:00');
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('returns kpis from applications and events in real time', function (): void {
    $user = User::factory()->create();

    $apps = Application::factory()->for($user)->count(10)->create([
        'applied_at' => '2026-04-15 10:00:00',
    ]);

    foreach ($apps->take(4) as $app) {
        $app->events()->create([
            'event_type' => 'status_changed',
            'payload' => ['from' => 'applied', 'to' => 'screening'],
            'occurred_at' => '2026-04-15 14:00:00',
        ]);
    }

    $apps[0]->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => 'screening', 'to' => 'interview_hr'],
        'occurred_at' => '2026-04-16 10:00:00',
    ]);
    $apps[1]->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => 'screening', 'to' => 'interview_tech'],
        'occurred_at' => '2026-04-16 11:00:00',
    ]);

    $apps[0]->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => 'interview_hr', 'to' => 'offer'],
        'occurred_at' => '2026-04-17 10:00:00',
    ]);

    $response = $this->actingAs($user)->getJson('/api/metrics');

    $response->assertOk()
        ->assertJsonPath('kpis.total_applications', 10)
        ->assertJsonPath('kpis.total_responses', 4)
        ->assertJsonPath('kpis.total_interviews', 2)
        ->assertJsonPath('kpis.total_offers', 1)
        ->assertJsonPath('kpis.response_rate', 0.4)
        ->assertJsonPath('kpis.interview_rate', 0.2)
        ->assertJsonPath('kpis.offer_rate', 0.1);
});

it('builds channels breakdown with response rate from events', function (): void {
    $user = User::factory()->create();

    $linkedin = Application::factory()->for($user)->create([
        'applied_at' => '2026-04-17 10:00:00',
        'source' => 'linkedin',
    ]);
    Application::factory()->for($user)->create([
        'applied_at' => '2026-04-17 11:00:00',
        'source' => 'linkedin',
    ]);
    Application::factory()->for($user)->create([
        'applied_at' => '2026-04-17 12:00:00',
        'source' => 'gupy',
    ]);

    $linkedin->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => 'applied', 'to' => 'screening'],
        'occurred_at' => '2026-04-17 15:00:00',
    ]);

    $response = $this->actingAs($user)->getJson('/api/metrics');

    $response->assertOk();
    $channels = collect($response->json('channels'))->keyBy('source');
    expect($channels['linkedin']['applications'])->toBe(2);
    expect($channels['linkedin']['responses'])->toBe(1);
    expect($channels['linkedin']['response_rate'])->toBe(0.5);
    expect($channels['gupy']['applications'])->toBe(1);
    expect($channels['gupy']['responses'])->toBe(0);
});

it('counts funnel reach including applications already past a stage', function (): void {
    $user = User::factory()->create();

    // Candidatura que já foi até interview_hr
    $app = Application::factory()->for($user)->create([
        'applied_at' => '2026-04-17 10:00:00',
        'status' => ApplicationStatus::InterviewHR->value,
    ]);
    $app->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => 'applied', 'to' => 'screening'],
        'occurred_at' => '2026-04-17 11:00:00',
    ]);
    $app->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => 'screening', 'to' => 'interview_hr'],
        'occurred_at' => '2026-04-17 15:00:00',
    ]);

    // Candidatura rejeitada direto — só "applied" alcançado
    Application::factory()->for($user)->create([
        'applied_at' => '2026-04-17 12:00:00',
        'status' => ApplicationStatus::Rejected->value,
    ]);

    $response = $this->actingAs($user)->getJson('/api/metrics');

    $response->assertOk();
    $funnel = collect($response->json('funnel'))->keyBy('status');
    expect($funnel['applied']['reached'])->toBe(2);
    expect($funnel['screening']['reached'])->toBe(1);
    expect($funnel['interview_hr']['reached'])->toBe(1);
    expect($funnel['offer']['reached'])->toBe(0);
});

it('emits an insight when response rate is low', function (): void {
    $user = User::factory()->create();

    $apps = Application::factory()->for($user)->count(20)->create([
        'applied_at' => '2026-04-17 10:00:00',
    ]);

    $apps[0]->events()->create([
        'event_type' => 'status_changed',
        'payload' => ['from' => 'applied', 'to' => 'screening'],
        'occurred_at' => '2026-04-17 14:00:00',
    ]);

    $response = $this->actingAs($user)->getJson('/api/metrics');

    $keys = collect($response->json('insights'))->pluck('key');
    expect($keys)->toContain('low_response_rate');
});

it('emits a no_data insight when there is no activity', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/metrics');

    $response->assertOk();
    $keys = collect($response->json('insights'))->pluck('key');
    expect($keys)->toContain('no_data');
});

it('requires authentication', function (): void {
    $this->getJson('/api/metrics')->assertUnauthorized();
});
