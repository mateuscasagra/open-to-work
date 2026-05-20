<?php

declare(strict_types=1);

namespace App\Domain\Subscription\DTOs;

use Spatie\LaravelData\Data;

final class CreateAsaasSubscriptionResult extends Data
{
    public function __construct(
        public string $asaasSubscriptionId,
        public string $asaasCustomerId,
        public string $paymentId,
        public string $pixQrCodeBase64,
        public string $pixCopyPaste,
        public string $dueDate,            // YYYY-MM-DD
        public ?string $expirationDate,    // ISO8601 ou null
    ) {}
}
