# Foundation integration check (kernel × schema)

Written on 2026-09-29, after the Platform kernel and the module schema were built in parallel. The task was to make them work together, so that module engineers can start.

## 1. Result

The kernel and the schema already worked together. There was **no integration defect** in code, migrations or models. I made two config fixes and one test clean-up, and added a smoke test.

| Check | Command | Result |
|---|---|---|
| Full suite | `scripts/test-api.sh foundation` | 547 passed (3187 assertions): the 509 existing tests plus 38 new ones |
| Full suite, parallel | `scripts/test-api.sh foundation --parallel --processes=4` | 547 passed |
| PHPStan level 6 | `vendor/bin/phpstan analyse --memory-limit=1G` (all of `app/`) | 0 errors |
| Pint | `vendor/bin/pint --test` | clean |
| Dev database | `php artisan migrate:fresh --seed --force`, then `php artisan db:seed --class=DemoSeeder --force` on `bafo` | 62 migrations; 10 legal documents; 8 settings. There are no demo users yet, because no module demo seeder exists. |
| Full rollback | `migrate:fresh --seed`, then `DemoSeeder`, `migrate:reset` and `migrate`, on a scratch database (since dropped) | Every `down()` works. After the reset, only `migrations` remains: no leftover tables, sequences or functions. |
| Caches | `config:cache`, `route:cache` and `event:cache`, written to scratch paths so parallel test runs are not affected | All three succeed. The app runs from the cached routes. |
| Schedule | `php artisan schedule:list` | `platform:prune` (daily) and `horizon:snapshot` (every 5 minutes) |

The following were checked with probes that were not kept:

- The Filament `/admin/login` page renders with the Identity users provider.
- A Passport client-credentials token is issued under `requireMorphMap()` (`POST /oauth/token` returns 200).
- A database notification is stored for the Identity user with a ULID id, and its `notifiable_type` is `user`.

**Models and tables.** Every table in ARCHITECTURE §5 has a model, with three exceptions that are intended: `notifications` (Laravel's `DatabaseNotification`) and the pivots `organization_category` and `vendor_category`. That makes 51 models. All 24 morph aliases point at an existing model whose table exists. Every model that uses `HasFactory` persists a row through its factory; that is 48 of them. `AuditLog`, `AppSetting` and `IdempotencyKey` have no factory, because they are written through `AuditLogger`, `Settings` and `idempotent`.

## 2. Changes made

| File | Change |
|---|---|
| `apps/api/tests/Feature/Platform/FoundationIntegrationTest.php` (new, 38 tests) | See the list below this table. |
| `apps/api/config/session.php` | The default driver changed from `database` to `redis`: there is no `sessions` table (§2.2 item 3). `.env.example` already set `redis`, and tests use `array`. |
| `apps/api/config/auth.php` | Comment only. The `passwords` broker is unused (OTP reset, §13.9) and there is no `password_reset_tokens` table. |
| `apps/api/tests/Feature/Platform/ActorTest.php` | Removed the `->skip(! class_exists(Membership::class))`: the Identity models exist now. |
| `apps/api/tests/Support/Platform/TestUser.php` | Docblock only: kernel tests only; module tests use the real `User` factory. |

`FoundationIntegrationTest.php` checks the following:

- The users provider is the Identity `User`. Sanctum authenticates by token only, with a 90-day expiry.
- A **real bearer token** from `User::factory()->withMembership($org, OrgRole::Owner)` authenticates on `app_v1`. `ResolveActor` builds the actor as follows:
  - type `user`;
  - `userId`;
  - `organizationId` from the membership;
  - channel from `X-Platform`;
  - label.
- The token is stored with `tokenable_type = user`.
- A missing, unknown, malformed or revoked token gets 401 `unauthenticated`. So does a token past 90 days, and so does a session-guard login.
- `AuditLogger` records the Identity user as the actor and as the subject: alias `user`, `public_id` and the feed organization.
- A `FileAccessRegistry` rule typed on `User` allows the file download (200) and denies another organization (403).
- `POST /broadcasting/auth` with a bearer token signs the viewer's private channel (200) and refuses another user's (403). The test uses the Reverb driver with dummy keys; it never makes a network call.
- Schema: every morph alias maps to a model with a table; every model has a table; every factory persists a row.

## 3. How module engineers authenticate in tests

```php
use App\Modules\Identity\Enums\OrgRole;
use App\Modules\Identity\Models\{Organization, User};
use Laravel\Sanctum\Sanctum;

$org  = Organization::factory()->create();
$user = User::factory()->withMembership($org, OrgRole::Owner)->create();

Sanctum::actingAs($user);                              // quickest
// or a real token, as the apps send it:
$token = $user->createToken('web')->plainTextToken;
$this->getJson('/api/app/v1/…', ['Authorization' => "Bearer {$token}"]);
```

**Test gotcha.** Within one test, the `sanctum` guard caches the first authenticated user. Call `Auth::forgetGuards()` before a request as a different user or with a different token.

## 4. Requests to other owners

**Notifications**

- Setting `$this->id = strtolower((string) Str::ulid())` in a notification's constructor, as the §5.8 note suggests, gives **every recipient the same id**. `Notification::send($users, …)` to two or more users then fails with a `notifications_pkey` unique violation. I confirmed this with a probe. Laravel only generates an id per notifiable when `$notification->id` is empty, and it generates a UUID (36 characters), which does not fit `char(26)`. So assign a fresh ULID per recipient. Two ways to do that:
  - notify each user separately;
  - use a custom `database` channel that sets the id per notifiable.

**Identity**

- Your STATUS row still says `app/Models/User.php` and `UserFactory` are framework leftovers. They were deleted: `App\Modules\Identity\Models\User` and its factory are live, and `config/auth.php` points at them.

**Admin**

- The Filament panel still authenticates through the `web` guard, which uses the users provider (Identity `User`). That is harmless locally, because `/admin/login` renders. The `admin` guard (§16) and `authGuard('admin')` remain yours.

**Integrations**

- The Passport client-credentials grant works under `requireMorphMap()`: a client created with `ClientRepository::createClientCredentialsGrantClient()` gets a token from `/oauth/token`. `Passport::ignoreRoutes()` is still open.

**Platform / team**

- `apps/api/CLAUDE.md` and `AGENTS.md` tell agents to `composer require laravel/boost` and run `boost:install`. That is not in the contract, and it would change the shared `composer.json` and `composer.lock` while modules work in parallel. I did not do it, and neither did the Platform owner.

## 5. Contract gaps

None new. The session default follows ARCHITECTURE §2.2 item 3.
