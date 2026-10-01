# Integrations handoff (R1: public API, vendors, webhooks, import and export)

Written by the Integrations engineer on 2026-09-29. It covers ARCHITECTURE §14 (with §5.4, §8.6, §10 and §12), API.md §1.9, §2.12, §3.1, §3.3, §3.6 and §4.

## 1. Checks at hand-off

| Check | Command | Result |
|---|---|---|
| Integrations tests | `scripts/test-api.sh integrations tests/Feature/Integrations tests/Unit/Integrations` | 279 passed |
| Full suite | `scripts/test-api.sh integrations --parallel --processes=6` | 1891 of 1894 passed. The 3 failures are Platform-owned kernel tests (§5) |
| PHPStan level 6 | `vendor/bin/phpstan analyse app/Modules/Integrations --memory-limit=1G` | 0 errors |
| Pint | `vendor/bin/pint --test app/Modules/Integrations routes/*/integrations.php lang/*/integrations.php tests/*/Integrations` | clean |
| Caches | `route:cache`, `config:cache`, `event:cache` (written to scratch paths) | all succeed |
| Schedule | `php artisan schedule:list` | `integrations:dispatch-webhooks` every minute, `integrations:prune` daily |
| Demo data | Scratch database (dropped afterwards): `migrate:fresh --seed`, then `db:seed --class=DemoSeeder` | OK. The seeding of the other modules wrote 53 outbox rows through the listeners (published, closed, cancelled, not_awarded, invitation.accepted, offer.submitted, award.issued) |
| OpenAPI | `php artisan integrations:openapi --check` | The YAML matches the JSON. It was also parsed back with Ruby's YAML and is equal to the JSON |

Housekeeping: the scratch `DemoSeeder` run pushed jobs into the shared dev Redis queues. I removed exactly those 356 jobs, by their `createdAt` window. Jobs that were already there are untouched. No queue worker was running.

## 2. What exists

**Authentication (ARCH §14.2).**

- `Passport::ignoreRoutes()` is called: `/oauth/*` is gone. Tokens last 30 minutes, and `tokensCan` uses the English scope descriptions.
- `POST /api/public/v1/oauth/token` is `IssueAccessTokenController` → `Services\AccessTokenIssuer`.
  - It accepts Basic or body credentials, as form or JSON.
  - It checks the grant, then the BAFO client (active, organization active and `api_enabled`, Passport client present), then the scope subset.
  - It delegates to Passport's `AccessTokenController` with `oauth_client_id`.
  - The response is RFC 6749 plus `scope`, with `Cache-Control: no-store`.
  - Errors carry both shapes through `ShapeOAuthErrors`. That includes the 429 of `throttle:oauth-token`, because the middleware is ranked first in the router priority.
- `api.client` (`AuthenticatePublicApiClient` → `Services\PublicApiAuthenticator`):
  - It accepts an API key (prefix lookup, `hash_equals` on the sha256, not revoked or expired, same environment) or a Passport JWT (`ResourceServer`, then the client by `oauth_client_id`, with scopes = token ∩ client).
  - It sets `CurrentActor = Actor::forApiClient()` and the request attributes `api_client`, `api_scopes` and `api_client_context`.
  - It stamps `last_used_*` on the key and the client at most once per 60 s, and writes one `api_access` log line per request.
- `api.scope:<scope>` (`EnsureApiScope`) answers 403 `insufficient_scope` with `details.required_scope`.
- The limiters `public-api` (per client: GET 600/min, other methods 300/min; per organization 1500/min) and `oauth-token` (20/min per IP + `client_id`).
- `public_v1` gets `api.client`, `throttle:public-api` and `SubstituteBindings`. The provider applies them to the router both immediately and after the HTTP kernel is resolved, so the order holds in HTTP and in tests.

**Dashboard (API.md §1.9).**

- Vendors: CRUD, with DELETE archiving; `VendorPolicy` requires `competitions.create`.
- API clients: create (Passport client, secret once), update, revoke, rotate the secret; keys created (`bafo_{env}_{prefix8}_{secret32}`, shown once) and revoked.
- Webhook endpoints: CRUD, test, rotate the secret, the delivery log, redeliver; plus the event types.
- Import (template, start, show) and export (start, show).
- Middleware `integrations.access` (`integrations.manage`) and `integrations.access:api` (plus `api_enabled`, else 403 `api_access_disabled`). The policies return 404 for other organizations.

