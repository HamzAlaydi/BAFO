<?php

declare(strict_types=1);

use App\Support\Auth\ActorType;
use App\Support\Auth\Channel;
use App\Support\Auth\CurrentActor;
use App\Support\Http\ApiResponse;
use App\Support\Http\Middleware\AssignRequestId;
use App\Support\Http\Middleware\BlockDuringMaintenance;
use App\Support\Http\Middleware\EnsureSupportedAppVersion;
use App\Support\Http\Middleware\ForceJsonResponse;
use App\Support\Http\Middleware\IdempotentRequest;
use App\Support\Http\Middleware\ResolveActor;
use App\Support\Http\Middleware\SetLocaleFromHeader;
use App\Support\Settings\Settings;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Route;
use Tests\Support\Platform\TestUser;

beforeEach(function () {
    Route::prefix('api/app/v1/__kernel')->middleware('app_v1')->group(function () {
        Route::get('me', fn () => ApiResponse::ok(['actor' => (array) CurrentActor::get()]))->name('app.v1.__kernel.me');

        Route::withoutMiddleware('auth:sanctum')->group(function () {
            Route::get('guest', fn () => ApiResponse::ok([
                'actor' => (array) CurrentActor::get(),
                'context_request_id' => Context::get('request_id'),
            ]));
            Route::get('boom', fn () => throw new RuntimeException('boom'));
        });
    });
});

describe('middleware groups', function () {
    it('builds app_v1 in the contract order', function () {
        // Modules append their own entries after these nine (ARCH §2.2 item 7: Identity's EnsureAccountActive).
        expect(array_slice(app(Router::class)->getMiddlewareGroups()['app_v1'], 0, 9))->toBe([
            ForceJsonResponse::class,
            AssignRequestId::class,
            SetLocaleFromHeader::class,
            EnsureSupportedAppVersion::class,
            BlockDuringMaintenance::class,
            'auth:sanctum',
            ResolveActor::class,
            'throttle:app',
            SubstituteBindings::class,
        ]);
    });

    it('builds public_v1 with the module-agnostic part only', function () {
        expect(array_slice(app(Router::class)->getMiddlewareGroups()['public_v1'], 0, 3))->toBe([
            ForceJsonResponse::class,
            AssignRequestId::class,
            SetLocaleFromHeader::class,
        ]);
    });

    it('registers the idempotent alias', function () {
        expect(app(Router::class)->getMiddleware()['idempotent'] ?? null)->toBe(IdempotentRequest::class);
    });

    it('keeps the order after middleware sorting', function () {
        app(Router::class)->getRoutes()->refreshNameLookups();
        $route = app(Router::class)->getRoutes()->getByName('app.v1.__kernel.me');
        $sorted = app(Router::class)->gatherRouteMiddleware($route);

        $position = fn (string $needle): int|false => array_search(
            true,
            array_map(fn (string $m): bool => str_starts_with($m, $needle), $sorted),
            true,
        );

        expect($position(AssignRequestId::class))->toBeLessThan($position(SetLocaleFromHeader::class))
            ->and($position(BlockDuringMaintenance::class))->toBeLessThan($position('Illuminate\\Auth\\Middleware\\Authenticate'))
            ->and($position('Illuminate\\Auth\\Middleware\\Authenticate'))->toBeLessThan($position(ResolveActor::class))
            ->and($position(ResolveActor::class))->toBeLessThan($position('Illuminate\\Routing\\Middleware\\ThrottleRequests'))
            ->and($position('Illuminate\\Routing\\Middleware\\ThrottleRequests'))->toBeLessThan($position(SubstituteBindings::class));
    });
});

describe('request id', function () {
    it('echoes a valid client request id and puts it in the context', function () {
        $this->getJson('/api/app/v1/__kernel/guest', ['X-Request-Id' => 'client-req-0001'])
            ->assertOk()
            ->assertHeader('X-Request-Id', 'client-req-0001')
            ->assertJsonPath('data.context_request_id', 'client-req-0001')
            ->assertJsonPath('data.actor.requestId', 'client-req-0001');
    });

    it('generates a ULID when the header is missing or invalid', function (?string $given) {
        $headers = $given === null ? [] : ['X-Request-Id' => $given];
        $id = $this->getJson('/api/app/v1/__kernel/guest', $headers)->assertOk()->headers->get('X-Request-Id');

        expect($id)->toMatch('/^[0-9a-z]{26}$/')->not->toBe($given);
    })->with([
        'missing' => null,
        'too short' => 'abc',
        'bad characters' => 'id with spaces!!',
        'too long' => str_repeat('a', 65),
    ]);

    it('is sent on error responses too', function () {
        config()->set('app.debug', false);

        $this->getJson('/api/app/v1/__kernel/boom', ['X-Request-Id' => 'trace-me-123'])
            ->assertStatus(500)
            ->assertHeader('X-Request-Id', 'trace-me-123');

        $this->getJson('/api/app/v1/__kernel/me', ['X-Request-Id' => 'trace-me-456'])
            ->assertUnauthorized()
            ->assertHeader('X-Request-Id', 'trace-me-456');
    });

    it('is assigned on the public api', function () {
        Route::prefix('api/public/v1/__kernel')->middleware('public_v1')->get('ping', fn () => ApiResponse::ok([]));

        $this->getJson('/api/public/v1/__kernel/ping', ['X-Request-Id' => 'erp-call-0001'])
            ->assertHeader('X-Request-Id', 'erp-call-0001');
    });
});

