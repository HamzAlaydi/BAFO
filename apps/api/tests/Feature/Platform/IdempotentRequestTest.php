<?php

declare(strict_types=1);

use App\Support\Auth\Actor;
use App\Support\Auth\CurrentActor;
use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use App\Support\Idempotency\IdempotencyKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Tests\Support\Platform\TestUser;

/**
 * Stands in for Integrations' `api.client`: sets an API client actor.
 */
final class PlatformFakeApiClient
{
    public function handle(Request $request, Closure $next): mixed
    {
        $client = new class extends Model {};
        $client->forceFill(['id' => 5, 'organization_id' => 3, 'name' => 'ERP']);
        CurrentActor::set(Actor::forApiClient($client, $request));

        return $next($request);
    }
}

beforeEach(function () {
    $this->calls = 0;

    Route::prefix('api/app/v1/__idem')->middleware('app_v1')->group(function () {
        Route::post('things', function (Request $request) {
            $this->calls++;

            return ApiResponse::created(['n' => $this->calls, 'name' => $request->input('name'), 'tags' => [], 'meta' => new stdClass]);
        })->middleware('idempotent');

        Route::post('optional', function () {
            $this->calls++;

            return ApiResponse::created(['n' => $this->calls]);
        })->middleware('idempotent:optional');

        Route::post('conflict', function () {
            $this->calls++;

            throw new ApiException('conflict', status: 409);
        })->middleware('idempotent');

        Route::post('crash', function () {
            $this->calls++;

            throw new RuntimeException('boom');
        })->middleware('idempotent');

        Route::post('empty', function () {
            $this->calls++;

            return ApiResponse::noContent();
        })->middleware('idempotent');
    });

    TestUser::actingAs(id: 5);
});

it('requires the Idempotency-Key header', function () {
    $this->postJson('/api/app/v1/__idem/things', ['name' => 'a'])
        ->assertStatus(400)
        ->assertJsonPath('code', 'idempotency_key_required');

    expect($this->calls)->toBe(0);
});

it('rejects a malformed key', function (string $key) {
    $this->postJson('/api/app/v1/__idem/things', ['name' => 'a'], ['Idempotency-Key' => $key])
        ->assertStatus(400)
        ->assertJsonPath('code', 'idempotency_key_required');
})->with(['too short' => 'abc', 'bad characters' => 'key with spaces', 'too long' => str_repeat('k', 65)]);

it('runs the first request and stores its response for 24 hours', function () {
    $this->freezeTime();

    $this->postJson('/api/app/v1/__idem/things', ['name' => 'a'], ['Idempotency-Key' => 'intent-0001'])
        ->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed')
        ->assertJsonPath('data.n', 1);

    $row = IdempotencyKey::query()->sole();

    expect($row->scope_type)->toBe('user')
        ->and($row->scope_id)->toBe(5)
        ->and($row->method)->toBe('POST')
        ->and($row->path)->toBe('/api/app/v1/__idem/things')
        ->and($row->response_status)->toBe(201)
        ->and($row->expires_at->equalTo(now()->addHours(24)->startOfSecond()))->toBeTrue();
});

it('replays the stored response for the same request', function () {
    $first = $this->postJson('/api/app/v1/__idem/things', ['name' => 'a'], ['Idempotency-Key' => 'intent-0001'])->assertCreated();

    $replay = $this->postJson('/api/app/v1/__idem/things', ['name' => 'a'], ['Idempotency-Key' => 'intent-0001'])
        ->assertCreated()
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('data.n', 1);

    expect($this->calls)->toBe(1)
        ->and($replay->json())->toEqual($first->json())
        // Empty objects and lists survive the round trip (the stored body is sealed, so it comes
        // back byte for byte; SECURITY_REVIEW S-08).
        ->and($replay->getContent())->toMatch('/"tags":\s?\[\]/')
        ->and($replay->getContent())->toMatch('/"meta":\s?\{\}/');
});

