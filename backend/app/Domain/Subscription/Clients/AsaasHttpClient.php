<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Clients;

use App\Domain\Subscription\Contracts\AsaasGateway;
use App\Domain\Subscription\Exceptions\AsaasClientException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

final class AsaasHttpClient implements AsaasGateway
{
    public function createCustomer(string $name, string $email, string $cpfCnpj): string
    {
        try {
            $response = $this->request()->post('/customers', [
                'name' => $name,
                'email' => $email,
                'cpfCnpj' => $cpfCnpj,
                // Desliga e-mail/SMS de cobrança do próprio Asaas. Esses avisos
                // (confirmação, vencimento, cancelamento) são responsabilidade
                // da nossa integração com Resend, não do gateway.
                'notificationDisabled' => true,
            ]);
            $response->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new AsaasClientException('Falha ao criar customer no Asaas.', 0, $e);
        } catch (Throwable $e) {
            throw new AsaasClientException('Falha ao criar customer no Asaas.', 0, $e);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $id = $data['id'] ?? null;

        if (! is_string($id) || $id === '') {
            throw new AsaasClientException('Asaas retornou customer sem id.');
        }

        return $id;
    }

    public function updateCustomerCpfCnpj(string $customerId, string $cpfCnpj): void
    {
        try {
            // Asaas usa POST /customers/{id} pra atualização parcial (não PATCH/PUT).
            $response = $this->request()->post('/customers/' . $customerId, [
                'cpfCnpj' => $cpfCnpj,
            ]);
            $response->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new AsaasClientException('Falha ao atualizar CPF do customer no Asaas.', 0, $e);
        } catch (Throwable $e) {
            throw new AsaasClientException('Falha ao atualizar CPF do customer no Asaas.', 0, $e);
        }
    }

    public function createSubscription(string $customerId, int $valueCents, string $nextDueDate): array
    {
        try {
            $response = $this->request()->post('/subscriptions', [
                'customer' => $customerId,
                'billingType' => 'PIX',
                'value' => $valueCents / 100.0,
                'cycle' => 'MONTHLY',
                'nextDueDate' => $nextDueDate,
                'description' => 'Open to Work Pro',
            ]);
            $response->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new AsaasClientException('Falha ao criar subscription no Asaas.', 0, $e);
        } catch (Throwable $e) {
            throw new AsaasClientException('Falha ao criar subscription no Asaas.', 0, $e);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $id = $data['id'] ?? null;
        $next = $data['nextDueDate'] ?? $nextDueDate;

        if (! is_string($id) || $id === '') {
            throw new AsaasClientException('Asaas retornou subscription sem id.');
        }

        // Asaas geralmente cria o primeiro payment automaticamente. Algumas
        // contas/configurações retornam null aqui — daí o getFirstSubscriptionPaymentId().
        $firstPaymentId = null;
        if (isset($data['firstPaymentId']) && is_string($data['firstPaymentId'])) {
            $firstPaymentId = $data['firstPaymentId'];
        }

        return [
            'id' => $id,
            'next_due_date' => is_string($next) ? $next : $nextDueDate,
            'first_payment_id' => $firstPaymentId,
        ];
    }

    public function getFirstSubscriptionPaymentId(string $subscriptionId): ?string
    {
        try {
            $response = $this->request()->get('/subscriptions/' . $subscriptionId . '/payments', [
                'limit' => 1,
                'offset' => 0,
            ]);
            $response->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new AsaasClientException('Falha ao listar payments da subscription.', 0, $e);
        } catch (Throwable $e) {
            throw new AsaasClientException('Falha ao listar payments da subscription.', 0, $e);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];
        $list = $data['data'] ?? [];

        if (! is_array($list) || count($list) === 0) {
            return null;
        }

        $first = $list[0];
        if (! is_array($first)) {
            return null;
        }

        $id = $first['id'] ?? null;

        return is_string($id) ? $id : null;
    }

    public function getPaymentPixQrCode(string $paymentId): array
    {
        try {
            $response = $this->request()->get('/payments/' . $paymentId . '/pixQrCode');
            $response->throw();
        } catch (ConnectionException|RequestException $e) {
            throw new AsaasClientException('Falha ao obter QR PIX do Asaas.', 0, $e);
        } catch (Throwable $e) {
            throw new AsaasClientException('Falha ao obter QR PIX do Asaas.', 0, $e);
        }

        /** @var array<string, mixed> $data */
        $data = $response->json() ?? [];

        $encoded = $data['encodedImage'] ?? null;
        $payload = $data['payload'] ?? null;
        $expiration = $data['expirationDate'] ?? null;

        if (! is_string($encoded) || $encoded === '' || ! is_string($payload) || $payload === '') {
            throw new AsaasClientException('Asaas retornou QR PIX vazio.');
        }

        return [
            'encoded_image' => $encoded,
            'payload' => $payload,
            'expiration_date' => is_string($expiration) ? $expiration : null,
        ];
    }

    public function cancelSubscription(string $subscriptionId): void
    {
        try {
            $response = $this->request()->delete('/subscriptions/' . $subscriptionId);
        } catch (ConnectionException $e) {
            throw new AsaasClientException('Falha ao cancelar subscription no Asaas.', 0, $e);
        } catch (Throwable $e) {
            throw new AsaasClientException('Falha ao cancelar subscription no Asaas.', 0, $e);
        }

        // 404 = idempotente (já cancelada ou removida no painel Asaas).
        if ($response->status() === 404) {
            return;
        }

        try {
            $response->throw();
        } catch (RequestException $e) {
            throw new AsaasClientException('Asaas retornou erro ao cancelar.', 0, $e);
        }
    }

    private function request(): PendingRequest
    {
        $baseUrl = rtrim((string) config('services.asaas.base_url'), '/');
        $apiKey = (string) config('services.asaas.api_key');

        return Http::baseUrl($baseUrl)
            ->acceptJson()
            ->asJson()
            ->withHeaders(['access_token' => $apiKey])
            ->withUserAgent('open-to-work/1.0 (https://opentowork.app.br)')
            ->timeout(15);
    }
}