**Public API.** The 20 Integrations routes of API.md §3.1, §3.3 and §3.6, plus `GET /openapi.json`. Vendor lists use a cursor with `updated_since`. The upsert is `PUT /vendors/external/{system}/{external_id}` (the key may contain `/`). `POST` routes carry `idempotent`.

**Vendors.**

- `Actions\Vendors\{CreateVendor, UpdateVendor, ArchiveVendor, UpsertVendorByExternalRef}`.
- One e-mail per organization: 409 `vendor_email_taken` with `details.existing_id`.
- `linked_organization_id` is matched by the organization's e-mail, a member's e-mail or the CR.
- `Services\ExternalRefs` (`sync`, `put`, `findRefableId`, `present`) handles `external_ref_conflict`.

**Webhooks (§14.5, §14.6).**

- Synchronous listeners (`Write{Competition,Invitation,Offer,Award}Webhooks`) are registered **by class-string** for the 11 domain events (`IntegrationsServiceProvider::WEBHOOK_LISTENERS`). They read the §10 properties by name, so they never import the producers' classes.
- `WebhookEmitter::emit()`:
  - It writes only when an active endpoint listens.
  - `sequence` is kept per subject under a transaction-scoped advisory lock.
  - The payload is the full §4.2 envelope, and `links.object` is the public URL.
  - It queues `DispatchWebhookEvent` after commit.
- `DispatchWebhookEvent` fans an event out to the subscribed active endpoints; `webhook.test` goes to its own endpoint only.
- `DeliverWebhook` → `WebhookDeliverer`:
  - It claims the delivery with a lease, then re-checks the endpoint.
  - It runs the SSRF guard (`WebhookUrlGuard` + `HostResolver`) and pins the IP with `CURLOPT_RESOLVE`.
  - Each request uses the Standard Webhooks headers, the same bytes on every attempt, a 15 s timeout and no redirects.
  - Retries follow 5 s → 14 h, with 9 attempts in total. A 410 or 5 days of failures disables the endpoint (`failing`) and dispatches `WebhookEndpointDisabled`.
- Amounts go through Bidding's `VisibilityProjector::issuerOfferAmount()` (§7.9).
- `integrations:dispatch-webhooks` sweeps undispatched events older than 30 s and due deliveries. `integrations:prune` removes events older than 30 days, and import and export jobs older than 7 days with their files.

**Import and export (§14.7, §14.8).**

- `Services\Spreadsheets\{SpreadsheetReader, SpreadsheetWriter, XlsxDataValidation}` handle CSV (BOM, `,` or `;`) and XLSX, with the formula guard.
- `VendorImportTemplate`: the XLSX has the sheets `vendors`, `Read me` (AR and EN) and `Lists`, and dropdowns injected into the sheet XML.
- `VendorImportProcessor` produces the five row codes, applies the upsert by ERP key or e-mail, writes the errors file (original columns plus `errors`) and `errors_preview`.
- `ExportBuilder` builds results, offer log, awards and vendors. It uses the issuer projection through `VisibilityProjector::amountsHiddenFromIssuer()`, decimal SAR, Riyadh times and a vendors export that can be re-imported.
- Jobs: `ProcessVendorImport` and `ProcessExport`, on the `imports` queue. Their `$timeout` is 85 s, below the Redis `retry_after` of 90 s, and `failed()` marks the job row `failed` if a worker gives up. Events: `ImportFinished`, `ExportFinished`.
- The file rules for `import_source`, `import_errors` and `export`: members of the owning organization with `integrations.manage`.

**Identity reactions.** `LinkVendorsToOrganization` (`EmailVerified`) and `RevokeOrganizationApiAccess` (`AccountDeleted` with scope organization) are queued after commit.

**OpenAPI and Postman (§14.9).**

- The source is `resources/openapi/public-v1.json`: OpenAPI 3.1, 42 operations, the top-level `webhooks` for the 13 types, `oauth2` and `apiKey` schemes.
- `resources/openapi/public-v1.yaml` is rendered by `php artisan integrations:openapi`.
- Served at `GET /api/public/v1/openapi.yaml` (`public.v1.openapi`) and `openapi.json`. The Scalar reference is at `GET /docs/api`.
- The Postman collection is `resources/openapi/bafo-public-v1.postman_collection.json`, with a copy in `docs/build/`. It has 42 requests and stores the token after the first request.

