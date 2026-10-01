<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Billing\Models\CompetitionSponsorship;
use App\Modules\Billing\Models\Coupon;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Support\Settings\Settings;
use Tests\Support\Billing\Billing;

beforeEach(fn () => Billing::plans());

describe('GET /competitions/{competition}/sponsorship', function () {
    it('returns mode none with the current pass price when there is no row', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs($owner);

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship")
            ->assertOk()
            ->assertJsonPath('data.mode', 'none')
            ->assertJsonPath('data.unit_price_minor', 20_000)
            ->assertJsonPath('data.enabled', true)
            ->assertJsonPath('data.funded_passes', 0)
            ->assertJsonPath('data.status', null);
    });

    it('returns the configuration with pass counts', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $sponsorship = Billing::sponsorship($competition, ['funded_passes' => 3, 'status' => 'active', 'max_passes' => 8]);
        SponsoredPass::factory()->count(2)->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id]);
        SponsoredPass::factory()->released()->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id]);
        Billing::actingAs(Billing::member($owner->membership->organization, OrgRole::Member));

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship")
            ->assertOk()
            ->assertJsonStructure(['data' => ['mode', 'max_passes', 'unit_price_minor', 'vat_rate_bp', 'currency', 'status', 'funded_passes', 'enabled',
                'counts' => ['pending', 'reserved', 'joined', 'released', 'unused', 'void', 'free_slots'], 'unused_count', 'voucher']])
            ->assertJsonPath('data.mode', 'all')
            ->assertJsonPath('data.max_passes', 8)
            ->assertJsonPath('data.counts.reserved', 2)
            ->assertJsonPath('data.counts.released', 1)
            ->assertJsonPath('data.counts.free_slots', 1);
    });

    it('hides the sponsorship from other organizations as 404', function () {
        [, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs(Billing::member());

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship")->assertNotFound();
        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship/quote")->assertNotFound();
    });

    it('requires authentication', function () {
        [, $competition] = Billing::sponsoringIssuer();

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship")->assertUnauthorized();
    });
});

describe('PUT /competitions/{competition}/sponsorship', function () {
    it('creates the row on the first mode with the direction pass price', function () {
        app(Settings::class)->set('sponsorship.pass_price_tender_minor', 25_000, null);
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs($owner);

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'selected', 'max_passes' => 5])
            ->assertOk()
            ->assertJsonPath('data.mode', 'selected')
            ->assertJsonPath('data.max_passes', 5)
            ->assertJsonPath('data.unit_price_minor', 25_000)
            ->assertJsonPath('data.status', 'draft');

        $row = CompetitionSponsorship::query()->where('competition_id', $competition->id)->firstOrFail();
        expect($row->configured_by_user_id)->toBe($owner->id)->and($row->organization_id)->toBe($competition->organization_id);
    });

    it('removes an unfunded row with mode none', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition);
        Billing::actingAs($owner);

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'none'])
            ->assertOk()
            ->assertJsonPath('data.mode', 'none');

        expect(CompetitionSponsorship::query()->count())->toBe(0);
    });

    it('locks the mode and the cap after funding', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $sponsorship = Billing::sponsorship($competition, ['funded_passes' => 2, 'status' => 'active', 'unit_price_minor' => 20_000]);
        SponsoredPass::factory()->count(2)->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id]);
        app(Settings::class)->set('sponsorship.pass_price_tender_minor', 99_000, null);
        Billing::actingAs($owner);

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'none'])
            ->assertStatus(409)->assertJsonPath('code', 'sponsorship_locked');
        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'all', 'max_passes' => 1])
            ->assertStatus(409)->assertJsonPath('code', 'sponsorship_locked')->assertJsonPath('details.min_max_passes', 2);

        // all ↔ selected stays allowed; the unit price is frozen.
        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'selected', 'max_passes' => 2])
            ->assertOk()
            ->assertJsonPath('data.mode', 'selected')
            ->assertJsonPath('data.unit_price_minor', 20_000);
    });

    it('refuses when the organization flag or the global setting is off', function (bool $flag, bool $setting) {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $owner->membership->organization->forceFill(['sponsorship_enabled' => $flag])->save();
        app(Settings::class)->set('sponsorship.enabled', $setting, null);
        Billing::actingAs($owner);

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'all'])
            ->assertForbidden()
            ->assertJsonPath('code', 'sponsorship_not_enabled');
    })->with([[false, true], [true, false]]);

    it('refuses outside draft, scheduled and live before the cutoff', function (Closure $state) {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $state($competition);
        Billing::actingAs($owner);

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'all'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_state_transition');
    })->with([
        'closed' => [fn (Competition $c) => $c->forceFill(['status' => 'closed'])->save()],
        'live after the cutoff' => [fn (Competition $c) => $c->forceFill(['status' => 'live', 'invitation_cutoff_at' => now()->subMinute()])->save()],
    ]);

    it('validates the body', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs($owner);

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'some', 'max_passes' => 0])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['mode', 'max_passes']);
    });

    it('needs competitions.manage on the competition', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs(Billing::member($owner->membership->organization, OrgRole::Member));

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'all'])->assertForbidden();
    });

    it('lets the creating member manage it', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $member = Billing::member($owner->membership->organization, OrgRole::Member);
        $competition->forceFill(['created_by_user_id' => $member->id])->save();
        Billing::actingAs($member);

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'all'])->assertOk();
    });

    it('hides other organizations as 404', function () {
        [, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs(Billing::member(Organization::factory()->sponsorshipEnabled()->create()));

        $this->putJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship", ['mode' => 'all'])->assertNotFound();
    });
});

