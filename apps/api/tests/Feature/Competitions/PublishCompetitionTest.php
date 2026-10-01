<?php

declare(strict_types=1);

use App\Modules\Billing\Contracts\SponsorshipService;
use App\Modules\Catalog\Models\Category;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\CompetitionOpened;
use App\Modules\Competitions\Events\CompetitionPublished;
use App\Modules\Competitions\Events\CompetitionStatusChanged;
use App\Modules\Competitions\Events\InvitationSent;
use App\Modules\Competitions\Jobs\CloseCompetition;
use App\Modules\Competitions\Mail\CompetitionInvitationMail;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Support\Audit\AuditLog;
use App\Support\Exceptions\ApiException;
use App\Support\Settings\Settings;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\Support\Competitions\Fixtures;

beforeEach(function (): void {
    Mail::fake();
    [$this->org, $this->owner] = Fixtures::issuer();
});

function publishUrl(Competition $competition): string
{
    return "/api/app/v1/competitions/{$competition->public_id}/publish";
}

it('publishes a draft to scheduled with the derived times, a reference and sent invitations', function (): void {
    Event::fake([CompetitionPublished::class, InvitationSent::class, CompetitionStatusChanged::class]);
    Queue::fake();
    $competition = Fixtures::draft($this->org, $this->owner);
    [$first, $second] = Fixtures::draftInvitations($competition);
    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))
        ->assertOk()
        ->assertJsonPath('data.status', 'scheduled')
        ->assertJsonPath('data.schedule.hard_stop_at', fn (?string $v): bool => $v !== null)
        ->assertJsonPath('data.permissions.can_publish', false)
        ->assertJsonPath('data.permissions.can_cancel', true);

    $competition->refresh();
    $close = $competition->scheduled_close_at;

    expect($competition->status)->toBe(CompetitionStatus::Scheduled)
        ->and($competition->reference_no)->toMatch('/^BAFO-T-\d{4}-\d{6}$/')
        ->and($competition->published_at)->not->toBeNull()
        ->and($competition->effective_close_at?->equalTo($close))->toBeTrue()
        ->and($competition->hard_stop_at?->equalTo($close?->addSeconds(10 * 180)))->toBeTrue()
        ->and($competition->final_window_starts_at?->equalTo($close?->subMinutes(60)))->toBeTrue()
        ->and($competition->invitation_cutoff_at?->equalTo($competition->final_window_starts_at))->toBeTrue()
        ->and($first->refresh()->status)->toBe(InvitationStatus::Sent)
        ->and($first->token_hash)->toHaveLength(64)
        ->and($second->refresh()->sent_at)->not->toBeNull()
        ->and(AuditLog::query()->where('action', 'competition.published')->exists())->toBeTrue();

    Event::assertDispatched(CompetitionPublished::class);
    Event::assertDispatchedTimes(InvitationSent::class, 2);
    Event::assertDispatched(CompetitionStatusChanged::class, fn (CompetitionStatusChanged $e): bool => $e->from === CompetitionStatus::Draft && $e->to === CompetitionStatus::Scheduled);
    Mail::assertQueued(CompetitionInvitationMail::class, 2);
    Queue::assertPushed(CloseCompetition::class, fn (CloseCompetition $job): bool => $job->competitionId === $competition->id);
});

it('puts the token in the URL fragment of the invitation mail only', function (): void {
    $competition = Fixtures::draft($this->org, $this->owner);
    [$invitation] = Fixtures::draftInvitations($competition);
    Fixtures::draftInvitations($competition, 1);
    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))->assertOk();

    Mail::assertQueued(CompetitionInvitationMail::class, function (CompetitionInvitationMail $mail) use ($invitation): bool {
        $html = $mail->render();

        return $mail->hasTo($invitation->email)
            && hash('sha256', $mail->plainToken) === $invitation->refresh()->token_hash
            && str_contains($html, '/invitations#t='.$mail->plainToken)
            && str_contains($html, 'action=decline');
    });
});

it('opens immediately when the opening time is not set (T2)', function (): void {
    Event::fake([CompetitionOpened::class, CompetitionPublished::class]);
    $competition = Fixtures::draft($this->org, $this->owner, ['bidding_opens_at' => null, 'scheduled_close_at' => now()->addDays(2)]);
    Fixtures::draftInvitations($competition);
    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))->assertOk()->assertJsonPath('data.status', 'live')->assertJsonPath('data.phase', 'initial');

    $competition->refresh();

    expect($competition->status)->toBe(CompetitionStatus::Live)
        ->and($competition->opened_at)->not->toBeNull()
        ->and($competition->bidding_opens_at)->not->toBeNull();

    Event::assertDispatched(CompetitionOpened::class);
});

it('returns 409 invalid_state_transition when not a draft', function (): void {
    $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))
        ->assertStatus(409)
        ->assertJsonPath('code', 'invalid_state_transition')
        ->assertJsonPath('details.from', 'scheduled');
});

