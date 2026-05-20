<?php

declare(strict_types=1);

use App\Domain\Subscription\Contracts\AsaasGateway;
use App\Domain\Subscription\Exceptions\AsaasClientException;
use App\Models\User;
use Mockery\MockInterface;

// CPF de teste válido em formato (Asaas sandbox aceita).
const VALID_CPF = '24971563792';

it('subscribes a free user and returns PIX checkout payload', function (): void {
    $user = User::factory()->create(['name' => 'Diego', 'email' => 'diego@example.com']);

    $this->mock(AsaasGateway::class, function (MockInterface $m): void {
        $m->shouldReceive('createCustomer')
            ->once()
            ->with('Diego', 'diego@example.com', VALID_CPF)
            ->andReturn('cus_999');
        $m->shouldReceive('createSubscription')
            ->once()
            ->andReturn(['id' => 'sub_777', 'next_due_date' => '2026-05-21', 'first_payment_id' => 'pay_111']);
        $m->shouldReceive('getPaymentPixQrCode')
            ->once()
            ->with('pay_111')
            ->andReturn([
                'encoded_image' => 'iVBORw0KGgo=',
                'payload' => '00020126...',
                'expiration_date' => '2026-05-24 23:59:59',
            ]);
    });

    $response = $this->actingAs($user)
        ->postJson('/api/subscriptions', ['cpf' => VALID_CPF])
        ->assertCreated();

    $response->assertJsonPath('pix_qr_code_base64', 'iVBORw0KGgo=')
        ->assertJsonPath('pix_copy_paste', '00020126...')
        ->assertJsonPath('due_date', '2026-05-21')
        ->assertJsonPath('payment_id', 'pay_111')
        ->assertJsonPath('asaas_subscription_id', 'sub_777');

    // DB foi atualizado mas plano permanece free até webhook confirmar.
    $user->refresh()->load('subscription');
    expect($user->subscription->asaas_customer_id)->toBe('cus_999');
    expect($user->subscription->asaas_subscription_id)->toBe('sub_777');
    expect($user->subscription->cpf)->toBe(VALID_CPF);
    expect($user->subscription->plan)->toBe('free');
});

it('accepts masked CPF and normalizes to digits only', function (): void {
    $user = User::factory()->create();

    $this->mock(AsaasGateway::class, function (MockInterface $m): void {
        $m->shouldReceive('createCustomer')
            ->once()
            ->with(\Mockery::any(), \Mockery::any(), VALID_CPF)
            ->andReturn('cus_x');
        $m->shouldReceive('createSubscription')
            ->andReturn(['id' => 'sub_x', 'next_due_date' => '2026-05-21', 'first_payment_id' => 'pay_x']);
        $m->shouldReceive('getPaymentPixQrCode')
            ->andReturn(['encoded_image' => 'a', 'payload' => 'b', 'expiration_date' => null]);
    });

    $this->actingAs($user)
        ->postJson('/api/subscriptions', ['cpf' => '249.715.637-92'])
        ->assertCreated();
});

it('rejects invalid CPF format with 422', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/subscriptions', ['cpf' => 'abc'])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cpf']);
});

it('rejects request without CPF with 422', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/subscriptions', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['cpf']);
});

it('reuses asaas_customer_id and updates CPF if changed on re-subscription', function (): void {
    $user = User::factory()->create();
    $user->subscription()->update([
        'asaas_customer_id' => 'cus_existing',
        'cpf' => '11111111111',
        'status' => 'active',
        'plan' => 'free',
    ]);

    $this->mock(AsaasGateway::class, function (MockInterface $m): void {
        // Não deve chamar createCustomer — reusa o existente.
        $m->shouldNotReceive('createCustomer');
        // CPF mudou: deve atualizar no Asaas.
        $m->shouldReceive('updateCustomerCpfCnpj')
            ->once()
            ->with('cus_existing', VALID_CPF);
        $m->shouldReceive('createSubscription')
            ->once()
            ->with('cus_existing', \Mockery::any(), \Mockery::any())
            ->andReturn(['id' => 'sub_new', 'next_due_date' => '2026-05-21', 'first_payment_id' => 'pay_x']);
        $m->shouldReceive('getPaymentPixQrCode')
            ->andReturn(['encoded_image' => 'a', 'payload' => 'b', 'expiration_date' => null]);
    });

    $this->actingAs($user)
        ->postJson('/api/subscriptions', ['cpf' => VALID_CPF])
        ->assertCreated();
});

it('reuses asaas_customer_id without updating CPF when CPF unchanged', function (): void {
    $user = User::factory()->create();
    $user->subscription()->update([
        'asaas_customer_id' => 'cus_existing',
        'cpf' => VALID_CPF,
        'status' => 'active',
        'plan' => 'free',
    ]);

    $this->mock(AsaasGateway::class, function (MockInterface $m): void {
        $m->shouldNotReceive('createCustomer');
        $m->shouldNotReceive('updateCustomerCpfCnpj');
        $m->shouldReceive('createSubscription')
            ->andReturn(['id' => 'sub_new', 'next_due_date' => '2026-05-21', 'first_payment_id' => 'pay_x']);
        $m->shouldReceive('getPaymentPixQrCode')
            ->andReturn(['encoded_image' => 'a', 'payload' => 'b', 'expiration_date' => null]);
    });

    $this->actingAs($user)
        ->postJson('/api/subscriptions', ['cpf' => VALID_CPF])
        ->assertCreated();
});

it('rejects subscription with 409 when user is already Pro', function (): void {
    $user = $this->makeProUser();

    // Mock pra garantir que NÃO bate no Asaas.
    $this->mock(AsaasGateway::class, function (MockInterface $m): void {
        $m->shouldNotReceive('createCustomer');
        $m->shouldNotReceive('createSubscription');
    });

    $this->actingAs($user)
        ->postJson('/api/subscriptions', ['cpf' => VALID_CPF])
        ->assertStatus(409);
});

it('returns 502 when Asaas gateway fails', function (): void {
    $user = User::factory()->create();

    $this->mock(AsaasGateway::class, function (MockInterface $m): void {
        $m->shouldReceive('createCustomer')->andThrow(new AsaasClientException('boom'));
    });

    $this->actingAs($user)
        ->postJson('/api/subscriptions', ['cpf' => VALID_CPF])
        ->assertStatus(502);
});

it('requires authentication', function (): void {
    $this->postJson('/api/subscriptions', ['cpf' => VALID_CPF])->assertUnauthorized();
});
