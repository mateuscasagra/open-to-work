<?php

declare(strict_types=1);

use App\Domain\Subscription\Clients\AsaasHttpClient;
use App\Domain\Subscription\Exceptions\AsaasClientException;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    config()->set('services.asaas.base_url', 'https://sandbox.asaas.com/api/v3');
    config()->set('services.asaas.api_key', 'test-key');
});

it('creates a customer with cpfCnpj and returns the id', function (): void {
    Http::fake([
        '*/customers' => Http::response(['id' => 'cus_123', 'name' => 'Diego'], 200),
    ]);

    $id = (new AsaasHttpClient)->createCustomer('Diego', 'diego@example.com', '24971563792');

    expect($id)->toBe('cus_123');

    Http::assertSent(function ($request): bool {
        return $request->url() === 'https://sandbox.asaas.com/api/v3/customers'
            && $request->header('access_token')[0] === 'test-key'
            && $request['name'] === 'Diego'
            && $request['email'] === 'diego@example.com'
            && $request['cpfCnpj'] === '24971563792';
    });
});

it('throws AsaasClientException when customer response has no id', function (): void {
    Http::fake([
        '*/customers' => Http::response(['name' => 'Diego'], 200),
    ]);

    (new AsaasHttpClient)->createCustomer('Diego', 'd@example.com', '24971563792');
})->throws(AsaasClientException::class);

it('throws AsaasClientException on customer 5xx', function (): void {
    Http::fake([
        '*/customers' => Http::response(['message' => 'Server Error'], 500),
    ]);

    (new AsaasHttpClient)->createCustomer('Diego', 'd@example.com', '24971563792');
})->throws(AsaasClientException::class);

it('updates customer cpfCnpj via POST /customers/{id}', function (): void {
    Http::fake([
        '*/customers/cus_999' => Http::response(['id' => 'cus_999', 'cpfCnpj' => '24971563792'], 200),
    ]);

    (new AsaasHttpClient)->updateCustomerCpfCnpj('cus_999', '24971563792');

    Http::assertSent(function ($request): bool {
        return $request->method() === 'POST'
            && str_ends_with($request->url(), '/customers/cus_999')
            && $request['cpfCnpj'] === '24971563792';
    });
});

it('throws AsaasClientException when updating cpfCnpj returns 5xx', function (): void {
    Http::fake([
        '*/customers/cus_999' => Http::response(['error' => 'boom'], 500),
    ]);

    (new AsaasHttpClient)->updateCustomerCpfCnpj('cus_999', '24971563792');
})->throws(AsaasClientException::class);

it('creates a subscription with PIX billing and monthly cycle', function (): void {
    Http::fake([
        '*/subscriptions' => Http::response([
            'id' => 'sub_999',
            'nextDueDate' => '2026-05-20',
            'firstPaymentId' => 'pay_abc',
        ], 200),
    ]);

    $result = (new AsaasHttpClient)->createSubscription('cus_123', 2500, '2026-05-20');

    expect($result['id'])->toBe('sub_999')
        ->and($result['next_due_date'])->toBe('2026-05-20')
        ->and($result['first_payment_id'])->toBe('pay_abc');

    Http::assertSent(function ($request): bool {
        return str_ends_with($request->url(), '/subscriptions')
            && $request['customer'] === 'cus_123'
            && $request['billingType'] === 'PIX'
            && $request['value'] === 25.0
            && $request['cycle'] === 'MONTHLY'
            && $request['nextDueDate'] === '2026-05-20';
    });
});

it('falls back to null first_payment_id when Asaas omits it', function (): void {
    Http::fake([
        '*/subscriptions' => Http::response([
            'id' => 'sub_999',
            'nextDueDate' => '2026-05-20',
        ], 200),
    ]);

    $result = (new AsaasHttpClient)->createSubscription('cus_123', 2500, '2026-05-20');

    expect($result['first_payment_id'])->toBeNull();
});

it('fetches PIX QR code data', function (): void {
    Http::fake([
        '*/payments/pay_abc/pixQrCode' => Http::response([
            'encodedImage' => 'iVBORw0KGgo=',
            'payload' => '00020126...',
            'expirationDate' => '2026-05-23 23:59:59',
        ], 200),
    ]);

    $qr = (new AsaasHttpClient)->getPaymentPixQrCode('pay_abc');

    expect($qr['encoded_image'])->toBe('iVBORw0KGgo=')
        ->and($qr['payload'])->toBe('00020126...')
        ->and($qr['expiration_date'])->toBe('2026-05-23 23:59:59');
});

it('throws when QR response is empty', function (): void {
    Http::fake([
        '*/payments/pay_abc/pixQrCode' => Http::response(['encodedImage' => '', 'payload' => ''], 200),
    ]);

    (new AsaasHttpClient)->getPaymentPixQrCode('pay_abc');
})->throws(AsaasClientException::class);

it('cancels subscription on 200', function (): void {
    Http::fake([
        '*/subscriptions/sub_999' => Http::response(['deleted' => true], 200),
    ]);

    (new AsaasHttpClient)->cancelSubscription('sub_999');

    Http::assertSent(fn ($request) => $request->method() === 'DELETE'
        && str_ends_with($request->url(), '/subscriptions/sub_999'));
});

it('treats 404 on cancel as idempotent success', function (): void {
    Http::fake([
        '*/subscriptions/sub_999' => Http::response(['errors' => [['code' => 'invalid_id']]], 404),
    ]);

    // Não deve lançar — já cancelada/removida no painel Asaas.
    (new AsaasHttpClient)->cancelSubscription('sub_999');

    expect(true)->toBeTrue();
});

it('throws AsaasClientException on cancel 5xx', function (): void {
    Http::fake([
        '*/subscriptions/sub_999' => Http::response(['error' => 'boom'], 500),
    ]);

    (new AsaasHttpClient)->cancelSubscription('sub_999');
})->throws(AsaasClientException::class);

it('gets first payment id from subscription listing', function (): void {
    Http::fake([
        '*/subscriptions/sub_999/payments*' => Http::response([
            'data' => [['id' => 'pay_first'], ['id' => 'pay_second']],
        ], 200),
    ]);

    $id = (new AsaasHttpClient)->getFirstSubscriptionPaymentId('sub_999');

    expect($id)->toBe('pay_first');
});

it('returns null when subscription has no payments yet', function (): void {
    Http::fake([
        '*/subscriptions/sub_999/payments*' => Http::response(['data' => []], 200),
    ]);

    expect((new AsaasHttpClient)->getFirstSubscriptionPaymentId('sub_999'))->toBeNull();
});
