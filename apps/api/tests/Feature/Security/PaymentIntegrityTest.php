<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Services\Gateways\PaymentGatewayManager;
use App\Support\Exceptions\ApiException;
use Tests\Support\Billing\Billing;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-10: payment integrity. The fake gateway is a
 * development tool: a production deployment that forgot PAYMENT_GATEWAY refuses to take
 * payments rather than opening checkouts nobody can pay. The gateway webhook never settles a
 * payment from its body, and a payment is only ever settled for its exact amount.
 */

beforeEach(fn () => Billing::plans());

it('refuses the fake gateway in production', function () {
    $this->app['env'] = 'production';

    try {
        expect(fn () => app(PaymentGatewayManager::class)->driver('fake'))
            ->toThrow(ApiException::class, 'gateway_not_configured');
    } finally {
        $this->app['env'] = 'testing';
    }

    expect(app(PaymentGatewayManager::class)->driver('fake'))->not->toBeNull();
});

it('does not settle a payment from a forged gateway webhook', function () {
    $owner = Billing::actingAs(Billing::member());
    $checkout = $this->postJson('/api/app/v1/billing/checkout/subscription', [
        'plan_id' => Plan::query()->where('code', 'pro')->value('public_id'),
        'interval' => 'monthly',
        'return_url' => Billing::RETURN_URL,
    ])->assertCreated();
    $payment = Payment::query()->where('public_id', $checkout->json('data.id'))->firstOrFail();

    foreach (['fake', 'moyasar'] as $gateway) {
        $this->postJson('/api/app/v1/billing/gateway-webhooks/'.$gateway, [
            'type' => 'payment_paid',
            'secret_token' => 'guess',
            'data' => ['id' => $payment->gateway_reference, 'status' => 'paid', 'amount' => 1],
        ])->assertStatus(400)->assertJsonPath('code', 'invalid_webhook');
    }

    // Verifying asks the gateway, which has recorded no payment.
    $this->postJson('/api/app/v1/billing/payments/'.$payment->public_id.'/verify')->assertOk()->assertJsonPath('data.status', 'pending');

    expect($payment->refresh()->status)->toBe(PaymentStatus::Pending)
        ->and($owner->id)->toBe($payment->created_by_user_id);
});

it('ignores the amount and status a client sends with the checkout', function () {
    Billing::actingAs(Billing::member());

    $this->postJson('/api/app/v1/billing/checkout/subscription', [
        'plan_id' => Plan::query()->where('code', 'pro')->value('public_id'),
        'interval' => 'monthly',
        'return_url' => Billing::RETURN_URL,
        'total_minor' => 1,
        'subtotal_minor' => 1,
        'discount_minor' => 172_499,
        'status' => 'succeeded',
        'gateway' => 'manual',
        'organization_id' => 1,
    ])->assertCreated()
        ->assertJsonPath('data.status', 'pending')
        ->assertJsonPath('data.total_minor', 172_500)
        ->assertJsonPath('data.discount_minor', 0);
});
