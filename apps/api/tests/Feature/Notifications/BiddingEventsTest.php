<?php

declare(strict_types=1);

use App\Modules\Bidding\Data\OfferAcceptedContext;
use App\Modules\Bidding\Enums\AwardStatus;
use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\BafoRound;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Bidding\Models\OfferVoid;
use App\Modules\Bidding\Models\ParticipantStanding;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Notifications\Notifications\AwardNotSelectedNotification;
use App\Modules\Notifications\Notifications\AwardRevokedNotification;
use App\Modules\Notifications\Notifications\AwardWonNotification;
use App\Modules\Notifications\Notifications\BafoEndedNotification;
use App\Modules\Notifications\Notifications\BafoInvitedNotification;
use App\Modules\Notifications\Notifications\OfferReceivedNotification;
use App\Modules\Notifications\Notifications\OfferVoidedNotification;
use App\Modules\Notifications\Notifications\StandingLostLeadNotification;
use App\Modules\Notifications\Services\LiveHeartbeats;
use App\Support\Http\Iso;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Notifications\NotificationScenario as S;

use function Pest\Laravel\travel;

/*
 * Bidding events (ARCHITECTURE §10) → `offer.*`, `standing.lost_lead`, `bafo.*`, `award.*` (§11.3).
 */

beforeEach(function () {
    Notification::fake();

    $this->issuer = S::team();
    // Live, no final pricing window: phase `open`, where standings are shown.
    $this->competition = S::competition($this->issuer->organization, fn ($f) => $f->withoutFinalWindow()->live());
    $this->bidderA = S::participant($this->competition, 'en');
    $this->bidderB = S::participant($this->competition);
});

function notificationsOfferAccepted(Competition $competition, Participant $by, bool $leaderChanged = false, ?int $previousLeader = null): Offer
{
    $offer = Offer::factory()->create(['participant_id' => $by->id]);

    S::fire(S::BIDDING.'OfferAccepted', [
        'offer' => $offer,
        'competition' => $competition,
        'context' => new OfferAcceptedContext(
            isFirstOfferOfParticipant: true,
            leaderChanged: $leaderChanged,
            previousLeaderParticipantId: $previousLeader,
            changedParticipantIds: array_values(array_filter([$by->id, $previousLeader])),
            extended: false,
            version: 1,
        ),
    ]);

    return $offer;
}

describe('offer.received', function () {
    it('tells the issuer team that offers arrived, as a digest of one per 5 minutes', function () {
        notificationsOfferAccepted($this->competition, $this->bidderA);

        $sent = S::sent(OfferReceivedNotification::class);
        expect(array_keys($sent))->toBe(S::ids($this->issuer->active()))
            ->and($sent[$this->issuer->owner->id]['channels'])->toBe(['database', 'push'])
            ->and($sent[$this->issuer->owner->id]['notification']->params)->toBe([
                'competition_title' => $this->competition->title,
                'direction' => 'tender',
            ]);

        notificationsOfferAccepted($this->competition, $this->bidderB);
        Notification::assertSentTimes(OfferReceivedNotification::class, 3);

        travel(6)->minutes();
        notificationsOfferAccepted($this->competition, $this->bidderB);
        Notification::assertSentTimes(OfferReceivedNotification::class, 6);
    });
});

describe('standing.lost_lead', function () {
    it('tells the previous leader, without push while it watches the live screen', function () {
        Cache::put(LiveHeartbeats::key($this->competition->id, $this->bidderA->id), 1, 45);

        notificationsOfferAccepted($this->competition, $this->bidderB, leaderChanged: true, previousLeader: $this->bidderA->id);

        $sent = S::sent(StandingLostLeadNotification::class);
        expect(array_keys($sent))->toBe(S::ids(S::usersOf($this->bidderA)))
            ->and(array_values($sent)[0]['channels'])->toBe(['database'])
            ->and(array_values($sent)[0]['locale'])->toBe('en')
            ->and(array_values($sent)[0]['notification']->route)->toBe('/competitions/'.$this->competition->public_id.'/live');
    });

    it('pushes when the previous leader is away, at most once per minute', function () {
        notificationsOfferAccepted($this->competition, $this->bidderB, leaderChanged: true, previousLeader: $this->bidderA->id);
        expect(array_values(S::sent(StandingLostLeadNotification::class))[0]['channels'])->toBe(['database', 'push']);

        notificationsOfferAccepted($this->competition, $this->bidderB, leaderChanged: true, previousLeader: $this->bidderA->id);
        Notification::assertSentTimes(StandingLostLeadNotification::class, 1);

        travel(61)->seconds();
        notificationsOfferAccepted($this->competition, $this->bidderB, leaderChanged: true, previousLeader: $this->bidderA->id);
        Notification::assertSentTimes(StandingLostLeadNotification::class, 2);
    });

    it('stays silent when standings are hidden, in the initial or sealed phase, or without a lead change', function (Closure $state) {
        $competition = S::competition($this->issuer->organization, $state);
        $leader = S::participant($competition);
        $challenger = S::participant($competition);

        notificationsOfferAccepted($competition, $challenger, leaderChanged: true, previousLeader: $leader->id);
        notificationsOfferAccepted($this->competition, $this->bidderB);

        Notification::assertNotSentTo(S::usersOf($leader), StandingLostLeadNotification::class);
        Notification::assertNotSentTo(S::usersOf($this->bidderA), StandingLostLeadNotification::class);
    })->with([
        'rank_visibility none' => [fn ($f) => $f->withoutFinalWindow()->live()->state(['rank_visibility' => RankVisibility::None])],
        'initial phase' => [fn ($f) => $f->live()],
        'sealed' => [fn ($f) => $f->sealed()->live()],
    ]);
});