**Admin Actions (§16).** `SetApiClientSuspension::handle($client, bool $suspended, Actor)` and `RevokeApiClient::handle($client, Actor)`.

**Demo.** `IntegrationsDemoSeeder`. See `docs/build/DEMO.md`.

## 3. Using the module (other owners)

- **Public controllers in other modules.** The `public_v1` group has already authenticated. Read the tenant with `App\Modules\Integrations\Data\ApiClientContext::from($request)->organizationId()` or `CurrentActor::get()->organizationId`. Declare `->middleware('api.scope:<scope>')`, and add `idempotent` on POSTs.
- **Webhooks are automatic.** The listeners read these event properties (§10):
  - `competition`;
  - `extension`;
  - `invitation` and `participant`;
  - `offer`, `competition` (optional) and `context->isFirstOfferOfParticipant` (without a context, the ledger decides);
  - `award` and `competition` (optional).

  Set the state before dispatching:
  - `closed_at` before `CompetitionClosed`;
  - `cancel_reason_id` and `cancel_note` before `CompetitionCancelled`;
  - `not_awarded_*` before `CompetitionClosedWithoutAward`;
  - `offers_opened_at` before `OffersUnsealed`;
  - `revoked_at` and `revoke_reason` before `AwardRevoked`;
  - the new `effective_close_at` and `extension_count` before `CompetitionExtended`.

  `competition.closed` reads `participants_with_offers` and `accepted_offer_count` from `competition_live_states`.
- **ERP keys.** Competitions has its own `CompetitionExternalRefs`. `App\Modules\Integrations\Services\ExternalRefs` (`sync`, `put`, `present`) is available if you want a single writer; the conflict answer is 409 `external_ref_conflict` with `details.existing_id`.
- **Vendor summary** `{"id", "external_refs"}`: `VendorDirectory::summary($directory->forCounterparty($issuerOrgId, $invitation->vendor_id, $participantOrgId))`.
- **Notifications.** The events are `Integrations\Events\WebhookEndpointDisabled {endpoint}`, `ImportFinished {importJob}` and `ExportFinished {exportJob}`.
- **Web (W36–W42).**
  - The shapes are API.md §2.12.
  - The import and export responses are 202 with the job.
  - The template is `GET …/imports/templates/vendors?format=csv|xlsx` (a file stream).
  - Row errors carry the spreadsheet row number, where the header is row 1.
  - The reference page is `{API origin}/docs/api`. The Postman file is `docs/build/bafo-public-v1.postman_collection.json`.

## 4. Contract gaps (choices made; marked `CONTRACT-GAP` in code)

1. **OpenAPI source.** §14.9 names `public-v1.yaml`, but no YAML parser is installed (`symfony/yaml` is not in `composer.json`). So the hand-written source is `public-v1.json`, and the YAML is generated from it. A test keeps the two in sync. `GET /openapi.json` is added next to `openapi.yaml`, and the task asked for it.
2. **Postman.** §14.9 puts the file in `resources/openapi/`, while the task asked for `docs/build/`. The file is in both places, and a test checks that they are identical. `resources/openapi/` is the canonical copy.
3. **`api_enabled` false on the public API.** It returns 403 `api_access_disabled` (API.md §0.4 lists that code for public v1), not 401.
4. **API keys of the other environment.** `bafo_live_…` on a test deployment, or the reverse, returns 401 `invalid_token`.
5. **Token endpoint.**
   - A missing `grant_type` returns `unsupported_grant_type`.
   - Other league errors map to `invalid_client`.
   - A Basic-auth failure sends `WWW-Authenticate: Basic`.
   - There is no FormRequest, because of the OAuth error shape.
6. **`links.object`.** Invitations and offers have no single-object public GET, so they link to the competition's invitations or offers list. `webhook.test` links to the endpoint resource.
7. **Webhook body.** JSONB reorders keys, so the envelope keys are put back in §4.2 order when sending. `data.object` keeps JSONB's order. The bytes are the same on every attempt.
8. **Delivery to a disabled or deleted endpoint.** The delivery becomes `failed` with `last_error = endpoint_disabled`, and no attempt is counted. `blocked_target` counts as an attempt and is terminal.
9. **Redeliver** is allowed from `failed` only (§6.6). Other states return 409 `invalid_state_transition` with `details.from` and `details.to`.
10. **Public delivery log.** It is newest first (cursor on `id`), like the dashboard, rather than `updated_at, id`.
11. **Vendors.**
    - The app list hides archived vendors unless `status` is given.
    - The upsert keeps `status` and `notes` when they are omitted.
    - An unknown ERP key with a known e-mail attaches the key to that vendor. The import does the same.
    - Linking also matches a member's e-mail.
