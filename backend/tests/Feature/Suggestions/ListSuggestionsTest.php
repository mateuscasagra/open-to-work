<?php

declare(strict_types=1);

use App\Models\Suggestion;
use App\Models\SuggestionVote;
use App\Models\User;

it('requires authentication', function (): void {
    $this->getJson('/api/suggestions')->assertUnauthorized();
});

it('lists all users\' suggestions paginated', function (): void {
    $authors = User::factory()->count(3)->create();
    foreach ($authors as $author) {
        Suggestion::factory()->count(2)->for($author)->create();
    }

    $viewer = User::factory()->create();

    $response = $this->actingAs($viewer)->getJson('/api/suggestions');

    $response->assertOk()
        ->assertJsonCount(6, 'data')
        ->assertJsonStructure([
            'data' => [
                ['id', 'user' => ['id', 'name'], 'title', 'body', 'upvotes_count', 'downvotes_count', 'score', 'my_vote', 'rank', 'created_at'],
            ],
            'current_page',
            'last_page',
            'total',
        ]);
});

it('orders by score DESC then created_at ASC (tiebreaker)', function (): void {
    $author = User::factory()->create();

    $low = Suggestion::factory()->for($author)->create(['score' => 1, 'created_at' => now()->subDays(5)]);
    $highOlder = Suggestion::factory()->for($author)->create(['score' => 10, 'created_at' => now()->subDays(3)]);
    $mid = Suggestion::factory()->for($author)->create(['score' => 5, 'created_at' => now()->subDays(2)]);
    $highNewer = Suggestion::factory()->for($author)->create(['score' => 10, 'created_at' => now()->subDay()]);

    $viewer = User::factory()->create();
    $response = $this->actingAs($viewer)->getJson('/api/suggestions');

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$highOlder->id, $highNewer->id, $mid->id, $low->id]);
});

it('includes my_vote reflecting current user vote', function (): void {
    $author = User::factory()->create();
    $voter = User::factory()->create();

    $upvoted = Suggestion::factory()->for($author)->create(['score' => 1]);
    $downvoted = Suggestion::factory()->for($author)->create(['score' => -1]);
    $unvoted = Suggestion::factory()->for($author)->create(['score' => 0]);

    SuggestionVote::factory()->create(['user_id' => $voter->id, 'suggestion_id' => $upvoted->id, 'value' => 1]);
    SuggestionVote::factory()->create(['user_id' => $voter->id, 'suggestion_id' => $downvoted->id, 'value' => -1]);

    $response = $this->actingAs($voter)->getJson('/api/suggestions');

    $byId = collect($response->json('data'))->keyBy('id');
    expect($byId[$upvoted->id]['my_vote'])->toBe(1);
    expect($byId[$downvoted->id]['my_vote'])->toBe(-1);
    expect($byId[$unvoted->id]['my_vote'])->toBeNull();
});

it('marks top 3 with rank globally regardless of pagination', function (): void {
    $author = User::factory()->create();

    Suggestion::factory()->count(25)->for($author)->create(['score' => 0]);
    $first = Suggestion::factory()->for($author)->create(['score' => 100, 'created_at' => now()->subMinutes(3)]);
    $second = Suggestion::factory()->for($author)->create(['score' => 50, 'created_at' => now()->subMinutes(2)]);
    $third = Suggestion::factory()->for($author)->create(['score' => 25, 'created_at' => now()->subMinute()]);

    $viewer = User::factory()->create();
    $response = $this->actingAs($viewer)->getJson('/api/suggestions');

    $page1 = collect($response->json('data'))->keyBy('id');
    expect($page1[$first->id]['rank'])->toBe(1);
    expect($page1[$second->id]['rank'])->toBe(2);
    expect($page1[$third->id]['rank'])->toBe(3);
    foreach ($page1 as $id => $row) {
        if (! in_array($id, [$first->id, $second->id, $third->id], true)) {
            expect($row['rank'])->toBeNull();
        }
    }

    $response2 = $this->actingAs($viewer)->getJson('/api/suggestions?page=2');
    foreach ($response2->json('data') as $row) {
        expect($row['rank'])->toBeNull();
    }
});
