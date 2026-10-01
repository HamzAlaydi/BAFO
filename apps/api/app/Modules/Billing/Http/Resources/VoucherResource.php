<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Coupon;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * API.md §2.10 `Voucher`: an organization-scoped voucher and its remaining balance.
 *
 * @mixin Coupon
 */
final class VoucherResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $this->resource->loadMissing('sourceCompetition');

        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'amount_minor' => (int) $this->amount_minor,
            'balance_minor' => (int) $this->balance_minor,
            'valid_until' => Iso::format($this->valid_until),
            'reason' => $this->reason,
            'source_competition_id' => $this->sourceCompetition?->public_id,
            'is_active' => $this->is_active,
        ];
    }
}
