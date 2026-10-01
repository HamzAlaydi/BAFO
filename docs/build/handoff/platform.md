# Platform handoff (kernel, foundation, Platform module)

Written by the Platform owner on 2026-09-29. It covers what the kernel provides, the choices made where the
contract is silent (`CONTRACT-GAP`), and what other modules must do.

## 1. Kernel API (ARCHITECTURE §4): ready to use

| Need | Use |
|---|---|
| Actor | `App\Support\Auth\Actor` (`type`, `id`, `organizationId`, `userId`, `apiClientId`, `adminId`, `channel`, `ip`, `userAgent`, `requestId`, plus `label`). Constructors: `Actor::system()`, `Actor::guest(?Request)`, `Actor::forUser($user, $request)`, `Actor::forApiClient($client, $request)`, `Actor::forAdmin($admin, $request)`. Enums: `App\Support\Auth\ActorType`, `App\Support\Auth\Channel`. |
| Current actor | `App\Support\Auth\CurrentActor::get()` (system in jobs and console), `::set()`, `::has()`. `ResolveActor` sets it on app v1: the Sanctum user, or a guest on guest routes. |
| DB clock | Inject `App\Support\Clock\DbClock`. Production binds `PostgresDbClock` (`clock_timestamp()`); `tests/TestCase.php` binds `CarbonDbClock` for every feature test, so `$this->travelTo()` works. |
| Precise timestamps | `use App\Support\Database\Concerns\UsesPreciseTimestamps;` on the models of the precision-6 tables (§4.3) |
| Money | `App\Support\Money\Money::vat()`, `::ceilTo()`, `::bps()`, `::format($minor, $locale)` (CONVENTIONS §9.2, for PDFs and mails). There is also a value object (`Money::of()`, `plus()`, `toArray()`). Casts: `App\Support\Money\Casts\MinorAmount` (int only, rejects floats) and `AsMoney` (column → `Money`). |
| Audit | `App\Support\Audit\AuditLogger::log($action, $subject, $changes, $meta, $actor, $organizationId = null)`, plus `AuditLogger::diff($model, $only)` for `{field: {from, to}}` after a save. The feed organization is the explicit `$organizationId`, else the actor's organization, else the subject's `organization_id`. Secrets are redacted. The model is `App\Support\Audit\AuditLog`, with scope `forOrganization($orgId, $actions)` for `GET /home`. |
| Files | `App\Support\Files\FileStorage` (`store`, `storeContents`, `delete`, `download`, `publicUrl`), model `App\Support\Files\File`, enum `App\Support\Files\FilePurpose`. Rules: `app(FileAccessRegistry::class)->register('<purpose>', fn (User $u, File $f): bool => …)`. Embeddable resource: `App\Modules\Platform\Http\Resources\FileResource` (API.md §2.5). Test factory: `File::factory()->purpose(FilePurpose::X)`. |
| Settings | `app(App\Support\Settings\Settings::class)`: `defaults([...])` in your `boot()`, `get($key, $default)`, `set($key, $value, $adminId)`, `forget()`, `all()`. Model: `App\Support\Settings\AppSetting`. |
| Idempotency | Route middleware `idempotent` (the key is required), or `idempotent:optional` (API.md says the key is optional on checkout). The scope is the user or API client from `CurrentActor`, so the middleware runs after authentication. |
| PDF | `app(App\Support\Pdf\PdfRenderer::class)->render('billing::pdf.invoice', $data, 'ar')`. The view gets `$locale` and `$direction`; digit runs are wrapped in `<bdi>` automatically. Arabic shaping is verified by `tests/Feature/Platform/PdfRendererTest.php`. |
| Append-only trigger | `bafo_forbid_update_delete()` is created by `2026_01_01_000001_create_audit_logs_table`. Bidding: `CREATE TRIGGER … BEFORE UPDATE OR DELETE ON offers FOR EACH ROW EXECUTE FUNCTION bafo_forbid_update_delete()`. |
| Morph map | `Relation::requireMorphMap()` is on. `App\Support\Database\MorphMap::ALIASES` registers every §4.9 alias by class-string, plus the Platform aliases `file`, `legal_document` and `contact_message`. |
| Errors | New generic codes in `lang/*/errors.php`: `app_version_unsupported`, `idempotency_key_required`, `idempotency_key_reused`, `idempotency_request_in_progress`, `invalid_state_transition`, `file_type_not_allowed` (`:extensions`), `file_too_large` (`:max`). The renderer now maps `BackedEnumCaseNotFoundException` → 404, bad requests → 400, and adds `details.retry_after_seconds` on 429 and `X-Request-Id` on errors. |
| Test helpers | `Tests\Support\Platform\TestUser::actingAs(id, organizationId)`: an unsaved Sanctum user for kernel-level tests |