describe('GET /competitions/{competition}/sponsorship/quote', function () {
    it('quotes the draft invitations: selection, own plan, cap and free slots', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $sponsorship = Billing::sponsorship($competition, ['mode' => SponsorshipMode::Selected, 'max_passes' => 3, 'funded_passes' => 1, 'status' => 'active']);

        $planned = Organization::factory()->create(['name' => 'Delta']);
        Billing::subscribe($planned, 'pro', startsAt: now()->subDay()->toImmutable(), endsAt: now()->addMonths(2)->toImmutable());

        $a = Invitation::factory()->create(['competition_id' => $competition->id, 'sponsored_requested' => true, 'created_at' => now()->subMinutes(5)]);
        $b = Invitation::factory()->forOrganization($planned)->create(['competition_id' => $competition->id, 'sponsored_requested' => true, 'created_at' => now()->subMinutes(4)]);
        $c = Invitation::factory()->create(['competition_id' => $competition->id, 'sponsored_requested' => false, 'created_at' => now()->subMinutes(3)]);
        $d = Invitation::factory()->create(['competition_id' => $competition->id, 'sponsored_requested' => true, 'created_at' => now()->subMinutes(2)]);
        $e = Invitation::factory()->create(['competition_id' => $competition->id, 'sponsored_requested' => true, 'created_at' => now()->subMinute()]);
        // One pass already reserved (an admin grant on $e would count against the cap and the funded slot).
        SponsoredPass::factory()->create(['sponsorship_id' => $sponsorship->id, 'competition_id' => $competition->id, 'invitation_id' => $e->id]);
        Billing::actingAs($owner);

        $response = $this->getJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship/quote")
            ->assertOk()
            ->assertJsonStructure(['data' => ['mode', 'max_passes', 'unit_price_minor', 'vat_rate_bp', 'currency', 'funded_passes', 'free_slots',
                'lines' => [['invitation_id', 'email', 'organization_name', 'coverage', 'reason']],
                'passes_to_reserve', 'passes_to_buy', 'subtotal_minor', 'discount_minor', 'vat_minor', 'total_minor']]);

        expect($response->json('data.lines'))->toBe([
            ['invitation_id' => $a->public_id, 'email' => $a->email, 'organization_name' => null, 'coverage' => 'sponsored', 'reason' => null],
            ['invitation_id' => $b->public_id, 'email' => $b->email, 'organization_name' => 'Delta', 'coverage' => 'own_plan', 'reason' => 'own_plan'],
            ['invitation_id' => $c->public_id, 'email' => $c->email, 'organization_name' => null, 'coverage' => 'none', 'reason' => 'not_selected'],
            ['invitation_id' => $d->public_id, 'email' => $d->email, 'organization_name' => null, 'coverage' => 'sponsored', 'reason' => null],
            ['invitation_id' => $e->public_id, 'email' => $e->email, 'organization_name' => null, 'coverage' => 'sponsored', 'reason' => null],
        ])
            // need = a, d (cap 3 − 1 live = 2); free = 1 − 1 = 0 → buy 2.
            ->and($response->json('data'))->toMatchArray([
                'free_slots' => 0, 'passes_to_reserve' => 0, 'passes_to_buy' => 2,
                'subtotal_minor' => 40_000, 'discount_minor' => 0, 'vat_minor' => 6_000, 'total_minor' => 46_000,
            ]);
    });

    it('reports the cap', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition, ['max_passes' => 1]);
        Invitation::factory()->count(2)->create(['competition_id' => $competition->id]);
        Billing::actingAs($owner);

        $response = $this->getJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship/quote")->assertOk();

        expect($response->json('data.lines.*.reason'))->toBe([null, 'cap_reached'])
            ->and($response->json('data.passes_to_buy'))->toBe(1);
    });

    it('applies a sponsorship coupon', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition);
        Invitation::factory()->count(2)->create(['competition_id' => $competition->id]);
        Coupon::factory()->create(['code' => 'R4HALF', 'percent_bps' => 5000, 'applies_to' => 'sponsorship', 'organization_id' => null]);
        Billing::actingAs($owner);

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship/quote?coupon_code=r4half")
            ->assertOk()
            ->assertJsonPath('data.discount_minor', 20_000)
            ->assertJsonPath('data.total_minor', 23_000);
    });

    it('has nothing to buy after publish', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $competition->forceFill(['status' => 'live'])->save();
        Billing::sponsorship($competition, ['funded_passes' => 4, 'status' => 'active']);
        Invitation::factory()->sent()->create(['competition_id' => $competition->id, 'status' => InvitationStatus::Sent]);
        Billing::actingAs($owner);

        $this->getJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship/quote")
            ->assertOk()
            ->assertJsonPath('data.passes_to_buy', 0)
            ->assertJsonPath('data.funded_passes', 4)
            ->assertJsonPath('data.free_slots', 4);
    });
});
