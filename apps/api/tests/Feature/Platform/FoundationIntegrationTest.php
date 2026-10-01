<?php

declare(strict_types=1);

use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\Organization;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditLog;
use App\Support\Audit\AuditLogger;
use App\Support\Auth\CurrentActor;
use App\Support\Database\MorphMap;
use App\Support\Files\File;
use App\Support\Files\FileAccessRegistry;
use App\Support\Files\FilePurpose;
use App\Support\Http\ApiResponse;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\PersonalAccessToken;

/*
|--------------------------------------------------------------------------
| Foundation integration (kernel × schema)
|--------------------------------------------------------------------------
|
| The kernel (app/Support) and the module schema were built in parallel. These checks run
| them together with the real Identity user and real Sanctum bearer tokens (no
| Sanctum::actingAs), so module engineers can rely on the wiring from day one.
|
*/

/**
 * An organization owner with a personal access token, as Identity's login will issue it.
 *
 * @return array{0: User, 1: Organization, 2: string}
 */
function foundationSignedInOwner(): array
{
    $organization = Organization::factory()->create();
    $user = User::factory()->withMembership($organization, OrgRole::Owner)->create();

    return [$user, $organization, $user->createToken('web')->plainTextToken];
}

/**
 * Every Eloquent model class of the app: module models and the kernel models.
 *
 * @return list<class-string<Model>>
 */
function foundationModelClasses(): array
{
    $files = [
        ...(glob(app_path('Modules/*/Models/*.php')) ?: []),
        ...(glob(app_path('Support/*/*.php')) ?: []),
    ];
    $classes = [];

    foreach ($files as $file) {
        $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], mb_substr($file, mb_strlen(app_path()) + 1));

        if (class_exists($class) && is_subclass_of($class, Model::class) && ! (new ReflectionClass($class))->isAbstract()) {
            $classes[] = $class;
        }
    }

    sort($classes);

    return $classes;
}

beforeEach(function () {
    Route::prefix('api/app/v1/__foundation')->middleware('app_v1')->group(function () {
        Route::get('me', fn (Request $request) => ApiResponse::ok([
            'user_class' => $request->user()::class,
            'user_id' => $request->user()?->getAttribute('public_id'),
            'actor' => (array) CurrentActor::get(),
        ]));

        Route::post('audit', function (Request $request) {
            AuditLogger::log('member.added', $request->user());

            return ApiResponse::ok(['logged' => true]);
        });
    });
});

describe('auth configuration', function () {
    it('points the users provider at the Identity user', function () {
        expect(config('auth.providers.users.model'))->toBe(User::class)
            ->and(Auth::createUserProvider('users')?->getModel())->toBe(User::class);
    });

    it('authenticates app v1 with Sanctum bearer tokens only', function () {
        expect(config('sanctum.guard'))->toBe([])
            ->and(config('sanctum.stateful'))->toBe([])
            ->and(config('sanctum.expiration'))->toBe(129600);
    });
});

describe('sanctum bearer tokens with the Identity user', function () {
    it('authenticates the Identity user by a personal access token and resolves the actor', function () {
        [$user, $organization, $token] = foundationSignedInOwner();

        $this->getJson('/api/app/v1/__foundation/me', [
            'Authorization' => "Bearer {$token}",
            'X-Platform' => 'ios',
            'User-Agent' => 'BafoApp/1.0',
        ])
            ->assertOk()
            ->assertJsonPath('data.user_class', User::class)
            ->assertJsonPath('data.user_id', $user->public_id)
            ->assertJsonPath('data.actor.type', 'user')
            ->assertJsonPath('data.actor.id', $user->id)
            ->assertJsonPath('data.actor.userId', $user->id)
            ->assertJsonPath('data.actor.organizationId', $organization->id)
            ->assertJsonPath('data.actor.channel', 'ios')
            ->assertJsonPath('data.actor.label', $user->name);

        $stored = PersonalAccessToken::query()->sole();

        expect($stored->tokenable_type)->toBe('user')
            ->and($stored->tokenable_id)->toBe($user->id)
            ->and($stored->last_used_at)->not->toBeNull();
    });

    it('answers 401 unauthenticated for a missing, unknown or revoked token', function (Closure $authorization) {
        [, , $token] = foundationSignedInOwner();
        $header = $authorization($token);

        $this->getJson('/api/app/v1/__foundation/me', $header === null ? [] : ['Authorization' => $header])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    })->with([
        'missing' => [fn (string $token): ?string => null],
        'unknown' => [fn (string $token): string => 'Bearer 999999|not-a-real-token'],
        'malformed' => [fn (string $token): string => 'Bearer '.$token.'x'],
        'revoked' => [function (string $token): string {
            PersonalAccessToken::query()->delete();

            return "Bearer {$token}";
        }],
    ]);

    it('expires tokens after 90 days (sanctum.expiration)', function () {
        [, , $token] = foundationSignedInOwner();

        $this->travel(129599)->minutes();
        $this->getJson('/api/app/v1/__foundation/me', ['Authorization' => "Bearer {$token}"])->assertOk();

        // The sanctum guard caches the user for the lifetime of the test application.
        Auth::forgetGuards();

        $this->travel(2)->minutes();
        $this->getJson('/api/app/v1/__foundation/me', ['Authorization' => "Bearer {$token}"])
            ->assertUnauthorized()
            ->assertJsonPath('code', 'unauthenticated');
    });

    it('never authenticates app v1 through the session guard', function () {
        [$user] = foundationSignedInOwner();

        $this->actingAs($user, 'web')
            ->getJson('/api/app/v1/__foundation/me')
            ->assertUnauthorized();
    });
});

