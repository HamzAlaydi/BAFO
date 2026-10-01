<?php

declare(strict_types=1);

use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Enums\RevokeReason;
use App\Modules\Competitions\Events\InvitationRevoked;
use App\Modules\Competitions\Events\InvitationSent;
use App\Modules\Competitions\Mail\CompetitionInvitationMail;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Identity\Events\EmailVerified;
use App\Modules\Identity\Models\User;
use App\Modules\Integrations\Enums\VendorStatus;
use App\Modules\Integrations\Models\Vendor;
use App\Support\Settings\Settings;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Competitions\Fixtures;

beforeEach(function (): void {
    Mail::fake();
    [$this->org, $this->owner] = Fixtures::issuer();
});

function invitationsUrl(Competition $competition, string $suffix = ''): string
{
    return "/api/app/v1/competitions/{$competition->public_id}/invitations{$suffix}";
}

describe('POST …/invitations', function (): void {
    it('creates draft invitations on a draft from e-mails, organizations and vendors', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        [$supplier] = Fixtures::supplier();
        [$registered, $registeredOwner] = Fixtures::supplier();
        $vendor = Vendor::factory()->create(['organization_id' => $this->org->id, 'contact_name' => 'خالد']);
        Fixtures::signIn($this->owner);

        $response = $this->postJson(invitationsUrl($competition), ['invitations' => [
            ['email' => 'Sales@NewSupplier.sa', 'name' => 'Sales', 'sponsored' => true],
            ['organization_id' => $supplier->public_id],
            ['vendor_id' => $vendor->public_id],
            ['email' => $registeredOwner->email],
        ]])->assertCreated()
            ->assertJsonCount(4, 'data')
            ->assertJsonStructure(['data' => [['id', 'email', 'name', 'status', 'organization', 'vendor', 'sponsored_requested',
                'coverage', 'pass_status', 'participant', 'sent_at', 'viewed_at', 'joined_at', 'declined_at', 'decline_reason',
                'revoked_at', 'revoke_reason', 'expired_at', 'created_at']]])
            ->assertJsonPath('data.0.email', 'sales@newsupplier.sa')
            ->assertJsonPath('data.0.status', 'draft')
            ->assertJsonPath('data.0.sponsored_requested', true)
            ->assertJsonPath('data.1.organization.id', $supplier->public_id)
            ->assertJsonPath('data.2.vendor.id', $vendor->public_id)
            ->assertJsonPath('data.2.name', 'خالد')
            ->assertJsonPath('data.3.organization.id', $registered->public_id);

        expect(Invitation::query()->where('competition_id', $competition->id)->where('status', 'draft')->count())->toBe(4)
            ->and($response->json('data.0.sent_at'))->toBeNull();
        Mail::assertNothingQueued();
    });

    it('sends invitations at once on a published competition', function (): void {
        Event::fake([InvitationSent::class]);
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        Fixtures::signIn($this->owner);

        $this->postJson(invitationsUrl($competition), ['invitations' => [['email' => 'buyer@acme.sa']]])
            ->assertCreated()
            ->assertJsonPath('data.0.status', 'sent');

        expect(Invitation::query()->where('email', 'buyer@acme.sa')->value('token_hash'))->toHaveLength(64);
        Mail::assertQueued(CompetitionInvitationMail::class, fn (CompetitionInvitationMail $mail): bool => $mail->hasTo('buyer@acme.sa'));
        Event::assertDispatched(InvitationSent::class);
    });

    it('rejects every row problem with item codes and creates nothing', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        Invitation::factory()->create(['competition_id' => $competition->id, 'email' => 'taken@acme.sa']);
        $blocked = Vendor::factory()->create(['organization_id' => $this->org->id, 'status' => VendorStatus::Blocked]);
        $foreignVendor = Vendor::factory()->create();
        Fixtures::signIn($this->owner);

        $this->postJson(invitationsUrl($competition), ['invitations' => [
            ['email' => 'ok@acme.sa'],
            ['email' => 'taken@acme.sa'],
            ['organization_id' => $this->org->public_id],
            ['vendor_id' => $blocked->public_id],
            ['vendor_id' => $foreignVendor->public_id],
            ['email' => 'ok@acme.sa'],
        ]])->assertStatus(422)
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('details.item_codes', [
                'invitations.1.email' => 'invitation_duplicate',
                'invitations.2.organization_id' => 'cannot_invite_own_organization',
                'invitations.3.vendor_id' => 'vendor_blocked',
                'invitations.4.vendor_id' => 'vendor_not_found',
                'invitations.5.email' => 'invitation_duplicate',
            ])
            ->assertJsonValidationErrors(['invitations.1.email', 'invitations.2.organization_id']);

        expect(Invitation::query()->where('competition_id', $competition->id)->count())->toBe(1);
    });

    it('validates the rows', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        Fixtures::signIn($this->owner);

        $this->postJson(invitationsUrl($competition), ['invitations' => []])->assertStatus(422)->assertJsonValidationErrors(['invitations']);
        $this->postJson(invitationsUrl($competition), ['invitations' => [['email' => 'not-an-email'], ['name' => 'x']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['invitations.0.email', 'invitations.1.email']);
    });

    it('returns 409 invitation_cutoff_passed after the cut-off', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        $competition->forceFill(['invitation_cutoff_at' => now()->subMinute()])->save();
        Fixtures::signIn($this->owner);

        $this->postJson(invitationsUrl($competition), ['invitations' => [['email' => 'late@acme.sa']]])
            ->assertStatus(409)->assertJsonPath('code', 'invitation_cutoff_passed');
    });

    it('returns 422 max_participants_exceeded over the setting', function (): void {
        app(Settings::class)->set('competitions.max_participants', 2, null);
        $competition = Fixtures::draft($this->org, $this->owner);
        Fixtures::draftInvitations($competition, 2);
        Fixtures::signIn($this->owner);

        $this->postJson(invitationsUrl($competition), ['invitations' => [['email' => 'third@acme.sa']]])
            ->assertStatus(422)->assertJsonPath('code', 'max_participants_exceeded');
    });

    it('returns 409 on a closed competition and 403 to a member without manage', function (): void {
        $closed = Competition::factory()->closed()->create(['organization_id' => $this->org->id]);
        Fixtures::signIn($this->owner);

        $this->postJson(invitationsUrl($closed), ['invitations' => [['email' => 'a@acme.sa']]])
            ->assertStatus(409)->assertJsonPath('code', 'invalid_state_transition');

        Fixtures::signIn(Fixtures::member($this->org));
        $this->postJson(invitationsUrl(Fixtures::draft($this->org, $this->owner)), ['invitations' => [['email' => 'a@acme.sa']]])
            ->assertForbidden();
    });
});

