<?php

declare(strict_types=1);

use App\Modules\Competitions\Enums\CompetitionStatus;
use App\Modules\Competitions\Models\Competition;
use App\Modules\Competitions\Models\Participant;
use App\Modules\Identity\Enums\DeletionScope;
use App\Modules\Identity\Enums\DeletionStatus;
use App\Modules\Identity\Enums\OrganizationStatus;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Enums\UserStatus;
use App\Modules\Identity\Events\AccountDeleted;
use App\Modules\Identity\Mail\AccountDeletionScheduledMail;
use App\Modules\Identity\Models\AccountDeletionRequest;
use App\Modules\Identity\Models\Membership;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Files\File;
use App\Support\Files\FilePurpose;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\Identity\Accounts;

beforeEach(function () {
    Mail::fake();
});

describe('POST /account/deletion', function () {
    it('schedules a member leaving in 14 days and revokes the other tokens', function () {
        $this->travelTo('2026-10-01 10:00:00');
        $member = Accounts::member(Organization::factory()->create());
        $member->createToken('other device');
        $headers = Accounts::headers($member, 'this device');

        $response = $this->postJson('/api/app/v1/account/deletion', ['password' => Accounts::PASSWORD, 'reason' => 'Leaving the company'], $headers)
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'scope', 'status', 'scheduled_for', 'created_at']])
            ->assertJsonPath('data.scope', 'user')
            ->assertJsonPath('data.status', 'pending')
            ->assertJsonPath('data.scheduled_for', '2026-10-15T10:00:00.000Z');

        $request = AccountDeletionRequest::query()->sole();

        expect($request->public_id)->toBe($response->json('data.id'))
            ->and($request->reason)->toBe('Leaving the company')
            ->and(PersonalAccessToken::query()->pluck('name')->all())->toBe(['this device'])
            ->and(AuditLog::query()->where('action', 'account_deletion.requested')->exists())->toBeTrue();

        Mail::assertQueued(AccountDeletionScheduledMail::class, fn (AccountDeletionScheduledMail $mail): bool => $mail->hasTo($member->email) && $mail->scope === DeletionScope::User);
    });

    it('deletes the whole organization when the owner asks', function () {
        $owner = Accounts::owner();

        $this->postJson('/api/app/v1/account/deletion', ['password' => Accounts::PASSWORD], Accounts::headers($owner))
            ->assertCreated()
            ->assertJsonPath('data.scope', 'organization');
    });

    it('is blocked by open competitions and participations', function () {
        $owner = Accounts::owner();
        $organizationId = $owner->membership->organization_id;
        $issued = Competition::factory()->create(['organization_id' => $organizationId, 'status' => CompetitionStatus::Live, 'title' => 'توريد أجهزة']);
        Competition::factory()->create(['organization_id' => $organizationId, 'status' => CompetitionStatus::Awarded]);
        $other = Competition::factory()->create(['status' => CompetitionStatus::Scheduled, 'title' => 'صيانة المباني']);
        Participant::factory()->create(['competition_id' => $other->id, 'organization_id' => $organizationId]);

        $this->postJson('/api/app/v1/account/deletion', ['password' => Accounts::PASSWORD], Accounts::headers($owner))
            ->assertStatus(409)
            ->assertJsonPath('code', 'account_deletion_blocked')
            ->assertJsonPath('details.blockers', [
                ['type' => 'issued_competition', 'competition_id' => $issued->public_id, 'title' => 'توريد أجهزة'],
                ['type' => 'participation', 'competition_id' => $other->public_id, 'title' => 'صيانة المباني'],
            ]);

        expect(AccountDeletionRequest::query()->count())->toBe(0);
    });

    it('does not block a member leaving', function () {
        $organization = Organization::factory()->create();
        Accounts::owner($organization);
        $member = Accounts::member($organization);
        Competition::factory()->create(['organization_id' => $organization->id, 'status' => CompetitionStatus::Live]);

        $this->postJson('/api/app/v1/account/deletion', ['password' => Accounts::PASSWORD], Accounts::headers($member))->assertCreated();
    });

    it('checks the password', function () {
        $this->postJson('/api/app/v1/account/deletion', ['password' => 'Wrong-Passw0rd!'], Accounts::headers(Accounts::owner()))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'password_incorrect');
    });

    it('refuses a second pending request', function () {
        $owner = Accounts::owner();
        $headers = Accounts::headers($owner);
        $this->postJson('/api/app/v1/account/deletion', ['password' => Accounts::PASSWORD], $headers)->assertCreated();

        $this->postJson('/api/app/v1/account/deletion', ['password' => Accounts::PASSWORD], $headers)
            ->assertStatus(409)
            ->assertJsonPath('code', 'account_deletion_pending');
    });

    it('stays available to a suspended organization', function () {
        $owner = Accounts::owner(Organization::factory()->suspended()->create());

        $this->postJson('/api/app/v1/account/deletion', ['password' => Accounts::PASSWORD], Accounts::headers($owner))->assertCreated();
    });

    it('validates the input', function () {
        $this->postJson('/api/app/v1/account/deletion', ['reason' => str_repeat('x', 1001)], Accounts::headers(Accounts::owner()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password', 'reason']);
    });

    it('requires a token', function () {
        $this->postJson('/api/app/v1/account/deletion', ['password' => 'x'])->assertUnauthorized();
    });
});

