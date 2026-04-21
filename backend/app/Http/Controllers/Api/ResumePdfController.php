<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Resume\Actions\UploadResumePdf;
use App\Http\Controllers\Controller;
use App\Http\Requests\Resume\UploadResumePdfRequest;
use App\Models\Resume;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class ResumePdfController extends Controller
{
    public function store(UploadResumePdfRequest $request, UploadResumePdf $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $resume = $action->execute(
            $user,
            $request->file('file'),
            $request->validated('title'),
            $request->validated('language') ?? 'pt_BR',
        );

        return response()->json($resume, 201);
    }

    public function download(Resume $resume): JsonResponse
    {
        Gate::authorize('view', $resume);

        abort_unless(
            $resume->is_pdf_upload && $resume->file_path !== null,
            404,
            'Resume has no uploaded PDF.'
        );

        $disk = Storage::disk('s3');

        try {
            $url = $disk->temporaryUrl($resume->file_path, now()->addMinutes(5));
        } catch (RuntimeException) {
            // Fallback for fake/local drivers in tests.
            $url = $disk->url($resume->file_path);
        }

        return response()->json(['url' => $url]);
    }
}
