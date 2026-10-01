<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Data\SubscriptionCheckoutInput;
use App\Modules\Billing\Enums\BillingInterval;
use App\Support\Http\Middleware\IdempotentRequest;
use Illuminate\Validation\Rule;

/**
 * `POST /billing/checkout/subscription` (API.md §1.7). The plan must be active (422
 * `plan_not_available`), custom seats must be within the bounds (422 `seats_out_of_range`)
 * and the return URL allow-listed (422 `return_url_not_allowed`): those are business checks in
 * the Action.
 */
final class StoreSubscriptionCheckoutRequest extends BillingFormRequest
{
    public function rules(): array
    {
        return [
            'plan_id' => ['required', 'string', 'max:40'],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
            'seats' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
            'return_url' => ['required', 'string', 'url', 'max:1000'],
        ];
    }

    public function checkoutInput(): SubscriptionCheckoutInput
    {
        $seats = $this->validated('seats');
        $key = trim((string) $this->header(IdempotentRequest::HEADER, ''));

        return new SubscriptionCheckoutInput(
            planId: (string) $this->validated('plan_id'),
            interval: BillingInterval::from((string) $this->validated('interval')),
            seats: is_numeric($seats) ? (int) $seats : null,
            couponCode: $this->stringOrNull('coupon_code'),
            returnUrl: (string) $this->validated('return_url'),
            idempotencyKey: $key === '' ? null : mb_substr($key, 0, 64),
        );
    }
}
