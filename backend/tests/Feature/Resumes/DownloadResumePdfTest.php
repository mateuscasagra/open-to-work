<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('s3');
});

it('returns a download url for a pdf-uploaded resume', function (): void {
    $user = User::factory()->create();
    Storage::disk('s3')->put('resumes/x.pdf', 'pdf-bytes');

    $resume = Resume::factory()->create([
        'user_id' => $user->id,
        'is_pdf_upload' => true,
        'file_path' => 'resumes/x.pdf',
    ]);

    $response = $this->actingAs($user)
        ->getJson("/api/resumes/{$resume->id}/download")
        ->assertOk()
        ->assertJsonStructure(['url']);

    expect($response->json('url'))->toBeString()->not->toBeEmpty();
});

it('returns 404 for a structured (non-upload) resume', function (): void {
    $user = User::factory()->create();
    $resume = Resume::factory()->create([
        'user_id' => $user->id,
        'is_pdf_upload' => false,
        'file_path' => null,
    ]);

    $this->actingAs($user)
        ->getJson("/api/resumes/{$resume->id}/download")
        ->assertNotFound();
});

it('forbids downloading another user resume', function (): void {
    $owner = User::factory()->create();
    $stranger = User::factory()->create();
    Storage::disk('s3')->put('resumes/y.pdf', 'pdf-bytes');

    $resume = Resume::factory()->create([
        'user_id' => $owner->id,
        'is_pdf_upload' => true,
        'file_path' => 'resumes/y.pdf',
    ]);

    $this->actingAs($stranger)
        ->getJson("/api/resumes/{$resume->id}/download")
        ->assertForbidden();
});
