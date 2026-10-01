<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Resources;

use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Catalog\Http\Resources\CloseReasonResource;
use App\Modules\Integrations\Models\ExternalRef;
use App\Support\Http\Iso;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `Award` (issuer view, API.md §2.9). Load `participant.organization`, `offer`, `awardedBy`,
 * `justificationReason` and `externalRefs`.
 *
 * @mixin Award
 */
final class AwardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $organization = $this->participant->organization;

        return [
            'id' => $this->public_id,
            'status' => $this->status->value,
            'participant' => [
                'id' => $this->participant->public_id,
                'alias_no' => $this->participant->alias_no,
                'organization' => [
                    'id' => $organization->public_id,
                    'name' => $organization->name,
                    'cr_number' => $organization->cr_number,
                    'vat_number' => $organization->vat_number,
                ],
            ],
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'price_basis' => 'excl_vat',
            'is_leading_offer' => $this->is_leading_offer,
            'rank_at_award' => $this->rank_at_award,
            'reserve_met' => $this->reserve_met,
            'justification' => $this->justificationReason !== null ? [
                'reason' => (new CloseReasonResource($this->justificationReason))->resolve($request),
                'text' => $this->justification_text,
            ] : null,
            'message_to_winner' => $this->message_to_winner,
            'internal_notes' => $this->internal_notes,
            'offer' => [
                'id' => $this->offer->public_id,
                'seq' => $this->offer->seq,
                'accepted_at' => Iso::format($this->offer->accepted_at),
            ],
            'awarded_by' => [
                'id' => $this->awardedBy->public_id,
                'name' => $this->awardedBy->name,
            ],
            'awarded_at' => Iso::format($this->awarded_at),
            'revoked_at' => Iso::format($this->revoked_at),
            'revoke_reason' => $this->revoke_reason,
            'erp_sync' => [
                'status' => $this->erp_sync_status->value,
                'message' => $this->erp_sync_message,
                'synced_at' => Iso::format($this->erp_synced_at),
                'refs' => $this->externalRefs
                    ->map(static fn (ExternalRef $ref): array => VisibilityProjector::externalRef($ref))
                    ->values()
                    ->all(),
            ],
            'ledger_head_hash' => $this->ledger_head_hash,
            'created_at' => Iso::format($this->created_at),
        ];
    }

    /**
     * The relations the resource reads.
     *
     * @return list<string>
     */
    public static function relations(): array
    {
        return ['participant.organization', 'offer', 'awardedBy', 'justificationReason', 'externalRefs'];
    }
}
