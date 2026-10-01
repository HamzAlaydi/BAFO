<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Enums\PaymentStatus;
use App\Modules\Billing\Enums\SponsorshipMode;
use App\Modules\Billing\Enums\SponsorshipStatus;
use App\Modules\Billing\Events\SponsorshipPublishFailed;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Auth\Channel;
use App\Support\Exceptions\ApiException;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Support\Billing\Billing;

beforeEach(function () {
    Storage::fake('private');
    Billing::plans();
});

function billingSponsorshipCheckout(Competition $competition, array $body = [], array $headers = []): TestResponse
{
    return test()->postJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship/checkout", [
        'intent' => 'publish',
        'return_url' => Billing::RETURN_URL,
        ...$body,
    ], $headers);
}

describe('intent: publish', function () {
    it('holds pending passes for the passes to buy and publishes after approval', function () {
        $spy = Billing::fakeCompetitionActions();
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition);
        $invitations = Invitation::factory()->count(3)->create(['competition_id' => $competition->id]);
        Billing::actingAs($owner);

        $response = billingSponsorshipCheckout($competition)
            ->assertCreated()
            ->assertJsonPath('data.purpose', 'sponsorship')
            ->assertJsonPath('data.lines.0.kind', 'sponsored_pass')
            ->assertJsonPath('data.lines.0.quantity', 3)
            ->assertJsonPath('data.lines.0.unit_price_minor', 20_000)
            ->assertJsonPath('data.subtotal_minor', 60_000)
            ->assertJsonPath('data.vat_minor', 9_000)
            ->assertJsonPath('data.total_minor', 69_000)
            ->assertJsonPath('data.context.competition_id', $competition->public_id)
            ->assertJsonPath('data.context.intent', 'publish');

        $payment = Payment::query()->where('public_id', $response->json('data.id'))->firstOrFail();
        $passes = SponsoredPass::query()->where('payment_id', $payment->id)->get();

        expect($passes)->toHaveCount(3)
            ->and($passes->pluck('status')->unique()->all())->toBe([PassStatus::Pending])
            ->and($passes->pluck('invitation_id')->sort()->values()->all())->toBe($invitations->pluck('id')->sort()->values()->all())
            ->and($passes->first()->hold_expires_at?->equalTo($payment->expires_at))->toBeTrue();

        $this->post('/pay/fake/'.$payment->public_id.'/approve')->assertStatus(303);

        $sponsorship = $competition->sponsorship()->firstOrFail();
        expect($payment->refresh()->status)->toBe(PaymentStatus::Succeeded)
            ->and($sponsorship->funded_passes)->toBe(3)
            ->and($sponsorship->status)->toBe(SponsorshipStatus::Active)
            ->and(SponsoredPass::query()->where('status', PassStatus::Reserved->value)->count())->toBe(3)
            ->and($spy->published)->toHaveCount(1)
            ->and($spy->published[0]['competition_id'])->toBe($competition->id)
            ->and($spy->published[0]['actor']->userId)->toBe($owner->id)
            ->and($spy->published[0]['actor']->channel)->toBe(Channel::System)
            ->and($payment->invoice)->not->toBeNull();
    });

    it('buys only what the free slots do not cover', function () {
        Billing::fakeCompetitionActions();
        [$owner, $competition] = Billing::sponsoringIssuer();
        $sponsorship = Billing::sponsorship($competition, ['funded_passes' => 2, 'status' => 'active']);
        Invitation::factory()->count(3)->create(['competition_id' => $competition->id]);
        Billing::actingAs($owner);

        $response = billingSponsorshipCheckout($competition)->assertCreated()->assertJsonPath('data.lines.0.quantity', 1);

        expect(SponsoredPass::query()->where('sponsorship_id', $sponsorship->id)->count())->toBe(1);
        $this->post('/pay/fake/'.$response->json('data.id').'/approve')->assertStatus(303);
        expect($sponsorship->refresh()->funded_passes)->toBe(3);
    });

    it('never charges twice for the same passes', function () {
        Billing::fakeCompetitionActions();
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition);
        Invitation::factory()->count(2)->create(['competition_id' => $competition->id]);
        Billing::actingAs($owner);

        billingSponsorshipCheckout($competition)->assertCreated();

        // The pending passes of the first checkout are already counted: nothing left to buy.
        billingSponsorshipCheckout($competition)->assertStatus(409)->assertJsonPath('code', 'sponsorship_already_funded');
        expect(Payment::query()->count())->toBe(1);
    });

    it('replays the same payment for the same Idempotency-Key', function () {
        Billing::fakeCompetitionActions();
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition);
        Invitation::factory()->create(['competition_id' => $competition->id]);
        Billing::actingAs($owner);

        $first = billingSponsorshipCheckout($competition, headers: ['Idempotency-Key' => 'sponsor-intent-1'])->assertCreated();
        $second = billingSponsorshipCheckout($competition, headers: ['Idempotency-Key' => 'sponsor-intent-1'])->assertCreated();

        expect($second->json('data.id'))->toBe($first->json('data.id'))->and(Payment::query()->count())->toBe(1);
    });

    it('answers sponsorship_already_funded when nothing needs a pass', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition, ['mode' => SponsorshipMode::Selected]);
        Invitation::factory()->create(['competition_id' => $competition->id, 'sponsored_requested' => false]);
        Billing::actingAs($owner);

        billingSponsorshipCheckout($competition)->assertStatus(409)->assertJsonPath('code', 'sponsorship_already_funded');
    });

    it('keeps the funded passes and reports when publish fails after payment', function () {
        Event::fake([SponsorshipPublishFailed::class]);
        Billing::fakeCompetitionActions(new ApiException('live_event_capacity_reached', status: 409));
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition);
        Invitation::factory()->count(2)->create(['competition_id' => $competition->id]);
        Billing::actingAs($owner);

        $payment = Payment::query()->where('public_id', billingSponsorshipCheckout($competition)->json('data.id'))->firstOrFail();
        $this->post('/pay/fake/'.$payment->public_id.'/approve')->assertStatus(303);

        expect($payment->refresh()->status)->toBe(PaymentStatus::Succeeded)
            ->and($competition->sponsorship()->value('funded_passes'))->toBe(2)
            ->and(SponsoredPass::query()->where('status', PassStatus::Reserved->value)->count())->toBe(2);
        Event::assertDispatched(SponsorshipPublishFailed::class, fn (SponsorshipPublishFailed $e) => $e->errorCode === 'live_event_capacity_reached' && $e->payment->is($payment));

        // Publishing again needs no new payment.
        billingSponsorshipCheckout($competition)->assertStatus(409)->assertJsonPath('code', 'sponsorship_already_funded');
    });

    it('voids the held passes when the payment is declined', function () {
        $spy = Billing::fakeCompetitionActions();
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::sponsorship($competition);
        Invitation::factory()->count(2)->create(['competition_id' => $competition->id]);
        Billing::actingAs($owner);

        $payment = Payment::query()->where('public_id', billingSponsorshipCheckout($competition)->json('data.id'))->firstOrFail();
        $this->post('/pay/fake/'.$payment->public_id.'/decline')->assertStatus(303);

        expect(SponsoredPass::query()->where('status', PassStatus::Void->value)->count())->toBe(2)
            ->and($competition->sponsorship()->value('funded_passes'))->toBe(0)
            ->and($spy->published)->toBe([]);

        // The invitations can be bought again.
        billingSponsorshipCheckout($competition)->assertCreated()->assertJsonPath('data.lines.0.quantity', 2);
    });

    it('is for drafts only', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $competition->forceFill(['status' => 'live'])->save();
        Billing::sponsorship($competition);
        Billing::actingAs($owner);

        billingSponsorshipCheckout($competition)->assertStatus(409)->assertJsonPath('code', 'invalid_state_transition');
    });
});

