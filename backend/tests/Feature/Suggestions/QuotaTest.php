<?php

declare(strict_types=1);

use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Support\Carbon;

it('requires authentication', function (): void {
    $this->getJson('/api/suggestions/quota')->assertUnauthorized();
});

it('returns used=0 limit=5 next_slot_at=null when no suggestions exist', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->getJson('/api/suggestions/quota');

    $response->assertOk()
        ->assertJson(['used' => 0, 'limit' => 5, 'next_slot_at' => null]);
});

it('returns used=N when N suggestions in 7d window', function (): void {
    $user = User::factory()->create();
    Suggestion::factory()->count(3)->for($user)->create();

    $response = $this->actingAs($user)->getJson('/api/suggestions/quota');

    $response->assertOk()
        ->assertJson(['used' => 3, 'limit' => 5, 'next_slot_at' => null]);
});

it('does not count suggestions older than 7 days', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-29 10:00:00'));

    $user = User::factory()->create();
    Suggestion::factory()->count(5)->for($user)->create([
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
    ]);
    Suggestion::factory()->count(2)->for($user)->create([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $response = $this->actingAs($user)->getJson('/api/suggestions/quota');

    $response->assertOk()
        ->assertJson(['used' => 2, 'limit' => 5, 'next_slot_at' => null]);

    Carbon::setTestNow();
});

it('returns next_slot_at when used >= limit (oldest + 7d)', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-29 10:00:00'));

    $user = User::factory()->create();

    $oldest = Suggestion::factory()->for($user)->create([
        'created_at' => now()->subDays(3),
        'updated_at' => now()->subDays(3),
    ]);
    Suggestion::factory()->count(4)->for($user)->create([
        'created_at' => now()->subDays(1),
        'updated_at' => now()->subDays(1),
    ]);

    $response = $this->actingAs($user)->getJson('/api/suggestions/quota');

    $expected = $oldest->created_at->copy()->addDays(7)->toIso8601String();

    $response->assertOk()
        ->assertJson([
            'used' => 5,
            'limit' => 5,
            'next_slot_at' => $expected,
        ]);

    Carbon::setTestNow();
});

it('only counts the calling user\'s suggestions', function (): void {
    $user = User::factory()->create();
    $other = User::factory()->create();

    Suggestion::factory()->count(2)->for($user)->create();
    Suggestion::factory()->count(4)->for($other)->create();

    $response = $this->actingAs($user)->getJson('/api/suggestions/quota');

    $response->assertOk()->assertJson(['used' => 2]);
});
