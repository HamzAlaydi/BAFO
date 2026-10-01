# BAFO architecture (binding technical contract)

Status: **binding** for every implementer. Written 2026-09-29 against the live scaffold in `apps/api` and `apps/web`.

**Order of precedence** when documents disagree:

1. `docs/build/BRIEF.md`
2. this file, `API.md` and `CONVENTIONS.md` (all three are one contract)
3. the design files in `docs/02_design/`

If something is not covered here, choose the simplest option that respects BRIEF.md. Record the choice in a code comment that starts with `CONTRACT-GAP:`.

Companion files:

| File | Contains |
|---|---|
| `API.md` | Every HTTP endpoint (app v1 and public v1), resource shapes, webhook catalogue |
| `CONVENTIONS.md` | Coding rules, i18n keys, the master error-code list, formatting, testing, how to add things without touching other people's files |

---

## 0. Decisions that shape everything

| # | Decision | Why |
|---|---|---|
| D1 | **Modular monolith.** One Laravel 13 app in `apps/api`, with nine modules under `app/Modules/<Module>` plus a shared kernel in `app/Support`. | BRIEF stack. It lets eight people work in parallel with few merge conflicts. |
| D2 | **Keys:** bigint `id` primary keys (internal) plus a `public_id` ULID column, exposed as `"id"`. Use the existing trait `App\Support\Database\Concerns\HasPublicId`. Route binding, channel names, webhook payloads and all API ids use `public_id`. | Already scaffolded. BRIEF: ULIDs public, bigints internal. |
| D3 | **Spelling:** `organization` (US) in code, tables and JSON. | It matches the web scaffold types and R1. |
| D4 | **Roles:** three organization roles, `owner`, `admin` and `member`, plus two flags per membership, `can_award` and `can_purchase`. Permissions are derived in code (§8). **`spatie/laravel-permission` is not used.** Remove the package and its migration (§2.2). | This is the MVP RBAC (mvp_linecut B-BE-06). The permission matrix is static, so a table-driven RBAC would be dead scaffolding. |
| D5 | **Public API OAuth2 uses Laravel Passport**, for the client-credentials grant only. Follow the exact recipe in §14.2. API keys use our own middleware. | BRIEF. Passport 13 is already installed. |
| D6 | **Competition rules are typed columns** on `competitions`, not a JSON blob. Rules are **locked at publish**. After publish, only the schedule (in `scheduled`, or by extension) and the content (title, description, attachments) can change. | The engine and admin need typed, validated values. Locking at publish removes the re-acknowledgement flow (an MVP simplification of R5 §4.4). |
| D7 | Competition **`format` is `live` or `sealed`**. The BAFO round is a boolean `bafo_round_enabled` fixed at publish. When it is on, the issuer may start **one** round after close with a **manual shortlist**. | mvp_linecut §1.5 and R5-08: manual shortlist; on-demand requests are dropped. |
| D8 | **Competition status values:** `draft`, `scheduled`, `live`, `closed`, `bafo_round`, `awarded`, `not_awarded`, `cancelled`. `closed` means bidding has ended and the issuer is evaluating (UI label "Evaluation"). While `live`, a derived `phase` is one of `sealed`, `initial`, `open` or `final_window`. | This replaces R5's `open` with `live`, which matches the task statement. The status is identical on the app API, the public API and in webhooks. |
| D9 | **Realtime channels follow BRIEF exactly:** `private-competition.{competitionId}` (issuer side) and `private-competition.{competitionId}.participant.{organizationId}` (one per participant organization). There is **no shared participant room channel**: every participant event is projected per organization. There is also `private-user.{userId}` for notification counters. | BRIEF. It removes a whole class of leaks. |
| D10 | **Serialisation point for bids:** `SELECT … FOR UPDATE` on the **`competitions` row**. The timestamp is the DB clock (`clock_timestamp()`) read after the lock. | BRIEF and the task. §7.4. |
| D11 | **No tokens in URLs.** File downloads are authenticated streaming endpoints; there are no presigned URLs. E-mail links carry one-time tokens only in the **URL fragment** (`#t=…`), which the web page posts in a JSON body. Logos and avatars are on the `public` disk with unguessable ULID paths. | BRIEF security rule. |
| D12 | **Web and mobile both authenticate with Sanctum personal access tokens** (`Authorization: Bearer`). There are no SPA cookies and no CSRF on the API. | BRIEF. |
| D13 | **Money:** integer halalas everywhere (`*_minor`), including the public API. There are no decimal strings (this overrides R1 §5). | BRIEF. |
| D14 | **Migration order is by module range** (§3.5). **Catalog runs before Identity, and Integrations before Competitions**, because of foreign keys. This deviates from the task's example numbering, which put Identity first. | Organizations reference regions; invitations reference vendors. |
| D15 | **R4 at MVP level:** a flat pass price per direction, prepaid through the same hosted checkout, publish blocked until paid, no double charge. Unused passes are counted at close; the **admin issues a voucher** by hand. There is no credit ledger and no bank transfer for passes. | mvp_linecut §1.4. |
| D16 | **R1 at MVP level:** about 40 public operations (§14 and API.md §3), 13 webhook event types, thin payloads, Standard Webhooks signing, and CSV/XLSX import of vendors plus exports in the dashboard. There is no sandbox stack, no event feed and no ETag. | mvp_linecut §1.2. |
| D17 | **PDF rendering** goes through `App\Support\Pdf\PdfRenderer`, with an `mpdf` driver (`mpdf/mpdf`) that handles Arabic shaping locally. Gotenberg is a later driver. | It keeps local development free of Docker. |
| D18 | **External services are fake by default:** `PaymentGateway=fake` (local hosted page `/pay/fake/{payment}`), `EInvoicing=fake`, `PushNotifier=log`, mail `log`, no SMS. | BRIEF. |

---

## 1. Runtime overview

```
apps/web  (Nuxt 4, :3000)  ──HTTPS/JSON──▶  apps/api (Laravel 13, :8000)
apps/mobile (Flutter)      ──Bearer token─▶    /api/app/v1/*        first-party API (Sanctum)
ERP / iPaaS                ──OAuth2 / key─▶    /api/public/v1/*     public API (Passport client credentials + API keys)
Browser                    ─────────────────▶  /admin               Filament 5 (session guard "admin")
                                               /pay/fake/{payment}  fake hosted checkout (non-production only)
                                               /broadcasting/auth   channel auth (Sanctum bearer)
clients  ◀──── WebSocket (Pusher protocol) ──  Reverb :8085 (private channels only)
                                               Redis :6379  (queues, cache, rate limits, presence TTLs)
                                               PostgreSQL 17 db "bafo" (tests: "bafo_test")
```

**Local processes** (`scripts/dev.sh` starts all of them):

- `php artisan serve --port=8000`
- `php artisan reverb:start --port=8085`
- `php artisan horizon`, or `queue:work redis --queue=live,default,notifications,mail,billing,webhooks,pdf,imports`
- `php artisan schedule:work`
- `pnpm --dir apps/web dev`

Android emulators reach the API at `http://10.0.2.2:8000` and Reverb at `10.0.2.2:8085`.

**Time zone.**

- The app, the database session and all storage use UTC. Set `'timezone' => 'UTC'` on the `pgsql` connection.
- Displays use Asia/Riyadh (UTC+3, no DST).

---

## 2. Foundation (already scaffolded) and required deltas

### 2.1 What exists and stays binding

| Path | What it is | Owner |
|---|---|---|
| `app/Support/Database/Concerns/HasPublicId.php` | `public_id` ULID trait (route key, `findByPublicId`) | Platform |
| `app/Support/Exceptions/ApiException.php`, `ApiExceptionRenderer.php` | Error envelope `{message, code, errors}` | Platform |
| `app/Support/Http/ApiResponse.php`, `Controllers/ApiController.php` | Success envelope `{data, meta}` and pagination meta | Platform |
| `app/Support/Http/Middleware/ForceJsonResponse.php`, `SetLocaleFromHeader.php` | Accept JSON; locale from `Accept-Language` (default `ar`) | Platform |
| `app/Support/Modules/ModuleServiceProvider.php` | Base provider; `$policies` map | Platform |
| `app/Support/Routing/ApiRoutes.php` | Loads `routes/app_v1/*.php` (prefix `api/app/v1`, group `app_v1`, names `app.v1.`) and `routes/public_v1/*.php` (prefix `api/public/v1`, group `public_v1`, names `public.v1.`) | Platform |
| `bootstrap/app.php`, `bootstrap/providers.php`, `config/*.php`, `composer.json`, `phpunit.xml`, `.env.example`, `routes/{web,console,channels}.php`, `lang/{ar,en}/{errors,validation,auth,passwords,pagination}.php`, `tests/{Pest.php,TestCase.php}`, `database/seeders/{DatabaseSeeder,DemoSeeder}.php` | Shared files | **Platform (foundation) only.** Other modules never edit them; they use the extension points in §3.4. |

### 2.2 Deltas the Platform owner must make to the scaffold

1. **`ApiException` gains `details`.** Add a final constructor argument `public readonly array $details = []`. `ApiExceptionRenderer` adds `"details": {...}` to the body **only when it is non-empty**. Modules construct the exception with named arguments.
2. **Remove the Spatie package and its migration:** `composer remove spatie/laravel-permission` and delete `database/migrations/*_create_permission_tables.php` (D4).
3. **Replace the default users migration.** Delete `database/migrations/0001_01_01_000000_create_users_table.php`. The `users` table is created by Identity (§5.3). No `sessions` or `password_reset_tokens` tables are needed: sessions use Redis (the admin panel), and password reset uses OTP codes.
4. **Keep the other framework and vendor migrations in `database/migrations/`:**
   - `cache`;
   - `jobs`, `job_batches` and `failed_jobs`;
   - `personal_access_tokens` (Sanctum);
   - the `oauth_*` tables (Passport).

   They are the only migrations outside the module ranges.
5. **Module discovery.** Register every `app/Modules/*/*ServiceProvider.php` automatically: in `AppServiceProvider::register()`, glob the paths and call `$this->app->register($class)` for each. Adding a module then never touches a shared file.
6. **Route loading.** `bootstrap/app.php` calls `ApiRoutes::register(base_path('routes'))` in `withRouting(then: …)`. Remove `api:` (delete `routes/api.php`) and `channels:`.
7. **Middleware groups.** `bootstrap/app.php` defines only the module-agnostic part:

   | Group | Stack, in order |
   |---|---|
   | `app_v1` | `ForceJsonResponse`, `AssignRequestId`, `SetLocaleFromHeader`, `EnsureSupportedAppVersion`, `BlockDuringMaintenance`, `auth:sanctum`, `ResolveActor`, `throttle:app`, `SubstituteBindings` |
   | `public_v1` | `ForceJsonResponse`, `AssignRequestId`, `SetLocaleFromHeader` |

   Route-model binding (`SubstituteBindings`) must run **after** authentication. So it is last in `app_v1`, and in `public_v1` Integrations appends it after its authentication middleware.

   Modules append their own middleware with `$router->pushMiddlewareToGroup()` in `boot()`:

   | Module | Appends to | Middleware |
   |---|---|---|
   | Identity | `app_v1` | `EnsureAccountActive` (§8.1) |
   | Integrations | `public_v1` | `AuthenticatePublicApiClient` (alias `api.client`), then `throttle:public-api` (§14.3), then `\Illuminate\Routing\Middleware\SubstituteBindings` |

   - **Guest app routes** call `->withoutMiddleware('auth:sanctum')`, as the `ApiRoutes` docblock already shows. `ResolveActor` then yields a guest actor, and `EnsureAccountActive` passes through when there is no authenticated user.
   - **The public token endpoint** calls `->withoutMiddleware([AuthenticatePublicApiClient::class, 'throttle:public-api'])`.
   - **Rate limiters.** Platform defines the shared limiters in `AppServiceProvider::boot()`, so no module depends on another module for them:

     | Limiter | Limit |
     |---|---|
     | `app` | 300 per minute per user id (per IP for guests) |
     | `auth` | 10 per minute per IP **and** 5 per minute per lowercased `email` input |
     | `guest-forms` | 5 per hour per IP |
     | `guest-actions` | 10 per minute per IP |

     Module-specific limiters are defined by their module: `offers` (Bidding: 60 per minute per user), `public-api` and `oauth-token` (Integrations).
   - **Middleware aliases.** Platform registers `idempotent` → `IdempotentRequest` in `bootstrap/app.php`. Modules register their own aliases (§3.4).
8. **Broadcasting.** `->withBroadcasting(base_path('routes/channels.php'), ['middleware' => ['auth:sanctum']])`, which keeps the path at `POST /broadcasting/auth`. Add `broadcasting/auth` to `config/cors.php` `paths`. `routes/channels.php` stays empty: modules register their channels in their providers.
9. **Configuration settings.**

   | Setting | Value |
   |---|---|
   | `pgsql.timezone` | `UTC` |
   | `SANCTUM_STATEFUL_DOMAINS` | empty |
   | `sanctum.expiration` | `129600` (90 days, in minutes) |
   | CORS `allowed_origins` | from `CORS_ALLOWED_ORIGINS` |
   | CORS `allowed_headers` | `Authorization`, `Content-Type`, `Accept`, `Accept-Language`, `Idempotency-Key`, `If-None-Match`, `X-Platform`, `X-App-Version`, `X-Request-Id` |
   | CORS `exposed_headers` | `ETag`, `Retry-After`, `Idempotent-Replayed`, `Content-Disposition`, `X-Request-Id`, `Content-Language` |
   | `supports_credentials` | `false` |

10. **Composer additions:**
    - `mpdf/mpdf` (PDF);
    - `openspout/openspout` (CSV/XLSX);
    - `larastan/larastan` (dev), plus `phpstan.neon` at level 6 over `app/`.
11. **`phpunit.xml` test environment:**

    | Variable | Value |
    |---|---|
    | `DB_CONNECTION` | `pgsql` |
    | `DB_DATABASE` | `bafo_test` |
    | `DB_HOST` | `/tmp` |
    | `QUEUE_CONNECTION` | `sync` |
    | `CACHE_STORE` | `array` |
    | `SESSION_DRIVER` | `array` |
    | `BROADCAST_CONNECTION` | `null` |
    | `MAIL_MAILER` | `array` |
    | `PAYMENT_GATEWAY` | `fake` |
    | `EINVOICING_DRIVER` | `fake` |
    | `PUSH_DRIVER` | `log` |
    | `PDF_DRIVER` | `mpdf` |
    | `OTP_FAKE_CODE` | `123456` |
    | `BCRYPT_ROUNDS` | `4` |

---

## 3. Module layout and ownership

### 3.1 Directory layout of a module

```
app/Modules/<Module>/
  <Module>ServiceProvider.php     extends App\Support\Modules\ModuleServiceProvider
  config.php                      merged into config("bafo.<module_snake>")
  Contracts/                      interfaces other modules may call (bound in register())
  Models/                         Eloquent models (HasPublicId where exposed)
  Enums/                          string-backed PHP enums (DB stores ->value)
  Data/                           readonly DTOs / value objects
  Http/Controllers/AppV1/         controllers for /api/app/v1
  Http/Controllers/PublicV1/      controllers for /api/public/v1
  Http/Requests/                  one FormRequest per write endpoint
  Http/Resources/                 JsonResources (always expose public_id as "id")
  Http/Middleware/                module middleware (aliases registered in the provider)
  Policies/                       Gate policies (listed in $policies)
  Services/                       stateful domain services
  Actions/                        one public method handle(...) per use case; controllers, admin and jobs call these
  Events/                         domain events (plain classes; see §10)
  Listeners/
  Jobs/
  Notifications/                  Laravel notification classes (Notifications module; token mails in Identity/Competitions)
  Broadcasting/                   ShouldBroadcast event classes (Bidding, Notifications)
  Console/Commands/
  Database/Migrations/            loaded with loadMigrationsFrom()
  Database/Factories/             models declare newFactory()
  Database/Seeders/               <Module>ReferenceSeeder (always), <Module>DemoSeeder (demo)
  Resources/views/                loadViewsFrom(..., '<module_snake>'), e.g. billing::pdf.invoice
  Routes/web.php                  non-API web routes (only Billing and Integrations have one)
routes/app_v1/<module_snake>.php
routes/public_v1/<module_snake>.php
lang/ar/<module_snake>.php, lang/en/<module_snake>.php
tests/Feature/<Module>/, tests/Unit/<Module>/, tests/Support/<Module>/
```

The route and lang files use the module's snake name:

| Module | Snake name |
|---|---|
| Identity | `identity` |
| Catalog | `catalog` |
| Competitions | `competitions` |
| Bidding | `bidding` |
| Billing | `billing` |
| Notifications | `notifications` |
| Integrations | `integrations` |
| Admin | `admin` |
| Platform | `platform` |

### 3.2 Modules and their boundaries

| Module | Owns (tables / features) | Must NOT own |
|---|---|---|
| **Platform** | Everything under `app/Support/**` (the kernel, §4). Tables `files`, `audit_logs`, `app_settings`, `idempotency_keys`, `contact_messages`, `legal_documents`. Endpoints: app config, time, health, contact, legal pages, file download. All shared files in §2.1. `DatabaseSeeder` and `DemoSeeder`. | Business rules of other modules |
| **Catalog** | Tables `regions`, `categories`, `close_reasons`, `competition_presets`. Lookup endpoints (app and public). Reference seeders. | – |
| **Identity** | Tables `organizations`, `organization_category`, `users`, `memberships`, `otp_codes`, `consents`, `account_deletion_requests`. Features: register, OTP, login and logout, password reset, `/me`, profile, organization profile and files, team (branch users), roles and permissions (`Permission` enum and `OrgRole` map), account deletion, `GET /organization` (public). | Subscriptions (Billing) |
| **Integrations** | Tables `vendors`, `vendor_category`, `external_refs`, `api_clients`, `api_keys`, `webhook_endpoints`, `webhook_events`, `webhook_deliveries`, `import_jobs`, `export_jobs`. Features: Passport client-credentials recipe, the `api.client` and `api.scope` middleware, the vendor directory (app and public), webhook outbox, delivery and signing, CSV/XLSX import and export, the OpenAPI document for public v1. | Competition or offer business rules |
| **Competitions** | Tables `competitions`, `competition_extensions`, `competition_attachments`, `invitations`, `participants`, `comments`. Features: CRUD, rules validation and rules summary, publish, the **lifecycle state machine and scheduler**, extend, cancel, close without award, invitations (send, claim, join, decline, revoke, expire), suggestions, attachments, Q&A, home stats, and the public competitions, attachments and invitations endpoints. | The offer ledger, ranking and awards (Bidding); passes (Billing) |
| **Bidding** | Tables `competition_live_states`, `offers` (append-only ledger), `offer_voids`, `offer_rejections`, `participant_standings`, `bafo_rounds`, `awards`, `competition_reports`. Features: the acceptance engine, ranking, the **VisibilityProjector**, anti-sniping, sealed unlock, the BAFO round, award and revoke, admin void, live snapshots, heartbeat, realtime broadcasts, the result PDF, and the public offers, results and awards endpoints. | Competition lifecycle transitions other than the ones in §6.1 |
| **Billing** | Tables `plans`, `subscriptions`, `coupons`, `coupon_redemptions`, `payments`, `payment_lines`, `invoices`, `invoice_lines`, `competition_sponsorships`, `sponsored_passes`. Features: plans, the custom-seat quote, trial, grants, checkout through `PaymentGateway`, invoices through `EInvoicing`, vouchers, R4 sponsorship and passes, **`AccessPolicy`** (it replaces `has_valid_subscription`), and the fake hosted checkout page. | – |
| **Notifications** | Tables `notifications` (Laravel shape) and `device_tokens`. Features: the notification catalogue (§11), in-app notification API, push channel via `PushNotifier`, the mail theme and layout (`resources/views/vendor/mail/**`), the `user.{id}` channel and unread counter, device registration. | Token-carrying mails (OTP, team invitation, competition invitation), which the owning module sends with the shared theme |
| **Admin** | Table `admins`. `app/Modules/Admin/Filament/**` (resources, pages, widgets), `app/Providers/Filament/AdminPanelProvider.php`, the admin guard. Every state change goes through module Actions. | Direct SQL updates of domain state |

### 3.3 Dependency rules between modules

1. A module may **read** another module's Models and Enums, and may type-hint its Events and Contracts.
2. A module may **write** another module's tables **only** through that module's Contracts or Actions listed in §3.6. The **one exception** is in §7.4: Bidding updates `competitions.effective_close_at` and `competitions.extension_count` through `CompetitionTimingService`, while holding the competition row lock.
3. Never call another module's controllers, FormRequests or Resources. The exception is JsonResources re-used for **embedding**, which are listed in API.md.
4. Cross-module reactions go through **domain events** (§10). The producer never knows its listeners.
5. Contracts are PHP interfaces in `app/Modules/<Owner>/Contracts`. The owner binds the implementation in its provider's `register()`. Consumers code against the interface, so they can work before the implementation lands and fake it in unit tests.

### 3.4 Extension points (how a module plugs in without editing shared files)

| Need | Do this in your module |
|---|---|
| Routes | Create `routes/app_v1/<module>.php` and/or `routes/public_v1/<module>.php`. They are auto-loaded. |
| Web (non-API) routes | Create `app/Modules/<M>/Routes/web.php`. The provider calls `Route::middleware('web')->group(__DIR__.'/Routes/web.php')` in `boot()`. |
| Migrations | Create `app/Modules/<M>/Database/Migrations/`. The provider calls `$this->loadMigrationsFrom(__DIR__.'/Database/Migrations')`. |
| Config | Create `app/Modules/<M>/config.php`. The provider calls `$this->mergeConfigFrom(__DIR__.'/config.php', 'bafo.<module_snake>')`. |
| Translations | Create `lang/ar/<module_snake>.php` and `lang/en/<module_snake>.php` (you own both files). |
| Policies | Add them to the `$policies` array. |
| Middleware aliases | `$this->app['router']->aliasMiddleware('alias', Class::class)` in `boot()`. |
| Rate limiters | `RateLimiter::for('name', fn (Request $r) => …)` in `boot()`. |
| Broadcast channels | `Broadcast::channel('competition.{competition}', fn (User $user, string $competition) => …)` in `boot()`. |
| Scheduled tasks | `$this->callAfterResolving(Schedule::class, fn (Schedule $s) => $s->command(...)->…)` in `boot()`. |
| Artisan commands | `$this->commands([...])` in `boot()`, inside `if ($this->app->runningInConsole())`. |
| Event listeners | `Event::listen(EventClass::class, ListenerClass::class)` in `boot()`. A listener may reference an event class from a module that is not implemented yet; the listener binding is just a string. |
| Morph aliases | `Relation::morphMap([...])` in `register()`. The Platform owner calls `Relation::requireMorphMap()` once. Aliases are listed in §4.9. |
| File access rules | `app(FileAccessRegistry::class)->register('<purpose>', fn (User $u, File $f): bool => …)` in `boot()`. |
| App settings defaults | `app(Settings::class)->defaults(['key' => value, ...])` in `boot()`. Keys are in §15.3. |
| Seeders | `Database/Seeders/<M>ReferenceSeeder.php` (idempotent: `updateOrCreate` by `code`). `DatabaseSeeder` calls every `*ReferenceSeeder` that exists, in module order (§3.5). |

### 3.5 Migration ranges (module order = FK order)

Every module migration file is named `2026_01_01_HHMMSS_<snake_description>.php`, where `HHMMSS` falls in the module's range:

| Order | Module | Range (`2026_01_01_…`) |
|---|---|---|
| 0 | Platform | `000000`–`000099` |
| 1 | Catalog | `000100`–`000199` |
| 2 | Identity | `000200`–`000299` |
| 3 | Integrations | `000300`–`000399` |
| 4 | Competitions | `000400`–`000499` |
| 5 | Bidding | `000500`–`000599` |
| 6 | Billing | `000600`–`000699` |
| 7 | Notifications | `000700`–`000799` |
| 8 | Admin | `000800`–`000899` |
| 9 | Reserved (late cross-module constraints; Platform owner only) | `000900`–`000999` |

**Rules for migrations:**

