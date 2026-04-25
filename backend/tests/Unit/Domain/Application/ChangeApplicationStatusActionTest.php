<?php

declare(strict_types=1);

use App\Domain\Application\Actions\ChangeApplicationStatus;
use App\Enums\ApplicationStatus;
use App\Models\Application;
use App\Models\ApplicationEvent;
use App\Models\User;

it('changes status on valid transition and logs event', function (): void {
    $user = User::factory()->create();
    $app = Application::factory()->inStatus(ApplicationStatus::Applied)->create(['user_id' => $user->id]);

    (new ChangeApplicationStatus)->execute($app, ApplicationStatus::Screening, 'em contato');

    expect($app->fresh()->status)->toBe(ApplicationStatus::Screening);
    expect(ApplicationEvent::where('application_id', $app->id)->count())->toBe(1);
});

it('throws on invalid transition', function (): void {
    $user = User::factory()->create();
    $app = Application::factory()->inStatus(ApplicationStatus::Applied)->create(['user_id' => $user->id]);

    expect(fn () => (new ChangeApplicationStatus)->execute($app, ApplicationStatus::Accepted))
        ->toThrow(DomainException::class);

    expect($app->fresh()->status)->toBe(ApplicationStatus::Applied);
});
