<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Services;

use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Competitions\Enums\Phase;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Data\Delivery;
use App\Modules\Notifications\Data\NotificationPayload;
use App\Modules\Notifications\Data\NotificationSubject;
use App\Modules\Notifications\Data\ParticipantRecipient;
use App\Modules\Notifications\Enums\DeliveryChannel;
use App\Modules\Notifications\Enums\NotificationType;
use App\Modules\Notifications\Support\NotificationRoutes;
use App\Support\Http\Iso;
use Carbon\CarbonImmutable;

/**
 * Bidding notifications (ARCHITECTURE §11.3, rows `offer.*`, `standing.lost_lead`, `bafo.*`,
 * `award.*`), called by the listeners of the Bidding events (§10). None of them carries an
 * amount, a rank or another participant's identity.
 */
final readonly class BiddingNotifier
{
    public function __construct(
        private NotificationDispatcher $dispatcher,
        private CompetitionAudience $audience,
        private Recipients $recipients,
        private LiveHeartbeats $heartbeats,
    ) {}

    /**
     * An accepted offer (§10 `OfferAccepted`):
     * - `offer.received` to the issuer team, as a digest (one in-app and one push per 5 minutes
     *   per competition per user);
     * - `standing.lost_lead` to the users of the previous leader when the lead changed hands,
     *   the competition shows standings (`rank_visibility ≠ none`) and the phase is open or the
     *   final window. At most once per 60 s; push only when they are not on the live screen.
     */
    public function offerAccepted(Offer $offer, Competition $competition, bool $leaderChanged, ?int $previousLeaderParticipantId): int
    {
        $sent = $this->offerReceived($competition);

        if ($leaderChanged && $previousLeaderParticipantId !== null && $previousLeaderParticipantId !== $offer->participant_id) {
            $sent += $this->lostLead($competition, $previousLeaderParticipantId);
        }

        return $sent;
    }

    /** `bafo.invited`: the users of the shortlisted participants. */
    public function bafoRoundStarted(BafoRound $round, Competition $competition): int
    {
        $shortlisted = Participant::query()
            ->whereIn('id', ParticipantStanding::query()
                ->where('competition_id', $competition->id)
                ->where('bafo_shortlisted', true)
                ->select('participant_id'))
            ->orderBy('id')
            ->get();

        return $this->dispatcher->send(
            NotificationType::BafoInvited,
            $this->payload($competition, NotificationRoutes::competitionLive($competition), [
                'cutoff_time' => Iso::format($round->cutoff_at),
            ]),
            $this->toParticipants($competition, $shortlisted),
        );
    }

    /** `bafo.ended`: the issuer team. */
    public function bafoRoundEnded(BafoRound $round, Competition $competition): int
    {
        return $this->dispatcher->send(
            NotificationType::BafoEnded,
            $this->payload($competition, NotificationRoutes::competition($competition)),
            Delivery::toEach($this->audience->issuerTeam($competition)),
        );
    }

    /**
     * `award.won` to the winner's users (the mail carries the message to the winner) and, unless
     * results are not published, `award.not_selected` to the other participants that made at
     * least one offer.
     */
    public function awardIssued(Award $award, Competition $competition): int
    {
        $sent = $this->dispatcher->send(
            NotificationType::AwardWon,
            $this->payload($competition, NotificationRoutes::competition($competition), [
                'message_to_winner' => $award->message_to_winner,
            ]),
            Delivery::toEach($this->recipients->members($award->organization_id)),
        );

        if ($competition->result_publication === ResultPublication::None) {
            return $sent;
        }

        $others = Participant::query()
            ->where('competition_id', $competition->id)
            ->whereKeyNot($award->participant_id)
            ->whereIn('id', Offer::query()->where('competition_id', $competition->id)->select('participant_id'))
            ->orderBy('id')
            ->get();

        return $sent + $this->dispatcher->send(
            NotificationType::AwardNotSelected,
            $this->payload($competition, NotificationRoutes::competition($competition)),
            $this->toParticipants($competition, $others),
        );
    }

    /** `award.revoked`: the users of the revoked winner, with the reason. */
    public function awardRevoked(Award $award, Competition $competition): int
    {
        return $this->dispatcher->send(
            NotificationType::AwardRevoked,
            $this->payload($competition, NotificationRoutes::competition($competition), [
                'reason' => $award->revoke_reason !== null && trim($award->revoke_reason) !== '' ? trim($award->revoke_reason) : null,
            ]),
            Delivery::toEach($this->recipients->members($award->organization_id)),
        );
    }

    /**
     * `offer.voided`: the users of the offer's organization (with mail) and the issuer team
     * (without mail).
     */
    public function offerVoided(Offer $offer, Competition $competition): int
    {
        return $this->dispatcher->send(
            NotificationType::OfferVoided,
            $this->payload($competition, NotificationRoutes::competition($competition)),
            [
                ...Delivery::toEach($this->recipients->members($offer->organization_id)),
                ...Delivery::toEach($this->audience->issuerTeam($competition), [DeliveryChannel::Mail]),
            ],
        );
    }

    private function offerReceived(Competition $competition): int
    {
        $deliveries = [];

        foreach ($this->audience->issuerTeam($competition) as $user) {
            if ($this->allow('offer.received', $competition, $user, 'offer_received')) {
                $deliveries[] = new Delivery($user);
            }
        }

        return $this->dispatcher->send(
            NotificationType::OfferReceived,
            $this->payload($competition, NotificationRoutes::competitionLive($competition)),
            $deliveries,
        );
    }

    private function lostLead(Competition $competition, int $previousLeaderParticipantId): int
    {
        // CONTRACT-GAP: §11.3 excludes the initial and sealed phases; outside `live` (for example
        // the BAFO round, whose offers are sealed until its end) there is no phase, so nothing is sent.
        $phase = $competition->phaseAt(CarbonImmutable::now());

        if ($competition->rank_visibility === RankVisibility::None
            || ! in_array($phase, [Phase::Open, Phase::FinalWindow], true)) {
            return 0;
        }

        $previousLeader = Participant::query()
            ->where('competition_id', $competition->id)
            ->whereKey($previousLeaderParticipantId)
            ->get();

        $deliveries = [];

        foreach ($this->audience->participantUsers($competition, $previousLeader) as $recipient) {
            if ($this->allow('standing.lost_lead', $competition, $recipient->user, 'standing_lost_lead')) {
                $watching = $this->heartbeats->isWatching($competition->id, $recipient->participant->id);
                $deliveries[] = new Delivery($recipient->user, $watching ? [DeliveryChannel::Push] : []);
            }
        }

        return $this->dispatcher->send(
            NotificationType::StandingLostLead,
            $this->payload($competition, NotificationRoutes::competitionLive($competition)),
            $deliveries,
        );
    }

    /**
     * @param  iterable<Participant>  $participants
     * @return list<Delivery>
     */
    private function toParticipants(Competition $competition, iterable $participants): array
    {
        return array_map(
            static fn (ParticipantRecipient $recipient): Delivery => new Delivery($recipient->user),
            $this->audience->participantUsers($competition, $participants),
        );
    }

    /**
     * @param  array<string, mixed>  $params
     */
    private function payload(Competition $competition, string $route, array $params = []): NotificationPayload
    {
        return new NotificationPayload(
            [...CompetitionAudience::params($competition), ...$params],
            NotificationSubject::of($competition),
            $route,
        );
    }

    private function allow(string $type, Competition $competition, User $user, string $throttle): bool
    {
        return $this->dispatcher->allow("{$type}:{$competition->id}:{$user->getKey()}", Throttles::seconds($throttle));
    }
}
