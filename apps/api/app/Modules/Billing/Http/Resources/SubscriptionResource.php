<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Subscription;
use App\Support\Http\Iso;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API.md §2.10 `Subscription`. `interval` and `amounts` are null for a trial or grant;
 * `days_left` counts whole days (rounded up) until `ends_at`, `total_days` the period length.
 *
 * @mixin Subscription
 */
final class SubscriptionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'plan' => PlanSummaryResource::make($this->plan)->resolve($request),
            'source' => $this->source->value,
            'interval' => $this->interval?->value,
            'seats' => $this->seats,
            'status' => $this->status->value,
            'starts_at' => Iso::format($this->starts_at),
            'ends_at' => Iso::format($this->ends_at),
            'days_left' => self::daysLeft($this->resource),
            'total_days' => self::totalDays($this->resource),
            'amounts' => $this->subtotal_minor === null ? null : [
                'subtotal_minor' => $this->subtotal_minor,
                'credit_minor' => (int) $this->credit_minor,
                'discount_minor' => (int) $this->discount_minor,
                'vat_minor' => (int) $this->vat_minor,
                'total_minor' => (int) $this->total_minor,
            ],
            'created_at' => Iso::format($this->created_at),
        ];
    }

    public static function daysLeft(Subscription $subscription): ?int
    {
        if ($subscription->ends_at === null) {
            return null;
        }

        $seconds = CarbonImmutable::now()->diffInSeconds($subscription->ends_at, false);

        return max(0, (int) ceil($seconds / 86_400));
    }

    public static function totalDays(Subscription $subscription): ?int
    {
        if ($subscription->starts_at === null || $subscription->ends_at === null) {
            return null;
        }

        return (int) round($subscription->starts_at->diffInSeconds($subscription->ends_at, true) / 86_400);
    }
}