describe('actor', function () {
    it('resolves a guest actor on guest routes', function () {
        $this->getJson('/api/app/v1/__kernel/guest', ['X-Platform' => 'android', 'User-Agent' => 'BafoApp/1.0'])
            ->assertJsonPath('data.actor.type', ActorType::Guest->value)
            ->assertJsonPath('data.actor.channel', Channel::Android->value)
            ->assertJsonPath('data.actor.ip', '127.0.0.1')
            ->assertJsonPath('data.actor.userAgent', 'BafoApp/1.0')
            ->assertJsonPath('data.actor.userId', null);
    });

    it('resolves the signed-in user with the channel from X-Platform', function (?string $platform, Channel $channel) {
        TestUser::actingAs(id: 42, organizationId: 9);

        $this->getJson('/api/app/v1/__kernel/me', $platform === null ? [] : ['X-Platform' => $platform])
            ->assertOk()
            ->assertJsonPath('data.actor.type', 'user')
            ->assertJsonPath('data.actor.id', 42)
            ->assertJsonPath('data.actor.userId', 42)
            ->assertJsonPath('data.actor.organizationId', 9)
            ->assertJsonPath('data.actor.label', 'Test User')
            ->assertJsonPath('data.actor.channel', $channel->value);
    })->with([
        'ios' => ['ios', Channel::Ios],
        'android' => ['ANDROID', Channel::Android],
        'web' => ['web', Channel::Web],
        'missing' => [null, Channel::Web],
        'unknown' => ['desktop', Channel::Web],
    ]);

    it('falls back to the system actor outside a request', function () {
        expect(CurrentActor::get()->type)->toBe(ActorType::System)
            ->and(CurrentActor::get()->channel)->toBe(Channel::System);
    });
});

describe('app version gate', function () {
    beforeEach(function () {
        app(Settings::class)->set('app.min_version.ios', '1.4.0', null);
        app(Settings::class)->set('app.min_version.android', '2.0.0', null);
    });

    it('answers 426 to a mobile app below the minimum version', function () {
        $this->getJson('/api/app/v1/__kernel/guest', ['X-Platform' => 'ios', 'X-App-Version' => '1.3.9', 'Accept-Language' => 'en'])
            ->assertStatus(426)
            ->assertJsonPath('code', 'app_version_unsupported')
            ->assertJsonPath('message', 'This version of the app is no longer supported. Please update the app to continue.')
            ->assertJsonPath('details.min_version', '1.4.0');
    });

    it('lets supported versions through', function (string $platform, string $version) {
        $this->getJson('/api/app/v1/__kernel/guest', ['X-Platform' => $platform, 'X-App-Version' => $version])->assertOk();
    })->with([
        'ios at the minimum' => ['ios', '1.4.0'],
        'ios above' => ['ios', '1.10.0'],
        'android above with build metadata' => ['android', '2.0.1+45'],
    ]);

    it('checks the platform-specific minimum', function () {
        $this->getJson('/api/app/v1/__kernel/guest', ['X-Platform' => 'android', 'X-App-Version' => '1.9.9'])
            ->assertStatus(426)
            ->assertJsonPath('details.min_version', '2.0.0');
    });

    it('never blocks the web client', function () {
        $this->getJson('/api/app/v1/__kernel/guest', ['X-Platform' => 'web', 'X-App-Version' => '0.0.1'])->assertOk();
    });

    it('does not block a missing or malformed version', function (?string $version) {
        $headers = ['X-Platform' => 'ios'] + ($version === null ? [] : ['X-App-Version' => $version]);

        $this->getJson('/api/app/v1/__kernel/guest', $headers)->assertOk();
    })->with(['missing' => null, 'malformed' => 'latest']);

    it('keeps app-config, time and health reachable for outdated apps', function (string $path) {
        $this->getJson("/api/app/v1/{$path}", ['X-Platform' => 'ios', 'X-App-Version' => '0.1.0'])
            ->assertSuccessful();
    })->with(['app-config', 'time', 'health']);
});

describe('maintenance', function () {
    beforeEach(function () {
        app(Settings::class)->set('app.maintenance.enabled', true, null);
    });

    it('answers 503 maintenance to app v1 requests', function () {
        $this->getJson('/api/app/v1/__kernel/guest', ['Accept-Language' => 'en'])
            ->assertStatus(503)
            ->assertHeader('Retry-After', '300')
            ->assertJsonPath('code', 'maintenance')
            ->assertJsonPath('message', 'The platform is under maintenance. Please try again later.');
    });

    it('shows the admin message in the request language', function () {
        app(Settings::class)->set('app.maintenance.message', ['ar' => 'نعمل على تحسين المنصة.', 'en' => 'We are improving the platform.'], null);

        $this->getJson('/api/app/v1/__kernel/guest')
            ->assertStatus(503)
            ->assertJsonPath('message', 'نعمل على تحسين المنصة.');

        $this->getJson('/api/app/v1/__kernel/guest', ['Accept-Language' => 'en'])
            ->assertJsonPath('message', 'We are improving the platform.');
    });

    it('blocks before authentication', function () {
        $this->getJson('/api/app/v1/__kernel/me')->assertStatus(503);
    });

    it('keeps app-config, time and health reachable', function (string $path) {
        $this->getJson("/api/app/v1/{$path}")->assertSuccessful();
    })->with(['app-config', 'time', 'health']);

    it('lets requests through once disabled', function () {
        app(Settings::class)->set('app.maintenance.enabled', false, null);

        $this->getJson('/api/app/v1/__kernel/guest')->assertOk();
    });
});
