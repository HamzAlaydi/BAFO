<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Billing\Enums\PassSource;
use App\Modules\Billing\Enums\PassStatus;
use App\Modules\Billing\Models\Payment;
use App\Modules\Billing\Models\SponsoredPass;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Models\Organization;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\Support\Billing\Billing;

/*
| R4 end to end with the real Competitions actions: pay for the passes → the competition is
| published → the sponsored invitee without a plan joins on its pass.
*/

it('publishes after the sponsorship payment and consumes the pass at join', function () {
    Storage::fake('private');
    Mail::fake();
    Billing::plans();

    $opensAt = CarbonImmutable::now()->addDay()->startOfHour();
    [$owner, $competition] = Billing::sponsoringIssuer([
        'bidding_opens_at' => $opensAt,
        'scheduled_close_at' => $opensAt->addDays(2),
    ]);
    Billing::sponsorship($competition);
    $invitee = Organization::factory()->create();
    $inviteeOwner = Billing::member($invitee);
    $invitations = collect([
        Invitation::factory()->forOrganization($invitee)->create(['competition_id' => $competition->id, 'email' => $inviteeOwner->email]),
        Invitation::factory()->create(['competition_id' => $competition->id]),
    ]);
    Billing::actingAs($owner);

    // Publishing without funded passes is refused with the quote.
    $this->postJson("/api/app/v1/competitions/{$competition->public_id}/publish")
        ->assertStatus(409)
        ->assertJsonPath('code', 'sponsorship_payment_required')
        ->assertJsonPath('details.quote.passes_to_buy', 2);

    $paymentId = $this->postJson("/api/app/v1/competitions/{$competition->public_id}/sponsorship/checkout", [
        'intent' => 'publish', 'return_url' => Billing::RETURN_URL,
    ])->assertCreated()->assertJsonPath('data.total_minor', 46_000)->json('data.id');

    $this->post('/pay/fake/'.$paymentId.'/approve')->assertStatus(303);

    $competition->refresh();
    expect(Payment::query()->where('public_id', $paymentId)->value('status')->value)->toBe('succeeded')
        ->and($competition->status)->toBe(CompetitionStatus::Scheduled)
        ->and(SponsoredPass::query()->where('status', PassStatus::Reserved->value)->where('source', PassSource::Purchase->value)->count())->toBe(2)
        ->and($invitations->first()->refresh()->status)->toBe(InvitationStatus::Sent);

    // The invitee has no plan: it joins on the sponsored pass.
    Billing::actingAs($inviteeOwner);
    $this->getJson("/api/app/v1/competitions/{$competition->public_id}")
        ->assertOk()
        ->assertJsonPath('data.access.coverage', 'sponsored')
        ->assertJsonPath('data.access.state', 'join_required');
    $this->postJson('/api/app/v1/invitations/'.$invitations->first()->public_id.'/join', ['accept_terms' => true])->assertOk();

    expect(SponsoredPass::query()->where('invitation_id', $invitations->first()->id)->value('status'))->toBe(PassStatus::Joined)
        ->and(Participant::query()->where('invitation_id', $invitations->first()->id)->value('entitlement_source'))->toBe(EntitlementSource::SponsoredPass);
})->group('cross-module');
