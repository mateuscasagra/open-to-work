<?php

declare(strict_types=1);

namespace App\Domain\Application\Actions;

use App\Domain\Application\Actions\SendApplicationEmail;
use App\Domain\Application\DTOs\ApplicationData;
use App\Domain\Application\Exceptions\DuplicateApplicationException;
use App\Enums\ApplicationStatus;
use App\Events\ApplicationCreated;
use App\Models\Application;
use App\Models\User;

final class CreateApplication
{
    public function __construct(
        private readonly SendApplicationEmail $sendEmail,
    ) {}

    public function execute(User $user, ApplicationData $data): Application
    {
        $exists = Application::query()
            ->where('user_id', $user->id)
            ->where('job_id', $data->jobId)
            ->exists();

        if ($exists) {
            throw new DuplicateApplicationException;
        }

        $application = Application::query()->create([
            'user_id' => $user->id,
            'job_id' => $data->jobId,
            'resume_id' => $data->resumeId,
            'status' => ApplicationStatus::Applied->value,
            'applied_at' => now(),
            'source' => $data->source,
            'notes' => $data->notes,
            'expected_salary' => $data->expectedSalary,
        ]);

        event(new ApplicationCreated($application));

        $this->sendEmail->execute($application, $data);

        return $application;
    }
}
