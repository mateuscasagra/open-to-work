<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Subscription\Actions\ProcessAsaasWebhook;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Endpoint público que recebe webhooks do Asaas. Validação em camadas:
 *  1. Header `asaas-access-token` deve bater com env var.
 *  2. Payload precisa de `id` e `event` (string).
 *  3. Idempotência via INSERT-or-skip em `webhook_logs.event_id` (PK).
 *  4. ProcessAsaasWebhook action despacha por tipo de evento.
 *
 * Processamento síncrono em fase 1 (volume baixo). Se falhar, salva o erro em
 * webhook_logs.error e re-throw → Sentry pega via render() do bootstrap.
 */
final class AsaasWebhookController extends Controller
{
    public function __invoke(Request $request, ProcessAsaasWebhook $action): JsonResponse
    {
        $expected = (string) config('services.asaas.webhook_token');
        $provided = (string) $request->header('asaas-access-token', '');

        if ($expected === '' || $provided === '' || ! hash_equals($expected, $provided)) {
            return response()->json(['message' => 'invalid_token'], 401);
        }

        /** @var array<string, mixed> $payload */
        $payload = $request->json()->all();
        $eventId = $payload['id'] ?? null;
        $eventType = $payload['event'] ?? null;

        if (! is_string($eventId) || $eventId === '' || ! is_string($eventType) || $eventType === '') {
            return response()->json(['message' => 'malformed'], 400);
        }

        // Idempotência: INSERT atômico. Se Asaas reentregar, retornamos 200
        // duplicate sem reprocessar (Asaas para de tentar).
        $inserted = DB::table('webhook_logs')->insertOrIgnore([
            'event_id' => $eventId,
            'source' => 'asaas',
            'event_type' => $eventType,
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR),
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            return response()->json(['status' => 'duplicate']);
        }

        try {
            $action->execute($payload);
            DB::table('webhook_logs')
                ->where('event_id', $eventId)
                ->update(['processed_at' => now()]);
        } catch (Throwable $e) {
            DB::table('webhook_logs')
                ->where('event_id', $eventId)
                ->update(['error' => mb_substr($e->getMessage(), 0, 1000)]);
            throw $e;
        }

        return response()->json(['status' => 'ok']);
    }
}
