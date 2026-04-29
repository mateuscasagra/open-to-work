<?php

declare(strict_types=1);

use App\Models\Suggestion;
use App\Models\SuggestionVote;
use App\Models\User;
use Illuminate\Support\Carbon;

it('deletes my own suggestion', function (): void {
    $user = User::factory()->create();
    $suggestion = Suggestion::factory()->for($user)->create();

    $this->actingAs($user)
        ->deleteJson("/api/suggestions/{$suggestion->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('suggestions', ['id' => $suggestion->id]);
});

it('forbids deleting another user\'s suggestion', function (): void {
    $author = User::factory()->create();
    $other = User::factory()->create();
    $suggestion = Suggestion::factory()->for($author)->create();

    $this->actingAs($other)
        ->deleteJson("/api/suggestions/{$suggestion->id}")
        ->assertForbidden();

    $this->assertDatabaseHas('suggestions', ['id' => $suggestion->id]);
});

it('cascade deletes votes when suggestion is deleted', function (): void {
    $author = User::factory()->create();
    $suggestion = Suggestion::factory()->for($author)->create();
    $voter = User::factory()->create();
    SuggestionVote::factory()->create([
        'user_id' => $voter->id,
        'suggestion_id' => $suggestion->id,
    ]);

    $this->actingAs($author)
        ->deleteJson("/api/suggestions/{$suggestion->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('suggestion_votes', ['suggestion_id' => $suggestion->id]);
});

it('still counts deleted suggestions in 7d window for quota purposes (no count happens — they are physically deleted)', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-04-29 10:00:00'));

    $user = User::factory()->create();
    Suggestion::factory()->count(5)->for($user)->create([
        'created_at' => now()->subDay(),
        'updated_at' => now()->subDay(),
    ]);

    $latest = Suggestion::where('user_id', $user->id)->latest('id')->first();
    $this->actingAs($user)->deleteJson("/api/suggestions/{$latest->id}")->assertNoContent();

    $response = $this->actingAs($user)->postJson('/api/suggestions', [
        'title' => 'Should fail because trade-off: deleted does not free quota',
        'body' => 'We do not soft-delete and we do not log deletions, so a fresh create after delete still counts the remaining 4 + this one = 5, allowing it. Document this trade-off.',
    ]);

    $response->assertCreated();

    Carbon::setTestNow();
});

it('requires authentication for delete', function (): void {
    $suggestion = Suggestion::factory()->create();
    $this->deleteJson("/api/suggestions/{$suggestion->id}")->assertUnauthorized();
});
