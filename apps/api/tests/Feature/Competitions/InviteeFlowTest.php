<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\EntitlementSource;
use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Enums\InvitationStatus;
use App\Modules\Competitions\Events\InvitationDeclined;
use App\Modules\Competitions\Events\InvitationJoined;
use App\Modules\Competitions\Events\InvitationViewed;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Invitation;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\OtpPurpose;
use App\Modules\Identity\Models\OtpCode;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\Support\Competitions\Fixtures;

beforeEach(function (): void {
    Mail::fake();
    [$this->org, $this->owner] = Fixtures::issuer();
    $this->competition = Competition::factory()->scheduled()->create(['organization_id' => $this->org->id, 'reserve_price_minor' => 40_000]);
});

describe('POST /invitations/lookup (guest)', function (): void {
    it('returns the teaser, a masked e-mail and the next step, and marks the invitation viewed', function (): void {
        Event::fake([InvitationViewed::class]);
        $invitation = Invitation::factory()->sent('plain-token-1234567890')->create([
            'competition_id' => $this->competition->id,
            'email' => 'sales@acme.sa',
        ]);

        $response = $this->postJson('/api/app/v1/invitations/lookup', ['token' => 'plain-token-1234567890'])
            ->assertOk()
            ->assertJsonPath('data.invitation.id', $invitation->public_id)
            ->assertJsonPath('data.invitation.status', 'viewed')
            ->assertJsonPath('data.invitation.email_masked', 's***@acme.sa')
            ->assertJsonPath('data.invitation.sponsored', false)
            ->assertJsonPath('data.next_step', 'register')
            ->assertJsonPath('data.competition.id', $this->competition->public_id)
            ->assertJsonStructure(['data' => ['competition' => ['title', 'direction', 'rules', 'rules_summary', 'schedule', 'issuer']]]);

        expect($response->json('data.competition'))->not->toHaveKeys(['access', 'permissions', 'invitation_documents'])
            ->and($response->json('data.competition.rules'))->not->toHaveKey('reserve_price_minor')
            ->and($invitation->refresh()->status)->toBe(InvitationStatus::Viewed);
        Event::assertDispatched(InvitationViewed::class);
    });

    it('says login when a user with the e-mail exists', function (): void {
        User::factory()->create(['email' => 'known@acme.sa']);
        Invitation::factory()->sent('plain-token-known')->create(['competition_id' => $this->competition->id, 'email' => 'known@acme.sa']);

        $this->postJson('/api/app/v1/invitations/lookup', ['token' => 'plain-token-known'])->assertOk()->assertJsonPath('data.next_step', 'login');
    });

    it('returns 404 invitation_invalid for unknown and revoked tokens', function (): void {
        Invitation::factory()->revoked()->create(['competition_id' => $this->competition->id, 'token_hash' => hash('sha256', 'revoked-token')]);

        $this->postJson('/api/app/v1/invitations/lookup', ['token' => 'nope'])->assertNotFound()->assertJsonPath('code', 'invitation_invalid');
        $this->postJson('/api/app/v1/invitations/lookup', ['token' => 'revoked-token'])->assertNotFound()->assertJsonPath('code', 'invitation_invalid');
    });

    it('rejects the honeypot and a missing token', function (): void {
        $this->postJson('/api/app/v1/invitations/lookup', ['token' => 'x', 'website_url' => 'spam'])
            ->assertStatus(422)->assertJsonValidationErrors(['website_url']);
        $this->postJson('/api/app/v1/invitations/lookup', [])->assertStatus(422)->assertJsonValidationErrors(['token']);
    });
});