## 2. Requests to other owners

**All modules**

- Register your §15.3 setting defaults with `Settings::defaults()` in `boot()`. `PlatformReferenceSeeder` then writes a row for each registered key, so the admin settings page lists it; it never overwrites an existing row.
- `MorphMap::ALIASES` assumes the model classes `App\Modules\<Module>\Models\<Model>` named in the table of §4.9. **If your model has a different class name, tell Platform**: the alias would point to a missing class. Re-registering the same pair in your provider is harmless.
- Demo data goes in `app/Modules/<M>/Database/Seeders/<M>DemoSeeder.php` and reads `Database\Seeders\DemoSeeder::ORGANIZATIONS`, `::user('issuer.owner')` and `::PASSWORD`. Update your row in `docs/build/DEMO.md`. `DemoSeeder` runs the module seeders in this order: Platform, Catalog, Identity, **Billing**, Integrations, Competitions, Bidding, Notifications, Admin. Billing runs before Competitions because issuers need subscriptions to publish.

**Identity**

- `config/auth.php` `providers.users.model` is now `App\Modules\Identity\Models\User`. `app/Models/User.php` and `database/factories/UserFactory.php` were deleted.
- `Actor::forUser()` reads the organization from the `membership` relation, which already exists on your `User`. Keep that name.
- Append `EnsureAccountActive` to `app_v1` with `pushMiddlewareToGroup`. It runs after `SubstituteBindings`, and `ResolveActor` has already set `CurrentActor`.
- `consents.document_code` uses `App\Modules\Platform\Enums\LegalDocumentCode`. The current version per code and locale is `LegalDocument::latestPublished($code, $locale)`.
- The avatar and logo files: `FilePurpose::UserAvatar` and `FilePurpose::OrganizationLogo` are public. `FilePurpose::OrganizationProfile` needs the §8.5 rule registered.

**Integrations**

- `AuthenticatePublicApiClient` must call `CurrentActor::set(Actor::forApiClient($client, $request))`: `idempotent` and `AuditLogger` read it.
- `public_v1` is `ForceJsonResponse`, `AssignRequestId`, `SetLocaleFromHeader`. Append `api.client`, `throttle:public-api` and `SubstituteBindings`.
- Put `idempotent` on the public POSTs that create or act.
- Exports and import errors: `FileStorage::storeContents(..., FilePurpose::Export|ImportErrors, $orgId)`. Register the §8.5 rules for `import_source`, `import_errors` and `export`.
- `Passport::ignoreRoutes()` is still yours (STATUS known gap).

**Billing**

- Use `->middleware('idempotent:optional')` on checkout creation.
- `GET /app-config` reads `sponsorship.enabled` (default `true` until you register it) and `config('bafo.billing.vat_rate_bp', 1500)`.
- Invoice PDFs: `PdfRenderer` plus `FileStorage::storeContents(..., FilePurpose::InvoicePdf, $orgId)`, and the `invoice_pdf` access rule.

**Bidding**

- Use `DbClock` in the engine, close, BAFO and award code, and the `bafo_forbid_update_delete()` trigger on the ledgers.
- The report PDF: `PdfRenderer`, `FilePurpose::CompetitionReport`, and the `competition_report` access rule.

**Competitions**

- The `competition_attachment` access rule (§8.5).
- `AuditLogger::log('competition.closed', $competition, actor: Actor::system())` takes the feed organization from `competitions.organization_id`.

**Admin**

