<?php

declare(strict_types=1);

use App\Models\Company;
use App\Models\Job;
use App\Models\User;

it('searches jobs by title via Scout', function (): void {
    $user = User::factory()->create();
    $acme = Company::factory()->create(['name' => 'Acme']);
    Job::factory()->create(['title' => 'Backend Engineer Kotlin', 'company_id' => $acme->id]);
    Job::factory()->create(['title' => 'Frontend React Developer', 'company_id' => $acme->id]);

    $response = $this->actingAs($user)->getJson('/api/jobs?q=Kotlin');

    $response->assertOk();
    $titles = collect($response->json('data'))->pluck('title');
    expect($titles)->toContain('Backend Engineer Kotlin')
        ->and($titles)->not->toContain('Frontend React Developer');
});

it('returns empty when search has no match', function (): void {
    $user = User::factory()->create();
    Job::factory()->create(['title' => 'Senior PHP']);

    $response = $this->actingAs($user)->getJson('/api/jobs?q=Rust');

    $response->assertOk()->assertJsonCount(0, 'data');
});
