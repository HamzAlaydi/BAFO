<?php

declare(strict_types=1);

namespace App\Modules\Bidding\Services;

use App\Modules\Bidding\Data\LastChange;
use App\Modules\Bidding\Data\LiveView;
use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Enums\OfferStage;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Enums\MustBeat;
use App\Modules\Competitions\Enums\Phase;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Models\ExternalRef;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Files\FileStorage;
use App\Support\Http\Iso;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Date;

/**
 * The single gate for everything about offers that leaves the server (ARCHITECTURE §7.9):
 * REST resources, realtime payloads, webhooks, the public API and the PDF. Every method
 * returns plain arrays in the exact API.md shapes.
 *
 * Participant projection. "Live rules" = stage `live` (phase open or final window), or the
 * competition is closed / awarded / not awarded and the format is `live`. Outside live rules a
 * participant sees only its own offer, the start price and its own bound. Never: the reserve
 * price, other participants' identities or aliases outside the ladder, issuer notes, online
 * counts, or others' offer logs.
 *
 * Issuer projection (members of the issuer organization and its API clients). Sealed
 * competitions before `offers_opened_at`: every amount, rank, leading flag, the leader and
 * `reserve_met` are null; `submitted` and `last_offer_at` are shown, and rows keep a neutral
 * order (alias) so that the order cannot leak the ranking.
 */
