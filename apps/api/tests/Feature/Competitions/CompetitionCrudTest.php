<?php

declare(strict_types=1);

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\CompetitionPreset;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\Direction;
use App\Modules\Competitions\Enums\Format;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\CompetitionCreated;
use App\Modules\Competitions\Events\CompetitionUpdated;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Identity\Enums\OrgRole;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Facades\Event;
use Tests\Support\Competitions\Fixtures;

beforeEach(function (): void {
    $this->category = Category::factory()->create();
    $this->region = Region::factory()->create();
    [$this->org, $this->owner] = Fixtures::issuer();
});

describe('POST /competitions', function (): void {
    it('creates a draft with typed rules and returns the issuer projection', function (): void {
        Event::fake([CompetitionCreated::class]);
        Fixtures::signIn($this->owner);

        $response = $this->postJson('/api/app/v1/competitions', Fixtures::createBody($this->category, $this->region, [
            'rules' => ['start_price_minor' => 25_000_000, 'reserve_price_minor' => 21_000_000, 'min_step_bps' => 50, 'must_beat' => 'own'],
        ]));

        $response->assertCreated()
            ->assertJsonStructure(['data' => [
                'id', 'reference_no', 'title', 'description', 'direction', 'format', 'status', 'phase', 'currency', 'price_basis',
                'category' => ['id', 'code', 'name', 'is_other', 'auction_allowed'], 'region' => ['id', 'code', 'name'],
                'rules' => ['start_price_minor', 'reserve_price_minor', 'min_step_minor', 'min_step_bps', 'amount_granularity_minor',
                    'must_beat', 'rank_visibility', 'show_prices', 'auto_extend' => ['enabled', 'window_seconds', 'by_seconds', 'max_extensions'],
                    'final_window_minutes', 'bafo_round' => ['enabled', 'duration_minutes'], 'min_participants', 'result_publication'],
                'rules_summary', 'schedule' => ['bidding_opens_at', 'scheduled_close_at', 'effective_close_at', 'invitation_cutoff_at'],
                'issuer' => ['id', 'name', 'logo_url', 'verified'], 'counts', 'leading_amount_minor', 'sponsorship', 'bafo_round',
                'award', 'cancellation', 'not_awarded', 'created_by' => ['type', 'id', 'name'], 'source', 'permissions',
                'viewer_role', 'live', 'server_time', 'created_at', 'updated_at',
            ], 'meta' => ['server_time']])
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.reference_no', null)
            ->assertJsonPath('data.viewer_role', 'issuer')
            ->assertJsonPath('data.rules.reserve_price_minor', 21_000_000)
            ->assertJsonPath('data.rules.must_beat', 'own')
            ->assertJsonPath('data.rules.rank_visibility', 'leading_flag')
            ->assertJsonPath('data.permissions.can_publish', true)
            ->assertJsonPath('data.permissions.can_join', false)
            ->assertJsonPath('data.created_by.type', 'user')
            ->assertJsonPath('data.source', 'web');

        $competition = Competition::query()->where('public_id', $response->json('data.id'))->firstOrFail();

        expect($competition->organization_id)->toBe($this->org->id)
            ->and($competition->created_by_user_id)->toBe($this->owner->id)
            ->and($competition->direction)->toBe(Direction::Tender)
            ->and(AuditLog::query()->where('action', 'competition.created')->where('organization_id', $this->org->id)->exists())->toBeTrue();

        Event::assertDispatched(CompetitionCreated::class);
    });

    it('fills rules from a matching preset and the column defaults', function (): void {
        $preset = CompetitionPreset::factory()->create(['code' => 'standard_live_tender']);
        Fixtures::signIn($this->owner);

        $body = Fixtures::createBody($this->category, $this->region, ['preset_code' => $preset->code, 'rules' => ['start_price_minor' => 1_000_000]]);

        $this->postJson('/api/app/v1/competitions', $body)
            ->assertCreated()
            ->assertJsonPath('data.preset_code', 'standard_live_tender')
            ->assertJsonPath('data.rules.min_step_bps', 50)
            ->assertJsonPath('data.rules.auto_extend.enabled', true)
            ->assertJsonPath('data.rules.final_window_minutes', 60);
    });

    it('rejects a preset of another type', function (): void {
        $preset = CompetitionPreset::factory()->create(['direction' => Direction::Auction]);
        Fixtures::signIn($this->owner);

        $this->postJson('/api/app/v1/competitions', Fixtures::createBody($this->category, $this->region, ['preset_code' => $preset->code]))
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['preset_code']);
    });

    it('defaults a sealed competition to no rank and no must-beat rule', function (): void {
        Fixtures::signIn($this->owner);

        $this->postJson('/api/app/v1/competitions', Fixtures::createBody($this->category, $this->region, [
            'format' => 'sealed',
            'rules' => ['start_price_minor' => 1_000_000],
        ]))
            ->assertCreated()
            ->assertJsonPath('data.format', 'sealed')
            ->assertJsonPath('data.rules.must_beat', null)
            ->assertJsonPath('data.rules.rank_visibility', 'none');
    });

    it('returns 401 without a token', function (): void {
        $this->postJson('/api/app/v1/competitions', [])->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    });

    it('returns 403 issuer_plan_required without an active plan', function (): void {
        [, $owner] = Fixtures::issuer(plan: false);
        Fixtures::signIn($owner);

        $this->postJson('/api/app/v1/competitions', Fixtures::createBody($this->category, $this->region))
            ->assertForbidden()
            ->assertJsonPath('code', 'issuer_plan_required');
    });

    it('returns 403 auction_not_enabled when the organization flag is off', function (): void {
        Fixtures::signIn($this->owner);

        $this->postJson('/api/app/v1/competitions', Fixtures::createBody($this->category, $this->region, [
            'direction' => 'auction',
            'rules' => ['start_price_minor' => 100_000, 'must_beat' => 'best', 'show_prices' => true],
        ]))->assertForbidden()->assertJsonPath('code', 'auction_not_enabled');
    });

    it('rejects an auction in a category that does not allow auctions (R14)', function (): void {
        $this->org->forceFill(['auction_enabled' => true])->save();
        $category = Category::factory()->auctionNotAllowed()->create();
        Fixtures::signIn($this->owner);

        $this->postJson('/api/app/v1/competitions', Fixtures::createBody($category, $this->region, [
            'direction' => 'auction',
            'rules' => ['start_price_minor' => 100_000, 'must_beat' => 'best', 'show_prices' => true],
        ]))->assertStatus(422)->assertJsonValidationErrors(['category_id']);
    });

    it('validates the request fields', function (): void {
        Fixtures::signIn($this->owner);

        $this->postJson('/api/app/v1/competitions', ['title' => str_repeat('x', 201), 'direction' => 'lottery'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors(['title', 'category_id', 'region_id', 'direction', 'format']);
    });

    it('applies the save rules', function (array $rules, string $field): void {
        Fixtures::signIn($this->owner);

        $this->postJson('/api/app/v1/competitions', Fixtures::createBody($this->category, $this->region, ['rules' => $rules]))
            ->assertStatus(422)
            ->assertJsonValidationErrors([$field]);
    })->with([
        'R2 live needs must_beat' => [['must_beat' => null], 'rules.must_beat'],
        'R3 best needs prices' => [['must_beat' => 'best', 'show_prices' => false], 'rules.must_beat'],
        'R4 amount publication needs prices' => [['result_publication' => 'outcome_and_amount'], 'rules.result_publication'],
        'R6 tender reserve above ceiling' => [['start_price_minor' => 100_000, 'reserve_price_minor' => 200_000], 'rules.reserve_price_minor'],
        'R7 one step kind' => [['min_step_minor' => 1_000, 'min_step_bps' => 50], 'rules.min_step_bps'],
        'R7 bps range' => [['min_step_bps' => 6_000], 'rules.min_step_bps'],
        'R8 granularity' => [['start_price_minor' => 100_050], 'rules.start_price_minor'],
        'R10 window bounds' => [['auto_extend' => ['enabled' => true, 'window_seconds' => 10, 'by_seconds' => 180, 'max_extensions' => 5]], 'rules.auto_extend.window_seconds'],
        'R11 final window bounds' => [['final_window_minutes' => 5], 'rules.final_window_minutes'],
        'R12 BAFO duration bounds' => [['bafo_round' => ['enabled' => true, 'duration_minutes' => 5]], 'rules.bafo_round.duration_minutes'],
        'R13 participants bounds' => [['min_participants' => 0], 'rules.min_participants'],
    ]);

    it('rejects a close before the opening (R16 at save)', function (): void {
        Fixtures::signIn($this->owner);

        $this->postJson('/api/app/v1/competitions', Fixtures::createBody($this->category, $this->region, [
            'bidding_opens_at' => now()->addDays(3)->toIso8601String(),
            'scheduled_close_at' => now()->addDays(2)->toIso8601String(),
        ]))->assertStatus(422)->assertJsonValidationErrors(['scheduled_close_at']);
    });

    it('requires the category text for the Other category', function (): void {
        $other = Category::factory()->other()->create();
        Fixtures::signIn($this->owner);

        $this->postJson('/api/app/v1/competitions', Fixtures::createBody($other, $this->region))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['category_other_text']);
    });
});