it('returns 403 issuer_plan_required when the plan lapsed (R20)', function (): void {
    [$org, $owner] = Fixtures::issuer(plan: false);
    $competition = Fixtures::draft($org, $owner);
    Fixtures::draftInvitations($competition);
    Fixtures::signIn($owner);

    $this->postJson(publishUrl($competition))->assertForbidden()->assertJsonPath('code', 'issuer_plan_required');
});

it('runs the publish checks all together', function (): void {
    $other = Category::factory()->other()->create();
    $competition = Fixtures::draft($this->org, $this->owner, [
        'description' => null,
        'category_id' => $other->id,
        'category_other_text' => null,
        'bidding_opens_at' => now()->subHours(2),
        'scheduled_close_at' => now()->addHour(),
    ]);
    Fixtures::draftInvitations($competition);
    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))
        ->assertStatus(422)
        ->assertJsonPath('code', 'validation_failed')
        ->assertJsonValidationErrors(['description', 'category_other_text', 'bidding_opens_at']);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Draft);
});

it('requires an opening price for an auction (R5) and a bidding period long enough (R16)', function (): void {
    $this->org->forceFill(['auction_enabled' => true])->save();
    $competition = Competition::factory()->auction()->create([
        'organization_id' => $this->org->id,
        'created_by_user_id' => $this->owner->id,
        'start_price_minor' => null,
        'min_step_minor' => null,
        'bidding_opens_at' => now()->addHour(),
        'scheduled_close_at' => now()->addHour()->addMinutes(5),
    ]);
    Fixtures::draftInvitations($competition);
    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['rules.start_price_minor', 'scheduled_close_at']);
});

it('rejects a final window as long as the bidding period (R11)', function (): void {
    $opens = now()->addHour()->startOfMinute();
    $competition = Fixtures::draft($this->org, $this->owner, ['bidding_opens_at' => $opens, 'scheduled_close_at' => $opens->addMinutes(60)]);
    Fixtures::draftInvitations($competition);
    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))->assertStatus(422)->assertJsonValidationErrors(['rules.final_window_minutes']);
});

it('returns 422 min_participants_not_met with details (R17)', function (): void {
    $competition = Fixtures::draft($this->org, $this->owner, ['min_participants' => 3]);
    Fixtures::draftInvitations($competition, 2);
    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))
        ->assertStatus(422)
        ->assertJsonPath('code', 'min_participants_not_met')
        ->assertJsonPath('details.required', 3)
        ->assertJsonPath('details.current', 2);
});

it('returns 409 live_event_capacity_reached at the cap (R19)', function (): void {
    app(Settings::class)->set('bidding.max_concurrent_live', 1, null);
    $competition = Fixtures::draft($this->org, $this->owner);
    Fixtures::draftInvitations($competition);
    Competition::factory()->live()->create(['bidding_opens_at' => now(), 'scheduled_close_at' => now()->addDays(5), 'hard_stop_at' => now()->addDays(5)]);
    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))->assertStatus(409)->assertJsonPath('code', 'live_event_capacity_reached');
});

it('returns 409 sponsorship_payment_required from Billing and writes nothing (R21)', function (): void {
    $competition = Fixtures::draft($this->org, $this->owner);
    [$invitation] = Fixtures::draftInvitations($competition);
    Fixtures::draftInvitations($competition, 1);

    app()->instance(SponsorshipService::class, new class implements SponsorshipService
    {
        public function reserveForPublish(Competition $c): void
        {
            throw new ApiException('sponsorship_payment_required', status: 409, details: ['quote' => ['passes_to_buy' => 2]]);
        }

        public function reserveForInvitations(Competition $c, Collection $invitations): void {}
    });

    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))
        ->assertStatus(409)
        ->assertJsonPath('code', 'sponsorship_payment_required')
        ->assertJsonPath('details.quote.passes_to_buy', 2);

    expect($competition->refresh()->status)->toBe(CompetitionStatus::Draft)
        ->and($invitation->refresh()->status)->toBe(InvitationStatus::Draft);
    Mail::assertNothingQueued();
});

it('returns 403 to a member without manage and 404 to a stranger', function (): void {
    $competition = Fixtures::draft($this->org, $this->owner);
    Fixtures::signIn(Fixtures::member($this->org));

    $this->postJson(publishUrl($competition))->assertForbidden();

    [, $stranger] = Fixtures::supplier();
    Fixtures::signIn($stranger);

    $this->postJson(publishUrl($competition))->assertNotFound();
});

it('binds the organization of an invitee that registered since the draft invitation', function (): void {
    $competition = Fixtures::draft($this->org, $this->owner);
    [$supplier, $supplierOwner] = Fixtures::supplier();
    $invitation = Invitation::factory()->create(['competition_id' => $competition->id, 'email' => $supplierOwner->email]);
    Fixtures::draftInvitations($competition, 1);
    Fixtures::signIn($this->owner);

    $this->postJson(publishUrl($competition))->assertOk();

    expect($invitation->refresh()->organization_id)->toBe($supplier->id);
});
