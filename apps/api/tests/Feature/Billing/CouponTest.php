<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\CouponScope;
use App\Modules\Billing\Enums\DiscountType;
use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\Plan;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use Tests\Support\Billing\Billing;

beforeEach(fn () => Billing::plans());

/**
 * @param  array<string, mixed>  $attributes
 */
function billingCoupon(array $attributes = []): Coupon
{
    return Coupon::factory()->create([
        'code' => 'LAUNCH10',
        'discount_type' => DiscountType::Percent,
        'percent_bps' => 1000,
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

/**
 * @return array<string, mixed>
 */
function billingSubscriptionCouponBody(string $code = 'launch10', string $plan = 'pro'): array
{
    return [
        'code' => $code,
        'purpose' => 'subscription',
        'plan_id' => Plan::query()->where('code', $plan)->value('public_id'),
        'interval' => 'monthly',
    ];
}

describe('POST /billing/coupons/validate', function () {
    it('validates a percent coupon and computes the discount for the plan', function () {
        billingCoupon();
        Billing::actingAs(Billing::member());

        $this->postJson('/api/app/v1/billing/coupons/validate', billingSubscriptionCouponBody())
            ->assertOk()
            ->assertJsonStructure(['data' => ['code', 'kind', 'discount_type', 'percent_bps', 'amount_minor', 'balance_minor', 'applies_to', 'valid_until', 'discount_minor']])
            ->assertJsonPath('data.code', 'LAUNCH10')
            ->assertJsonPath('data.kind', 'coupon')
            ->assertJsonPath('data.percent_bps', 1000)
            ->assertJsonPath('data.discount_minor', 15_000);
    });

    it('computes a voucher discount capped by its balance', function () {
        $owner = Billing::actingAs(Billing::member());
        $voucher = Coupon::factory()->voucher($owner->membership->organization)->create(['amount_minor' => 40_000, 'balance_minor' => 25_000]);

        $this->postJson('/api/app/v1/billing/coupons/validate', billingSubscriptionCouponBody($voucher->code, 'single'))
            ->assertOk()
            ->assertJsonPath('data.kind', 'voucher')
            ->assertJsonPath('data.balance_minor', 25_000)
            ->assertJsonPath('data.discount_minor', 25_000);
    });

    it('computes the discount of a sponsorship checkout', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition);
        Invitation::factory()->count(3)->create(['competition_id' => $competition->id, 'status' => InvitationStatus::Draft]);
        billingCoupon(['percent_bps' => 2500, 'applies_to' => CouponScope::Sponsorship]);
        Billing::actingAs($owner);

        $this->postJson('/api/app/v1/billing/coupons/validate', [
            'code' => 'LAUNCH10', 'purpose' => 'sponsorship', 'competition_id' => $competition->public_id,
        ])->assertOk()->assertJsonPath('data.discount_minor', 15_000);
    });

    it('rejects the codes of §13.4 in order', function (Closure $setup, string $code) {
        $owner = Billing::actingAs(Billing::member());
        $setup($owner->membership->organization);

        $this->postJson('/api/app/v1/billing/coupons/validate', billingSubscriptionCouponBody())
            ->assertStatus(422)
            ->assertJsonPath('code', $code);
    })->with([
        'unknown' => [fn () => null, 'coupon_invalid'],
        'inactive' => [fn () => billingCoupon(['is_active' => false]), 'coupon_invalid'],
        'not yet valid' => [fn () => billingCoupon(['valid_from' => now()->addDay()]), 'coupon_expired'],
        'expired' => [fn () => billingCoupon(['valid_until' => now()->subMinute()]), 'coupon_expired'],
        'another organization' => [fn () => billingCoupon(['organization_id' => Organization::factory()->create()->id]), 'coupon_invalid'],
        'wrong purpose' => [fn () => billingCoupon(['applies_to' => CouponScope::Sponsorship]), 'coupon_not_applicable'],
        'max redemptions' => [fn () => billingCoupon(['max_redemptions' => 5, 'redemptions_count' => 5]), 'coupon_exhausted'],
        'per organization limit' => [function (Organization $organization): void {
            $coupon = billingCoupon();
            CouponRedemption::factory()->create(['coupon_id' => $coupon->id, 'organization_id' => $organization->id]);
        }, 'coupon_exhausted'],
        'voucher without balance' => [fn (Organization $organization) => billingCoupon([
            'code' => 'LAUNCH10', 'kind' => 'voucher', 'discount_type' => DiscountType::Fixed, 'percent_bps' => null,
            'amount_minor' => 1_000, 'balance_minor' => 0, 'organization_id' => $organization->id, 'per_organization_limit' => null,
        ]), 'coupon_exhausted'],
        'voucher in a pending payment' => [function (Organization $organization): void {
            $voucher = billingCoupon([
                'code' => 'LAUNCH10', 'kind' => 'voucher', 'discount_type' => DiscountType::Fixed, 'percent_bps' => null,
                'amount_minor' => 1_000, 'balance_minor' => 1_000, 'organization_id' => $organization->id, 'per_organization_limit' => null,
            ]);
            Payment::factory()->create(['organization_id' => $organization->id, 'coupon_id' => $voucher->id]);
        }, 'coupon_exhausted'],
    ]);

    it('validates the body', function () {
        Billing::actingAs(Billing::member());

        $this->postJson('/api/app/v1/billing/coupons/validate', ['purpose' => 'lunch'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['code', 'purpose']);
    });

    it('requires billing.purchase', function () {
        Billing::actingAs(Billing::member(role: OrgRole::Member));

        $this->postJson('/api/app/v1/billing/coupons/validate', ['code' => 'X', 'purpose' => PaymentPurpose::Subscription->value])
            ->assertForbidden();
    });

    it('requires authentication', function () {
        $this->postJson('/api/app/v1/billing/coupons/validate', [])->assertUnauthorized();
    });
});

describe('GET /billing/vouchers', function () {
    it('lists the organization vouchers, active first', function () {
        $owner = Billing::actingAs(Billing::member());
        $organization = $owner->membership->organization;
        $spent = Coupon::factory()->voucher($organization)->create(['balance_minor' => 0, 'created_at' => now()]);
        $active = Coupon::factory()->voucher($organization)->create(['created_at' => now()->subDay()]);
        Coupon::factory()->voucher(Organization::factory()->create())->create();

        $this->getJson('/api/app/v1/billing/vouchers')
            ->assertOk()
            ->assertJsonStructure(['data' => [['id', 'code', 'amount_minor', 'balance_minor', 'valid_until', 'reason', 'source_competition_id', 'is_active']]])
            ->assertJsonPath('data.*.id', [$active->public_id, $spent->public_id]);
    });

    it('requires billing.view', function () {
        Billing::actingAs(Billing::member(role: OrgRole::Member));

        $this->getJson('/api/app/v1/billing/vouchers')->assertForbidden();
    });
});
