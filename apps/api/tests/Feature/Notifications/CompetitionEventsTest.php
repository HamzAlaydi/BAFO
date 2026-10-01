<?php

declare(strict_types=1);

use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Enums\ResultPublication;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionAttachment;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Models\Organization;
use App\Modules\Notifications\Notifications\CommentCreatedNotification;
use App\Modules\Notifications\Notifications\CompetitionCancelledNotification;
use App\Modules\Notifications\Notifications\CompetitionClosedNotification;
use App\Modules\Notifications\Notifications\CompetitionClosingSoonNotification;
use App\Modules\Notifications\Notifications\CompetitionExtendedNotification;
use App\Modules\Notifications\Notifications\CompetitionFinalWindowStartedNotification;
use App\Modules\Notifications\Notifications\CompetitionInvitedNotification;
use App\Modules\Notifications\Notifications\CompetitionNotAwardedNotification;
use App\Modules\Notifications\Notifications\CompetitionOpenedNotification;
use App\Modules\Notifications\Notifications\CompetitionUpdatedNotification;
use App\Modules\Notifications\Notifications\InvitationDeclinedNotification;
use App\Modules\Notifications\Notifications\InvitationJoinedNotification;
use App\Modules\Notifications\Services\LiveHeartbeats;
use App\Support\Http\Iso;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Tests\Support\Notifications\NotificationScenario as S;

use function Pest\Laravel\travel;

/*
 * Competitions events (ARCHITECTURE §10) → catalogue notifications (§11.3): recipients,
 * channels, recipient language, parameters and throttles.
 */

beforeEach(function () {
    Notification::fake();

    $this->issuer = S::team();
    $this->competition = S::competition($this->issuer->organization, attributes: ['title' => 'توريد أجهزة حاسب']);
    $this->bidderA = S::participant($this->competition, 'en');
    $this->bidderB = S::participant($this->competition);
    $this->participantUsers = S::ids([...S::usersOf($this->bidderA), ...S::usersOf($this->bidderB)]);
});

describe('competition.invited', function () {
    it('notifies the active users of a known invitee organization in-app and by push, in their language', function () {
        $invitee = S::team('en');
        $invitation = Invitation::factory()->forOrganization($invitee->organization)->sent()->create(['competition_id' => $this->competition->id]);

        S::fire(S::COMPETITIONS.'InvitationSent', ['invitation' => $invitation, 'actor' => S::actor()]);

        $sent = S::sent(CompetitionInvitedNotification::class);
        expect(array_keys($sent))->toBe(S::ids($invitee->active()))
            ->and($sent[$invitee->owner->id]['channels'])->toBe(['database', 'push'])
            ->and($sent[$invitee->owner->id]['locale'])->toBe('en')
            ->and($sent[$invitee->owner->id]['notification']->params)->toMatchArray([
                'competition_title' => 'توريد أجهزة حاسب',
                'direction' => 'tender',
                'issuer_name' => $this->issuer->organization->name,
                'sponsored' => false,
            ])
            ->and($sent[$invitee->owner->id]['notification']->route)->toBe('/competitions/'.$this->competition->public_id)
            ->and($sent[$invitee->owner->id]['notification']->subject->toArray())->toBe(['type' => 'competition', 'id' => $this->competition->public_id]);
    });

    it('marks the invitation sponsored when a pass is reserved for it', function () {
        $invitee = S::team();
        $invitation = Invitation::factory()->forOrganization($invitee->organization)->sent()->create(['competition_id' => $this->competition->id]);
        $sponsorship = CompetitionSponsorship::factory()->funded()->create(['competition_id' => $this->competition->id, 'organization_id' => $this->issuer->organization->id]);
        SponsoredPass::factory()->create(['sponsorship_id' => $sponsorship->id, 'invitation_id' => $invitation->id, 'competition_id' => $this->competition->id]);

        S::fire(S::COMPETITIONS.'InvitationSent', ['invitation' => $invitation, 'actor' => S::actor()]);

        expect(S::sent(CompetitionInvitedNotification::class)[$invitee->owner->id]['notification']->params['sponsored'])->toBeTrue();
    });

    it('sends nothing for an organization that is not on BAFO yet (the token mail covers it)', function () {
        $invitation = Invitation::factory()->sent()->create(['competition_id' => $this->competition->id]);

        S::fire(S::COMPETITIONS.'InvitationSent', ['invitation' => $invitation, 'actor' => S::actor()]);

        Notification::assertNothingSent();
    });
});

