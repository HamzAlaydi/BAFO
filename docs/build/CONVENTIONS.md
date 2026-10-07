# BAFO conventions (binding)

This file is part of the same contract as `ARCHITECTURE.md` and `API.md`. BRIEF.md wins on conflict.

---

## 1. Working in parallel

### 1.1 Ownership

**You edit only what you own.** If you need a change in someone else's file, write it in your final report under "Requests to other owners". Do not make the change yourself.

| Path | Owner |
|---|---|
| `apps/api/app/Support/**`, `bootstrap/**`, `config/**`, `composer.json`, `phpunit.xml`, `phpstan.neon`, `.env.example`, `routes/{web,console,channels}.php`, `database/migrations/0001_01_01_*` and the vendor (Sanctum/Passport) migrations, `database/seeders/{DatabaseSeeder,DemoSeeder}.php`, `lang/{ar,en}/{errors,validation,auth,passwords,pagination,platform}.php`, `tests/{Pest.php,TestCase.php}`, `resources/fonts/**` | **Platform** (the API foundation) |
| `apps/api/app/Modules/<Module>/**`, `routes/app_v1/<module>.php`, `routes/public_v1/<module>.php`, `lang/{ar,en}/<module>.php`, `tests/{Feature,Unit,Support}/<Module>/**` | That module's implementer |
| `apps/api/resources/views/vendor/mail/**`, `lang/{ar,en}/notifications.php` | Notifications |
| `apps/api/resources/openapi/**` | Integrations |
| `apps/api/app/Providers/Filament/AdminPanelProvider.php` | Admin |
| `apps/web/**` | Web |
| `apps/mobile/**` | Mobile |
| `scripts/**` | Platform (DevOps) |
| `docs/build/{ARCHITECTURE,API,CONVENTIONS}.md` | The architect. Report contract gaps; do not edit. |

### 1.2 General rules

- **No git commits** by agents.
- **No secrets in the repo.** `.env.example` holds placeholders only. Real `.env` files are gitignored.
- **No real external credentials** anywhere. Every external service uses its fake driver (ARCHITECTURE §15).
- **Contract gaps.** When the contract is silent, choose the simplest option that respects BRIEF.md. Mark it in code with `// CONTRACT-GAP: <what and why>` and list it in your report.
- **Before reporting, run the checks for your area (§4.6)** and report the exact commands and results.

---

## 2. PHP / Laravel

### 2.1 Language and style

- Every PHP file starts with `declare(strict_types=1);`.
- Formatting: Laravel Pint (`vendor/bin/pint`, preset `laravel`).
- Static analysis: Larastan level 6 over `app/` (`vendor/bin/phpstan analyse`). Nothing new at level 6 may be ignored.
- **Classes are `final`** unless they are designed for extension (abstract bases, models).
- **DTOs and value objects** are `final readonly class` with promoted constructor properties.
- **Enums.** Every enumerated column is a string-backed PHP enum in `Enums/`, cast on the model. There are no magic strings for statuses.
- **Types.** Every property, parameter and return is typed. Arrays carry PHPDoc shapes (`@param list<Invitation>`, `@return array{status: string}`).
- **Time.** Use `CarbonImmutable` (set with `Date::use(CarbonImmutable::class)` in the Platform provider). Read time with `now()`, except in bidding code, which uses `DbClock` (ARCHITECTURE §4.2).
- **Configuration.** Never call `env()` outside config files. Never write `DB::raw` with interpolated input.
- **Money** is always `int` halalas. Never use floats. Use the `Money` helpers.

### 2.2 Naming

| Thing | Convention | Example |
|---|---|---|
| Module namespace | `App\Modules\<Module>` | `App\Modules\Competitions` |
| Model | Singular PascalCase | `Competition`, `SponsoredPass` |
| Table | Plural snake_case | `sponsored_passes` |
| Pivot | Singular names, alphabetical | `organization_category` |
| Column | snake_case; FK `<model>_id`; money `*_minor`; bps `*_bps`; timestamps `*_at`; booleans `is_*`, `has_*`, `can_*` or an adjective (`show_prices`) | `effective_close_at` |
| Enum | PascalCase, cases PascalCase, values snake_case | `CompetitionStatus::BafoRound` → `'bafo_round'` |
| Action | Verb + noun, one public `handle()` | `PublishCompetition::handle(Competition $c, Actor $a): Competition` |
| Service | Noun + `Service` or role noun | `CompetitionTimingService`, `VisibilityProjector`, `Ranking` |
| Event | Noun + past participle | `CompetitionPublished`, `OfferAccepted` |
| Listener | Verb phrase | `SettleSponsorship`, `WriteWebhookEventForAward` |
| Job | Verb phrase | `CloseCompetition`, `DeliverWebhook` |
| Command | `<module>:<verb>` | `competitions:tick` |
| Controller | `<Resource>Controller` (resourceful), or `<Verb><Resource>Controller` (single action, `__invoke`) | `CompetitionController`, `PublishCompetitionController` |
| FormRequest | `<Verb><Resource>Request` | `StoreCompetitionRequest`, `SubmitOfferRequest` |
| Resource | `<Resource>Resource`, plus a variant suffix | `CompetitionResource`, `CompetitionTeaserResource`, `PublicCompetitionResource` |
| Policy | `<Model>Policy` | `CompetitionPolicy` |
| Route name | `app.v1.<resource>.<action>`, `public.v1.<resource>.<action>`; kebab-case segments | `app.v1.competitions.bafo-round.store` |
| URL path | kebab-case nouns, plural collections, verbs only for actions | `/competitions/{competition}/bafo-round` |
| Route parameter | The singular model name, bound by `public_id` | `{competition}`, `{invitation}` |
| Permission | `<area>.<verb>` | `competitions.award` |
| Public API scope | `<resource>:<verb>` | `competitions:read` |
| Audit action | `<noun>.<verb_past>` | `invitation.revoked` |
| Webhook type | `<noun>.<verb_past>` | `award.issued` |
| Realtime event | `<noun>.<verb_past>` | `live.updated` |
| Error code | snake_case, prefixed by domain where ambiguous | `offer_step_not_met` |

