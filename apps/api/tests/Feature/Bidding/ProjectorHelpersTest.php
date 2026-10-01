<?php

declare(strict_types=1);

use App\Modules\Bidding\Policies\BiddingPolicy;
use App\Modules\Bidding\Services\VisibilityProjector;
use App\Modules\Competitions\Database\Factories\CompetitionFactory;
use App\Modules\Competitions\Enums\RankVisibility;
use App\Modules\Competitions\Models\Comment;
use App\Modules\Identity\Enums\MembershipStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\User;
use Tests\Support\Bidding\Scenario;

/*
 * The pieces other modules call: the permission abilities of BiddingPolicy, the `offers` route
 * limiter, and the projector helpers for comment authors (Competitions' Q&A broadcasts) and
 * participant list items (API.md §2.6).
 */

it('grants the bidding abilities from the organization permissions', function () {
    $s = Scenario::make($this);
    $policy = new BiddingPolicy;
    $member = User::factory()->withMembership($s->issuerOrganization, OrgRole::Member)->create();
    $inactive = User::factory()->withMembership($s->issuerOrganization, OrgRole::Admin)->create();
    $inactive->membership->forceFill(['status' => MembershipStatus::Inactive])->save();

    expect($policy->submitOffers($s->bidder(0))->allowed())->toBeTrue()
        ->and($policy->award($s->issuer)->allowed())->toBeTrue()
        ->and($policy->award($member)->allowed())->toBeFalse()
        ->and($policy->submitOffers($inactive->refresh())->allowed())->toBeFalse()
        ->and($policy->award($inactive)->allowed())->toBeFalse();
});

it('limits offers per user on the route', function () {
    config(['bafo.bidding.offers_per_minute' => 1]);
    $s = Scenario::make($this, attributes: ['start_price_minor' => 10_000_000]);

    $s->offer(0, 9_900_000)->assertCreated();

    // The engine limit is cleared by the helper; the route limiter still answers.
    $s->offer(0, 9_800_000)
        ->assertStatus(429)
        ->assertJsonPath('code', 'too_many_requests')
        ->assertHeader('X-RateLimit-Limit', '1');
});

it('projects Q&A authors per audience without revealing names to other participants', function () {
    $s = Scenario::make($this);
    $projector = app(VisibilityProjector::class);
    $question = Comment::factory()->byParticipant($s->participant(0))->create();
    $announcement = Comment::factory()->create(['competition_id' => $s->competition->id, 'author_organization_id' => $s->issuerOrganization->id, 'author_user_id' => $s->issuer->id]);
    $name = $s->participant(0)->organization->name;
    $alias = $s->participant(0)->alias_no;

    expect($projector->commentAuthor($question, true, $s->issuerOrganization->id))->toBe(['kind' => 'participant', 'alias_no' => $alias, 'organization_name' => $name])
        ->and($projector->commentAuthor($question, false, $s->participant(0)->organization_id))->toBe(['kind' => 'me'])
        ->and($projector->commentAuthor($question, false, $s->participant(1)->organization_id))->toBe(['kind' => 'participant', 'alias_no' => $alias])
        ->and($projector->commentAuthor($announcement, false, $s->participant(1)->organization_id))->toBe(['kind' => 'issuer', 'organization_name' => $s->issuerOrganization->name]);
});

it('summarises a participant list item with the projection rules', function () {
    $s = Scenario::make($this, fn (CompetitionFactory $f) => $f->withoutFinalWindow()->live(), 2, [
        'start_price_minor' => 10_000_000,
        'rank_visibility' => RankVisibility::LeadingFlag,
    ]);
    $s->offer(0, 9_500_000)->assertCreated();
    $projector = app(VisibilityProjector::class);

    expect($projector->participantSummary($s->competition, $s->participant(0)))->toBe(['my_offer_amount_minor' => 9_500_000, 'is_leading' => true, 'result' => null])
        ->and($projector->participantSummary($s->competition, $s->participant(1)))->toBe(['my_offer_amount_minor' => null, 'is_leading' => false, 'result' => null]);
});
