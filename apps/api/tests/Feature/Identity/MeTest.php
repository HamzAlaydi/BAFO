<?php

declare(strict_types=1);

use App\Modules\Billing\Enums\SubscriptionSource;
use App\Modules\Billing\Enums\SubscriptionStatus;
use App\Modules\Billing\Models\Plan;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Support\Audit\AuditLog;
use App\Support\Files\File;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\Support\Identity\Accounts;

describe('GET /me', function () {
    it('returns the Me payload of an owner', function () {
        $user = Accounts::owner();

        $response = $this->getJson('/api/app/v1/me', Accounts::headers($user))
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'user' => ['id', 'name', 'email', 'phone', 'locale', 'avatar_url', 'status', 'email_verified_at', 'created_at'],
                    'organization' => ['id', 'name', 'legal_name_ar', 'legal_name_en', 'cr_number', 'vat_registered', 'vat_number', 'region', 'city',
                        'national_address', 'website', 'email', 'phone', 'logo_url', 'profile_document', 'categories', 'visible_in_suggestions',
                        'status', 'verified', 'features', 'billing_profile_complete', 'billing_profile_missing', 'trial_available', 'created_at'],
                    'membership' => ['id', 'role', 'can_award', 'can_purchase', 'status'],
                    'permissions',
                    'subscription',
                    'entitlements' => ['can_issue', 'seats_used', 'seats_total'],
                    'unread_notifications_count',
                ],
                'meta' => ['server_time'],
            ])
            ->assertJsonMissingPath('data.token')
            ->assertJsonPath('data.user.id', $user->public_id)
            ->assertJsonPath('data.membership.role', 'owner')
            ->assertJsonPath('data.subscription', null)
            ->assertJsonPath('data.entitlements', ['can_issue' => false, 'seats_used' => 1, 'seats_total' => 1])
            ->assertJsonPath('data.unread_notifications_count', 0);

        expect($response->json('data.permissions'))->toBe([
            'organization.update', 'team.manage', 'billing.view', 'billing.purchase', 'competitions.create',
            'competitions.manage_all', 'competitions.award', 'participation.submit_offers', 'integrations.manage',
            'account.delete_organization',
        ])->and($response->json('data.user.created_at'))->toBeIso8601Utc();
    });

    it('derives the permissions from the role and flags', function (OrgRole $role, array $flags, array $expected) {
        $user = Accounts::member(Organization::factory()->create(), $role);
        $user->membership->forceFill($flags)->save();

        $response = $this->getJson('/api/app/v1/me', Accounts::headers($user))->assertOk();

        expect($response->json('data.permissions'))->toBe($expected)
            ->and($response->json('data.membership.can_award'))->toBe($flags['can_award']);
    })->with([
        'admin with both flags' => [OrgRole::Admin, ['can_award' => true, 'can_purchase' => true], [
            'organization.update', 'team.manage', 'billing.view', 'billing.purchase', 'competitions.create',
            'competitions.manage_all', 'competitions.award', 'participation.submit_offers', 'integrations.manage',
        ]],
        'admin without flags' => [OrgRole::Admin, ['can_award' => false, 'can_purchase' => false], [
            'organization.update', 'team.manage', 'billing.view', 'competitions.create',
            'competitions.manage_all', 'participation.submit_offers', 'integrations.manage',
        ]],
        'member' => [OrgRole::Member, ['can_award' => false, 'can_purchase' => false], [
            'competitions.create', 'participation.submit_offers',
        ]],
        'member who may award' => [OrgRole::Member, ['can_award' => true, 'can_purchase' => false], [
            'competitions.create', 'competitions.award', 'participation.submit_offers',
        ]],
    ]);

    it('includes the current subscription and the seats of its plan', function () {
        $this->travelTo('2026-10-10 09:00:00');
        $user = Accounts::owner();
        $plan = Plan::factory()->create(['code' => 'pro', 'name' => ['ar' => 'باقة برو', 'en' => 'Pro plan'], 'seats' => 3]);
        Subscription::factory()->create([
            'organization_id' => $user->membership->organization_id,
            'plan_id' => $plan->id,
            'source' => SubscriptionSource::Paid,
            'status' => SubscriptionStatus::Active,
            'seats' => 3,
            'starts_at' => '2026-10-01 00:00:00',
            'ends_at' => '2026-10-31 00:00:00',
        ]);

        $this->getJson('/api/app/v1/me', Accounts::headers($user))
            ->assertOk()
            ->assertJsonPath('data.subscription', [
                'plan' => ['id' => $plan->public_id, 'code' => 'pro', 'name' => 'باقة برو'],
                'source' => 'paid',
                'status' => 'active',
                'ends_at' => '2026-10-31T00:00:00.000Z',
                'days_left' => 21,
                'total_days' => 30,
            ])
            ->assertJsonPath('data.entitlements', ['can_issue' => true, 'seats_used' => 1, 'seats_total' => 3])
            ->assertJsonPath('data.organization.trial_available', false);
    });

    it('counts the unread notifications', function () {
        $user = Accounts::owner();

        foreach ([null, null, now()] as $readAt) {
            $user->notifications()->create([
                'id' => strtolower((string) Str::ulid()),
                'type' => 'competition.invited',
                'data' => ['type' => 'competition.invited'],
                'read_at' => $readAt,
            ]);
        }

        $this->getJson('/api/app/v1/me', Accounts::headers($user))->assertJsonPath('data.unread_notifications_count', 2);
    });

    it('requires a token', function () {
        $this->getJson('/api/app/v1/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
    });
});

