<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\User;
use App\Modules\Notifications\Models\DeviceToken;
use App\Support\Audit\AuditLog;
use Illuminate\Support\Facades\Auth;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\travel;

/*
 * Push device registration (API.md §1.8, resource §2.11).
 */

beforeEach(function () {
    $this->user = User::factory()->withMembership(null, OrgRole::Owner)->create();
    $this->other = User::factory()->withMembership(null, OrgRole::Owner)->create();
    $this->payload = [
        'token' => 'fcm-token-'.str_repeat('a', 140),
        'platform' => 'android',
        'device_name' => 'Pixel 8 · android',
        'app_version' => '1.0.0',
    ];
});

describe('POST /devices', function () {
    it('registers the device and never returns the token', function () {
        Sanctum::actingAs($this->user);

        $response = $this->postJson('/api/app/v1/devices', $this->payload, ['Accept-Language' => 'en'])
            ->assertCreated()
            ->assertJsonStructure(['data' => ['id', 'platform', 'device_name', 'app_version', 'last_seen_at'], 'meta'])
            ->assertJsonPath('data.platform', 'android')
            ->assertJsonPath('data.device_name', 'Pixel 8 · android')
            ->assertJsonPath('data.app_version', '1.0.0')
            ->assertJsonMissingPath('data.token');

        $device = DeviceToken::query()->sole();
        expect($response->json('data.id'))->toBe($device->public_id)
            ->and($response->json('data.last_seen_at'))->toBeIso8601Utc()
            ->and($device->user_id)->toBe($this->user->id)
            ->and($device->locale)->toBe('en')
            ->and(AuditLog::query()->where('action', 'device.registered')->count())->toBe(1)
            ->and(json_encode(AuditLog::query()->sole()->toArray()))->not->toContain($this->payload['token']);
    });

    it('upserts by token: a refresh keeps one row and moves last_seen_at, without a new audit entry', function () {
        Sanctum::actingAs($this->user);
        $this->postJson('/api/app/v1/devices', $this->payload)->assertCreated();
        $firstSeen = DeviceToken::query()->sole()->last_seen_at;
        travel(2)->hours();

        $this->postJson('/api/app/v1/devices', [...$this->payload, 'app_version' => '1.1.0'])->assertCreated();

        $device = DeviceToken::query()->sole();
        expect($device->app_version)->toBe('1.1.0')
            ->and($device->last_seen_at->greaterThan($firstSeen))->toBeTrue()
            ->and(AuditLog::query()->where('action', 'device.registered')->count())->toBe(1);
    });

    it('re-assigns a known token to the user who registers it', function () {
        $device = DeviceToken::factory()->create(['user_id' => $this->other->id, 'token' => $this->payload['token']]);
        Sanctum::actingAs($this->user);

        $this->postJson('/api/app/v1/devices', $this->payload)->assertCreated()->assertJsonPath('data.id', $device->public_id);

        expect($device->fresh()?->user_id)->toBe($this->user->id)
            ->and(AuditLog::query()->where('action', 'device.registered')->sole()->meta)->toMatchArray(['reassigned' => true]);
    });

    it('validates the input', function (array $override, string $field) {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/app/v1/devices', [...$this->payload, ...$override])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonValidationErrors([$field]);

        expect(DeviceToken::query()->count())->toBe(0);
    })->with([
        'missing token' => [['token' => null], 'token'],
        'token too long' => [['token' => str_repeat('t', 513)], 'token'],
        'unknown platform' => [['platform' => 'windows'], 'platform'],
        'missing platform' => [['platform' => null], 'platform'],
        'device name too long' => [['device_name' => str_repeat('n', 121)], 'device_name'],
        'not semver' => [['app_version' => 'v1'], 'app_version'],
    ]);

    it('explains a bad version in the request language', function () {
        Sanctum::actingAs($this->user);

        $this->postJson('/api/app/v1/devices', [...$this->payload, 'app_version' => '1.0'], ['Accept-Language' => 'en'])
            ->assertJsonPath('errors.app_version.0', 'The app version must be a version number such as 1.0.0.');
    });

    it('requires authentication', function () {
        $this->postJson('/api/app/v1/devices', $this->payload)->assertUnauthorized();
    });
});

describe('DELETE /devices/{device}', function () {
    it('removes the user\'s device', function () {
        $device = DeviceToken::factory()->create(['user_id' => $this->user->id]);
        Sanctum::actingAs($this->user);

        $this->deleteJson('/api/app/v1/devices/'.strtoupper($device->public_id))->assertNoContent();

        expect(DeviceToken::query()->find($device->id))->toBeNull()
            ->and(AuditLog::query()->where('action', 'device.removed')->count())->toBe(1);
    });

    it('answers 404 for another user\'s device or an unknown id', function () {
        $theirs = DeviceToken::factory()->create(['user_id' => $this->other->id]);
        Sanctum::actingAs($this->user);

        $this->deleteJson('/api/app/v1/devices/'.$theirs->public_id)->assertNotFound()->assertJsonPath('code', 'not_found');
        $this->deleteJson('/api/app/v1/devices/01j9zq4m1x2a3b4c5d6e7f8g9h')->assertNotFound();

        expect(DeviceToken::query()->find($theirs->id))->not->toBeNull();
    });

    it('requires authentication', function () {
        $device = DeviceToken::factory()->create(['user_id' => $this->user->id]);
        Auth::forgetGuards();

        $this->deleteJson('/api/app/v1/devices/'.$device->public_id)->assertUnauthorized();
    });
});
