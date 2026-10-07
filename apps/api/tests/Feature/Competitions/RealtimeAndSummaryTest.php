<?php

declare(strict_types=1);

use App\Modules\Competitions\Broadcasting\CommentCreatedBroadcast;
use App\Modules\Competitions\Broadcasting\CompetitionUpdatedBroadcast;
use App\Modules\Competitions\Broadcasting\InvitationUpdatedBroadcast;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\CommentPosted;
use App\Modules\Competitions\Events\CompetitionUpdated;
use App\Modules\Competitions\Events\InvitationViewed;
use App\Modules\Competitions\Listeners\BroadcastCommentCreated;
use App\Modules\Competitions\Listeners\BroadcastCompetitionUpdated;
use App\Modules\Competitions\Listeners\BroadcastInvitationUpdated;
use App\Modules\Competitions\Mail\CompetitionInvitationMail;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Services\RulesSummary;
use App\Support\Auth\Actor;
use Illuminate\Support\Facades\Event;
use Tests\Support\Competitions\Fixtures;

describe('realtime broadcasts (§9.3)', function (): void {
    it('sends competition.updated to the issuer and every participant channel of a published competition', function (): void {
        Event::fake([CompetitionUpdatedBroadcast::class]);
        $competition = Competition::factory()->live()->create();
        [, $participantOrg] = Fixtures::participant($competition);

        app(BroadcastCompetitionUpdated::class)->handle(new CompetitionUpdated($competition, ['title'], Actor::system()));

        Event::assertDispatched(CompetitionUpdatedBroadcast::class, function (CompetitionUpdatedBroadcast $b) use ($competition, $participantOrg): bool {
            $channels = array_map(static fn ($c): string => $c->name, $b->broadcastOn());

            return $b->broadcastAs() === 'competition.updated'
                && $channels === ["private-competition.{$competition->public_id}", "private-competition.{$competition->public_id}.participant.{$participantOrg->public_id}"]
                && $b->broadcastWith()['fields'] === ['title'];
        });
    });

    it('stays silent for drafts', function (): void {
        Event::fake([CompetitionUpdatedBroadcast::class]);
        $competition = Competition::factory()->create();

        app(BroadcastCompetitionUpdated::class)->handle(new CompetitionUpdated($competition, ['title'], Actor::system()));

        Event::assertNotDispatched(CompetitionUpdatedBroadcast::class);
    });

    it('projects comment.created per audience without leaking participant names', function (): void {
        Event::fake([CommentCreatedBroadcast::class]);
        $competition = Competition::factory()->live()->create();
        [$asker, $askerOrg, $askerOwner] = Fixtures::participant($competition);
        [, $otherOrg] = Fixtures::participant($competition);
        $comment = Comment::factory()->create([
            'competition_id' => $competition->id,
            'author_user_id' => $askerOwner->id,
            'author_organization_id' => $askerOrg->id,
            'author_participant_id' => $asker->id,
            'is_issuer' => false,
        ]);

        app(BroadcastCommentCreated::class)->handle(new CommentPosted($comment, Actor::system()));

        Event::assertDispatchedTimes(CommentCreatedBroadcast::class, 3);
        Event::assertDispatched(CommentCreatedBroadcast::class, fn (CommentCreatedBroadcast $b): bool => $b->channel === "competition.{$competition->public_id}"
            && $b->broadcastWith()['author']['organization_name'] === $askerOrg->name);
        Event::assertDispatched(CommentCreatedBroadcast::class, fn (CommentCreatedBroadcast $b): bool => $b->channel === "competition.{$competition->public_id}.participant.{$askerOrg->public_id}"
            && $b->broadcastWith()['author'] === ['kind' => 'me']);
        Event::assertDispatched(CommentCreatedBroadcast::class, fn (CommentCreatedBroadcast $b): bool => $b->channel === "competition.{$competition->public_id}.participant.{$otherOrg->public_id}"
            && ! str_contains(json_encode($b->broadcastWith(), JSON_THROW_ON_ERROR), $askerOrg->name));
    });

    it('sends invitation.updated to the issuer channel', function (): void {
        Event::fake([InvitationUpdatedBroadcast::class]);
        $competition = Competition::factory()->scheduled()->create();
        [$invitation] = Fixtures::invitee($competition, status: InvitationStatus::Viewed);

        app(BroadcastInvitationUpdated::class)->handle(new InvitationViewed($invitation));

        Event::assertDispatched(InvitationUpdatedBroadcast::class, fn (InvitationUpdatedBroadcast $b): bool => $b->broadcastAs() === 'invitation.updated'
            && $b->broadcastOn()->name === "private-competition.{$competition->public_id}"
            && $b->broadcastWith()['id'] === $invitation->public_id
            && $b->broadcastWith()['status'] === 'viewed');
    });
});

