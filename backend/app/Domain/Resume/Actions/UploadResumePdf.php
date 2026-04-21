<?php

declare(strict_types=1);

namespace App\Domain\Resume\Actions;

use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

final class UploadResumePdf
{
    public function execute(User $user, UploadedFile $file, string $title, string $language = 'pt_BR'): Resume
    {
        $path = sprintf('resumes/%d/%s.pdf', $user->id, Str::uuid()->toString());

        Storage::disk('s3')->putFileAs(
            dirname($path),
            $file,
            basename($path),
            ['visibility' => 'private']
        );

        return Resume::query()->create([
            'user_id' => $user->id,
            'title' => $title,
            'language' => $language,
            'is_pdf_upload' => true,
            'file_path' => $path,
            'metadata' => [
                'original_name' => $file->getClientOriginalName(),
                'size' => $file->getSize(),
            ],
        ]);
    }
}