- **FK constraints point only to tables that come earlier in this order.** A column that references a later table (for example `files.organization_id`, or `api_clients.oauth_client_id` pointing at Passport's `oauth_clients`) is indexed but **unconstrained**. §5 says so explicitly for each such column.
- Framework and vendor migrations (`0001_01_01_*`, `2026_09_29_*`) sort **before** or **after** the module ranges and never reference module tables.
- A module that needs a later change (after first delivery) uses a new date with its own range, for example `2026_02_15_000410_add_x_to_competitions.php`.

### 3.6 Inter-module contracts (exact signatures)

All of these live in `App\Modules\<Owner>\Contracts` and are bound as singletons.

```php
// Billing — the single entitlement policy (replaces has_valid_subscription)
interface AccessPolicy {
    public function canIssue(Organization $org): bool;                        // active subscription (paid|trial|grant) now
    public function activeSubscription(Organization $org): ?Subscription;
    public function seatLimit(Organization $org): int;                        // plan seats, or 1 without a plan
    /** Access of an invitee organization to a competition it was invited to (§8.4). */
    public function participationAccess(Organization $org, Invitation $invitation): ParticipationAccess;
    /** Called INSIDE the join transaction. Consumes/releases a pass. Throws ApiException(plan_required, 403). */
    public function resolveJoin(Organization $org, Invitation $invitation): EntitlementSource;   // plan|sponsored_pass|grant
    /** @param iterable<Invitation> $invitations @return array<int, Coverage> keyed by invitation id */
    public function coverageFor(iterable $invitations): array;
}

// Billing — sponsorship hooks used by Competitions
interface SponsorshipService {
    /** Inside the publish TX. Reserves passes for sponsored invitations from funded slots;
     *  throws ApiException(sponsorship_payment_required, 409, details: ['quote' => SponsorshipQuote]) if not enough. */
    public function reserveForPublish(Competition $c): void;
    /** Inside the invite-more TX, same semantics for newly created invitations. */
    public function reserveForInvitations(Competition $c, Collection $invitations): void;
}

// Competitions
interface CompetitionTimingService {
    /** Caller must hold SELECT … FOR UPDATE on the competition row. Moves effective_close_at, bumps
     *  extension_count, writes competition_extensions, dispatches CompetitionExtended (after commit side effects). */
    public function extend(Competition $locked, CarbonImmutable $newCloseAt, ExtensionKind $kind,
                           Actor $actor, ?int $triggeredByOfferId = null, ?string $reason = null): CompetitionExtension;
}
interface CompetitionStateMachine {
    /** Validates the transition against §6.1, sets status + the matching *_at column, saves, dispatches
     *  CompetitionStatusChanged. Throws ApiException(invalid_state_transition, 409). Caller holds the row lock. */
    public function transition(Competition $locked, CompetitionStatus $to, Actor $actor, array $attributes = []): void;
}
// Competitions Actions called by Billing after payment:
//   App\Modules\Competitions\Actions\PublishCompetition::handle(Competition $c, Actor $actor): Competition
//   App\Modules\Competitions\Actions\InviteParticipants::handle(Competition $c, array $rows, Actor $actor): Collection<Invitation>
//     $rows: list<array{email:string, name?:?string, organization_id?:?int, vendor_id?:?int, sponsored:bool}>

// Bidding
interface BiddingEngine {
    /** Inside the close TX (competition row locked, status still live): final ranking, sealed unlock bookkeeping. */
    public function finalizeLiveBidding(Competition $locked, CarbonImmutable $now): void;
    /** Projected live snapshot for the viewer (§7.9), or null when the viewer has no live view. */
    public function snapshotFor(Competition $c, Viewer $viewer): ?array;
    /** Issuer-visible leading amount (null when sealed and not yet unlocked, or no offers). */
    public function issuerLeadingAmount(Competition $c): ?int;
    public function participantsWithOffersCount(Competition $c): int;
}
```

`Viewer` is `App\Modules\Competitions\Data\Viewer` (readonly):

- `role`, one of `issuer`, `participant`, `invitee`, `api_client`;
- `organizationId` (int);
- `?Participant $participant`;
- `?Invitation $invitation`.

`Competitions\Services\ViewerResolver::for(Competition, Actor): Viewer` builds it.

---

## 4. Shared kernel (`app/Support`, Platform-owned)

These classes are binding names. Consumers can rely on them from day one.

### 4.1 Actor

`App\Support\Auth\Actor` (final, readonly):

| Property | Type / values |
|---|---|
| `type` | `ActorType` enum: `user`, `api_client`, `admin`, `system`, `guest` |
| `id` | `?int` (internal id of the user, API client or admin) |
| `organizationId` | `?int` |
| `userId` | `?int` |
| `apiClientId` | `?int` |
| `adminId` | `?int` |
| `channel` | `Channel` enum: `web`, `ios`, `android`, `api`, `admin`, `system` |
| `ip` | `?string` |
| `userAgent` | `?string` |
| `requestId` | `?string` |

- Static constructors: `Actor::system()`, `Actor::forUser(User, Request)`, `Actor::forApiClient(ApiClient, Request)`, `Actor::forAdmin(Admin, Request)`.
- **Setting the current actor.** `ResolveActor` (Platform middleware) builds it for app v1 from the Sanctum user and `X-Platform`. `api.client` (Integrations) builds it for public v1.
- **Reading it.** `App\Support\Auth\CurrentActor::get(): Actor` returns the current request's actor, or `Actor::system()` in jobs and console.
- **Channel from `X-Platform`:** `ios` → `ios`, `android` → `android`, anything else → `web`.

### 4.2 Clocks

| Class | Contract |
|---|---|
| `App\Support\Clock\DbClock` (interface) `now(): CarbonImmutable` | The **database** clock. `PostgresDbClock` runs `select clock_timestamp() as now` on the current connection, so inside a transaction it returns the time after the lock. **Only the bidding engine, close, BAFO and award code use it.** Tests bind `CarbonDbClock`, which returns `CarbonImmutable::now()` and so honours `$this->travelTo()`. |
| Everything else | `CarbonImmutable::now()` (`now()`), UTC. |

### 4.3 Timestamps

- Default: `$table->timestampsTz()`. Other time columns use `timestampTz`, precision 0.
- **Precision 6 (microseconds)** applies to every column of these tables:
  - `competitions`
  - `competition_extensions`
  - `offers`
  - `offer_rejections`
  - `offer_voids`
  - `participant_standings`
  - `competition_live_states`
  - `bafo_rounds`
  - `awards`

  Their models use `App\Support\Database\Concerns\UsesPreciseTimestamps`, which sets `protected $dateFormat = 'Y-m-d H:i:s.uP'`.
- API output: **always** `App\Support\Http\Iso::format(?CarbonInterface): ?string`, which returns `Y-m-d\TH:i:s.v\Z` in UTC, for example `2026-10-01T12:59:58.412Z`. Date-only fields use `Y-m-d`.

### 4.4 Money

`App\Support\Money\Money` (static helpers; amounts are `int` halalas; currency is always `SAR`):

- `vat(int $netMinor, int $rateBp = 1500): int` computes `intdiv($netMinor * $rateBp + 5000, 10000)`, rounding half up. Negative inputs are not allowed.
- `ceilTo(int $value, int $granularity): int` rounds up to a multiple of the granularity.
- `bps(int $part, int $whole): int` computes `intdiv($part * 10000, $whole)`, truncating toward zero.
- Money is never a float. JSON sends integers.

### 4.5 Audit log

- **API.** `App\Support\Audit\AuditLogger::log(string $action, ?Model $subject = null, array $changes = [], array $meta = [], ?Actor $actor = null): void`. It writes to `audit_logs`, and the actor defaults to `CurrentActor::get()`.
- **Action names** are `<noun>.<verb_past>`, for example `competition.published`, `invitation.revoked`, `member.added`, `api_key.created`, `webhook_endpoint.secret_rotated`, `offer.voided`, `award.issued`.
- **Who writes.** Every Action that changes state writes an entry.
- **Exceptions.** Offer acceptance does not write one (the `offers` ledger is its own audit), and neither do read endpoints.
- **Home feed.** `GET /home` shows an allow-listed subset of these actions as the activity feed. These action names are therefore **mandatory**, with `organization_id` = the organization whose feed shows them:

  | Action | Written by | Organization |
  |---|---|---|
  | `competition.created` | Competitions | issuer |
  | `competition.published` | Competitions | issuer |
  | `competition.cancelled` | Competitions | issuer |
  | `competition.closed` | Competitions (system actor) | issuer |
  | `award.issued` | Bidding | issuer |
  | `invitation.joined` | Competitions | **participant** |
  | `member.added` | Identity | own |
  | `subscription.activated` | Billing | own |

### 4.6 Files

`App\Support\Files\FileStorage`:

- `store(UploadedFile $file, FilePurpose $purpose, ?int $organizationId, ?int $userId): File` checks the size and type rules for the purpose (see the table below).
- `storeContents(string $bytes, string $name, string $mime, FilePurpose $purpose, ?int $organizationId): File`, for generated PDFs and exports.
- `delete(File $file): void`.
- `download(File $file): StreamedResponse` sends `Content-Disposition: attachment`, with the filename RFC 5987-encoded.
- `publicUrl(File $file): ?string` returns an absolute URL for public-disk files only.

The `public` disk serves `storage/app/public` at `/storage` (requires `php artisan storage:link`). The `private` disk is `storage/app/private`. Paths are `{purpose}/{yyyy}/{mm}/{public_id}.{ext}`.

| `FilePurpose` | Disk | Allowed extensions | Max size |
|---|---|---|---|
| `organization_logo`, `user_avatar` | public | png, jpg, jpeg, webp | 2 MB |
| `organization_profile` | private | pdf | 20 MB |
| `competition_attachment` | private | pdf, doc, docx, xls, xlsx, png, jpg, jpeg, zip | 100 MB |
| `invoice_pdf`, `competition_report` | private | pdf (generated) | – |
| `import_source` | private | csv, xlsx | 20 MB |
| `import_errors`, `export` | private | csv, xlsx (generated) | – |

- **Type checks.** The MIME type is sniffed with `finfo` and must match the extension.
- **Access.** `App\Support\Files\FileAccessRegistry`: `register(string $purpose, Closure $rule)` and `allows(User $user, File $file): bool`. There is no rule for public purposes. `GET /api/app/v1/files/{file}/download` calls the registry, returning 404 when the file does not exist and 403 when the rule denies.

### 4.7 Settings

- **API.** `App\Support\Settings\Settings`: `get(string $key, mixed $default = null)`, `set(string $key, mixed $value, ?int $adminId)`, `defaults(array $map)`.
- **Storage.** Values are stored in `app_settings` as JSONB and cached for 60 s (cache key `settings:all`, cleared on `set`).
- The key catalogue is in §15.3.

### 4.8 Idempotency, request id, versions, maintenance

**Middleware (Platform owner):**

| Class | Alias | Behaviour |
|---|---|---|
| `AssignRequestId` | – | Accepts `X-Request-Id` (8–64 chars, `[A-Za-z0-9-]`) or generates a ULID. Echoes it on the response and adds it to the log context. |
| `EnsureSupportedAppVersion` | – | Only when `X-Platform` is `ios` or `android`. If `X-App-Version` (semver) is below the setting `app.min_version.{platform}`, it returns **426** `app_version_unsupported`. |
| `BlockDuringMaintenance` | – | If the setting `app.maintenance.enabled` is true, it returns **503** `maintenance`. It skips the route names `app.v1.app-config`, `app.v1.time` and `app.v1.health`. |
| `ResolveActor` | – | Sets `CurrentActor` (§4.1). |
| `IdempotentRequest` | `idempotent` | See below. |

**`idempotent`** (used on public API POSTs and on checkout creation):

- It requires `Idempotency-Key` (8–64 chars, `[A-Za-z0-9_-]`). If the header is missing, it returns 400 `idempotency_key_required`.
- Rows are stored in `idempotency_keys`, keyed by the scope (user or API client) and the key.
- A replay with the same method, path and body hash returns the stored status and body, plus the header `Idempotent-Replayed: true`.
- A different request hash returns 422 `idempotency_key_reused`.
- A row still in progress returns 409 `idempotency_request_in_progress`.
- Rows are kept for 24 h.
- Offers use their own mechanism (§7.4).

### 4.9 Morph aliases

`Relation::morphMap` aliases, registered by the owning module:

| Module | Aliases |
|---|---|
| Identity | `user`, `organization`, `membership` |
| Integrations | `vendor`, `api_client`, `webhook_endpoint` |
| Competitions | `competition`, `invitation`, `participant`, `comment`, `competition_attachment` |
| Bidding | `offer`, `award`, `bafo_round` |
| Billing | `payment`, `subscription`, `invoice`, `coupon`, `competition_sponsorship`, `sponsored_pass` |
| Admin | `admin` |

Sanctum `tokenable_type` and the notifications `notifiable_type` use `user`.

### 4.10 Events and transactions

- Every Action wraps its writes in `DB::transaction()` and dispatches its domain events **inside** the transaction.
- Domain events do **not** implement `ShouldDispatchAfterCommit`.
- **Synchronous listeners** run inside the transaction and **must** be fast and DB-only. The two allowed kinds are the webhook outbox writer (Integrations) and pass releases (Billing).
- **Every other listener** implements `ShouldQueue` and `ShouldHandleEventsAfterCommit`, and sets `$queue`.
- **Broadcast event classes** implement `ShouldBroadcast` and `ShouldDispatchAfterCommit`, with `public string $queue = 'live'`.

### 4.11 PDF

- **API.** `App\Support\Pdf\PdfRenderer::render(string $view, array $data, string $locale): string` returns the PDF bytes.
- **Driver `mpdf`** (config `bafo.platform.pdf.driver`, env `PDF_DRIVER`):
  - It sets the direction to RTL for `ar`.
  - It uses the IBM Plex Sans Arabic font if the files are present in `resources/fonts/`, and otherwise mPDF's built-in `dejavusans`.
  - Numbers are wrapped in `<bdi>`.
- The views belong to the modules: `billing::pdf.invoice` and `bidding::pdf.report`.

---

## 5. Database schema (complete)

### 5.0 Notation

| Notation | Meaning |
|---|---|
| `id` | `bigIncrements` primary key (internal, never in JSON) |
| `public_id` | `ulid` NOT NULL UNIQUE (lowercase), JSON `"id"`; set by `HasPublicId` |
| `fk → t` | `foreignId()->constrained(t)`; `ON DELETE RESTRICT` unless it says *cascade* or *null* |
| `ref → t` | `unsignedBigInteger` + index, **no FK constraint** (the target is created later in migration order, or is polymorphic) |
| `str(n)` | `varchar(n)` |
| `tstz` / `tstz6` | `timestampTz` with precision 0 / 6 |
| `ts` | `timestampsTz()` (created_at, updated_at); `ts6` = `timestampsTz(6)` |
| `sd` | `softDeletesTz()` (`deleted_at`) |
| `jsonb` | `jsonb`. Translated names are `{"ar": "...", "en": "..."}` |
| `enum:X` | A `str(40)` column holding the value of PHP enum `X`. **No DB enum type**; values are listed. |
| `money` | `bigInteger` in halalas |

- Every "Null" column is nullable; everything else is NOT NULL.
- Booleans default to `false` unless stated.
- All e-mail columns store the **lowercased** value.

### 5.1 Platform (`000000–000099`)

**`files`**

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| organization_id | ref → organizations | yes | | owner org |
| uploaded_by_user_id | ref → users | yes | | |
| purpose | enum:FilePurpose | | | `organization_logo`, `organization_profile`, `user_avatar`, `competition_attachment`, `invoice_pdf`, `competition_report`, `import_source`, `import_errors`, `export` |
| disk | str(20) | | | `public` or `private` |
| path | str(500) | | | unique |
| original_name | str(255) | | | |
| mime_type | str(150) | | | |
| extension | str(16) | | | lowercase |
| size_bytes | bigint | | | |
| sha256 | char(64) | | | |
| ts | | | | |

Indexes: `(organization_id, purpose)`.

**`audit_logs`** (append-only; migration creates trigger `bafo_forbid_update_delete` BEFORE UPDATE OR DELETE that raises)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | bigIncrements | | no public_id |
| occurred_at | tstz6 | | |
| organization_id | ref → organizations | yes | |
| actor_type | enum:ActorType | | user/api_client/admin/system/guest |
| actor_id | bigint | yes | |
| actor_label | str(255) | yes | name/e-mail snapshot |
| action | str(80) | | e.g. `competition.published` |
| subject_type | str(40) | yes | morph alias |
| subject_id | bigint | yes | |
| subject_public_id | str(26) | yes | |
| changes | jsonb | yes | `{field: {from, to}}`; secrets redacted |
| meta | jsonb | yes | |
| channel | str(10) | yes | |
| ip | str(45) | yes | |
| user_agent | str(500) | yes | |
| request_id | str(64) | yes | |

Indexes: `(organization_id, occurred_at DESC)`, `(subject_type, subject_id)`, `(action)`.

The trigger function is defined once in this migration:

```sql
CREATE OR REPLACE FUNCTION bafo_forbid_update_delete() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN RAISE EXCEPTION 'table % is append-only', TG_TABLE_NAME; END $$;
```

The Bidding migrations reuse it.

**`app_settings`**

| Column | Type | Notes |
|---|---|---|
| id | | |
| key | str(120) | unique |
| value | jsonb | |
| updated_by_admin_id | ref → admins, null | |
| ts | | |

**`idempotency_keys`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id | | | |
| scope_type | str(20) | | `user` or `api_client` |
| scope_id | bigint | | |
| key | str(64) | | |
| method | str(8) | | |
| path | str(255) | | |
| request_hash | char(64) | | sha256 of method + path + raw body |
| response_status | smallint | yes | null = in progress |
| response_body | jsonb | yes | |
| expires_at | tstz | | now + 24 h |
| created_at | tstz | | |

Unique `(scope_type, scope_id, key)`. Index `(expires_at)`.

**`contact_messages`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| name | str(150) | | |
| email | str(255) | | |
| phone | str(20) | yes | |
| company | str(150) | yes | |
| subject | str(150) | | |
| message | text | | ≤ 5000 chars |
| locale | str(2) | | |
| status | enum:ContactStatus | | `new` / `read` / `archived`, default `new` |
| ip | str(45) | yes | |
| user_agent | str(500) | yes | |
| handled_by_admin_id | ref → admins | yes | |
| ts | | | |

**`legal_documents`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| code | enum:LegalDocumentCode | | `terms`, `privacy`, `refund`, `competition_rules`, `api_terms` |
| locale | str(2) | | |
| version | str(20) | | e.g. `2026-10-01` |
| title | str(200) | | |
| body_markdown | text | | |
| published_at | tstz | yes | null = draft |
| created_by_admin_id | ref → admins | yes | |
| ts | | | |

Unique `(code, locale, version)`.

### 5.2 Catalog (`000100–000199`)

**`regions`**

| Column | Type | Default | Notes |
|---|---|---|---|
| id / public_id | | | |
| code | str(8) | | unique, e.g. `RIY` |
| name | jsonb | | |
| sort_order | smallint | 0 | |
| is_active | bool | true | |
| ts | | | |

**`categories`**

| Column | Type | Default | Notes |
|---|---|---|---|
| id / public_id | | | |
| code | str(40) | | unique, snake_case |
| name | jsonb | | |
| is_other | bool | false | "Other" requires `competitions.category_other_text` |
| auction_allowed | bool | true | false for real estate and vehicles (R5 guardrail) |
| sort_order | smallint | 0 | |
| is_active | bool | true | |
| ts | | | |

**`close_reasons`**

| Column | Type | Default | Notes |
|---|---|---|---|
| id / public_id | | | |
| code | str(60) | | unique |
| kind | enum:CloseReasonKind | | `cancel`, `not_awarded`, `award_justification`, `void_offer` |
| name | jsonb | | |
| requires_note | bool | false | the "Other" reasons are true |
| sort_order | smallint | 0 | |
| is_active | bool | true | |
| ts | | | |

**`competition_presets`**

| Column | Type | Notes |
|---|---|---|
| id / public_id | | |
| code | str(60) | unique |
| name | jsonb | |
| description | jsonb | |
| direction | enum:Direction | `tender`, `auction` |
| format | enum:Format | `live`, `sealed` |
| rules | jsonb | Keys exactly as the `rules` input object of API.md §2.6 (without prices) |
| sort_order | smallint | |
| is_active | bool, default true | |
| ts | | |

**Seeded reference data** (`CatalogReferenceSeeder`, idempotent by `code`):

- **Regions (13):**

  | Code | Arabic | English |
  |---|---|---|
  | RIY | الرياض | Riyadh |
  | MAK | مكة المكرمة | Makkah |
  | MED | المدينة المنورة | Madinah |
  | EAS | المنطقة الشرقية | Eastern Province |
  | QAS | القصيم | Al-Qassim |
  | ASR | عسير | Asir |
  | TAB | تبوك | Tabuk |
  | HAI | حائل | Hail |
  | NBO | الحدود الشمالية | Northern Borders |
  | JAZ | جازان | Jazan |
  | NAJ | نجران | Najran |
  | BAH | الباحة | Al-Bahah |
  | JOU | الجوف | Al-Jouf |

- **Categories:**
  - `it_hardware`
  - `software_services`
  - `construction`
  - `facility_management`
  - `office_supplies`
  - `logistics`
  - `medical_supplies`
  - `marketing_printing`
  - `consulting`
  - `industrial_equipment`
  - `surplus_scrap`
  - `vehicles` (`auction_allowed = false`)
  - `real_estate` (`auction_allowed = false`)
  - `other` (`is_other = true`)

- **Close reasons:**

  | Kind | Codes |
  |---|---|
  | cancel | `cancel_requirements_changed`, `cancel_budget_withdrawn`, `cancel_insufficient_participants`, `cancel_other`* |
  | not_awarded | `not_awarded_prices_above_budget`, `not_awarded_no_compliant_offers`, `not_awarded_requirement_cancelled`, `not_awarded_other`* |
  | award_justification | `award_leader_non_compliant`, `award_commercial_terms`, `award_leader_unable_to_deliver`, `award_other`* |
  | void_offer | `void_participant_error`, `void_technical_issue`, `void_other`* |

  \* `requires_note = true`

- **Presets (3):**

  | Code | Direction and format | Rules |
  |---|---|---|
  | `standard_live_tender` | tender, live | `must_beat=own`, `min_step_bps=50`, `rank_visibility=leading_flag`, `show_prices=false`, auto-extend 180/180/10, `final_window_minutes=60`, BAFO off |
  | `sealed_rfq` | tender, sealed | `rank_visibility=none`, `show_prices=false`, no step, no auto-extend, no final window, BAFO on (60 min) |
  | `surplus_sale_auction` | auction, live | `must_beat=best`, `min_step_minor=50000`, `rank_visibility=leading_flag`, `show_prices=true`, auto-extend 120/120/20, no final window, BAFO off |

  The issuer supplies prices; start price is required for an auction.

### 5.3 Identity (`000200–000299`)

**`organizations`** — sd

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| name | str(150) | | | display / trading name |
| legal_name_ar | str(200) | yes | | required before the first purchase |
| legal_name_en | str(200) | yes | | |
| cr_number | str(10) | | | unique; exactly 10 digits |
| vat_registered | bool | | false | |
| vat_number | str(15) | yes | | 15 digits, first and last digit `3`; required when `vat_registered` |
| region_id | fk → regions | | | |
| city | str(100) | | | |
| address_building_number | str(4) | yes | | 4 digits |
| address_street | str(150) | yes | | |
| address_district | str(150) | yes | | |
| address_postal_code | str(5) | yes | | 5 digits |
| address_additional_number | str(4) | yes | | |
| address_short | str(8) | yes | | national short address, 4 letters + 4 digits |
| website | str(255) | yes | | https URL |
| email | str(255) | | | company contact (the owner's e-mail at registration) |
| phone | str(20) | | | E.164, Saudi mobile `+9665XXXXXXXX` |
| logo_file_id | fk → files, *null on delete* | yes | | public disk |
| profile_file_id | fk → files, *null on delete* | yes | | private |
| visible_in_suggestions | bool | | true | appears in issuers' supplier suggestions |
| status | enum:OrganizationStatus | | active | `active`, `suspended`, `deleted` |
| verified_at | tstz | yes | | admin "verified" badge |
| suspended_at | tstz | yes | | |
| suspension_reason | text | yes | | |
| api_enabled | bool | | false | R1 toggle (admin) |
| auction_enabled | bool | | false | R5 guardrail (admin) |
| sponsorship_enabled | bool | | false | R4 per-org flag (admin) |
| trial_used_at | tstz | yes | | trial once per organization |
| ts, sd | | | | |

Indexes: `region_id`, `status`, `vat_number`.

"Billing profile complete" means `legal_name_ar`, `cr_number`, `city`, `address_building_number`, `address_street`, `address_district` and `address_postal_code` are all set, and `vat_number` is set when `vat_registered`.

**`organization_category`**: `organization_id` fk *cascade*, `category_id` fk *cascade*; primary key `(organization_id, category_id)`.

**`users`** — sd

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| name | str(150) | | | contact person |
| email | str(255) | | | unique |
| phone | str(20) | yes | | E.164 |
| password | str(255) | yes | | null while a team invitation is pending |
| email_verified_at | tstz | yes | | |
| locale | str(2) | | `ar` | `ar` or `en`; used for mail and push |
| avatar_file_id | fk → files, *null* | yes | | |
| status | enum:UserStatus | | active | `active`, `pending_verification`, `deleted` |
| last_login_at | tstz | yes | | |
| ts, sd | | | | |

The model implements `HasLocalePreference` (returns `locale`) and uses Sanctum `HasApiTokens`.

**`memberships`** (v1: exactly one per user)

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| organization_id | fk → organizations *cascade* | | | |
| user_id | fk → users *cascade* | | | **unique** |
| role | enum:OrgRole | | | `owner`, `admin`, `member` |
| can_award | bool | | false | owner is always treated as true |
| can_purchase | bool | | false | owner is always treated as true |
| status | enum:MembershipStatus | | | `invited`, `active`, `inactive` |
| invited_by_user_id | fk → users *null* | yes | | |
| invite_token_hash | char(64) | yes | | unique; sha256 of the plain token |
| invite_expires_at | tstz | yes | | +7 days |
| joined_at | tstz | yes | | |
| ts | | | | |

- Partial unique index: `(organization_id) WHERE role = 'owner'` (one owner per organization).
- Index: `(organization_id, status)`.

**`otp_codes`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id | | | |
| email | str(255) | | index |
| user_id | fk → users *cascade* | yes | |
| purpose | enum:OtpPurpose | | `email_verification`, `password_reset`, `invitation_claim` |
| code_hash | char(64) | | `hash_hmac('sha256', code, config('app.key'))` |
| context | jsonb | yes | e.g. `{invitation_id}` |
| attempts | smallint | | default 0 |
| expires_at | tstz | | now + 10 min |
| consumed_at | tstz | yes | |
| ip | str(45) | yes | |
| created_at | tstz | | |

Index `(email, purpose, created_at)`.

**`consents`**

| Column | Type | Notes |
|---|---|---|
| id | | |
| user_id | fk → users *cascade* | |
| organization_id | fk → organizations *cascade*, null | |
| document_code | enum:LegalDocumentCode | |
| document_version | str(20) | |
| locale | str(2) | |
| accepted_at | tstz | |
| ip | str(45), null | |
| user_agent | str(500), null | |

**`account_deletion_requests`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| user_id | fk → users | | |
| organization_id | fk → organizations | | |
| scope | enum:DeletionScope | | `user` (member leaves and is anonymised) or `organization` (owner deletes the whole organization) |
| reason | text | yes | |
| status | enum:DeletionStatus | | `pending`, `cancelled`, `completed` |
| scheduled_for | tstz | | requested + 14 days |
| cancelled_at / completed_at | tstz | yes | |
| ts | | | |

Partial unique `(user_id) WHERE status = 'pending'`.

### 5.4 Integrations (`000300–000399`)

**`vendors`** (the issuer's own counterparty directory; the R1 "partner")

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| organization_id | fk → organizations *cascade* | | | the owning (issuer) org |
| name | str(200) | | | legal or trading name (Arabic or Latin) |
| name_en | str(200) | yes | | |
| cr_number | str(10) | yes | | |
| vat_number | str(15) | yes | | |
| email | str(255) | | | primary contact, where invitations go |
| contact_name | str(150) | yes | | |
| phone | str(20) | yes | | |
| region_id | fk → regions | yes | | |
| city | str(100) | yes | | |
| status | enum:VendorStatus | | active | `active`, `blocked` (cannot be invited), `archived` |
| linked_organization_id | fk → organizations *null* | yes | | set when the e-mail or CR matches a BAFO organization |
| source | enum:VendorSource | | | `web`, `api`, `import` |
| notes | text | yes | | issuer-private |
| ts | | | | |

Unique `(organization_id, email)`. Indexes `(organization_id, status)` and `(linked_organization_id)`.

**`vendor_category`**: `vendor_id` fk *cascade*, `category_id` fk *cascade*; primary key over both.

**`external_refs`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id | | | |
| organization_id | fk → organizations *cascade* | | owner org |
| refable_type | str(40) | | morph alias: `vendor`, `competition`, `invitation`, `award`, `organization` |
| refable_id | bigint | | |
| system | str(60) | | slug: `sap_s4`, `sap_ecc`, `oracle_fusion`, `oracle_ebs`, `d365_fo`, `d365_bc`, `odoo`, `netsuite`, `zoho`, or `custom:<name>` (regex `^[a-z0-9_]+(:[a-z0-9_]+)?$`) |
| type | str(60) | | e.g. `supplier`, `customer`, `rfq`, `purchase_requisition`, `purchase_order`, `supplier_quotation` |
| value | str(120) | | the ERP key |
| number | str(120) | yes | human document number when it differs |
| url | str(500) | yes | |
| created_by_api_client_id | ref → api_clients | yes | |
| ts | | | |

Unique `(organization_id, refable_type, system, type, value)`. Index `(refable_type, refable_id)`.

**`api_clients`**

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | the public id is the OAuth `client_id` shown to users |
| organization_id | fk → organizations *cascade* | | | |
| name | str(120) | | | |
| description | str(255) | yes | | |
| scopes | jsonb | | | array of scope strings (§14.4) |
| status | enum:ApiClientStatus | | active | `active`, `suspended` (admin), `revoked` |
| oauth_client_id | uuid | yes | | Passport `oauth_clients.id`; unique; **no FK** |
| created_by_user_id | fk → users *null* | yes | | |
| last_used_at | tstz | yes | | |
| last_used_ip | str(45) | yes | | |
| revoked_at | tstz | yes | | |
| ts | | | | |

**`api_keys`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| api_client_id | fk → api_clients *cascade* | | |
| prefix | str(12) | | unique, 8 random `[a-z0-9]` |
| key_hash | char(64) | | sha256 of the full plain key |
| last_four | str(4) | | |
| expires_at | tstz | yes | default +365 days, maximum +730 |
| revoked_at | tstz | yes | |
| last_used_at | tstz | yes | |
| last_used_ip | str(45) | yes | |
| created_by_user_id | fk → users *null* | yes | |
| ts | | | |

**`webhook_endpoints`** — sd

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| organization_id | fk → organizations *cascade* | | | |
| url | str(1000) | | | https (§14.6) |
| description | str(255) | yes | | |
| event_types | jsonb | | | array of catalogue types, or `["*"]` |
| secret | text | | | Eloquent `encrypted` cast. Plain form: `whsec_` + base64 of 32 random bytes |
| status | enum:WebhookEndpointStatus | | active | `active`, `disabled` |
| disabled_reason | str(20) | yes | | `manual` or `failing` |
| failing_since | tstz | yes | | |
| last_success_at / last_failure_at | tstz | yes | | |
| created_by_user_id | fk → users *null* | yes | | |
| created_by_api_client_id | fk → api_clients *null* | yes | | |
| ts, sd | | | | |

**`webhook_events`** (transactional outbox, written by synchronous listeners)

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | the public id is the Standard Webhooks `webhook-id` |
| organization_id | fk → organizations *cascade* | | |
| type | str(60) | | |
| subject_type | str(40) | | morph alias |
| subject_id | bigint | | |
| sequence | bigint | | per `(subject_type, subject_id)`, max + 1 |
| payload | jsonb | | the full envelope body (API.md §4.2) |
| occurred_at | tstz6 | | |
| dispatched_at | tstz | yes | set when delivery rows are created |
| created_at | tstz | | |

Indexes: partial `(created_at) WHERE dispatched_at IS NULL`; `(organization_id, occurred_at)`.

**`webhook_deliveries`**

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| webhook_event_id | fk → webhook_events *cascade* | | | |
| webhook_endpoint_id | fk → webhook_endpoints *cascade* | | | |
| status | enum:DeliveryStatus | | pending | `pending`, `succeeded`, `failed` |
| attempts | smallint | | 0 | |
| next_attempt_at | tstz | yes | | |
| last_attempt_at | tstz | yes | | |
| last_http_status | smallint | yes | | |
| last_error | str(500) | yes | | |
| last_response_excerpt | text | yes | | ≤ 2 KB |
| last_duration_ms | integer | yes | | |
| succeeded_at / failed_at | tstz | yes | | |
| ts | | | | |

Unique `(webhook_event_id, webhook_endpoint_id)`. Index `(status, next_attempt_at)`.

**`import_jobs`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| organization_id | fk *cascade* | | |
| created_by_user_id | fk → users | | |
| type | enum:ImportType | | `vendors` |
| mode | enum:ImportMode | | `validate`, `commit` |
| status | enum:JobStatus | | `queued`, `processing`, `completed`, `failed` |
| source_file_id | fk → files | | |
| errors_file_id | fk → files | yes | |
| total_rows / valid_rows / created_rows / updated_rows / error_rows | integer | | default 0 |
| errors_preview | jsonb | yes | first 100 `{row, column, code, message}` |
| failure_message | str(500) | yes | |
| finished_at | tstz | yes | |
| ts | | | |

**`export_jobs`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| organization_id | fk *cascade* | | |
| created_by_user_id | fk → users | | |
| type | enum:ExportType | | `results`, `offer_log`, `awards`, `vendors` |
| format | enum:ExportFormat | | `csv`, `xlsx` |
| filters | jsonb | | e.g. `{competition_id}` (public id), `{from, to}` |
| status | enum:JobStatus | | |
| file_id | fk → files | yes | |
| row_count | integer | yes | |
| failure_message | str(500) | yes | |
| finished_at | tstz | yes | |
| ts | | | |

### 5.5 Competitions (`000400–000499`)

The migration creates the sequence `competition_reference_seq` (`CREATE SEQUENCE competition_reference_seq START 1`).

**`competitions`** — sd (drafts only); ts6; all time columns tstz6

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| reference_no | str(24) | yes | | unique. Assigned at publish: `BAFO-{T or A}-{YYYY Riyadh}-{nextval padded to 6}` |
| organization_id | fk → organizations | | | issuer |
| created_by_user_id | fk → users *null* | yes | | |
| created_by_api_client_id | fk → api_clients *null* | yes | | |
| source | enum:CompetitionSource | | | `web`, `ios`, `android`, `api` |
| title | str(200) | | | |
| description | text | yes | | ≤ 20 000 chars; required at publish |
| category_id | fk → categories | | | |
| category_other_text | str(150) | yes | | required when the category `is_other` |
| region_id | fk → regions | | | |
| direction | enum:Direction | | | `tender`, `auction` |
| format | enum:Format | | | `live`, `sealed` |
| status | enum:CompetitionStatus | | draft | §6.1 |
| currency | char(3) | | `SAR` | |
| preset_code | str(60) | yes | | informational |
| start_price_minor | money | yes | | tender: ceiling (optional); auction: opening (required). Always disclosed. |
| reserve_price_minor | money | yes | | issuer-only; never rejects an offer |
| min_step_minor | money | yes | | absolute step; mutually exclusive with `min_step_bps` |
| min_step_bps | integer | yes | | percentage step in basis points (50 = 0.5%) |
| amount_granularity_minor | integer | | 100 | 100 = whole riyals, 1 = halalas |
| must_beat | enum:MustBeat | yes | | `own`, `best`; null for sealed |
| rank_visibility | enum:RankVisibility | | leading_flag | `full`, `leading_flag`, `none` |
| show_prices | bool | | false | |
| auto_extend_enabled | bool | | false | |
| auto_extend_window_seconds | integer | yes | | |
| auto_extend_by_seconds | integer | yes | | |
| auto_extend_max | integer | yes | | |
| final_window_minutes | integer | yes | | null = no final pricing window |
| bafo_round_enabled | bool | | false | |
| bafo_duration_minutes | integer | yes | | default 60 when enabled |
| min_participants | smallint | | 2 | |
| result_publication | enum:ResultPublication | | outcome_only | `none`, `outcome_only`, `outcome_and_amount` |
| bidding_opens_at | tstz6 | yes | | null in draft means "at publish" |
| scheduled_close_at | tstz6 | yes | | required at publish |
| effective_close_at | tstz6 | yes | | set at publish = scheduled; moved by extensions |
| hard_stop_at | tstz6 | yes | | at publish, when auto-extend is on: `scheduled_close_at + auto_extend_max × auto_extend_by_seconds` |
| final_window_starts_at | tstz6 | yes | | at publish: `scheduled_close_at − final_window_minutes` |
| invitation_cutoff_at | tstz6 | yes | | at publish: `final_window_starts_at` if set, else `scheduled_close_at − setting competitions.invite_cutoff_minutes` |
| extension_count | integer | | 0 | |
| notified_thresholds | jsonb | | `[]` | closing-soon minutes already announced |
| published_at / opened_at / final_window_started_at / closed_at / offers_opened_at / awarded_at / not_awarded_at / cancelled_at | tstz6 | yes | | lifecycle stamps |
| cancel_reason_id | fk → close_reasons | yes | | kind `cancel` |
| cancel_note | text | yes | | |
| cancelled_by_user_id | fk → users *null* | yes | | |
| cancelled_by_admin_id | ref → admins | yes | | |
| not_awarded_reason_id | fk → close_reasons | yes | | kind `not_awarded` |
| not_awarded_note | text | yes | | |
| ts6, sd | | | | |

Indexes:

- `(organization_id, status)`
- `(status, bidding_opens_at)`
- `(status, effective_close_at)`
- `(status, final_window_starts_at)`
- `(status, invitation_cutoff_at)`
- CHECK `start_price_minor IS NULL OR start_price_minor > 0` (the same for reserve and min_step)
- CHECK `NOT (min_step_minor IS NOT NULL AND min_step_bps IS NOT NULL)`

**`competition_extensions`** (ts6; no updated_at)

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| competition_id | fk *cascade* | | |
| kind | enum:ExtensionKind | | `auto`, `manual`, `admin` |
| previous_close_at | tstz6 | | |
| new_close_at | tstz6 | | |
| triggered_by_offer_id | ref → offers | yes | auto only |
| actor_user_id | fk → users *null* | yes | |
| actor_admin_id | ref → admins | yes | |
| reason | text | yes | required for manual and admin |
| created_at | tstz6 | | |

**`competition_attachments`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| competition_id | fk *cascade* | | |
| kind | enum:AttachmentKind | | `document` (joined participants and issuer), `invitation_document` (also visible on the invitee teaser), `external_link` (joined participants and issuer) |
| file_id | fk → files | yes | null for `external_link` |
| title | str(200) | yes | defaults to the original file name |
| url | str(1000) | yes | https; `external_link` only |
| is_addendum | bool | | true when added after publish |
| uploaded_by_user_id | fk → users *null* | yes | |
| sort_order | smallint | | default 0 |
| ts | | | |

**`invitations`**

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| competition_id | fk *cascade* | | | |
| email | str(255) | | | |
| name | str(150) | yes | | contact name |
| organization_id | fk → organizations *null* | yes | | set at creation when known, or at claim |
| vendor_id | fk → vendors *null* | yes | | when invited from the vendor directory |
| status | enum:InvitationStatus | | draft | §6.2 |
| sponsored_requested | bool | | false | the issuer ticked "cover fees" (used in `selected` mode) |
| token_hash | char(64) | yes | | unique; created when sent |
| invited_by_user_id | fk → users *null* | yes | | |
| invited_by_api_client_id | fk → api_clients *null* | yes | | |
| sent_at / viewed_at / joined_at / declined_at / revoked_at / expired_at | tstz | yes | | |
| decline_reason | str(500) | yes | | |
| revoke_reason | enum:RevokeReason | yes | | `issuer`, `duplicate_organization`, `admin` |
| ts | | | | |

Unique `(competition_id, email)`. Indexes `(organization_id, status)` and `(competition_id, status)`.

**`participants`** (created on join; the "participation lock")

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| competition_id | fk *cascade* | | |
| organization_id | fk → organizations | | |
| invitation_id | fk → invitations | | unique |
| alias_no | smallint | | random unused integer in 1–99 (then 100–999); shown as "Participant {n}" |
| entitlement_source | enum:EntitlementSource | | `plan`, `sponsored_pass`, `grant` |
| terms_version | str(40) | | `{competition_rules legal version}` |
| terms_accepted_at | tstz | | |
| terms_ip | str(45) | yes | |
| joined_by_user_id | fk → users | | |
| ts | | | |

Unique `(competition_id, organization_id)` and `(competition_id, alias_no)`.

**`comments`** (Q&A)

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| competition_id | fk *cascade* | | |
| parent_id | fk → comments *cascade* | yes | one level of replies only |
| author_user_id | fk → users | | |
| author_organization_id | fk → organizations | | |
| author_participant_id | fk → participants | yes | null when the author is the issuer |
| is_issuer | bool | | |
| body | text | | 1–2000 chars |
| ts | | | |

Index `(competition_id, created_at)`.

### 5.6 Bidding (`000500–000599`)

**`competition_live_states`** (1:1 with a published competition; created lazily under the competition lock with `firstOrCreate`)

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| competition_id | fk → competitions *cascade* | | | **primary key** |
| version | bigint | | 0 | bumped on every audience-visible change (§9.3) |
| last_seq | bigint | | 0 | gapless offer sequence |
| leader_participant_id | fk → participants *null* | yes | | |
| leader_offer_id | ref → offers | yes | | |
| leader_amount_minor | money | yes | | |
| accepted_offer_count | integer | | 0 | non-voided |
| participants_with_offers | integer | | 0 | |
| reserve_met | bool | yes | | null when there is no reserve |
| ledger_head_hash | char(64) | yes | | |
| updated_at | tstz6 | | | |

**`offers`** — the **append-only ledger**. It has a BEFORE UPDATE OR DELETE trigger that calls `bafo_forbid_update_delete()`, and no `updated_at`.

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| competition_id | fk → competitions | | |
| participant_id | fk → participants | | |
| organization_id | fk → organizations | | participant org (denormalised) |
| submitted_by_user_id | fk → users | | |
| seq | bigint | | per competition, starts at 1, gapless |
| stage | enum:OfferStage | | `initial`, `live`, `sealed`, `bafo` |
| amount_minor | money | | CHECK > 0 |
| rank_key | bigint | | `amount_minor` for tender, `−amount_minor` for auction |
| accepted_at | tstz6 | | DB clock read after the lock |
| idempotency_key | str(64) | | |
| channel | enum:Channel | | `web`, `ios`, `android`, `api` |
| ip | str(45) | yes | |
| user_agent | str(500) | yes | |
| outlier_confirmed | bool | | |
| prev_hash | char(64) | yes | null for seq 1 |
| hash | char(64) | | see the hash formula below |
| created_at | tstz6 | | = accepted_at |

Unique `(competition_id, seq)` and `(participant_id, idempotency_key)`. Index `(competition_id, participant_id, seq)`.

**Hash formula.**

```php
$hash = hash('sha256', implode('|', [
    $prevHash ?? '',                              // the previous row's hash, or '' for seq 1
    $competition->public_id, (string) $seq, $participant->public_id,
    (string) $amountMinor, $stage->value,
    $acceptedAt->utc()->format('Y-m-d\TH:i:s.u\Z'),  // microseconds
]));
```

**`offer_voids`** (append-only; trigger)

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| offer_id | fk → offers | | unique |
| reason_id | fk → close_reasons | | kind `void_offer` |
| note | text | yes | required when the reason `requires_note` |
| voided_by_admin_id | ref → admins | | |
| created_at | tstz6 | | |

**`offer_rejections`** (rejected attempts, kept for disputes)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | | | |
| competition_id | fk *cascade* | | |
| participant_id | fk *cascade* | yes | |
| user_id | fk → users *null* | yes | |
| amount_minor | bigint | yes | |
| code | str(60) | | error code |
| idempotency_key | str(64) | yes | |
| stage | str(10) | yes | |
| received_at | tstz6 | | app time |
| db_time | tstz6 | yes | DB clock, when the lock was taken |
| channel | str(10) | yes | |
| ip | str(45) | yes | |
| created_at | tstz6 | | |

Index `(competition_id, created_at)`.

**`participant_standings`** (derived; can be rebuilt from the ledger minus voids)

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| participant_id | fk → participants *cascade* | | | **primary key** |
| competition_id | fk → competitions *cascade* | | | |
| current_offer_id | fk → offers | yes | | latest non-voided offer |
| current_amount_minor | money | yes | | |
| current_rank_key | bigint | yes | | |
| current_at | tstz6 | yes | | `accepted_at` of the current offer |
| current_seq | bigint | yes | | |
| first_amount_minor | money | yes | | first non-voided offer |
| offers_count | integer | | 0 | non-voided |
| rank | integer | yes | | 1 = leader |
| is_leader | bool | | false | |
| bafo_shortlisted | bool | | false | |
| bafo_reference_amount_minor | money | yes | | current amount when the BAFO round started |
| bafo_offer_id | fk → offers | yes | | |
| last_offer_at | tstz6 | yes | | |
| updated_at | tstz6 | | | |

Index `(competition_id, current_rank_key, current_at, current_seq)`.

**`bafo_rounds`** (at most one per competition in the MVP)

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| competition_id | fk *cascade* | | unique |
| started_by_user_id | fk → users | | |
| status | enum:BafoRoundStatus | | `running`, `ended` |
| starts_at | tstz6 | | |
| cutoff_at | tstz6 | | |
| ended_at | tstz6 | yes | |
| shortlist_count | integer | | |
| ts6 | | | |

**`awards`** (ts6)

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| competition_id | fk → competitions | | |
| participant_id | fk → participants | | |
| organization_id | fk → organizations | | the winner |
| offer_id | fk → offers | | the winner's current offer at award time |
| amount_minor | money | | |
| currency | char(3) | | |
| status | enum:AwardStatus | | `issued`, `revoked` |
| is_leading_offer | bool | | |
| rank_at_award | integer | | |
| reserve_met | bool | yes | null without a reserve |
| justification_reason_id | fk → close_reasons | yes | kind `award_justification`; required when not leading or the reserve is not met |
| justification_text | text | yes | |
| message_to_winner | text | yes | ≤ 2000 |
| internal_notes | text | yes | issuer-only |
| awarded_by_user_id | fk → users | | |
| awarded_at | tstz6 | | |
| revoked_by_user_id | fk → users *null* | yes | |
| revoked_at | tstz6 | yes | |
| revoke_reason | text | yes | |
| erp_sync_status | enum:ErpSyncStatus | | `not_required` (organization `api_enabled = false` at award), `pending`, `synced`, `failed` |
| erp_sync_message | str(500) | yes | |
| erp_synced_at | tstz6 | yes | |
| ledger_head_hash | char(64) | | copied from the live state at award |
| ts6 | | | |

Partial unique `(competition_id) WHERE status = 'issued'`.

**`competition_reports`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id | | | |
| competition_id | fk *cascade* | | |
| locale | str(2) | | |
| status | enum:ReportStatus | | `pending`, `ready`, `failed` |
| file_id | fk → files | yes | |
| live_version | bigint | | the live-state version that was rendered |
| generated_at | tstz | yes | |
| ts | | | |

Unique `(competition_id, locale)`.

### 5.7 Billing (`000600–000699`)

The migration creates the sequence `invoice_number_seq`.

**`plans`**

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| code | str(40) | | | unique: `single`, `plus`, `pro`, `custom` |
| name | jsonb | | | |
| description | jsonb | yes | | |
| features | jsonb | | `[]` | array of `{ar, en}` |
| seats | smallint | yes | | total users including the owner; null for custom |
| monthly_price_minor / annual_price_minor | money | yes | | excl. VAT; null for custom |
| monthly_list_price_minor / annual_list_price_minor | money | yes | | struck-through "before" price |
| is_custom | bool | | false | |
| is_featured | bool | | false | |
| is_active | bool | | true | |
| sort_order | smallint | | 0 | |
| ts | | | | |

Demo seed:

| Plan | Seats | Price (monthly / annual, halalas) | List price (monthly / annual, halalas) |
|---|---|---|---|
| single | 1 | 31 500 / 315 000 | 150 000 / 1 500 000 |
| plus | 2 | 90 000 / 900 000 | 200 000 / 2 000 000 |
| pro | 3 | 150 000 / 1 500 000 | 300 000 / 3 000 000 |
| custom | – | – | – |

All values are admin-editable.

**`coupons`** (coupons and org-scoped vouchers)

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| code | str(40) | | | unique, stored UPPERCASE; vouchers `V-` + 10 random `[A-Z0-9]` |
| kind | enum:CouponKind | | | `coupon`, `voucher` |
| discount_type | enum:DiscountType | | | `percent`, `fixed` |
| percent_bps | integer | yes | | percent coupons, 1–10000 |
| amount_minor | money | yes | | fixed coupons: the discount; vouchers: the original value |
| balance_minor | money | yes | | vouchers: remaining value |
| applies_to | enum:CouponScope | | any | `any`, `subscription`, `sponsorship` |
| organization_id | fk → organizations *cascade* | yes | | org-scoped (every voucher) |
| max_redemptions | integer | yes | | null = unlimited |
| redemptions_count | integer | | 0 | |
| per_organization_limit | integer | yes | 1 | vouchers: null (the balance governs) |
| valid_from / valid_until | tstz | yes | | vouchers: now → +12 months |
| is_active | bool | | true | |
| reason | text | yes | | e.g. "Unused passes BAFO-T-2026-000123" |
| source_competition_id | fk → competitions *null* | yes | | |
| created_by_admin_id | ref → admins | yes | | |
| ts | | | | |

**`payments`** (the checkout order and payment record)

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| organization_id | fk → organizations | | | |
| created_by_user_id | fk → users | | | |
| purpose | enum:PaymentPurpose | | | `subscription`, `sponsorship` |
| status | enum:PaymentStatus | | pending | §6.4 |
| gateway | str(20) | | | `fake`, `moyasar` |
| gateway_reference | str(120) | yes | | unique |
| currency | char(3) | | SAR | |
| subtotal_minor | money | | | sum of the lines |
| discount_minor | money | | 0 | coupon or voucher |
| credit_minor | money | | 0 | pro-rata upgrade credit |
| vat_rate_bp | integer | | 1500 | |
| vat_minor | money | | | `Money::vat(subtotal − discount − credit)` |
| total_minor | money | | | subtotal − discount − credit + vat (≥ 0) |
| coupon_id | fk → coupons *null* | yes | | |
| idempotency_key | str(64) | yes | | unique with `organization_id` |
| return_url | str(1000) | | | allow-listed (§13.1) |
| redirect_url | str(1000) | yes | | the gateway checkout URL |
| metadata | jsonb | | | per purpose (§13) |
| expires_at | tstz | | | created + setting `billing.checkout_hold_minutes` (30) |
| paid_at / failed_at / refunded_at | tstz | yes | | |
| failure_code | str(60) | yes | | e.g. `declined`, `expired`, `amount_mismatch` |
| failure_message | str(500) | yes | | |
| refund_reference | str(120) | yes | | admin-recorded |
| refunded_by_admin_id | ref → admins | yes | | |
| manual_reference | str(120) | yes | | admin "mark as paid" (bank transfer) |
| ts | | | | |

Indexes `(status, created_at)` and `(organization_id, created_at)`.

A zero-total payment (for example one covered by a voucher) is fulfilled immediately without the gateway: status `succeeded`, gateway `fake`, `gateway_reference` = `internal:{public_id}`.

**`payment_lines`**

| Column | Type | Notes |
|---|---|---|
| id | | |
| payment_id | fk *cascade* | |
| kind | enum:PaymentLineKind | `plan`, `custom_seats`, `sponsored_pass` |
| description | jsonb | `{ar, en}` |
| quantity | integer | |
| unit_price_minor | money | |
| net_minor | money | quantity × unit |
| ref_type | str(40), null | |
| ref_id | bigint, null | |
| ts | | |

**`coupon_redemptions`**

| Column | Type | Notes |
|---|---|---|
| id | | |
| coupon_id | fk *cascade* | |
| organization_id | fk | |
| payment_id | fk → payments | unique |
| amount_minor | money | discount actually applied |
| redeemed_at | tstz | |
| ts | | |

**`subscriptions`**

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| organization_id | fk → organizations | | | |
| plan_id | fk → plans | | | |
| source | enum:SubscriptionSource | | | `paid`, `trial`, `grant` |
| interval | enum:BillingInterval | yes | | `monthly`, `annual` (paid only) |
| seats | smallint | | | |
| status | enum:SubscriptionStatus | | | §6.5 |
| starts_at / ends_at | tstz | yes | | set on activation |
| unit_price_minor / subtotal_minor / discount_minor / credit_minor / vat_minor / total_minor | money | yes | | snapshot from the payment (null for trial or grant) |
| payment_id | fk → payments *null* | yes | | |
| replaces_subscription_id | fk → subscriptions *null* | yes | | upgrade or renewal link |
| granted_by_admin_id | ref → admins | yes | | |
| grant_reason | text | yes | | |
| activated_at / superseded_at / expired_at / cancelled_at | tstz | yes | | |
| reminders_sent | jsonb | | `[]` | e.g. `[7, 3, 1]` |
| ts | | | | |

Index `(organization_id, status, ends_at)`.

"Current subscription" means `status = active AND starts_at <= now < ends_at`. An `active` subscription with `starts_at > now` is "upcoming" (a queued renewal).

**`competition_sponsorships`**

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| competition_id | fk → competitions *cascade* | | | unique |
| organization_id | fk → organizations | | | the sponsor (the issuer) |
| mode | enum:SponsorshipMode | | | `all`, `selected` (a row exists only when mode ≠ none) |
| max_passes | integer | yes | | cap; null = no cap |
| unit_price_minor | money | | | snapshot from setting `sponsorship.pass_price_{direction}_minor` when the row is created; frozen at first funding |
| vat_rate_bp | integer | | 1500 | |
| funded_passes | integer | | 0 | slots paid for (or granted) |
| status | enum:SponsorshipStatus | | draft | `draft`, `active` (≥ 1 funded), `settled` |
| settled_at | tstz | yes | | |
| unused_count | integer | yes | | set at settlement |
| voucher_coupon_id | fk → coupons *null* | yes | | set when the admin issues the voucher |
| configured_by_user_id | fk → users | | | |
| ts | | | | |

**`sponsored_passes`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| sponsorship_id | fk *cascade* | | |
| competition_id | fk → competitions | | |
| invitation_id | fk → invitations | | |
| organization_id | fk → organizations *null* | yes | invitee org, once known |
| payment_id | fk → payments *null* | yes | the funding payment (null for `freed_slot` or `admin_grant`) |
| source | enum:PassSource | | `purchase`, `freed_slot`, `admin_grant` |
| status | enum:PassStatus | | §6.3 |
| release_reason | enum:PassReleaseReason | yes | `declined`, `revoked`, `covered_by_own_plan`, `duplicate_organization` |
| hold_expires_at | tstz | yes | pending only |
| reserved_at / joined_at / released_at / settled_at / voided_at | tstz | yes | |
| ts | | | |

Partial unique `(invitation_id) WHERE status IN ('pending','reserved','joined')`.

**Free slots** = `funded_passes − count(passes WHERE status IN (reserved, joined))`. A released pass frees its slot. An `unused` pass does not free a slot (settlement is final).

**`invoices`**

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| number | str(30) | | | unique: `BAFO-INV-{YYYY}-{nextval padded to 6}` |
| organization_id | fk | | | buyer |
| payment_id | fk → payments | yes | | unique when set |
| type | enum:InvoiceType | | tax_invoice | `tax_invoice`, `credit_note` (credit notes are recorded by the admin only) |
| original_invoice_id | fk → invoices *null* | yes | | |
| issue_date / supply_date | date | | | Riyadh date |
| currency | char(3) | | | |
| subtotal_minor / discount_minor / vat_minor / total_minor | money | | | discount includes the pro-rata credit |
| vat_rate_bp | integer | | | |
| seller_snapshot | jsonb | | | from config `bafo.billing.seller` |
| buyer_snapshot | jsonb | | | legal names, CR, VAT, address from the organization |
| einvoice_provider | str(20) | | | `fake`, … |
| einvoice_status | enum:EInvoiceStatus | | pending | `pending`, `cleared`, `reported`, `rejected`, `failed` |
| einvoice_document_id | str(120) | yes | | |
| zatca_uuid | uuid | yes | | |
| qr_payload | text | yes | | base64 TLV |
| einvoice_attempts | smallint | | 0 | |
| einvoice_last_error | text | yes | | |
| pdf_file_id | fk → files | yes | | |
| issued_at | tstz | | | |
| cleared_at | tstz | yes | | |
| ts | | | | |

**`invoice_lines`**

| Column | Type | Notes |
|---|---|---|
| id | | |
| invoice_id | fk *cascade* | |
| description | jsonb | |
| quantity | integer | |
| unit_price_minor | money | |
| net_minor | money | |
| ts | | |

### 5.8 Notifications (`000700–000799`)

**`notifications`** (Laravel database-notification shape; the id is a ULID string, the one exception to `id`/`public_id`)

| Column | Type | Null | Notes |
|---|---|---|---|
| id | char(26) | | primary key; set by the base notification class (`strtolower(Str::ulid())`) |
| type | str(255) | | notification class |
| notifiable_type / notifiable_id | `morphs('notifiable')` (bigint id) | | alias `user` |
| data | jsonb | | `{type, params, subject: {type, id}, route}` (§11.2) |
| read_at | tstz | yes | |
| ts | | | |

Index `(notifiable_type, notifiable_id, read_at)`.

**`device_tokens`**

| Column | Type | Null | Notes |
|---|---|---|---|
| id / public_id | | | |
| user_id | fk → users *cascade* | | |
| token | str(512) | | unique (FCM token) |
| platform | enum:DevicePlatform | | `ios`, `android`, `web` |
| device_name | str(120) | yes | |
| app_version | str(20) | yes | |
| locale | str(2) | | |
| last_seen_at | tstz | | |
| ts | | | |

### 5.9 Admin (`000800–000899`)

**`admins`**

| Column | Type | Null | Default | Notes |
|---|---|---|---|---|
| id / public_id | | | | |
| name | str(150) | | | |
| email | str(255) | | | unique |
| password | str(255) | | | |
| role | enum:AdminRole | | operator | `super_admin`, `operator` (operators cannot manage admins, plans, prices or settings) |
| is_active | bool | | true | |
| app_authentication_secret | text | yes | | Filament MFA (encrypted cast) |
| app_authentication_recovery_codes | text | yes | | encrypted cast |
| last_login_at | tstz | yes | | |
| remember_token | str(100) | yes | | |
| ts | | | | |

---

## 6. State machines

Every transition happens inside a DB transaction that holds the relevant row lock. Every transition writes an audit entry (§4.5) and dispatches the listed event. An attempt outside the table returns **409 `invalid_state_transition`**, with `details: {from, to}`.

### 6.1 Competition (`competitions.status`)

```
                 publish (opens ≤ now)
  draft ──publish──▶ scheduled ──tick (opens_at)──▶ live ──close job (effective_close_at)──▶ closed ◀──┐
    │                   │                            │                                       │  ▲   │   │
 (soft delete)          └──cancel──▶ cancelled ◀─────┘ (cancel)                              │  │   │  revoke
                                        ▲                                  start BAFO round  │  │   │   │
                                        └─────────cancel─────────── bafo_round ◀──────────────┘  │   │   │
                                                                         └──cutoff──────────────┘   │   │
                                                         closed ──award──▶ awarded ──────────────────────┘
                                                         closed ──close without award──▶ not_awarded
```

| # | From → To | Action (owner) | Actor | Guards | Side effects (same TX unless noted) |
|---|---|---|---|---|---|
| T1 | draft → scheduled | `PublishCompetition` (Competitions) | issuer user with `competitions.manage` on it; API client with `competitions:publish`; Billing after payment (system actor, audited on behalf of the payer) | §7.2 publish checks; effective opens > now | `published_at`, `reference_no`, derived times (§5.5); invitations `draft → sent` (tokens and mails); passes reserved (Billing); `CompetitionPublished`, `InvitationSent` × n |
| T2 | draft → live | `PublishCompetition` | same | `bidding_opens_at` null or ≤ now | as T1, plus `bidding_opens_at = opened_at = now`; `CompetitionOpened` |
| T3 | scheduled → live | `OpenDueCompetitions` (tick) | system | now ≥ `bidding_opens_at` | `opened_at`; `CompetitionOpened` |
| T4 | scheduled → cancelled | `CancelCompetition` | issuer (`competitions.manage`), API (`competitions:manage`), admin | reason of kind `cancel` (+ note if required) | `cancelled_at` and the `cancel_*` columns; invitations `sent`/`viewed` → `expired`; `CompetitionCancelled` |
| T5 | live → closed | `CloseCompetition` (job or tick), or `ForceCloseCompetition` (admin) | system or admin | DB clock ≥ `effective_close_at`. Admin force-close first sets `effective_close_at = now` and writes an `admin` extension row with the reason. | `BiddingEngine::finalizeLiveBidding()`; `closed_at = effective_close_at`; `offers_opened_at = now` if sealed; invitations `sent`/`viewed` → `expired`; `CompetitionClosed` |
| T6 | live → cancelled | `CancelCompetition` | as T4 | as T4 | as T4 |
| T7 | closed → bafo_round | `StartBafoRound` (Bidding) | issuer with `competitions.award` | `bafo_round_enabled`; no round exists yet; shortlist valid (§7.11) | `bafo_rounds` row; standings flagged; `BafoRoundStarted` |
| T8 | bafo_round → closed | `EndDueBafoRounds` (Bidding tick) | system | DB clock ≥ `cutoff_at` | round `ended`; final re-rank; `BafoRoundEnded` |
| T9 | bafo_round → cancelled | `CancelCompetition` | as T4 | as T4 | as T4 |
| T10 | closed → awarded | `IssueAward` (Bidding) | issuer with `competitions.award` | §7.12 | `awarded_at`; `AwardIssued` |
| T11 | awarded → closed | `RevokeAward` (Bidding) | issuer with `competitions.award` | reason text required | `awarded_at = null`; `AwardRevoked` |
| T12 | closed → not_awarded | `CloseWithoutAward` (Competitions) | issuer with `competitions.award`; API `competitions:manage`; admin | reason of kind `not_awarded` | `not_awarded_at`, the `not_awarded_*` columns; `CompetitionClosedWithoutAward` |

- **There is no early close.** Once bidding has opened, the issuer can only cancel (fairness, R5 §4.5). Only the platform admin can force-close.
- **Deleting a draft** is `DeleteDraftCompetition`: a soft delete, allowed only in `draft`. It is not a transition.
- **Terminal states** are `awarded` (unless revoked), `not_awarded` and `cancelled`. `CompetitionStateMachine::transition()` (§3.6) is the only code that writes `status`.

**Derived `phase`** (only while `status = live`), `Competition::phaseAt(CarbonInterface $t): ?Phase`:

```
if status != live            → null
if format == sealed          → sealed
if final_window_minutes null → open
t < final_window_starts_at   → initial
otherwise                    → final_window
```

### 6.2 Invitation (`invitations.status`)

| From → To | Trigger | Rules |
|---|---|---|
| (new) → draft | `InviteParticipants` on a draft competition | No mail |
| (new) → sent | `InviteParticipants` on a scheduled or live competition | Only while now < `invitation_cutoff_at`; creates the token and sends the mail |
| draft → sent | `PublishCompetition` | As above |
| draft → *deleted* | `RemoveInvitation` on a draft competition | Hard delete |
| sent → viewed | The invitee organization opens the competition, or the token lookup is called | Idempotent |
| sent, viewed → joined | `JoinCompetition` | now < `invitation_cutoff_at`; competition `scheduled` or `live`; `AccessPolicy::resolveJoin()` allows; creates the participant |
| sent, viewed → declined | `DeclineInvitation` (in-app or by token) | Before joining |
| sent, viewed → revoked | `RevokeInvitation` (issuer, admin), or a duplicate organization at join | Before joining |
| sent, viewed → expired | Tick at `invitation_cutoff_at`; competition closed or cancelled | – |

Terminal states: `joined`, `declined`, `revoked`, `expired`.

### 6.3 Sponsored pass (`sponsored_passes.status`)

| From → To | Trigger |
|---|---|
| (new) → pending | Sponsorship checkout created for specific invitations; `hold_expires_at = now + 30 min` |
| (new) → reserved | A covered invitation is sent while free slots exist (`source = freed_slot` or `purchase` after funding); admin grant (`admin_grant`) |
| pending → reserved | Payment `succeeded` |
| pending → void | Payment failed or expired, or the hold expired |
| reserved → joined | The invitee joins **without** its own plan |
| reserved → released | Declined, revoked, invitee has its own plan at join (`covered_by_own_plan`), or duplicate organization. The slot is freed. |
| reserved → unused | Settlement at close or cancel |

Terminal states: `joined`, `released`, `unused`, `void`.

### 6.4 Payment (`payments.status`)

| From → To | Trigger |
|---|---|
| pending → succeeded | Gateway confirms paid; the amount and currency equal `total_minor`/`currency` (otherwise → failed, `amount_mismatch`) |
| pending → failed | Gateway declined, or the user cancelled on the hosted page |
| pending → expired | Reconciler: `expires_at` passed and the gateway reports not paid |
| expired → succeeded | Late confirmation. Fulfil if still possible; otherwise set `metadata.needs_manual_review = true` and alert the admins. |
| succeeded → refunded | Admin records a refund done in the gateway dashboard |

Fulfilment (§13) runs exactly once, on the transition into `succeeded`, under `SELECT … FOR UPDATE` on the payment row.

### 6.5 Subscription (`subscriptions.status`)

| From → To | Trigger |
|---|---|
| (new) → pending_payment | Subscription checkout created |
| (new) → active | Trial or admin grant |
| pending_payment → active | Payment succeeded |
| pending_payment → cancelled | Payment failed or expired |
| active → superseded | Upgrade activated, or a paid plan activated during a trial |
| active → expired | `ends_at ≤ now` (job) |

### 6.6 Other state machines

| Object | States and transitions |
|---|---|
| Award | `issued → revoked`. A new award after a revoke is a new row. |
| BAFO round | `running → ended` |
| Membership | `invited → active` (accept); `active ↔ inactive`; removal deletes the membership and soft-deletes the user |
| Webhook delivery | `pending → succeeded`; `pending → pending` (retry scheduled); `pending → failed` (attempts exhausted); `failed → pending` (manual redeliver) |
| Webhook endpoint | `active ↔ disabled` (manual, or auto after 5 days of continuous failure) |
| Account deletion request | `pending → cancelled`; `pending → completed` (executor) |
| Import and export jobs | `queued → processing → completed \| failed` |
| Organization | `active ↔ suspended` (admin); `active → deleted` (account deletion executor) |

---

## 7. The bidding engine (Bidding module, R5 MVP)

### 7.1 Direction

Every comparison uses one sign **`d`**: `tender → −1`, `auction → +1`.

| Relation | Formula |
|---|---|
| "a is better than b" | `d × (a − b) > 0` |
| Sort key | `rank_key = −d × amount`, so ascending `rank_key` is best first. Tender: `rank_key = amount`. Auction: `rank_key = −amount`. |

**No code path may hard-code "lowest" or "highest".** Copy is rendered from `direction`.

### 7.2 Rules validation (Competitions `RulesValidator`)

"Save" checks run on create and update (draft). "Publish" checks run again, all together, at publish. Violations return 422 `validation_failed` with field errors. The error message keys are `competitions.validation.<rule>`.

| # | Rule | When | Field |
|---|---|---|---|
| R1 | `format = sealed` ⇒ `must_beat` null, `rank_visibility = none`, `show_prices = false`, `auto_extend_enabled = false`, `final_window_minutes` null | save | the offending field |
| R2 | `format = live` ⇒ `must_beat` ∈ {own, best} | save | `must_beat` |
| R3 | `must_beat = best` ⇒ `show_prices = true` (anti-probing) | save | `must_beat` |
| R4 | `result_publication = outcome_and_amount` ⇒ `show_prices = true` | save | `result_publication` |
| R5 | `direction = auction` ⇒ `start_price_minor` required | publish | `start_price_minor` |
| R6 | tender: `reserve ≤ start`; auction: `reserve ≥ start` (when both are set) | save | `reserve_price_minor` |
| R7 | At most one of `min_step_minor` / `min_step_bps`. `min_step_bps` 1–5000. `min_step_minor` > 0 and < `start_price_minor` if set. | save | – |
| R8 | `start_price_minor`, `reserve_price_minor` and `min_step_minor` are positive multiples of `amount_granularity_minor` and ≤ setting `bidding.max_amount_minor` | save | – |
| R9 | `amount_granularity_minor` ∈ {1, 100} | save | – |
| R10 | `auto_extend_enabled` ⇒ `window` and `by` in [setting min, max] (default 60–1800 s), `max` in 1–50; otherwise the three fields are null | save | – |
| R11 | `final_window_minutes` null or in [30, 600] (settings); at publish, < bidding duration in minutes | save, publish | – |
| R12 | `bafo_round_enabled` ⇒ `bafo_duration_minutes` in [15, 4320], default 60; else null | save | – |
| R13 | `min_participants` in 1–50 | save | – |
| R14 | `direction = auction` ⇒ `organization.auction_enabled` (else **403 `auction_not_enabled`**) and `category.auction_allowed` (else 422 on `category_id`) | save | – |
| R15 | `category.is_other` ⇒ `category_other_text` required | publish | – |
| R16 | Schedule. `opens = bidding_opens_at ?? now`. At publish: `opens ≥ now − 60 s`; `scheduled_close_at − opens ≥ competitions.min_duration_minutes` (10); ≤ `competitions.max_duration_days` (90). At save, when both are set: close > opens. | save, publish | `bidding_opens_at`, `scheduled_close_at` |
| R17 | Invitations in `draft` ≥ `min_participants`, and ≤ setting `competitions.max_participants` (200) | publish | `invitations` → 422 `min_participants_not_met` |
| R18 | `description` is not blank | publish | `description` |
| R19 | Live-event cap: the number of competitions in `scheduled` or `live` whose window `[bidding_opens_at, coalesce(hard_stop_at, scheduled_close_at)]` overlaps this one must be < setting `bidding.max_concurrent_live` (30) | publish | **409 `live_event_capacity_reached`** |
| R20 | `AccessPolicy::canIssue(org)` | create, publish | **403 `issuer_plan_required`** |
| R21 | `SponsorshipService::reserveForPublish()` | publish | **409 `sponsorship_payment_required`** |

**Derived values at publish.**

| Value | Rule |
|---|---|
| `effective_close_at` | `scheduled_close_at` |
| `hard_stop_at` | `scheduled_close_at + max × by`, when auto-extend is on; otherwise null |
| `final_window_starts_at` | `scheduled_close_at − final_window_minutes` |
| `invitation_cutoff_at` | `final_window_starts_at`, or else `scheduled_close_at − competitions.invite_cutoff_minutes` (60), but never earlier than `opens` |

### 7.3 Stages

| Competition state | Offer `stage` | Rules that apply |
|---|---|---|
| live / `sealed` | `sealed` | Start-price bound only. Revisions may move in either direction. No visibility. No anti-sniping. |
| live / `initial` | `initial` | `must_beat` is forced to `own`. Visibility is own-only. No anti-sniping. |
| live / `open` or `final_window` | `live` | The full rules: `must_beat`, step, visibility, anti-sniping |
| bafo_round | `bafo` | One offer per shortlisted participant. Must not be worse than `bafo_reference_amount_minor`. No step. Visibility as in `sealed`, except each participant still sees their own BAFO status. |

### 7.4 Offer acceptance: `POST /api/app/v1/competitions/{id}/offers` (`SubmitOffer` Action)

The input is `amount_minor` (int) and `confirm_outlier` (bool, default false), plus the `Idempotency-Key` header, which is required. The steps run **in this exact order**.

```
PRE-CHECKS (no transaction, no lock)
 1. auth:sanctum; permission participation.submit_offers                         → 403 forbidden
 2. Idempotency-Key header present, 8–64 chars [A-Za-z0-9_-]                     → 400 idempotency_key_required
 3. competition exists and is visible to the viewer                              → 404 not_found
 4. participant row for (competition, viewer org) exists                          → 403 not_a_participant
 5. REPLAY: offer (participant_id, idempotency_key) exists?
      same amount → 200 with the original offer + the current snapshot (header Idempotent-Replayed: true)
      different amount → 422 idempotency_key_reused
    cached rejection Cache["offer-rej:{participant_id}:{key}"] (24 h) exists?
      same amount → re-throw the same error (status, code, details); different → 422 idempotency_key_reused
 6. RATE LIMIT: Cache::add("offer-rate:{participant_id}", 1, setting bidding.offer_min_interval_seconds=2)
      false → 429 too_many_requests, Retry-After = interval
 7. SYNTAX: amount_minor integer > 0                                                → 422 offer_amount_invalid
            amount_minor ≤ setting bidding.max_amount_minor                         → 422 offer_amount_too_large
            amount_minor % amount_granularity_minor == 0                            → 422 offer_granularity  (details.granularity_minor)

TRANSACTION (DB::transaction, 1 attempt)
 8. $c = Competition::whereKey(id)->lockForUpdate()->first()        -- SELECT … FROM competitions WHERE id=? FOR UPDATE
 9. $now = DbClock::now()                                            -- clock_timestamp() AFTER the lock
10. STATE:
      status = live:
        now < bidding_opens_at                        → 409 offer_not_accepting (details.status, details.opens_at)
        now ≥ effective_close_at                      → 409 offer_closed
        stage = §7.3 from phaseAt(now)
      status = bafo_round:
        round = bafo_rounds(c); standing.bafo_shortlisted = false → 403 offer_not_shortlisted
        now ≥ round.cutoff_at                         → 409 offer_closed
        standing.bafo_offer_id not null               → 409 offer_bafo_already_submitted
        stage = bafo
      otherwise                                       → 409 offer_not_accepting (details.status)
11. $ls = CompetitionLiveState::firstOrCreate(c); $st = ParticipantStanding::firstOrCreate(participant)
12. d = direction sign
13. START PRICE (every stage): if start_price_minor set and d × (amount − start) < 0
                                                      → 422 offer_start_price (details.start_price_minor)
14. STAGE BOUND:
      stage ∈ {live, initial}:
        ref = (stage = live AND must_beat = best AND $ls.leader_amount_minor not null)
                ? $ls.leader_amount_minor : $st.current_amount_minor        -- may be null
        if ref not null:
          required = ref + d × step(ref)                                    -- §7.5
          if d × (amount − required) < 0 → 422 offer_step_not_met
              details.required_amount_minor = required   (always disclosable: ref is own, or public leader since R3)
      stage = sealed: no bound
      stage = bafo:   ref = $st.bafo_reference_amount_minor
                      if d × (amount − ref) < 0 → 422 offer_bafo_worse_than_reference (details.reference_amount_minor)
15. OUTLIER: oref = $st.current_amount_minor ?? start_price_minor ?? null   (never a hidden value)
      if oref and |amount − oref| × 10000 / oref > setting bidding.outlier_guard_bps (2000) and not confirm_outlier
                                                      → 422 offer_outlier_confirm_required (details.change_bps)
16. INSERT offers: seq = $ls.last_seq + 1, stage, amount, rank_key = −d × amount, accepted_at = $now,
                   idempotency_key, channel, ip, user_agent, outlier_confirmed, prev_hash = $ls.ledger_head_hash, hash
17. UPDATE standing: current_* = the new offer; first_amount_minor ??= amount; offers_count++; last_offer_at = $now;
                     stage = bafo → bafo_offer_id = offer.id
18. RANK: Ranking::recompute(c) (§7.6) → (leaderChanged, previousLeaderParticipantId, changedParticipantIds)
19. ANTI-SNIPING (stage = live only) (§7.7) → maybe CompetitionTimingService::extend(kind auto, triggeredByOfferId)
20. $ls: version++, last_seq = seq, ledger_head_hash = hash, leader_*, reserve_met, accepted_offer_count,
         participants_with_offers
21. event(new OfferAccepted($offer, $c, new OfferAcceptedContext(...)))   -- sync webhook-outbox listener runs here
COMMIT

ON ANY ApiException IN STEPS 10–15: roll back; INSERT offer_rejections (outside the TX) with code, db_time;
     Cache::put("offer-rej:{participant_id}:{key}", {status, code, details, amount_minor}, 24 h); re-throw.

RESPONSE 201: { data: { offer: {id, seq, amount_minor, stage, accepted_at}, live: ParticipantLiveSnapshot } }
   The snapshot is built AFTER commit (it may already include later offers; its "v" says so).
```

- **The close race.** The close job takes the same lock (§7.8). A bid is valid **only** if `accepted_at < effective_close_at`, as read under the lock. Job lateness can therefore never accept a late bid.
- **Lock order.** It is always the `competitions` row first, then `competition_live_states` and `participant_standings`. Every other writer (close, BAFO, award, void, extend, cancel) locks the competition row first as well.
- **Performance target.** The lock is held for < 20 ms p99. p95 acceptance is ≤ 250 ms end-to-end.

### 7.5 Step

```
step(ref) = min_step_minor                                            if set
          = max(granularity, ceilTo(ceil(ref × min_step_bps / 10000), granularity))   if min_step_bps set
          = granularity                                               otherwise
```

The step is always ≥ `amount_granularity_minor`, so an offer must improve by at least one unit. Ties with **another** participant's amount are allowed (`must_beat = own`) and are ranked by time.

### 7.6 Ranking (`Ranking::recompute`)

A full recompute is cheap (N ≤ 200 participants).

```sql
WITH ranked AS (
  SELECT ps.participant_id,
         ROW_NUMBER() OVER (ORDER BY ps.current_rank_key ASC, ps.current_at ASC, ps.current_seq ASC) AS r
  FROM participant_standings ps
  WHERE ps.competition_id = :cid AND ps.current_offer_id IS NOT NULL
)
UPDATE participant_standings ps
SET rank = ranked.r, is_leader = (ranked.r = 1), updated_at = :now
FROM ranked WHERE ps.participant_id = ranked.participant_id;
-- standings without a current offer: rank = NULL, is_leader = false
```

- **Tie-break.** The earliest `current_at` wins (the time the participant *reached* that amount), then the lower `current_seq`.
- **Changed set.** Read `(participant_id, rank, is_leader)` before and after. The changed set is the bidder plus every participant whose rank or `is_leader` changed.
- **Live state.** `leader_*` is taken from rank 1. `reserve_met = d × (leader_amount − reserve) ≥ 0` when a reserve is set.
- **During a BAFO round**, the current offer of a shortlisted participant that submitted is its BAFO offer. Everyone else keeps their last offer. The same query applies.

### 7.7 Anti-sniping

```
if stage = live and auto_extend_enabled and leaderChanged
   and $now ≥ effective_close_at − auto_extend_window_seconds
   and extension_count < auto_extend_max:
      new_close = min(max(effective_close_at, $now + auto_extend_by_seconds), hard_stop_at)
      if new_close > effective_close_at:
          CompetitionTimingService::extend($c, new_close, ExtensionKind::Auto, Actor::system(), $offer->id)
          -- sets effective_close_at, extension_count++, inserts competition_extensions,
          -- dispatches CompetitionExtended; after commit, (re)dispatches CloseCompetition with delay = new_close
```

- **The trigger is a change of leader**, not any offer. This means non-competitive offers never prolong a live event.
- **The soft close does not stack.** It is `max(close, t + by)`.

### 7.8 Close

`CloseCompetition` job (queue `live`, `ShouldBeUnique` per competition id) and the tick safety net:

- **The job.** It is dispatched at publish with `delay(effective_close_at)`, and again after each extension.
- **The tick.** `competitions:tick` runs every 10 s. It dispatches the job for every `live` competition whose `effective_close_at ≤ now()`.

```
TX: $c = lockForUpdate; $now = DbClock::now()
    if $c.status != live → return (no-op)
    if $now < $c.effective_close_at → release; re-dispatch with delay; return
    BiddingEngine::finalizeLiveBidding($c, $now)      -- Ranking::recompute; live state version++
    StateMachine::transition($c, closed, system, [closed_at = effective_close_at,
                                                  offers_opened_at = format=sealed ? $now : null])
    invitations of $c with status sent|viewed → expired (expired_at = $now)
    events: CompetitionClosed($c); if sealed: OffersUnsealed($c) (Bidding)
```

### 7.9 VisibilityProjector (the single gate for everything that leaves the server)

`App\Modules\Bidding\Services\VisibilityProjector` serves REST resources, realtime payloads, webhooks, public API responses and the PDF. **Push notifications and e-mails never contain amounts.**

**Participant projection** (the viewer's organization is a joined participant). Here "live rules" means stage `live`, that is phase `open` or `final_window`, **or** the competition is closed or later and the format is `live`.

| Field | Shown when | Otherwise |
|---|---|---|
| `my_offer` `{amount_minor, seq, stage, accepted_at}`, `my_offers_count` | always | – |
| `start_price_minor` | always (when set) | – |
| `is_leading` | live rules and `rank_visibility ∈ {leading_flag, full}` | null |
| `rank`, `ranked_count` | live rules and `rank_visibility = full` | null |
| `leading_amount_minor` | live rules and `show_prices` | null |
| `ladder` `[{alias_no, amount_minor, is_me}]`, sorted by rank | live rules and `rank_visibility = full` and `show_prices` | null |
| `required_next_amount_minor` | stage `live` or `initial` and a reference exists (own current; or leader when `must_beat = best`) | null. It is a **bound**: tender → the offer must be ≤ it; auction → ≥ it. |
| `bafo` `{shortlisted, cutoff_at, submitted, reference_amount_minor}` | status `bafo_round`, or a BAFO round exists | null. `reference_amount_minor` is shown only to that participant. |
| `result` `{outcome, winning_amount_minor}` | status `awarded` or `not_awarded` | null. See the table below. |
| reserve price, other participants' identities or aliases (outside the ladder), issuer notes, online counts, the offer log of others | **never** | – |

**`result` values:**

| `outcome` | When |
|---|---|
| `won` | This participant holds the issued award |
| `not_selected` | The award went to someone else and `result_publication` ≠ none |
| `not_awarded` | Status `not_awarded` and `result_publication` ≠ none |
| `null` | `result_publication = none` (the viewer sees only the status) |

`winning_amount_minor` is set only when `result_publication = outcome_and_amount`.

- **During the `initial` phase**, a participant sees only `my_offer`, `start_price_minor` and `required_next_amount_minor` (own-based).
- **Sealed format.** Before close, the same own-only view applies. After close, the view is still own-only, because sealed competitions have `rank_visibility = none` and `show_prices = false` (rule R1).

**Issuer projection** (a member of the issuer organization, or an issuer API client).

- It shows every participant: organization id, name, logo, contact e-mail and phone, `alias_no`, current amount, first amount, rank, `is_leader`, offers count, `last_offer_at`, BAFO flags, and reserve status and metrics.
- **Sealed, before `offers_opened_at`:** amounts, ranks, `is_leader`, the leader and `reserve_met` are `null`. `submitted: true|false` and `last_offer_at` are shown.
- **Offer logs** (`GET …/offers/log`, webhooks) follow the same rule. While sealed, the amount is `null`.

**Invitee (not joined) projection:** the teaser only (API.md §2.6). There is no live block.

**Public API** (issuer API clients) uses the issuer projection. Participant-side public API views are not in the MVP.

### 7.10 Who receives a live update

After any change, the Bidding listener bumps `competition_live_states.version`. That happens in the same TX for offers, voids, BAFO and award. For lifecycle events coming from Competitions, it uses an atomic `UPDATE … SET version = version + 1 RETURNING version`. The listener then dispatches (after commit) `IssuerLiveUpdated` to `competition.{id}`, and `ParticipantLiveUpdated` to the recipients below.

| Change kind (`last_change.kind`) | Participant recipients |
|---|---|
| `offer` | The bidder. Plus, when `rank_visibility = leading_flag`: the previous and new leader. When `full`: every participant in the changed set. When `show_prices` and the leading amount changed: **all** participants. |
| `extension`, `status`, `bafo`, `award`, `void` | All joined participants |

Every payload is a **full snapshot for that audience**, carrying `v`. Clients apply it only if `v > lastAppliedV` (§9.3).

### 7.11 BAFO round

**Start** (`POST …/bafo-round`, `StartBafoRound`). The body is `participant_ids[]` (1–50 participant public ids) and `duration_minutes` (default `bafo_duration_minutes`).

- **Guards:**
  - status `closed`;
  - `bafo_round_enabled`;
  - no existing round;
  - every id is a joined participant with a current, non-voided offer;
  - permission `competitions.award`.
- **TX, holding the competition lock:**
  - create the round with `starts_at = now` and `cutoff_at = now + duration`;
  - for the shortlisted: `bafo_shortlisted = true` and `bafo_reference_amount_minor = current_amount_minor`;
  - transition to `bafo_round`;
  - bump the version;
  - dispatch `BafoRoundStarted`.
- **Scheduling.** After commit, `EndBafoRound` is dispatched with delay `cutoff_at`. The `bidding:tick` safety net runs every 10 s.

**End.** Under the lock, with DB time ≥ `cutoff_at`:

- round `ended`, `ended_at`;
- `Ranking::recompute`;
- transition to `closed`;
- bump the version;
- `BafoRoundEnded`.

Participants that were not shortlisted keep their standing. They can still be awarded, with a justification.

### 7.12 Award, revoke, close without award

**Award** (`POST …/award`, `IssueAward`). The input is:

- `participant_id`;
- `justification_reason_id?` and `justification_text?`;
- `confirm_reserve_not_met?`;
- `message_to_winner?`;
- `internal_notes?`.

| Guard | Error |
|---|---|
| Status is `closed` | 409 `invalid_state_transition` |
| Permission `competitions.award` | 403 `forbidden` |
| The participant has a current, non-voided offer | 422 `award_participant_has_no_offer` |
| If the participant is not rank 1: a `justification_reason_id` of kind `award_justification` (and `justification_text` when the reason `requires_note`) | 422 `award_justification_required` |
| If a reserve is set and not met for this amount (`d × (amount − reserve) < 0`): `confirm_reserve_not_met = true` **and** a justification | 422 `award_reserve_confirmation_required` |

TX, holding the competition lock:

1. Insert the award (`status = issued`). `amount_minor` is the participant's current amount; `offer_id` is its current offer; `rank_at_award` and `is_leading_offer` come from the standing.
2. `erp_sync_status = organization.api_enabled ? pending : not_required`.
3. `ledger_head_hash` is copied from the live state.
4. Transition to `awarded`, bump the version, and dispatch `AwardIssued`.

After commit, the report is regenerated (§7.14).

**Revoke** (`POST …/award/revoke`, `RevokeAward`):

- input: `reason` (text, 5–1000 chars);
- guard: status `awarded`;
- effect: award `revoked` with the revoke columns; transition to `closed`; `AwardRevoked`.

**Close without award** (`POST …/close`, Competitions `CloseWithoutAward`): status `closed` → `not_awarded`, with a reason of kind `not_awarded` and an optional note.

### 7.13 Voiding an offer (platform admin only)

`VoidOffer(Offer, reasonId, note, Admin)`:

- **Allowed** while the competition is not `awarded`, `not_awarded` or `cancelled`. To void the awarded offer, revoke the award first.
- **TX, holding the lock:**
  1. Insert `offer_voids`.
  2. Rebuild that participant's standing from the ledger, excluding voided offers: current = the latest non-voided offer; first = the earliest; `offers_count`.
  3. Run `Ranking::recompute`, update the live state and bump the version.
  4. Dispatch `OfferVoided`.

  The ledger rows are never changed.

### 7.14 Result report (PDF)

- **Generation.** `GenerateCompetitionReport(competitionId, locale)` runs on queue `pdf`. It renders `bidding::pdf.report`, the issuer variant only. It stores the file with purpose `competition_report` and upserts `competition_reports` (`status = ready`, `live_version`).
- **When it runs:**
  - at close, in the issuer creator's locale;
  - after an award or revoke;
  - on demand through `GET …/report?locale=` when the stored `live_version` < the current version.
- **Contents:**
  1. Cover (reference, title, issuer, type chip, format, category, generated at).
  2. Rules summary.
  3. Timeline (publish, open, final window, every extension with its trigger, close, BAFO, award).
  4. Participants (alias ↔ name, join time).
  5. Final ranking (rank, name, final amount, time reached, change ratio first → last, BAFO offer).
  6. Full offer log (seq, time in ms Asia/Riyadh, participant, amount, stage; voids marked).
  7. Metrics (§7.15).
  8. Award (awardee, amount, leading or not, justification, notes).
  9. Integrity (ledger head hash, generated-at, page x/y).

### 7.15 Metrics (issuer)

| Metric | Formula |
|---|---|
| `improvement_vs_start_bps` | `d × (leader_amount − start) × 10000 / start` when a start price and a leader exist. Positive is good: savings for a tender, uplift for an auction. |
| `change_ratio_bps` (per participant) | `d × (current − first) × 10000 / first`. The legacy "first → last offer" ratio. |
| Activity | `offers_count`, `participants_joined`, `participants_with_offers`, `invitations_count`, `extension_count` |

### 7.16 Rules summary (Competitions `RulesSummary`)

`RulesSummary::lines(Competition $c, string $locale): list<string>` returns localised sentences, generated server-side from the columns so that every surface describes a competition identically. Keys live in `lang/*/competitions.php` under `rules_summary.*`:

- `type` — "Tender: the lowest offer wins" or "Auction: …"
- `format_live` / `format_sealed`
- `start_price` — "Ceiling price" (tender) or "Opening price" (auction), formatted
- `must_beat_own` / `must_beat_best`
- `min_step_amount` / `min_step_percent`
- `visibility_none` / `visibility_leading_flag` / `visibility_full` (with or without prices)
- `final_window` — "The final pricing window starts N minutes before closing; before it only your own offer is visible"
- `auto_extend` — "Offers that change the leading offer in the last N minutes extend closing by M minutes, up to K times (latest possible close …)"
- `bafo` — "The issuer may invite a shortlist to submit one best and final offer after closing"
- `server_time` — "Offers are timed on receipt by the BAFO server"
- `prices_excl_vat`

The reserve price is **never** mentioned to participants. The issuer variant adds `reserve_hidden`.

---

### 7.17 Manual extension (Competitions `ExtendCompetition`)

The input is `new_close_at` and `reason` (5–1000 chars).

**Guards:**

- status `live`;
- `competitions.manage` (issuer), `competitions:manage` (API) or an admin;
- `new_close_at ≥ effective_close_at + setting competitions.min_extend_minutes (5)`;
- `new_close_at − bidding_opens_at ≤ competitions.max_duration_days`.

Violations return 422 `extend_invalid` (field `new_close_at`).

**Effect** (holding the lock; `delta = new_close_at − effective_close_at`):

- `CompetitionTimingService::extend(kind manual)`.
- `hard_stop_at += delta` when it is set.
- If the phase is `initial`: `final_window_starts_at += delta`.
- If now < `invitation_cutoff_at`: `invitation_cutoff_at += delta`.
- The close job is re-dispatched.
- `CompetitionExtended` is dispatched. A manual extension is always announced to every participant, with its reason.

**Admin extension:** the same, with `kind = admin` and an `actor_admin_id`.

---

## 8. Access policy and permissions

### 8.1 Organization permissions (Identity)

`App\Modules\Identity\Enums\Permission` (string values). `OrgRole::permissions(Membership $m): list<Permission>` is the only source.

| Permission | owner | admin | member |
|---|:-:|:-:|:-:|
| `organization.update` | ✓ | ✓ | – |
| `team.manage` | ✓ | ✓ | – |
| `billing.view` | ✓ | ✓ | – |
| `billing.purchase` | ✓ | if `can_purchase` | if `can_purchase` |
| `competitions.create` | ✓ | ✓ | ✓ |
| `competitions.manage_all` | ✓ | ✓ | – |
| `competitions.award` | ✓ | if `can_award` | if `can_award` |
| `participation.submit_offers` | ✓ | ✓ | ✓ |
| `integrations.manage` | ✓ | ✓ | – |
| `account.delete_organization` | ✓ | – | – |

- **Defaults when creating a team member:** admin → `can_award = true`, `can_purchase = true`; member → both false.
- **Granting a flag** (SECURITY_REVIEW S-02): an editor with `team.manage` grants `can_award` / `can_purchase` only if it holds `competitions.award` / `billing.purchase` itself (422 on the flag otherwise); the admin defaults stop at the inviter's own flags. Revoking, or resending an unchanged value, is always allowed.
- **Owner.** There is always exactly one owner, and the owner cannot be edited, deactivated or removed by others.
- **`competitions.manage` on a competition** is derived: `competitions.manage_all`, **or** (`competitions.create` **and** `competition.created_by_user_id == user.id`).
- **Check helpers:**
  - `User::hasPermission(Permission $p): bool`;
  - `Gate::define('perm', fn (User $u, string $p) => $u->hasPermission(Permission::from($p)))`;
  - policies use `$user->hasPermission(...)`.

**Account gate** (Identity middleware `EnsureAccountActive`, appended to the `app_v1` group with `Router::pushMiddlewareToGroup`):

| Condition | Response |
|---|---|
| `user.status` must be `active` | 403 `account_inactive` |
| `email_verified_at` must be set | 403 `email_not_verified` |
| The membership must be `active` | 403 `account_inactive` |
| `organization.status` must be `active` | 403 `organization_suspended` |

The exempt route names are `app.v1.auth.logout`, `app.v1.me.show` and `app.v1.account.deletion.*`. It passes through when there is no authenticated user (guest routes).

### 8.2 Who sees a competition (`ViewerResolver`)

| Viewer | Condition | Sees |
|---|---|---|
| **issuer** | The viewer's organization is `competitions.organization_id` (any active member). API clients of that organization. | Everything, through the issuer projection (§7.9). Drafts are visible to the issuer only. |
| **participant** | A `participants` row exists for (competition, viewer org) | The participant projection |
| **invitee** | An invitation with `organization_id = viewer org` and status ∈ {sent, viewed, declined, expired} | The teaser, the invitation, access, and `invitation_document` attachments |
| anyone else | – | **404** (existence is never revealed) |

### 8.3 Action matrix (app v1)

| Action | Who (permission) | State |
|---|---|---|
| create draft | issuer, `competitions.create` + `AccessPolicy::canIssue` | – |
| edit, delete draft, publish, invite, revoke invitation, attachments add or remove, sponsorship config and checkout | issuer, `competitions.manage` (and `billing.purchase` for checkout) | per §6 and API.md |
| extend, cancel | issuer, `competitions.manage` | live (extend); scheduled, live or bafo_round (cancel) |
| start BAFO, award, revoke, close without award | issuer, `competitions.award` | closed or awarded |
| view offers, offer log, report | issuer, any member | – |
| join, decline | invitee organization, any active member | §6.2 |
| submit offer, heartbeat | participant, `participation.submit_offers` | §7.4 |
| post a question or comment | issuer (any member) or participant | competition `scheduled` or `live` |
| read the Q&A | issuer or participant | any state after publish |

### 8.4 Billing `AccessPolicy` (it replaces `has_valid_subscription`)

```
canIssue(org)       = org.status = active AND ∃ subscription(org, status = active, starts_at ≤ now < ends_at)   -- paid | trial | grant
seatLimit(org)      = currentSubscription(org)?.seats ?? 1
participationAccess(org, inv) → ParticipationAccess{state, coverage, sponsor_name, join_deadline = comp.invitation_cutoff_at}
  comp = inv.competition
  if participant(comp, org) exists:
       state = comp.status ∈ {scheduled, live, bafo_round} ? full : read_only
       coverage = participant.entitlement_source = sponsored_pass ? sponsored : own_plan
  elif inv.status ∈ {declined, expired, revoked} or comp.status ∉ {scheduled, live} or now ≥ comp.invitation_cutoff_at:
       state = unavailable, coverage = none
  elif reservedPass(inv):  state = join_required, coverage = sponsored
  elif canIssue(org):      state = join_required, coverage = own_plan
  else:                    state = plan_required, coverage = none
  sponsor_name = coverage = sponsored ? comp.organization.name : null
resolveJoin(org, inv)   (inside the join TX)
  pass = sponsored_passes(inv) WHERE status = reserved FOR UPDATE
  if canIssue(org): if pass → pass.released(covered_by_own_plan); return plan (or grant when the current subscription.source = grant)
  if pass: pass.joined(organization_id = org); return sponsored_pass
  throw ApiException('plan_required', 'billing.errors.plan_required', 403)
coverageFor(invitations)   (for the issuer's invitation list and quotes)
  pass status ∈ {pending, reserved, joined}                       → sponsored
  inv.organization has a current subscription with ends_at > comp.invitation_cutoff_at (or the draft's scheduled close) → own_plan
  otherwise                                                       → none
```

- **Participation lock-in (R4 §2.5).** Once joined, the `participants` row is the entitlement. Access **never** depends on the plan again for that competition.
- **Issuer continuity.** An issuer whose plan lapses keeps running its published competitions. It cannot create or publish new ones.
- **Apps render only from these values.** They never compute entitlement. Every competition item carries `access {state, coverage, sponsor_name, join_deadline}`.

### 8.5 File access (`FileAccessRegistry` rules)

| Purpose | Allowed users |
|---|---|
| `organization_profile` | Members of the owning organization; members of an issuer organization with a competition in which the owner organization is a participant |
| `competition_attachment` | The issuer; joined participants (`document`, `external_link`); invitees with access state ≠ unavailable (`invitation_document` only) |
| `invoice_pdf` | Owning organization members with `billing.view` |
| `competition_report` | Issuer organization members |
| `import_source`, `import_errors`, `export` | Owning organization members with `integrations.manage` |

### 8.6 Public API authorization

- The organization comes from the credential (API client).
- Every lookup is scoped: `where organization_id = client org`. For competitions, the client's organization must be the issuer; anything else is 404.
- **Scope checks** use the middleware `api.scope:<scope>` (§14.4).
- **Policies are not used for API clients.** The same Actions enforce the state rules.

### 8.7 Platform admin

- Admins use the Filament panel only (guard `admin`, provider `admins`, session driver Redis).
- **MFA** (Filament app authentication) is required when `bafo.admin.mfa_required` is true (env `ADMIN_MFA_REQUIRED`, default `false` locally and `true` in production).
- **Roles:**
  - `super_admin` can do everything;
  - `operator` cannot manage admins, plans, prices, coupons or settings.

---

## 9. Realtime (Laravel Reverb, Pusher protocol, private channels only)

### 9.1 Connection

| Item | Value |
|---|---|
| Server | `php artisan reverb:start` on :8085 |
| App id / key / secret | env `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` (local placeholders only) |
| Client config | from `GET /api/app/v1/app-config` → `realtime {key, host, port, scheme}` (env `REVERB_PUBLIC_HOST` / `REVERB_PORT` / `REVERB_SCHEME`). The Android dev flavour overrides host to `10.0.2.2`. |
| Channel auth | `POST /broadcasting/auth` with header `Authorization: Bearer <sanctum token>` and `Accept: application/json` (form fields `socket_id`, `channel_name`). There is **never** a token in the URL. |
| Web client | `laravel-echo` + `pusher-js`: `broadcaster: 'reverb'`, `authEndpoint`, `auth.headers` |
| Flutter client | a Pusher-protocol client with custom-host support (for example `dart_pusher_channels`) and an authorizer that posts to the same endpoint |

### 9.2 Channels and authorisation callbacks

| Channel (Echo `private()` name) | Registered by | Authorise when |
|---|---|---|
| `competition.{competitionPublicId}` | Bidding | the user's organization is the issuer of the competition |
| `competition.{competitionPublicId}.participant.{organizationPublicId}` | Bidding | `organizationPublicId` equals the user's organization **and** a `participants` row exists for (competition, organization) |
| `user.{userPublicId}` | Notifications | `userPublicId` equals the authenticated user's `public_id` |

There are no presence channels. Participant online status uses the heartbeat (§9.5).

The account gate (§8.1) does not run on `/broadcasting/auth`, so the Bidding callbacks apply it themselves: an active user with a verified e-mail, an active membership and an active organization (SECURITY_REVIEW S-06). Reverb client events are off by default (`REVERB_APP_ACCEPT_CLIENT_EVENTS_FROM=none`).

### 9.3 Events (`broadcastAs` names; Echo listens with a leading dot, for example `.live.updated`)

| Event | Channel(s) | Payload (`broadcastWith`) | When |
|---|---|---|---|
| `live.updated` | issuer channel | `IssuerLiveSnapshot` (API.md §2.8) | Every live-state change (§7.10) |
| `live.updated` | participant channel | `ParticipantLiveSnapshot` (API.md §2.8) | Per recipient set (§7.10) |
| `offer.accepted` | issuer channel | `OfferLogEntry` (issuer projection; amount null while sealed) | Each accepted offer. Clients append by `seq`; on a gap they call `GET …/offers/log?after_seq=`. |
| `competition.updated` | issuer channel + every participant channel | `{competition_id, fields: [..], server_time}` | Title, description, attachment or schedule changes, invitation-count changes. Clients refetch the competition. |
| `comment.created` | issuer channel + every participant channel | `Comment` resource, projected per audience (participants see aliases) | A new question or reply |
| `invitation.updated` | issuer channel | `Invitation` resource (issuer view) | Any invitation status change |
| `notification.created` | user channel | `{notification: Notification, unread_count}` | Every in-app notification |
| `notifications.unread_count` | user channel | `{unread_count}` | After read, read-all or delete |

**Versioning.**

- `competition_live_states.version` (`v`) increases on every audience-visible change.
- Every snapshot carries `v`. A client keeps `lastAppliedV` and **drops** any snapshot with `v ≤ lastAppliedV`.
- A participant who is not in a recipient set keeps an older `v`. The next snapshot it receives will simply have a higher `v`.

### 9.4 Resync

On connect, reconnect or app resume:

1. Subscribe first and buffer incoming events.
2. Call `GET /api/app/v1/competitions/{id}/live` (the projected snapshot with `v` and `server_time`).
3. Apply the snapshot, then apply the buffered events with `v > snapshot.v`.

**Degraded connection.**

- If the WebSocket is not connected within 10 s, poll `/live` every 3 s during the last 5 minutes and every 10 s otherwise.
- After 3 s without a connection, show "Reconnecting…" and disable the submit button.
- Submitting is always safe, because the server decides and requests are idempotent.

### 9.5 Heartbeat (issuer "online participants")

- **Participant clients.** While the live screen is visible, they call `POST /api/app/v1/competitions/{id}/live/heartbeat` every 20 s.
- **Storage.** The server stores `Cache::put("hb:{competitionId}:{participantId}", 1, 45 s)`.
- **Issuer view.** The issuer snapshot's `online_participants_count` counts the live keys.
- **Push suppression.** Notifications use the same keys to suppress "no longer leading" pushes for participants who are on the live screen.

### 9.6 Clock sync

- Every app v1 response carries `meta.server_time`, and every snapshot carries `server_time`.
- `GET /api/app/v1/time` (guest, uncached) is called at screen open, every 60 s and on resume.
- **Offset.** `offset = server_time − (t_send + t_recv) / 2`, taking the median of the last 3 samples.
- **Remaining time.** `effective_close_at − (Date.now() + offset)`, rendered every 250 ms. **The device clock is never trusted alone.**
- **Slow connection.** If the round trip exceeds 2 s, show a hint to allow extra time.
- **Final seconds.** In the last 10 s, show "Offers are timed on receipt by the BAFO server".

---

## 10. Domain events (catalogue)

Events are plain final classes with public readonly constructor properties, in `app/Modules/<Owner>/Events`. They are dispatched **inside** the Action's transaction (§4.10).

In the Listeners column:

- **sync** means a synchronous in-transaction listener;
- **q** means a queued listener that runs after commit (`ShouldQueue` and `ShouldHandleEventsAfterCommit`), on the queue named in brackets.

| Event (owner) | Properties | Listeners |
|---|---|---|
| `Identity\UserRegistered` | `User $user, Organization $organization` | – (OTP mail is sent by the Action) |
| `Identity\EmailVerified` | `User $user` | Competitions q[default] `AttachPendingInvitations`: invitations with `email = user.email`, `organization_id` null and status sent or viewed get the user's organization. Integrations q[default] `LinkVendorsToOrganization`: vendors with the same e-mail or CR get `linked_organization_id`. |
| `Identity\MemberAdded`, `MemberUpdated`, `MemberRemoved` | `Membership $membership, Actor $actor` (+ `array $changes` on update) | – |
| `Identity\OrganizationUpdated` | `Organization $organization, array $changedFields, Actor $actor` | – |
| `Identity\AccountDeleted` | `int $userId, ?int $organizationId, DeletionScope $scope` | Notifications q `RemoveDeviceTokens` (the user's device tokens). Integrations q `RevokeOrganizationApiAccess` (organization scope only: revoke its API clients and keys, disable its webhook endpoints). |
| `Competitions\CompetitionCreated` | `Competition $competition, Actor $actor` | – |
| `Competitions\CompetitionUpdated` | `Competition $competition, list<string> $fields, Actor $actor` | Competitions q[live] broadcasts `competition.updated` (published competitions only). Notifications q `competition.updated` (fields ⊂ {title, description, schedule, attachments}). |
| `Competitions\CompetitionStatusChanged` | `Competition $competition, CompetitionStatus $from, CompetitionStatus $to, Actor $actor` | Bidding q[live] `BumpVersionAndBroadcast` (kind `status`) |
| `Competitions\CompetitionPublished` | `Competition $competition, Actor $actor` | Integrations sync webhook `competition.published` |
| `Competitions\CompetitionOpened` | `Competition $competition` | Notifications q `competition.opened` |
| `Competitions\CompetitionFinalWindowStarted` | `Competition $competition` | Bidding q[live] broadcast (kind `status`). Notifications q `competition.final_window_started`. |
| `Competitions\CompetitionClosingSoon` | `Competition $competition, int $minutes` | Notifications q `competition.closing_soon` |
| `Competitions\CompetitionExtended` | `Competition $competition, CompetitionExtension $extension, Actor $actor` | Integrations sync webhook `competition.extended`. Bidding q[live] broadcast (kind `extension`). Notifications q `competition.extended`. |
| `Competitions\CompetitionClosed` | `Competition $competition` | Integrations sync webhook `competition.closed`. Billing q[billing] `SettleSponsorship`. Bidding q[pdf] `GenerateCompetitionReport`. Notifications q `competition.closed`. |
| `Competitions\CompetitionCancelled` | `Competition $competition, Actor $actor` | Integrations sync webhook `competition.cancelled`. Billing q `SettleSponsorship`. Notifications q `competition.cancelled`. |
| `Competitions\CompetitionClosedWithoutAward` | `Competition $competition, Actor $actor` | Integrations sync webhook `competition.not_awarded`. Notifications q `competition.not_awarded`. Bidding q[pdf] report. |
| `Competitions\InvitationSent` | `Invitation $invitation, Actor $actor` | Notifications q `competition.invited` (in-app and push, known organizations only). Competitions q[live] broadcasts `invitation.updated`. |
| `Competitions\InvitationViewed` | `Invitation $invitation` | Competitions q[live] broadcasts `invitation.updated` |
| `Competitions\InvitationJoined` | `Invitation $invitation, Participant $participant, Actor $actor` | Integrations sync webhook `invitation.accepted`. Notifications q `invitation.joined`. Competitions q[live] broadcasts `invitation.updated`. |
| `Competitions\InvitationDeclined` | `Invitation $invitation` | Billing sync `ReleasePassForInvitation` (declined). Integrations sync webhook `invitation.declined`. Notifications q `invitation.declined`. Broadcasts `invitation.updated`. |
| `Competitions\InvitationRevoked` | `Invitation $invitation, Actor $actor` | Billing sync `ReleasePassForInvitation` (revoked or duplicate_organization). Broadcasts `invitation.updated`. |
| `Competitions\InvitationsExpired` | `Competition $competition, list<int> $invitationIds` | Broadcasts `invitation.updated` (one per id) |
| `Competitions\CommentPosted` | `Comment $comment, Actor $actor` | Competitions q[live] broadcasts `comment.created`. Notifications q `comment.created`. |
| `Competitions\AttachmentAdded` | `CompetitionAttachment $attachment, Actor $actor` | Published competitions only: Notifications q `competition.updated` (addendum). Broadcasts `competition.updated`. |
| `Bidding\OfferAccepted` | `Offer $offer, Competition $competition, OfferAcceptedContext $context` (`bool isFirstOfferOfParticipant`, `bool leaderChanged`, `?int previousLeaderParticipantId`, `list<int> changedParticipantIds`, `bool extended`, `int version`) | Integrations sync webhook `offer.submitted` (first) or `offer.updated`. Bidding q[live] broadcasts `live.updated` (§7.10) and `offer.accepted`. Notifications q `offer.received` (digest) and `standing.lost_lead`. |
| `Bidding\OffersUnsealed` | `Competition $competition` | Integrations sync webhook `competition.offers_opened` |
| `Bidding\BafoRoundStarted` | `BafoRound $round, Competition $competition, Actor $actor` | Bidding q[live] broadcast (kind `bafo`). Notifications q `bafo.invited`. |
| `Bidding\BafoRoundEnded` | `BafoRound $round, Competition $competition` | Bidding q[live] broadcast (kind `bafo`). Notifications q `bafo.ended`. |
| `Bidding\AwardIssued` | `Award $award, Competition $competition, Actor $actor` | Integrations sync webhook `award.issued`. Bidding q[live] broadcast (kind `award`) and q[pdf] report. Notifications q `award.won` and `award.not_selected`. |
| `Bidding\AwardRevoked` | `Award $award, Competition $competition, Actor $actor` | Integrations sync webhook `award.cancelled`. Bidding q[live] broadcast (kind `award`) and q[pdf] report. Notifications q `award.revoked`. |
| `Bidding\AwardErpSynced` | `Award $award` | – |
| `Bidding\OfferVoided` | `OfferVoid $void, Offer $offer, Competition $competition` | Bidding q[live] broadcast (kind `void`). Notifications q `offer.voided`. |
| `Billing\PaymentSucceeded` | `Payment $payment` | Billing q[billing] `IssueInvoiceForPayment` |
| `Billing\PaymentFailed` | `Payment $payment` | Notifications q `payment.failed` |
| `Billing\SubscriptionActivated` | `Subscription $subscription` | Notifications q `subscription.activated` |
| `Billing\SubscriptionExpiring` | `Subscription $subscription, int $daysLeft` | Notifications q `subscription.expiring` |
| `Billing\SubscriptionExpired` | `Subscription $subscription` | Notifications q `subscription.expired` |
| `Billing\InvoiceIssued` | `Invoice $invoice` | Notifications q `invoice.issued` |
| `Billing\SponsorshipSettled` | `CompetitionSponsorship $sponsorship` | Notifications q `sponsorship.unused_passes` (when `unused_count > 0`) |
| `Billing\SponsorshipPublishFailed` | `CompetitionSponsorship $sponsorship, Payment $payment, string $errorCode` | Notifications q `sponsorship.publish_failed` |
| `Billing\VoucherIssued` | `Coupon $voucher` | Notifications q `voucher.issued` |
| `Integrations\WebhookEndpointDisabled` | `WebhookEndpoint $endpoint` | Notifications q `webhook.endpoint_disabled` |
| `Integrations\ImportFinished`, `ExportFinished` | `ImportJob` / `ExportJob` | Notifications q `import.finished` / `export.finished` |

**Broadcast classes** (they implement `ShouldBroadcast` and `ShouldDispatchAfterCommit`, queue `live`):

| Module | Classes | Channels |
|---|---|---|
| Bidding | `IssuerLiveUpdated`, `ParticipantLiveUpdated`, `OfferAcceptedBroadcast` | the competition channels |
| Competitions | `CompetitionUpdatedBroadcast`, `CommentCreatedBroadcast`, `InvitationUpdatedBroadcast` | the competition channels, using Bidding's projector for comment aliases |
| Notifications | `NotificationCreatedBroadcast`, `UnreadCountBroadcast` | `user.{id}` |

---

## 11. Notifications

### 11.1 Mechanics

- **Base class.** `App\Modules\Notifications\Notifications\BafoNotification` (abstract; implements `ShouldQueue`; `$queue = 'notifications'`):
  - the constructor sets `$this->id = strtolower((string) Str::ulid())`;
  - `via()` reads the catalogue row;
  - `toDatabase()` returns the data shape below;
  - `toMail()` builds a markdown `MailMessage` with the BAFO theme;
  - `toPush()` returns a `PushMessage`.
- **Channels:** `database` (in-app), `mail`, `push` (the custom channel `PushChannel` → `PushNotifier`), and `broadcast`, which is **not** used per notification: `NotificationCreatedBroadcast` is dispatched from a `NotificationSent` listener for the database channel.
- **Recipients.** User-level recipient sets are resolved from organizations:
  - **issuer team** = active members of the issuer organization;
  - **participant users** = active members of the participant organization;
  - **billing users** = active members with `billing.view`;
  - **integration users** = active members with `integrations.manage`.
- **Locale.** Rendering uses the recipient's `users.locale` (`HasLocalePreference`). The API renders in-app titles and bodies in the **request** locale at read time.
- **Push and e-mail never contain offer amounts, ranks or other participants' identities.** Billing e-mails may show the organization's own payment amounts.
- **Deep links.** Push `data` = `{type, notification_id, subject_type, subject_id, route}` (all strings). `route` is a client route path without locale, for example `/competitions/01j…`, `/competitions/01j…/live`, `/billing/invoices/01j…`.

### 11.2 In-app data (`notifications.data`)

```json
{
  "type": "competition.invited",
  "params": {"competition_title": "توريد أجهزة", "issuer_name": "شركة أ", "direction": "tender", "sponsored": true},
  "subject": {"type": "competition", "id": "01j9…"},
  "route": "/competitions/01j9…"
}
```

**Templates.**

- Templates live in `lang/{ar,en}/notifications.php` (Notifications-owned), under the key `str_replace('.', '_', type)`, with the sub-keys `title`, `body`, and for mail `mail_subject`, `mail_intro`, `mail_action`.
- The placeholder `:competition_type` is filled from `competitions.direction.{tender|auction}` («مناقصة» / «مزايدة»). Both are feminine nouns, so a single Arabic template fits both modes.
- Other placeholders use the `params` keys.

### 11.3 Catalogue

| Type | Recipients | In-app | Push | Mail | Throttle / notes | Route |
|---|---|:-:|:-:|:-:|---|---|
| `competition.invited` | invitee organization users (known organizations) | ✓ | ✓ | – (Competitions sends the token mail) | `sponsored` param adds the "Fees covered" line | `/competitions/{id}` |
| `competition.updated` | participant users | ✓ | ✓ | – | at most 1 per 10 min per competition per user | `/competitions/{id}` |
| `competition.opened` | participant users | ✓ | ✓ | – | – | `/competitions/{id}/live` |
| `competition.final_window_started` | participant users | ✓ | ✓ | – | – | `/competitions/{id}/live` |
| `competition.closing_soon` | participant users without a heartbeat | – | ✓ | – | thresholds from setting `bidding.closing_soon_minutes` [10, 2], once each | `/competitions/{id}/live` |
| `competition.extended` | participant users and issuer team | ✓ | ✓ | – | manual or admin: always. Auto: push only to users without a heartbeat, at most 1 per 2 min per competition per user | `/competitions/{id}/live` |
| `competition.closed` | issuer team and participant users | ✓ | ✓ | issuer only | – | `/competitions/{id}` |
| `competition.cancelled` | participant users and invitee organizations (sent/viewed) | ✓ | ✓ | ✓ | reason included | `/competitions/{id}` |
| `competition.not_awarded` | participant users (if `result_publication ≠ none`) | ✓ | ✓ | ✓ | – | `/competitions/{id}` |
| `offer.received` | issuer team | ✓ | ✓ | – | digest: at most 1 in-app and 1 push per 5 min per competition per user | `/competitions/{id}/live` |
| `standing.lost_lead` | users of the previous leader (only if `rank_visibility ≠ none` and the phase is not initial or sealed) | ✓ | ✓ if no heartbeat | – | at most 1 per 60 s per competition per user | `/competitions/{id}/live` |
| `bafo.invited` | shortlisted participant users | ✓ | ✓ | ✓ | – | `/competitions/{id}/live` |
| `bafo.ended` | issuer team | ✓ | ✓ | – | – | `/competitions/{id}` |
| `award.won` | winner users | ✓ | ✓ | ✓ | includes `message_to_winner` in the mail | `/competitions/{id}` |
| `award.not_selected` | other participants with ≥ 1 offer (if `result_publication ≠ none`) | ✓ | ✓ | ✓ | – | `/competitions/{id}` |
| `award.revoked` | the revoked winner's users | ✓ | ✓ | ✓ | reason included | `/competitions/{id}` |
| `offer.voided` | the offer's organization users and the issuer team | ✓ | ✓ | participant only | – | `/competitions/{id}` |
| `comment.created` | participant question → issuer team; issuer reply → the author organization's users; issuer top-level post → all participant users | ✓ | ✓ | – | at most 1 push per 5 min per competition per user | `/competitions/{id}/qa` |
| `invitation.joined` | issuer team | ✓ | ✓ | – | params: organization name, sponsored | `/competitions/{id}` |
| `invitation.declined` | issuer team | ✓ | – | – | – | `/competitions/{id}` |
| `subscription.activated` | billing users | ✓ | – | ✓ | – | `/billing` |
| `subscription.expiring` | billing users | ✓ | ✓ | ✓ | T−7, T−3 and T−1 days, once each | `/billing` |
| `subscription.expired` | billing users | ✓ | – | ✓ | – | `/billing` |
| `payment.failed` | the paying user | ✓ | – | ✓ | – | `/billing` |
| `invoice.issued` | billing users | ✓ | – | ✓ | the PDF is downloadable from the dashboard (no attachment) | `/billing/invoices/{id}` |
| `sponsorship.unused_passes` | issuer billing users | ✓ | – | ✓ | tells the issuer the admin will issue a voucher | `/competitions/{id}` |
| `sponsorship.publish_failed` | the paying user | ✓ | – | ✓ | payment received, publish refused (code) | `/competitions/{id}` |
| `voucher.issued` | billing users | ✓ | – | ✓ | code and value | `/billing` |
| `webhook.endpoint_disabled` | integration users | ✓ | – | ✓ | – | `/integrations` |
| `import.finished`, `export.finished` | the job creator | ✓ | – | – | – | `/integrations` |

### 11.4 Draft copy (keys and placeholders are binding; the R3 writer may refine wording)

- **Keys** are `notifications.<type_key>.title` and `notifications.<type_key>.body`.
- **Time placeholders** (`:close_time`, `:cutoff_time`, `:ends_at`) are formatted in the recipient's locale in Asia/Riyadh (CONVENTIONS §9.1).
- **Money placeholders** (`:amount`) use `formatMoney`.
- **Rows marked (tc)** are rendered with `trans_choice` on the named count. The Arabic strings use the Laravel plural forms shown.

| Type key | Title AR | Body AR | Title EN | Body EN |
|---|---|---|---|---|
| `competition_invited` | دعوة للمشاركة في :competition_type | تدعوك :issuer_name للمشاركة في «:competition_title». (+ `body_sponsored_suffix`: « رسوم المشاركة مغطّاة.») | Invitation to a :competition_type | :issuer_name invites you to take part in “:competition_title”. (+ " Participation fees are covered.") |
| `competition_updated` | تحديث على المنافسة | حدّث طارح المنافسة تفاصيل «:competition_title». | Competition updated | The issuer updated “:competition_title”. |
| `competition_opened` | بدأ استقبال العروض | بدأ استقبال العروض في «:competition_title». | Offers are open | Offers are now open for “:competition_title”. |
| `competition_final_window_started` | بدأت فترة التسعير النهائية | بدأت فترة التسعير النهائية في «:competition_title»، وتُغلق المنافسة عند :close_time. | Final pricing window started | The final pricing window of “:competition_title” has started. It closes at :close_time. |
| `competition_closing_soon` (tc `:minutes`) | المنافسة تُغلق قريباً | `{1} تُغلق «:competition_title» خلال دقيقة\|{2} تُغلق «:competition_title» خلال دقيقتين\|[3,10] تُغلق «:competition_title» خلال :minutes دقائق\|[11,*] تُغلق «:competition_title» خلال :minutes دقيقة` | Closing soon | `{1} “:competition_title” closes in 1 minute\|[2,*] “:competition_title” closes in :minutes minutes` |
| `competition_extended` | مُدّد وقت الإغلاق | مُدّد وقت إغلاق «:competition_title» إلى :close_time. | Closing time extended | The closing time of “:competition_title” was extended to :close_time. |
| `competition_closed` | أُغلقت المنافسة | أُغلق استقبال العروض في «:competition_title». | Competition closed | Offers are closed for “:competition_title”. |
| `competition_cancelled` | أُلغيت المنافسة | أُلغيت «:competition_title». السبب: :reason | Competition cancelled | “:competition_title” was cancelled. Reason: :reason |
| `competition_not_awarded` | أُغلقت المنافسة دون ترسية | أغلق طارح المنافسة «:competition_title» دون ترسية. | Closed without award | The issuer closed “:competition_title” without award. |
| `offer_received` | عروض جديدة | وصلت عروض جديدة في «:competition_title». | New offers | New offers arrived in “:competition_title”. |
| `standing_lost_lead` | لم يعد عرضك متصدراً | لم يعد عرضك العرض المتصدر في «:competition_title». | You are no longer leading | Your offer is no longer the leading offer in “:competition_title”. |
| `bafo_invited` | دعوة لجولة العرض النهائي | أنت مدعو لتقديم عرضك النهائي في «:competition_title» قبل :cutoff_time. | Best-and-final-offer round | You are invited to submit your best and final offer for “:competition_title” before :cutoff_time. |
| `bafo_ended` | انتهت جولة العرض النهائي | انتهت جولة العرض النهائي في «:competition_title»، ويمكنك الآن الترسية. | BAFO round ended | The best-and-final-offer round of “:competition_title” has ended. You can now award. |
| `award_won` | تمت الترسية عليكم | تمت ترسية «:competition_title» عليكم. | Competition awarded to you | “:competition_title” has been awarded to you. |
| `award_not_selected` | نتيجة المنافسة | تمت ترسية «:competition_title» على متنافس آخر. نشكركم على المشاركة. | Competition result | “:competition_title” was awarded to another participant. Thank you for taking part. |
| `award_revoked` | أُلغيت الترسية | ألغى طارح المنافسة ترسية «:competition_title». السبب: :reason | Award revoked | The issuer revoked the award of “:competition_title”. Reason: :reason |
| `offer_voided` | أُلغي عرض | ألغت إدارة المنصة أحد العروض في «:competition_title». | An offer was voided | The platform voided an offer in “:competition_title”. |
| `comment_created` | رسالة جديدة في الأسئلة | توجد رسالة جديدة في أسئلة «:competition_title». | New Q&A message | There is a new message in the Q&A of “:competition_title”. |
| `invitation_joined` | انضم متنافس | انضمت :organization_name إلى «:competition_title». | Participant joined | :organization_name joined “:competition_title”. |
| `invitation_declined` | اعتذار عن المشاركة | اعتذرت :organization_name عن المشاركة في «:competition_title». | Invitation declined | :organization_name declined to take part in “:competition_title”. |
| `subscription_activated` | تم تفعيل الاشتراك | تم تفعيل :plan_name حتى :ends_at. | Subscription active | Your :plan_name plan is active until :ends_at. |
| `subscription_expiring` (tc `:days_left`) | الاشتراك ينتهي قريباً | `{1} ينتهي اشتراكك غداً\|{2} ينتهي اشتراكك خلال يومين\|[3,10] ينتهي اشتراكك خلال :days_left أيام\|[11,*] ينتهي اشتراكك خلال :days_left يوماً` | Subscription ending soon | `{1} Your subscription ends tomorrow\|[2,*] Your subscription ends in :days_left days` |
| `subscription_expired` | انتهى الاشتراك | انتهى اشتراك :plan_name. | Subscription expired | Your :plan_name plan has expired. |
| `payment_failed` | تعذّر إتمام الدفع | لم تكتمل عملية الدفع، ويمكنك المحاولة مرة أخرى. | Payment failed | The payment was not completed. You can try again. |
| `invoice_issued` | فاتورة ضريبية جديدة | أُصدرت الفاتورة :number. | New tax invoice | Invoice :number has been issued. |
| `sponsorship_unused_passes` | تصاريح مشاركة غير مستخدمة | عدد التصاريح غير المستخدمة في «:competition_title»: :unused_count. ستصدر إدارة المنصة قسيمة بقيمتها. | Unused participation passes | Unused passes in “:competition_title”: :unused_count. The platform team will issue a voucher for their value. |
| `sponsorship_publish_failed` | تم الدفع ولم تُنشر المنافسة | استلمنا الدفع لكن تعذّر نشر «:competition_title». راجع التفاصيل ثم انشر مجدداً دون دفع إضافي. | Paid, not yet published | We received your payment but could not publish “:competition_title”. Review it and publish again. You will not be charged again. |
| `voucher_issued` | قسيمة جديدة | أُضيفت القسيمة :code بقيمة :amount إلى حسابك. | New voucher | Voucher :code worth :amount was added to your account. |
| `webhook_endpoint_disabled` | تعطّل عنوان الإشعارات البرمجية | عُطّل العنوان :url بعد تكرار فشل الإرسال. | Webhook endpoint disabled | The endpoint :url was disabled after repeated delivery failures. |
| `import_finished` | اكتمل الاستيراد | اكتمل استيراد الموردين: :created جديد، :updated محدَّث، :errors خطأ. | Import finished | Vendor import finished: :created created, :updated updated, :errors errors. |
| `export_finished` | ملف التصدير جاهز | ملف التصدير جاهز للتنزيل. | Export ready | Your export file is ready to download. |

**Placeholder values:**

- `:competition_type` is `competitions.direction.tender` («مناقصة» / "tender") or `competitions.direction.auction` («مزايدة» / "auction"). **In English it is lowercase inside sentences.**
- `:reason` is the close reason's localised name, plus the note when present.

### 11.5 Token-carrying mails

These mails are sent directly by the owning Action as Laravel `Mailable`s with the shared theme. They are **not** stored in-app.

| Mail | Owner | Content |
|---|---|---|
| OTP code | Identity | purposes: e-mail verification, password reset, invitation claim |
| Team invitation | Identity | link `{WEB_URL}/{locale}/auth/accept-invite#t={token}` |
| Competition invitation | Competitions | link `{WEB_URL}/{locale}/invitations#t={token}`; decline link `…#t={token}&action=decline`; the sponsored line when covered |
| Account deletion scheduled | Identity | – |

---

## 12. Queues and scheduled work

**Queues**, in priority order: `live`, `default`, `notifications`, `mail`, `billing`, `webhooks`, `pdf`, `imports`.

**Horizon supervisors** (`config/horizon.php`, Platform):

| Supervisor | Queues | Processes |
|---|---|---|
| `live` | live | 3 |
| `main` | default, notifications, mail, billing | 3 |
| `low` | webhooks, pdf, imports | 2 |

**Jobs:** every job is idempotent and has `$tries = 3` unless stated. Close, open and BAFO jobs are `ShouldBeUnique` by competition id.

| Command | Owner | Schedule | Work |
|---|---|---|---|
| `competitions:tick` | Competitions | `everyTenSeconds()` `withoutOverlapping()` | (1) scheduled → live where `bidding_opens_at ≤ now`; (2) live, final window due and `final_window_started_at` null → stamp + `CompetitionFinalWindowStarted`; (3) closing-soon thresholds → `CompetitionClosingSoon` (record the threshold in `notified_thresholds`); (4) dispatch `CloseCompetition` for live rows with `effective_close_at ≤ now`; (5) invitations `sent`/`viewed` with competition `invitation_cutoff_at ≤ now` → `expired` + `InvitationsExpired` |
| `bidding:tick` | Bidding | `everyTenSeconds()` | Dispatch `EndBafoRound` for running rounds with `cutoff_at ≤ now` |
| `billing:expire-subscriptions` | Billing | `everyFiveMinutes()` | active with `ends_at ≤ now` → expired + event |
| `billing:subscription-reminders` | Billing | `dailyAt('06:00')` UTC (09:00 Riyadh) | T−7/3/1 `SubscriptionExpiring` (recorded in `reminders_sent`) |
| `billing:reconcile-payments` | Billing | `everyFiveMinutes()` | pending payments older than 5 min → `PaymentGateway::fetch()` → handle; pending past `expires_at` and not paid → expired; pending passes past `hold_expires_at` → void |
| `billing:retry-einvoices` | Billing | `everyFiveMinutes()` | invoices with `einvoice_status ∈ {pending, failed}` and `attempts < 10` → issue |
| `integrations:dispatch-webhooks` | Integrations | `everyMinute()` | undispatched `webhook_events` older than 30 s → dispatch; deliveries `pending` with `next_attempt_at ≤ now` → deliver |
| `integrations:prune` | Integrations | `daily()` | `webhook_events` and deliveries older than 30 days; expired import and export files older than 7 days |
| `platform:prune` | Platform | `daily()` | expired `idempotency_keys` |
| `identity:prune` | Identity | `daily()` | `otp_codes` older than 24 h; expired membership invite tokens (the token is nulled, status stays `invited`) |
| `identity:execute-account-deletions` | Identity | `hourly()` | pending requests with `scheduled_for ≤ now` → execute (§13.8) |
| `notifications:prune-devices` | Notifications | `weekly()` | device tokens with `last_seen_at` older than 90 days |
| `horizon:snapshot` | Platform | `everyFiveMinutes()` | – |

---

## 13. Flows

### 13.1 Checkout (common to subscriptions and sponsorship)

**Preconditions**, checked in this order:

| # | Check | Error |
|---|---|---|
| 1 | Permission `billing.purchase` | 403 `forbidden` |
| 2 | `X-Platform` is `web` or absent. `ios` and `android` never purchase, which is the store policy (R4 §13). | 403 `purchase_not_available_on_platform` |
| 3 | Organization billing profile complete (§5.3) | 422 `billing_profile_incomplete`; `errors` lists the missing organization fields |
| 4 | `return_url` starts with one of `config('bafo.billing.allowed_return_urls')` (env `BILLING_ALLOWED_RETURN_URLS`, comma-separated, default `http://localhost:3000`) | 422 `return_url_not_allowed` |
| 5 | Optional `Idempotency-Key` → `payments.idempotency_key` (a replay returns the same payment) | – |

**Creation (TX):**

1. Insert the payment (`pending`) with its lines and the §13.2 amounts. Purpose-specific rows: a `pending_payment` subscription, or `pending` passes.
2. After commit, call `PaymentGateway::createCheckout($payment, $payment->return_url)`, then store `gateway_reference` and `redirect_url`.
3. The response is the Payment resource with `redirect_url`.
4. **Zero-total payments** are fulfilled immediately (§5.7). They have no redirect.

**Outcome.** The payment's result arrives in one of four ways, all of which call the same Action, `HandleGatewayResult(Payment, GatewayPaymentState)`:

- the gateway webhook `POST /api/app/v1/billing/gateway-webhooks/{gateway}`;
- `POST /api/app/v1/billing/payments/{id}/verify` (the replacement for the legacy `checkTransaction`);
- the fake page's approve or decline;
- the reconciler.

**`HandleGatewayResult`** runs under `SELECT … FOR UPDATE` on the payment:

- If the status is already terminal (except the late case `expired → succeeded`), it does nothing.
- If the gateway amount or currency differs from `total_minor`/`currency` → `failed` (`amount_mismatch`).
- **paid** → `succeeded` and `paid_at`, then **fulfil** (§13.2, §13.5) in the same TX, record the coupon redemption, and dispatch `PaymentSucceeded`.
- **failed** → `failed`, cancel the pending subscription or void the pending passes, and dispatch `PaymentFailed`.

**Return.** The gateway (or the fake page) redirects the browser to `return_url` with `payment={public_id}` appended as a query parameter. The payment id is not a secret. The web page polls `GET /billing/payments/{id}` every 2 s, for up to 60 s, until the status leaves `pending`.

### 13.2 Subscription pricing and lifecycle

```
unit      = interval = monthly ? plan.monthly_price_minor : plan.annual_price_minor
            (custom plan: unit = setting billing.custom_seat_{monthly|annual}_price_minor, quantity = seats,
             seats ∈ [billing.custom_min_seats (4), billing.custom_max_seats (50)])
subtotal  = unit × quantity (quantity = 1 for fixed plans)
credit    = upgrade only: floor(currentNet × remainingSeconds / totalSeconds), capped at subtotal
            where currentNet = current.subtotal − current.discount − current.credit
discount  = coupon applies_to ∈ {any, subscription}:
              percent: floor((subtotal − credit) × percent_bps / 10000)
              fixed:   min(amount_minor, subtotal − credit)
              voucher: min(balance_minor, subtotal − credit)
taxable   = subtotal − credit − discount
vat       = Money::vat(taxable, 1500)
total     = taxable + vat
```

**Deciding the kind of purchase** (`current` = the current subscription, §5.7):

| Situation | Result |
|---|---|
| No current subscription, or current is a trial or grant | **new**. It starts at activation; the current one becomes `superseded` at activation. |
| Current is paid; same plan, same seats | **renewal**. Allowed only when `current.ends_at − now ≤ billing.renewal_window_days` (30), otherwise 409 `subscription_renewal_too_early`. `starts_at = current.ends_at`. |
| Current is paid; the requested plan has a higher monthly equivalent (`unit / (interval = annual ? 12 : 1)`), or more seats | **upgrade**. Credit as above. It starts at activation; the current one becomes `superseded`. |
| Otherwise | 409 `subscription_downgrade_not_allowed` |

**Periods.** Monthly is `addMonthNoOverflow()` and annual is `addYearNoOverflow()`, both from `starts_at`.

**Fulfilment:** the subscription becomes `active` with `starts_at`/`ends_at` and its snapshot amounts, the superseded row is updated, and `SubscriptionActivated` is dispatched.

**Seats.** Seat usage is the count of memberships with status `invited` or `active`. Adding or reactivating a member when usage ≥ `AccessPolicy::seatLimit()` returns 409 `seat_limit_reached`. When a plan lapses, existing members keep working; only adding members is blocked.

### 13.3 Trial and grants

**Trial.** `POST /billing/trial`:

- **Allowed** when `organizations.trial_used_at` is null and the organization has never had a `paid` subscription. Otherwise → 409 `trial_not_available`.
- **Creates** a `source = trial` subscription on plan `setting billing.trial_plan_code` (`plus`) with that plan's seats. It is `active` now, for `billing.trial_days` (30).
- **Records** `trial_used_at = now`.

**Admin grant** (Filament). A `source = grant` subscription: plan, seats, `starts_at`, `ends_at` and a reason. It supersedes any current subscription.

### 13.4 Coupons and vouchers

**Validation** (`POST /billing/coupons/validate` and at checkout). The checks, in order:

| # | Check | Error |
|---|---|---|
| 1 | The code exists and `is_active` | 422 `coupon_invalid` |
| 2 | Within `valid_from`/`valid_until` | 422 `coupon_expired` |
| 3 | Org-scoped coupons belong to the caller's organization. Checked together with #1, before #2, so another organization's expired voucher never answers `coupon_expired` | 422 `coupon_invalid` (never reveal other organizations' codes) |
| 4 | `applies_to` matches the purpose | 422 `coupon_not_applicable` |
| 5 | `redemptions_count < max_redemptions`, per-organization limit, voucher `balance_minor > 0`, and no other `pending` payment of the same organization is using the voucher. The organization's own pending, unexpired payments that carry the coupon count as uses against `max_redemptions` and the per-organization limit too (SECURITY_REVIEW S-04) | 422 `coupon_exhausted` |

**Redemption.**

- The redemption is recorded when the payment succeeds, which increments `redemptions_count`.
- Vouchers also decrement `balance_minor` under `SELECT … FOR UPDATE`. If the balance became insufficient in the meantime, the discount already charged is honoured and the balance is floored at 0.
- **One coupon or voucher per payment.**

### 13.5 Sponsored participation (R4 MVP)

**Enabling.** Requires `organizations.sponsorship_enabled` **and** setting `sponsorship.enabled`. Otherwise → 403 `sponsorship_not_enabled`.

**Configuration.** `PUT /competitions/{id}/sponsorship` `{mode: none|all|selected, max_passes?}`:

- **Allowed** in `draft`, `scheduled` and `live` before `invitation_cutoff_at`.
- **The row** is created on the first non-`none` mode, with `unit_price_minor = setting sponsorship.pass_price_{direction}_minor` (a snapshot).
- **After the first funding:** the mode cannot become `none`, and `max_passes` cannot drop below the passes that are `reserved`, `joined` or `pending` → 409 `sponsorship_locked`. The unit price is frozen.
- **Per-invitee selection** is the invitation's `sponsored_requested` flag, set through the invitation endpoints.

**Quote** (`SponsorshipQuote`: `GET …/sponsorship/quote` and inside publish, invite and checkout):

```
candidates = invitations to consider (publish: status draft; invite: the new rows as unsaved candidates)
covered    = mode all ? candidates : candidates where sponsored_requested (mode none ⇒ none covered)
for c in covered: coverage(c) via AccessPolicy::coverageFor → own_plan needs no pass
need       = covered where coverage ≠ own_plan and no pass in {pending, reserved, joined}
if max_passes: allowed = max(0, max_passes − count(passes in {pending, reserved, joined}))
               need = first `allowed` of need (ordered by created_at / row order); the rest are reported cap_reached
free       = funded_passes − count(passes in {reserved, joined})
to_reserve = min(|need|, free);   to_buy = |need| − to_reserve
subtotal   = to_buy × unit_price_minor; discount (coupon, applies_to ∈ {any, sponsorship}); vat; total
```

**Publish** (`SponsorshipService::reserveForPublish`):

- **mode none** → nothing to do.
- **Otherwise** compute the quote.
  - If `to_buy > 0`: throw **409 `sponsorship_payment_required`** with `details.quote`. Nothing is written.
  - If not: create `reserved` passes (`source = purchase` when funded by a payment, `freed_slot` when reusing a released slot) for each `need` invitation.

**Checkout, `intent: publish`.**

- If `to_buy = 0`, it returns 409 `sponsorship_already_funded`; the client simply publishes.
- **Otherwise** it creates:
  - a payment (purpose `sponsorship`) with the line `sponsored_pass × to_buy`;
  - `pending` passes for the first `to_buy` invitations of `need` (hold 30 min);
  - metadata `{competition_id, intent: "publish", pass_ids}`.
- **On success:**
  1. The passes become `reserved`, `funded_passes += to_buy`, and the sponsorship becomes `active`.
  2. After the fulfilment commit, `PublishCompetition` is called with actor = the payer user (channel `system`).
  3. If publish throws (for example `validation_failed` because `bidding_opens_at` is now in the past, or `live_event_capacity_reached`), the competition stays a draft, the funded passes stay reserved, and `SponsorshipPublishFailed` is dispatched with the error code. The issuer fixes the problem and publishes again without paying again.

**Checkout, `intent: invite`** (`body.invitations = [{email, name?, organization_id?, vendor_id?}]`, all sponsored):

- The quote treats the rows as new candidates.
- The payment metadata stores the rows. **No invitation or pass exists before the payment succeeds.**
- **On success:**
  1. `funded_passes += to_buy`.
  2. Then `InviteParticipants($c, rows with sponsored = true, payer)`. Its `reserveForInvitations` now finds enough free slots.
  3. If the invite throws (for example `invitation_cutoff_passed`), the funded slots stay free and are counted as unused at settlement. `SponsorshipPublishFailed` is dispatched with the error code.

**Invite more without a payment.** `InviteParticipants` → `reserveForInvitations`:

- It uses free slots.
- If sponsored rows need more than the free slots → 409 `sponsorship_payment_required` with `details.quote`, and the whole call rolls back.

**Release** (sync listeners on `InvitationDeclined` and `InvitationRevoked`):

- a `reserved` pass → `released` (its slot is freed);
- a `pending` pass → `void`.

**Duplicate organization.** When an organization joins through invitation A while invitation B to the same organization is `sent`/`viewed`, B → `revoked` (`duplicate_organization`) and its pass is released.

**Join.** `AccessPolicy::resolveJoin` (§8.4).

**Settlement** (queued listener on `CompetitionClosed` and `CompetitionCancelled`; locks the sponsorship):

- `reserved → unused` and `pending → void`;
- `unused_count = funded_passes − count(joined)`;
- `status = settled`, `settled_at`;
- `SponsorshipSettled`.

**Voucher.**

- The admin sees settled sponsorships with `unused_count > 0` and no `voucher_coupon_id`.
- The action **Issue voucher** creates a coupon with:
  - `kind = voucher`, `discount_type = fixed`;
  - `amount_minor = balance_minor = unused_count × unit_price_minor` (excl. VAT);
  - `organization_id` = the sponsor, `applies_to = any`;
  - validity now → +12 months;
  - `source_competition_id` and a reason.
- It sets `voucher_coupon_id` and dispatches `VoucherIssued`.

**What each audience sees.**

| Audience | Sees |
|---|---|
| The sponsored invitee | `access.coverage = sponsored` and `sponsor_name` |
| Other participants | Nothing about sponsorship |
| The issuer | Per-invitation coverage and pass status |
| The public API | `sponsored` and `pass_status` on invitations, for the issuer's own clients only |

### 13.6 Payment gateway drivers

```php
interface PaymentGateway {   // App\Modules\Billing\Contracts\PaymentGateway
    public function createCheckout(Payment $payment, string $returnUrl): CheckoutSession;   // {reference, redirectUrl}
    public function fetch(Payment $payment): GatewayPaymentState;  // {status: paid|failed|pending, amountMinor, currency, failureCode?, failureMessage?}
    public function parseWebhook(Request $request): ?GatewayWebhook; // verified {reference} or null (→ 400)
}
```

**`fake`** (default; `PAYMENT_GATEWAY=fake`):

| Step | Behaviour |
|---|---|
| `createCheckout` | reference = `fake_{payment public_id}`; `redirectUrl = {APP_URL}/pay/fake/{payment public_id}`; sets `metadata.fake_state = "pending"` |
| `GET /pay/fake/{payment}` | Blade view `billing::fake-pay`, route name `billing.fake-pay.show`, `web` middleware. It shows AR/EN (from `?lang=`, else the payer's locale), BAFO as the merchant, the description lines, subtotal, discount, VAT and total, and two buttons: **Approve** and **Decline**. With `PAYMENT_FAKE_AUTO_APPROVE=true`, the page auto-submits Approve after 1 s (for E2E tests). |
| `POST /pay/fake/{payment}/approve` and `/decline` | CSRF-protected. Sets `fake_state = paid` or `failed`, calls `HandleGatewayResult` synchronously, then returns a **303** to `return_url` + `payment={id}`. A payment that is not pending returns 409 with the page. |
| `fetch()` | Reads `metadata.fake_state` |
| `parseWebhook()` | Returns null; the fake driver has no webhooks |
| Route registration | The routes exist **only** when the driver is `fake` and `! app()->isProduction()` |

**`moyasar`** (a real skeleton, not dead code; config `bafo.billing.gateway.moyasar.*`):

| Method | Behaviour |
|---|---|
| `createCheckout` | `POST {base_url}/v1/invoices` (Basic auth with the secret key) `{amount: total_minor, currency: "SAR", description, callback_url: returnUrl, metadata: {payment_id}}` → `{id, url}` |
| `fetch()` | `GET /v1/invoices/{id}`: `paid` → paid; `failed` or `expired` → failed; otherwise pending |
| `parseWebhook()` | Checks that `secret_token` equals the configured webhook secret, then takes `data.invoice_id` or `data.id` |

Missing keys → 503 `gateway_not_configured`.

### 13.7 Invoicing (`EInvoicing`)

```php
interface EInvoicing {   // App\Modules\Billing\Contracts\EInvoicing
    public function issue(Invoice $invoice): EInvoiceResult; // {status: cleared|reported|rejected, documentId?, zatcaUuid?, qrPayload?, error?}
}
```

**`IssueInvoiceForPayment`** (queued on `PaymentSucceeded`; skipped when `total_minor = 0`):

1. Create the invoice:
   - `number` from `invoice_number_seq` with year = the Riyadh year;
   - `issue_date = supply_date` = the Riyadh date of `paid_at`;
   - lines copied from `payment_lines`;
   - `discount_minor` = payment discount + credit;
   - VAT and total as on the payment;
   - `seller_snapshot` from `bafo.billing.seller` and `buyer_snapshot` from the organization;
   - `einvoice_status = pending`.
2. Call `EInvoicing::issue()`.
3. **cleared or reported:** store the ids and QR, render `billing::pdf.invoice` through `PdfRenderer` (AR and EN side by side, with the QR image generated from `qr_payload`), save the file (purpose `invoice_pdf`), set `pdf_file_id` and `cleared_at`, and dispatch `InvoiceIssued`.
4. **rejected or error:** `einvoice_status = rejected` or `failed`, increment attempts, store `last_error`. The retry command handles it; an admin widget lists the failures.

**The `fake` driver** returns `cleared`, with:

- `documentId = "fake-{public_id}"`;
- `zatcaUuid = Str::uuid()`;
- `qrPayload` = base64 TLV of: (1) seller name AR, (2) seller VAT number, (3) the issue timestamp ISO, (4) the total incl. VAT as a decimal string, (5) the VAT total as a decimal string.

**Credit notes** are recorded manually by the admin in Filament: type `credit_note` with `original_invoice_id`. They are issued in the provider's portal in the MVP.

### 13.8 Account deletion (Identity)

**Request.** `POST /account/deletion` `{password, reason?}`:

- **Scope.** Owner → scope `organization`; anyone else → scope `user`.
- **Organization-scope blockers** (409 `account_deletion_blocked`, `details.blockers[]`):
  - the organization has competitions in `scheduled`, `live`, `bafo_round` or `closed`;
  - it is a participant in competitions in `scheduled`, `live` or `bafo_round`.
- **User-scope blockers:** none. The member's in-flight work stays with the organization.
- **Effect:** creates the request with `scheduled_for = now + 14 days`, sends the mail, and revokes all other tokens.
- **Cancel:** `DELETE /account/deletion` → `cancelled`.

**Execution** (hourly command), in one TX per request:

- **user scope:**
  - anonymise the user: `name = "Deleted user"` (the lang key `identity.deleted_user` in the API), `email = deleted+{public_id}@invalid.bafo`, phone null, avatar deleted;
  - revoke all Sanctum tokens, delete the membership, soft-delete the user. Device tokens are removed by the Notifications listener on `AccountDeleted`, not by Identity.
- **organization scope:** the same for every member, then the organization:
  - `status = deleted`, `name = "Deleted organization"`;
  - contact fields and the logo and profile files are cleared;
  - soft delete;
  - API clients and webhook endpoints are revoked by the Integrations listener on `AccountDeleted`, not by Identity.
- **What is kept:** legal and financial records (invoices, payments, competitions, the offers ledger, audit logs), with the snapshots they hold.
- Finally: `AccountDeleted`.

### 13.9 Registration, OTP, login, password reset (Identity)

**Register.** `POST /auth/register` (guest, honeypot, `throttle:auth`):

- **Creates** the organization (`status active`), the user (`pending_verification`), a membership (`owner`, `active`), the consents (terms and privacy, at the latest published versions), and the category pivots.
- **If `invitation_token`** is present, its e-mail must equal the registering e-mail, otherwise → 422 `invitation_email_mismatch`.
- **Sends** the OTP for `email_verification`.
- **Returns** 201 `{email, otp_expires_at}`. There is no token yet.

**OTP.**

| Rule | Value / error |
|---|---|
| Code | 6 digits, random. With `OTP_FAKE_CODE` set **and** `APP_ENV ∈ {local, testing}`, that value is used instead. |
| Lifetime | 10 min TTL |
| Attempts | 5 per code → 429 `otp_too_many_attempts` (the code is consumed) |
| Resend | 60 s cooldown → 429 `otp_resend_cooldown` (`details.retry_after_seconds`); 5 per hour per e-mail |
| Wrong code | 422 `otp_invalid` |
| Expired code | 422 `otp_expired` |
| Replacement | A new send invalidates older unconsumed codes of the same purpose |

**Verify.** `POST /auth/otp/verify` `{email, code, purpose: "email_verification", device_name}`:

- sets `email_verified_at`, `status active`, `last_login_at`;
- issues a Sanctum token (name = `device_name`);
- dispatches `EmailVerified`;
- returns `AuthTokenPayload`.

**Login.** `POST /auth/login` `{email, password, device_name}`. Errors, in order:

| Case | Error |
|---|---|
| Unknown e-mail, deleted user or bad password | 401 `invalid_credentials` (never reveal which) |
| Membership invited or inactive | 403 `account_inactive` |
| Organization suspended | 403 `organization_suspended` |
| E-mail not verified | 403 `email_not_verified`. A new verification OTP is sent automatically, respecting the cooldown. |

On success: a token and `last_login_at`.

**Logout.** `POST /auth/logout` deletes the current token.

**Password reset.**

- **Forgot.** `POST /auth/password/forgot` `{email}` always returns 202. It sends a `password_reset` OTP only when the user exists.
- **Check (optional).** `POST /auth/otp/check` `{email, code, purpose: "password_reset"}` returns 200 `{valid: true}` without consuming the code; errors are as for OTP.
- **Reset.** `POST /auth/password/reset` `{email, code, password, password_confirmation}` consumes the code, sets the password, revokes **all** tokens, and returns 204.

**Password rule:** ≥ 8 characters with at least one lowercase letter, one uppercase letter, one digit and one symbol. `Password::min(8)->mixedCase()->numbers()->symbols()`, with messages from the validation lang files.

### 13.10 Team members (Identity)

**Add.** `POST /team/members` `{name, email, phone?, role: admin|member, can_award?, can_purchase?}`:

- permission `team.manage`;
- seat check (§13.2);
- the e-mail must not belong to an existing user → 422 `validation_failed` on `email`;
- creates the user (`pending_verification`, password null) and a membership (`invited`, token hash, expiry +7 days);
- sends the team-invitation mail.

**Accept.** `POST /auth/team-invitations/accept` `{token, password, password_confirmation, device_name}`:

- the token must be valid and unexpired → otherwise 422 `team_invitation_invalid`;
- sets the password, `email_verified_at = now` (the link proves the e-mail), user `active`, membership `active`, `joined_at`;
- returns `AuthTokenPayload`.

**Lookup.** `POST /auth/team-invitations/lookup` `{token}` → `{email, name, organization: {name, logo_url}}`.

**Update.** `PATCH /team/members/{membership}` `{role?, can_award?, can_purchase?, status?: active|inactive}`:

- the owner cannot be changed → 409 `cannot_modify_owner`;
- a member cannot change themselves → 409 `cannot_modify_self`;
- reactivation checks seats.

**Remove.** `DELETE /team/members/{membership}`:

- not the owner, not self;
- deletes the membership, anonymises and soft-deletes the user, and revokes their tokens;
- the member's authored competitions stay with the organization.

**Resend.** `POST /team/members/{membership}/resend-invitation` (invited only) issues a new token, so the old one becomes invalid.

### 13.11 Invitations: create, claim, join, decline (Competitions)

**Create** (`InviteParticipants`). Each row is `{email}`, `{organization_id}` (from suggestions) or `{vendor_id}`, plus `{name?, sponsored?}`.

- **E-mail resolution:**
  - `organization_id` → the organization's `email` (and `organization_id` is set);
  - `vendor_id` → the vendor's `email` (and `organization_id = vendor.linked_organization_id` when that organization is a proven recipient, see below);
  - a raw e-mail → if an **active user who verified that e-mail** has an active membership of an active organization, `organization_id` = that organization.
  - **Proven recipient** (SECURITY_REVIEW S-12, `Organization::isProvenRecipient`): the address is an active member's verified e-mail, or the platform verified the organization (`verified_at`) and the vendor's CR number is its own. A pending team member, a self-declared contact e-mail or the CR number of an unverified organization never binds an invitation; such invitations stay unbound and the address owner claims them (below).
- **Rejections** (per row). The response is 422 `validation_failed`: `errors` has the index-keyed messages (`invitations.{i}.email`) and `details.item_codes` maps the same paths to these codes (CONVENTIONS §8.1):
  - the e-mail or organization is already invited to this competition → `invitation_duplicate`;
  - the resolved organization is the issuer, or has the same CR → `cannot_invite_own_organization`;
  - the vendor is `blocked` → `vendor_blocked`.
- **Competition-level rejections:**
  - after `invitation_cutoff_at` → 409 `invitation_cutoff_passed`;
  - the total would exceed `competitions.max_participants` → 422 `max_participants_exceeded`.
- **On a draft:** the status is `draft`. **On scheduled or live:** the status is `sent` immediately, with a token, the mail and `InvitationSent`. Sponsored rows go through `reserveForInvitations` first (§13.5).

**Token.** 40 random bytes in base64url. The DB stores `sha256(token)`. The mail link carries it in the fragment only (§11.3).

**Lookup** (guest). `POST /invitations/lookup` `{token}` (`throttle:guest`):

- 404 `invitation_invalid` for unknown, revoked or draft invitations;
- otherwise the teaser (API.md §2.6), `status`, a masked e-mail, `join_deadline`, `sponsored` (bool; only true if the coverage is sponsored), and `next_step`:

  | `next_step` | When |
  |---|---|
  | `register` | no user with that e-mail |
  | `login` | a user exists |
  | `join` | never returned to guests |

- sets `viewed` if `sent`.

**Decline by token** (guest). `POST /invitations/decline` `{token, reason?}` → `declined` (from sent or viewed only; otherwise 409 `invalid_state_transition`).

**Claim** (auth). `POST /invitations/claim` `{token, code?}` binds the invitation to the caller's organization:

- If the invitation already has `organization_id`: it must equal the caller's organization, otherwise → 409 `invitation_belongs_to_another_organization`.
- If the invitation e-mail equals the user's e-mail: bind immediately (200, the Invitation).
- Otherwise:
  - without `code`, send an OTP (`invitation_claim`, `context.invitation_id`) to the **invited** e-mail and return 202 `{otp_sent_to: masked}`;
  - with a valid `code`, bind (200).

**Join.** `POST /invitations/{invitation}/join` `{accept_terms: true}`:

- **Preconditions:**
  - the invitation belongs to the caller's organization;
  - `accept_terms` must be true → otherwise 422 `terms_not_accepted`;
  - status `sent` or `viewed` → otherwise 409 `invalid_state_transition`;
  - competition `scheduled` or `live` and now < `invitation_cutoff_at` → otherwise 409 `join_deadline_passed`;
  - no participant exists for (competition, organization) → otherwise 409 `already_participating`.
- **TX:**
  - `AccessPolicy::resolveJoin` (it may throw 403 `plan_required`);
  - create the participant (random `alias_no`; `terms_version` = the current published `competition_rules` version; `entitlement_source`);
  - invitation → `joined`;
  - revoke duplicate invitations (§13.5);
  - dispatch `InvitationJoined`.
- **Response:** 200, the Competition (participant projection).

**Decline** (auth). `POST /invitations/{invitation}/decline` `{reason?}`.

---

## 14. Integrations (R1 MVP)

### 14.1 API clients and keys (managed in the dashboard only)

**Gate.** `integrations.manage` **and** `organizations.api_enabled` (else 403 `api_access_disabled`).

**Create client.** `POST /integrations/api-clients` `{name, description?, scopes[]}`:

1. Insert the `api_clients` row.
2. Create a Passport client with `app(\Laravel\Passport\ClientRepository::class)->createClientCredentialsGrantClient($name)`.
3. Store its id in `oauth_client_id`.
4. Return `client_id` (= the api_client **public_id**) and `client_secret` (Passport's `plainSecret`), **shown once**.

**Create key.** `POST /integrations/api-clients/{id}/keys` `{expires_in_days?: 1–730, default 365}` returns the plain key once, in the format:

```
bafo_{env}_{prefix8}_{secret32}      env = config('bafo.integrations.key_environment') ∈ {live, test}
prefix8  = 8 × [a-z0-9]          secret32 = 32 × [A-Za-z0-9]
stored: prefix, sha256(full key), last_four
```

- **Revoke:** `DELETE` sets `revoked_at`.
- **Rotate the client secret:** `POST …/rotate-secret` calls `ClientRepository::regenerateSecret($passportClient)` and shows `$passportClient->plainSecret` once.
- **Suspend or revoke a client:** revoked clients cannot authenticate by either method. Revoking runs `$passportClient->tokens()->each(fn ($t) => $t->revoke())` and `$passportClient->forceFill(['revoked' => true])->save()`. Do not call the deprecated `ClientRepository::delete()`. It also sets `api_keys.revoked_at` on every key.

### 14.2 OAuth2 client credentials with Passport 13 (exact recipe)

**`IntegrationsServiceProvider`:**

- `register()`: `Passport::ignoreRoutes()`.
- `boot()`:
  - `Passport::tokensExpireIn(CarbonInterval::minutes(30))`;
  - `Passport::tokensCan(ApiScope::descriptions())`, where the descriptions are English.

**Keys.**

- `php artisan passport:keys` writes `storage/oauth-{private,public}.key` (gitignored). `scripts/dev.sh` runs it if the files are missing.
- Tests generate keys once, in `tests/Support/Integrations/PassportKeys.php` (`Passport::loadKeysFrom()` pointed at a temp directory).

**Token endpoint.** `POST /api/public/v1/oauth/token`, route name `public.v1.oauth.token`, handled by `IssueAccessTokenController`, with `withoutMiddleware([AuthenticatePublicApiClient::class, 'throttle:public-api'])` and `throttle:oauth-token`.

1. **Input.** Accept `application/x-www-form-urlencoded` or JSON. Client credentials come from HTTP Basic, or from the body fields `client_id` and `client_secret`.
2. **Grant.** `grant_type` must be `client_credentials` → otherwise 400 `unsupported_grant_type`.
3. **Client.** `client_id` is an api_client **public_id**. Load it; it must be `active`, with the organization active and `api_enabled` → otherwise 401 `invalid_client`.
4. **Scope.** `scope` (space-separated) must be ⊆ `api_clients.scopes` → otherwise 400 `invalid_scope`. When it is absent, use all of the client's scopes.
5. **Delegate.** Build a PSR-7 request with `grant_type`, `client_id = api_clients.oauth_client_id`, `client_secret` and `scope`. Delegate to `Laravel\Passport\Http\Controllers\AccessTokenController::issueToken`. A league `invalid_client` becomes 401 `invalid_client`.
6. **Response.** 200, in the **RFC 6749 shape (the only response without the `{data}` envelope)**:

   ```json
   {"access_token": "eyJ…", "token_type": "Bearer", "expires_in": 1800, "scope": "competitions:read vendors:write"}
   ```

   Errors carry both shapes:

   ```json
   {"error": "invalid_client", "error_description": "…", "message": "…", "code": "invalid_client", "errors": {}}
   ```

**Request authentication.** `AuthenticatePublicApiClient` (alias `api.client`), pushed onto `public_v1`:

- **API-key path.** The bearer matches `^bafo_(live|test)_[a-z0-9]{8}_[A-Za-z0-9]{32}$`:
  - find by `prefix` and check `hash_equals(sha256(token), key_hash)`;
  - the key must not be revoked or expired;
  - the client must be active, and the organization active and `api_enabled`;
  - `scopes = client.scopes`.
- **OAuth path.** Otherwise:
  - `app(\League\OAuth2\Server\ResourceServer::class)->validateAuthenticatedRequest($psr)`;
  - read `oauth_client_id` and `oauth_scopes`;
  - load the api_client by `oauth_client_id`, with the same checks;
  - `scopes = token scopes ∩ client.scopes`.
- **Failure:** 401 `invalid_token`, with header `WWW-Authenticate: Bearer error="invalid_token"`.
- **Success:**
  - set `CurrentActor = Actor::forApiClient()`;
  - set request attributes `api_client` and `api_scopes`;
  - update `last_used_at`/`last_used_ip` on the key or client at most once per 60 s (cache guard).

**Scope middleware.** `EnsureApiScope` (alias `api.scope`), for example `->middleware('api.scope:competitions:read')`. Failure: 403 `insufficient_scope` with `details.required_scope`.

### 14.3 Rate limits (public API)

| Limiter | Key | Limit |
|---|---|---|
| `public-api` | the API client id | GET: 600 per minute; other methods: 300 per minute |
| `public-api` | the organization id | 1500 per minute |
| `oauth-token` | IP + `client_id` | 20 per minute |

The `public-api` limiter returns both limits. Every limit returns 429 `too_many_requests` with `Retry-After`.

### 14.4 Scopes (`App\Modules\Integrations\Enums\ApiScope`)

- `organization:read`
- `lookups:read`
- `vendors:read`, `vendors:write`
- `competitions:read`, `competitions:write`, `competitions:publish`, `competitions:manage`
- `invitations:read`, `invitations:write`
- `offers:read`
- `awards:read`, `awards:sync`
- `webhooks:manage`

**UI defaults** when creating a client:

- all read scopes;
- `vendors:write`, `competitions:write`, `invitations:write` and `awards:sync`.

`competitions:publish`, `competitions:manage` and `webhooks:manage` are opt-in, with a warning.

### 14.5 Webhooks (outbox → delivery)

**Emit.** `App\Modules\Integrations\Services\WebhookEmitter::emit(Organization $org, string $type, Model $subject, array $object): void`:

- It is called only from the Integrations synchronous listeners (§10).
- It writes **only if** the organization has at least one `active` endpoint whose `event_types` contains the type or `*`.
- It inserts a `webhook_events` row with `sequence = coalesce(max(sequence), 0) + 1` for that subject and `payload` = the full envelope (API.md §4.2).

**Dispatch.** After commit, `DispatchWebhookEvent($eventId)` (queue `webhooks`):

1. Create `webhook_deliveries` rows for the matching active endpoints.
2. Set `dispatched_at`.
3. Dispatch `DeliverWebhook($deliveryId)` for each.

**Deliver.** `DeliverWebhook`:

- Re-check that the endpoint is active, then run the SSRF guard (§14.6).
- `POST` with body = `json_encode(payload)`. The same bytes are used on every attempt, and the encoding is stored with the event.
- Timeout 15 s. **Redirects are not followed.** Headers:

  ```
  content-type: application/json
  user-agent: BAFO-Webhooks/1.0
  webhook-id: {event public_id}
  webhook-timestamp: {unix seconds at send time}
  webhook-signature: v1,{base64(hmac_sha256(key = base64_decode(substr(secret, 6)), "{webhook-id}.{webhook-timestamp}.{body}"))}
  ```

- **2xx** → `succeeded`: record the stats, set `endpoint.last_success_at`, and clear `failing_since`.
- **Any other status, a timeout or a network error** → `attempts++`, store the stats, and set `failing_since ??= now`.
  - While attempts < 9: `next_attempt_at = now + backoff[attempts − 1]`, where `backoff = [5 s, 1 min, 5 min, 30 min, 2 h, 5 h, 10 h, 14 h]`. That is about 31.6 h in total, which is ≥ 24 h.
  - Otherwise → `failed`.
- **410 Gone** → the delivery `failed`, and the endpoint `disabled` (`failing`).
- **After any failure**, if `failing_since < now − 5 days` → the endpoint is `disabled` (`failing`) and `WebhookEndpointDisabled` is dispatched.

**Test.** `POST …/test` creates a `webhook.test` event for **that endpoint only**, whatever its subscription.

**Redeliver.** `POST /…/webhook-deliveries/{id}/redeliver` sets the delivery to `pending` with `next_attempt_at = now`. The attempt count is kept.

**Secrets.**

- Created at endpoint creation as `whsec_` + base64 of 32 random bytes, and **shown once**.
- `POST …/rotate-secret` replaces the secret immediately and shows it once. There is no dual-secret overlap in the MVP.

### 14.6 SSRF guard (`WebhookUrlGuard`)

- **On create, update and each send:**
  - the scheme must be `https`;
  - there are no credentials in the URL;
  - the port is 443, or 1024–65535.
- **Resolution:** resolve A and AAAA. **Every** address must be public. Blocked ranges:
  - RFC 1918, 100.64/10, 127/8, 169.254/16, 0/8;
  - `::1`, `fc00::/7`, `fe80::/10`;
  - IPv4 multicast.
- **Connect to the resolved IP** (pin it; set the `Host` header) to defeat DNS rebinding.
- **Violations:** 422 `webhook_url_invalid` at create or update; the delivery `failed` with error `blocked_target` at send.
- **Local development.** `WEBHOOKS_ALLOW_PRIVATE_TARGETS=true` (honoured only when `APP_ENV ≠ production`) allows `http` and private or loopback targets.

### 14.7 Vendor import (CSV/XLSX, dashboard)

**Template.** `GET /integrations/imports/templates/vendors?format=csv|xlsx`:

- CSV is UTF-8 **with BOM**; the delimiter `,` or `;` is auto-detected.
- The XLSX has a "Read me" sheet in AR/EN and dropdowns for `region_code`, `category_codes` and `status`.

**Columns** (`*` = required):

| Column | Rule |
|---|---|
| `external_system` | slug, §5.4 |
| `external_id` | |
| `name`* | |
| `name_en` | |
| `cr_number` | 10 digits |
| `vat_number` | 15 digits, `3…3` |
| `email`* | |
| `contact_name` | |
| `phone` | E.164 |
| `region_code` | |
| `city` | |
| `category_codes` | `;`-separated |
| `status` | `active` / `blocked`, default `active` |

**Job.** `POST /integrations/imports` (multipart `file`, `type = vendors`, `mode = validate|commit`) runs asynchronously (queue `imports`), with ≤ 10 000 rows and ≤ 20 MB.

**Row processing:**

- **Validation:** each row → errors `{row, column, code, message}`. The codes are `required`, `invalid_format`, `unknown_region`, `unknown_category`, `duplicate_in_file`.
- **Upsert key:** `(external_system, external_id)` through `external_refs` (type `supplier`) when both are given. Otherwise the vendor e-mail in the organization.
- **commit** applies the valid rows (and skips the invalid ones), with source `import`.
- **validate** writes nothing.

**Results.** The errors file is the original columns plus an `errors` column (CSV/XLSX, same format). `errors_preview` holds the first 100.

### 14.8 Exports (dashboard)

**Job.** `POST /integrations/exports` `{type, format, competition_id?}` (async).

- The competition must belong to the organization as issuer.
- **Every export applies the issuer projection:** amounts in sealed competitions before unlock are empty.
- **Formula-injection guard:** a cell starting with `= + - @` is prefixed with `'`.
- **CSV** is UTF-8 with BOM. Amounts are written as **decimal SAR with 2 decimals** (for Excel users), and the headers carry "(SAR, excl. VAT)".

| Type | One row per | Columns |
|---|---|---|
| `results` | participant (competition required) | reference_no, title, direction, format, closed_at, participant_name, cr_number, vat_number, vendor_external_id, rank, current_amount, first_amount, offers_count, last_offer_at, is_leader, awarded |
| `offer_log` | ledger row (competition required) | reference_no, seq, accepted_at (Asia/Riyadh, ms), participant_name, amount, stage, voided |
| `awards` | award (optional date range) | award_id, reference_no, title, direction, awarded_at, status, winner_name, cr_number, vat_number, vendor_external_system, vendor_external_id, amount, vat_rate, vat_amount, amount_incl_vat, justification, erp_sync_status, erp_refs |
| `vendors` | vendor | the import columns + linked (Y/N) |

### 14.9 OpenAPI document

- **Location:** `apps/api/resources/openapi/public-v1.yaml` (Integrations-owned). It is OpenAPI 3.1 and hand-written from API.md §3. Keep it in sync.
- **Served at:**
  - `GET /api/public/v1/openapi.yaml` (guest);
  - an HTML reference at `GET /docs/api` (Integrations `Routes/web.php`, the Scalar bundle from `cdn.jsdelivr.net`).
- **Postman collection:** `apps/api/resources/openapi/bafo-public-v1.postman_collection.json`.

---

## 15. Drivers, configuration and settings

### 15.1 Driver interfaces

| Interface | Module | Drivers | Selected by |
|---|---|---|---|
| `Billing\Contracts\PaymentGateway` | Billing | `fake` (default), `moyasar` | `bafo.billing.gateway.driver` |
| `Billing\Contracts\EInvoicing` | Billing | `fake` | `bafo.billing.einvoicing.driver` |
| `Notifications\Contracts\PushNotifier` `send(Collection<DeviceToken> $tokens, PushMessage $message): void` | Notifications | `log` (writes to the `push` log channel: title, body, data, token count) | `bafo.notifications.push.driver` |
| Laravel mail | Platform | `log` (default), `smtp` | `MAIL_MAILER` |
| `Support\Pdf\PdfRenderer` | Platform | `mpdf` | `bafo.platform.pdf.driver` |
| SMS | – | none | – |

### 15.2 Environment keys (all in `.env.example` with placeholders; Platform owns the file, modules list their keys here)

| Key | Default (local) | Config path | Owner |
|---|---|---|---|
| `WEB_URL` | `http://localhost:3000` | `bafo.platform.web_url` (links in mails) | Platform |
| `PDF_DRIVER` | `mpdf` | `bafo.platform.pdf.driver` | Platform |
| `REVERB_PUBLIC_HOST` | `localhost` | `bafo.platform.realtime.host` (returned by app-config) | Platform |
| `OTP_FAKE_CODE` | *(empty)* | `bafo.identity.otp.fake_code` (local and testing only) | Identity |
| `PAYMENT_GATEWAY` | `fake` | `bafo.billing.gateway.driver` | Billing |
| `PAYMENT_FAKE_AUTO_APPROVE` | `false` | `bafo.billing.gateway.fake.auto_approve` | Billing |
| `MOYASAR_BASE_URL` / `MOYASAR_SECRET_KEY` / `MOYASAR_PUBLISHABLE_KEY` / `MOYASAR_WEBHOOK_SECRET` | `https://api.moyasar.com` / empty ×3 | `bafo.billing.gateway.moyasar.*` | Billing |
| `EINVOICING_DRIVER` | `fake` | `bafo.billing.einvoicing.driver` | Billing |
| `BILLING_ALLOWED_RETURN_URLS` | `http://localhost:3000` | `bafo.billing.allowed_return_urls` | Billing |
| `SELLER_NAME_AR` / `SELLER_NAME_EN` / `SELLER_VAT_NUMBER` / `SELLER_CR_NUMBER` / `SELLER_ADDRESS` | placeholders (`BAFO (placeholder)`, `300000000000003`, `0000000000`, …) | `bafo.billing.seller.*` | Billing |
| `PUSH_DRIVER` | `log` | `bafo.notifications.push.driver` | Notifications |
| `API_KEY_ENV` | `test` | `bafo.integrations.key_environment` | Integrations |
| `WEBHOOKS_ALLOW_PRIVATE_TARGETS` | `true` locally, `false` in production | `bafo.integrations.webhooks.allow_private_targets` | Integrations |
| `ADMIN_MFA_REQUIRED` | `false` | `bafo.admin.mfa_required` | Admin |

### 15.3 Runtime settings (`app_settings`, editable in Filament; defaults registered by the owning module)

| Key | Default | Owner | Meaning |
|---|---|---|---|
| `app.min_version.ios` / `app.min_version.android` | `"1.0.0"` | Platform | Force update (426) |
| `app.latest_version.ios` / `app.latest_version.android` | `"1.0.0"` | Platform | Soft-update hint |
| `app.store_links` | `{"ios": "", "android": ""}` | Platform | |
| `app.maintenance.enabled` | `false` | Platform | 503 `maintenance` |
| `app.maintenance.message` | `{"ar": "", "en": ""}` | Platform | |
| `app.support` | `{"email": "", "phone": "", "whatsapp": ""}` | Platform | |
| `competitions.min_duration_minutes` | 10 | Competitions | R16 |
| `competitions.max_duration_days` | 90 | Competitions | R16 |
| `competitions.invite_cutoff_minutes` | 60 | Competitions | cutoff without a final window |
| `competitions.max_participants` | 200 | Competitions | R17 |
| `competitions.min_extend_minutes` | 5 | Competitions | manual extend |
| `competitions.final_window_bounds` | `{"min": 30, "max": 600}` | Competitions | R11 |
| `bidding.max_amount_minor` | 1000000000000 | Bidding | SAR 10 bn |
| `bidding.offer_min_interval_seconds` | 2 | Bidding | rate limit |
| `bidding.outlier_guard_bps` | 2000 | Bidding | 20% |
| `bidding.auto_extend_bounds` | `{"window_min": 60, "window_max": 1800, "by_min": 60, "by_max": 1800, "max_min": 1, "max_max": 50}` | Bidding | R10 |
| `bidding.bafo_duration_bounds` | `{"min": 15, "max": 4320}` | Bidding | R12 |
| `bidding.max_concurrent_live` | 30 | Bidding | R19 live-event cap |
| `bidding.closing_soon_minutes` | `[10, 2]` | Bidding | push thresholds |
| `billing.trial_days` | 30 | Billing | |
| `billing.trial_plan_code` | `"plus"` | Billing | |
| `billing.custom_min_seats` / `billing.custom_max_seats` | 4 / 50 | Billing | |
| `billing.custom_seat_monthly_price_minor` / `billing.custom_seat_annual_price_minor` | 50000 / 500000 | Billing | |
| `billing.checkout_hold_minutes` | 30 | Billing | |
| `billing.renewal_window_days` | 30 | Billing | |
| `sponsorship.enabled` | `true` | Billing | global switch (per-org flag also required) |
| `sponsorship.pass_price_tender_minor` / `sponsorship.pass_price_auction_minor` | 20000 / 20000 | Billing | SAR 200 excl. VAT |

VAT (`bafo.billing.vat_rate_bp = 1500`) is **config, not a setting**.

---

## 16. Admin panel (Filament 5, `/admin`)

**Panel.**

- `AdminPanelProvider` is moved under Admin ownership. It uses `authGuard('admin')` and discovers resources, pages and widgets in `app/Modules/Admin/Filament`.
- Brand colour: `#0B7A55`. Locales: `ar` (RTL) and `en`.
- MFA follows §8.7.
- **The guard and provider** are added at runtime in `AdminServiceProvider::register()`:
  - `config(['auth.guards.admin' => ['driver' => 'session', 'provider' => 'admins']])`;
  - `config(['auth.providers.admins' => ['driver' => 'eloquent', 'model' => Admin::class]])`.

**Every state-changing action calls the module Action** with `Actor::forAdmin()`. Each is audited.

| Resource / page | Content | Actions (→ module Action) |
|---|---|---|
| Dashboard | 6 counters (organizations, active subscriptions, live competitions, payments today, failed e-invoices, sponsorships awaiting a voucher) and a live-events table | – |
| Organizations | Search; view (profile, members, subscription, competitions) | verify / unverify; suspend / unsuspend (reason); toggle `api_enabled`, `auction_enabled`, `sponsorship_enabled`; resend the verification OTP to the owner |
| Users | Search, view | deactivate / reactivate the membership |
| Plans | CRUD (super_admin) | – |
| Coupons and vouchers | CRUD for coupons; vouchers are read-only with their balance | – |
| Subscriptions | List and filter | **Grant subscription** → `GrantSubscription` |
| Lookups | Regions, categories (`auction_allowed`), close reasons, presets: CRUD (codes immutable after create) | – |
| Competitions | List (status filter), view: timeline, invitations, participants, offer ledger (issuer projection plus voids), extensions, rejections, award | **Extend** → `ExtendCompetition` (kind admin); **Cancel** → `CancelCompetition`; **Force close** → `ForceCloseCompetition`; **Void offer** → `VoidOffer` |
| Payments | List, filter, CSV export | **Reconcile now** → `ReconcilePayment`; **Mark paid manually** (reference) → `HandleGatewayResult` with a manual state; **Record refund** (reference) |
| Invoices | List with e-invoice status, download | **Retry e-invoice**; **Record credit note** (manual) |
| Sponsorships | List with counters | **Issue voucher** → `IssueSponsorshipVoucher`; **Grant pass** (for an invitation) → `GrantSponsoredPass` |
| API clients / webhook endpoints | Per organization, read-only | **Suspend client** / **Reactivate client** |
| Settings | Page editing every §15.3 key (super_admin) | – |
| Legal documents | CRUD; publish a version | – |
| Contact inbox | List, view, change status | – |
| Audit log | Read-only list with filters | – |
| Account deletions | Queue (pending, completed) | – |
| Admins | CRUD (super_admin), reset MFA | – |

---

## 17. Seeds and demo data

**`DatabaseSeeder`** (Platform) runs every reference seeder that exists, in module order (all idempotent):

1. `CatalogReferenceSeeder` (§5.2)
2. `BillingReferenceSeeder` (plans)
3. `PlatformReferenceSeeder`: default settings, plus placeholder legal documents for every code in AR and EN, version `2026-10-01`, **published** (`published_at` = seed time) so that consent capture works. The body starts with a draft notice in the document's language («مسودة: يقدّم المستشار القانوني النص النهائي.» / "Draft text – counsel to provide."), so an Arabic document never opens with an English line.
4. `AdminReferenceSeeder` (one `super_admin`; its credentials come from `ADMIN_SEED_EMAIL` and `ADMIN_SEED_PASSWORD` if set, otherwise from `DemoSeeder` in local only)

**`DemoSeeder`** (`apps/api/database/seeders/DemoSeeder.php`, Platform; run with `php artisan db:seed --class=DemoSeeder`, refused in production).

- **It holds the demo credentials** (also written in `docs/build/DEMO.md`). It builds data **through module factories and Actions** where state machines are involved.
- **Organizations and users:**

  | Organization | Plan | Flags | Users |
  |---|---|---|---|
  | "Issuer Co" | pro | `api_enabled`, `auction_enabled`, `sponsorship_enabled` | owner, admin, member |
  | "Supplier A" | single | – | owner |
  | "Supplier B" | none | – | owner |
  | "Supplier C" | trial | – | owner |
  | "Buyer D" | plus | – | owner (auction bidder) |

- **Competitions of Issuer Co, in every state:**
  - draft;
  - scheduled;
  - live tender, phase `initial`;
  - live tender, phase `final_window` with offers;
  - live auction with `show_prices`;
  - live sealed;
  - `closed` with offers;
  - `bafo_round`;
  - `awarded`;
  - `not_awarded`;
  - `cancelled`;
  - a sponsored live tender (Supplier B joined on a pass).
- **Integrations:** one API client with a key, and one webhook endpoint pointing at `http://localhost:9999/webhooks` (private targets allowed locally).

---

## 18. Explicitly out of scope for the MVP (do not build)

- **Account and team:**
  - Google sign-in;
  - owner "log in as" a branch user;
  - support impersonation;
  - SSO, SCIM, Nafath and Wathq;
  - SMS;
  - web push in the browser.
- **Competitions:**
  - the deletion-approval flow, deleted list, restore and force delete (drafts are soft-deleted; published competitions are cancelled);
  - suspend and resume;
  - disqualify;
  - relaunch;
  - on-demand BAFO without the flag;
  - maker-checker award approval;
  - winner acknowledgement, decline and award-next;
  - split awards;
  - BOQ and lots;
  - public auctions;
  - deposits;
  - participant bidding through the public API;
  - issuing awards through the public API.
- **Billing and R4:**
  - the credit ledger;
  - automatic credit notes;
  - bank transfer for passes;
  - on-account billing;
  - refund automation;
  - price tiers and caps beyond `max_passes`;
  - the issuer sponsorship panel on mobile;
  - any purchase in the mobile apps.
- **R1:**
  - the sandbox stack;
  - the event feed (`/v1/events`);
  - ETag and `If-Match`;
  - `expand`;
  - tombstones;
  - IP allow-lists;
  - metering;
  - the audit API;
  - import templates other than vendors;
  - per-ERP export layouts;
  - dual-secret rotation;
  - full-payload webhooks;
  - OpenAPI 2.0 and the Power Automate connector.
- **Admin:** broadcasts (`GeneralNotification`), KPI reports, the full live-operations console, nightly hash-chain verification, and the participant PDF.
- **General:** dark mode is optional for the web (tokens exist) and not required on mobile; iPad layouts.
