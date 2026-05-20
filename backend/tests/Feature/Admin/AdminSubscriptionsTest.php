<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Helper: cria subscription via update direto (não via factory, pra contornar
 * o User::booted() que já cria free). Sem cache na chamada subsequente do GET
 * porque o controller lê do DB a cada request.
 */
function setSub(User $user, array $attrs): void
{
    $user->subscription()->update($attrs);
}

beforeEach(function (): void {
    $this->admin = User::factory()->admin()->create();
});

it('rejects unauthenticated', function (): void {
    $this->getJson('/api/admin/subscriptions')->assertUnauthorized();
});

it('rejects non-admin', function (): void {
    $regular = User::factory()->create();
    $this->actingAs($regular)->getJson('/api/admin/subscriptions')->assertForbidden();
});

it('returns paginated list (all filter by default)', function (): void {
    $u1 = User::factory()->create(['name' => 'Diego']);
    setSub($u1, ['asaas_subscription_id' => 'sub_1', 'plan' => 'pro', 'status' => 'active']);

    $u2 = User::factory()->create(['name' => 'Maria']);
    setSub($u2, ['asaas_subscription_id' => 'sub_2', 'plan' => 'free', 'status' => 'canceled', 'canceled_at' => now()]);

    // Sub free sem asaas_subscription_id — não aparece na lista
    User::factory()->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/admin/subscriptions')
        ->assertOk()
        ->assertJsonStructure([
            'data' => ['*' => ['id', 'user_id', 'name', 'email', 'plan', 'status', 'asaas_subscription_id']],
            'current_page',
            'last_page',
            'total',
        ]);

    expect($response->json('total'))->toBe(2);
});

it('filters by status=active', function (): void {
    $u1 = User::factory()->create();
    setSub($u1, ['asaas_subscription_id' => 'sub_active', 'plan' => 'pro', 'status' => 'active']);

    $u2 = User::factory()->create();
    setSub($u2, ['asaas_subscription_id' => 'sub_canc', 'plan' => 'pro', 'status' => 'canceled']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/admin/subscriptions?status=active')
        ->assertOk();

    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.asaas_subscription_id'))->toBe('sub_active');
});

it('filters by status=canceled (includes past_due)', function (): void {
    $u1 = User::factory()->create();
    setSub($u1, ['asaas_subscription_id' => 'sub_canc', 'plan' => 'pro', 'status' => 'canceled']);

    $u2 = User::factory()->create();
    setSub($u2, ['asaas_subscription_id' => 'sub_past', 'plan' => 'pro', 'status' => 'past_due']);

    $u3 = User::factory()->create();
    setSub($u3, ['asaas_subscription_id' => 'sub_active', 'plan' => 'pro', 'status' => 'active']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/admin/subscriptions?status=canceled')
        ->assertOk();

    expect($response->json('total'))->toBe(2);
});

it('includes user who canceled before first payment (plan=free still)', function (): void {
    $u = User::factory()->create();
    setSub($u, [
        'asaas_subscription_id' => 'sub_x',
        'plan' => 'free',          // webhook nunca chegou
        'status' => 'canceled',
        'canceled_at' => now(),
    ]);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/admin/subscriptions?status=canceled')
        ->assertOk();

    expect($response->json('total'))->toBe(1);
    expect($response->json('data.0.plan'))->toBe('free');
    expect($response->json('data.0.status'))->toBe('canceled');
});

it('excludes subs with no asaas_subscription_id (free user never engaged)', function (): void {
    // Booted hook cria sub free pra todo user — verificar que essas não aparecem.
    User::factory()->count(5)->create();

    $response = $this->actingAs($this->admin)
        ->getJson('/api/admin/subscriptions')
        ->assertOk();

    expect($response->json('total'))->toBe(0);
});

it('falls back to all when status is invalid', function (): void {
    $u = User::factory()->create();
    setSub($u, ['asaas_subscription_id' => 'sub_x', 'status' => 'active']);

    $response = $this->actingAs($this->admin)
        ->getJson('/api/admin/subscriptions?status=garbage')
        ->assertOk();

    expect($response->json('total'))->toBe(1);
});
