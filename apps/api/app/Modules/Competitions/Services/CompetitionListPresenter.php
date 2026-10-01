<?php

declare(strict_types=1);

namespace App\Modules\Competitions\Services;

use App\Modules\Bidding\Contracts\BiddingEngine;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Billing\Contracts\AccessPolicy;
use App\Modules\Competitions\Enums\CompetitionStatus as S;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\Phase;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Competitions\Http\Resources\Shapes;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Support\Http\Iso;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * CompetitionListItem (API.md §2.6), issuer and participant variants, for `GET /competitions`.
 */
final readonly class CompetitionListPresenter
{
    public function __construct(
        private BiddingEngine $bidding,
        private AccessPolicy $access,
    ) {}

    /**
     * @param  Collection<int, Competition>  $competitions  with `category`, `region` and the counts
     * @return list<array<string, mixed>>
     */
    public function issuerItems(Collection $competitions): array
    {
        return $competitions->map(fn (Competition $competition): array => [
            ...$this->head($competition),
            'schedule' => [
                'bidding_opens_at' => Iso::format($competition->bidding_opens_at),
                'effective_close_at' => Iso::format($competition->effective_close_at),
            ],
            'counts' => [
                'invitations' => (int) $competition->getAttribute('invitations_count'),
                'joined' => (int) $competition->getAttribute('joined_count'),
                'participants_with_offers' => $this->bidding->participantsWithOffersCount($competition),
            ],
            'leading_amount_minor' => $this->bidding->issuerLeadingAmount($competition),
            'created_at' => Iso::format($competition->created_at),
            'updated_at' => Iso::format($competition->updated_at),
        ])->values()->all();
    }

    /**
     * @param  Collection<int, Competition>  $competitions  with `category`, `region`, `organization`
     * @return list<array<string, mixed>>
     */
    public function participantItems(Collection $competitions, Organization $organization): array
    {
        $ids = $competitions->pluck('id')->all();

        $invitations = Invitation::query()
            ->whereIn('competition_id', $ids)
            ->where('organization_id', $organization->id)
            ->whereIn('status', ['sent', 'viewed', 'joined', 'declined', 'expired'])
            ->orderByRaw("case status when 'joined' then 0 when 'sent' then 1 when 'viewed' then 1 else 2 end")
            ->orderByDesc('id')
            ->get()
            ->unique('competition_id')
            ->keyBy('competition_id');

        $participants = Participant::query()
            ->whereIn('competition_id', $ids)
            ->where('organization_id', $organization->id)
            ->get()
            ->keyBy('competition_id');

        $standings = ParticipantStanding::query()
            ->whereIn('participant_id', $participants->pluck('id')->all())
            ->get()
            ->keyBy('participant_id');

        $awards = Award::query()->whereIn('competition_id', $ids)->where('status', 'issued')->get()->keyBy('competition_id');

        return $competitions->map(function (Competition $competition) use ($invitations, $participants, $standings, $awards, $organization): array {
            /** @var Invitation|null $invitation */
            $invitation = $invitations->get($competition->id);
            /** @var Participant|null $participant */
            $participant = $participants->get($competition->id);
            /** @var ParticipantStanding|null $standing */
            $standing = $participant !== null ? $standings->get($participant->id) : null;
            /** @var Award|null $award */
            $award = $awards->get($competition->id);

            if ($invitation !== null) {
                $invitation->setRelation('competition', $competition);
            }

            return [
                ...$this->head($competition),
                'issuer' => Shapes::organization($competition->organization),
                'schedule' => [
                    'bidding_opens_at' => Iso::format($competition->bidding_opens_at),
                    'effective_close_at' => Iso::format($competition->effective_close_at),
                    'invitation_cutoff_at' => Iso::format($competition->invitation_cutoff_at),
                ],
                'invitation' => $invitation !== null ? [
                    'id' => $invitation->public_id,
                    'status' => $invitation->status->value,
                    'join_deadline' => Iso::format($competition->invitation_cutoff_at),
                ] : null,
                'access' => $invitation !== null ? $this->access->participationAccess($organization, $invitation)->toArray() : null,
                'my_offer_amount_minor' => $standing?->current_amount_minor,
                'is_leading' => $standing !== null && self::leadingVisible($competition) ? $standing->is_leader : null,
                'result' => ['outcome' => $this->outcome($competition, $participant, $award)],
                'updated_at' => Iso::format($competition->updated_at),
            ];
        })->values()->all();
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
            'category' => Shapes::category($competition->category),
            'region' => Shapes::region($competition->region),
        ];
    }

    /**
     * `is_leading` is shown under "live rules" (ARCHITECTURE §7.9: phase open or final window, or
     * closed and later with the live format) with rank visibility leading_flag or full. A running
     * BAFO round is sealed (§7.3), as in the VisibilityProjector: no flag until it ends
     * (SECURITY_REVIEW S-03).
     */
    private static function leadingVisible(Competition $competition): bool
    {
        if ($competition->rank_visibility === RankVisibility::None) {
            return false;
        }

        if ($competition->status === S::Live) {
            return in_array($competition->phaseAt(Date::now()), [Phase::Open, Phase::FinalWindow], true);
        }

        return $competition->format === Format::Live
            && in_array($competition->status, [S::Closed, S::Awarded, S::NotAwarded], true);
    }

    private function outcome(Competition $competition, ?Participant $participant, ?Award $award): ?string
    {
        $published = $competition->result_publication !== ResultPublication::None;

        if ($competition->status === S::NotAwarded) {
            return $published && $participant !== null ? 'not_awarded' : null;
        }

        if ($competition->status !== S::Awarded || $award === null || $participant === null) {
            return null;
        }

        if ($award->participant_id === $participant->id) {
            return 'won';
        }

        return $published ? 'not_selected' : null;
    }

    /**
     * Invitation statuses listed on the participant side (API.md §1.4 `role=participant`).
     *
     * @return list<string>
     */
    public static function participantStatuses(): array
    {
        return [
            InvitationStatus::Sent->value, InvitationStatus::Viewed->value, InvitationStatus::Joined->value,
            InvitationStatus::Declined->value, InvitationStatus::Expired->value,
        ];
    }
}