describe('GET and DELETE /account/deletion', function () {
    it('shows the pending request, then cancels it', function () {
        $owner = Accounts::owner();
        $headers = Accounts::headers($owner);

        $this->getJson('/api/app/v1/account/deletion', $headers)->assertOk()->assertJsonPath('data', null);

        $id = $this->postJson('/api/app/v1/account/deletion', ['password' => Accounts::PASSWORD], $headers)->json('data.id');

        $this->getJson('/api/app/v1/account/deletion', $headers)->assertOk()->assertJsonPath('data.id', $id);

        $this->deleteJson('/api/app/v1/account/deletion', [], $headers)->assertNoContent();

        expect(AccountDeletionRequest::query()->sole())
            ->status->toBe(DeletionStatus::Cancelled)
            ->cancelled_at->not->toBeNull();

        $this->getJson('/api/app/v1/account/deletion', $headers)->assertJsonPath('data', null);
        $this->deleteJson('/api/app/v1/account/deletion', [], $headers)->assertNotFound();
    });
});

describe('identity:execute-account-deletions', function () {
    it('anonymises a member once the request is due', function () {
        Event::fake([AccountDeleted::class]);
        $organization = Organization::factory()->create();
        Accounts::owner($organization);
        $member = Accounts::member($organization);
        $request = AccountDeletionRequest::factory()->create(['user_id' => $member->id, 'organization_id' => $organization->id, 'scope' => DeletionScope::User]);

        $this->artisan('identity:execute-account-deletions')->assertSuccessful();
        expect($request->refresh()->status)->toBe(DeletionStatus::Pending);

        $this->travel(15)->days();
        $this->artisan('identity:execute-account-deletions')->assertSuccessful();

        $user = User::withTrashed()->findOrFail($member->id);

        expect($request->refresh()->status)->toBe(DeletionStatus::Completed)
            ->and($user->trashed())->toBeTrue()
            ->and($user->status)->toBe(UserStatus::Deleted)
            ->and($user->name)->toBe('Deleted user')
            ->and(Membership::query()->where('user_id', $member->id)->exists())->toBeFalse()
            ->and($organization->refresh()->status)->toBe(OrganizationStatus::Active);

        Event::assertDispatched(AccountDeleted::class, fn (AccountDeleted $event): bool => $event->userId === $member->id
            && $event->organizationId === $organization->id
            && $event->scope === DeletionScope::User);
    });

    it('deletes the organization and every member for an owner request', function () {
        Event::fake([AccountDeleted::class]);
        Storage::fake('public');
        $organization = Organization::factory()->create();
        $owner = Accounts::owner($organization);
        $admin = Accounts::member($organization, OrgRole::Admin);
        $logo = File::factory()->purpose(FilePurpose::OrganizationLogo)->create(['organization_id' => $organization->id]);
        $organization->forceFill(['logo_file_id' => $logo->id])->save();
        AccountDeletionRequest::factory()->organizationScope()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'scheduled_for' => now()->subMinute(),
        ]);

        $this->artisan('identity:execute-account-deletions')->assertSuccessful();

        $organization = Organization::withTrashed()->findOrFail($organization->id);

        expect($organization->trashed())->toBeTrue()
            ->and($organization->status)->toBe(OrganizationStatus::Deleted)
            ->and($organization->name)->toBe('Deleted organization')
            ->and($organization->logo_file_id)->toBeNull()
            ->and(File::query()->whereKey($logo->id)->exists())->toBeFalse()
            ->and(User::query()->whereIn('id', [$owner->id, $admin->id])->count())->toBe(0)
            ->and(Membership::query()->where('organization_id', $organization->id)->count())->toBe(0)
            ->and(AuditLog::query()->where('action', 'account_deletion.completed')->exists())->toBeTrue();

        Event::assertDispatched(AccountDeleted::class, 2);
        Event::assertDispatched(AccountDeleted::class, fn (AccountDeleted $event): bool => $event->userId === $owner->id && $event->scope === DeletionScope::Organization);
        Event::assertDispatched(AccountDeleted::class, fn (AccountDeleted $event): bool => $event->userId === $admin->id && $event->scope === DeletionScope::User);
    });

    it('postpones an organization deletion while a competition is open', function () {
        $organization = Organization::factory()->create();
        $owner = Accounts::owner($organization);
        $request = AccountDeletionRequest::factory()->organizationScope()->create([
            'user_id' => $owner->id,
            'organization_id' => $organization->id,
            'scheduled_for' => now()->subMinute(),
        ]);
        Competition::factory()->create(['organization_id' => $organization->id, 'status' => CompetitionStatus::Live]);

        $this->artisan('identity:execute-account-deletions')->assertSuccessful();

        expect($request->refresh()->status)->toBe(DeletionStatus::Pending)
            ->and($owner->refresh()->trashed())->toBeFalse();
    });
});