describe('competition.updated', function () {
    it('notifies participant users of content changes, at most once per 10 minutes', function () {
        S::fire(S::COMPETITIONS.'CompetitionUpdated', ['competition' => $this->competition, 'fields' => ['title', 'rules'], 'actor' => S::actor()]);

        $sent = S::sent(CompetitionUpdatedNotification::class);
        expect(array_keys($sent))->toBe($this->participantUsers)
            ->and($sent[S::usersOf($this->bidderA)[0]->id]['channels'])->toBe(['database', 'push'])
            ->and($sent[S::usersOf($this->bidderA)[0]->id]['locale'])->toBe('en');

        S::fire(S::COMPETITIONS.'CompetitionUpdated', ['competition' => $this->competition, 'fields' => ['description'], 'actor' => S::actor()]);
        Notification::assertSentTimes(CompetitionUpdatedNotification::class, count($this->participantUsers));

        travel(11)->minutes();
        S::fire(S::COMPETITIONS.'CompetitionUpdated', ['competition' => $this->competition, 'fields' => ['schedule'], 'actor' => S::actor()]);
        Notification::assertSentTimes(CompetitionUpdatedNotification::class, 2 * count($this->participantUsers));
    });

    it('ignores fields that do not concern participants and draft competitions', function () {
        S::fire(S::COMPETITIONS.'CompetitionUpdated', ['competition' => $this->competition, 'fields' => ['invitations', 'rules'], 'actor' => S::actor()]);

        $draft = S::competition($this->issuer->organization, fn ($f) => $f);
        S::fire(S::COMPETITIONS.'CompetitionUpdated', ['competition' => $draft, 'fields' => ['title'], 'actor' => S::actor()]);

        Notification::assertNothingSent();
    });

    it('treats an addendum on a published competition as an update', function () {
        $attachment = CompetitionAttachment::factory()->addendum()->create(['competition_id' => $this->competition->id]);

        S::fire(S::COMPETITIONS.'AttachmentAdded', ['attachment' => $attachment, 'actor' => S::actor()]);

        expect(array_keys(S::sent(CompetitionUpdatedNotification::class)))->toBe($this->participantUsers);
    });

    it('skips an attachment whose draft was deleted before the queued listener ran', function () {
        $draft = S::competition($this->issuer->organization, fn ($f) => $f);
        $attachment = CompetitionAttachment::factory()->create(['competition_id' => $draft->id]);
        $draft->delete();

        S::fire(S::COMPETITIONS.'AttachmentAdded', ['attachment' => $attachment->fresh(), 'actor' => S::actor()]);

        Notification::assertNothingSent();
    });
});

describe('opening, final window and closing soon', function () {
    it('notifies participant users when offers open, with the live route', function () {
        S::fire(S::COMPETITIONS.'CompetitionOpened', ['competition' => $this->competition]);

        $sent = S::sent(CompetitionOpenedNotification::class);
        expect(array_keys($sent))->toBe($this->participantUsers)
            ->and(array_values($sent)[0]['notification']->route)->toBe('/competitions/'.$this->competition->public_id.'/live');
    });

    it('sends the closing time when the final window starts', function () {
        S::fire(S::COMPETITIONS.'CompetitionFinalWindowStarted', ['competition' => $this->competition]);

        $sent = S::sent(CompetitionFinalWindowStartedNotification::class);
        expect(array_keys($sent))->toBe($this->participantUsers)
            ->and(array_values($sent)[0]['notification']->params['close_time'])->toBe(Iso::format($this->competition->effective_close_at));
    });

    it('pushes "closing soon" only, and only to participants who are not on the live screen', function () {
        Cache::put(LiveHeartbeats::key($this->competition->id, $this->bidderB->id), 1, 45);

        S::fire(S::COMPETITIONS.'CompetitionClosingSoon', ['competition' => $this->competition, 'minutes' => 2]);

        $sent = S::sent(CompetitionClosingSoonNotification::class);
        expect(array_keys($sent))->toBe(S::ids(S::usersOf($this->bidderA)))
            ->and(array_values($sent)[0]['channels'])->toBe(['push'])
            ->and(array_values($sent)[0]['notification']->params['minutes'])->toBe(2);
    });
});

