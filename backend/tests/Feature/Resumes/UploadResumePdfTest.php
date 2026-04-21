<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('s3');
});

it('uploads a pdf and creates a resume flagged as pdf_upload', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('curriculo.pdf', 200, 'application/pdf');

    $response = $this->actingAs($user)->postJson('/api/resumes/pdf', [
        'title' => 'CV Uploadado',
        'language' => 'pt_BR',
        'file' => $file,
    ])->assertCreated()
        ->assertJsonPath('title', 'CV Uploadado')
        ->assertJsonPath('is_pdf_upload', true);

    $resume = Resume::first();
    expect($resume)->not->toBeNull();
    expect($resume->user_id)->toBe($user->id);
    expect($resume->file_path)->toStartWith("resumes/{$user->id}/");
    expect($resume->file_path)->toEndWith('.pdf');
    expect($resume->metadata['original_name'])->toBe('curriculo.pdf');

    Storage::disk('s3')->assertExists($resume->file_path);
    expect($response->json('file_path'))->toBe($resume->file_path);
});

it('rejects non-pdf files', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('cv.docx', 100, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document');

    $this->actingAs($user)->postJson('/api/resumes/pdf', [
        'title' => 'X',
        'file' => $file,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['file']);

    expect(Resume::count())->toBe(0);
});

it('requires title', function (): void {
    $user = User::factory()->create();
    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');

    $this->actingAs($user)->postJson('/api/resumes/pdf', ['file' => $file])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('requires authentication', function (): void {
    $file = UploadedFile::fake()->create('cv.pdf', 100, 'application/pdf');
    $this->postJson('/api/resumes/pdf', ['title' => 'X', 'file' => $file])
        ->assertUnauthorized();
});