describe('POST /invitations/decline (guest)', function (): void {
    it('declines by token and records the reason', function (): void {
        Event::fake([InvitationDeclined::class]);
        $invitation = Invitation::factory()->sent('decline-token')->create(['competition_id' => $this->competition->id]);

        $this->postJson('/api/app/v1/invitations/decline', ['token' => 'decline-token', 'reason' => 'لا نورد هذا الصنف'])
            ->assertOk()
            ->assertJsonPath('data.status', 'declined');

        expect($invitation->refresh()->status)->toBe(InvitationStatus::Declined)
            ->and($invitation->decline_reason)->toBe('لا نورد هذا الصنف');
        Event::assertDispatched(InvitationDeclined::class);
    });

    it('returns 409 for an invitation that is no longer pending', function (): void {
        Invitation::factory()->declined()->create(['competition_id' => $this->competition->id, 'token_hash' => hash('sha256', 'declined-token')]);

        $this->postJson('/api/app/v1/invitations/decline', ['token' => 'declined-token'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_state_transition');
    });
});

describe('POST /invitations/claim', function (): void {
    it('binds at once when the invited e-mail is the caller', function (): void {
        [$supplier, $supplierOwner] = Fixtures::supplier();
        $invitation = Invitation::factory()->sent('claim-token')->create(['competition_id' => $this->competition->id, 'email' => $supplierOwner->email]);
        Fixtures::signIn($supplierOwner);

        $this->postJson('/api/app/v1/invitations/claim', ['token' => 'claim-token'])
            ->assertOk()
            ->assertJsonPath('data.id', $invitation->public_id)
            ->assertJsonPath('data.competition.viewer_role', 'invitee')
            ->assertJsonStructure(['data' => ['id', 'status', 'join_deadline', 'sent_at', 'competition' => ['access', 'permissions']]]);

        expect($invitation->refresh()->organization_id)->toBe($supplier->id);
    });

    it('sends an OTP to the invited e-mail, then binds with the code', function (): void {
        config(['bafo.identity.otp.fake_code' => '123456']);
        [$supplier, $supplierOwner] = Fixtures::supplier();
        $invitation = Invitation::factory()->sent('claim-otp')->create(['competition_id' => $this->competition->id, 'email' => 'procurement@other.sa']);
        Fixtures::signIn($supplierOwner);

        $this->postJson('/api/app/v1/invitations/claim', ['token' => 'claim-otp'])
            ->assertStatus(202)
            ->assertJsonPath('data.otp_sent_to', 'p***@other.sa')
            ->assertJsonPath('data.otp_expires_at', fn (?string $v): bool => $v !== null);

        expect(OtpCode::query()->where('email', 'procurement@other.sa')->where('purpose', OtpPurpose::InvitationClaim->value)->exists())->toBeTrue()
            ->and($invitation->refresh()->organization_id)->toBeNull();

        $this->postJson('/api/app/v1/invitations/claim', ['token' => 'claim-otp', 'code' => '000000'])
            ->assertStatus(422)
            ->assertJsonPath('code', 'otp_invalid');

        $this->postJson('/api/app/v1/invitations/claim', ['token' => 'claim-otp', 'code' => '123456'])->assertOk();

        expect($invitation->refresh()->organization_id)->toBe($supplier->id);
    });

    it('returns 409 when the invitation belongs to another organization', function (): void {
        [$invitation] = Fixtures::invitee($this->competition, token: 'bound-token');
        [, $otherOwner] = Fixtures::supplier();
        Fixtures::signIn($otherOwner);

        $this->postJson('/api/app/v1/invitations/claim', ['token' => 'bound-token'])
            ->assertStatus(409)
            ->assertJsonPath('code', 'invitation_belongs_to_another_organization');

        expect($invitation->refresh()->organization_id)->not->toBeNull();
    });

    it('returns 401 for guests and 404 invitation_invalid for unknown tokens', function (): void {
        $this->postJson('/api/app/v1/invitations/claim', ['token' => 'x'])->assertUnauthorized();

        [, $owner] = Fixtures::supplier();
        Fixtures::signIn($owner);
        $this->postJson('/api/app/v1/invitations/claim', ['token' => 'unknown'])->assertNotFound()->assertJsonPath('code', 'invitation_invalid');
    });
});

describe('POST /invitations/{invitation}/join', function (): void {
    it('joins with the own plan and returns the participant projection', function (): void {
        Event::fake([InvitationJoined::class]);
        [$invitation, $supplier, $supplierOwner] = Fixtures::invitee($this->competition);
        Fixtures::signIn($supplierOwner);

        $this->postJson("/api/app/v1/invitations/{$invitation->public_id}/join", ['accept_terms' => true])
            ->assertOk()
            ->assertJsonPath('data.viewer_role', 'participant')
            ->assertJsonPath('data.access.state', 'full')
            ->assertJsonPath('data.access.coverage', 'own_plan')
            ->assertJsonStructure(['data' => ['participation' => ['participant_id', 'alias_no', 'joined_at', 'terms_accepted_at']]]);

        $participant = Participant::query()->where('competition_id', $this->competition->id)->where('organization_id', $supplier->id)->firstOrFail();

        expect($participant->entitlement_source)->toBe(EntitlementSource::Plan)
            ->and($participant->alias_no)->toBeBetween(1, 99)
            ->and($participant->joined_by_user_id)->toBe($supplierOwner->id)
            ->and($invitation->refresh()->status)->toBe(InvitationStatus::Joined)
            ->and(AuditLog::query()->where('action', 'invitation.joined')->where('organization_id', $supplier->id)->exists())->toBeTrue();
        Event::assertDispatched(InvitationJoined::class);
    });

    it('returns 422 terms_not_accepted without the terms', function (): void {
        [$invitation, , $supplierOwner] = Fixtures::invitee($this->competition);
        Fixtures::signIn($supplierOwner);

        $this->postJson("/api/app/v1/invitations/{$invitation->public_id}/join", ['accept_terms' => false])
            ->assertStatus(422)
            ->assertJsonPath('code', 'terms_not_accepted');
    });

    it('returns 403 plan_required without a plan or a pass', function (): void {
        [$invitation, , $supplierOwner] = Fixtures::invitee($this->competition, plan: false);
        Fixtures::signIn($supplierOwner);

        $this->postJson("/api/app/v1/invitations/{$invitation->public_id}/join", ['accept_terms' => true])
            ->assertForbidden()
            ->assertJsonPath('code', 'plan_required');

        expect(Participant::query()->where('competition_id', $this->competition->id)->exists())->toBeFalse();
    });

    it('returns 409 join_deadline_passed after the cut-off or on a closed competition', function (): void {
        [$invitation, , $supplierOwner] = Fixtures::invitee($this->competition);
        $this->competition->forceFill(['invitation_cutoff_at' => now()->subMinute()])->save();
        Fixtures::signIn($supplierOwner);

        $this->postJson("/api/app/v1/invitations/{$invitation->public_id}/join", ['accept_terms' => true])
            ->assertStatus(409)
            ->assertJsonPath('code', 'join_deadline_passed');
    });

    it('returns 409 already_participating and invalid_state_transition', function (): void {
        [$participant, $supplier, $supplierOwner] = Fixtures::participant(Competition::factory()->live()->create(['organization_id' => $this->org->id]));
        $competition = $participant->competition()->firstOrFail();
        $second = Invitation::factory()->sent()->create(['competition_id' => $competition->id, 'organization_id' => $supplier->id, 'email' => 'second@x.sa']);
        Fixtures::signIn($supplierOwner);

        $this->postJson("/api/app/v1/invitations/{$second->public_id}/join", ['accept_terms' => true])
            ->assertStatus(409)
            ->assertJsonPath('code', 'already_participating');

        $joined = $participant->invitation()->firstOrFail();
        $this->postJson("/api/app/v1/invitations/{$joined->public_id}/join", ['accept_terms' => true])
            ->assertStatus(409)
            ->assertJsonPath('code', 'invalid_state_transition');
    });

    it('returns 404 for an invitation of another organization', function (): void {
        [$invitation] = Fixtures::invitee($this->competition);
        [, $otherOwner] = Fixtures::supplier();
        Fixtures::signIn($otherOwner);

        $this->postJson("/api/app/v1/invitations/{$invitation->public_id}/join", ['accept_terms' => true])->assertNotFound();
    });
});

describe('POST /invitations/{invitation}/decline', function (): void {
    it('declines in the app and returns the invitee view', function (): void {
        [$invitation, , $supplierOwner] = Fixtures::invitee($this->competition, status: InvitationStatus::Viewed);
        Fixtures::signIn($supplierOwner);

        $this->postJson("/api/app/v1/invitations/{$invitation->public_id}/decline", ['reason' => 'مشغولون'])
            ->assertOk()
            ->assertJsonPath('data.status', 'declined')
            ->assertJsonPath('data.competition.permissions.can_join', false);

        expect($invitation->refresh()->status)->toBe(InvitationStatus::Declined);
    });

    it('keeps the declined organization an invitee (read-only teaser)', function (): void {
        [$invitation, , $supplierOwner] = Fixtures::invitee($this->competition, status: InvitationStatus::Declined);
        Fixtures::signIn($supplierOwner);

        $this->getJson("/api/app/v1/competitions/{$this->competition->public_id}")
            ->assertOk()
            ->assertJsonPath('data.viewer_role', 'invitee')
            ->assertJsonPath('data.access.state', 'unavailable')
            ->assertJsonPath('data.permissions.can_join', false);

        expect($this->competition->refresh()->status)->toBe(CompetitionStatus::Scheduled);
    });
});
