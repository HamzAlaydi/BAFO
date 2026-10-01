# Backend integration QA handoff

Written by the backend integration QA lead on 2026-09-29, after every backend module reported done.
It lists the handoff requests resolved, the defects fixed, the end-to-end test, the stack check, and
what is left to other owners or to the user.

## 1. Checks

| Check | Command | Result |
|---|---|---|
| Full suite, parallel | `scripts/test-api.sh qa --parallel --processes=8` | 2085 passed (14081 assertions), about 16 s |
| Full suite, serial | `scripts/test-api.sh qa` (run twice) | 2085 passed both times, about 75 s |
| End-to-end scenarios | `scripts/test-api.sh qa tests/Feature/E2E/FullFlowTest.php` | 2 passed (532 assertions) |
| PHPStan level 6 | `vendor/bin/phpstan analyse --memory-limit=1G` (all of `app/`) | 0 errors |
| Pint | `vendor/bin/pint --test` | clean |
| Caches | `config:cache`, `route:cache`, `event:cache` written to scratch paths | all succeed; 268 routes (128 app v1, 43 public v1) |
| Schedule | `php artisan schedule:list` | the 13 commands of ARCHITECTURE §12 |
| OpenAPI coverage | every public v1 route against `resources/openapi/public-v1.json` | all 42 operations present (only `GET /openapi.json` itself is not listed) |
| Stack | `php artisan serve` (8000), `reverb:start` (8085), `queue:work`; see §4 | up, checked, stopped |

## 2. Handoff requests resolved

| From → to | Request | Resolution |
|---|---|---|
| Identity, Integrations, Bidding, Competitions, Admin → Platform | `KernelMiddlewareTest`, `IdempotentRequestTest`, `ApiConventionsTest` fail on the middleware the modules append | The app_v1 test compares the first nine entries. The two public_v1 test routes exclude the module part of the group (`withoutMiddleware(array_slice(public_v1, 3))`), so the kernel tests import no module class. |
| Billing → Identity | Remove the `HANDOFF-STUB` fallback now that `AccessPolicy` is bound; align `trial_available` with `StartTrial` | `Identity\Services\Entitlements` now takes `AccessPolicy` by injection (no fallback). `trialAvailable()` = `trial_used_at` null, no activated paid subscription, and no current subscription: the rule of `StartTrial` and `GET /billing/subscription`. |
| Competitions → Bidding | A cancel during a BAFO round (T9) leaves the round `running` | New `Bidding\Listeners\EndCancelledBafoRound` (queued on `live`, after commit) on `CompetitionCancelled` calls the idempotent `FinishBafoRound`, which ends the round without a transition. Test in `tests/Feature/Bidding/BafoRoundTest.php`. |
| Notifications → Identity, Competitions | Put user-entered values of token mails in HTML paragraphs, not Markdown lines | `competitions::mail.invitation`, `identity::mail.team-invitation` and `identity::mail.account-deletion-scheduled` now render names, titles and references in `<h1>`/`<p>`. Before, a competition title such as `[Pay here](https://…)` became a link in the invitation mail. Two tests cover it. |
| Billing → Platform | Require `chillerlan/php-qrcode` directly | `composer require chillerlan/php-qrcode:^5.0` (already installed at 5.0.5; only `composer.json` and the lock hash changed). |
| Billing → all modules | Tests that complete a payment should fake the private disk | `tests/TestCase.php` fakes the `private` and `public` disks for every feature test. Before, the suite wrote report and invoice PDFs into `storage/app/private`. |
| Integrations → Platform | Tests write the `api_access` log to `storage/logs` | `tests/TestCase.php` discards the default log channel and the `api_access` and `push` channels (a test that checks a channel installs its own handler). No test writes to `storage/` any more. |
| Integrations → Platform | Redis `retry_after` 90 s | Raised to 150 s in `config/queue.php`: the `low` Horizon supervisor has a 120 s timeout, so a slow PDF job could be handed to a second worker. The import and export jobs keep `$timeout = 85`. |
| Admin → Platform | Production must set `ADMIN_MFA_REQUIRED=true` | Comment added in `.env.example`. |
| Competitions → Bidding | A `BiddingDemoSeeder` should only add offers to live competitions | Not added: `CompetitionsDemoSeeder` already places every offer, the BAFO round and the award through Bidding's Actions. DEMO.md's Bidding row now says so. |

**Already done by their owners (checked):** `StoreExportRequest::exportFormat()`; `DbAccessPolicy::coverageFor()` loads missing competitions in one query; `bindKnownOrganizations()` before `reserveForPublish()`; `OffersUnsealed` only from `finalizeLiveBidding()`, after `offers_opened_at`; `AwardRevoked` after `revoked_at`/`revoke_reason`; `SponsorshipSettled` after `unused_count`; Q&A authors through `VisibilityProjector::commentAuthor()`; webhook and export amounts through the projector; the Competitions and Bidding public operations in the OpenAPI document; the listeners `AttachPendingInvitations`, `LinkVendorsToOrganization` (EmailVerified), `RemoveDeviceTokens`, `RevokeOrganizationApiAccess` (AccountDeleted); the Admin panel calls of every module Action; no `cursorPaginate()` on a microsecond cursor remains (vendors use a second-precision column, deliveries the id).

