<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Resources;

use App\Modules\Billing\Models\Plan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The plan embedded in a subscription (API.md §2.10): `{"id", "code", "name"}`, the name in
 * the request locale. Other modules may embed it (e.g. Identity's `Me.subscription`).
 *
 * @mixin Plan
 */
final class PlanSummaryResource extends JsonResource
{
    /**
     * @return array{id: string, code: string, name: string|null}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->public_id,
            'code' => $this->code,
            'name' => $this->translated('name'),
        ];
    }
}
