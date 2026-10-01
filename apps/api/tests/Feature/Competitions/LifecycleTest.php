<?php

declare(strict_types=1);

use App\Modules\Catalog\Enums\CloseReasonKind;
use App\Modules\Catalog\Models\CloseReason;
use App\Modules\Competitions\Actions\CloseDueCompetition;
use App\Modules\Competitions\Actions\ExtendCompetition;
use App\Modules\Competitions\Actions\ForceCloseCompetition;
use App\Modules\Competitions\Contracts\CompetitionStateMachine;
use App\Modules\Competitions\Contracts\CompetitionTimingService;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\ExtensionKind;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\CompetitionCancelled;
use App\Modules\Competitions\Events\CompetitionClosed;
use App\Modules\Competitions\Events\CompetitionClosedWithoutAward;
use App\Modules\Competitions\Events\CompetitionClosingSoon;
use App\Modules\Competitions\Events\CompetitionExtended;
use App\Modules\Competitions\Events\CompetitionFinalWindowStarted;
use App\Modules\Competitions\Events\CompetitionOpened;
use App\Modules\Competitions\Events\CompetitionStatusChanged;
use App\Modules\Competitions\Events\InvitationsExpired;
use App\Modules\Competitions\Jobs\CloseCompetition;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\CompetitionExtension;
use App\Modules\Identity\Enums\OrgRole;
use App\Support\Audit\AuditLog;
use App\Support\Auth\Actor;
use App\Support\Auth\ActorType;
use App\Support\Auth\Channel;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Competitions\Fixtures;

beforeEach(function (): void {
    [$this->org, $this->owner] = Fixtures::issuer();
});

describe('state machine (§6.1)', function (): void {
    it('allows exactly the transitions of the table', function (CompetitionStatus $from, CompetitionStatus $to, bool $allowed): void {
        expect(app(CompetitionStateMachine::class)->allows($from, $to))->toBe($allowed);
    })->with([
        'T1' => [CompetitionStatus::Draft, CompetitionStatus::Scheduled, true],
        'T2' => [CompetitionStatus::Draft, CompetitionStatus::Live, true],
        'T3' => [CompetitionStatus::Scheduled, CompetitionStatus::Live, true],
        'T4' => [CompetitionStatus::Scheduled, CompetitionStatus::Cancelled, true],
        'T5' => [CompetitionStatus::Live, CompetitionStatus::Closed, true],
        'T6' => [CompetitionStatus::Live, CompetitionStatus::Cancelled, true],
        'T7' => [CompetitionStatus::Closed, CompetitionStatus::BafoRound, true],
        'T8' => [CompetitionStatus::BafoRound, CompetitionStatus::Closed, true],
        'T9' => [CompetitionStatus::BafoRound, CompetitionStatus::Cancelled, true],
        'T10' => [CompetitionStatus::Closed, CompetitionStatus::Awarded, true],
        'T11' => [CompetitionStatus::Awarded, CompetitionStatus::Closed, true],
        'T12' => [CompetitionStatus::Closed, CompetitionStatus::NotAwarded, true],
        'no early close' => [CompetitionStatus::Scheduled, CompetitionStatus::Closed, false],
        'closed cannot be cancelled' => [CompetitionStatus::Closed, CompetitionStatus::Cancelled, false],
        'cancelled is terminal' => [CompetitionStatus::Cancelled, CompetitionStatus::Live, false],
        'not awarded is terminal' => [CompetitionStatus::NotAwarded, CompetitionStatus::Closed, false],
        'draft cannot be cancelled' => [CompetitionStatus::Draft, CompetitionStatus::Cancelled, false],
    ]);

    it('sets the lifecycle stamp, dispatches CompetitionStatusChanged and refuses other transitions', function (): void {
        Event::fake([CompetitionStatusChanged::class]);
        $competition = Competition::factory()->closed()->create();
        $machine = app(CompetitionStateMachine::class);

        DB::transaction(fn () => $machine->transition($competition, CompetitionStatus::Awarded, Actor::system()));
        expect($competition->refresh()->awarded_at)->not->toBeNull();

        DB::transaction(fn () => $machine->transition($competition, CompetitionStatus::Closed, Actor::system()));
        expect($competition->refresh()->awarded_at)->toBeNull()
            ->and($competition->closed_at)->not->toBeNull();

        Event::assertDispatchedTimes(CompetitionStatusChanged::class, 2);

        expect(fn () => $machine->transition($competition, CompetitionStatus::Live, Actor::system()))
            ->toThrow(fn (ApiException $e) => expect($e->errorCode)->toBe('invalid_state_transition')
                ->and($e->status)->toBe(409)
                ->and($e->details)->toBe(['from' => 'closed', 'to' => 'live']));
    });
});

