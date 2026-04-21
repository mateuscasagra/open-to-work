<?php

declare(strict_types=1);

use App\Enums\ApplicationStatus;

it('allows valid transitions', function (): void {
    expect(ApplicationStatus::Applied->canTransitionTo(ApplicationStatus::Screening))->toBeTrue()
        ->and(ApplicationStatus::InterviewTech->canTransitionTo(ApplicationStatus::Offer))->toBeTrue()
        ->and(ApplicationStatus::Offer->canTransitionTo(ApplicationStatus::Accepted))->toBeTrue();
});

it('rejects invalid transitions', function (): void {
    expect(ApplicationStatus::Applied->canTransitionTo(ApplicationStatus::Accepted))->toBeFalse()
        ->and(ApplicationStatus::Rejected->canTransitionTo(ApplicationStatus::Offer))->toBeFalse()
        ->and(ApplicationStatus::Accepted->canTransitionTo(ApplicationStatus::Rejected))->toBeFalse();
});

it('marks terminal states as final', function (): void {
    expect(ApplicationStatus::Accepted->isFinal())->toBeTrue()
        ->and(ApplicationStatus::Rejected->isFinal())->toBeTrue()
        ->and(ApplicationStatus::Applied->isFinal())->toBeFalse();
});
