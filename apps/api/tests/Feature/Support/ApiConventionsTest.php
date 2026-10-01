<?php

declare(strict_types=1);

use App\Support\Exceptions\ApiException;
use App\Support\Http\ApiResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Route;
use Tests\Support\Platform\TestUser;

beforeEach(function () {
    Route::prefix('api/app/v1/__test')->middleware('app_v1')->group(function () {
        Route::get('protected', fn () => ApiResponse::ok(['ok' => true]));

        Route::withoutMiddleware('auth:sanctum')->group(function () {
            Route::post('validate', function (Request $request) {
                $request->validate(['email' => ['required', 'email']]);

                return ApiResponse::ok([]);
            });
            Route::get('domain-error', fn () => throw new ApiException('conflict', status: 409));
            Route::get('domain-error-details', fn () => throw new ApiException(
                'conflict',
                status: 409,
                details: ['quote' => ['amount_minor' => 11500, 'currency' => 'SAR']],
            ));
            Route::get('crash', fn () => throw new LogicException('boom'));
            Route::get('paginated', fn () => ApiResponse::paginated(
                new LengthAwarePaginator([['n' => 1], ['n' => 2]], 5, 2, 1),
            ));
        });
    });
});

describe('locale', function () {
    it('defaults to Arabic', function () {
        $this->getJson('/api/app/v1/__test/protected')
            ->assertHeader('Content-Language', 'ar')
            ->assertJsonPath('message', 'يجب تسجيل الدخول للمتابعة.');
    });

    it('switches to English with Accept-Language', function () {
        $this->getJson('/api/app/v1/__test/protected', ['Accept-Language' => 'en-US,en;q=0.9'])
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', 'You must be signed in to continue.');
    });

    it('localises errors raised before route middleware runs', function () {
        $this->getJson('/api/app/v1/does-not-exist', ['Accept-Language' => 'en'])
            ->assertNotFound()
            ->assertHeader('Content-Language', 'en')
            ->assertJsonPath('message', 'The requested resource was not found.');
    });

    it('falls back to Arabic for unsupported languages', function () {
        $this->getJson('/api/app/v1/__test/protected', ['Accept-Language' => 'fr-FR'])
            ->assertHeader('Content-Language', 'ar');
    });
});

describe('authentication', function () {
    it('requires a Sanctum token on app_v1 routes by default', function () {
        $this->getJson('/api/app/v1/__test/protected')
            ->assertUnauthorized()
            ->assertExactJson([
                'message' => 'يجب تسجيل الدخول للمتابعة.',
                'code' => 'unauthenticated',
                'errors' => [],
            ]);
    });

    it('lets an authenticated user through', function () {
        // An unsaved stand-in user: the kernel does not depend on the Identity schema.
        TestUser::actingAs();

        $response = $this->getJson('/api/app/v1/__test/protected')
            ->assertOk()
            ->assertJsonPath('data', ['ok' => true]);

        expect(array_keys($response->json()))->toBe(['data', 'meta'])
            ->and($response->json('meta.server_time'))->toBeIso8601Utc();
    });
});

describe('error envelope', function () {
    it('renders validation errors in Arabic', function () {
        $this->postJson('/api/app/v1/__test/validate', ['email' => 'not-an-email'])
            ->assertUnprocessable()
            ->assertJsonPath('code', 'validation_failed')
            ->assertJsonPath('message', 'البيانات المُدخلة غير صالحة.')
            ->assertJsonPath('errors.email.0', 'يجب أن يكون البريد الإلكتروني بريداً إلكترونياً صحيحاً.');
    });

    it('renders validation errors in English', function () {
        $this->postJson('/api/app/v1/__test/validate', [], ['Accept-Language' => 'en'])
            ->assertUnprocessable()
            ->assertJsonPath('errors.email.0', 'The email address field is required.');
    });

    it('renders domain exceptions with their code and status', function () {
        $this->getJson('/api/app/v1/__test/domain-error', ['Accept-Language' => 'en'])
            ->assertStatus(409)
            ->assertExactJson([
                'message' => 'The request conflicts with the current state of the resource.',
                'code' => 'conflict',
                'errors' => [],
            ]);
    });

    it('adds details only when the exception carries them', function () {
        $this->getJson('/api/app/v1/__test/domain-error-details')
            ->assertStatus(409)
            ->assertJsonPath('code', 'conflict')
            ->assertJsonPath('details.quote.amount_minor', 11500);

        $this->getJson('/api/app/v1/__test/domain-error')
            ->assertJsonMissingPath('details');
    });

    it('renders unknown routes as not_found', function () {
        $this->getJson('/api/app/v1/does-not-exist')
            ->assertNotFound()
            ->assertJsonPath('code', 'not_found')
            ->assertJsonPath('message', 'المورد المطلوب غير موجود.');
    });

    it('renders wrong methods as method_not_allowed', function () {
        $this->postJson('/api/app/v1/health')
            ->assertStatus(405)
            ->assertJsonPath('code', 'method_not_allowed');
    });

    it('hides internals of unexpected errors', function () {
        config()->set('app.debug', false);

        $this->getJson('/api/app/v1/__test/crash', ['Accept-Language' => 'en'])
            ->assertStatus(500)
            ->assertExactJson([
                'message' => 'Something went wrong. Please try again later.',
                'code' => 'server_error',
                'errors' => [],
            ]);
    });
});

describe('broadcasting and public api', function () {
    it('requires a token for channel auth', function () {
        $this->postJson('/broadcasting/auth', ['socket_id' => '1.1', 'channel_name' => 'private-user.x'])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    });

    it('answers the public api without meta.server_time', function () {
        // Only the module-agnostic part of public_v1 is under test: the client authentication and throttle
        // that Integrations appends (ARCH §2.2 item 7) are excluded.
        Route::prefix('api/public/v1/__test')->middleware('public_v1')->get('ping', fn () => ApiResponse::ok(['ok' => true]))
            ->withoutMiddleware(array_slice(app(Router::class)->getMiddlewareGroups()['public_v1'], 3));

        $this->getJson('/api/public/v1/__test/ping', ['Accept-Language' => 'en'])
            ->assertOk()
            ->assertHeader('Content-Language', 'en')
            ->assertExactJson(['data' => ['ok' => true], 'meta' => []]);
    });
});

describe('success envelope', function () {
    it('adds page pagination meta', function () {
        $this->getJson('/api/app/v1/__test/paginated')
            ->assertOk()
            ->assertJsonPath('data', [['n' => 1], ['n' => 2]])
            ->assertJsonPath('meta.pagination', [
                'type' => 'page',
                'current_page' => 1,
                'per_page' => 2,
                'has_more' => true,
                'total' => 5,
                'last_page' => 3,
            ])
            ->assertJsonStructure(['meta' => ['pagination', 'server_time']]);
    });
});
