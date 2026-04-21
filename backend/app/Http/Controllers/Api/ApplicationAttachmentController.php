<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Application;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class ApplicationAttachmentController extends Controller
{
    private const ALLOWED_MIMES = 'application/pdf,image/png,image/jpeg,image/webp,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document';

    public function index(Application $application): JsonResponse
    {
        Gate::authorize('view', $application);

        return response()->json([
            'data' => $application->getMedia(Application::ATTACHMENT_COLLECTION)->map(
                fn (Media $m): array => $this->serialize($m)
            ),
        ]);
    }

    public function store(Request $request, Application $application): JsonResponse
    {
        Gate::authorize('update', $application);

        $request->validate([
            'file' => [
                'required',
                'file',
                'max:'.(int) (Application::MAX_ATTACHMENT_BYTES / 1024),
                'mimetypes:'.self::ALLOWED_MIMES,
            ],
        ]);

        $media = $application
            ->addMediaFromRequest('file')
            ->toMediaCollection(Application::ATTACHMENT_COLLECTION);

        $application->events()->create([
            'event_type' => 'attachment_added',
            'payload' => ['media_id' => $media->id, 'name' => $media->file_name],
            'occurred_at' => now(),
        ]);

        return response()->json($this->serialize($media), 201);
    }

    public function destroy(Application $application, Media $media): JsonResponse
    {
        Gate::authorize('update', $application);

        abort_if($media->model_id !== $application->id, 404);
        abort_if($media->collection_name !== Application::ATTACHMENT_COLLECTION, 404);

        $media->delete();

        return response()->json(null, 204);
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Media $media): array
    {
        return [
            'id' => $media->id,
            'name' => $media->name,
            'file_name' => $media->file_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'url' => $media->getUrl(),
            'created_at' => $media->created_at?->toIso8601String(),
        ];
    }
}