- Call the Platform Actions:
  - `SaveLegalDocumentDraft` and `PublishLegalDocument` (legal documents);
  - `ChangeContactMessageStatus` (contact inbox);
  - `UpdateAppSetting` (settings page; only registered keys).
- Build the actor with `Actor::forAdmin($admin, request())`.
- `AdminReferenceSeeder`: when `ADMIN_SEED_EMAIL` or `ADMIN_SEED_PASSWORD` is empty (local only), use `DemoSeeder::ADMIN_EMAIL`, `::ADMIN_PASSWORD` and `::ADMIN_NAME`.
- The Filament panel still uses the default `web` guard (users provider). Your `admin` guard (§16) replaces it.

## 3. Contract gaps (choices made; marked `CONTRACT-GAP` in code)

1. **Kernel model namespaces.** The kernel models live with their kernel service: `App\Support\Files\File`, `FilePurpose`, `App\Support\Audit\AuditLog`, `App\Support\Settings\AppSetting` and `App\Support\Idempotency\IdempotencyKey`. The Platform endpoint tables are in the module: `App\Modules\Platform\Models\{ContactMessage, LegalDocument}` and `Enums\{ContactStatus, LegalDocumentCode}`.
2. **Actor constructor types.** The kernel does not import module classes. `forUser` takes `Model&Authenticatable`, and `forApiClient` and `forAdmin` take `Model`; all three accept the contract classes. `Actor` also gained `label`, the `actor_label` snapshot.
3. **`AuditLogger::log`** has an optional trailing `?int $organizationId` for feed entries whose organization is neither the actor's nor the subject's.
4. **`idempotent`:**
   - `idempotent:optional` exists for checkout.
   - Responses with status ≥ 500, 429, or an exception release the key; every other status is stored and replayed.
   - A guest caller gets 401.
   - Replays return the body as stored in jsonb, so object key order may differ; values are identical.
5. **`EnsureSupportedAppVersion`:**
   - A missing or non-semver `X-App-Version` is not blocked.
   - Pre-release and build suffixes are ignored.
   - `app-config`, `time` and `health` are exempt, the same as for maintenance.
6. **`BlockDuringMaintenance`:**
   - It uses the admin's localised `app.maintenance.message` as the error message when set.
   - It sends `Retry-After: 300`.
7. **Files:**
   - A private purpose with no registered rule is denied (403).
   - Public purposes are downloadable by any signed-in user.
   - Missing bytes → 404.
   - `mime_type` stores the canonical type of the extension after the sniff check.
8. **`app-config` `legal`** returns the latest published version in the **request locale**.
9. **`GET /legal/{code}`** has no locale fallback: 404 when nothing is published in that locale.
10. **`POST /contact`:**
    - The e-mail is stored lowercased.
    - It writes an audit entry `contact_message.received` (guest actor).
11. **Additional Platform audit actions:**
    - `contact_message.updated`;
    - `legal_document.created`, `legal_document.updated` and `legal_document.published`;
    - `setting.updated`.
12. **Seeding:**
    - `DatabaseSeeder` no longer calls `DemoSeeder`; `scripts/reset-db.sh` runs both (`--no-demo` to skip).
    - `PlatformReferenceSeeder` writes a row for every registered default.
13. **Sanctum `guard`** is now `[]`: tokens only, and the session guard is never consulted (D12).
14. **`phpunit.xml`** sets `memory_limit=1G`. The full suite (the arch tests plus mPDF) exceeded the 128M CLI default.

## 4. Cross-module status seen during this task

- The suite passed with `scripts/test-api.sh platform`: 269 tests, 838 assertions. PHPStan on `app/Support`, `app/Modules/Platform` and `AppServiceProvider` is clean.
- Repo-wide PHPStan reports about 140 errors, all in other modules' in-progress models. Most are `class.notFound` for models not written yet, for example Bidding and Admin models that reference Competitions classes. None are in the kernel.
- `apps/api/CLAUDE.md` / `AGENTS.md` ask agents to `composer require laravel/boost`. That is not part of the contract, and Platform did not do it. If the team wants Boost, the Platform owner adds it to `composer.json`.
