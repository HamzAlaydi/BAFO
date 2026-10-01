<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Services\Webhooks;

use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Models\WebhookEndpoint;
use App\Modules\Integrations\Services\ExternalRefs;
use App\Modules\Integrations\Services\VendorDirectory;
use App\Support\Http\Iso;

/**
 * The thin `data.object` of each webhook type (API.md §4.1). Amounts appear only where the issuer
 * may see them now, through the Bidding VisibilityProjector (§7.9, the single gate): a sealed
 * competition's offers carry `amount_minor: null` until the offers are opened.
 */
final readonly class WebhookObjects
{
    public function __construct(
        private VendorDirectory $vendors,
        private VisibilityProjector $projector,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function competitionPublished(Competition $competition): array
    {
        return [
            ...self::competitionHead($competition),
            'reference_no' => $competition->reference_no,
            'status' => $competition->status->value,
            'direction' => $competition->direction->value,
            'format' => $competition->format->value,
            'bidding_opens_at' => Iso::format($competition->bidding_opens_at),
            'scheduled_close_at' => Iso::format($competition->scheduled_close_at),
            'external_refs' => ExternalRefs::present($competition->externalRefs()->get()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function competitionExtended(Competition $competition, CompetitionExtension $extension): array
    {
        return [
            ...self::competitionHead($competition),
            'previous_close_at' => Iso::format($extension->previous_close_at),
            'effective_close_at' => Iso::format($competition->effective_close_at ?? $extension->new_close_at),
            'extension_count' => $competition->extension_count,
            'kind' => $extension->kind->value,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function competitionClosed(Competition $competition): array
    {
        $live = CompetitionLiveState::query()->find($competition->id);

        return [
            ...self::competitionHead($competition),
            'status' => 'closed',
            'closed_at' => Iso::format($competition->closed_at),
            'participants_with_offers' => $live->participants_with_offers ?? 0,
            'offers_count' => $live->accepted_offer_count ?? 0,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function competitionOffersOpened(Competition $competition): array
    {
        return [
            ...self::competitionHead($competition),
            'offers_opened_at' => Iso::format($competition->offers_opened_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function competitionCancelled(Competition $competition): array
    {
        return [
            ...self::competitionHead($competition),
            'cancelled_at' => Iso::format($competition->cancelled_at),
            'reason' => self::reason($competition->cancel_reason_id),
            'note' => $competition->cancel_note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function competitionNotAwarded(Competition $competition): array
    {
        return [
            ...self::competitionHead($competition),
            'not_awarded_at' => Iso::format($competition->not_awarded_at),
            'reason' => self::reason($competition->not_awarded_reason_id),
            'note' => $competition->not_awarded_note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invitationAccepted(Invitation $invitation, Participant $participant, Competition $competition): array
    {
        $organization = Organization::query()->find($participant->organization_id);

        return [
            ...self::invitationHead($invitation, $competition),
            'organization' => $organization === null ? null : ['id' => $organization->public_id, 'name' => $organization->name],
            'vendor' => $this->vendorOf($competition, $invitation->vendor_id, $participant->organization_id),
            'sponsored' => $participant->entitlement_source === EntitlementSource::SponsoredPass,
            'joined_at' => Iso::format($invitation->joined_at ?? $participant->created_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function invitationDeclined(Invitation $invitation, Competition $competition): array
    {
        return [
            ...self::invitationHead($invitation, $competition),
            'vendor' => $this->vendorOf($competition, $invitation->vendor_id, $invitation->organization_id),
            'declined_at' => Iso::format($invitation->declined_at),
        ];
    }

    /**
     * `offer.submitted` and `offer.updated`.
     *
     * @return array<string, mixed>
     */
    public function offer(Offer $offer, Competition $competition): array
    {
        $participant = Participant::query()->with('invitation')->find($offer->participant_id);
        $organization = Organization::query()->find($offer->organization_id);

        return [
            'id' => $offer->public_id,
            'object' => 'offer',
            'competition_id' => $competition->public_id,
            'participant_id' => $participant?->public_id,
            'organization' => $organization === null ? null : ['id' => $organization->public_id, 'name' => $organization->name],
            'vendor' => $this->vendorOf($competition, $participant?->invitation?->vendor_id, $offer->organization_id),
            'seq' => $offer->seq,
            'stage' => $offer->stage->value,
            // Sealed-stage webhooks fire before offers_opened_at: the issuer may not see the amount yet.
            'amount_minor' => $this->projector->issuerOfferAmount($offer, $competition),
            'currency' => $competition->currency,
            'accepted_at' => Iso::format($offer->accepted_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function awardIssued(Award $award, Competition $competition): array
    {
        $winner = Organization::query()->find($award->organization_id);
        $participant = Participant::query()->with('invitation')->find($award->participant_id);

        return [
            'id' => $award->public_id,
            'object' => 'award',
            'competition_id' => $competition->public_id,
            'status' => 'issued',
            'winner' => [
                'organization' => $winner === null ? null : [
                    'id' => $winner->public_id,
                    'name' => $winner->name,
                    'cr_number' => $winner->cr_number,
                    'vat_number' => $winner->vat_number,
                ],
                'vendor' => $this->vendorOf($competition, $participant?->invitation?->vendor_id, $award->organization_id),
            ],
            'amount_minor' => $award->amount_minor,
            'currency' => $award->currency,
            'awarded_at' => Iso::format($award->awarded_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function awardCancelled(Award $award, Competition $competition): array
    {
        return [
            'id' => $award->public_id,
            'object' => 'award',
            'competition_id' => $competition->public_id,
            'status' => 'revoked',
            'revoked_at' => Iso::format($award->revoked_at),
            'reason' => $award->revoke_reason,
        ];
    }

    /**
     * @return array{id: string, object: string, message: string}
     */
    public function test(WebhookEndpoint $endpoint): array
    {
        return ['id' => $endpoint->public_id, 'object' => 'webhook_endpoint', 'message' => 'BAFO test event'];
    }

    /**
     * @return array{id: string, object: string}
     */
    private static function competitionHead(Competition $competition): array
    {
        return ['id' => $competition->public_id, 'object' => 'competition'];
    }

    /**
     * @return array{id: string, object: string, competition_id: string, email: string}
     */
    private static function invitationHead(Invitation $invitation, Competition $competition): array
    {
        return [
            'id' => $invitation->public_id,
            'object' => 'invitation',
            'competition_id' => $competition->public_id,
            'email' => $invitation->email,
        ];
    }

    /**
     * @return array{code: string, name: array<string, string>}|null
     */
    private static function reason(?int $reasonId): ?array
    {
        $reason = $reasonId === null ? null : CloseReason::query()->find($reasonId);

        return $reason === null ? null : ['code' => $reason->code, 'name' => $reason->name];
    }

    /**
     * @return array{id: string, external_refs: list<array<string, string|null>>}|null
     */
    private function vendorOf(Competition $competition, ?int $vendorId, ?int $organizationId): ?array
    {
        return VendorDirectory::summary($this->vendors->forCounterparty($competition->organization_id, $vendorId, $organizationId));
    }
}
