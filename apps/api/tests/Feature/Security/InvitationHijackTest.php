<?php

declare(strict_types=1);

use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Integrations\Models\Vendor;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Competitions\Fixtures;

/*
 * Security review (docs/build/SECURITY_REVIEW.md) S-12: an invitation is bound to an organization
 * (which may then see and join the competition) only on proven identity. A curious competitor
 * must not be able to intercept another company's invitations by pre-registering that company's
 * e-mail address or CR number:
 *
 *   - as a pending team member of its own organization (never verified by the address owner);
 *   - as its organization's self-declared contact e-mail;
 *   - as its organization's CR number, before the platform verified the organization.
 *
 * Unbound invitations stay claimable by the real owner of the address (claim flow, §13.11).
 */

beforeEach(function (): void {
    Mail::fake();
    [$this->issuer, $this->issuerOwner] = Fixtures::issuer();
    $this->competition = Fixtures::draft($this->issuer, $this->issuerOwner);
});

function securityInvite(object $test, array $row): Invitation
{
    Fixtures::signIn($test->issuerOwner);

    $response = $test->postJson('/api/app/v1/competitions/'.$test->competition->public_id.'/invitations', ['invitations' => [$row]])
        ->assertCreated();

    return Invitation::query()->where('public_id', $response->json('data.0.id'))->firstOrFail();
}

it('does not bind an invitation to the organization of an unverified, pending team member with that e-mail', function () {
    [$attacker, $attackerOwner] = Fixtures::supplier();
    Fixtures::signIn($attackerOwner);
    $this->postJson('/api/app/v1/team/members', ['name' => 'Squatter', 'email' => 'procurement@victim.sa', 'role' => 'member'])
        ->assertCreated();

    $invitation = securityInvite($this, ['email' => 'procurement@victim.sa']);

    expect($invitation->organization_id)->toBeNull();
    expect($attacker->id)->not->toBe($invitation->organization_id);
});

it('does not bind a vendor invitation to an organization that only claims the vendor\'s e-mail as its contact e-mail', function () {
    [$attacker] = Fixtures::supplier();
    $attacker->forceFill(['email' => 'procurement@victim.sa'])->save();

    $vendor = Vendor::factory()->create(['organization_id' => $this->issuer->id, 'email' => 'procurement@victim.sa', 'linked_organization_id' => $attacker->id]);

    expect(securityInvite($this, ['vendor_id' => $vendor->public_id])->organization_id)->toBeNull();
});

it('does not bind a vendor invitation to an unverified organization that only claims the vendor\'s CR number', function () {
    [$attacker] = Fixtures::supplier();
    $attacker->forceFill(['cr_number' => '7009998887', 'verified_at' => null])->save();

    $vendor = Vendor::factory()->create(['organization_id' => $this->issuer->id, 'email' => 'sales@victim.sa', 'cr_number' => '7009998887', 'linked_organization_id' => $attacker->id]);

    expect(securityInvite($this, ['vendor_id' => $vendor->public_id])->organization_id)->toBeNull();
});

it('still binds invitations on proven identities', function () {
    // A registered user who verified the address and is an active member.
    [$supplier, $supplierOwner] = Fixtures::supplier();
    expect(securityInvite($this, ['email' => $supplierOwner->email])->organization_id)->toBe($supplier->id);

    // A vendor whose e-mail is a verified, active member of the linked organization.
    [$linked, $linkedOwner] = Fixtures::supplier();
    $byMember = Vendor::factory()->create(['organization_id' => $this->issuer->id, 'email' => $linkedOwner->email, 'linked_organization_id' => $linked->id]);
    expect(securityInvite($this, ['vendor_id' => $byMember->public_id])->organization_id)->toBe($linked->id);

    // A vendor linked by CR number to an organization the platform verified.
    [$verified] = Fixtures::supplier();
    $verified->forceFill(['cr_number' => '7005554443', 'verified_at' => now()])->save();
    $byCr = Vendor::factory()->create(['organization_id' => $this->issuer->id, 'email' => 'tenders@verified.sa', 'cr_number' => '7005554443', 'linked_organization_id' => $verified->id]);
    expect(securityInvite($this, ['vendor_id' => $byCr->public_id])->organization_id)->toBe($verified->id);

    // An organization picked by the issuer from the suggestions.
    [$picked] = Fixtures::supplier();
    expect(securityInvite($this, ['organization_id' => $picked->public_id])->organization_id)->toBe($picked->id);
});

it('keeps the competition away from the squatting organization', function () {
    $this->competition = Competition::factory()->scheduled()->create(['organization_id' => $this->issuer->id, 'created_by_user_id' => $this->issuerOwner->id]);
    [, $attackerOwner] = Fixtures::supplier();
    Fixtures::signIn($attackerOwner);
    $this->postJson('/api/app/v1/team/members', ['name' => 'Squatter', 'email' => 'procurement@victim.sa', 'role' => 'member'])
        ->assertCreated();

    $invitation = securityInvite($this, ['email' => 'procurement@victim.sa']);

    expect($invitation->organization_id)->toBeNull()
        ->and($invitation->status->value)->toBe('sent');

    // The squatting organization neither sees nor joins the competition.
    Fixtures::signIn($attackerOwner);
    $this->getJson('/api/app/v1/competitions/'.$this->competition->public_id)->assertNotFound();
    $this->postJson('/api/app/v1/invitations/'.$invitation->public_id.'/join', ['accept_terms' => true])->assertNotFound();
    expect(collect($this->getJson('/api/app/v1/competitions?role=participant')->assertOk()->json('data'))->pluck('id')->all())
        ->not->toContain($this->competition->public_id);
});
