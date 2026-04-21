<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Account\Actions\DeleteAccount;
use App\Domain\Account\Actions\ExportUserData;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class AccountController extends Controller
{
    public function export(Request $request, ExportUserData $action): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();

        $payload = $action->execute($user);
        $filename = sprintf('opentowork-export-%d-%s.json', $user->id, now()->format('Ymd-His'));

        return response()->streamDownload(
            static function () use ($payload): void {
                echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            },
            $filename,
            ['Content-Type' => 'application/json'],
        );
    }

    public function destroy(Request $request, DeleteAccount $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $action->execute($user);

        if ($request->hasSession()) {
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }

        return response()->json(['ok' => true]);
    }
}