describe('intent: invite', function () {
    it('stores the rows and invites them after approval', function () {
        $spy = Billing::fakeCompetitionActions();
        [$owner, $competition] = Billing::sponsoringIssuer();
        $competition->forceFill(['status' => 'live', 'invitation_cutoff_at' => now()->addDay()])->save();
        Billing::sponsorship($competition, ['mode' => SponsorshipMode::Selected, 'funded_passes' => 1, 'status' => 'active']);
        $known = Organization::factory()->create();
        $vendor = Vendor::factory()->create(['organization_id' => $competition->organization_id, 'linked_organization_id' => null]);
        Billing::actingAs($owner);

        $response = billingSponsorshipCheckout($competition, ['intent' => 'invite', 'invitations' => [
            ['email' => 'New@Supplier.sa', 'name' => 'Ahmed'],
            ['organization_id' => $known->public_id],
            ['vendor_id' => $vendor->public_id],
        ]])->assertCreated()
            ->assertJsonPath('data.context.intent', 'invite')
            ->assertJsonPath('data.lines.0.quantity', 2);

        $payment = Payment::query()->where('public_id', $response->json('data.id'))->firstOrFail();
        expect(SponsoredPass::query()->count())->toBe(0)
            ->and(Invitation::query()->count())->toBe(0);

        $this->post('/pay/fake/'.$payment->public_id.'/approve')->assertStatus(303);

        expect($competition->sponsorship()->value('funded_passes'))->toBe(3)
            ->and($spy->invited)->toHaveCount(1)
            ->and($spy->invited[0]['rows'])->toEqual([
                ['email' => 'new@supplier.sa', 'name' => 'Ahmed', 'organization_id' => null, 'vendor_id' => null, 'sponsored' => true],
                ['email' => mb_strtolower($known->email), 'name' => null, 'organization_id' => $known->id, 'vendor_id' => null, 'sponsored' => true],
                ['email' => mb_strtolower($vendor->email), 'name' => null, 'organization_id' => null, 'vendor_id' => $vendor->id, 'sponsored' => true],
            ]);
    });

    it('validates the rows up front with item codes', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $competition->forceFill(['status' => 'live', 'invitation_cutoff_at' => now()->addDay()])->save();
        Billing::sponsorship($competition);
        Invitation::factory()->sent()->create(['competition_id' => $competition->id, 'email' => 'taken@supplier.sa']);
        $blocked = Vendor::factory()->create(['organization_id' => $competition->organization_id, 'status' => 'blocked']);
        $foreignVendor = Vendor::factory()->create();
        Billing::actingAs($owner);

        $response = billingSponsorshipCheckout($competition, ['intent' => 'invite', 'invitations' => [
            ['email' => 'taken@supplier.sa'],
            ['organization_id' => $competition->organization->public_id],
            ['vendor_id' => $blocked->public_id],
            ['vendor_id' => $foreignVendor->public_id],
            ['email' => 'fine@supplier.sa'],
        ]])->assertStatus(422)->assertJsonPath('code', 'validation_failed');

        expect($response->json('details.item_codes'))->toBe([
            'invitations.0.email' => 'invitation_duplicate',
            'invitations.1.organization_id' => 'cannot_invite_own_organization',
            'invitations.2.vendor_id' => 'vendor_blocked',
            'invitations.3.vendor_id' => 'vendor_not_found',
        ])->and(Payment::query()->count())->toBe(0);
    });

    it('refuses after the invitation cutoff', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $competition->forceFill(['status' => 'live', 'invitation_cutoff_at' => now()->subMinute()])->save();
        Billing::sponsorship($competition);
        Billing::actingAs($owner);

        billingSponsorshipCheckout($competition, ['intent' => 'invite', 'invitations' => [['email' => 'a@b.sa']]])
            ->assertStatus(409)->assertJsonPath('code', 'invitation_cutoff_passed');
    });

    it('requires the rows', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs($owner);

        billingSponsorshipCheckout($competition, ['intent' => 'invite'])->assertStatus(422)->assertJsonValidationErrors(['invitations']);
    });
});