it('rejects the same key with a different body', function () {
    $this->postJson('/api/app/v1/__idem/things', ['name' => 'a'], ['Idempotency-Key' => 'intent-0001'])->assertCreated();

    $this->postJson('/api/app/v1/__idem/things', ['name' => 'b'], ['Idempotency-Key' => 'intent-0001'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'idempotency_key_reused');

    expect($this->calls)->toBe(1);
});

it('rejects the same key on a different path', function () {
    $this->postJson('/api/app/v1/__idem/things', [], ['Idempotency-Key' => 'intent-0001'])->assertCreated();

    $this->postJson('/api/app/v1/__idem/optional', [], ['Idempotency-Key' => 'intent-0001'])
        ->assertStatus(422)
        ->assertJsonPath('code', 'idempotency_key_reused');
});

it('answers 409 while the first request is still running', function () {
    IdempotencyKey::query()->insert([
        'scope_type' => 'user',
        'scope_id' => 5,
        'key' => 'intent-0001',
        'method' => 'POST',
        'path' => '/api/app/v1/__idem/things',
        'request_hash' => hash('sha256', "POST\n/api/app/v1/__idem/things\n".json_encode(['name' => 'a'])),
        'response_status' => null,
        'response_body' => null,
        'expires_at' => now()->addDay(),
        'created_at' => now(),
    ]);

    $this->postJson('/api/app/v1/__idem/things', ['name' => 'a'], ['Idempotency-Key' => 'intent-0001'])
        ->assertStatus(409)
        ->assertJsonPath('code', 'idempotency_request_in_progress');

    expect($this->calls)->toBe(0);
});

it('scopes keys to the caller', function () {
    $this->postJson('/api/app/v1/__idem/things', ['name' => 'a'], ['Idempotency-Key' => 'intent-0001'])->assertCreated();

    TestUser::actingAs(id: 6);

    $this->postJson('/api/app/v1/__idem/things', ['name' => 'a'], ['Idempotency-Key' => 'intent-0001'])
        ->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed')
        ->assertJsonPath('data.n', 2);

    expect(IdempotencyKey::query()->count())->toBe(2);
});

it('scopes keys of API clients separately from users', function () {
    Route::prefix('api/public/v1/__idem')->middleware('public_v1')->post('things', function () {
        $this->calls++;

        return ApiResponse::created(['n' => $this->calls]);
    })->middleware([PlatformFakeApiClient::class, 'idempotent'])
        // The kernel owns the first three public_v1 entries; the client authentication and throttle that
        // Integrations appends are replaced here by the fake client above.
        ->withoutMiddleware(array_slice(app(Router::class)->getMiddlewareGroups()['public_v1'], 3));

    $this->postJson('/api/public/v1/__idem/things', [], ['Idempotency-Key' => 'erp-intent-01'])->assertCreated();

    expect(IdempotencyKey::query()->sole()->scope_type)->toBe('api_client');
});

it('stores and replays business errors', function () {
    $this->postJson('/api/app/v1/__idem/conflict', [], ['Idempotency-Key' => 'intent-0002'])->assertStatus(409);

    $this->postJson('/api/app/v1/__idem/conflict', [], ['Idempotency-Key' => 'intent-0002'])
        ->assertStatus(409)
        ->assertHeader('Idempotent-Replayed', 'true')
        ->assertJsonPath('code', 'conflict');

    expect($this->calls)->toBe(1);
});

it('releases the key after a server error so the client can retry', function () {
    config()->set('app.debug', false);

    $this->postJson('/api/app/v1/__idem/crash', [], ['Idempotency-Key' => 'intent-0003'])->assertStatus(500);
    $this->postJson('/api/app/v1/__idem/crash', [], ['Idempotency-Key' => 'intent-0003'])
        ->assertStatus(500)
        ->assertHeaderMissing('Idempotent-Replayed');

    expect($this->calls)->toBe(2)
        ->and(IdempotencyKey::query()->count())->toBe(0);
});

it('replays empty responses', function () {
    $this->postJson('/api/app/v1/__idem/empty', [], ['Idempotency-Key' => 'intent-0004'])->assertNoContent();

    $this->postJson('/api/app/v1/__idem/empty', [], ['Idempotency-Key' => 'intent-0004'])
        ->assertNoContent()
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($this->calls)->toBe(1);
});

it('treats an expired key as new', function () {
    $this->postJson('/api/app/v1/__idem/things', ['name' => 'a'], ['Idempotency-Key' => 'intent-0001'])->assertCreated();

    $this->travel(25)->hours();

    $this->postJson('/api/app/v1/__idem/things', ['name' => 'b'], ['Idempotency-Key' => 'intent-0001'])
        ->assertCreated()
        ->assertHeaderMissing('Idempotent-Replayed')
        ->assertJsonPath('data.n', 2);

    expect(IdempotencyKey::query()->count())->toBe(1);
});

it('runs without a key when the key is optional', function () {
    $this->postJson('/api/app/v1/__idem/optional')->assertCreated();
    $this->postJson('/api/app/v1/__idem/optional')->assertCreated();

    expect($this->calls)->toBe(2)
        ->and(IdempotencyKey::query()->count())->toBe(0);

    $this->postJson('/api/app/v1/__idem/optional', [], ['Idempotency-Key' => 'intent-0005'])->assertCreated();
    $this->postJson('/api/app/v1/__idem/optional', [], ['Idempotency-Key' => 'intent-0005'])
        ->assertHeader('Idempotent-Replayed', 'true');

    expect($this->calls)->toBe(3);
});

it('requires an authenticated caller', function () {
    Route::prefix('api/app/v1/__idem')->middleware('app_v1')->withoutMiddleware('auth:sanctum')
        ->post('guest', fn () => ApiResponse::created([]))->middleware('idempotent');

    app('auth')->forgetGuards();

    $this->postJson('/api/app/v1/__idem/guest', [], ['Idempotency-Key' => 'intent-0006'])
        ->assertUnauthorized();
});