describe('CompetitionTimingService (§3.6)', function (): void {
    it('moves the close, bumps the count, records the extension and schedules the close job', function (): void {
        Event::fake([CompetitionExtended::class]);
        Queue::fake();
        $competition = Competition::factory()->live()->create();
        $previous = $competition->effective_close_at;
        $newClose = $previous->addMinutes(3);

        $extension = DB::transaction(fn () => app(CompetitionTimingService::class)
            ->extend($competition, $newClose, ExtensionKind::Auto, Actor::system(), 99));

        $competition->refresh();

        expect($competition->effective_close_at?->equalTo($newClose))->toBeTrue()
            ->and($competition->extension_count)->toBe(1)
            ->and($extension->kind)->toBe(ExtensionKind::Auto)
            ->and($extension->triggered_by_offer_id)->toBe(99)
            ->and($extension->previous_close_at->equalTo($previous))->toBeTrue();

        Event::assertDispatched(CompetitionExtended::class);
        Queue::assertPushed(CloseCompetition::class);
    });
});

describe('competitions:tick', function (): void {
    it('opens due scheduled competitions (T3)', function (): void {
        Event::fake([CompetitionOpened::class]);
        $due = Competition::factory()->scheduled()->create(['bidding_opens_at' => now()->subMinute()]);
        $later = Competition::factory()->scheduled()->create();

        $this->artisan('competitions:tick')->assertSuccessful();

        expect($due->refresh()->status)->toBe(CompetitionStatus::Live)
            ->and($due->opened_at)->not->toBeNull()
            ->and($later->refresh()->status)->toBe(CompetitionStatus::Scheduled);
        Event::assertDispatchedTimes(CompetitionOpened::class, 1);
    });

    it('announces the final window once', function (): void {
        Event::fake([CompetitionFinalWindowStarted::class]);
        $competition = Competition::factory()->live()->create();
        $competition->forceFill(['final_window_starts_at' => now()->subMinute()])->save();

        $this->artisan('competitions:tick')->assertSuccessful();
        $this->artisan('competitions:tick')->assertSuccessful();

        expect($competition->refresh()->final_window_started_at)->not->toBeNull();
        Event::assertDispatchedTimes(CompetitionFinalWindowStarted::class, 1);
    });

    it('announces each closing-soon threshold once', function (): void {
        Event::fake([CompetitionClosingSoon::class]);
        $competition = Competition::factory()->withoutFinalWindow()->live()->create();
        $competition->forceFill(['effective_close_at' => now()->addMinutes(8)])->save();

        $this->artisan('competitions:tick')->assertSuccessful();
        $this->travel(7)->minutes();
        $this->artisan('competitions:tick')->assertSuccessful();
        $this->artisan('competitions:tick')->assertSuccessful();

        expect($competition->refresh()->notified_thresholds)->toBe([10, 2]);
        Event::assertDispatchedTimes(CompetitionClosingSoon::class, 2);
        Event::assertDispatched(CompetitionClosingSoon::class, fn (CompetitionClosingSoon $e): bool => $e->minutes === 2);
    });

    it('dispatches the close job for due live competitions', function (): void {
        Queue::fake();
        $due = Competition::factory()->live()->create();
        $due->forceFill(['effective_close_at' => now()->subSecond()])->save();
        Competition::factory()->live()->create();

        $this->artisan('competitions:tick')->assertSuccessful();

        Queue::assertPushed(CloseCompetition::class, 1);
        Queue::assertPushed(CloseCompetition::class, fn (CloseCompetition $job): bool => $job->competitionId === $due->id);
    });

    it('expires pending invitations at the cut-off', function (): void {
        Event::fake([InvitationsExpired::class]);
        $competition = Competition::factory()->live()->create();
        [$sent] = Fixtures::invitee($competition);
        [$viewed] = Fixtures::invitee($competition, status: InvitationStatus::Viewed);
        [$declined] = Fixtures::invitee($competition, status: InvitationStatus::Declined);
        $competition->forceFill(['invitation_cutoff_at' => now()->subMinute()])->save();

        $this->artisan('competitions:tick')->assertSuccessful();

        expect($sent->refresh()->status)->toBe(InvitationStatus::Expired)
            ->and($viewed->refresh()->status)->toBe(InvitationStatus::Expired)
            ->and($sent->expired_at)->not->toBeNull()
            ->and($declined->refresh()->status)->toBe(InvitationStatus::Declined);
        Event::assertDispatched(InvitationsExpired::class, fn (InvitationsExpired $e): bool => count($e->invitationIds) === 2);
    });
});

