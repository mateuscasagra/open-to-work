<?php

declare(strict_types=1);

use App\Models\Application;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    config()->set('media-library.disk_name', 'public');
});

it('uploads a PDF attachment and records an event', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create(['user_id' => $user->id]);

    $pdf = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    $response = $this->actingAs($user)
        ->postJson("/api/applications/{$application->id}/attachments", [
            'file' => $pdf,
        ]);

    $response->assertCreated()
        ->assertJsonStructure(['id', 'file_name', 'mime_type', 'size', 'url']);

    expect($application->fresh()->getMedia(Application::ATTACHMENT_COLLECTION))->toHaveCount(1);
    expect($application->events()->where('event_type', 'attachment_added')->exists())->toBeTrue();
});

it('lists attachments for owner', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create(['user_id' => $user->id]);
    $application->addMedia(UploadedFile::fake()->create('doc.pdf', 50, 'application/pdf'))
        ->toMediaCollection(Application::ATTACHMENT_COLLECTION);

    $this->actingAs($user)
        ->getJson("/api/applications/{$application->id}/attachments")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.file_name', 'doc.pdf');
});

it('rejects unsupported mime types', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create(['user_id' => $user->id]);

    $exe = UploadedFile::fake()->create('malware.exe', 50, 'application/x-msdownload');

    $this->actingAs($user)
        ->postJson("/api/applications/{$application->id}/attachments", ['file' => $exe])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);
});

it('blocks non-owner from uploading', function (): void {
    $me = User::factory()->create();
    $other = User::factory()->create();
    $otherApp = Application::factory()->create(['user_id' => $other->id]);

    $this->actingAs($me)
        ->postJson("/api/applications/{$otherApp->id}/attachments", [
            'file' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf'),
        ])
        ->assertForbidden();
});

it('deletes an attachment', function (): void {
    $user = User::factory()->create();
    $application = Application::factory()->create(['user_id' => $user->id]);
    $media = $application->addMedia(UploadedFile::fake()->create('x.pdf', 10, 'application/pdf'))
        ->toMediaCollection(Application::ATTACHMENT_COLLECTION);

    $this->actingAs($user)
        ->deleteJson("/api/applications/{$application->id}/attachments/{$media->id}")
        ->assertNoContent();

    expect($application->fresh()->getMedia(Application::ATTACHMENT_COLLECTION))->toHaveCount(0);
});