### 2.3 Layers (who does what)

**Controllers** stay thin. The pattern is: authorize (policy or permission), take the validated input from the FormRequest, call **one** Action, and return a Resource through `ApiController::ok()`/`created()`/`paginated()`. Controllers contain no business logic and no queries beyond route-model binding and simple list filters.

**FormRequests:**

- They carry all input validation.
- Custom messages and attribute names come from the module lang file (`attributes()` returns `__('<module>.attributes.<field>')`).
- `authorize()` returns true: authorization happens in the controller or policy, so that failures render the 403 or 404 envelope consistently.
- Public API requests use the same FormRequests, plus code→id translation in `prepareForValidation()`.

**Actions:**

- Actions are the only place where state changes.
- They open `DB::transaction()`, take row locks where the contract says so, enforce state rules (throwing `ApiException`), write `AuditLogger` entries, and dispatch domain events inside the transaction.
- They accept an `Actor`.
- Filament admin, jobs, the public API and the app API all call the same Actions.

**Queries.** Read endpoints may use query classes (`Queries/`) or model scopes. Every tenant-scoped query filters by organization **explicitly** (`->where('organization_id', $actor->organizationId)`). There are no global scopes for tenancy.

**Resources:**

- Always `'id' => $this->public_id`. Never expose internal ids.
- Dates go through `Iso::format()`.
- Money is an int plus `currency`.
- Conditional fields use `$this->when()` **only** where API.md says the field is omitted; everywhere else, output `null`.

**Errors.** Throw `new ApiException(errorCode: 'offer_step_not_met', messageKey: 'bidding.errors.offer_step_not_met', status: 422, details: [...], replace: [...])`.

- The code must exist in §8.
- The message key lives in **your** module lang file.
- Generic codes (§8.1) use `errors.<code>` from `lang/*/errors.php` (Platform).

**Authorization:**