describe('GET …/invitations', function (): void {
    it('lists the invitations with status counts for any issuer member', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id]);
        Fixtures::invitee($competition);
        Fixtures::invitee($competition, status: InvitationStatus::Declined);
        Fixtures::participant($competition);
        Fixtures::signIn(Fixtures::member($this->org));

        $this->getJson(invitationsUrl($competition))
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('meta.counts.sent', 1)
            ->assertJsonPath('meta.counts.declined', 1)
            ->assertJsonPath('meta.counts.joined', 1)
            ->assertJsonPath('data.2.participant.alias_no', fn (int $alias): bool => $alias >= 1);

        $this->getJson(invitationsUrl($competition, '?status=declined'))->assertJsonCount(1, 'data');
    });

    it('is issuer-only: 403 for a participant, 404 for a stranger', function (): void {
        $competition = Competition::factory()->live()->create(['organization_id' => $this->org->id]);
        [, , $participantOwner] = Fixtures::participant($competition);
        Fixtures::signIn($participantOwner);

        $this->getJson(invitationsUrl($competition))->assertForbidden();

        [, $stranger] = Fixtures::supplier();
        Fixtures::signIn($stranger);
        $this->getJson(invitationsUrl($competition))->assertNotFound();
    });
});

describe('PATCH / DELETE / resend', function (): void {
    it('updates the name and the sponsored flag of a draft invitation', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        [$invitation] = Fixtures::draftInvitations($competition, 1);
        Fixtures::signIn($this->owner);

        $this->patchJson(invitationsUrl($competition, "/{$invitation->public_id}"), ['name' => 'أحمد', 'sponsored' => true])
            ->assertOk()
            ->assertJsonPath('data.name', 'أحمد')
            ->assertJsonPath('data.sponsored_requested', true);
    });

    it('refuses a name change once sent', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        [$invitation] = Fixtures::invitee($competition);
        Fixtures::signIn($this->owner);

        $this->patchJson(invitationsUrl($competition, "/{$invitation->public_id}"), ['name' => 'x'])
            ->assertStatus(409)->assertJsonPath('code', 'invalid_state_transition');
        $this->patchJson(invitationsUrl($competition, "/{$invitation->public_id}"), ['sponsored' => true])->assertOk();
    });

    it('deletes a draft invitation and revokes a sent one', function (): void {
        Event::fake([InvitationRevoked::class]);
        $draftCompetition = Fixtures::draft($this->org, $this->owner);
        [$draft] = Fixtures::draftInvitations($draftCompetition, 1);
        $published = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        [$sent] = Fixtures::invitee($published);
        [$joined] = Fixtures::invitee($published);
        $joined->forceFill(['status' => InvitationStatus::Joined])->save();
        Fixtures::signIn($this->owner);

        $this->deleteJson(invitationsUrl($draftCompetition, "/{$draft->public_id}"))->assertNoContent();
        expect(Invitation::query()->find($draft->id))->toBeNull();

        $this->deleteJson(invitationsUrl($published, "/{$sent->public_id}"))
            ->assertOk()
            ->assertJsonPath('data.status', 'revoked')
            ->assertJsonPath('data.revoke_reason', 'issuer');
        Event::assertDispatched(InvitationRevoked::class);

        $this->deleteJson(invitationsUrl($published, "/{$joined->public_id}"))->assertStatus(409);
    });

    it('scopes the invitation to the competition in the path', function (): void {
        $competition = Fixtures::draft($this->org, $this->owner);
        $other = Fixtures::draft($this->org, $this->owner);
        [$invitation] = Fixtures::draftInvitations($other, 1);
        Fixtures::signIn($this->owner);

        $this->deleteJson(invitationsUrl($competition, "/{$invitation->public_id}"))->assertNotFound();
    });

    it('resends with a new token, at most 3 times a day', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'created_by_user_id' => $this->owner->id]);
        [$invitation] = Fixtures::invitee($competition);
        $oldHash = $invitation->token_hash;
        Fixtures::signIn($this->owner);

        $this->postJson(invitationsUrl($competition, "/{$invitation->public_id}/resend"))->assertNoContent();
        expect($invitation->refresh()->token_hash)->not->toBe($oldHash);
        Mail::assertQueued(CompetitionInvitationMail::class, 1);

        $this->postJson(invitationsUrl($competition, "/{$invitation->public_id}/resend"))->assertNoContent();
        $this->postJson(invitationsUrl($competition, "/{$invitation->public_id}/resend"))->assertNoContent();
        $this->postJson(invitationsUrl($competition, "/{$invitation->public_id}/resend"))
            ->assertStatus(429)
            ->assertJsonPath('code', 'too_many_requests')
            ->assertHeader('Retry-After');
    });

    it('revokes duplicate invitations of an organization with the duplicate reason at join', function (): void {
        $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
        [$first, $supplier, $supplierOwner] = Fixtures::invitee($competition);
        $second = Invitation::factory()->sent()->create([
            'competition_id' => $competition->id,
            'organization_id' => $supplier->id,
            'email' => 'other@'.explode('@', $supplier->email)[1],
        ]);
        Fixtures::signIn($supplierOwner);

        $this->postJson("/api/app/v1/invitations/{$first->public_id}/join", ['accept_terms' => true])->assertOk();

        expect($second->refresh()->status)->toBe(InvitationStatus::Revoked)
            ->and($second->revoke_reason)->toBe(RevokeReason::DuplicateOrganization);
    });
});

it('binds pending invitations to the organization of a user who verifies the invited e-mail', function (): void {
    $competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id]);
    $invitation = Invitation::factory()->sent()->create(['competition_id' => $competition->id, 'email' => 'new@supplier.sa']);
    [$supplier] = Fixtures::supplier();
    $user = User::factory()->withMembership($supplier)->create(['email' => 'new@supplier.sa']);

    event(new EmailVerified($user));

    expect($invitation->refresh()->organization_id)->toBe($supplier->id);
});
