<?php

declare(strict_types=1);

namespace App\Modules\Billing\Services;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Competitions\Enums\Direction;
use App\Support\Settings\Settings;

/**
 * Typed access to the Billing runtime settings (ARCHITECTURE §15.3, defaults in config.php)
 * and the VAT rate (config, not a setting).
 */
final readonly class BillingSettings
{
    public function __construct(private Settings $settings) {}

    public function vatRateBp(): int
    {
        $rate = config('bafo.billing.vat_rate_bp', 1500);

        return is_numeric($rate) ? (int) $rate : 1500;
    }

    public function trialDays(): int
    {
        return $this->int('billing.trial_days', 30);
    }

    public function trialPlanCode(): string
    {
        $code = $this->settings->get('billing.trial_plan_code', 'plus');

        return is_string($code) && $code !== '' ? $code : 'plus';
    }

    public function customMinSeats(): int
    {
        return $this->int('billing.custom_min_seats', 4);
    }

    public function customMaxSeats(): int
    {
        return $this->int('billing.custom_max_seats', 50);
    }

    public function customSeatPrice(BillingInterval $interval): int
    {
        return $interval === BillingInterval::Annual
            ? $this->int('billing.custom_seat_annual_price_minor', 500_000)
            : $this->int('billing.custom_seat_monthly_price_minor', 50_000);
    }

    public function checkoutHoldMinutes(): int
    {
        return $this->int('billing.checkout_hold_minutes', 30);
    }

    public function renewalWindowDays(): int
    {
        return $this->int('billing.renewal_window_days', 30);
    }

    /**
     * The global switch; the organization flag `sponsorship_enabled` is also required.
     */
    public function sponsorshipEnabled(): bool
    {
        return (bool) $this->settings->get('sponsorship.enabled', true);
    }

    /**
     * The flat pass price per direction, excl. VAT (R4 MVP, D15).
     */
    public function passPrice(Direction $direction): int
    {
        return $this->int('sponsorship.pass_price_'.$direction->value.'_minor', 20_000);
    }

    private function int(string $key, int $default): int
    {
        $value = $this->settings->get($key, $default);

        return is_numeric($value) ? (int) $value : $default;
    }
}
