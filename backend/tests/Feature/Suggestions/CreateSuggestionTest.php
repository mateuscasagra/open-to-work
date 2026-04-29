<?php

declare(strict_types=1);

use App\Models\Suggestion;
use App\Models\User;
use Illuminate\Support\Carbon;

it('creates a suggestion for the authenticated user', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/suggestions', [
        'title' => 'Add dark mode to the dashboard',
        'body' => 'It would be nice to have a dark mode for late night job hunting.',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonPath('title', 'Add dark mode to the dashboard')
        ->assertJsonPath('upvotes_count', 0)
        ->assertJsonPath('downvotes_count', 0)
        ->assertJsonPath('score', 0)
        ->assertJsonPath('my_vote', null)
        ->assertJsonPath('rank', 1);

    $this->assertDatabaseHas('suggestions', [
        'user_id' => $user->id,
        'title' => 'Add dark mode to the dashboard',
    ]);
});

it('requires authentication', function (): void {
    $response = $this->postJson('/api/suggestions', [
        'title' => 'Some title here',
        'body' => 'A reasonable body for the suggestion.',
    ]);

    $response->assertUnauthorized();
});

it('validates required fields', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/suggestions', []);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'body']);
});

it('validates min lengths', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/suggestions', [
        'title' => 'abc',
        'body' => 'short',
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'body']);
});

it('validates max lengths', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson('/api/suggestions', [
        'title' => str_repeat('a', 121),
        'body' => str_repeat('b', 2001),
    ]);

    $response->assertUnprocessable()
        ->assertJsonValidationErrors(['title', 'body']);
});

it('rejects 6th suggestion in a 7-day window with 429 and next_slot_at', function (): void {
    $user = User::factory()->create();

    Suggestion::factory()->count(5)->for($user)->create();

    $response = $this->actingAs($user)->postJson('/api/suggestions', [
        'title' => 'Sixth suggestion of the week',
        'body' => 'This should fail because the user already has five suggestions.',
    ]);

    $response->assertStatus(429)
        ->assertJsonStructure(['message', 'next_slot_at']);

    expect(Suggestion::where('user_id', $user->id)->count())->toBe(5);
});

it('allows a new suggestion when the oldest is older than 7 days', function (): void {
    $user = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-04-29 10:00:00'));

    Suggestion::factory()->count(5)->for($user)->create([
        'created_at' => now()->subDays(8),
        'updated_at' => now()->subDays(8),
    ]);

    $response = $this->actingAs($user)->postJson('/api/suggestions', [
        'title' => 'Fresh suggestion after window',
        'body' => 'The oldest 5 suggestions are out of the rolling 7d window.',
    ]);

    $response->assertCreated();
    expect(Suggestion::where('user_id', $user->id)->count())->toBe(6);

    Carbon::setTestNow();
});

it('counts soft-recent suggestions (within 7d) toward the quota even when other older ones exist', function (): void {
    $user = User::factory()->create();

    Carbon::setTestNow(Carbon::parse('2026-04-29 10:00:00'));

    Suggestion::factory()->count(3)->for($user)->create([
        'created_at' => now()->subDays(10),
        'updated_at' => now()->subDays(10),
    ]);
    Suggestion::factory()->count(5)->for($user)->create([
        'created_at' => now()->subDays(2),
        'updated_at' => now()->subDays(2),
    ]);

    $response = $this->actingAs($user)->postJson('/api/suggestions', [
        'title' => 'Should fail — 5 already in 7d window',
        'body' => 'Even though there are old ones, 5 fresh ones already fill the quota.',
    ]);

    $response->assertStatus(429);

    Carbon::setTestNow();
});
