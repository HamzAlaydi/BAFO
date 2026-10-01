# Bidding handoff (engine, visibility, realtime, BAFO, award, report, public offers/awards)

Written by the Bidding engineer on 2026-09-29. It covers what the module provides, the choices made
where the contract is silent (`CONTRACT-GAP` in code), and what other owners need to do.

## 1. Checks

| Check | Command | Result |
|---|---|---|
| Bidding tests | `scripts/test-api.sh bidding tests/Unit/Bidding tests/Feature/Bidding` | 197 passed (1,741 assertions), about 10 s |
| PHPStan level 6 | `vendor/bin/phpstan analyse app/Modules/Bidding --memory-limit=1G` | 0 errors. On all of `app/`, the only errors are 10 in Integrations. |
| Pint | `vendor/bin/pint --test app/Modules/Bidding routes/*/bidding.php lang/*/bidding.php tests/*/Bidding` | clean |
| Routes | `php artisan route:list` | 11 app v1 and 5 public v1 routes; every name matches API.md §1.6 and §3.5 |
| Enum labels | `scripts/test-api.sh bidding tests/Unit/Schema/EnumsTest.php` | passes with the new `LiveChangeKind` |
| Full suite | `scripts/test-api.sh bidding` | Does not finish today, because of other modules' in-progress code (§6). Without `tests/Unit/ArchTest.php`, 3 failures remain, none in Bidding. |

## 2. What exists

**Contract.** `Contracts\BiddingEngine` is bound as a singleton to `Services\BiddingEngineService`. It implements the exact §3.6 signatures:

- `finalizeLiveBidding()` re-ranks, syncs the live state and bumps the version. For a sealed competition it also dispatches `OffersUnsealed` (see §5).
- `snapshotFor()` maps Competitions' `Viewer`: issuer and api_client get the issuer snapshot; a participant gets its own; an invitee or a draft gets null.
- `issuerLeadingAmount()` is null while sealed and locked.
- `participantsWithOffersCount()`.

**Engine (§7.4, `Actions\SubmitOffer`).** The steps run in the contract order:

- **Pre-checks, in the controller.** Permission (403), then `Idempotency-Key` (400), then visibility (404), then participant (403 `not_a_participant`). The `{competition}` parameter is resolved by hand so that this order holds.
- **Replay.** The ledger row is checked first, then the rejection cached for 24 h.
- **Rate limit.** `Cache::add` per participant.
- **Syntax.** Positive integer, maximum amount, granularity.
- **In the transaction** (one attempt):
  - `SELECT … FOR UPDATE` on `competitions`, then `DbClock::now()` after the lock;
  - a replay check under the lock (for double-clicks);
  - the status and stage checks (§7.3);
  - start price, stage bound and outlier guard;
  - the ledger insert with `seq`, `prev_hash` and `hash` (`Offer::hashFor`);
  - the standing, `Ranking::recompute`, anti-sniping through `CompetitionTimingService::extend(kind auto)` and the live state (version++);
  - `OfferAccepted`.
- **Rejections in steps 10–15** are written to `offer_rejections` outside the transaction (with `db_time` and the stage), cached, and re-thrown.

Supporting services:

- `Services\OfferRules`: the direction sign, the step rounding of §7.5, the outlier math and the improvement bps.
- `Services\Ranking`: the §7.6 SQL. It writes only the rows that change and returns the changed set.
- `Services\LiveStateManager`: `firstOrCreate` under the lock, `syncFromStandings`, and the atomic `bumpVersion()` (an `INSERT … ON CONFLICT … RETURNING` upsert).

**VisibilityProjector (`Services\VisibilityProjector`).** It is the only place that shapes offer data for the outside. It covers:

- the participant snapshot, following the "live rules", rank visibility and `show_prices` of §7.9;
- the issuer snapshot. While sealed and locked, every amount, rank, `is_leader`, the leader and `reserve_met` are null, and rows are ordered by alias so that the order does not leak the ranking;
- `ParticipantStandingRow` (and the public rows with the vendor), `OfferLogEntry`, `MyOffer`, `PublicResults`, the participant `result` and award view;
- helpers for other modules:
  - `issuerOfferAmount()` and `amountsHiddenFromIssuer()`, for webhooks and exports;
  - `commentAuthor()`, the Q&A author projection of API.md §2.7;
  - `participantSummary()`, the list-item fields of API.md §2.6.

