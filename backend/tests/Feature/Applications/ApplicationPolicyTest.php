<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\User;
use App\Policies\ApplicationPolicy;

it('allows the owner to view/update/delete', function (): void {
    $user = User::factory()->create();
    $app = Application::factory()->create(['user_id' => $user->id]);
    $policy = new ApplicationPolicy;

    expect($policy->view($user, $app))->toBeTrue()
        ->and($policy->update($user, $app))->toBeTrue()
        ->and($policy->delete($user, $app))->toBeTrue();
});

it('denies access to non-owner', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    $app = Application::factory()->create(['user_id' => $owner->id]);
    $policy = new ApplicationPolicy;

    expect($policy->view($stranger, $app))->toBeFalse()
        ->and($policy->update($stranger, $app))->toBeFalse()
        ->and($policy->delete($stranger, $app))->toBeFalse();
});
