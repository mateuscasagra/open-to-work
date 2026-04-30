<?php

declare(strict_types=1);

use App\Models\Resume;
use App\Models\User;

it('creates a structured resume with sections', function (): void {
    $user = User::factory()->create();

    $payload = [
        'title' => 'Fullstack Pleno',
        'language' => 'pt_BR',
        'sections' => [
            [
                'type' => 'summary',
                'order' => 0,
                'content' => ['text' => 'Dev com 5 anos em PHP e Vue.'],
            ],
            [
                'type' => 'experience',
                'order' => 1,
                'content' => [
                    'company' => 'Acme',
                    'role' => 'Developer',
                    'startDate' => '2022-01',
                    'endDate' => null,
                    'current' => true,
                    'description' => 'Stack Laravel + Vue.',
                ],
            ],
            [
                'type' => 'skill',
                'order' => 2,
                'content' => ['name' => 'PHP', 'level' => 'expert'],
            ],
        ],
    ];

    $response = $this->actingAs($user)
        ->postJson('/api/resumes', $payload)
        ->assertCreated()
        ->assertJsonPath('title', 'Fullstack Pleno')
        ->assertJsonPath('is_pdf_upload', false)
        ->assertJsonCount(3, 'sections');

    expect(Resume::count())->toBe(1);
    expect(Resume::first()->sections()->count())->toBe(3);
    expect($response->json('user_id'))->toBe($user->id);
});

it('creates a resume with no sections', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/resumes', [
            'title' => 'Vazio',
            'language' => 'en',
        ])
        ->assertCreated()
        ->assertJsonPath('title', 'Vazio')
        ->assertJsonCount(0, 'sections');
});

it('returns file_path and metadata in response (frontend schema requires them)', function (): void {
    $user = User::factory()->create();

    $response = $this->actingAs($user)
        ->postJson('/api/resumes', ['title' => 'X', 'language' => 'pt_BR'])
        ->assertCreated();

    expect($response->json())->toHaveKeys(['id', 'user_id', 'title', 'language', 'is_pdf_upload', 'file_path', 'metadata', 'sections']);
    expect($response->json('file_path'))->toBeNull();
});

it('validates required title', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/resumes', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['title']);
});

it('rejects unsupported language', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/resumes', [
            'title' => 'X',
            'language' => 'fr',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['language']);
});

it('rejects unsupported section type', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/resumes', [
            'title' => 'X',
            'sections' => [['type' => 'hobby', 'order' => 0, 'content' => []]],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sections.0.type']);
});