describe('close (§7.8, T5)', function (): void {
    it('closes a due live competition, expires invitations and announces it', function (): void {
        Event::fake([CompetitionClosed::class, InvitationsExpired::class]);
        $competition = Competition::factory()->live()->create();
        [$pending] = Fixtures::invitee($competition);
        $closeAt = now()->subSecond();
        $competition->forceFill(['effective_close_at' => $closeAt])->save();

        CloseCompetition::dispatchSync($competition->id);

        $competition->refresh();

        expect($competition->status)->toBe(CompetitionStatus::Closed)
            ->and($competition->closed_at?->equalTo($closeAt))->toBeTrue()
            ->and($competition->offers_opened_at)->toBeNull()
            ->and($pending->refresh()->status)->toBe(InvitationStatus::Expired)
            ->and(AuditLog::query()->where('action', 'competition.closed')->where('organization_id', $competition->organization_id)->exists())->toBeTrue();
        Event::assertDispatched(CompetitionClosed::class);
    });

    it('unlocks a sealed competition at the close', function (): void {
        $competition = Competition::factory()->sealed()->live()->create();
        $competition->forceFill(['effective_close_at' => now()->subSecond()])->save();

        $result = app(CloseDueCompetition::class)->handle($competition->id);

        expect($result->closed)->toBeTrue()
            ->and($competition->refresh()->offers_opened_at)->not->toBeNull();
    });

    it('does nothing before the (extended) close and reports when to retry', function (): void {
        $competition = Competition::factory()->live()->create();

        $result = app(CloseDueCompetition::class)->handle($competition->id);

        expect($result->closed)->toBeFalse()
            ->and($result->notDueUntil?->equalTo($competition->effective_close_at))->toBeTrue()
            ->and($competition->refresh()->status)->toBe(CompetitionStatus::Live);
    });

    it('is idempotent once closed', function (): void {
        $competition = Competition::factory()->closed()->create();

        expect(app(CloseDueCompetition::class)->handle($competition->id)->closed)->toBeFalse();
    });

    it('lets an admin force-close with an admin extension row', function (): void {
        Event::fake([CompetitionClosed::class]);
        $competition = Competition::factory()->live()->create();
        $admin = new Actor(ActorType::Admin, 7, null, null, null, 7, Channel::Admin, label: 'Admin');

        app(ForceCloseCompetition::class)->handle($competition, 'Technical incident', $admin);

        expect($competition->refresh()->status)->toBe(CompetitionStatus::Closed)
            ->and(CompetitionExtension::query()->where('competition_id', $competition->id)->where('kind', 'admin')->where('actor_admin_id', 7)->exists())->toBeTrue();
        Event::assertDispatched(CompetitionClosed::class);
    });
});

describe('POST /competitions/{competition}/extend (§7.17)', function (): void {
    it('extends a live competition and shifts the dependent times', function (): void {
        Event::fake([CompetitionExtended::class]);
        $this->travelTo(now()->startOfSecond());
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        $newClose = $competition->effective_close_at->addHour();
        $hardStop = $competition->hard_stop_at;
        $finalWindow = $competition->final_window_starts_at;
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/extend", [
            'new_close_at' => $newClose->toIso8601String(),
            'reason' => 'طلب المتنافسين وقتا إضافيا',
        ])->assertOk()->assertJsonPath('data.schedule.extension_count', 1);

        $competition->refresh();

        expect($competition->effective_close_at?->equalTo($newClose))->toBeTrue()
            ->and($competition->hard_stop_at?->equalTo($hardStop->addHour()))->toBeTrue()
            ->and($competition->final_window_starts_at?->equalTo($finalWindow->addHour()))->toBeTrue()
            ->and(CompetitionExtension::query()->where('competition_id', $competition->id)->where('kind', 'manual')->value('reason'))->toBe('طلب المتنافسين وقتا إضافيا');
        Event::assertDispatched(CompetitionExtended::class, fn (CompetitionExtended $e): bool => $e->extension->kind === ExtensionKind::Manual);
    });

    it('returns 422 extend_invalid below the minimum extension', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/extend", [
            'new_close_at' => $competition->effective_close_at->addMinutes(2)->toIso8601String(),
            'reason' => 'سبب كاف',
        ])->assertStatus(422)
            ->assertJsonPath('code', 'extend_invalid')
            ->assertJsonValidationErrors(['new_close_at'])
            ->assertJsonPath('details.min_new_close_at', fn (?string $v): bool => $v !== null);
    });

    it('returns 409 unless live, and 422 on a short reason', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/extend", [
            'new_close_at' => now()->addDays(5)->toIso8601String(),
            'reason' => 'سبب كاف',
        ])->assertStatus(409)->assertJsonPath('code', 'invalid_state_transition');

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/extend", ['new_close_at' => 'x', 'reason' => 'no'])
            ->assertStatus(422)->assertJsonValidationErrors(['new_close_at', 'reason']);
    });
});

