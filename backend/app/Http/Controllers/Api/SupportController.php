<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Support\StoreSupportRequest;
use App\Mail\SupportRequestMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

final class SupportController extends Controller
{
    public function store(StoreSupportRequest $request): JsonResponse
    {
        $data = $request->validated();
        $recipient = (string) config('services.support.recipient');

        if ($recipient === '') {
            // Mal-configurado em prod — log e responde sucesso pra não quebrar UX
            // (usuário não tem como saber/resolver isso).
            Log::error('support.recipient_not_configured');

            return response()->json(['ok' => true]);
        }

        try {
            Mail::to($recipient)->send(new SupportRequestMail(
                senderName: trim((string) ($data['name'] ?? '')) !== ''
                    ? (string) $data['name']
                    : __('support.anonymous_name'),
                senderEmail: (string) $data['email'],
                subjectLine: (string) $data['title'],
                messageBody: (string) $data['description'],
            ));
        } catch (Throwable $e) {
            Log::error('support.mail_failed', ['error' => $e->getMessage()]);

            return response()->json([
                'message' => __('support.errors.send_failed'),
            ], 500);
        }

        return response()->json(['ok' => true]);
    }
}