describe('GET /competitions/{competition}', function (): void {
    it('shows the issuer projection to any member of the issuer', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner, ['reserve_price_minor' => 50_000]);
        $member = Fixtures::member($this->org);
        Fixtures::signIn($member);

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_role', 'issuer')
            ->assertJsonPath('data.rules.reserve_price_minor', 50_000)
            ->assertJsonPath('data.permissions.can_edit', false)
            ->assertJsonPath('data.permissions.can_publish', false);
    });

    it('lets the creating member manage its own competition', function (): void {
        $member = Fixtures::member($this->org);
        $competition = Fixtures::draft($this->org, $member);
        Fixtures::signIn($member);

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}")
            ->assertOk()
            ->assertJsonPath('data.permissions.can_edit', true)
            ->assertJsonPath('data.permissions.can_publish', true);
    });

    it('hides drafts from everyone but the issuer', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        [, , $inviteeOwner] = Fixtures::invitee($competition, status: InvitationStatus::Draft);
        Fixtures::signIn($inviteeOwner);

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}")->assertNotFound()->assertJsonPath('code', 'not_found');
    });

    it('returns 404 for an organization with no view, and for malformed ids', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
        [, $stranger] = Fixtures::supplier();
        Fixtures::signIn($stranger);

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}")->assertNotFound();
        $this->getJson('/api/app/v1/competitions/not-a-ulid')->assertNotFound();
    });

    it('shows the teaser to an invitee and marks the invitation viewed', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'reserve_price_minor' => 50_000]);
        [$invitation, , $inviteeOwner] = Fixtures::invitee($competition);
        Fixtures::signIn($inviteeOwner);

        $response = $this->getJson("/api/app/v1/competitions/{$competition->public_id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_role', 'invitee')
            ->assertJsonPath('data.invitation.id', $invitation->public_id)
            ->assertJsonPath('data.invitation.status', 'viewed')
            ->assertJsonPath('data.permissions.can_decline', true)
            ->assertJsonStructure(['data' => ['access' => ['state', 'coverage', 'sponsor_name', 'join_deadline'], 'invitation_documents', 'schedule' => ['invitation_cutoff_at']]]);

        expect($response->json('data'))->not->toHaveKeys(['counts', 'description', 'leading_amount_minor', 'created_by'])
            ->and($response->json('data.rules'))->not->toHaveKey('reserve_price_minor')
            ->and($invitation->refresh()->status)->toBe(InvitationStatus::Viewed);
    });

    it('shows the participant projection without issuer-only fields', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id, 'reserve_price_minor' => 50_000]);
        [$participant, , $participantOwner] = Fixtures::participant($competition);
        Fixtures::signIn($participantOwner);

        $response = $this->getJson("/api/app/v1/competitions/{$competition->public_id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_role', 'participant')
            ->assertJsonPath('data.participation.participant_id', $participant->public_id)
            ->assertJsonPath('data.participation.alias_no', $participant->alias_no)
            ->assertJsonPath('data.result.outcome', null)
            ->assertJsonPath('data.permissions.can_edit', false)
            ->assertJsonStructure(['data' => ['access' => ['state', 'coverage'], 'live']]);

        $json = json_encode($response->json('data'), JSON_THROW_ON_ERROR);

        expect($response->json('data'))->not->toHaveKeys(['counts', 'leading_amount_minor', 'sponsorship', 'award', 'created_by', 'source'])
            ->and($response->json('data.rules'))->not->toHaveKey('reserve_price_minor')
            ->and($json)->not->toContain('50000');
    });
});