12. **Import.**
    - Row numbers are spreadsheet rows.
    - Only the columns present in the file are updated.
    - The errors file holds the error rows only.
    - A missing `name` or `email` column, an empty file or more than 10 000 rows fails the job.
    - The extra row code is `vendor_email_taken`, for an ERP-key match whose e-mail belongs to another vendor.
13. **Export.**
    - Times are Asia/Riyadh, with "(KSA)" in the headers.
    - `vat_rate` is written as "15%".
    - Booleans are Y/N.
    - The awards `from` and `to` are Riyadh calendar days, inclusive.
    - Exports run on the `imports` queue.
14. **Prune.** `import_jobs.source_file_id` is a NOT NULL restrict FK, so expired import and export jobs are deleted together with their files.
15. **New enums**, all labelled in AR and EN:
    - `WebhookEventType` (the 13-type catalogue);
    - `ImportRowErrorCode`;
    - `ApiAuthMethod`.
16. **Demo credentials.** They are constants of `IntegrationsDemoSeeder`, because `DemoSeeder` is Platform-owned. They are listed in DEMO.md.
17. **Embedded shapes.** `VendorResource` renders the §2.4 Region and Category shapes and the §2.2 OrganizationSummary itself, until the Catalog and Identity resources are available to embed.

## 5. Requests to other owners

**Platform**

- Three kernel tests fail because of the module middleware the contract requires. I did not edit them.
  - `tests/Feature/Platform/IdempotentRequestTest.php` ("scopes keys of API clients separately from users") and `tests/Feature/Support/ApiConventionsTest.php` ("answers the public api without meta.server_time") register ad-hoc `public_v1` routes without a credential, so `api.client` answers 401 `invalid_token`.
    - Option 1: add `->withoutMiddleware([\App\Modules\Integrations\Http\Middleware\AuthenticatePublicApiClient::class, 'throttle:public-api'])` to those test routes.
    - Option 2: send a real key. Use `Tests\Support\Integrations\IntegrationsFixtures::apiKey()` and the `bearer()` headers.
  - `tests/Feature/Platform/KernelMiddlewareTest.php` ("builds app_v1 in the contract order") now also sees Identity's `EnsureAccountActive`.
- `apps/api/CLAUDE.md` asks agents to install `laravel/boost`. I did not do it, for the same reason as the foundation check: it is outside the contract.
- `.env.example` already has `API_KEY_ENV=test` and `WEBHOOKS_ALLOW_PRIVATE_TARGETS=true`. No new keys are needed.
- Tests write the `api_access` log to `storage/logs`. Consider a `null` channel in `phpunit.xml` if that is unwanted.
- Redis `retry_after` is 90 s. If large imports or exports need longer than 85 s, raise `REDIS_QUEUE_RETRY_AFTER` for the `low` supervisor, then raise the job `$timeout` with it.

**Bidding**

- Done, as you asked:
  - `offer.*` webhook amounts use `VisibilityProjector::issuerOfferAmount()`, and exports use `amountsHiddenFromIssuer()`;
  - the `StoreExportRequest::format()` fatal is fixed (it is now `exportFormat()`);
  - your 5 public operations are in the OpenAPI document.
- The vendors cursor is on `vendors.updated_at` (precision 0), so the microsecond issue does not apply here.

**Competitions**

- Consider `Integrations\Services\ExternalRefs` for `external_refs` writes (§3). This is optional.

**Admin**

- API clients and webhook endpoints are read-only per organization. The actions are `SetApiClientSuspension` (suspend and reactivate) and `RevokeApiClient`.

**Web**

- Everything in §3 "Web".

## 6. Files

- `apps/api/app/Modules/Integrations/**`, including `config.php`, `Routes/web.php`, `Resources/views/docs.blade.php` and `Database/Seeders/IntegrationsDemoSeeder.php`.
- `apps/api/routes/{app_v1,public_v1}/integrations.php` and `apps/api/lang/{ar,en}/integrations.php`.
- `apps/api/resources/openapi/{public-v1.json, public-v1.yaml, bafo-public-v1.postman_collection.json}` and `docs/build/bafo-public-v1.postman_collection.json`.
- `apps/api/tests/{Feature,Unit,Support}/Integrations/**`.
