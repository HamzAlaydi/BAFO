<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Enums\PaymentPurpose;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\PaymentLine;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Competitions\Models\Competition;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API.md §2.10 `Payment`. Line descriptions are in the request locale; `coupon` is
 * `{"code"}` when one was applied; `context` links the payment to what it buys.
 *
 * @mixin Payment
 */
final class PaymentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing(['lines', 'coupon', 'invoice', 'subscription']);
        $competitionId = $this->metadata['competition_id'] ?? null;
        $competition = $this->purpose === PaymentPurpose::Sponsorship && is_numeric($competitionId)
            ? Competition::query()->find((int) $competitionId)
            : null;
        $subscription = $this->subscription;

        return [
            'id' => $this->public_id,
            'purpose' => $this->purpose->value,
            'status' => $this->status->value,
            'currency' => $this->currency,
            'lines' => $this->lines->map(static fn (PaymentLine $line): array => [
                'kind' => $line->kind->value,
                'description' => $line->translated('description'),
                'quantity' => $line->quantity,
                'unit_price_minor' => $line->unit_price_minor,
                'net_minor' => $line->net_minor,
            ])->values()->all(),
            'subtotal_minor' => $this->subtotal_minor,
            'credit_minor' => $this->credit_minor,
            'discount_minor' => $this->discount_minor,
            'vat_rate_bp' => $this->vat_rate_bp,
            'vat_minor' => $this->vat_minor,
            'total_minor' => $this->total_minor,
            'coupon' => $this->coupon === null ? null : ['code' => $this->coupon->code],
            'redirect_url' => $this->redirect_url,
            'failure_code' => $this->failure_code,
            'failure_message' => $this->failure_message,
            'paid_at' => Iso::format($this->paid_at),
            'expires_at' => Iso::format($this->expires_at),
            'created_at' => Iso::format($this->created_at),
            'invoice_id' => $this->invoice?->public_id,
            'context' => [
                'competition_id' => $competition?->public_id,
                'intent' => $this->purpose === PaymentPurpose::Sponsorship ? ($this->metadata['intent'] ?? null) : null,
                'subscription_id' => $subscription instanceof Subscription ? $subscription->public_id : null,
            ],
        ];
    }
}