describe('PATCH /competitions/{competition}', function (): void {
    it('updates every field of a draft and announces the change', function (): void {
        Event::fake([CompetitionUpdated::class]);
        $competition = Fixtures::draft($this->org, $this->owner);
        Fixtures::signIn($this->owner);

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", [
            'title' => 'عنوان جديد',
            'format' => 'sealed',
            'rules' => ['must_beat' => null, 'rank_visibility' => 'none', 'auto_extend' => ['enabled' => false], 'final_window_minutes' => null, 'min_step_bps' => null],
        ])->assertOk()
            ->assertJsonPath('data.title', 'عنوان جديد')
            ->assertJsonPath('data.format', 'sealed')
            ->assertJsonPath('data.rules.auto_extend.window_seconds', null);

        expect($competition->refresh()->format)->toBe(Format::Sealed);
        Event::assertDispatched(CompetitionUpdated::class, fn (CompetitionUpdated $e): bool => in_array('title', $e->fields, true) && in_array('rules', $e->fields, true));
    });

    it('applies the save rules to the merged draft', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        Fixtures::signIn($this->owner);

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", ['format' => 'sealed'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['rules.must_beat', 'rules.rank_visibility', 'rules.auto_extend.enabled', 'rules.final_window_minutes']);
    });

    it('refuses rules on a scheduled competition with competition_not_editable', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", ['title' => 'x', 'rules' => ['show_prices' => true]])
            ->assertStatus(409)
            ->assertJsonPath('code', 'competition_not_editable')
            ->assertJsonPath('details.fields', ['rules']);
    });

    it('reschedules a scheduled competition and recomputes the derived times', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);
        $opens = now()->addDays(2)->startOfHour();

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", [
            'bidding_opens_at' => $opens->toIso8601String(),
            'scheduled_close_at' => $opens->addDays(3)->toIso8601String(),
        ])->assertOk();

        $competition->refresh();

        expect($competition->effective_close_at?->equalTo($opens->addDays(3)))->toBeTrue()
            ->and($competition->final_window_starts_at?->equalTo($opens->addDays(3)->subHour()))->toBeTrue()
            ->and($competition->invitation_cutoff_at?->equalTo($opens->addDays(3)->subHour()))->toBeTrue();
    });

    it('re-checks the schedule of a scheduled competition at publish strictness', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", ['bidding_opens_at' => now()->subHour()->toIso8601String()])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['bidding_opens_at']);
    });

    it('allows only the title and description while live', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", ['description' => 'وصف محدث'])->assertOk();
        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", ['scheduled_close_at' => now()->addDays(5)->toIso8601String()])
            ->assertStatus(409)
            ->assertJsonPath('details.fields', ['scheduled_close_at']);
    });

    it('allows nothing once closed', function (): void {
        $competition = Competition::factory()->closed()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", ['title' => 'x'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'competition_not_editable');
    });

    it('returns 403 to a member who did not create it and 404 to a stranger', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        Fixtures::signIn(Fixtures::member($this->org));

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", ['title' => 'x'])->assertForbidden()->assertJsonPath('code', 'forbidden');

        [, $stranger] = Fixtures::supplier();
        Fixtures::signIn($stranger);

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", ['title' => 'x'])->assertNotFound();
    });

    it('lets an admin with manage_all edit any competition of the organization', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        Fixtures::signIn(Fixtures::member($this->org, OrgRole::Admin));

        $this->patchJson("/api/app/v1/competitions/{$competition->public_id}", ['title' => 'x'])->assertOk();
    });
});

