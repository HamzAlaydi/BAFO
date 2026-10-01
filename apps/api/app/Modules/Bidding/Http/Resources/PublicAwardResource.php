<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Http\Resources;

use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Http\Iso;
use App\Support\Money\Money;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * `PublicAward` (API.md §3.5). The VAT is informational: `Money::vat(amount_minor)`. The
 * winner's vendor is the invitation's vendor, else the issuer's vendor linked to the winner.
 * Load `relations()`.
 *
 * @mixin Award
 */
final class PublicAwardResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $competition = $this->competition;
        $winner = $this->participant->organization;
        $vendor = $this->participant->invitation->vendor ?? Vendor::query()
            ->where('organization_id', $competition->organization_id)
            ->where('linked_organization_id', $winner->id)
            ->with('externalRefs')
            ->first();
        $vat = Money::vat($this->amount_minor);
        $refs = static fn (iterable $items): array => collect($items)
            ->map(static fn (ExternalRef $ref): array => VisibilityProjector::externalRef($ref))
            ->values()
            ->all();

        return [
            'id' => $this->public_id,
            'object' => 'award',
            'status' => $this->status->value,
            'competition' => [
                'id' => $competition->public_id,
                'reference_no' => $competition->reference_no,
                'title' => $competition->title,
                'direction' => $competition->direction->value,
                'format' => $competition->format->value,
                'external_refs' => $refs($competition->externalRefs),
            ],
            'winner' => [
                'participant_id' => $this->participant->public_id,
                'organization' => [
                    'id' => $winner->public_id,
                    'name' => $winner->name,
                    'legal_name_ar' => $winner->legal_name_ar,
                    'legal_name_en' => $winner->legal_name_en,
                    'cr_number' => $winner->cr_number,
                    'vat_number' => $winner->vat_number,
                ],
                'vendor' => $vendor !== null ? [
                    'id' => $vendor->public_id,
                    'external_refs' => $refs($vendor->externalRefs),
                ] : null,
            ],
            'amount_minor' => $this->amount_minor,
            'currency' => $this->currency,
            'price_basis' => 'excl_vat',
            'vat_rate_bp' => Money::DEFAULT_VAT_RATE_BP,
            'vat_minor' => $vat,
            'amount_incl_vat_minor' => $this->amount_minor + $vat,
            'is_leading_offer' => $this->is_leading_offer,
            'rank_at_award' => $this->rank_at_award,
            'reserve_met' => $this->reserve_met,
            'justification' => $this->justificationReason !== null ? [
                'code' => $this->justificationReason->code,
                'name' => $this->justificationReason->name,
                'text' => $this->justification_text,
            ] : null,
            'offer' => [
                'id' => $this->offer->public_id,
                'seq' => $this->offer->seq,
                'accepted_at' => Iso::format($this->offer->accepted_at),
            ],
            'awarded_at' => Iso::format($this->awarded_at),
            'awarded_by' => [
                'name' => $this->awardedBy->name,
                'email' => $this->awardedBy->email,
            ],
            'revoked_at' => Iso::format($this->revoked_at),
            'revoke_reason' => $this->revoke_reason,
            'erp_sync' => [
                'status' => $this->erp_sync_status->value,
                'message' => $this->erp_sync_message,
                'synced_at' => Iso::format($this->erp_synced_at),
                'refs' => $refs($this->externalRefs),
            ],
            'ledger_head_hash' => $this->ledger_head_hash,
            'updated_at' => Iso::format($this->updated_at),
        ];
    }

    /**
     * @return list<string>
     */
    public static function relations(): array
    {
        return [
            'competition.externalRefs',
            'participant.organization',
            'participant.invitation.vendor.externalRefs',
            'offer',
            'awardedBy',
            'justificationReason',
            'externalRefs',
        ];
    }
}