describe('PATCH /me', function () {
    it('updates the name, phone and language', function () {
        $user = Accounts::owner();

        $this->patchJson('/api/app/v1/me', ['name' => ' Mona Saleh ', 'phone' => '+966555555555', 'locale' => 'en'], Accounts::headers($user))
            ->assertOk()
            ->assertJsonPath('data.user.name', 'Mona Saleh')
            ->assertJsonPath('data.user.phone', '+966555555555')
            ->assertJsonPath('data.user.locale', 'en');

        expect(AuditLog::query()->where('action', 'user.updated')->sole()->changes)->toHaveKeys(['name', 'phone', 'locale']);
    });

    it('validates the fields', function () {
        $user = Accounts::owner();

        $this->patchJson('/api/app/v1/me', ['name' => '', 'phone' => '0555555555', 'locale' => 'fr'], Accounts::headers($user))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'phone', 'locale']);
    });

    it('requires a token', function () {
        $this->patchJson('/api/app/v1/me', ['name' => 'x'])->assertUnauthorized();
    });
});

describe('PUT /me/password', function () {
    it('changes the password and revokes the other tokens', function () {
        $user = Accounts::owner();
        $user->createToken('other device');
        $headers = Accounts::headers($user, 'this device');

        $this->putJson('/api/app/v1/me/password', [
            'current_password' => Accounts::PASSWORD,
            'password' => Accounts::NEW_PASSWORD,
            'password_confirmation' => Accounts::NEW_PASSWORD,
        ], $headers)->assertNoContent();

        expect(Hash::check(Accounts::NEW_PASSWORD, (string) $user->refresh()->password))->toBeTrue()
            ->and(PersonalAccessToken::query()->pluck('name')->all())->toBe(['this device']);

        $this->getJson('/api/app/v1/me', Accounts::bearer(substr($headers['Authorization'], 7)))->assertOk();
    });

    it('rejects a wrong current password', function () {
        $user = Accounts::owner();

        $this->putJson('/api/app/v1/me/password', [
            'current_password' => 'Wrong-Passw0rd!',
            'password' => Accounts::NEW_PASSWORD,
            'password_confirmation' => Accounts::NEW_PASSWORD,
        ], Accounts::headers($user))
            ->assertUnprocessable()
            ->assertJsonPath('code', 'password_incorrect');
    });

    it('enforces the password rule', function () {
        $this->putJson('/api/app/v1/me/password', ['current_password' => 'x', 'password' => 'short', 'password_confirmation' => 'short'], Accounts::headers(Accounts::owner()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['password']);
    });
});

describe('avatar', function () {
    beforeEach(function () {
        Storage::fake('public');
    });

    it('uploads an avatar to the public disk and replaces the previous one', function () {
        $user = Accounts::owner();
        $headers = Accounts::headers($user);

        $first = $this->post('/api/app/v1/me/avatar', ['file' => UploadedFile::fake()->image('me.png')], [...$headers, 'Accept' => 'application/json'])
            ->assertOk()
            ->json('data.user.avatar_url');

        expect($first)->toBeString()->toContain('/storage/user_avatar/');
        $oldFile = File::query()->sole();

        $this->post('/api/app/v1/me/avatar', ['file' => UploadedFile::fake()->image('me.jpg')], [...$headers, 'Accept' => 'application/json'])->assertOk();

        expect(File::query()->count())->toBe(1)
            ->and(File::query()->whereKey($oldFile->id)->exists())->toBeFalse();
        Storage::disk('public')->assertMissing($oldFile->path);
    });

    it('removes the avatar', function () {
        $user = Accounts::owner();
        $headers = Accounts::headers($user);
        $this->post('/api/app/v1/me/avatar', ['file' => UploadedFile::fake()->image('me.png')], [...$headers, 'Accept' => 'application/json'])->assertOk();

        $this->deleteJson('/api/app/v1/me/avatar', [], $headers)
            ->assertOk()
            ->assertJsonPath('data.user.avatar_url', null);

        expect(File::query()->count())->toBe(0);
    });

    it('rejects a file of the wrong type', function () {
        $this->post('/api/app/v1/me/avatar', ['file' => UploadedFile::fake()->create('cv.pdf', 10, 'application/pdf')], [...Accounts::headers(Accounts::owner()), 'Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'file_type_not_allowed');
    });

    it('requires a file', function () {
        $this->postJson('/api/app/v1/me/avatar', [], Accounts::headers(Accounts::owner()))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file']);
    });
});
