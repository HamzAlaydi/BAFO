<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\BillingInterval;
use App\Modules\Billing\Enums\PaymentPurpose;
use Illuminate\Validation\Rule;

/**
 * `POST /billing/coupons/validate` (API.md §1.7): the code, the purpose and its context
 * (plan, interval and seats for a subscription; the competition for sponsorship), so that
 * `discount_minor` can be computed.
 */
final class ValidateCouponRequest extends BillingFormRequest
{
    public function rules(): array
    {
        return [
            'code' => ['required', 'string', 'max:40'],
            'purpose' => ['required', Rule::enum(PaymentPurpose::class)],
            'plan_id' => ['required_if:purpose,subscription', 'nullable', 'string', 'max:40'],
            'interval' => ['required_if:purpose,subscription', 'nullable', Rule::enum(BillingInterval::class)],
            'seats' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'competition_id' => ['required_if:purpose,sponsorship', 'nullable', 'string', 'max:40'],
        ];
    }

    public function code(): string
    {
        return (string) $this->validated('code');
    }

    public function purpose(): PaymentPurpose
    {
        return PaymentPurpose::from((string) $this->validated('purpose'));
    }

    public function planId(): ?string
    {
        return $this->stringOrNull('plan_id');
    }

    public function billingInterval(): BillingInterval
    {
        return BillingInterval::tryFrom((string) $this->validated('interval')) ?? BillingInterval::Monthly;
    }

    public function seats(): ?int
    {
        $seats = $this->validated('seats');

        return is_numeric($seats) ? (int) $seats : null;
    }

    public function competitionId(): ?string
    {
        return $this->stringOrNull('competition_id');
    }
}
