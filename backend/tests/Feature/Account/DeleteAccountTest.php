<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

it('deletes the user and cascades personal data', function (): void {
    Storage::fake('s3');

    $user = User::factory()->create();
    $profile = $user->profile()->create(['desired_role' => 'Dev']);
    Resume::factory()->create(['user_id' => $user->id]);
    Application::factory()->for($user)->create();

    $response = $this->actingAs($user)->deleteJson('/api/account');

    $response->assertOk()->assertJsonPath('ok', true);

    expect(User::find($user->id))->toBeNull();
    expect($user->resumes()->count())->toBe(0);
    expect($user->applications()->count())->toBe(0);
    unset($profile);
});

it('removes uploaded resume PDF from storage', function (): void {
    Storage::fake('s3');
    $user = User::factory()->create();

    Storage::disk('s3')->put('resumes/abc.pdf', 'fake-pdf-bytes');
    Resume::factory()->create([
        'user_id' => $user->id,
        'is_pdf_upload' => true,
        'file_path' => 'resumes/abc.pdf',
    ]);

    $this->actingAs($user)->deleteJson('/api/account')->assertOk();

    Storage::disk('s3')->assertMissing('resumes/abc.pdf');
});

it('requires authentication', function (): void {
    $this->deleteJson('/api/account')->assertUnauthorized();
});
