<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Exceptions;

use RuntimeException;

/**
 * Lançada quando uma chamada à API do Asaas falha por motivo técnico
 * (timeout, 5xx, payload inesperado). Captura erros de infra separados
 * de regras de negócio (AlreadySubscribed/NotSubscribed/QuotaExceeded).
 */
final class AsaasClientException extends RuntimeException
{
}
