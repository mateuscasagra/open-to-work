<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Contracts;

/**
 * Gateway de pagamento (Asaas). Abstrai chamadas HTTP pra permitir mock em testes
 * e troca de provedor no futuro sem mexer nas Actions.
 *
 * Todos os métodos lançam AsaasClientException em falha de rede / 5xx /
 * timeout — atraso de Sentry em vez de propagar para o usuário sem contexto.
 */
interface AsaasGateway
{
    /**
     * Cria um customer e retorna o ID interno do Asaas.
     *
     * @param  string  $cpfCnpj  Apenas dígitos (11 CPF ou 14 CNPJ). Obrigatório
     *                           pra Asaas gerar cobranças PIX.
     */
    public function createCustomer(string $name, string $email, string $cpfCnpj): string;

    /**
     * Atualiza CPF/CNPJ de um customer já existente. Útil quando um customer
     * foi criado em fluxo anterior sem CPF (legado pré-validação).
     */
    public function updateCustomerCpfCnpj(string $customerId, string $cpfCnpj): void;

    /**
     * Cria uma subscription recorrente em centavos. Retorna o payload:
     *   - id: string (asaas_subscription_id)
     *   - next_due_date: 'YYYY-MM-DD'
     *   - first_payment_id: string|null (pode ser null se Asaas só criar na due_date)
     *
     * @return array{id: string, next_due_date: string, first_payment_id: ?string}
     */
    public function createSubscription(
        string $customerId,
        int $valueCents,
        string $nextDueDate, // YYYY-MM-DD
    ): array;

    /**
     * Localiza o primeiro payment da subscription. Útil quando createSubscription
     * não devolveu o paymentId no payload de criação.
     */
    public function getFirstSubscriptionPaymentId(string $subscriptionId): ?string;

    /**
     * Busca o QR Code PIX de um payment.
     *
     * @return array{encoded_image: string, payload: string, expiration_date: ?string}
     */
    public function getPaymentPixQrCode(string $paymentId): array;

    /**
     * Cancela uma subscription no Asaas. 404 não deve lançar (idempotente).
     */
    public function cancelSubscription(string $subscriptionId): void;
}
