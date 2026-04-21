<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\User;

it('belongs to a user', function (): void {
    $user = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $user->id]);

    expect($resume->user->id)->toBe($user->id);
});

it('can have sections', function (): void {
    $resume = Resume::factory()->create();
    $resume->sections()->create([
        'type' => 'experience',
        'order' => 0,
        'content' => ['company' => 'Acme', 'role' => 'Dev'],
    ]);

    expect($resume->sections)->toHaveCount(1);
    expect($resume->sections->first()->content['company'])->toBe('Acme');
});