describe('rules summary (§7.16)', function (): void {
    it('describes a live tender in Arabic and English, with the reserve for the issuer only', function (): void {
        $competition = Competition::factory()->make(['start_price_minor' => 25_000_000, 'reserve_price_minor' => 21_000_000, 'min_step_bps' => 50]);

        $ar = RulesSummary::lines($competition, 'ar');
        $en = RulesSummary::lines($competition, 'en');
        $issuer = RulesSummary::lines($competition, 'en', issuer: true);

        expect($ar[0])->toBe('مناقصة: العرض الأقل سعراً يتصدر.')
            ->and($en)->toContain('Tender: the lowest offer leads.', 'Ceiling price: SAR 250,000.00', 'Minimum improvement: 0.5%', 'Each new offer must improve on your previous offer.', 'Prices exclude VAT.')
            ->and(implode(' ', $en))->not->toContain('210,000')
            ->and($issuer)->toContain('The target price (SAR 210,000.00) is hidden from participants.')
            ->and(implode(' ', RulesSummary::lines($competition, 'ar', issuer: true)))->toContain('السعر المستهدف (')->not->toContain('التحفظي')
            ->and(count($ar))->toBe(count($en));
    });

    it('describes an auction and a sealed competition from their columns', function (): void {
        $auction = Competition::factory()->auction()->make(['start_price_minor' => 5_000_000, 'reserve_price_minor' => 6_000_000]);
        $sealed = Competition::factory()->sealed()->make();

        expect(RulesSummary::lines($auction, 'en'))->toContain('Auction: the highest offer leads.', 'Opening price: SAR 50,000.00', 'Minimum improvement: SAR 500.00', 'Each new offer must beat the leading offer.')
            ->and(RulesSummary::lines($auction, 'en', issuer: true))->toContain('The reserve price (SAR 60,000.00) is hidden from participants.')
            ->and(implode(' ', RulesSummary::lines($auction, 'ar', issuer: true)))->toContain('الحد الأدنى المقبول (')
            ->and(RulesSummary::lines($sealed, 'en'))->toContain('Participants see no rank and no prices.', 'The issuer may invite a shortlist to submit one best and final offer after closing.');
    });

    it('counts minutes and extensions with the Arabic plural forms of the tier presets', function (int $window, int $max, string $ar, string $en): void {
        $competition = Competition::factory()->make([
            'auto_extend_enabled' => true, 'auto_extend_window_seconds' => $window, 'auto_extend_by_seconds' => $window,
            'auto_extend_max' => $max, 'hard_stop_at' => null,
        ]);

        expect(RulesSummary::lines($competition, 'ar'))->toContain($ar)
            ->and(RulesSummary::lines($competition, 'en'))->toContain($en);
    })->with([
        'standard tier' => [180, 10, 'العروض التي تغيّر العرض المتصدر في آخر 3 دقائق تمدد الإغلاق 3 دقائق، بحد أقصى 10 مرات.', 'Offers that change the leading offer in the last 3 minutes extend closing by 3 minutes, up to 10 times.'],
        'protected tier' => [300, 20, 'العروض التي تغيّر العرض المتصدر في آخر 5 دقائق تمدد الإغلاق 5 دقائق، بحد أقصى 20 مرة.', 'Offers that change the leading offer in the last 5 minutes extend closing by 5 minutes, up to 20 times.'],
        'two and one' => [120, 1, 'العروض التي تغيّر العرض المتصدر في آخر دقيقتين تمدد الإغلاق دقيقتين، بحد أقصى مرة واحدة.', 'Offers that change the leading offer in the last 2 minutes extend closing by 2 minutes, up to 1 time.'],
        'eleven' => [660, 11, 'العروض التي تغيّر العرض المتصدر في آخر 11 دقيقة تمدد الإغلاق 11 دقيقة، بحد أقصى 11 مرة.', 'Offers that change the leading offer in the last 11 minutes extend closing by 11 minutes, up to 11 times.'],
    ]);
});

it('renders the invitation mail in both languages with the sponsored line and no amounts', function (): void {
    $mail = static fn (string $locale): CompetitionInvitationMail => new CompetitionInvitationMail(
        plainToken: 'tok_123', competitionTitle: 'توريد أجهزة', referenceNo: 'BAFO-T-2026-000001', direction: 'tender',
        issuerName: 'شركة المصدر', contactName: 'أحمد', closesAt: '9 نوفمبر 2026، 3:00 م', sponsored: true, mailLocale: $locale,
    );

    $ar = $mail('ar')->render();
    $en = $mail('en')->render();

    expect($ar)->toContain('تدعوك شركة المصدر للمشاركة في مناقصة', 'رسوم المشاركة مغطّاة', '/ar/invitations#t=tok_123')
        ->and($en)->toContain('invites you to take part in the tender', 'Participation fees are covered', '/en/invitations#t=tok_123&amp;action=decline')
        ->and($mail('en')->envelope()->subject)->toBe('Invitation to a tender: توريد أجهزة');
});

it('never turns user-entered names or titles in the invitation mail into links or formatting', function (): void {
    $html = (new CompetitionInvitationMail(
        plainToken: 'tok_123', competitionTitle: '[Pay here](https://evil.example/x) **urgent**', referenceNo: 'BAFO-T-2026-000001',
        direction: 'tender', issuerName: '<b>Acme</b>', contactName: '[Ali](https://evil.example/y)', closesAt: null,
        sponsored: false, mailLocale: 'en',
    ))->render();

    expect($html)->not->toContain('href="https://evil.example')
        ->and($html)->not->toContain('<strong>urgent</strong>')
        ->and($html)->not->toContain('<b>Acme</b>')
        ->and($html)->toContain('[Pay here](https://evil.example/x) **urgent**', '&lt;b&gt;Acme&lt;/b&gt;');
});