**Endpoints.**

- App v1 (`routes/app_v1/bidding.php`): `live`, `live/heartbeat`, `offers` (POST, with `throttle:offers`), `offers`, `offers/log`, `my-offers`, `bafo-round`, `award` (POST and GET), `award/revoke` and `report`.
- Public v1 (`routes/public_v1/bidding.php`): `competitions/{id}/offers`, `competitions/{id}/results`, `awards`, `awards/{id}` and `awards/{id}/erp-sync` (`idempotent`). Every public lookup is scoped to the API client's organization; anything else is 404.

**BAFO (§7.11).** `Actions\StartBafoRound` (T7) and `Actions\FinishBafoRound` (T8, idempotent). `Jobs\EndBafoRound` is dispatched after commit with the cutoff as its delay; it is unique for 30 s and re-dispatches itself when not due. `bidding:tick` runs every 10 s. A round cancelled with its competition (T9) is simply ended.

**Award (§7.12).** `Actions\IssueAward` (T10) applies the guards in order: status, offer, `not_leading`, then reserve confirmation and justification. It stores the ledger head hash and sets `erp_sync_status` from the issuer's `api_enabled`. `Actions\RevokeAward` (T11) revokes; a re-award is a new row. Both write the audit entries `award.issued` and `award.revoked`, with the issuer as the feed organization.

**Void (§7.13).** `Actions\VoidOffer::handle(Offer $offer, CloseReason $reason, ?string $note, Actor $actor)`, for admins only. It rebuilds the standing from the ledger minus voids, re-ranks, bumps the version and writes the audit entry `offer.voided`.

**ERP write-back.** `Actions\RecordAwardErpSync`: `synced` or `failed`, plus the refs stored on the award as `external_refs`; 409 `award_not_active`; `AwardErpSynced`.

**Realtime (§9).**

- Channels (`Broadcasting\Channels\BiddingChannels`, registered in the provider):
  - `competition.{id}`: the user's active organization is the issuer;
  - `competition.{id}.participant.{orgId}`: it is the user's own organization, and that organization joined.
- Broadcasts: `IssuerLiveUpdated`, `ParticipantLiveUpdated` and `OfferAcceptedBroadcast`. They implement `ShouldBroadcast` and `ShouldDispatchAfterCommit` and use queue `live`; they are sent as `live.updated` and `offer.accepted`.
- Listeners (all queued after commit):
  - `BroadcastOfferAccepted`: the §7.10 recipients;
  - `BroadcastLiveChange`: kinds `bafo`, `award` and `void`, to everyone;
  - `BumpVersionAndBroadcast`: `CompetitionStatusChanged`, `CompetitionFinalWindowStarted` (kind `status`) and `CompetitionExtended` (kind `extension` with the extension kind), to everyone.
- Heartbeat: `Services\Heartbeats` uses the key `hb:{competitions.id}:{participants.id}` with a 45 s TTL. The endpoint refreshes at most once per 10 s per user and answers 204 to every call.

**Report (§7.14).**

- `Services\ReportGenerator` renders `bidding::pdf.report` through `PdfRenderer`. It uses `RulesSummary::lines(issuer: true)` and has all 9 sections, AR and EN, with the page x/y footer.
- The file is stored with purpose `competition_report` for the issuer organization. A regeneration replaces the previous file.
- `Jobs\GenerateCompetitionReport` runs on queue `pdf`, unique per competition and locale. A rendering failure marks the report `failed` and never fails the close or award that triggered it.
- `Listeners\QueueCompetitionReport` runs on `CompetitionClosed`, `CompetitionClosedWithoutAward`, `AwardIssued` and `AwardRevoked`.
- `Actions\RequestCompetitionReport` returns 200 when the report is ready and current, 202 when it was queued, and 409 `report_not_available` before the first close.
- The `competition_report` file rule is registered: members of the issuer organization.

**Other.**

- Events: the 8 Bidding events of §10, with the exact property names. They use `SerializesModels`, so queued listeners re-read the rows.
- Gate abilities `bidding.submit-offers` and `bidding.award` (`Policies\BiddingPolicy`).
- Limiter `offers`: 60 per minute per user.
- The §15.3 `bidding.*` setting defaults are registered from `config.php`.
- Lang files `lang/{ar,en}/bidding.php` hold the errors, validation, attributes, enums and report strings, with identical keys in both.