- Permissions: `$user->hasPermission(Permission::CompetitionsAward)`.
- Relationship rules: policies registered in the module provider's `$policies`.
- **Deny with 404, not 403,** when the actor must not learn that the resource exists (a competition outside the viewer's rights; a private file of another organization). Use `Response::denyAsNotFound()`.

**Models:**

- `HasPublicId` on exposed models.
- Explicit `$fillable`; no `$guarded = []`.
- The `casts()` method holds enums, dates, `array` (JSONB) and `encrypted` where stated.
- `newFactory()` returns the module factory.
- No business logic beyond accessors and small predicates (`isLive()`, `phaseAt()`).

**Events** are `final readonly class` with public constructor properties (ARCHITECTURE §10). Listeners follow ARCHITECTURE §4.10: synchronous listeners only where the contract says so, otherwise `ShouldQueue` and `ShouldHandleEventsAfterCommit` with `$queue` set.

**Jobs** implement `ShouldQueue`, set `$queue`, are idempotent (they re-read state under a lock and no-op if it is already done), and use `ShouldBeUnique` where the contract says so.

**Logging:**

- `Log::info/warning/error` with structured context arrays (`['competition_id' => …]`).
- Never log tokens, secrets, passwords, OTP codes, full API keys, or offer amounts at `info` or above in production channels.
- Dedicated channels: `push` and `api_access`.

### 2.4 Migrations

- **File and range:** `app/Modules/<M>/Database/Migrations/2026_01_01_HHMMSS_<description>.php`, inside your module's range (ARCHITECTURE §3.5). Number them sequentially inside your range in creation order: `000200`, `000201`, and so on.
- **Style:** anonymous class, `declare(strict_types=1)`, with both `up()` and `down()`.
- **Keys:** `$table->id()` and `$table->ulid('public_id')->unique()` for exposed tables.
- **Timestamps:** `$table->timestampsTz()`, or `timestampsTz(6)` for the precise tables.
- **Foreign keys:** `foreignId('x_id')->constrained('xs')` only for **earlier** tables. Otherwise use `unsignedBigInteger` + `index()`, with a comment `// ref → table (unconstrained: later module)`.
- **Raw SQL:** checks, partial indexes, triggers and sequences use `DB::statement()` with the exact SQL of ARCHITECTURE §5.
- **Later changes** get a new file with a later date prefix. Never edit a migration another module owns.

### 2.5 Tests (Pest 4)

- **Location:** `tests/Feature/<Module>/<Thing>Test.php` and `tests/Unit/<Module>/…`. Shared helpers for a module go in `tests/Support/<Module>/` as namespaced functions or traits (`Tests\Support\Identity\…`).
- **Database:** PostgreSQL `bafo_test` (never SQLite; the engine relies on `FOR UPDATE`, `clock_timestamp()`, triggers, JSONB and partial indexes). `uses(RefreshDatabase::class)` comes from `tests/Pest.php`.
- **Clock:** bind `DbClock` to `CarbonDbClock`. Use `$this->travelTo()` to control time.
- **Fakes:** `Queue::fake()`, `Event::fake([...only the events under test])`, `Notification::fake()`, `Mail::fake()`, `Http::fake()` (webhook delivery) and `Storage::fake('private')` / `Storage::fake('public')` where relevant. Never call real hosts.
- **Every endpoint needs, at least:**
  - the happy path (status and response shape through `assertJsonStructure`, plus the key values);
  - 401 (unauthenticated);
  - 403 or 404 (wrong organization, missing permission);
  - 422 (a validation sample);
  - each documented business error code.
- **The engine and visibility need, at least:**
  - the direction × format × `must_beat` × `rank_visibility` × `show_prices` matrix for acceptance and projection;
  - step rounding with `min_step_bps` and granularity;
  - the tie-break by time;
  - anti-sniping (trigger window, `max`, `hard_stop`, the leader-change-only rule);
  - the close race (an offer at or after `effective_close_at` is rejected, even with status `live`);
  - idempotent replay and key reuse;
  - the rate limit;
  - BAFO (shortlist, one offer, must-not-worsen);
  - award (justification and reserve);
  - void (re-rank);
  - **leak tests**: serialise every participant-facing payload (REST, broadcast, webhook, push data) and assert it contains no reserve price, no other participant identity, and no hidden amounts.
- **Billing needs:** the pricing math (credit, coupon, voucher, VAT rounding); the fake gateway approve and decline flows; idempotent fulfilment; the sponsorship quote, reserve and release; settlement; the entitlement matrix (plan, pass or none × join or read-only).
- **Integrations need:** both auth paths (OAuth token and API key); scope enforcement; tenancy isolation (another organization's object → 404); the webhook signature (verify it with the §4.3 algorithm); the retry schedule; SSRF rejects; import validate and commit.
- **Naming:** `it('rejects an offer that does not beat the leading offer by the minimum step')`. Datasets for matrices.
- **Speed:** keep the suite under 3 minutes locally. Use `--parallel` if needed (Laravel creates `bafo_test_N` databases).

---

## 3. How to add things without touching other people's files

| To add | Do |
|---|---|
| A module | Create `app/Modules/<M>/<M>ServiceProvider.php` extending `ModuleServiceProvider`. It is discovered automatically (ARCHITECTURE §2.2 item 5). |
| An app API route | Add it to `routes/app_v1/<module>.php` (auto-loaded, prefix `api/app/v1`, name prefix `app.v1.`). Guest routes use `->withoutMiddleware('auth:sanctum')`. |
| A public API route | Add it to `routes/public_v1/<module>.php`, with `->middleware('api.scope:<scope>')` and `idempotent` on creating or acting POSTs |
| A web (Blade) route | Add it to `app/Modules/<M>/Routes/web.php`, loaded by your provider with the `web` middleware |
| A migration | Your module range and folder (§2.4) |
| Configuration | Your `app/Modules/<M>/config.php`, merged under `bafo.<module>`. List new env keys in your report so that Platform adds them to `.env.example`. |
| A runtime setting | Register its default in your provider (`Settings::defaults()`), then document it in your report (ARCHITECTURE §15.3 is the catalogue) |
| Translations | `lang/{ar,en}/<module>.php`. Both files, always the same keys. |
| An error code | Use a code from §8. A genuinely new code needs a `CONTRACT-GAP` note and a report entry. Put its message in your lang files under `<module>.errors.<code>`. |
| A permission | Report it; `Permission` is Identity-owned. **Do not invent permissions.** |
| An event or listener | The event class goes in your `Events/`. Register listeners in **your** provider, even for other modules' events. |
| A queued job or scheduled command | In your module. The schedule is registered in your provider (ARCHITECTURE §3.4). Use one of the named queues in ARCHITECTURE §12. |
| A broadcast channel | `Broadcast::channel()` in your provider's `boot()`, only for the channels ARCHITECTURE §9.2 assigns to you |
| A file purpose rule | `FileAccessRegistry::register()` in your provider |
| A morph alias | `Relation::morphMap()` in your provider's `register()` |
| A notification type | Notifications only (ARCHITECTURE §11.3). Other modules dispatch events. |
| A webhook event type | Integrations only (API.md §4.1) |
| A Filament resource | Admin only, under `app/Modules/Admin/Filament/**`. It calls module Actions. |
| Seed data | `Database/Seeders/<M>ReferenceSeeder.php` (idempotent; always run) and factories. Demo scenarios go through Platform's `DemoSeeder`, which uses your factories and Actions. |

---

## 4. TypeScript / Nuxt 4 (`apps/web`)

### 4.1 Language and tooling

- TypeScript is `strict`, with no `any`: use `unknown` and narrow it. `vue-tsc --noEmit` must pass.
- ESLint through `@nuxt/eslint`, as configured in the scaffold: 2 spaces, single quotes, no semicolons.
- `<script setup lang="ts">` everywhere. Components are PascalCase single-file components. Composables are `useX`. Stores are Pinia setup stores, `useXStore`, in `app/stores/<name>.ts`.
- API types live in `app/types/api/<module>.ts`. They mirror API.md §2 **with the same snake_case field names** (no camelCase mapping layer), with interface names equal to the resource names (`Competition`, `ParticipantLiveSnapshot`, …). Money fields are `number`.
- **All HTTP calls go through `useApi()`** (the scaffold). It adds `Authorization`, `Accept-Language` (the current locale), `X-Platform: web` and `X-Request-Id`, unwraps `{data, meta}`, and throws a normalised `ApiError {status, code, message, errors, details}`.
- API functions per module live in `app/services/<module>.ts`, exporting plain functions (`publishCompetition(id)`).
- **The token** is kept in the `bafo_token` cookie (scaffold `utils/session.ts`: SameSite=Lax, Secure in production) and is never placed in a URL.
- **Error display:** `t(\`errors.${code}\`)` if the key exists, otherwise the server `message`. Field errors are bound to form fields by path.

### 4.2 UI rules

- **RTL-first.**
  - Use logical Tailwind utilities (`ms-*`, `me-*`, `ps-*`, `pe-*`, `start-*`, `end-*`, `text-start`) and never `left`/`right`.
  - Icons that imply direction (arrows, chevrons) are mirrored in RTL.
  - Numbers, amounts, codes and e-mails go inside `<bdi>`.
- **Design tokens.** Colours come only from the CSS variables generated from `assets/brand/04_ui_color_tokens/bafo_colors.json`. There are no hex values in components, and brand rules apply:

  | Use | Colours |
  |---|---|
  | Leading standing | `#0B7A55` on `#E6F7F1`, with an icon and text |
  | Not leading | `#8C5802` on `#FEF3E2`, with an icon and text |
  | Red | Destructive actions and errors only |

  **Never colour-code a price going up or down.**
- **Server time.** Countdowns and "time left" use `useServerTime()` (the offset algorithm in ARCHITECTURE §9.6), never `Date.now()` alone.
- **Live pages** follow ARCHITECTURE §9.4: subscribe, buffer, fetch `/live`, then apply by `v`. The submit button stays enabled only while connected **or** while the poll fallback is running.
- **Offer submission.** Generate one `Idempotency-Key` per submit intent (`crypto.randomUUID()`) and reuse it on retry. Show the outlier confirmation when the server returns `offer_outlier_confirm_required`, then re-send with `confirm_outlier: true` and a **new** key.
- **Accessibility:**
  - labels on all inputs;
  - visible focus;
  - `aria-live="polite"` for standing changes, and `assertive` only for "closing in 1 minute" and "closed";
  - WCAG 2.1 AA contrast where the brand allows.

### 4.3 Canonical client routes (web pages and push deep links)

A notification's `route` field (ARCHITECTURE §11.1) is locale-free. The web prefixes `/{locale}/dashboard`; mobile maps the same path in `go_router`.

| `route` | Web page | Mobile |
|---|---|---|
| `/competitions/{id}` | `/{locale}/dashboard/competitions/{id}` | `/competitions/:id` |
| `/competitions/{id}/live` | `…/competitions/{id}/live` | `/competitions/:id/live` |
| `/competitions/{id}/qa` | `…/competitions/{id}/qa` | `/competitions/:id/qa` |
| `/billing` | `/{locale}/dashboard/billing` | `/billing` (plan status only; no purchase UI) |
| `/billing/invoices/{id}` | `/{locale}/dashboard/billing/invoices/{id}` | `/billing` (information: invoices are on the web) |
| `/integrations` | `/{locale}/dashboard/integrations` | `/home` (not available on mobile) |
| `/notifications` | `/{locale}/dashboard/notifications` | `/notifications` |

**Links in e-mails:**

- `/{locale}/invitations#t={token}` (invitation landing; SSR shell, the token is read client-side from the fragment);
- `/{locale}/auth/accept-invite#t={token}`;
- `/{locale}/auth/verify?email=` (the OTP page; no token).

**Payment return.** Send `return_url = {WEB origin}/{locale}/dashboard/billing/checkout/return`, with **no query string**. The server or gateway appends `?payment={id}`, and the page reads `payment` from the query.

### 4.4 Web tests

- **Vitest:** stores, composables and utils (money parsing and formatting, the server-time offset, the live-snapshot reducer with `v` ordering, error mapping).
- **Playwright E2E, in AR and EN:**
  - register → OTP (`OTP_FAKE_CODE`);
  - create a tender → invite → publish;
  - participant join → offer;
  - checkout through `/pay/fake` (`PAYMENT_FAKE_AUTO_APPROVE=true`);
  - award.
- **Commands:** `pnpm --dir apps/web lint`, `pnpm --dir apps/web typecheck` (`nuxt typecheck` or `vue-tsc`), `pnpm --dir apps/web test` (Vitest), `pnpm --dir apps/web test:e2e`.

---

## 5. Dart / Flutter (`apps/mobile`)

### 5.1 Language and tooling

- Dart 3, current stable Flutter. `analysis_options.yaml` includes `package:flutter_lints/flutter.yaml` plus `prefer_final_locals`, `prefer_const_constructors`, `always_declare_return_types`, `avoid_dynamic_calls`, `unawaited_futures`. `flutter analyze` must be clean.
- **Structure:** `lib/core/` (api, config, l10n, realtime, push, storage, theme, router, utils) and `lib/features/<feature>/{data,domain,presentation}`. The features are `auth`, `home`, `competitions`, `live`, `invitations`, `team`, `profile`, `billing`, `notifications`.
- **State:**
  - `flutter_bloc`, with `Cubit` for simple screens and `Bloc` for event-driven ones (live room, lists).
  - States are `sealed class XState` with subclasses `XInitial`, `XLoading`, `XLoaded`, `XFailure`.
  - Blocs never call Dio directly; they go through a repository.
- **Routing:** `go_router` with the paths of §4.3. Deep links from push use the `route` string.
- **HTTP:** `dio` with interceptors for `Authorization`, `Accept-Language` (the app locale), `X-Platform: ios|android`, `X-App-Version` (from `package_info_plus`), `X-Request-Id` and `Accept: application/json`.
  - An error interceptor maps the error envelope to `ApiException(statusCode, code, message, errors, details)`.
  - A **426** response routes to the force-update screen; **503** `maintenance` routes to the maintenance screen.
  - Base URL comes from flavour config: dev on Android is `http://10.0.2.2:8000/api/app/v1`.
- **Models:** immutable Dart classes with `fromJson`/`toJson`. JSON keys stay snake_case (`@JsonKey(name: 'amount_minor')` when using `json_serializable`). Money is `int`; timestamps are `DateTime.parse(...)` in UTC and shown in Asia/Riyadh. Unknown enum values map to an `unknown` case; they never crash.
- **Storage:** the token lives in `flutter_secure_storage` only (Android `encryptedSharedPreferences`; backups excluded). It is never logged.
- **Realtime:** behind `RealtimeClient` (abstract), implemented with a Pusher-protocol client that supports a custom host (for example `dart_pusher_channels`). The authoriser posts to `/broadcasting/auth` with the bearer header. It reconnects and resyncs as ARCHITECTURE §9.4 describes.
- **Push:**
  - behind `PushService` (abstract), with the default implementation `NoopPushService`;
  - a `FirebasePushService` using `firebase_messaging` exists but is **not wired** until a Firebase project exists (a flavour flag);
  - notification permission is requested in context (after the first join or publish), never at cold start.
- **No purchases in the app.** Plans show status only. On iOS there is no link or button to buy. Any action that needs payment shows the key `billing.managed_on_web`.
- **Offer submission:** the same idempotency, outlier and server-time rules as the web (§4.2).

### 5.2 Mobile tests

- `bloc_test` + `mocktail` for every bloc and cubit, including live-room ordering by `v`, reconnect and resync, and the idempotent retry.
- Widget tests for the auth forms (validators in AR and EN), the offer sheet, and the RTL layout smoke test.
- **Commands:** `flutter analyze`, `flutter test`.

---

## 6. Internationalisation

### 6.1 Rules

- **Arabic is the default** on every surface. Every user-facing string exists in both `ar` and `en`, with identical key sets. **There are no hard-coded UI strings, including validators, errors and dates.**
- **Digits are always Western (0–9)**, in both languages. Inputs accept Arabic-Indic digits and normalise them (scaffold `utils/digits.ts`; the same on mobile).
- **Brand voice:** no exclamation marks, no hype.

**Glossary** (use exactly):

| Arabic | English |
|---|---|
| منافسة | Competition |
| مناقصة | Tender |
| مزايدة | Auction |
| طارح المنافسة | Issuer |
| متنافس / المتنافسون | Participant(s) |
| عرض | Offer |
| العرض المتصدر | Leading offer |
| ترسية | Award |
| رسوم مغطّاة | Fees covered |
| تصريح مشاركة مغطّاة | Sponsored participation pass |
| الفئة | Category |
| نوع المنافسة | Type (tender or auction) |
| جولة العرض النهائي | Best-and-final-offer round |
| قيد التقييم | Evaluation (status `closed`) |

**Forbidden:**

- «مناقص» as a noun for people;
- the old brand names (Munaqes, مناقص as the brand, Monaqus);
- "Bid" for a competition;
- «أقل سعر» / "Lowest price" as a generic label. Use "Leading offer" and render direction-specific phrases only from `direction`.

### 6.2 Key naming

- **Canonical key:** a dot path of lower_snake_case segments, `<area>.<screen_or_feature>.<element>[.<variant>]`. Examples:
  - `competitions.create.title`
  - `competitions.create.fields.start_price.label`
  - `live.status.not_leading`
  - `errors.offer_step_not_met`
- **Area namespaces** (shared by web and mobile):
  - `common`, `nav`, `auth`, `home`, `competitions`, `rules`, `live`, `offers`, `invitations`, `qa`, `award`, `bafo`
  - `billing`, `sponsorship`, `team`, `profile`, `organization`, `notifications`, `integrations`, `vendors`
  - `errors`, `validation`, `glossary`, `landing`, `legal`
- **Direction-dependent text uses variant sub-keys** `.tender` and `.auction`: `live.status.not_leading.tender` («عرضك ليس العرض المتصدر. خفّض عرضك لتنافس.»), `live.status.not_leading.auction`. The client picks the key by `competition.direction`.
- **Web:** nested JSON in `apps/web/i18n/locales/{ar,en}.json` (keys = the dot path). Arabic plurals need the 6-form rule (`zero|one|two|few|many|other`), so register a custom `pluralRules.ar` in `i18n.config.ts` and write the plural messages with 6 pipe-separated forms.
- **Flutter:** ARB files `lib/l10n/app_ar.arb` (the template) and `app_en.arb`. **ARB key = the canonical path in lowerCamelCase**: `competitions.create.title` → `competitionsCreateTitle`. Direction variants become **one** key with an ICU `select` on `direction`, for example `"liveStatusNotLeading": "{direction, select, tender{…} auction{…} other{…}}"`. Plurals use ICU `plural`.
- **Server** (`lang/{ar,en}/<module>.php`):

  | Kind | Key |
  |---|---|
  | Errors | `<module>.errors.<code>` |
  | Validation messages | `<module>.validation.<rule>` |
  | Attributes | `<module>.attributes.<field>` |
  | Enum labels | `<module>.enums.<enum_snake>.<value>` |
  | Direction words | `competitions.direction.tender` = «مناقصة» / "Tender" |
  | Notifications | `notifications.<type with . → _>.{title, body, mail_subject, mail_intro, mail_action}` |
  | Rules summary | `competitions.rules_summary.*` |

- **Dynamic values** use named placeholders: `:name` in Laravel, `{name}` in vue-i18n and ARB. Amounts are formatted **before** insertion (§9).

---

## 7. API usage rules for clients (web and mobile)

- **Always send** `Accept-Language`, `X-Platform` and (on mobile) `X-App-Version`.
- **Render from server decisions:**
  - `permissions` (competition);
  - `access` (invitee and participant);
  - `accepting_offers`, `required_next_amount_minor` and the projection fields (live).

  **Never recompute entitlement, visibility or ranking on the client.**
- **Apply live snapshots only if `v` > the last applied `v`.** On an `offer.accepted` sequence gap, fetch `/offers/log?after_seq=`.
- **Poll payments** after a return from checkout: `GET /billing/payments/{id}` every 2 s, for up to 60 s. Then call `/verify` once.
- **Handle `details`** on documented errors (for example show `required_amount_minor`, and offer the quote on `sponsorship_payment_required`).

---

## 8. Error codes (master list)

Every error body is `{message, code, errors, details?}` (API.md §0.4). **Each code has exactly one HTTP status.** Messages come from `lang/*/errors.php` for the generic codes and from `lang/*/<module>.php` under `errors.<code>` for module codes.

### 8.1 Validation versus business codes

- **Field problems** (format, required, unique, max, exists, …) are **always** 422 `validation_failed`, with `errors` keyed by field path. Messages come from the validation lang files or `<module>.validation.*`.
- **Bulk requests** (invitations) also return `details.item_codes`, which maps the field path to a machine code: `invitation_duplicate`, `cannot_invite_own_organization`, `vendor_blocked`, `vendor_not_found`. These item codes are **not** top-level codes.
- **Import row errors** use the row codes of ARCHITECTURE §14.7: `required`, `invalid_format`, `unknown_region`, `unknown_category`, `duplicate_in_file`.
- **Release-scope refusals** (`RELEASE_SCOPE.md` §1.5): a value of a hidden feature on a visible endpoint is a field problem, 422 `validation_failed` with the shared message `errors.feature_disabled_field` on its path; a hidden endpoint is the top-level `feature_disabled` below.
- **Business rules** use the top-level codes below.

### 8.2 Generic (Platform, `lang/*/errors.php`)

| Code | HTTP | Meaning |
|---|---|---|
| `bad_request` | 400 | Malformed request |
| `unauthenticated` | 401 | Missing or invalid Sanctum token |
| `forbidden` | 403 | Missing permission |
| `not_found` | 404 | Unknown, or not visible to the caller |
| `method_not_allowed` | 405 | – |
| `not_acceptable` | 406 | – |
| `conflict` | 409 | Generic conflict (prefer a specific code) |
| `gone` | 410 | – |
| `payload_too_large` | 413 | Request over the server limit |
| `unsupported_media_type` | 415 | – |
| `session_expired` | 419 | Admin panel only |
| `validation_failed` | 422 | Field errors in `errors` |
| `app_version_unsupported` | 426 | Mobile version below the minimum (`details.min_version`) |
| `too_many_requests` | 429 | Rate limited (`Retry-After`; `details.retry_after_seconds` where given) |
| `server_error` | 500 | – |
| `service_unavailable` | 503 | – |
| `maintenance` | 503 | Maintenance mode (setting or `artisan down`) |
| `http_error` | varies | Fallback for other HTTP exceptions |
| `idempotency_key_required` | 400 | `Idempotency-Key` missing or malformed |
| `idempotency_key_reused` | 422 | The same key with a different request |
| `idempotency_request_in_progress` | 409 | The same key still processing |
| `invalid_state_transition` | 409 | The action is not allowed in the current state (`details.from`, `details.to` or `details.status`) |
| `file_type_not_allowed` | 422 | Upload extension or MIME not allowed for the purpose |
| `file_too_large` | 422 | Upload over the purpose limit |
| `feature_disabled` | 404 | The feature is not available in this release scope (`details.feature` = the flag of `features.flags`, `RELEASE_SCOPE.md` §1.5) |

### 8.3 Identity (`lang/*/identity.php`)

| Code | HTTP | Meaning |
|---|---|---|
| `invalid_credentials` | 401 | Wrong e-mail or password (never says which) |
| `email_not_verified` | 403 | Login before OTP verification (`details.otp_expires_at`) |
| `account_inactive` | 403 | User or membership not active |
| `organization_suspended` | 403 | Organization suspended by the admin |
| `otp_invalid` | 422 | Wrong code |
| `otp_expired` | 422 | Code expired or consumed |
| `otp_too_many_attempts` | 429 | 5 wrong attempts |
| `otp_resend_cooldown` | 429 | Resend within 60 s (`details.retry_after_seconds`) |
| `password_incorrect` | 422 | Current password wrong (change password, account deletion) |
| `team_invitation_invalid` | 422 | Team token unknown, used or expired |
| `seat_limit_reached` | 409 | Plan seats exhausted (`details.seats`) |
| `cannot_modify_owner` | 409 | Change or remove the owner |
| `cannot_modify_self` | 409 | Change or remove yourself |
| `account_deletion_blocked` | 409 | Open competitions or participations (`details.blockers`) |
| `account_deletion_pending` | 409 | A request already pending |
| `invitation_email_mismatch` | 422 | Registering with an invitation token for a different e-mail |

### 8.4 Competitions (`lang/*/competitions.php`)

| Code | HTTP | Meaning |
|---|---|---|
| `competition_not_editable` | 409 | Field or action not allowed in this status (`details.fields`) |
| `issuer_plan_required` | 403 | Creating or publishing without an active plan |
| `auction_not_enabled` | 403 | The organization's auction flag is off |
| `min_participants_not_met` | 422 | Too few invitations at publish (`details.required`, `details.current`) |
| `max_participants_exceeded` | 422 | Over `competitions.max_participants` |
| `live_event_capacity_reached` | 409 | Live-event cap reached at publish |
| `extend_invalid` | 422 | New close time not allowed (`details.min_new_close_at`) |
| `invitation_cutoff_passed` | 409 | Inviting after `invitation_cutoff_at` |
| `invitation_invalid` | 404 | Token unknown, revoked or draft |
| `invitation_belongs_to_another_organization` | 409 | Claiming an invitation bound to another organization |
| `join_deadline_passed` | 409 | Joining after the cutoff or in the wrong competition status |
| `already_participating` | 409 | The organization already joined through another invitation |
| `terms_not_accepted` | 422 | `accept_terms` missing |
| `not_a_participant` | 403 | Participant-only endpoint called by a non-participant |
| `comments_closed` | 409 | Q&A outside `scheduled` or `live` |
| `report_not_available` | 409 | Report requested before the first close |
| `results_not_available` | 409 | Public results before close |

### 8.5 Bidding (`lang/*/bidding.php`)

| Code | HTTP | Meaning / `details` |
|---|---|---|
| `offer_amount_invalid` | 422 | Not a positive integer |
| `offer_amount_too_large` | 422 | Above the platform maximum (`max_amount_minor`) |
| `offer_granularity` | 422 | Not a multiple of the granularity (`granularity_minor`) |
| `offer_not_accepting` | 409 | Status or phase does not accept offers (`status`, `opens_at`) |
| `offer_closed` | 409 | DB time ≥ `effective_close_at` or the BAFO cutoff (`closed_at`) |
| `offer_not_shortlisted` | 403 | BAFO offer from a non-shortlisted participant |
| `offer_bafo_already_submitted` | 409 | Second BAFO offer |
| `offer_start_price` | 422 | Violates the ceiling or opening price (`start_price_minor`) |
| `offer_step_not_met` | 422 | Does not improve enough (`required_amount_minor`) |
| `offer_bafo_worse_than_reference` | 422 | BAFO offer worse than the last offer (`reference_amount_minor`) |
| `offer_outlier_confirm_required` | 422 | Change above the outlier threshold (`change_bps`, `reference_amount_minor`) |
| `bafo_not_enabled` | 409 | Competition published without a BAFO round |
| `bafo_already_used` | 409 | A round already happened |
| `bafo_shortlist_invalid` | 422 | Shortlist ids are not joined participants with offers (`invalid_participant_ids`) |
| `award_participant_has_no_offer` | 422 | Awarding a participant without a current offer |
| `award_justification_required` | 422 | Non-leading or reserve-not-met award without a justification (`reason`) |
| `award_reserve_confirmation_required` | 422 | Reserve not met and `confirm_reserve_not_met` is false |
| `award_not_active` | 409 | ERP sync on a revoked award |

### 8.6 Billing (`lang/*/billing.php`)

| Code | HTTP | Meaning |
|---|---|---|
| `plan_required` | 403 | Joining without a plan or a sponsored pass (`details.access`) |
| `purchase_not_available_on_platform` | 403 | Checkout from `ios` or `android` |
| `billing_profile_incomplete` | 422 | Missing legal or tax data (fields in `errors`) |
| `return_url_not_allowed` | 422 | `return_url` not allow-listed |
| `plan_not_available` | 422 | Inactive or unknown plan |
| `seats_out_of_range` | 422 | Custom seats outside the bounds |
| `subscription_downgrade_not_allowed` | 409 | Buying a lower plan while one is current |
| `subscription_renewal_too_early` | 409 | Renewal outside the window (`details.renewable_from`) |
| `trial_not_available` | 409 | Trial already used or a paid history exists |
| `coupon_invalid` | 422 | Unknown, inactive or not the caller's |
| `coupon_expired` | 422 | Outside its validity |
| `coupon_not_applicable` | 422 | Wrong purpose |
| `coupon_exhausted` | 422 | No redemptions or balance left, or in use by another pending payment |
| `invoice_pdf_not_ready` | 409 | The e-invoice is not cleared yet |
| `sponsorship_not_enabled` | 403 | The organization flag or the global setting is off |
| `sponsorship_locked` | 409 | Changing the mode or cap after funding in a way that is not allowed |
| `sponsorship_payment_required` | 409 | Passes must be bought first (`details.quote`) |
| `sponsorship_already_funded` | 409 | Checkout with nothing to buy |
| `invalid_webhook` | 400 | Gateway webhook signature invalid |
| `gateway_error` | 502 | Gateway call failed |
| `gateway_not_configured` | 503 | Driver keys missing |

### 8.7 Integrations (`lang/*/integrations.php`)

| Code | HTTP | Meaning |
|---|---|---|
| `invalid_token` | 401 | Public API bearer invalid or expired |
| `invalid_client` | 401 | OAuth client authentication failed |
| `unsupported_grant_type` | 400 | Grant type other than client credentials |
| `invalid_scope` | 400 | Requested scope outside the client's scopes |
| `insufficient_scope` | 403 | Token lacks the endpoint scope (`details.required_scope`) |
| `api_access_disabled` | 403 | The organization's `api_enabled` is false |
| `external_ref_conflict` | 409 | The same ERP key is already used (`details.existing_id`) |
| `vendor_email_taken` | 409 | A vendor with this e-mail exists (`details.existing_id`) |
| `webhook_url_invalid` | 422 | Not https, private target, bad port, or credentials in the URL |

---

## 9. Formatting (display)

### 9.1 Dates and times

- **Stored and transported** in UTC (API.md §0.6). **Displayed** in Asia/Riyadh on every surface. Gregorian calendar.

| Format | Arabic | English |
|---|---|---|
| Long date | `d MMMM yyyy`, Arabic month names, Western digits (e.g. «9 نوفمبر 2026») | `d MMM yyyy` |
| Time | 12-hour `h:mm a`, where `a` = ص / م | 12-hour `h:mm a` (AM / PM) |
| Date and time | date, then `، ` and the time | date, then `, ` and the time |
| Relative (activity and notification lists, < 7 days) | «منذ 5 دقائق» | "5 minutes ago" |

- **Countdowns** use tabular figures:
  - ≥ 24 h: "N days HH:MM" («N يوم»; pluralised);
  - < 24 h: `HH:MM:SS`;
  - the last 5 minutes use the warning colour, never flashing red.
- **Machine-readable** views (logs, the offer log, PDF): `yyyy-MM-dd HH:mm:ss.SSS` in Asia/Riyadh, with a "(KSA)" suffix.

### 9.2 Money

- **Input and transport:** integer halalas.
- **Display** uses `formatMoney` (web scaffold `utils/money.ts`; the same algorithm on mobile and in PDFs):
  - Arabic: `12,500.00 ر.س`;
  - English: `SAR 12,500.00`;
  - **always 2 decimals**, comma grouping, Western digits.
- **VAT.** Prices are shown excl. VAT, with the note `common.prices_exclude_vat` («الأسعار لا تشمل ضريبة القيمة المضافة»). Checkout shows subtotal, discount, credit, VAT 15% and total.
- **User input.** Accept `12500`, `12,500.5` or Arabic-Indic digits. Parse with `parseAmountToMinor` (no floats). Reject more than 2 decimals, and reject values not on the competition granularity **before** sending (the server re-checks).
- **Percentages:** `bps / 100` with up to 2 decimals, for example `0.5%`.
- **RTL.** Amounts are always isolated with `<bdi>` (web), `Directionality` / `TextDirection.ltr` spans (Flutter) or `<bdi>` (PDF).

### 9.3 Lists and pagination in the UI

- **App lists** use page pagination with `per_page = 20`, and infinite scroll on mobile (`has_more`).
- **Empty states** have their own i18n keys: `<area>.<list>.empty`.
- **Search inputs** debounce by 300 ms and send `q`.

---

## 10. Verification commands (run the ones for your area before reporting)

| Area | Commands |
|---|---|
| API | `cd apps/api && vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G && php artisan test`. Tests migrate `bafo_test` themselves through `RefreshDatabase`. |
| API migrations and seeds (dev database `bafo`) | `php artisan migrate:fresh --seed && php artisan db:seed --class=DemoSeeder` |
| API routes | `php artisan route:list --path=api` (the names must match API.md) |
| Web | `pnpm --dir apps/web lint && pnpm --dir apps/web typecheck && pnpm --dir apps/web test` |
| Mobile | `cd apps/mobile && flutter analyze && flutter test` |
