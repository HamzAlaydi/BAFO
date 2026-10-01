<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Bidding\Contracts\BiddingEngine;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\CompetitionLiveState;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Data\Viewer;
use App\Modules\Competitions\Enums\AttachmentKind;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Competitions\Http\Resources\AttachmentResource;
use App\Modules\Competitions\Http\Resources\Shapes;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Http\Iso;
use Illuminate\Support\Facades\Date;

/**
 * Builds the Competition shapes of API.md §2.6 for a viewer: the issuer projection, the
 * participant projection and the invitee teaser, plus the public API variant (§3.4). Live values
 * come from Bidding's `BiddingEngine`; the visibility rules are the engine's (§7.9).
 */
final readonly class CompetitionPresenter
{
    public function __construct(
        private BiddingEngine $bidding,
        private AccessPolicy $accessPolicy,
    ) {}

    /**
     * Relations and counts the full projections read.
     */
    public static function load(Competition $competition): Competition
    {
        $competition->loadMissing([
            'category', 'region', 'organization.logoFile', 'cancelReason', 'notAwardedReason',
            'createdBy', 'createdByApiClient',
        ]);

        if (! array_key_exists('invitations_count', $competition->getAttributes())) {
            $competition->loadCount([
                'invitations' => static fn ($q) => $q->where('status', '!=', InvitationStatus::Revoked->value),
                'invitations as joined_count' => static fn ($q) => $q->where('status', InvitationStatus::Joined->value),
                'invitations as declined_count' => static fn ($q) => $q->where('status', InvitationStatus::Declined->value),
                'comments',
                'attachments',
            ]);
        }

        return $competition;
    }

    /**
     * @return array<string, mixed>
     */
    public function present(Competition $competition, Viewer $viewer, ?User $user): array
    {
        self::load($competition);

        return match (true) {
            $viewer->isIssuer() => $this->issuer($competition, $viewer, $user),
            $viewer->isParticipant() => $this->participant($competition, $viewer, $user),
            default => $this->teaser($competition, $viewer, $user),
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function issuer(Competition $competition, Viewer $viewer, ?User $user): array
    {
        $locale = app()->getLocale();

        return [
            ...$this->head($competition),
            'description' => $competition->description,
            'category' => Shapes::category($competition->category),
            'category_other_text' => $competition->category_other_text,
            'region' => Shapes::region($competition->region),
            'preset_code' => $competition->preset_code,
            'rules' => RulesMapper::toRules($competition, withReserve: true),
            'rules_summary' => RulesSummary::lines($competition, $locale, issuer: true),
            'schedule' => $this->schedule($competition),
            'issuer' => Shapes::organization($competition->organization),
            'counts' => $this->counts($competition),
            'leading_amount_minor' => $this->bidding->issuerLeadingAmount($competition),
            'sponsorship' => $this->sponsorship($competition),
            'bafo_round' => $this->issuerBafoRound($competition),
            'award' => $this->awardSummary($competition),
            'cancellation' => $this->cancellation($competition),
            'not_awarded' => $this->notAwarded($competition),
            'created_by' => $this->createdBy($competition),
            'source' => $competition->source->value,
            'permissions' => CompetitionPermissions::for($competition, $viewer, $user),
            'viewer_role' => $viewer->role->value,
            'live' => $this->hasLive($competition) ? $this->bidding->snapshotFor($competition, $viewer) : null,
            'server_time' => Iso::format(Date::now()),
            'created_at' => Iso::format($competition->created_at),
            'updated_at' => Iso::format($competition->updated_at),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function participant(Competition $competition, Viewer $viewer, ?User $user): array
    {
        $participant = $viewer->participant;
        $locale = app()->getLocale();
        $live = $this->hasLive($competition) ? $this->bidding->snapshotFor($competition, $viewer) : null;
        $access = $participant !== null ? $this->access($participant->invitation()->firstOrFail(), $viewer->organizationId) : null;

        return [
            ...$this->head($competition),
            'description' => $competition->description,
            'category' => Shapes::category($competition->category),
            'category_other_text' => $competition->category_other_text,
            'region' => Shapes::region($competition->region),
            'preset_code' => $competition->preset_code,
            'rules' => RulesMapper::toRules($competition, withReserve: false),
            'rules_summary' => RulesSummary::lines($competition, $locale),
            'schedule' => $this->schedule($competition),
            'issuer' => Shapes::organization($competition->organization),
            'bafo_round' => $this->participantBafoRound($competition, $viewer),
            'cancellation' => $this->cancellation($competition),
            'not_awarded' => $this->notAwarded($competition),
            'participation' => $participant !== null ? [
                'participant_id' => $participant->public_id,
                'alias_no' => $participant->alias_no,
                'joined_at' => Iso::format($participant->created_at),
                'terms_accepted_at' => Iso::format($participant->terms_accepted_at),
            ] : null,
            'access' => $access,
            'result' => $this->result($competition, $viewer),
            'permissions' => CompetitionPermissions::for($competition, $viewer, $user, $live),
            'viewer_role' => $viewer->role->value,
            'live' => $live,
            'server_time' => Iso::format(Date::now()),
            'created_at' => Iso::format($competition->created_at),
            'updated_at' => Iso::format($competition->updated_at),
        ];
    }

    /**
     * The invitee projection = CompetitionTeaser (API.md §2.6). The guest lookup leaves out
     * `invitation_documents`, `access` and `permissions` (`$guest`).
     *
     * @return array<string, mixed>
     */
    public function teaser(Competition $competition, Viewer $viewer, ?User $user, bool $guest = false): array
    {
        $competition->loadMissing(['category', 'region', 'organization.logoFile']);
        $invitation = $viewer->invitation;
        $access = ! $guest && $invitation !== null ? $this->access($invitation, $viewer->organizationId) : null;

        $teaser = [
            ...$this->head($competition),
            'category' => Shapes::category($competition->category),
            'region' => Shapes::region($competition->region),
            'issuer' => Shapes::organization($competition->organization),
            'rules' => RulesMapper::toRules($competition, withReserve: false),
            'rules_summary' => RulesSummary::lines($competition, app()->getLocale()),
            'schedule' => [
                'bidding_opens_at' => Iso::format($competition->bidding_opens_at),
                'scheduled_close_at' => Iso::format($competition->scheduled_close_at),
                'effective_close_at' => Iso::format($competition->effective_close_at),
                'invitation_cutoff_at' => Iso::format($competition->invitation_cutoff_at),
            ],
            'invitation' => $invitation !== null ? [
                'id' => $invitation->public_id,
                'status' => $invitation->status->value,
                'join_deadline' => Iso::format($competition->invitation_cutoff_at),
                'sent_at' => Iso::format($invitation->sent_at),
            ] : null,
        ];

        if ($guest) {
            return [...$teaser, 'viewer_role' => $viewer->role->value, 'server_time' => Iso::format(Date::now())];
        }

        return [
            ...$teaser,
            'access' => $access,
            'invitation_documents' => AttachmentResource::collection(
                CompetitionAttachment::query()->with('file')
                    ->where('competition_id', $competition->id)
                    ->where('kind', AttachmentKind::InvitationDocument->value)
                    ->orderBy('sort_order')->orderBy('id')->get(),
            )->resolve(),
            'permissions' => CompetitionPermissions::for($competition, $viewer, $user, access: $access),
            'viewer_role' => $viewer->role->value,
            'server_time' => Iso::format(Date::now()),
        ];
    }

    /**
     * PublicCompetition (API.md §3.4): the issuer projection without `permissions`, `viewer_role`
     * and `live`, with `{ar, en}` lookup names, `external_refs` and `created_by`.
     *
     * @return array<string, mixed>
     */
    public function publicCompetition(Competition $competition, Viewer $viewer): array
    {
        $competition->loadMissing('externalRefs');
        $data = $this->issuer($competition, $viewer, null);

        unset($data['permissions'], $data['viewer_role'], $data['live']);

        $data['category'] = Shapes::category($competition->category, public: true);
        $data['region'] = Shapes::region($competition->region, public: true);
        $data['rules_summary'] = RulesSummary::lines($competition, app()->getLocale(), issuer: true);
        $data['cancellation'] = $this->cancellation($competition, public: true);
        $data['not_awarded'] = $this->notAwarded($competition, public: true);
        $data['external_refs'] = $competition->externalRefs->map(static fn ($ref): array => [
            'system' => $ref->system,
            'type' => $ref->type,
            'id' => $ref->value,
            'number' => $ref->number,
            'url' => $ref->url,
        ])->values()->all();

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    private function head(Competition $competition): array
    {
        return [
            'id' => $competition->public_id,
            'reference_no' => $competition->reference_no,
            'title' => $competition->title,
            'direction' => $competition->direction->value,
            'format' => $competition->format->value,
            'status' => $competition->status->value,
            'phase' => $competition->phaseAt(Date::now())?->value,
            'currency' => $competition->currency,
            'price_basis' => 'excl_vat',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function schedule(Competition $competition): array
    {
        return [
            'bidding_opens_at' => Iso::format($competition->bidding_opens_at),
            'scheduled_close_at' => Iso::format($competition->scheduled_close_at),
            'effective_close_at' => Iso::format($competition->effective_close_at),
            'hard_stop_at' => Iso::format($competition->hard_stop_at),
            'final_window_starts_at' => Iso::format($competition->final_window_starts_at),
            'invitation_cutoff_at' => Iso::format($competition->invitation_cutoff_at),
            'extension_count' => $competition->extension_count,
            'published_at' => Iso::format($competition->published_at),
            'opened_at' => Iso::format($competition->opened_at),
            'closed_at' => Iso::format($competition->closed_at),
            'offers_opened_at' => Iso::format($competition->offers_opened_at),
            'awarded_at' => Iso::format($competition->awarded_at),
            'not_awarded_at' => Iso::format($competition->not_awarded_at),
            'cancelled_at' => Iso::format($competition->cancelled_at),
        ];
    }

    /**
     * @return array<string, int>
     */
    private function counts(Competition $competition): array
    {
        $offers = CompetitionLiveState::query()->where('competition_id', $competition->id)->value('accepted_offer_count');

        return [
            'invitations' => (int) $competition->getAttribute('invitations_count'),
            'joined' => (int) $competition->getAttribute('joined_count'),
            'declined' => (int) $competition->getAttribute('declined_count'),
            'participants_with_offers' => $this->bidding->participantsWithOffersCount($competition),
            'offers' => is_numeric($offers) ? (int) $offers : 0,
            'comments' => (int) $competition->getAttribute('comments_count'),
            'attachments' => (int) $competition->getAttribute('attachments_count'),
        ];
    }

    /**
     * @return array{mode: string, status: string, funded_passes: int, free_slots: int}|null
     */
    private function sponsorship(Competition $competition): ?array
    {
        $sponsorship = CompetitionSponsorship::query()->where('competition_id', $competition->id)->first();

        // A row exists only when the mode is not `none` (§5.7).
        if ($sponsorship === null) {
            return null;
        }

        $used = SponsoredPass::query()
            ->where('sponsorship_id', $sponsorship->id)
            ->whereIn('status', [PassStatus::Reserved->value, PassStatus::Joined->value])
            ->count();

        return [
            'mode' => $sponsorship->mode->value,
            'status' => $sponsorship->status->value,
            'funded_passes' => $sponsorship->funded_passes,
            'free_slots' => max(0, $sponsorship->funded_passes - $used),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function issuerBafoRound(Competition $competition): ?array
    {
        $round = BafoRound::query()->where('competition_id', $competition->id)->first();

        if ($round === null) {
            return null;
        }

        return [
            'id' => $round->public_id,
            'status' => $round->status->value,
            'starts_at' => Iso::format($round->starts_at),
            'cutoff_at' => Iso::format($round->cutoff_at),
            'ended_at' => Iso::format($round->ended_at),
            'shortlist_count' => $round->shortlist_count,
            'submitted_count' => ParticipantStanding::query()
                ->where('competition_id', $competition->id)
                ->whereNotNull('bafo_offer_id')
                ->count(),
        ];
    }

    /**
     * @return array{status: string, cutoff_at: string|null, shortlisted: bool, submitted: bool}|null
     */
    private function participantBafoRound(Competition $competition, Viewer $viewer): ?array
    {
        $round = BafoRound::query()->where('competition_id', $competition->id)->first();

        if ($round === null || $viewer->participant === null) {
            return null;
        }

        $standing = ParticipantStanding::query()->find($viewer->participant->id);

        return [
            'status' => $round->status->value,
            'cutoff_at' => Iso::format($round->cutoff_at),
            'shortlisted' => $standing !== null && $standing->bafo_shortlisted,
            'submitted' => $standing !== null && $standing->bafo_offer_id !== null,
        ];
    }

    /**
     * AwardSummary of the issued award.
     *
     * @return array<string, mixed>|null
     */
    private function awardSummary(Competition $competition): ?array
    {
        $award = $this->issuedAward($competition);

        if ($award === null) {
            return null;
        }

        return [
            'id' => $award->public_id,
            'status' => $award->status->value,
            'participant' => [
                'id' => $award->participant->public_id,
                'alias_no' => $award->participant->alias_no,
                'organization' => Shapes::organization($award->participant->organization),
            ],
            'amount_minor' => $award->amount_minor,
            'awarded_at' => Iso::format($award->awarded_at),
        ];
    }

    private function issuedAward(Competition $competition): ?Award
    {
        return Award::query()
            ->with('participant.organization.logoFile')
            ->where('competition_id', $competition->id)
            ->where('status', 'issued')
            ->first();
    }

    /**
     * The participant's `result` (ARCHITECTURE §7.9): `won` for the holder of the issued award;
     * otherwise `not_selected` / `not_awarded` unless `result_publication` is none. The winning
     * amount only with `outcome_and_amount`.
     *
     * CONTRACT-GAP: the winner sees `won` even with `result_publication = none` (it is told by the
     * `award.won` notification anyway).
     *
     * @return array{outcome: string|null, winning_amount_minor: int|null}
     */
    private function result(Competition $competition, Viewer $viewer): array
    {
        $none = ['outcome' => null, 'winning_amount_minor' => null];
        $publication = $competition->result_publication;

        if ($competition->status === CompetitionStatus::NotAwarded) {
            return $publication === ResultPublication::None ? $none : ['outcome' => 'not_awarded', 'winning_amount_minor' => null];
        }

        if ($competition->status !== CompetitionStatus::Awarded) {
            return $none;
        }

        $award = $this->issuedAward($competition);

        if ($award === null) {
            return $none;
        }

        $amount = $publication === ResultPublication::OutcomeAndAmount ? $award->amount_minor : null;

        if ($award->participant_id === $viewer->participant?->id) {
            return ['outcome' => 'won', 'winning_amount_minor' => $amount];
        }

        return $publication === ResultPublication::None ? $none : ['outcome' => 'not_selected', 'winning_amount_minor' => $amount];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function cancellation(Competition $competition, bool $public = false): ?array
    {
        if ($competition->status !== CompetitionStatus::Cancelled) {
            return null;
        }

        return [
            'reason' => Shapes::closeReason($competition->cancelReason, $public),
            'note' => $competition->cancel_note,
            'cancelled_at' => Iso::format($competition->cancelled_at),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function notAwarded(Competition $competition, bool $public = false): ?array
    {
        if ($competition->status !== CompetitionStatus::NotAwarded) {
            return null;
        }

        return [
            'reason' => Shapes::closeReason($competition->notAwardedReason, $public),
            'note' => $competition->not_awarded_note,
            'not_awarded_at' => Iso::format($competition->not_awarded_at),
        ];
    }

    /**
     * @return array{type: string, id: string, name: string}|null
     */
    private function createdBy(Competition $competition): ?array
    {
        if ($competition->createdByApiClient !== null) {
            return ['type' => 'api_client', 'id' => $competition->createdByApiClient->public_id, 'name' => $competition->createdByApiClient->name];
        }

        if ($competition->createdBy !== null) {
            return ['type' => 'user', 'id' => $competition->createdBy->public_id, 'name' => $competition->createdBy->name];
        }

        return null;
    }

    /**
     * @return array{state: string, coverage: string, sponsor_name: string|null, join_deadline: string|null}
     */
    public function access(Invitation $invitation, int $organizationId): array
    {
        $organization = Organization::query()->findOrFail($organizationId);

        return $this->accessPolicy->participationAccess($organization, $invitation)->toArray();
    }

    /**
     * The live block exists from the opening onward (null in draft and scheduled).
     */
    private function hasLive(Competition $competition): bool
    {
        return ! in_array($competition->status, [CompetitionStatus::Draft, CompetitionStatus::Scheduled], true);
    }
}
