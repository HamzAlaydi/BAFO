# Competitions handoff

Written by the Competitions engineer on 2026-09-29. It covers what the module provides, the choices made
where the contract is silent (`CONTRACT-GAP`), and what other owners need to know or do.

## 1. Checks at hand-off

| Check | Command | Result |
|---|---|---|
| Competitions tests | `scripts/test-api.sh competitions tests/Feature/Competitions` | 163 passed |
| Other modules against this code | `scripts/test-api.sh competitions tests/Feature/{Billing,Bidding,Notifications,Identity,Integrations,Catalog} tests/Unit/{Billing,Bidding,Schema}` | 1,300 passed |
| Full suite | `scripts/test-api.sh competitions` | 1,890 of 1,893 pass; the 3 failures are Platform tests (see §6) |
| PHPStan level 6 | `vendor/bin/phpstan analyse app/Modules/Competitions --memory-limit=1G` | 0 errors |
| Pint | `vendor/bin/pint --test` on the module, routes, lang and tests | clean |
| Demo chain | `DemoSeeder` on the isolated database `bafo_competitions_test` | 12 competitions: draft 1, scheduled 1, live 5, closed 1, bafo_round 1, awarded 1, not_awarded 1, cancelled 1; 15 offers |

## 2. What exists

**Contracts** (bound in `CompetitionsServiceProvider::register()`):

| Contract | Implementation | Notes |
|---|---|---|
| `Contracts\CompetitionStateMachine` | `Services\StateMachine` | `transition($locked, $to, $actor, $attributes)`: T1–T12 of §6.1, sets the lifecycle stamp unless `$attributes` sets it (`closed_at` only when leaving `live`; leaving `awarded` clears `awarded_at`), saves, dispatches `CompetitionStatusChanged`. It writes **no** audit entry: the calling Action does. Extra read-only `allows($from, $to)`. |
| `Contracts\CompetitionTimingService` | `Services\CompetitionTiming` | `extend(...)`: moves `effective_close_at`, bumps `extension_count`, writes `competition_extensions` (`triggered_by_offer_id` for `auto` only), dispatches `CompetitionExtended`, and schedules `Jobs\CloseCompetition` after commit. |
| `Data\Viewer` + `Enums\ViewerRole` | `Services\ViewerResolver::for(Competition, Actor): Viewer` (404 otherwise), `resolve()` (nullable) | Roles `issuer`, `participant`, `invitee`, `api_client`. Helpers `isIssuer()` (issuer or API client), `isParticipant()`, `isInvitee()`, `isApiClient()`. |

**App v1 endpoints** (`routes/app_v1/competitions.php`, API.md §1.4 and §1.5): `home`; `competitions.{index,store,show,update,destroy}`; `competitions.{publish,extend,cancel,close,suggestions}`; `competitions.invitations.{index,store,update,destroy,resend}`; `competitions.attachments.{index,store,update,destroy}`; `competitions.comments.{index,store}`; `invitations.{lookup,decline-by-token,claim,join,decline}`. Child routes use scoped bindings.

**Public v1 endpoints** (`routes/public_v1/competitions.php`, API.md §3.4): `competitions.{index,store,show,update,destroy,publish,extend,cancel,close}`, `competitions.attachments.{index,store,destroy}`, `competitions.invitations.{index,store,destroy}`, each with `api.scope:<scope>` and `idempotent` on the creating/acting POSTs. Tenancy: another organization's competition is 404. The list uses a keyset cursor on `updated_at` (µs) and `public_id`.

**Actions** (every state change; Filament must call these):

| Use case | Action |
|---|---|
| Create, update, delete a draft | `CreateCompetition`, `UpdateCompetition`, `DeleteDraftCompetition`; public create with invitations and sponsorship: `CreateCompetitionFromApi` |
| Publish (T1/T2) | `PublishCompetition::handle(Competition, Actor)` |
| Open (T3), final window, closing soon, cut-off expiry (tick) | `OpenCompetition`, `StartFinalWindow`, `AnnounceClosingSoon`, `ExpireInvitationsAtCutoff` |
| Close (T5) | `CloseDueCompetition::handle(int $id, ?Actor)` (the `CloseCompetition` job and the tick) |
| Admin force close | `ForceCloseCompetition::handle(Competition, string $reason, Actor::forAdmin(...))` |
| Extend (manual; `kind = admin` when the actor is an admin) | `ExtendCompetition::handle(Competition, CarbonImmutable $newCloseAt, string $reason, Actor)` |
| Cancel (T4/T6/T9), close without award (T12) | `CancelCompetition`, `CloseWithoutAward` (`handle(Competition, CloseReason, ?string $note, Actor)`; they check the reason kind and the note themselves) |
| Invitations | `InviteParticipants::handle(Competition, array $rows, Actor): Collection<Invitation>`, `UpdateInvitation`, `RemoveInvitation`, `RevokeInvitation::handle(Invitation, Actor, ?RevokeReason)` (admin actor → `admin`), `ResendInvitation`, `LookupInvitation`, `MarkInvitationViewed`, `ClaimInvitation`, `JoinCompetition`, `DeclineInvitation` |
| Attachments, Q&A | `AddAttachment`, `UpdateAttachment`, `DeleteAttachment`, `PostComment` |