describe('POST /competitions/{competition}/cancel', function (): void {
    it('cancels a scheduled competition with a reason and expires its invitations', function (): void {
        Event::fake([CompetitionCancelled::class]);
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        [$invitation] = Fixtures::invitee($competition);
        $reason = CloseReason::factory()->kind(CloseReasonKind::Cancel)->create();
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/cancel", ['close_reason_id' => $reason->public_id])
            ->assertOk()
            ->assertJsonPath('data.status', 'cancelled')
            ->assertJsonPath('data.cancellation.reason.code', $reason->code)
            ->assertJsonPath('data.permissions.can_cancel', false);

        $competition->refresh();

        expect($competition->cancel_reason_id)->toBe($reason->id)
            ->and($competition->cancelled_by_user_id)->toBe($this->owner->id)
            ->and($invitation->refresh()->status)->toBe(InvitationStatus::Expired);
        Event::assertDispatched(CompetitionCancelled::class);
    });

    it('requires the note of an "Other" reason and a reason of the cancel kind', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        $other = CloseReason::factory()->kind(CloseReasonKind::Cancel)->requiresNote()->create();
        $wrongKind = CloseReason::factory()->kind(CloseReasonKind::NotAwarded)->create();
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/cancel", ['close_reason_id' => $other->public_id])
            ->assertStatus(422)->assertJsonValidationErrors(['note']);
        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/cancel", ['close_reason_id' => $wrongKind->public_id])
            ->assertStatus(422)->assertJsonValidationErrors(['close_reason_id']);
        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/cancel", ['close_reason_id' => $other->public_id, 'note' => 'ميزانية'])
            ->assertOk();
    });

    it('returns 409 once closed (no early close, no cancel after close)', function (): void {
        $competition = Competition::factory()->closed()->create(['organization_id' => $this->org->id]);
        $reason = CloseReason::factory()->kind(CloseReasonKind::Cancel)->create();
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/cancel", ['close_reason_id' => $reason->public_id])
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_state_transition');
    });
});

describe('POST /competitions/{competition}/close (close without award, T12)', function (): void {
    it('closes without award with a reason', function (): void {
        Event::fake([CompetitionClosedWithoutAward::class]);
        $competition = Competition::factory()->closed()->create(['organization_id' => $this->org->id]);
        $reason = CloseReason::factory()->kind(CloseReasonKind::NotAwarded)->create();
        Fixtures::signIn($this->owner);

        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/close", ['close_reason_id' => $reason->public_id, 'note' => 'الأسعار أعلى من الميزانية'])
            ->assertOk()
            ->assertJsonPath('data.status', 'not_awarded')
            ->assertJsonPath('data.not_awarded.note', 'الأسعار أعلى من الميزانية');

        expect($competition->refresh()->not_awarded_at)->not->toBeNull();
        Event::assertDispatched(CompetitionClosedWithoutAward::class);
    });

    it('requires competitions.award and a closed competition', function (): void {
        $competition = Competition::factory()->closed()->create(['organization_id' => $this->org->id]);
        $reason = CloseReason::factory()->kind(CloseReasonKind::NotAwarded)->create();

        Fixtures::signIn(Fixtures::member($this->org, OrgRole::Admin, canAward: false));
        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/close", ['close_reason_id' => $reason->public_id])->assertForbidden();

        Fixtures::signIn(Fixtures::member($this->org, OrgRole::Member, canAward: true));
        $this->postJson("/api/app/v1/competitions/{$competition->public_id}/close", ['close_reason_id' => $reason->public_id])->assertOk();

        $live = Competition::factory()->live()->create(['organization_id' => $this->org->id]);
        $this->postJson("/api/app/v1/competitions/{$live->public_id}/close", ['close_reason_id' => $reason->public_id])
            ->assertStatus(409)->assertJsonPath('code', 'invalid_state_transition');
    });
});

it('keeps the close time of the first close when the BAFO round ends (T8)', function (): void {
    $competition = Competition::factory()->inBafoRound()->create();
    $closedAt = $competition->closed_at;

    DB::transaction(fn () => app(CompetitionStateMachine::class)->transition($competition, CompetitionStatus::Closed, Actor::system()));

    expect($competition->refresh()->closed_at?->equalTo($closedAt))->toBeTrue();
});

it('records an admin extension with the admin actor', function (): void {
    $competition = Competition::factory()->live()->create();
    $admin = new Actor(ActorType::Admin, 9, null, null, null, 9, Channel::Admin, label: 'Admin');

    app(ExtendCompetition::class)
        ->handle($competition, $competition->effective_close_at->addMinutes(30), 'Platform incident', $admin);

    expect(CompetitionExtension::query()->where('competition_id', $competition->id)->where('kind', 'admin')->where('actor_admin_id', 9)->exists())->toBeTrue();
});

it('schedules competitions:tick every ten seconds', function (): void {
    $this->artisan('schedule:list')->expectsOutputToContain('competitions:tick')->assertSuccessful();
});
