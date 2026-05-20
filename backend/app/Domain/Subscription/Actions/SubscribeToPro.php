<?php

declare(strict_types=1);

namespace App\Domain\Subscription\Actions;

use App\Domain\Subscription\Contracts\AsaasGateway;
use App\Domain\Subscription\DTOs\CreateAsaasSubscriptionResult;
use App\Domain\Subscription\Exceptions\AlreadySubscribedException;
use App\Domain\Subscription\Exceptions\AsaasClientException;
use App\Models\Plan;
use App\Models\Subscription;
use App\Models\User;
use Carbon\CarbonImmutable;

/**
 * Cria customer + subscription PIX no Asaas e devolve o primeiro PIX QR
 * para o frontend renderizar. **NÃO** ativa Pro imediatamente — só salva
 * o asaas_subscription_id. A ativação fica a cargo do webhook PAYMENT_CONFIRMED,
 * evitando "Pro grátis" se o usuário não pagar.
 *
 * Asaas exige cpfCnpj no customer pra gerar cobranças PIX — por isso o
 * parâmetro $cpfCnpj é obrigatório. Frontend coleta via modal antes de
 * chamar essa action.
 */
final class SubscribeToPro
{
    public function __construct(
        private readonly AsaasGateway $asaas,
    ) {}

    public function execute(User $user, string $cpfCnpj): CreateAsaasSubscriptionResult
    {
        $user->loadMissing('subscription');
        /** @var Subscription $sub */
        $sub = $user->subscription;

        // Já tem Pro vigente — não duplica.
        if ($sub->isPro()) {
            throw new AlreadySubscribedException;
        }

        // Reusa customer ID se já existe (re-assinatura pós-cancelamento, ou
        // tentativa anterior que falhou após criar o customer). Se o CPF
        // informado agora difere do salvo, atualiza no Asaas — cobre o caso
        // de customer legado criado sem CPF antes dessa validação existir.
        if ($sub->asaas_customer_id !== null) {
            $customerId = $sub->asaas_customer_id;
            if ($sub->cpf !== $cpfCnpj) {
                $this->asaas->updateCustomerCpfCnpj($customerId, $cpfCnpj);
            }
        } else {
            $customerId = $this->asaas->createCustomer($user->name, $user->email, $cpfCnpj);
        }

        // Preço vem da tabela `plans` (cache 60s, apenas o int — não o model).
        // Permite alterar valor via UPDATE direto no DB sem rebuild/restart.
        // Fallback pra env apenas como safety net se a migration não rodou.
        $value = Plan::priceCentsBySlug('pro') ?? (int) config('services.asaas.pro_value_cents', 2500);

        $nextDueDate = CarbonImmutable::now('America/Sao_Paulo')->addDay()->format('Y-m-d');

        $createResult = $this->asaas->createSubscription($customerId, $value, $nextDueDate);

        $paymentId = $createResult['first_payment_id']
            ?? $this->asaas->getFirstSubscriptionPaymentId($createResult['id']);

        if ($paymentId === null) {
            throw new AsaasClientException('Subscription criada sem payment associado.');
        }

        $qr = $this->asaas->getPaymentPixQrCode($paymentId);

        // Persiste o estado: customer + subscription IDs + CPF. Plan=free ainda.
        // Pro só liga quando webhook PAYMENT_CONFIRMED chegar.
        $sub->update([
            'asaas_customer_id' => $customerId,
            'asaas_subscription_id' => $createResult['id'],
            'cpf' => $cpfCnpj,
        ]);

        return new CreateAsaasSubscriptionResult(
            asaasSubscriptionId: $createResult['id'],
            asaasCustomerId: $customerId,
            paymentId: $paymentId,
            pixQrCodeBase64: $qr['encoded_image'],
            pixCopyPaste: $qr['payload'],
            dueDate: $createResult['next_due_date'],
            expirationDate: $qr['expiration_date'],
        );
    }
}