**Rules.** `Services\RulesValidator` (R1–R19; R20 through `AccessPolicy::canIssue`, R21 through `SponsorshipService::reserveForPublish`), `Services\RulesMapper` (RulesInput ↔ columns, error paths), `Services\RulesSummary::lines($competition, $locale, $issuer)` (§7.16, AR/EN, direction variants), `Services\ScheduleCalculator` (derived times, `reference_no`).

**Presentation.** `Services\CompetitionPresenter::present($competition, $viewer, $user)` (issuer, participant and invitee projections; stable for Bidding's `POST …/bafo-round` response), `teaser()`, `publicCompetition()`; `CompetitionListPresenter`, `InvitationPresenter`; `Http\Resources\{AttachmentResource, CommentResource, Shapes}`. Lookups are embedded through Catalog's resources; comment authors through Bidding's `VisibilityProjector::commentAuthor()`.

**Events** (§10): all 19 Competitions events. **Listeners:** `BroadcastCompetitionUpdated` (on `CompetitionUpdated` and `AttachmentAdded`, published competitions only), `BroadcastCommentCreated` (one payload per audience), `BroadcastInvitationUpdated` (the six invitation events), and `AttachPendingInvitations` (on Identity `EmailVerified`). **Broadcasts** (queue `live`): `competition.updated`, `comment.created`, `invitation.updated`.

**Scheduler.** `competitions:tick` every 10 s, `withoutOverlapping()->onOneServer()`: opens due competitions, stamps the final window, announces closing-soon thresholds, dispatches `CloseCompetition` for due live rows, expires invitations at the cut-off. `CloseCompetition` (queue `live`, `ShouldBeUnique` for 60 s) re-schedules itself when an extension moved the close.

**Also.** The token-carrying invitation mail (`Mail\CompetitionInvitationMail`, `competitions::mail.invitation`, queued on `mail`, encrypted, after commit; the token only in the fragment `#t=`). The `competition_attachment` file rule (§8.5). The §15.3 `competitions.*` setting defaults. `lang/{ar,en}/competitions.php`: `errors`, `validation`, `attributes`, `rules_summary`, `mail`, and `enums.viewer_role`. `CompetitionsDemoSeeder` (§4).

## 3. Contract gaps (choices made; marked `CONTRACT-GAP` in code)

1. **Create defaults by format.** Absent rules keys take the column defaults, except a sealed competition defaults to `rank_visibility = none` and a live one to `must_beat = own` (the raw defaults would fail R1/R2).
2. **Disabled groups are cleared, not rejected.** With `auto_extend.enabled = false` the three values are nulled; with the BAFO round off its duration is nulled, and when on it defaults to 60.
3. **Error path of R5** is `rules.start_price_minor` (the input path), not `start_price_minor`.
4. **`external_refs`** (public API): written by Competitions (`Services\CompetitionExternalRefs`) inside the competition transaction, because §3.6 has no Integrations contract for it; accepted in every status on `PATCH`; a key used by another competition is 409 `external_ref_conflict`.
5. **Public `sponsorship`** on create goes through Billing's `ConfigureSponsorship` Action.
6. **Force close** writes the `admin` extension row directly, without `CompetitionExtended` (the close is brought forward, not extended).
7. **Closing soon.** When several thresholds are reached at once only the smallest is announced; all are recorded.
8. **Attachments `PATCH`** follows the add rule (draft, scheduled, live).
9. **Participant `result`.** The winner sees `won` even with `result_publication = none` (Bidding chose the same).
10. **`terms_version`** is the latest published `competition_rules` version (Arabic, then English), or `unpublished`.
11. **Home.** The alert conditions (`subscription_expiring` within 7 days; `subscription_expired` / `plan_required` without a current subscription; `trial_available`; `billing_profile_incomplete`), and the activity `id`, an opaque HMAC of the audit row id (audit logs have no public id). Activity subjects of invitations and competitions are shown as the competition.
12. **Claim by an issuer member** of its own competition's invitation → 409 `invitation_belongs_to_another_organization`.
13. **"Already invited"** means the same e-mail in any status, or the same organization with a draft, sent, viewed or joined invitation.
14. **Invitation mail language:** the invited user's `locale` when the e-mail belongs to a user, else Arabic.
15. **Keyset cursor** for the public list: `cursorPaginate()` drops microseconds (Bidding reported the same).
16. **`ViewerRole`** is a string enum; **`CompetitionStateMachine::allows()`** is an extra read-only method.

## 4. Demo data

`CompetitionsDemoSeeder` builds the 12 competitions of DEMO.md through the Actions. Titles are stable: `CompetitionsDemoSeeder::TITLES` and `CompetitionsDemoSeeder::find($key)`.

- Subscriptions start when Billing seeds them, so the Actions run in real time and the published timeline is then aged (`age()`), so each competition is in the wanted phase now.
- Offers (#4 final window, #5 auction with Buyer D, #6 sealed, #7 closed, #8 BAFO, #9 awarded, #10 not awarded) are placed with Bidding's `SubmitOffer` in real time (one per participant: the engine allows one every 2 s). #7–#10 are then brought to their close and closed with `CloseDueCompetition`; #8 continues with `StartBafoRound`, #9 with `IssueAward`, #10 with `CloseWithoutAward`.
- #12 follows Billing's recipe: `ConfigureSponsorship` (selected), `GrantSponsoredPass` for Supplier B, publish, then B joins on the pass.
- A scenario that throws is skipped with a warning; the others still run.

## 5. Requests to other owners

**Bidding**

- `CompetitionPresenter::present($competition, $viewer, $user)` will stay stable (your `POST …/bafo-round`).
- `CompetitionsDemoSeeder` already places the offers and drives the BAFO round and the award of the DEMO.md list; a `BiddingDemoSeeder`, if you add one, should only add offers to the live competitions (#4, #5, #6, #12).
- A cancel during a BAFO round (T9) leaves the `bafo_rounds` row `running`; please end it on `CompetitionCancelled` (or `CompetitionStatusChanged` to `cancelled`) if the round must not stay running.

**Billing**

- Done: `bindKnownOrganizations()` now runs before `reserveForPublish()`; `POST …/invitations` rows keep the request order (`ksort`).
- `DbAccessPolicy::coverageFor()` reads `$invitation->competition` per row, which is a lazy-loading violation in non-production for rows of a multi-row query. Competitions sets the relation before calling; eager-loading it inside `coverageFor()` would make the contract safe for every caller.

**Integrations**

- Add the Competitions public operations (API.md §3.4) to `resources/openapi/public-v1.yaml`.
- `external_refs` rows of competitions are written by Competitions (gap 4). If you add an `ExternalRefs` contract, tell me and I will switch to it.

**Platform**

- `FileResource.download_path` is the app v1 path; public API clients cannot use it for attachments (API.md has no public download). Decide whether a public download endpoint is needed.
- A shared keyset cursor in `app/Support` would replace `Competitions\Queries\KeysetCursor` and Bidding's `Support\UpdatedAtCursor`.
- No new env keys. Settings defaults registered: `competitions.min_duration_minutes`, `max_duration_days`, `invite_cutoff_minutes`, `max_participants`, `min_extend_minutes`, `final_window_bounds`.

**Notifications**

- Fixed: `CompetitionExtensionFactory::auto()` (unresolved closure) and `InvitationPresenter` (`loadMissing` on a base collection).
- `InvitationSent` is also dispatched on resend (a new token and mail): expect a second `competition.invited` for a resent invitation.
- `CompetitionUpdated::$fields` may also contain `category`, `region` and `rules` (drafts only; drafts have no audience).

**Admin (Filament)**

- Competitions page: Extend → `ExtendCompetition` with `Actor::forAdmin()` (kind `admin`); Cancel → `CancelCompetition` (sets `cancelled_by_admin_id`); Force close → `ForceCloseCompetition($competition, $reason, $actor)`; revoke an invitation → `RevokeInvitation` (reason `admin`).

**Web and mobile**

- Render from `viewer_role` and `permissions`. `POST /invitations/claim` answers 202 `{otp_sent_to, otp_expires_at}` when an OTP was sent, else 200 with the invitee view.
- Bulk invitations: all or nothing, `errors."invitations.{i}.<field>"` plus `details.item_codes`.

## 6. Known issues

- Full suite: `Platform/KernelMiddlewareTest` (expects `app_v1` without Identity's `EnsureAccountActive`), `Platform/IdempotentRequestTest` and `Support/ApiConventionsTest` (call `public_v1` without a credential, now 401 from Integrations' `api.client`). Not Competitions code; reported by Bidding too.
- `MigrationsTest` failed once during a full run while other engineers were changing files, and passed on the next run.
- The dev database `bafo` was not reset (other engineers use it); the demo chain was run on `bafo_competitions_test`. Those runs left a few demo PDFs under `storage/app/private/competition_attachment/` (gitignored).
- `apps/api/CLAUDE.md` asks agents to install `laravel/boost`; that is outside the contract and was not done.