**Tests** (`tests/Feature/Bidding`, `tests/Unit/Bidding`, helper `tests/Support/Bidding/Scenario.php`):

| Area | Tests |
|---|---|
| Endpoint and pre-checks | `SubmitOfferEndpointTest`: order of the pre-checks, idempotent replay and key reuse (ledger and cached rejection), the rate limit, syntax codes, state codes, the close race (at and after `effective_close_at` while still `live`, and 1 µs before it) |
| Rules | `EngineRulesTest`: start price for both directions, must-beat own and best for tender and auction, step rounding, the tie-break by time and by seq, stages (initial forces own, final window, sealed revisions), the outlier guard, the ledger hash chain, the event context |
| Anti-sniping | `AntiSnipingTest`: window, leader change only, `max`, hard stop, no stacking, not in the initial phase |
| Visibility | `VisibilityTest` and `LiveEndpointTest`: the rank_visibility × show_prices matrix, the initial phase, sealed for both audiences with the unlock at the real Competitions close. **Leak tests** serialise every participant REST and broadcast payload and assert that no reserve, other amounts or identities appear. Also: channel auth through `/broadcasting/auth` with Reverb, and the §7.10 recipients |
| Lifecycle | `BafoRoundTest`, `AwardTest` (every guard, results per `result_publication`, revoke and re-award, not_awarded), `VoidOfferTest`, `LedgerImmutabilityTest`, `ReportTest`, `PublicApiTest` (real API keys through Integrations' middleware), `BiddingEngineContractTest` |
| Concurrency | `Unit/Bidding/ConcurrentOffersTest` forks two processes. Both are shown blocked on `SELECT … FOR UPDATE` (checked through `pg_stat_activity`) while a third connection holds the lock; after the release they commit serially, with a gapless and chained ledger, DB-clock times and the correct ranking. It commits and truncates after itself |

## 3. Using the module (other owners)

- **Competitions.** Call `BiddingEngine` through the contract (your `LiveBidding` does this).
  - Do **not** dispatch `OffersUnsealed` in `CloseDueCompetition`: `finalizeLiveBidding()` dispatches it after you set `offers_opened_at` on the locked model (which you already do).
  - For Q&A broadcasts use `VisibilityProjector::commentAuthor($comment, $viewerIsIssuer, $viewerOrganizationId)`.
  - For participant list items use `participantSummary()`.
- **Integrations.** Build `offer.*` webhook amounts and exports with `VisibilityProjector::issuerOfferAmount()` / `amountsHiddenFromIssuer()` / `standingRows()` / `publicResults()`. §7.9 makes the projector the single gate; `ExportBuilder` currently re-implements the sealed rule.
  - `OfferAcceptedContext::isFirstOfferOfParticipant` tells `offer.submitted` from `offer.updated`.
  - Add the 5 Bidding public operations to `resources/openapi/public-v1.yaml`.
- **Notifications.** Your key assumption is right: `hb:{competitions.id}:{participants.id}` with **internal ids**, on the default cache store, with a 45 s TTL (`Bidding\Services\Heartbeats::key()` / `isOnline()`). The event property names you listed are exact. `OfferAcceptedContext` also carries `leadingAmountChanged` (§5).
- **Admin.** Void offer: `app(VoidOffer::class)->handle($offer, $closeReason, $note, Actor::forAdmin($admin))`. The reason must be an active close reason of kind `void_offer`, with a note when it `requires_note`. The ledger (with voids) is `Offer::query()->where('competition_id', …)->withExists('void')`. The rejected attempts are in `offer_rejections`.
- **Web and mobile.**
  - `last_change.kind` is one of `offer`, `extension`, `status`, `bafo`, `award`, `void` or `snapshot`.
  - A participant not in a recipient set keeps an older `v`.
  - The POST offer response is 201, or 200 with `Idempotent-Replayed: true`.

## 4. Requests to other owners

**Competitions**

- `POST …/bafo-round` returns the Competition shape through your `CompetitionPresenter::present($competition, $viewer, $user)`. API.md lists no embeddable presenter, so please keep that method stable (or expose a contract). If it cannot be resolved, the endpoint falls back to a minimal shape (`Services\CompetitionPayload`).

**Integrations**

- `app/Modules/Integrations/Http/Requests/StoreExportRequest::format(): ExportFormat` is a fatal error: it clashes with `Illuminate\Http\Request::format($default = 'html')`.
  - `tests/Unit/ArchTest.php` loads every class and dies there. Pest's agent-mode reporter (it switches on when `CLAUDECODE` / `CLAUDE_CODE` is set) prints **nothing** and exits 1, so the full suite looks silent.
  - Rename the method, for example to `exportFormat()`.
- **Laravel's `cursorPaginate()` drops microseconds.** It serialises timestamp cursor values through `Carbon::__toString()`. On the tstz6 tables (`competitions`, `awards`, `webhook_events`, …), rows that share a second repeat on the next page. Bidding's public `GET /awards` uses its own keyset cursor, `Support\UpdatedAtCursor` (`updated_at` with microseconds, tie-break on `public_id`). The public competitions list will need the same.

**Platform**

- `tests/Feature/Platform/KernelMiddlewareTest` expects the `app_v1` group without Identity's `EnsureAccountActive`. `IdempotentRequestTest` and `Support/ApiConventionsTest` hit `public_v1` without a credential, so Integrations' `api.client` now answers 401. These are not Bidding failures, but they are red in the full suite.
- Bidding registers the §15.3 defaults `bidding.max_amount_minor`, `offer_min_interval_seconds`, `outlier_guard_bps`, `auto_extend_bounds`, `bafo_duration_bounds`, `max_concurrent_live` and `closing_soon_minutes`. There are no new env keys.

## 5. Contract gaps (choices made; marked `CONTRACT-GAP` in code)

1. **`OfferAcceptedContext::leadingAmountChanged`** is an extra property. §7.10 sends the update to everyone "when show_prices and the leading amount changed", which a queued listener cannot reconstruct later.
2. **"Live rules" "closed or later"** is read as closed, awarded or not_awarded, for the live format. During a BAFO round visibility is as in `sealed` (§7.3). A cancelled competition shows no standings.
3. **Recipients.** Offers outside stage `live` (initial, sealed, BAFO) are sent to the bidder only. Other projections do not change, and a snapshot would otherwise reveal that someone bid.
4. **`OffersUnsealed`** is dispatched by `finalizeLiveBidding()` (the close sets `offers_opened_at` first).
5. **`GET …/live` on a draft** answers 404.
6. **Heartbeat** is accepted in any status.
7. **Second void** of the same offer answers 409 `invalid_state_transition` with `details.status = voided`.
8. **Issuer `GET …/award`** returns the issued award, else the latest revoked one, else null.
9. **Participant `result`.** The winner always gets `won`, even with `result_publication = none`. `winning_amount_minor` is set only with `outcome_and_amount`.
10. **ERP refs** are upserted by (system, type, id). A key already on another award answers 409 `external_ref_conflict`, with `details.existing_id` = that award. `erp_synced_at` is set on `synced`.
11. **The `POST …/bafo-round` response** uses Competitions' presenter (see §4).
12. **Public `GET /awards`** uses the keyset cursor on `updated_at` (µs) and `public_id` (see §4).
13. **Offer amount validation.** `SubmitOfferRequest` does not validate `amount_minor`: §7.4 fixes the codes (`offer_amount_invalid`, …) and their place after the pre-checks. It is the only FormRequest that leaves its main field to the Action.
14. **Codes of other lists.** `not_a_participant`, `results_not_available`, `report_not_available` and `external_ref_conflict` get their messages in `lang/*/bidding.php`, because Bidding throws them.

## 6. Known issues

- The full suite currently fails because of other modules' in-progress code (§4). Only the Bidding directories were verified green.
- `CompetitionsDemoSeeder` already drives `SubmitOffer`, `StartBafoRound` and `IssueAward` with the right signatures. On a scratch database (since dropped), every scenario was skipped with `issuer_plan_required` before Billing's `AccessPolicy` landed, so the demo path through Bidding has not run end to end yet. `ConcurrentOffersTest` exercises the engine with the real `PostgresDbClock`.
- `apps/api/CLAUDE.md` asks agents to install `laravel/boost`. That is outside the contract and would change the shared `composer.json` and `composer.lock`, so it was not done.