describe('BAFO round', function () {
    it('invites the shortlisted participants by in-app, push and mail, with the cutoff', function () {
        $competition = S::competition($this->issuer->organization, fn ($f) => $f->inBafoRound());
        $shortlisted = S::participant($competition, 'en');
        $left = S::participant($competition);
        ParticipantStanding::factory()->create(['participant_id' => $shortlisted->id, 'bafo_shortlisted' => true]);
        ParticipantStanding::factory()->create(['participant_id' => $left->id, 'bafo_shortlisted' => false]);
        $round = BafoRound::factory()->create(['competition_id' => $competition->id]);

        S::fire(S::BIDDING.'BafoRoundStarted', ['round' => $round, 'competition' => $competition, 'actor' => S::actor()]);

        $sent = S::sent(BafoInvitedNotification::class);
        expect(array_keys($sent))->toBe(S::ids(S::usersOf($shortlisted)))
            ->and(array_values($sent)[0]['channels'])->toBe(['database', 'push', 'mail'])
            ->and(array_values($sent)[0]['notification']->params['cutoff_time'])->toBe(Iso::format($round->cutoff_at));
    });

    it('tells the issuer team that the round ended', function () {
        $competition = S::competition($this->issuer->organization, fn ($f) => $f->inBafoRound());
        $round = BafoRound::factory()->ended()->create(['competition_id' => $competition->id, 'started_by_user_id' => $this->issuer->owner->id]);

        S::fire(S::BIDDING.'BafoRoundEnded', ['round' => $round, 'competition' => $competition]);

        $sent = S::sent(BafoEndedNotification::class);
        expect(array_keys($sent))->toBe(S::ids($this->issuer->active()))
            ->and($sent[$this->issuer->owner->id]['channels'])->toBe(['database', 'push']);
    });
});

describe('awards', function () {
    beforeEach(function () {
        $this->competition->update(['status' => 'awarded']);
        $this->winningOffer = Offer::factory()->create(['participant_id' => $this->bidderA->id]);
        Offer::factory()->create(['participant_id' => $this->bidderB->id]);
        $this->silent = S::participant($this->competition); // joined, never offered
        $this->award = Award::factory()->create(['offer_id' => $this->winningOffer->id, 'awarded_by_user_id' => $this->issuer->owner->id]);
    });

    it('tells the winner by in-app, push and mail with the message, and the other bidders they were not selected', function () {
        S::fire(S::BIDDING.'AwardIssued', ['award' => $this->award, 'competition' => $this->competition, 'actor' => S::actor()]);

        $won = S::sent(AwardWonNotification::class);
        expect(array_keys($won))->toBe(S::ids(S::usersOf($this->bidderA)))
            ->and(array_values($won)[0]['channels'])->toBe(['database', 'push', 'mail'])
            ->and(array_values($won)[0]['locale'])->toBe('en')
            ->and(array_values($won)[0]['notification']->params['message_to_winner'])->toBe($this->award->message_to_winner);

        $notSelected = S::sent(AwardNotSelectedNotification::class);
        expect(array_keys($notSelected))->toBe(S::ids(S::usersOf($this->bidderB)))
            ->and(array_values($notSelected)[0]['channels'])->toBe(['database', 'push', 'mail']);
        Notification::assertNotSentTo(S::usersOf($this->silent), AwardNotSelectedNotification::class);
    });

    it('does not tell the other bidders when results are not published', function () {
        $this->competition->update(['result_publication' => ResultPublication::None]);

        S::fire(S::BIDDING.'AwardIssued', ['award' => $this->award, 'competition' => $this->competition->fresh(), 'actor' => S::actor()]);

        Notification::assertSentTimes(AwardWonNotification::class, count(S::usersOf($this->bidderA)));
        Notification::assertNothingSentTo(S::usersOf($this->bidderB)[0]);
    });

    it('tells the revoked winner why', function () {
        $this->award->update(['status' => AwardStatus::Revoked, 'revoked_at' => now(), 'revoke_reason' => 'لم تُستكمل متطلبات التعاقد']);

        S::fire(S::BIDDING.'AwardRevoked', ['award' => $this->award->fresh(), 'competition' => $this->competition, 'actor' => S::actor()]);

        $sent = S::sent(AwardRevokedNotification::class);
        expect(array_keys($sent))->toBe(S::ids(S::usersOf($this->bidderA)))
            ->and(array_values($sent)[0]['channels'])->toBe(['database', 'push', 'mail'])
            ->and(array_values($sent)[0]['notification']->params['reason'])->toBe('لم تُستكمل متطلبات التعاقد');
    });
});

it('tells the offer\'s organization (with mail) and the issuer team (without) that an offer was voided', function () {
    $offer = Offer::factory()->create(['participant_id' => $this->bidderB->id]);
    $void = OfferVoid::factory()->create(['offer_id' => $offer->id]);

    S::fire(S::BIDDING.'OfferVoided', ['void' => $void, 'offer' => $offer, 'competition' => $this->competition]);

    $sent = S::sent(OfferVoidedNotification::class);
    expect(array_keys($sent))->toBe(S::ids([...S::usersOf($this->bidderB), ...$this->issuer->active()]))
        ->and($sent[S::usersOf($this->bidderB)[0]->id]['channels'])->toBe(['database', 'push', 'mail'])
        ->and($sent[$this->issuer->owner->id]['channels'])->toBe(['database', 'push']);
    Notification::assertNotSentTo(S::usersOf($this->bidderA), OfferVoidedNotification::class);
});