describe('DELETE /competitions/{competition}', function (): void {
    it('soft-deletes a draft', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        Fixtures::signIn($this->owner);

        $this->deleteJson("/api/app/v1/competitions/{$competition->public_id}")->assertNoContent();

        expect(Competition::withTrashed()->find($competition->id)?->trashed())->toBeTrue();
    });

    it('refuses to delete a published competition', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
        Fixtures::signIn($this->owner);

        $this->deleteJson("/api/app/v1/competitions/{$competition->public_id}")
            ->assertStatus(409)
            ->assertJsonPath('code', 'competition_not_editable');

        expect($competition->refresh()->status)->toBe(CompetitionStatus::Scheduled);
    });
});

describe('GET /competitions', function (): void {
    it('lists the issuer competitions with filters and page pagination', function (): void {
        Fixtures::draft($this->org, $this->owner, ['title' => 'مسودة أولى']);
        Competition::factory()->live()->create(['organization_id' => $this->org->id, 'title' => 'منافسة جارية']);
        Competition::factory()->awarded()->create(['organization_id' => $this->org->id]);
        Competition::factory()->live()->create();
        Fixtures::signIn($this->owner);

        $this->getJson('/api/app/v1/competitions?role=issuer')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonStructure(['data' => [['id', 'reference_no', 'title', 'direction', 'format', 'status', 'phase', 'category', 'region',
                'schedule' => ['bidding_opens_at', 'effective_close_at'], 'counts' => ['invitations', 'joined', 'participants_with_offers'],
                'leading_amount_minor', 'created_at', 'updated_at']], 'meta' => ['pagination' => ['type', 'current_page', 'per_page', 'has_more', 'total'], 'server_time']]);

        $this->getJson('/api/app/v1/competitions?role=issuer&status_group=active')->assertJsonCount(1, 'data');
        $this->getJson('/api/app/v1/competitions?role=issuer&status=draft,awarded')->assertJsonCount(2, 'data');
        $this->getJson('/api/app/v1/competitions?role=issuer&q='.urlencode('جارية'))->assertJsonCount(1, 'data');
        $this->getJson('/api/app/v1/competitions?role=issuer&per_page=2')->assertJsonCount(2, 'data')->assertJsonPath('meta.pagination.has_more', true);
    });

    it('lists the participant side with invitation and access', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
        [$invitation, , $inviteeOwner] = Fixtures::invitee($competition);
        Fixtures::invitee(Competition::factory()->scheduled()->create(), status: InvitationStatus::Revoked);
        Fixtures::signIn($inviteeOwner);

        $this->getJson('/api/app/v1/competitions?role=participant')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.invitation.id', $invitation->public_id)
            ->assertJsonPath('data.0.my_offer_amount_minor', null)
            ->assertJsonStructure(['data' => [['issuer' => ['id', 'name'], 'access' => ['state', 'coverage'], 'result' => ['outcome']]]]);
    });

    it('requires the role', function (): void {
        Fixtures::signIn($this->owner);

        $this->getJson('/api/app/v1/competitions')->assertStatus(422)->assertJsonValidationErrors(['role']);
    });
});