describe('competition.extended', function () {
    it('always notifies participants and the issuer team of a manual extension', function () {
        $extension = CompetitionExtension::factory()->create(['competition_id' => $this->competition->id]);

        S::fire(S::COMPETITIONS.'CompetitionExtended', ['competition' => $this->competition, 'extension' => $extension, 'actor' => S::actor()]);

        $sent = S::sent(CompetitionExtendedNotification::class);
        expect(array_keys($sent))->toBe(S::ids([...S::usersOf($this->bidderA), ...S::usersOf($this->bidderB), ...$this->issuer->active()]))
            ->and($sent[$this->issuer->owner->id]['notification']->params['close_time'])->toBe(Iso::format($extension->new_close_at));
    });

    it('throttles anti-sniping extensions and does not push to participants on the live screen', function () {
        Cache::put(LiveHeartbeats::key($this->competition->id, $this->bidderB->id), 1, 45);
        $auto = fn () => CompetitionExtension::factory()->create([
            'competition_id' => $this->competition->id,
            'kind' => ExtensionKind::Auto,
            'triggered_by_offer_id' => 1,
            'previous_close_at' => $this->competition->effective_close_at,
            'new_close_at' => $this->competition->effective_close_at->addMinutes(3),
            'reason' => null,
        ]);

        S::fire(S::COMPETITIONS.'CompetitionExtended', ['competition' => $this->competition, 'extension' => $auto(), 'actor' => S::actor()]);

        $sent = S::sent(CompetitionExtendedNotification::class);
        expect($sent[S::usersOf($this->bidderA)[0]->id]['channels'])->toBe(['database', 'push'])
            ->and($sent[S::usersOf($this->bidderB)[0]->id]['channels'])->toBe(['database']);

        S::fire(S::COMPETITIONS.'CompetitionExtended', ['competition' => $this->competition, 'extension' => $auto(), 'actor' => S::actor()]);
        Notification::assertSentTimes(CompetitionExtendedNotification::class, count($sent));

        travel(3)->minutes();
        S::fire(S::COMPETITIONS.'CompetitionExtended', ['competition' => $this->competition, 'extension' => $auto(), 'actor' => S::actor()]);
        Notification::assertSentTimes(CompetitionExtendedNotification::class, 2 * count($sent));
    });
});

describe('closing outcomes', function () {
    it('mails the close to the issuer team only', function () {
        S::fire(S::COMPETITIONS.'CompetitionClosed', ['competition' => $this->competition]);

        $sent = S::sent(CompetitionClosedNotification::class);
        expect(array_keys($sent))->toBe(S::ids([...S::usersOf($this->bidderA), ...S::usersOf($this->bidderB), ...$this->issuer->active()]))
            ->and($sent[$this->issuer->owner->id]['channels'])->toBe(['database', 'push', 'mail'])
            ->and($sent[S::usersOf($this->bidderA)[0]->id]['channels'])->toBe(['database', 'push']);
    });

    it('notifies participants and open invitees of a cancellation, with the reason in both languages', function () {
        $reason = CloseReason::factory()->kind(CloseReasonKind::Cancel)->create(['name' => ['ar' => 'تغيّر الاحتياج', 'en' => 'The requirement changed']]);
        $this->competition->update(['cancel_reason_id' => $reason->id, 'cancel_note' => 'دمج مع طلب آخر']);
        $openInvitee = S::team();
        $declinedInvitee = S::team();
        Invitation::factory()->forOrganization($openInvitee->organization)->viewed()->create(['competition_id' => $this->competition->id]);
        Invitation::factory()->forOrganization($declinedInvitee->organization)->declined()->create(['competition_id' => $this->competition->id]);

        S::fire(S::COMPETITIONS.'CompetitionCancelled', ['competition' => $this->competition->fresh(), 'actor' => S::actor()]);

        $sent = S::sent(CompetitionCancelledNotification::class);
        expect(array_keys($sent))->toBe(S::ids([...S::usersOf($this->bidderA), ...S::usersOf($this->bidderB), ...$openInvitee->active()]))
            ->and($sent[$openInvitee->owner->id]['channels'])->toBe(['database', 'push', 'mail'])
            ->and($sent[$openInvitee->owner->id]['notification']->params['reason'])->toBe([
                'ar' => 'تغيّر الاحتياج — دمج مع طلب آخر',
                'en' => 'The requirement changed — دمج مع طلب آخر',
            ]);
    });

    it('notifies participants of a close without award unless results are not published', function () {
        S::fire(S::COMPETITIONS.'CompetitionClosedWithoutAward', ['competition' => $this->competition, 'actor' => S::actor()]);
        expect(array_keys(S::sent(CompetitionNotAwardedNotification::class)))->toBe($this->participantUsers);

        $hidden = S::competition($this->issuer->organization, attributes: ['result_publication' => ResultPublication::None]);
        S::participant($hidden);
        S::fire(S::COMPETITIONS.'CompetitionClosedWithoutAward', ['competition' => $hidden, 'actor' => S::actor()]);

        Notification::assertSentTimes(CompetitionNotAwardedNotification::class, count($this->participantUsers));
    });
});

