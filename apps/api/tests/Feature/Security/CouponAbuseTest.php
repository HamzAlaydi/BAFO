<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\CouponScope;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Plan;
use Carbon\CarbonImmutable;
use Illuminate\Testing\TestResponse;
use Tests\Support\Billing\Billing;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-04: a coupon limit cannot be multiplied by
 * opening several checkouts before paying any of them. An organization's pending (unexpired)
 * checkouts that carry the coupon count against the per-organization limit and against
 * `max_redemptions`, as they already do for vouchers (ARCHITECTURE §13.4 check 5).
 */

beforeEach(fn () => Billing::plans());

/**
 * @param  array<string, mixed>  $attributes
 */
function securityCoupon(array $attributes = []): Coupon
{
    return Coupon::factory()->create([
        'code' => 'WELCOME50',
        'discount_type' => DiscountType::Percent,
        'percent_bps' => 5000,
        'amount_minor' => null,
        'applies_to' => CouponScope::Any,
        'organization_id' => null,
        'max_redemptions' => null,
        'redemptions_count' => 0,
        'per_organization_limit' => 1,
        'valid_from' => now()->subDay(),
        'valid_until' => now()->addMonth(),
        'is_active' => true,
        ...$attributes,
    ]);
}

function securityCheckout(object $test, string $coupon = 'welcome50'): TestResponse
{
    return $test->postJson('/api/app/v1/billing/checkout/subscription', [
        'plan_id' => Plan::query()->where('code', 'pro')->value('public_id'),
        'interval' => 'monthly',
        'return_url' => Billing::RETURN_URL,
        'coupon_code' => $coupon,
    ]);
}

function securityApprove(object $test, TestResponse $checkout): void
{
    $test->post('/pay/fake/'.$checkout->json('data.id').'/approve')->assertStatus(303);
}

it('does not let one organization redeem a once-per-organization coupon twice through parallel checkouts', function () {
    // The last checkout renews the plan bought here, and a renewal opens `billing.renewal_window_days`
    // (30) before the period ends: on a day whose next month is 31 days away it would stop at
    // `subscription_renewal_too_early` before the coupon check. Freeze a day whose monthly period
    // (28 days) is inside the window, so the purchase is valid and only the coupon can refuse it.
    $this->travelTo(CarbonImmutable::parse('2027-02-10 09:00:00', 'UTC'));

    $coupon = securityCoupon(['per_organization_limit' => 1]);
    Billing::actingAs(Billing::member());

    $first = securityCheckout($this)->assertCreated()->assertJsonPath('data.coupon.code', 'WELCOME50');

    // A second checkout while the first one is still open: the coupon is held.
    securityCheckout($this)->assertUnprocessable()->assertJsonPath('code', 'coupon_exhausted');
    $this->postJson('/api/app/v1/billing/coupons/validate', [
        'code' => 'welcome50', 'purpose' => 'subscription',
        'plan_id' => Plan::query()->where('code', 'pro')->value('public_id'), 'interval' => 'monthly',
    ])->assertUnprocessable()->assertJsonPath('code', 'coupon_exhausted');

    securityApprove($this, $first);

    expect(CouponRedemption::query()->where('coupon_id', $coupon->id)->count())->toBe(1);
    securityCheckout($this)->assertUnprocessable()->assertJsonPath('code', 'coupon_exhausted');
});

it('does not let one organization go past max_redemptions through parallel checkouts', function () {
    securityCoupon(['per_organization_limit' => null, 'max_redemptions' => 1]);
    Billing::actingAs(Billing::member());

    securityCheckout($this)->assertCreated();
    securityCheckout($this)->assertUnprocessable()->assertJsonPath('code', 'coupon_exhausted');
});

it('releases the hold when the open checkout fails or its hold expires', function () {
    securityCoupon(['per_organization_limit' => 1]);
    Billing::actingAs(Billing::member());

    $first = securityCheckout($this)->assertCreated();
    $this->post('/pay/fake/'.$first->json('data.id').'/decline')->assertStatus(303);

    // The declined checkout no longer holds the coupon.
    $second = securityCheckout($this)->assertCreated();

    // An abandoned checkout holds it only until its hold expires (30 minutes).
    $this->travel(31)->minutes();
    securityCheckout($this)->assertCreated();

    expect($second->json('data.status'))->toBe('pending');
});

it('lets other organizations use a coupon another organization holds in an open checkout', function () {
    securityCoupon(['per_organization_limit' => 1, 'max_redemptions' => 10]);

    Billing::actingAs(Billing::member());
    securityCheckout($this)->assertCreated();

    Billing::actingAs(Billing::member());
    securityCheckout($this)->assertCreated();
});

it('answers coupon_invalid for another organization\'s voucher whatever its validity', function (Closure $state) {
    $voucher = $state(Coupon::factory()->voucher())->create();
    Billing::actingAs(Billing::member());

    $this->postJson('/api/app/v1/billing/coupons/validate', [
        'code' => $voucher->code, 'purpose' => 'subscription',
        'plan_id' => Plan::query()->where('code', 'pro')->value('public_id'), 'interval' => 'monthly',
    ])->assertUnprocessable()->assertJsonPath('code', 'coupon_invalid');
})->with([
    'valid' => [fn ($factory) => $factory],
    'expired' => [fn ($factory) => $factory->expired()],
    'not yet valid' => [fn ($factory) => $factory->state(['valid_from' => now()->addDay()])],
]);