describe('guards', function () {
    it('refuses when sponsorship is not enabled', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $owner->membership->organization->forceFill(['sponsorship_enabled' => false])->save();
        Billing::actingAs($owner);

        billingSponsorshipCheckout($competition)->assertForbidden()->assertJsonPath('code', 'sponsorship_not_enabled');
    });

    it('refuses the mobile apps', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs($owner);

        billingSponsorshipCheckout($competition, headers: ['X-Platform' => 'ios'])->assertForbidden()->assertJsonPath('code', 'purchase_not_available_on_platform');
    });

    it('checks the billing profile and the return URL', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs($owner);

        billingSponsorshipCheckout($competition, ['return_url' => 'https://evil.test/r'])->assertStatus(422)->assertJsonPath('code', 'return_url_not_allowed');

        $owner->membership->organization->forceFill(['address_street' => null])->save();
        billingSponsorshipCheckout($competition)->assertStatus(422)->assertJsonPath('code', 'billing_profile_incomplete');
    });

    it('needs competitions.manage and billing.purchase', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        $admin = Billing::member($owner->membership->organization, OrgRole::Admin, ['can_purchase' => false]);
        Billing::actingAs($admin);

        billingSponsorshipCheckout($competition)->assertForbidden()->assertJsonPath('code', 'forbidden');
    });

    it('hides other organizations as 404 and requires authentication', function () {
        [, $competition] = Billing::sponsoringIssuer();
        billingSponsorshipCheckout($competition)->assertUnauthorized();

        Billing::actingAs(Billing::member());
        billingSponsorshipCheckout($competition)->assertNotFound();
    });

    it('validates the body', function () {
        [$owner, $competition] = Billing::sponsoringIssuer();
        Billing::actingAs($owner);

        billingSponsorshipCheckout($competition, ['intent' => 'buy', 'return_url' => null])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['intent', 'return_url']);
    });
});
