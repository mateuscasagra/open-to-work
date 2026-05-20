<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Domain\Subscription\Actions\CancelSubscription;
use App\Domain\Subscription\Actions\SubscribeToPro;
use App\Domain\Subscription\DTOs\QuotaData;
use App\Domain\Subscription\Exceptions\AlreadySubscribedException;
use App\Domain\Subscription\Exceptions\AsaasClientException;
use App\Domain\Subscription\Exceptions\NotSubscribedException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscription\StoreSubscriptionRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SubscriptionController extends Controller
{
    public function quota(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(QuotaData::fromUser($user));
    }

    public function store(StoreSubscriptionRequest $request, SubscribeToPro $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $result = $action->execute($user, $request->normalizedCpf());
        } catch (AlreadySubscribedException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        } catch (AsaasClientException) {
            return response()->json([
                'message' => __('subscription.errors.gateway_failure'),
            ], 502);
        }

        return response()->json([
            'pix_qr_code_base64' => $result->pixQrCodeBase64,
            'pix_copy_paste' => $result->pixCopyPaste,
            'due_date' => $result->dueDate,
            'payment_id' => $result->paymentId,
            'asaas_subscription_id' => $result->asaasSubscriptionId,
        ], 201);
    }

    public function destroy(Request $request, CancelSubscription $action): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $action->execute($user);
        } catch (NotSubscribedException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (AsaasClientException) {
            return response()->json([
                'message' => __('subscription.errors.gateway_failure'),
            ], 502);
        }

        return response()->json(['ok' => true]);
    }
}
