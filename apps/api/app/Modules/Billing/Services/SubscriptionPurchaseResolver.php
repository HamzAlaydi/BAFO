<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Data\SubscriptionPurchase;
use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\PurchaseKind;
use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Support\Exceptions\ApiException;
use App\Support\Http\Iso;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

/**
 * Prices a subscription purchase and decides its kind (ARCHITECTURE §13.2):
 *
 * | Situation                                                       | Result   |
 * |-----------------------------------------------------------------|----------|
 * | no current subscription, or a trial or grant                    | new      |
 * | current paid; same plan, same seats                             | renewal (within the window) |
 * | current paid; higher monthly equivalent, or more seats          | upgrade (pro-rata credit)   |
 * | otherwise                                                       | 409 subscription_downgrade_not_allowed |
 */
final readonly class SubscriptionPurchaseResolver
{
    public function __construct(
        private BillingSettings $settings,
        private SubscriptionLookup $subscriptions,
        private PricingCalculator $pricing,
    ) {}

    /**
     * @throws ApiException plan_not_available, seats_out_of_range, subscription_renewal_too_early,
     *                      subscription_downgrade_not_allowed
     * @throws ValidationException seats missing for the custom plan
     */
    public function resolve(int $organizationId, Plan $plan, BillingInterval $interval, ?int $seats, ?CarbonImmutable $at = null): SubscriptionPurchase
    {
        $at ??= CarbonImmutable::now();
        [$unit, $quantity, $seats] = $this->planLine($plan, $interval, $seats);
        $subtotal = $unit * $quantity;

        $current = $this->subscriptions->current($organizationId, $at);

        if ($current === null || $current->source !== SubscriptionSource::Paid) {
            return new SubscriptionPurchase(PurchaseKind::New, $plan, $interval, $seats, $unit, $quantity, 0, $current);
        }

        $window = $this->settings->renewalWindowDays();
        $upcoming = $this->subscriptions->upcoming($organizationId, $at);

        // CONTRACT-GAP: §13.2 does not cover a purchase while a renewal is already queued. One
        // queued renewal at a time: buy again once the queued period is current.
        if ($upcoming !== null && $upcoming->ends_at !== null) {
            throw $this->renewalTooEarly($upcoming->ends_at->subDays($window));
        }

        if ($plan->id === $current->plan_id && $seats === $current->seats) {
            $renewableFrom = $current->ends_at?->subDays($window);

            if ($renewableFrom !== null && $at->lessThan($renewableFrom)) {
                throw $this->renewalTooEarly($renewableFrom);
            }

            return new SubscriptionPurchase(PurchaseKind::Renewal, $plan, $interval, $seats, $unit, $quantity, 0, $current);
        }

        if ($this->isHigher($subtotal, $interval, $seats, $current)) {
            $credit = min($this->pricing->upgradeCredit($current, $at), $subtotal);

            return new SubscriptionPurchase(PurchaseKind::Upgrade, $plan, $interval, $seats, $unit, $quantity, $credit, $current);
        }

        throw new ApiException('subscription_downgrade_not_allowed', 'billing.errors.subscription_downgrade_not_allowed', 409);
    }

    /**
     * The plan line: `[unit, quantity, seats]`. Fixed plans are one unit with the plan's seats;
     * the custom plan is priced per seat from the settings.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    public function planLine(Plan $plan, BillingInterval $interval, ?int $seats): array
    {
        if (! $plan->is_active) {
            throw new ApiException('plan_not_available', 'billing.errors.plan_not_available', 422);
        }

        if ($plan->is_custom) {
            if ($seats === null) {
                throw ValidationException::withMessages([
                    'seats' => [$this->message('billing.validation.seats_required')],
                ]);
            }

            $min = $this->settings->customMinSeats();
            $max = $this->settings->customMaxSeats();

            if ($seats < $min || $seats > $max) {
                throw new ApiException(
                    'seats_out_of_range',
                    'billing.errors.seats_out_of_range',
                    422,
                    replace: ['min' => $min, 'max' => $max],
                    details: ['min_seats' => $min, 'max_seats' => $max],
                );
            }

            return [$this->settings->customSeatPrice($interval), $seats, $seats];
        }

        $unit = $interval === BillingInterval::Annual ? $plan->annual_price_minor : $plan->monthly_price_minor;

        if ($unit === null || $plan->seats === null) {
            throw new ApiException('plan_not_available', 'billing.errors.plan_not_available', 422);
        }

        return [$unit, 1, $plan->seats];
    }

    /**
     * A higher monthly equivalent (`subtotal / months`, compared without division) or more seats.
     *
     * CONTRACT-GAP: §13.2 compares `unit / months`; for the custom plan the unit is a seat price,
     * so the comparison uses the plan line subtotal (unit × quantity), which equals the unit for
     * fixed plans.
     */
    private function isHigher(int $subtotal, BillingInterval $interval, int $seats, Subscription $current): bool
    {
        if ($seats > $current->seats) {
            return true;
        }

        $currentMonths = ($current->interval ?? BillingInterval::Monthly)->months();
        $currentSubtotal = (int) ($current->subtotal_minor ?? 0);

        return $subtotal * $currentMonths > $currentSubtotal * $interval->months();
    }

    private function renewalTooEarly(CarbonImmutable $renewableFrom): ApiException
    {
        return new ApiException(
            'subscription_renewal_too_early',
            'billing.errors.subscription_renewal_too_early',
            409,
            details: ['renewable_from' => Iso::format($renewableFrom)],
        );
    }

    private function message(string $key): string
    {
        $message = __($key);

        return is_string($message) ? $message : $key;
    }
}