describe('kernel services with the Identity user', function () {
    it('audits with the Identity user as actor and as subject (morph alias and public id)', function () {
        [$user, $organization, $token] = foundationSignedInOwner();

        $this->postJson('/api/app/v1/__foundation/audit', [], [
            'Authorization' => "Bearer {$token}",
            'X-Request-Id' => 'foundation-0001',
        ])->assertOk();

        $entry = AuditLog::query()->sole();

        expect($entry->action)->toBe('member.added')
            ->and($entry->organization_id)->toBe($organization->id)
            ->and($entry->actor_type->value)->toBe('user')
            ->and($entry->actor_id)->toBe($user->id)
            ->and($entry->actor_label)->toBe($user->name)
            ->and($entry->subject_type)->toBe('user')
            ->and($entry->subject_id)->toBe($user->id)
            ->and($entry->subject_public_id)->toBe($user->public_id)
            ->and($entry->channel)->toBe('web')
            ->and($entry->request_id)->toBe('foundation-0001');
    });

    it('downloads a private file when the purpose rule allows the Identity user', function () {
        Storage::fake('private');
        [$user, $organization, $token] = foundationSignedInOwner();
        [, , $otherToken] = foundationSignedInOwner();

        // The shape of a module rule (Identity registers the real organization_profile rule).
        app(FileAccessRegistry::class)->register(FilePurpose::OrganizationProfile,
            fn (User $viewer, File $file): bool => $viewer->membership?->organization_id === $file->organization_id);

        $file = File::factory()->purpose(FilePurpose::OrganizationProfile)->create([
            'organization_id' => $organization->id,
            'uploaded_by_user_id' => $user->id,
        ]);
        Storage::disk('private')->put($file->path, '%PDF-1.4');

        $response = $this->get($file->downloadPath(), ['Authorization' => "Bearer {$token}"])->assertOk();
        expect($response->streamedContent())->toBe('%PDF-1.4');

        Auth::forgetGuards();

        $this->getJson($file->downloadPath(), ['Authorization' => "Bearer {$otherToken}"])
            ->assertForbidden()
            ->assertJsonPath('code', 'forbidden');
    });

    it('authorizes private realtime channels for the Identity user with a bearer token', function () {
        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb' => [
                'driver' => 'reverb',
                'key' => 'foundation-key',
                'secret' => 'foundation-secret',
                'app_id' => 'foundation',
                'options' => ['host' => 'localhost', 'port' => 8085, 'scheme' => 'http', 'useTLS' => false],
                'client_options' => [],
            ],
        ]);
        app(BroadcastManager::class)->forgetDrivers();

        // The shape of a module channel (Notifications registers the real user.{id} channel).
        Broadcast::channel('foundation.{userId}', fn (User $viewer, string $userId): bool => $viewer->public_id === $userId);

        [$user, , $token] = foundationSignedInOwner();
        [$other] = foundationSignedInOwner();

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-foundation.'.$user->public_id,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertOk()
            ->assertJsonStructure(['auth']);

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-foundation.'.$other->public_id,
        ], ['Authorization' => "Bearer {$token}"])
            ->assertForbidden();
    });
});

describe('schema and models', function () {
    it('maps every morph alias to an existing model with a table', function (string $alias, string $class) {
        expect(class_exists($class))->toBeTrue("Morph alias [{$alias}] points at missing class [{$class}].")
            ->and(is_subclass_of($class, Model::class))->toBeTrue()
            ->and(Schema::hasTable((new $class)->getTable()))->toBeTrue()
            ->and((new $class)->getMorphClass())->toBe($alias);
    })->with(fn (): array => array_map(
        fn (string $alias, string $class): array => [$alias, $class],
        array_keys(MorphMap::ALIASES),
        array_values(MorphMap::ALIASES),
    ));

    it('has a migrated table for every model', function () {
        $classes = foundationModelClasses();
        $missing = array_values(array_filter(
            $classes,
            fn (string $class): bool => ! Schema::hasTable((new $class)->getTable()),
        ));

        expect(count($classes))->toBeGreaterThanOrEqual(51)
            ->and($missing)->toBe([]);
    });

    it('persists a row with the factory of every model that has one', function () {
        $withFactory = array_values(array_filter(
            foundationModelClasses(),
            fn (string $class): bool => in_array(HasFactory::class, class_uses_recursive($class), true),
        ));

        foreach ($withFactory as $class) {
            $model = $class::factory()->create();

            expect($model->exists)->toBeTrue("{$class} factory did not persist a row.")
                ->and($class::query()->whereKey($model->getKey())->exists())->toBeTrue();
        }

        expect(count($withFactory))->toBeGreaterThanOrEqual(48);
    });
});