final readonly class VisibilityProjector
{
    public function __construct(
        private LiveStateManager $liveStates,
        private Heartbeats $heartbeats,
        private FileStorage $files,
    ) {}

    // ---------------------------------------------------------------- rules

    /**
     * Sealed and not yet unlocked: the issuer sees no amounts, ranks or leader.
     */
    public function amountsHiddenFromIssuer(Competition $competition): bool
    {
        return $competition->format === Format::Sealed && $competition->offers_opened_at === null;
    }

    /**
     * §7.9 "live rules".
     */
    public function liveRules(Competition $competition, CarbonImmutable $now): bool
    {
        if ($competition->format !== Format::Live) {
            return false;
        }

        if ($competition->status === CompetitionStatus::Live) {
            return in_array($competition->phaseAt($now), [Phase::Open, Phase::FinalWindow], true);
        }

        // CONTRACT-GAP: "closed or later" is read as closed, awarded and not_awarded. A BAFO round
        // is shown as in `sealed` (§7.3); a cancelled competition shows no standings.
        return in_array($competition->status, [
            CompetitionStatus::Closed,
            CompetitionStatus::Awarded,
            CompetitionStatus::NotAwarded,
        ], true);
    }

    /**
     * The offer stage that applies now (§7.3), or null when no offer is accepted in this status.
     */
    public function stageAt(Competition $competition, CarbonImmutable $now): ?OfferStage
    {
        if ($competition->status === CompetitionStatus::BafoRound) {
            return OfferStage::Bafo;
        }

        return match ($competition->phaseAt($now)) {
            Phase::Sealed => OfferStage::Sealed,
            Phase::Initial => OfferStage::Initial,
            Phase::Open, Phase::FinalWindow => OfferStage::Live,
            null => null,
        };
    }

    /**
     * The issuer-visible amount of an offer (REST logs, `offer.accepted`, webhooks, exports).
     */
    public function issuerOfferAmount(Offer $offer, Competition $competition): ?int
    {
        return $this->amountsHiddenFromIssuer($competition) ? null : $offer->amount_minor;
    }

    /**
     * `BiddingEngine::issuerLeadingAmount()`: null while sealed and locked, or without offers.
     */
    public function issuerLeadingAmount(Competition $competition): ?int
    {
        return $this->amountsHiddenFromIssuer($competition)
            ? null
            : $this->liveStates->read($competition)->leader_amount_minor;
    }

    // ---------------------------------------------------------------- loading

    public function load(Competition $competition, ?CarbonImmutable $now = null): LiveView
    {
        $standings = ParticipantStanding::query()
            ->where('competition_id', $competition->id)
            ->with(['currentOffer', 'participant'])
            ->get()
            ->keyBy('participant_id');

        $round = BafoRound::query()->where('competition_id', $competition->id)->first();

        $award = in_array($competition->status, [CompetitionStatus::Awarded], true)
            ? Award::query()->where('competition_id', $competition->id)->where('status', AwardStatus::Issued->value)->first()
            : null;

        return new LiveView(
            competition: $competition,
            state: $this->liveStates->read($competition),
            standings: $standings,
            round: $round,
            issuedAward: $award,
            now: $now ?? CarbonImmutable::instance(Date::now())->utc(),
        );
    }

    // ---------------------------------------------------------------- participant

    /**
     * `ParticipantLiveSnapshot` (API.md §2.8) for one participant.
     *
     * @return array<string, mixed>
     */
    public function participantSnapshot(Competition $competition, Participant $participant, ?LastChange $change = null, ?LiveView $view = null): array
    {
        $view ??= $this->load($competition);

        return $this->participantSnapshotFrom($view, $participant, $change ?? LastChange::snapshot());
    }

    /**
     * @return array<string, mixed>
     */
    public function participantSnapshotFrom(LiveView $view, Participant $participant, LastChange $change): array
    {
        $competition = $view->competition;
        $now = $view->now;
        $standing = $view->standingOf($participant->id);
        $liveRules = $this->liveRules($competition, $now);
        $rankVisibility = $competition->rank_visibility;
        $currentOffer = $standing?->currentOffer;

        $showFlag = $liveRules && in_array($rankVisibility, [RankVisibility::LeadingFlag, RankVisibility::Full], true);
        $showRank = $liveRules && $rankVisibility === RankVisibility::Full;
        $showPrices = $liveRules && $competition->show_prices;

        return [
            'v' => $view->state->version,
            'competition_id' => $competition->public_id,
            'direction' => $competition->direction->value,
            'status' => $competition->status->value,
            'phase' => $competition->phaseAt($now)?->value,
            'server_time' => Iso::format($now),
            'bidding_opens_at' => Iso::format($competition->bidding_opens_at),
            'effective_close_at' => Iso::format($competition->effective_close_at),
            'hard_stop_at' => Iso::format($competition->hard_stop_at),
            'extension_count' => $competition->extension_count,
            'accepting_offers' => $this->acceptingOffers($view, $standing),
            'start_price_minor' => $competition->start_price_minor,
            'min_step' => ['minor' => $competition->min_step_minor, 'bps' => $competition->min_step_bps],
            'amount_granularity_minor' => $competition->amount_granularity_minor,
            'my_offer' => $currentOffer !== null ? $this->offerSummary($currentOffer) : null,
            'my_offers_count' => $standing->offers_count ?? 0,
            'is_leading' => $showFlag ? ($standing->is_leader ?? false) : null,
            'rank' => $showRank ? $standing?->rank : null,
            'ranked_count' => $showRank ? $view->ranked()->count() : null,
            'leading_amount_minor' => $showPrices ? $view->state->leader_amount_minor : null,
            'ladder' => $showRank && $showPrices ? $this->ladder($view, $participant) : null,
            'required_next_amount_minor' => $this->requiredNextAmount($view, $standing),
            'bafo' => $this->participantBafo($view, $standing),
            'result' => $this->participantResult($competition, $participant, $view->issuedAward),
            'last_change' => $change->toArray(),
        ];
    }

    /**
     * Snapshots for several participants from one load (live broadcasts).
     *
     * @param  iterable<Participant>  $participants
     * @return array<int, array<string, mixed>> keyed by participant id
     */
    public function participantSnapshots(Competition $competition, iterable $participants, LastChange $change): array
    {
        $view = $this->load($competition);
        $snapshots = [];

        foreach ($participants as $participant) {
            $snapshots[$participant->id] = $this->participantSnapshotFrom($view, $participant, $change);
        }

        return $snapshots;
    }

    /**
     * The participant-side fields of a competition list item and detail (API.md §2.6):
     * `my_offer_amount_minor`, `is_leading` and `result`.
     *
     * @return array{my_offer_amount_minor: int|null, is_leading: bool|null, result: array{outcome: string|null, winning_amount_minor: int|null}|null}
     */
    public function participantSummary(Competition $competition, Participant $participant): array
    {
        $snapshot = $this->participantSnapshot($competition, $participant);
        /** @var array{amount_minor: int}|null $myOffer */
        $myOffer = $snapshot['my_offer'];
        /** @var bool|null $isLeading */
        $isLeading = $snapshot['is_leading'];
        /** @var array{outcome: string|null, winning_amount_minor: int|null}|null $result */
        $result = $snapshot['result'];

        return [
            'my_offer_amount_minor' => $myOffer['amount_minor'] ?? null,
            'is_leading' => $isLeading,
            'result' => $result,
        ];
    }

    /**
     * `result` (§7.9): won / not_selected / not_awarded / null, and the winning amount only
     * with `outcome_and_amount`. Null before an outcome exists.
     *
     * @return array{outcome: string|null, winning_amount_minor: int|null}|null
     */
    public function participantResult(Competition $competition, Participant $participant, ?Award $issuedAward): ?array
    {
        if (! in_array($competition->status, [CompetitionStatus::Awarded, CompetitionStatus::NotAwarded], true)) {
            return null;
        }

        $publication = $competition->result_publication;
        $won = $competition->status === CompetitionStatus::Awarded
            && $issuedAward !== null
            && $issuedAward->participant_id === $participant->id;

        // CONTRACT-GAP: §7.9 gates `not_selected` / `not_awarded` on result_publication ≠ none but
        // not `won`: the winner is always told (it also receives award.won).
        $outcome = match (true) {
            $won => 'won',
            $publication === ResultPublication::None => null,
            $competition->status === CompetitionStatus::Awarded => 'not_selected',
            default => 'not_awarded',
        };

        $winning = $publication === ResultPublication::OutcomeAndAmount
            && $competition->status === CompetitionStatus::Awarded
            && $issuedAward !== null
                ? $issuedAward->amount_minor
                : null;

        return ['outcome' => $outcome, 'winning_amount_minor' => $winning];
    }

    /**
     * `GET …/award` for a participant (API.md §1.6): the result plus `message_to_winner` for
     * the winner; null when there is no outcome yet.
     *
     * @return array<string, mixed>|null
     */
    public function participantAward(Competition $competition, Participant $participant): ?array
    {
        $award = $competition->status === CompetitionStatus::Awarded
            ? Award::query()->where('competition_id', $competition->id)->where('status', AwardStatus::Issued->value)->first()
            : null;
        $result = $this->participantResult($competition, $participant, $award);

        if ($result === null) {
            return null;
        }

        if ($result['outcome'] === 'won' && $award !== null) {
            $result['message_to_winner'] = $award->message_to_winner;
        }

        return $result;
    }

    /**
     * `[MyOffer]`, newest first (API.md §2.8). A participant always sees its own amounts.
     *
     * @return list<array<string, mixed>>
     */
    public function myOffers(Participant $participant, int $limit = 500): array
    {
        return Offer::query()
            ->where('participant_id', $participant->id)
            ->withExists('void')
            ->orderByDesc('seq')
            ->limit($limit)
            ->get()
            ->map(fn (Offer $offer): array => [
                ...$this->offerSummary($offer),
                'voided' => (bool) $offer->getAttribute('void_exists'),
            ])
            ->all();
    }

    /**
     * `{id, seq, amount_minor, stage, accepted_at}`: the viewer's own offer.
     *
     * @return array{id: string, seq: int, amount_minor: int, stage: string, accepted_at: string|null}
     */
    public function offerSummary(Offer $offer): array
    {
        return [
            'id' => $offer->public_id,
            'seq' => $offer->seq,
            'amount_minor' => $offer->amount_minor,
            'stage' => $offer->stage->value,
            'accepted_at' => Iso::format($offer->accepted_at),
        ];
    }

    private function acceptingOffers(LiveView $view, ?ParticipantStanding $standing): bool
    {
        $competition = $view->competition;
        $now = $view->now;

        if ($competition->status === CompetitionStatus::Live) {
            return $competition->bidding_opens_at !== null
                && $competition->effective_close_at !== null
                && $now->greaterThanOrEqualTo($competition->bidding_opens_at)
                && $now->lessThan($competition->effective_close_at);
        }

        if ($competition->status === CompetitionStatus::BafoRound) {
            return $view->round !== null
                && $view->round->isRunning()
                && $standing !== null
                && $standing->bafo_shortlisted
                && $standing->bafo_offer_id === null
                && $now->lessThan($view->round->cutoff_at);
        }

        return false;
    }

    /**
     * The bound of §7.4 step 14 for stages `live` and `initial`: own current amount, or the
     * leading amount when `must_beat = best` in stage `live` (disclosable: R3 forces
     * show_prices). Tender → the next offer must be ≤ it; auction → ≥ it.
     */
    private function requiredNextAmount(LiveView $view, ?ParticipantStanding $standing): ?int
    {
        $competition = $view->competition;
        $stage = $competition->status === CompetitionStatus::Live ? $this->stageAt($competition, $view->now) : null;

        if (! in_array($stage, [OfferStage::Live, OfferStage::Initial], true)) {
            return null;
        }

        $reference = $stage === OfferStage::Live && $competition->must_beat === MustBeat::Best && $view->state->leader_amount_minor !== null
            ? $view->state->leader_amount_minor
            : $standing?->current_amount_minor;

        return $reference === null ? null : OfferRules::requiredAmount($competition, $reference);
    }

    /**
     * @return list<array{alias_no: int, amount_minor: int|null, is_me: bool}>
     */
    private function ladder(LiveView $view, Participant $participant): array
    {
        return $view->ranked()
            ->map(static fn (ParticipantStanding $standing): array => [
                'alias_no' => $standing->participant->alias_no,
                'amount_minor' => $standing->current_amount_minor,
                'is_me' => $standing->participant_id === $participant->id,
            ])
            ->values()
            ->all();
    }

    /**
     * @return array{shortlisted: bool, cutoff_at: string|null, submitted: bool, reference_amount_minor: int|null}|null
     */
    private function participantBafo(LiveView $view, ?ParticipantStanding $standing): ?array
    {
        if ($view->round === null && $view->competition->status !== CompetitionStatus::BafoRound) {
            return null;
        }

        $shortlisted = $standing !== null && $standing->bafo_shortlisted;

        return [
            'shortlisted' => $shortlisted,
            'cutoff_at' => Iso::format($view->round?->cutoff_at),
            'submitted' => $shortlisted && $standing->bafo_offer_id !== null,
            'reference_amount_minor' => $shortlisted ? $standing->bafo_reference_amount_minor : null,
        ];
    }

    // ---------------------------------------------------------------- issuer

    /**
     * `IssuerLiveSnapshot` (API.md §2.8).
     *
     * @return array<string, mixed>
     */
    public function issuerSnapshot(Competition $competition, ?LastChange $change = null, ?LiveView $view = null): array
    {
        $view ??= $this->load($competition);
        $hidden = $this->amountsHiddenFromIssuer($competition);
        $participants = $this->participantsWithOrganizations($competition);
        $leaderStanding = $hidden ? null : $view->standings->first(static fn (ParticipantStanding $s): bool => $s->is_leader);
        $leaderParticipant = $leaderStanding !== null ? $participants->get($leaderStanding->participant_id) : null;

        $ranking = $this->orderRows($participants, $view, $hidden)
            ->map(function (Participant $participant) use ($view, $hidden): array {
                $standing = $view->standingOf($participant->id);

                return [
                    'participant_id' => $participant->public_id,
                    'alias_no' => $participant->alias_no,
                    'organization' => [
                        'id' => $participant->organization->public_id,
                        'name' => $participant->organization->name,
                        'logo_url' => $this->logoUrl($participant->organization),
                    ],
                    'current_amount_minor' => $hidden ? null : $standing?->current_amount_minor,
                    'first_amount_minor' => $hidden ? null : $standing?->first_amount_minor,
                    'rank' => $hidden ? null : $standing?->rank,
                    'is_leader' => $hidden ? null : ($standing->is_leader ?? false),
                    'offers_count' => $standing->offers_count ?? 0,
                    'last_offer_at' => Iso::format($standing?->last_offer_at),
                    'submitted' => $standing?->current_offer_id !== null,
                    'bafo' => [
                        'shortlisted' => $standing->bafo_shortlisted ?? false,
                        'submitted' => $standing?->bafo_offer_id !== null,
                    ],
                ];
            })
            ->values()
            ->all();

        return [
            'v' => $view->state->version,
            'competition_id' => $competition->public_id,
            'direction' => $competition->direction->value,
            'status' => $competition->status->value,
            'phase' => $competition->phaseAt($view->now)?->value,
            'server_time' => Iso::format($view->now),
            'bidding_opens_at' => Iso::format($competition->bidding_opens_at),
            'effective_close_at' => Iso::format($competition->effective_close_at),
            'hard_stop_at' => Iso::format($competition->hard_stop_at),
            'extension_count' => $competition->extension_count,
            'leader' => $leaderStanding !== null && $leaderParticipant !== null ? [
                'participant_id' => $leaderParticipant->public_id,
                'alias_no' => $leaderParticipant->alias_no,
                'organization' => [
                    'id' => $leaderParticipant->organization->public_id,
                    'name' => $leaderParticipant->organization->name,
                ],
                'amount_minor' => $leaderStanding->current_amount_minor,
                'accepted_at' => Iso::format($leaderStanding->current_at),
            ] : null,
            'reserve_met' => $hidden ? null : $view->state->reserve_met,
            'ranking' => $ranking,
            'metrics' => $this->metrics($view, $participants->count(), $hidden),
            'online_participants_count' => $this->heartbeats->onlineCount($competition->id, $participants->keys()),
            'bafo' => $view->round !== null ? $this->issuerBafo($view->round, $view) : null,
            'last_change' => ($change ?? LastChange::snapshot())->toArray(),
        ];
    }

    /**
     * `BafoRound` (issuer, API.md §2.9).
     *
     * @return array<string, mixed>
     */
    public function bafoRound(BafoRound $round): array
    {
        return [
            'id' => $round->public_id,
            'status' => $round->status->value,
            'starts_at' => Iso::format($round->starts_at),
            'cutoff_at' => Iso::format($round->cutoff_at),
            'ended_at' => Iso::format($round->ended_at),
            'shortlist_count' => $round->shortlist_count,
            'submitted_count' => $this->bafoSubmittedCount($round->competition_id),
        ];
    }

    /**
     * `[ParticipantStandingRow]` (issuer `GET …/offers`), best first; participants without
     * offers last. Public API rows add the vendor (`PublicStandingRow`).
     *
     * @return list<array<string, mixed>>
     */
    public function standingRows(Competition $competition, bool $withVendor = false): array
    {
        $view = $this->load($competition);
        $hidden = $this->amountsHiddenFromIssuer($competition);
        $participants = $this->participantsWithOrganizations($competition, $withVendor);
        $vendors = $withVendor ? $this->vendorsFor($competition, $participants) : collect();

        return $this->orderRows($participants, $view, $hidden)
            ->map(function (Participant $participant) use ($view, $hidden, $withVendor, $vendors, $competition): array {
                $standing = $view->standingOf($participant->id);
                $organization = $participant->organization;

                $row = [
                    'participant' => [
                        'id' => $participant->public_id,
                        'alias_no' => $participant->alias_no,
                        'joined_at' => Iso::format($participant->created_at),
                        'organization' => [
                            'id' => $organization->public_id,
                            'name' => $organization->name,
                            'logo_url' => $this->logoUrl($organization),
                            'email' => $organization->email,
                            'phone' => $organization->phone,
                            'cr_number' => $organization->cr_number,
                        ],
                        'coverage' => $participant->entitlement_source === EntitlementSource::SponsoredPass ? 'sponsored' : 'own_plan',
                    ],
                    'current_amount_minor' => $hidden ? null : $standing?->current_amount_minor,
                    'first_amount_minor' => $hidden ? null : $standing?->first_amount_minor,
                    'offers_count' => $standing->offers_count ?? 0,
                    'last_offer_at' => Iso::format($standing?->last_offer_at),
                    'rank' => $hidden ? null : $standing?->rank,
                    'is_leader' => $hidden ? null : ($standing->is_leader ?? false),
                    'change_ratio_bps' => $hidden ? null : OfferRules::improvementBps($competition, $standing?->current_amount_minor, $standing?->first_amount_minor),
                    'submitted' => $standing?->current_offer_id !== null,
                    'bafo' => [
                        'shortlisted' => $standing->bafo_shortlisted ?? false,
                        'submitted' => $standing?->bafo_offer_id !== null,
                        'reference_amount_minor' => $hidden ? null : $standing?->bafo_reference_amount_minor,
                    ],
                ];

                if ($withVendor) {
                    $row['vendor'] = $this->vendorRef($vendors->get($participant->id));
                }

                return $row;
            })
            ->values()
            ->all();
    }

    /**
     * `OfferLogEntry` (issuer `GET …/offers/log` and realtime `offer.accepted`). The offer's
     * participant and organization must be loaded.
     *
     * @return array<string, mixed>
     */
    public function offerLogEntry(Offer $offer, Competition $competition, ?bool $voided = null): array
    {
        $participant = $offer->participant;

        return [
            'id' => $offer->public_id,
            'seq' => $offer->seq,
            'participant' => [
                'id' => $participant->public_id,
                'alias_no' => $participant->alias_no,
                'organization' => [
                    'id' => $participant->organization->public_id,
                    'name' => $participant->organization->name,
                ],
            ],
            'amount_minor' => $this->issuerOfferAmount($offer, $competition),
            'stage' => $offer->stage->value,
            'accepted_at' => Iso::format($offer->accepted_at),
            'channel' => $offer->channel->value,
            'voided' => $voided ?? (bool) ($offer->getAttribute('void_exists') ?? $offer->void()->exists()),
        ];
    }

    /**
     * `PublicResults` (API.md §3.5), the issuer projection.
     *
     * @return array<string, mixed>
     */
    public function publicResults(Competition $competition): array
    {
        $view = $this->load($competition);
        $hidden = $this->amountsHiddenFromIssuer($competition);
        $participants = $this->participantsWithOrganizations($competition, true);
        $vendors = $this->vendorsFor($competition, $participants);

        $ranking = $this->orderRows($participants, $view, $hidden)
            ->map(function (Participant $participant) use ($view, $hidden, $vendors): array {
                $standing = $view->standingOf($participant->id);

                return [
                    'participant_id' => $participant->public_id,
                    'alias_no' => $participant->alias_no,
                    'organization' => [
                        'id' => $participant->organization->public_id,
                        'name' => $participant->organization->name,
                        'cr_number' => $participant->organization->cr_number,
                        'vat_number' => $participant->organization->vat_number,
                    ],
                    'vendor' => $this->vendorRef($vendors->get($participant->id)),
                    'rank' => $hidden ? null : $standing?->rank,
                    'current_amount_minor' => $hidden ? null : $standing?->current_amount_minor,
                    'first_amount_minor' => $hidden ? null : $standing?->first_amount_minor,
                    'offers_count' => $standing->offers_count ?? 0,
                    'last_offer_at' => Iso::format($standing?->last_offer_at),
                    'is_leader' => $hidden ? null : ($standing->is_leader ?? false),
                ];
            })
            ->values()
            ->all();

        $leader = $hidden ? null : $view->state->leader_amount_minor;

        return [
            'competition_id' => $competition->public_id,
            'reference_no' => $competition->reference_no,
            'status' => $competition->status->value,
            'direction' => $competition->direction->value,
            'closed_at' => Iso::format($competition->closed_at),
            'offers_opened_at' => Iso::format($competition->offers_opened_at),
            'leader_amount_minor' => $leader,
            'reserve_met' => $hidden ? null : $view->state->reserve_met,
            'improvement_vs_start_bps' => OfferRules::improvementBps($competition, $leader, $competition->start_price_minor),
            'ranking' => $ranking,
            'generated_at' => Iso::format($view->now),
        ];
    }

    /**
     * The `author` of a Q&A comment for one audience (API.md §2.7): participants see other
     * participants only as "Participant {alias}", never by name.
     *
     * @return array<string, mixed>
     */
    public function commentAuthor(Comment $comment, bool $viewerIsIssuer, ?int $viewerOrganizationId): array
    {
        $organization = Organization::query()->find($comment->author_organization_id);

        if ($comment->is_issuer) {
            return ['kind' => 'issuer', 'organization_name' => $organization?->name];
        }

        if (! $viewerIsIssuer && $viewerOrganizationId === $comment->author_organization_id) {
            return ['kind' => 'me'];
        }

        $alias = Participant::query()->whereKey($comment->author_participant_id)->value('alias_no');

        return $viewerIsIssuer
            ? ['kind' => 'participant', 'alias_no' => $alias === null ? null : (int) $alias, 'organization_name' => $organization?->name]
            : ['kind' => 'participant', 'alias_no' => $alias === null ? null : (int) $alias];
    }

    // ---------------------------------------------------------------- helpers

    /**
     * The joined participants with their organization (and logo), keyed by id.
     *
     * @return Collection<int, Participant>
     */
    private function participantsWithOrganizations(Competition $competition, bool $withInvitation = false): Collection
    {
        $relations = ['organization.logoFile'];

        if ($withInvitation) {
            $relations[] = 'invitation.vendor.externalRefs';
        }

        return Participant::query()
            ->where('competition_id', $competition->id)
            ->with($relations)
            ->orderBy('alias_no')
            ->get()
            ->keyBy('id');
    }

    /**
     * Ranked first (by rank), then the others by alias. While amounts are hidden every row
     * keeps the alias order.
     *
     * @param  Collection<int, Participant>  $participants
     * @return Collection<int, Participant>
     */
    private function orderRows(Collection $participants, LiveView $view, bool $hidden): Collection
    {
        if ($hidden) {
            return $participants->sortBy('alias_no')->values();
        }

        return $participants
            ->sortBy(static fn (Participant $participant): array => [
                $view->standingOf($participant->id)->rank ?? PHP_INT_MAX,
                $participant->alias_no,
            ])
            ->values();
    }

    /**
     * @return array<string, mixed>
     */
    private function metrics(LiveView $view, int $participantsJoined, bool $hidden): array
    {
        $competition = $view->competition;

        return [
            'offers_count' => $view->state->accepted_offer_count,
            'participants_joined' => $participantsJoined,
            'participants_with_offers' => $view->state->participants_with_offers,
            'invitations_count' => $competition->invitations()->count(),
            'improvement_vs_start_bps' => $hidden
                ? null
                : OfferRules::improvementBps($competition, $view->state->leader_amount_minor, $competition->start_price_minor),
        ];
    }

    /**
     * @return array{status: string, cutoff_at: string|null, shortlist_count: int, submitted_count: int}
     */
    private function issuerBafo(BafoRound $round, LiveView $view): array
    {
        return [
            'status' => $round->status->value,
            'cutoff_at' => Iso::format($round->cutoff_at),
            'shortlist_count' => $round->shortlist_count,
            'submitted_count' => $view->standings
                ->filter(static fn (ParticipantStanding $s): bool => $s->bafo_shortlisted && $s->bafo_offer_id !== null)
                ->count(),
        ];
    }

    private function bafoSubmittedCount(int $competitionId): int
    {
        return ParticipantStanding::query()
            ->where('competition_id', $competitionId)
            ->where('bafo_shortlisted', true)
            ->whereNotNull('bafo_offer_id')
            ->count();
    }

    private function logoUrl(Organization $organization): ?string
    {
        $logo = $organization->logoFile;

        return $logo !== null ? $this->files->publicUrl($logo) : null;
    }

    /**
     * The issuer's vendor record of each participant: the invitation's vendor, else a vendor of
     * the issuer linked to the participant organization.
     *
     * @param  Collection<int, Participant>  $participants
     * @return Collection<int, Vendor> keyed by participant id
     */
    private function vendorsFor(Competition $competition, Collection $participants): Collection
    {
        $linked = Vendor::query()
            ->where('organization_id', $competition->organization_id)
            ->whereIn('linked_organization_id', $participants->pluck('organization_id')->all())
            ->with('externalRefs')
            ->get()
            ->keyBy('linked_organization_id');

        $vendors = collect();

        foreach ($participants as $participant) {
            $vendor = $participant->invitation->vendor ?? $linked->get($participant->organization_id);

            if ($vendor !== null) {
                $vendors->put($participant->id, $vendor);
            }
        }

        return $vendors;
    }

    /**
     * `{"id", "external_refs"}` or null.
     *
     * @return array{id: string, external_refs: list<array<string, string|null>>}|null
     */
    private function vendorRef(?Vendor $vendor): ?array
    {
        if ($vendor === null) {
            return null;
        }

        return [
            'id' => $vendor->public_id,
            'external_refs' => $vendor->externalRefs
                ->map(static fn (ExternalRef $ref): array => self::externalRef($ref))
                ->values()
                ->all(),
        ];
    }

    /**
     * `ExternalRef` (API.md §2.12): `id` is the ERP key.
     *
     * @return array{system: string, type: string, id: string, number: string|null, url: string|null}
     */
    public static function externalRef(ExternalRef $ref): array
    {
        return [
            'system' => $ref->system,
            'type' => $ref->type,
            'id' => $ref->value,
            'number' => $ref->number,
            'url' => $ref->url,
        ];
    }
}
