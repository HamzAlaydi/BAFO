<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Requests;

use App\Modules\Billing\Enums\BillingInterval;
use Illuminate\Validation\Rule;

/**
 * `GET /plans/custom-quote?seats=&interval=` (API.md §1.7). Seats outside the custom bounds are
 * the business error `seats_out_of_range`, not a validation error.
 */
final class CustomQuoteRequest extends BillingFormRequest
{
    public function rules(): array
    {
        return [
            'seats' => ['required', 'integer', 'min:1', 'max:100000'],
            'interval' => ['required', Rule::enum(BillingInterval::class)],
        ];
    }

    public function seats(): int
    {
        return (int) $this->validated('seats');
    }

    public function billingInterval(): BillingInterval
    {
        return BillingInterval::from((string) $this->validated('interval'));
    }
}
