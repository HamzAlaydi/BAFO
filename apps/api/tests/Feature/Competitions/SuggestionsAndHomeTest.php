<?php

declare(strict_types=1);

use App\Modules\Bidding\Models\Award;
use App\Modules\Bidding\Models\Offer;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Region;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\Actor;
use Tests\Support\Competitions\Fixtures;

beforeEach(function (): void {
    [$this->org, $this->owner] = Fixtures::issuer();
});

describe('GET …/suggestions', function (): void {
    it('suggests opted-in organizations sharing the category or the region, best matches first', function (): void {
        $category = Category::factory()->create();
        $region = Region::factory()->create();
        $competition = Fixtures::draft($this->org, $this->owner, ['category_id' => $category->id, 'region_id' => $region->id]);

        $both = Organization::factory()->create(['name' => 'ب مورد', 'region_id' => $region->id]);
        $both->categories()->attach($category);
        $categoryOnly = Organization::factory()->create(['name' => 'أ مورد']);
        $categoryOnly->categories()->attach($category);
        $regionOnly = Organization::factory()->create(['name' => 'ج مورد', 'region_id' => $region->id]);
        Fixtures::subscribe($regionOnly);
        $hidden = Organization::factory()->create(['region_id' => $region->id, 'visible_in_suggestions' => false]);
        $invited = Organization::factory()->create(['region_id' => $region->id]);
        Invitation::factory()->forOrganization($invited)->create(['competition_id' => $competition->id]);
        Organization::factory()->create();
        Fixtures::signIn($this->owner);

        $response = $this->getJson("/api/app/v1/competitions/{$competition->public_id}/suggestions")
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.organization.id', $both->public_id)
            ->assertJsonPath('data.0.match', ['category' => true, 'region' => true])
            ->assertJsonStructure(['data' => [['organization' => ['id', 'name', 'logo_url', 'verified'], 'region', 'categories', 'has_active_plan', 'match' => ['category', 'region']]]]);

        $ids = collect($response->json('data'))->pluck('organization.id')->all();

        expect($ids)->not->toContain($hidden->public_id, $invited->public_id, $this->org->public_id)
            ->and(collect($response->json('data'))->firstWhere('organization.id', $regionOnly->public_id)['has_active_plan'])->toBeTrue();

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/suggestions?q=".urlencode('أ مورد').'&limit=1')
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.organization.id', $categoryOnly->public_id);
    });

    it('is for managers of the issuer only', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        Fixtures::signIn(Fixtures::member($this->org));

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/suggestions")->assertForbidden();
    });
});

describe('GET /home', function (): void {
    it('returns the issuer and participant counters, team, subscription, alerts and activity', function (): void {
        Fixtures::draft($this->org, $this->owner);
        $live = Competition::factory()->live()->create(['organization_id' => $this->org->id]);
        Competition::factory()->closed()->create(['organization_id' => $this->org->id]);
        [$participant] = Fixtures::participant($live);
        Offer::factory()->create(['competition_id' => $live->id, 'participant_id' => $participant->id]);

        // The issuer also takes part in someone else's competition.
        $foreign = Competition::factory()->live()->create();
        Invitation::factory()->forOrganization($this->org)->sent()->create(['competition_id' => $foreign->id]);
        $won = Competition::factory()->awarded()->create();
        $wonParticipant = Participant::factory()->create(['competition_id' => $won->id, 'organization_id' => $this->org->id, 'joined_by_user_id' => $this->owner->id]);
        Award::factory()->create(['offer_id' => Offer::factory()->create(['participant_id' => $wonParticipant->id])->id]);

        AuditLogger::log('competition.published', $live, actor: Actor::system(), organizationId: $this->org->id);
        AuditLogger::log('competition.updated', $live, actor: Actor::system(), organizationId: $this->org->id);
        Fixtures::signIn($this->owner);

        $this->getJson('/api/app/v1/home')
            ->assertOk()
            ->assertJsonStructure(['data' => [
                'issuer' => ['active_competitions', 'draft_competitions', 'live_now', 'awaiting_award', 'offers_received_30d'],
                'participant' => ['pending_invitations', 'active_participations', 'offers_submitted_30d', 'awards_won'],
                'team' => ['members', 'seats_total'],
                'subscription' => ['plan' => ['id', 'code', 'name'], 'status', 'days_left', 'total_days', 'ends_at'],
                'alerts',
                'activities' => [['id', 'action', 'occurred_at', 'actor' => ['name'], 'subject' => ['type', 'id', 'title']]],
            ]])
            ->assertJsonPath('data.issuer.active_competitions', 2)
            ->assertJsonPath('data.issuer.draft_competitions', 1)
            ->assertJsonPath('data.issuer.live_now', 1)
            ->assertJsonPath('data.issuer.awaiting_award', 1)
            ->assertJsonPath('data.issuer.offers_received_30d', 1)
            ->assertJsonPath('data.participant.pending_invitations', 1)
            ->assertJsonPath('data.participant.awards_won', 1)
            ->assertJsonPath('data.team.members', 1)
            ->assertJsonPath('data.subscription.status', 'active')
            ->assertJsonCount(1, 'data.activities')
            ->assertJsonPath('data.activities.0.action', 'competition.published')
            ->assertJsonPath('data.activities.0.subject', ['type' => 'competition', 'id' => $live->public_id, 'title' => $live->title]);
    });

    it('raises plan alerts without a subscription', function (): void {
        [, $owner] = Fixtures::issuer(plan: false);
        Fixtures::signIn($owner);

        $response = $this->getJson('/api/app/v1/home')
            ->assertOk()
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.alerts.0.code', 'plan_required')
            ->assertJsonPath('data.alerts.1.code', 'trial_available');

        // `params` is a JSON object even without parameters (API.md §2.13), never `[]`.
        expect($response->getContent())->toContain('{"code":"plan_required","params":{}}')
            ->not->toContain('"params":[]');
    });

    it('requires authentication', function (): void {
        $this->getJson('/api/app/v1/home')->assertUnauthorized();
    });
});