**Other defect fixed:** `tests/Feature/Identity/AdminActionsTest.php` compared audit actions without `ORDER BY`; it failed in one serial run.

## 3. End-to-end test (`tests/Feature/E2E/FullFlowTest.php`, group `e2e`)

Everything goes through HTTP, except the admin's feature flags (Identity Action with an admin actor),
the clock (`travelTo`) and `competitions:tick`. Queues are synchronous, so the close job, report
job, invoices and webhook deliveries run inline. Webhooks go to `Http::fake` with a fake DNS
answer and the SSRF guard on; OAuth uses real Passport keys.

- **Auction:** register + OTP + login (refused before verification, wrong password 401); plan required to create; subscription through the hosted fake page (approve → 303 to `return_url?payment=`, poll, verify); webhook endpoint `*`; two suppliers register; draft auction, sponsorship `selected`, invitations (A sponsored); publish refused with the quote; pass paid on the fake page, which publishes; A (no plan) joins on the pass (`sponsored_pass`); B refused with `plan_required`, subscribes, joins (`plan`); tick opens; opening price, granularity, rate limit, absolute step on own offer; **no leak** with `show_prices = false` across live, detail, list and my-offers, in both directions; anti-sniping in the last 30 s (+120 s); a tie after the original close is accepted and does not extend; `offer_closed` at the extended close; tick closes; award; results per participant; report PDF (issuer 200, participant 403); two cleared invoices with PDFs; every webhook signature verified with the API.md §4.3 algorithm; the ERP creates an API client, gets a client-credentials token and reads the award (and gets `insufficient_scope` elsewhere).
- **Tender:** the same core path with a trial participant, a reserve, a 1% step, an initial phase (standings hidden, must beat own) and a 30-minute final window (must beat the leading offer), prices and ranks shown without identities or the reserve, anti-sniping, close, reserve met, award with the amount published, report, invoices, webhooks limited to the subscribed types, and the award with VAT through OAuth.

## 4. Stack check (dev `.env`, 2026-09-29)

Started `php artisan serve` on 8000, `reverb:start` on 8085 and one `queue:work` (see §5 for the
queues), then stopped all three. Another agent's server on 8310 was left running.

| Check | Result |
|---|---|
| `GET /api/app/v1/health` | 200, database, redis and queue `ok` |
| `GET /time`, `/app-config`, `/lookups`, `/plans` | 200 with the §0 envelope (`meta.server_time`); `/lookups` and `/plans` are empty because the dev database has no seed |
| `GET /me`, `/broadcasting/auth` without a token | 401 `unauthenticated` |
| `GET /api/public/v1/client` without a key; `POST /oauth/token` with a bad client | 401 `invalid_token`; 401 `invalid_client` (OAuth shape) |
| `GET /admin` | 302 to `/admin/login`, which renders (200, `lang="ar"`, `dir="rtl"`, e-mail field) |
| `GET /docs/api`, `/api/public/v1/openapi.yaml` | 200 Scalar page; 200 `application/yaml` (169 KB) |
| `GET /pay/fake/{unknown}` | 404. The page with a real payment is covered by the E2E test (200, then approve → 303); it needs a seeded plan to try by hand |
| Reverb WebSocket handshake on 8085 | 101 and `pusher:connection_established` |

Not checked on the running stack: an admin sign-in and the fake page with a real payment, because
the dev database has no admin and no plans (see §5).

## 5. Needs the user

- **Reseed the dev database.** `scripts/reset-db.sh --yes` (task step 3) was refused by the
  permission classifier and was not run. `bafo` is migrated but empty. DEMO.md says so.
- **Stale jobs in the dev Redis queues.** 7 jobs on `notifications` and 3 on `billing`, queued at
  2026-09-29 15:52 UTC by an earlier seed of another database state. Clearing them was refused, so
  the QA worker left those two queues alone. Clear them before a reseed, or they will run against
  the new rows (a few duplicate demo notifications; invoice issuing is idempotent).
- **Leftover files.** `storage/app/private/{competition_report,invoice_pdf,competition_attachment}`
  hold PDFs from earlier test runs and other agents' seeds (the suite no longer writes there). They
  were not deleted, because other agents' databases (e.g. `bafo_issuer_web`) may reference some.

## 6. Left to owners (optional or architect decisions)

- **Architect (contract gap):** `FileResource.download_path` is the app v1 path, and API.md §3 has no public file download, so ERP clients cannot fetch attachment bytes. Either add a public download or document that attachments are metadata only on the public API.
- **Architect (contract gap, from Identity):** a deleted organization keeps its CR number, so the company cannot register again.
- **Platform (optional refactor):** one shared keyset cursor in `app/Support` for `Competitions\Queries\KeysetCursor` and `Bidding\Support\UpdatedAtCursor`.
- **Competitions (optional):** use `Integrations\Services\ExternalRefs` as the single writer of `external_refs`.
- **Competitions (minor):** `Queries\HomeStats` computes the `trial_available` alert with its own paid-history query (statuses) while Identity and Billing use `activated_at`; the results differ only for a paid subscription that was activated and later `cancelled`.
- **Billing / Catalog / Platform (optional, from Admin):** Actions for plan, coupon and lookup writes; `DeleteLegalDocumentDraft`.
- **Admin, Identity, Competitions handoffs** mention the kernel test failures and the Identity fallback; both are resolved here.