describe('comment.created', function () {
    it('sends a participant question to the issuer team', function () {
        $question = Comment::factory()->byParticipant($this->bidderA)->create();

        S::fire(S::COMPETITIONS.'CommentPosted', ['comment' => $question, 'actor' => S::actor()]);

        $sent = S::sent(CommentCreatedNotification::class);
        expect(array_keys($sent))->toBe(S::ids($this->issuer->active()))
            ->and(array_values($sent)[0]['notification']->route)->toBe('/competitions/'.$this->competition->public_id.'/qa');
    });

    it('sends an issuer reply to the organization that asked, and an issuer post to every participant, never to the author', function () {
        $question = Comment::factory()->byParticipant($this->bidderA)->create();
        $reply = Comment::factory()->replyTo($question)->create(['author_organization_id' => $this->issuer->organization->id, 'author_user_id' => $this->issuer->owner->id]);

        S::fire(S::COMPETITIONS.'CommentPosted', ['comment' => $reply, 'actor' => S::actor()]);
        expect(array_keys(S::sent(CommentCreatedNotification::class)))->toBe(S::ids(S::usersOf($this->bidderA)));

        $post = Comment::factory()->create(['competition_id' => $this->competition->id, 'author_user_id' => $this->issuer->owner->id]);
        S::fire(S::COMPETITIONS.'CommentPosted', ['comment' => $post, 'actor' => S::actor()]);

        Notification::assertSentTimes(CommentCreatedNotification::class, count(S::usersOf($this->bidderA)) + count($this->participantUsers));
        Notification::assertNotSentTo($this->issuer->owner, CommentCreatedNotification::class);
    });

    it('pushes at most once per 5 minutes per competition but keeps every in-app message', function () {
        $first = Comment::factory()->create(['competition_id' => $this->competition->id, 'author_user_id' => $this->issuer->owner->id]);
        $second = Comment::factory()->create(['competition_id' => $this->competition->id, 'author_user_id' => $this->issuer->owner->id]);
        $bidder = S::usersOf($this->bidderA)[0];

        S::fire(S::COMPETITIONS.'CommentPosted', ['comment' => $first, 'actor' => S::actor()]);
        expect(S::sent(CommentCreatedNotification::class)[$bidder->id]['channels'])->toBe(['database', 'push']);

        S::fire(S::COMPETITIONS.'CommentPosted', ['comment' => $second, 'actor' => S::actor()]);
        expect(S::sent(CommentCreatedNotification::class)[$bidder->id]['channels'])->toBe(['database']);
    });
});

describe('invitation responses', function () {
    it('tells the issuer team who joined and whether a pass covers the fees', function () {
        $participant = S::participant($this->competition);
        $participant->update(['entitlement_source' => 'sponsored_pass']);

        S::fire(S::COMPETITIONS.'InvitationJoined', ['invitation' => $participant->invitation, 'participant' => $participant->fresh(), 'actor' => S::actor()]);

        $sent = S::sent(InvitationJoinedNotification::class);
        expect(array_keys($sent))->toBe(S::ids($this->issuer->active()))
            ->and($sent[$this->issuer->owner->id]['channels'])->toBe(['database', 'push'])
            ->and($sent[$this->issuer->owner->id]['notification']->params)->toMatchArray([
                'organization_name' => $participant->organization->name,
                'sponsored' => true,
            ]);
    });

    it('tells the issuer team in-app who declined, by name when the organization is unknown', function () {
        $known = Invitation::factory()->forOrganization(Organization::factory()->create(['name' => 'مورد التقنية']))->declined()->create(['competition_id' => $this->competition->id]);
        $unknown = Invitation::factory()->declined()->create(['competition_id' => $this->competition->id, 'name' => 'أحمد الزهراني']);

        S::fire(S::COMPETITIONS.'InvitationDeclined', ['invitation' => $known]);
        expect(S::sent(InvitationDeclinedNotification::class)[$this->issuer->owner->id]['notification']->params['organization_name'])->toBe('مورد التقنية')
            ->and(S::sent(InvitationDeclinedNotification::class)[$this->issuer->owner->id]['channels'])->toBe(['database']);

        S::fire(S::COMPETITIONS.'InvitationDeclined', ['invitation' => $unknown]);
        expect(S::sent(InvitationDeclinedNotification::class)[$this->issuer->owner->id]['notification']->params['organization_name'])->toBe('أحمد الزهراني');
    });
});

it('never notifies inactive members', function () {
    S::fire(S::COMPETITIONS.'CompetitionClosed', ['competition' => $this->competition]);

    Notification::assertNotSentTo($this->issuer->inactive, CompetitionClosedNotification::class);
    expect(Competition::query()->count())->toBeGreaterThan(0);
});
