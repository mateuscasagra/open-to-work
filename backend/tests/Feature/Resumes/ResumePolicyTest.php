<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\User;
use App\Policies\ResumePolicy;

beforeEach(function (): void {
    $this->policy = new ResumePolicy;
});

it('allows owner to view, update, delete', function (): void {
    $user = User::factory()->create();
    $resume = Resume::factory()->create(['user_id' => $user->id]);

    expect($this->policy->view($user, $resume))->toBeTrue()
        ->and($this->policy->update($user, $resume))->toBeTrue()
        ->and($this->policy->delete($user, $resume))->toBeTrue();
});

it('denies non-owner', function (): void {
    $resume = Resume::factory()->create();
    $stranger = User::factory()->create();

    expect($this->policy->view($stranger, $resume))->toBeFalse()
        ->and($this->policy->update($stranger, $resume))->toBeFalse()
        ->and($this->policy->delete($stranger, $resume))->toBeFalse();
});
