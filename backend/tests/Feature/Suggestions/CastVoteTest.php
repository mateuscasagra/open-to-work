<?php

declare(strict_types=1);

use App\Models\Suggestion;
use App\Models\SuggestionVote;
use App\Models\User;

it('requires authentication', function (): void {
    $suggestion = Suggestion::factory()->create();
    $this->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'up'])
        ->assertUnauthorized();
});

it('returns 404 for non-existent suggestion', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user)
        ->postJson('/api/suggestions/999/vote', ['value' => 'up'])
        ->assertNotFound();
});

it('validates value field is up or down', function (): void {
    $voter = User::factory()->create();
    $suggestion = Suggestion::factory()->create();

    $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'sideways'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);

    $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['value']);
});

it('creates an upvote and recomputes counters', function (): void {
    $voter = User::factory()->create();
    $suggestion = Suggestion::factory()->create();

    $response = $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'up']);

    $response->assertOk()
        ->assertJsonPath('id', $suggestion->id)
        ->assertJsonPath('upvotes_count', 1)
        ->assertJsonPath('downvotes_count', 0)
        ->assertJsonPath('score', 1)
        ->assertJsonPath('my_vote', 1);

    $this->assertDatabaseHas('suggestion_votes', [
        'user_id' => $voter->id,
        'suggestion_id' => $suggestion->id,
        'value' => 1,
    ]);

    $suggestion->refresh();
    expect($suggestion->upvotes_count)->toBe(1);
    expect($suggestion->score)->toBe(1);
});

it('creates a downvote and recomputes counters', function (): void {
    $voter = User::factory()->create();
    $suggestion = Suggestion::factory()->create();

    $response = $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'down']);

    $response->assertOk()
        ->assertJsonPath('upvotes_count', 0)
        ->assertJsonPath('downvotes_count', 1)
        ->assertJsonPath('score', -1)
        ->assertJsonPath('my_vote', -1);
});

it('toggles off when same value is sent twice', function (): void {
    $voter = User::factory()->create();
    $suggestion = Suggestion::factory()->create();

    $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'up'])
        ->assertOk();

    $second = $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'up']);

    $second->assertOk()
        ->assertJsonPath('upvotes_count', 0)
        ->assertJsonPath('downvotes_count', 0)
        ->assertJsonPath('score', 0)
        ->assertJsonPath('my_vote', null);

    $this->assertDatabaseMissing('suggestion_votes', [
        'user_id' => $voter->id,
        'suggestion_id' => $suggestion->id,
    ]);
});

it('replaces vote when opposite value is sent (up then down)', function (): void {
    $voter = User::factory()->create();
    $suggestion = Suggestion::factory()->create();

    $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'up'])
        ->assertOk();

    $second = $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'down']);

    $second->assertOk()
        ->assertJsonPath('upvotes_count', 0)
        ->assertJsonPath('downvotes_count', 1)
        ->assertJsonPath('score', -1)
        ->assertJsonPath('my_vote', -1);

    expect(SuggestionVote::where('user_id', $voter->id)->where('suggestion_id', $suggestion->id)->count())->toBe(1);
});

it('forbids voting on own suggestion (403)', function (): void {
    $author = User::factory()->create();
    $suggestion = Suggestion::factory()->for($author)->create();

    $response = $this->actingAs($author)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'up']);

    $response->assertForbidden();
    $this->assertDatabaseMissing('suggestion_votes', ['suggestion_id' => $suggestion->id]);
});

it('enforces a single vote per user per suggestion (no duplicates)', function (): void {
    $voter = User::factory()->create();
    $suggestion = Suggestion::factory()->create();

    $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'up'])
        ->assertOk();
    $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'down'])
        ->assertOk();
    $this->actingAs($voter)
        ->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'up'])
        ->assertOk();

    expect(SuggestionVote::where('suggestion_id', $suggestion->id)->count())->toBe(1);
});

it('aggregates votes from multiple users correctly', function (): void {
    $author = User::factory()->create();
    $suggestion = Suggestion::factory()->for($author)->create();
    $u1 = User::factory()->create();
    $u2 = User::factory()->create();
    $u3 = User::factory()->create();

    $this->actingAs($u1)->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'up'])->assertOk();
    $this->actingAs($u2)->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'up'])->assertOk();
    $this->actingAs($u3)->postJson("/api/suggestions/{$suggestion->id}/vote", ['value' => 'down'])->assertOk();

    $suggestion->refresh();
    expect($suggestion->upvotes_count)->toBe(2);
    expect($suggestion->downvotes_count)->toBe(1);
    expect($suggestion->score)->toBe(1);
});
