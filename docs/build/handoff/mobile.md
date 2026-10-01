# Mobile handoff (`apps/mobile`)

Written on 2026-09-29 by the mobile foundation engineer (phase 1: foundation + auth). Phase-2 mobile engineers build on this; other owners will find the requests to them in §4.

## 1. What phase 1 delivered

| Area | Where | Notes |
|---|---|---|
| Models for every app v1 module | `lib/core/models/` (shared: enums, rules, lookups, files, `Me`), `lib/features/<f>/domain/` | Hand-written `fromJson` on `core/api/json.dart` readers. Unknown enums map to `unknown`. Omitted projection fields read as null. |
| Repositories for every app v1 module | `lib/features/<f>/data/`, `lib/core/lookups/`, `lib/core/config/` | auth, legal, me/organization/deletion, team, home, competitions (+ award, suggestions, sponsorship), attachments, comments, vendors, invitations (both sides), live/offers/log, billing (read-only), notifications, devices, lookups (ETag per language), app-config (cached). All are provided in `app.dart`; `AppDependencies.wire(repositories: (r) => r.copyWith(...))` swaps them in tests. |
| Fixtures | `test/fixtures/api/*.json` (109 files) | Captured from the running API on the demo seed. Tokens are redacted. `test/core/models/api_models_test.dart` parses every one and checks the projections (no reserve price, no other participant's identity or price for participants). |
| Core services | `lib/core/**` | `AppConfigCubit` (min version → M03, maintenance → M04, realtime settings), `ReverbRealtimeClient` (reconfigure, suspend/resume, subscriptions survive reconnects), `CompetitionChannelHub` (ref-counted channels, per-handle `v` gate, resync on reconnect and resume), `UnreadCountCubit`, `NetworkStatusCubit` + offline banner, `DeepLinkRouter` (S11 table), `BafoDateFormat` (R-M3 Western digits), the account gate (403 `account_inactive` / `organization_suspended`), `FileDownloadService` (bearer download to the temp dir, open with the system viewer). |
| Widget kit additions | `lib/widgets/` | Direction/format/status chips with the S2 overlays, access and result chips, fees-covered and standing badges, `StandingBanner` (throttled live region), `CompetitionCountdown` (extension banner, «جارٍ الإغلاق…», threshold announcements), `MoneyInputField` (halalas, granularity, currency side), `RulesSummaryCard`, `KeyValueList`, `InfoNotice`, `StatTile`, `MeterBar`, `AttachmentTile`, phone/OTP/password/digits/search/date-time fields, consent checkbox, segmented filter, stepper header, cooldown button, reason/image/multi-select sheets, forbidden/not-found states, offline banner, Markdown view, language switch, LTR islands. |
| Screens | `lib/features/auth/presentation/`, `competitions/presentation/competition_overview_screen.dart`, `billing/presentation/plan_status_screen.dart` | M02 welcome (once), M06 sign in, M07–M09 register, M10 verify, M11 forgot, M12 reset, M13 legal; account gate; `/competitions/:id` read-only overview for every `viewer_role`; M58 plan status (no price, link or button). |
| Router (R-M1, R-M2) | `lib/core/router/app_router.dart` | «مشاركاتي» tab; `/competitions/:id` subtree and `/billing` on the root navigator; welcome on the first signed-out run. |

## 2. For phase-2 mobile engineers

- **Add your routes in your feature, not in the router.** `features/competitions/presentation/competition_routes.dart` owns the `/competitions/:id` subtree (`live`, `qa`, `my-offers`, `offers`, `participants`, `invite`, `attachments`, `edit`, `award`). `live` and `qa` redirect to the overview until M23/M31 exist; replace the redirects.
- **Live data:** acquire `context.read<CompetitionChannelHub>().acquire(competitionId:, role: competition.viewerRole, organizationId: me.organization.id)`. Feed every REST snapshot (`GET …/live`, `competition.liveJson`, `OfferSubmission.liveJson`) through `acceptSnapshot` before applying it; `liveSnapshots` already passes the gate. Refetch on `resyncRequests`. Release in `close()`.
- **Offers:** `LiveRepository.submitOffer(idempotencyKey: IdempotencyKey.generate())`, one key per intent, reused on retries; a new key for the outlier re-send. `OfferSubmission.replayed` reads `Idempotent-Replayed`.
- **Errors:** `errorMessage(l10n, error)` shows `errors.<code>` from the ARB (every code of CONVENTIONS §8 a mobile client can receive has a key), else the server message. Server field errors bind by path (`organization.cr_number`, `invitations.2.email`).
- **Time:** `ServerClock` for anything about bidding; `BafoDateFormat` for display (`deadline`, `relative`, `machine`, `timeWithMillis`, Riyadh picker conversions).
- **Session:** `context.read<SessionCubit>().state.me` is `Me` (null only on an offline start). Call `session.updateMe(me)` after `PATCH /me` or avatar changes.
- **Unread badge:** `UnreadCountCubit` follows the user channel; `notificationCreated` republishes `notification.created` payloads for the notifications list and home. Call `set(count)` after a local mark-read.
- **Entitlement only:** never call checkout, trial, coupon or plan endpoints. Use `InfoNotice(message: l10n.billingManagedOnWeb)` / `sponsorshipManagedOnWeb`.
- **ARB:** phase 2 adds keys with its feature prefix only. `errors*`, `common*`, `competitionsStatus*`, `competitionsDirection*`, `liveStatus*`, `invitationsAccess*`, `awardOutcome*`, `billing*` already exist.
- **Live test:** `BAFO_LIVE_API=http://localhost:8000/api/app/v1 BAFO_DEMO_EMAIL=… BAFO_DEMO_PASSWORD=… flutter test test/live/live_api_test.dart` (add `BAFO_LIVE_OFFER=1` to place one offer and receive the `live.updated` over Reverb). Skipped in the default run.

## 3. Client decisions (CONTRACT-GAP notes)

| # | Decision | Why |
|---|---|---|
| M-1 | Sign-out order: `DELETE /devices/{id}` **before** `POST /auth/logout`. | SCREENS M51 lists logout first, but the device call would then fail with 401 (the token is revoked). |
| M-2 | A loopback `realtime.host` from app-config (`localhost`, `127.0.0.1`) is replaced by the API host; explicit `REVERB_*` defines override the server. | ARCHITECTURE §9.1 "the Android dev flavour overrides host to `10.0.2.2`", done generically so a LAN phone works too. |
| M-3 | The welcome slides (M02) show once per device before the first sign-in; a `from` deep link survives them. | SCREENS §3.1 "a first run without a session goes to `/welcome`". |
| M-4 | The account gate (S8) only reacts to 403 `account_inactive` / `organization_suspended` on requests that carried a token; a sign-in answering these codes stays a form error. | Login returns the same codes as form errors (W04/M06). |
| M-5 | `errors.not_found` is generic; the competition screen uses `competitionsDetailNotFound` for the S8 **N** text. | The S8 text names a competition, but the same code comes from every resource. |
| M-6 | Mobile is light-only (`themeMode: light`). | SCREENS CD11. |
| M-7 | The status chip of a BAFO round uses a glyph instead of the brand mark. | The mark has a 24 dp minimum (05_brand.md §2.5); the BAFO banner can show the mark at full size. |

## 4. API mismatches and requests to other owners

| # | Owner | Finding (checked against the running API, 2026-09-29) | Client handling | Request |
|---|---|---|---|---|
| A-1 | Identity / Competitions (Home) | `GET /home` `alerts[].params` is `[]` when empty (PHP empty array), not an object. API.md §2.13 shows an object (`{"days_left": 3}`). | Read as an empty map. | Return `{}` (cast to object) when there are no params. |
| A-2 | Platform (dev env) | `OTP_FAKE_CODE` is not set in `apps/api/.env`, so demo OTPs are random and only in the log mailer, while DEMO.md and the mobile brief say every OTP is `123456`. | The live check read the code from `storage/logs/laravel.log`. | Set `OTP_FAKE_CODE=123456` in the local `.env`, or update DEMO.md. |
| A-3 | Competitions (Home) | `activities[].id` is a 26-char hex hash (e.g. `e449d6888cc510eee26a47fad4`), not a ULID (API.md §0.6 "id is a 26-char lowercase ULID"). | Treated as an opaque string. | Either document it as an opaque id or return the audit entry's ULID. |
| A-4 | Platform (settings) | `app-config` `store_links` and `support` are empty strings on the dev database. | M03 hides the store button; the account gate hides the support card. | Seed placeholder values for demos, and real ones before release. |
| A-5 | Identity | `POST /auth/login` with a malformed `email` does not return a format error (only `required` errors appear). | The client validates the format first. | None needed (not a contract break); noted for consistency with register. |

No other differences were found: every fixture in `test/fixtures/api` matches API.md §2 field names, types and projections (issuer, participant, invitee; sealed amounts null; comment authors by alias; participant snapshots own-only in the initial phase).

## 5. Phase 2: participant, live room and Q&A (M16–M32)

Written on 2026-09-29 by the participant feature engineer. Code in `lib/features/participant/**`, `lib/features/live/**` (the existing models and repository plus the new presentation) and `lib/features/qa/**`; tests in `test/features/{participant,live,qa}/`.

### 5.1 Routes and integration

| Path | Screen | Notes |
|---|---|---|
| `/competitions` (tab) | `ParticipatingListScreen` (M16) | The shell branch builder in `app_router.dart` now points here. |
| `/competitions/:id` | `ParticipantCompetitionScreen` (M17 invitee, M21 participant) | `viewer_role` dispatch: an issuer gets `issuerDetailView` (M38). `?intent=join` opens the join sheet once loaded (the list's "Join"). |
| `/competitions/:id/live` | `LiveRoomScreen` (M23–M27) | An issuer gets `issuerLiveView` (M46); an invitee is replaced by the overview (`not_a_participant`). |
| `/competitions/:id/qa` | `QaScreen` (M31–M32) | Serves the issuer too (announcements, replies to every thread). |
| `/competitions/:id/my-offers` | `MyOffersScreen` (M28) | 403 → back to the overview. |

- `participantRoutes(rootKey, issuerDetail:, issuerLive:)` (in `features/participant/participant_routes.dart`) is listed in `app_router.dart` **before** the shell: go_router takes the first full match, and a child these routes do not have (`offers`, `participants`, …) falls through to `issuerCompetitionRoutes`. The shell's `competitionDetailRoute` overview subtree is now unreachable for these four paths; the competitions owner may drop it.
- `test/app_test.dart` ("/competitions/:id opens above the shell") now expects `ParticipantCompetitionScreen` for the participant projection (one-line change).
- Canonical paths are in `ParticipantPaths`; deep links (S11) map to them unchanged.

### 5.2 Client decisions

| # | Decision | Why |
|---|---|---|
| P-1 | The list's "Join" opens the invitation page (M17) with the join sheet, instead of a sheet on the list. Decline stays a sheet on the list. | M18 needs the rules summary, which the list item does not carry. |
| P-2 | "Needs action first" is applied per loaded page (appending never reorders rows already shown). | SCREENS G1 (no server filter). |
| P-3 | The join deadline shows as a date («الانضمام قبل {date}») with "time remaining" under it, not «الانضمام قبل» + countdown. | The label followed by a duration reads ungrammatically in Arabic. |
| P-4 | The live room keeps the newest accepted snapshot even if it arrives over the socket before the first `GET …/live` completes. | §9.4 buffering: the gate records the socket `v`, so a later REST read with a lower `v` is (correctly) dropped. |
| P-5 | At zero the composer shows «جارٍ الإغلاق…» and the room asks `GET …/live` 2 s later, then every 10 s, until the server reports the new state. | S3 step 9; covers a silent socket and a late close job. |
| P-6 | Resume: the room restarts heartbeat and clock sync; the snapshot itself is refetched by `CompetitionChannelHub.notifyResumed` (app root), not by the screen. | Avoids a double `GET …/live` per resume. |
| P-7 | Success UI (toast with `accepted_at` HH:mm:ss.SSS Riyadh, sealed receipt, BAFO line, haptics) runs after the confirm sheet has closed. | The sheet closes on the same state; a receipt opened first would be popped with it. |
| P-8 | `ResultChip(not_awarded)` is not shown next to the status chip. | It repeats «أُغلقت دون ترسية». |
| P-9 | Join, decline and Q&A send are disabled while offline (`watchOffline`); the live composer follows the S4 connection table. | S8. |
| P-10 | The push explainer (M50) is called after a successful join through the notifications feature's `showPushExplainerIfNeeded`. | SCREENS M18. |

### 5.3 Checked against the running API (local dev DB)

Opt-in test `test/features/live/participant_flow_live_test.dart` (credentials from the environment; `BAFO_LIVE_MUTATE=1` for writes) drives the app's blocs with the real `ApiClient` and Reverb, as Buyer D: M16 list (needs-action first), M17 invitee → M18 join → participant view and channel, M23 room connected over Reverb, a below-bound offer refused with `offer_step_not_met` and the server amount, an accepted offer applied to the room (v3 → v4, now leading), M28 lists it, M32 question posted and received once over `comment.created`. With curl: `offer_outlier_confirm_required` (`change_bps`, `reference_amount_minor`), `offer_start_price`, `too_many_requests` (`retry_after_seconds: 2`), `idempotency_key_required`, `comments_closed`, `terms_not_accepted`, `offer_not_accepting` on a scheduled competition (`details.status` only), and `plan_required` with `details.access` (a demo competition «اختبار المشاركة من التطبيق» was created and published as Issuer Co, inviting Supplier B and Supplier C; Supplier C also joined the scheduled demo competition #2). No emulator run: no APK was built to spare shared memory; phone-size RTL/LTR renders of M16, M17, M21, M23 (AR/EN), M26, M27 and M31 were checked from widget renders with the real fonts.

### 5.4 API notes

No contract mismatch found in the participant endpoints and events used (`GET /competitions?role=participant`, `GET /competitions/{id}` participant and invitee projections, attachments, join, decline, `GET …/live`, heartbeat, `POST …/offers`, `GET …/my-offers`, `GET …/award`, comments, `live.updated`, `competition.updated`, `comment.created`). Observations only:

- `offer_not_accepting` on a **scheduled** competition carries `details.status` but no `opens_at` (ARCHITECTURE §7.4 sends `opens_at` only for a live competition before `bidding_opens_at`). The app never sends then (`accepting_offers` is false); SCREENS S5's «تبدأ العروض في {time}» is shown only when `opens_at` is present.
- `terms_not_accepted` (422) also fills `errors.accept_terms`; the app shows the code's text.
- A participant's `GET /competitions/{id}` has `live: null` while the competition is `scheduled`; the room reads `GET …/live` (v1, `accepting_offers: false`).

## 6. Phase 2: home, notifications and account (M14–M15, M49–M61)

Written on 2026-09-29 by the home, notifications and account feature engineer. Code is in `lib/features/home/**`, `lib/features/notifications/**` and `lib/features/account/**`. Tests are in `test/features/{home,notifications,account}/`.

### 6.1 Routes and integration

| Path | Screen | Notes |
|---|---|---|
| `/home` (tab) | `HomeScreen` (M14, M15) | `HomeCubit`: `GET /home`, pull to refresh, refetch on resume, and a debounced (2 s) refetch on `UnreadCountCubit.notificationCreated`. |
| `/notifications` (tab) | `NotificationsScreen` (M49) | `NotificationsBloc`: All/Unread, 20 per page with infinite scroll, S11 routing through `DeepLinkRouter`, and unread counts pushed to `UnreadCountCubit.set` (the tab badge). |
| `/account` (tab) | `AccountHubScreen` (M51) | `accountRoute()` in `features/account/presentation/account_routes.dart`. In `app_router.dart`, the Account branch now lists `accountRoute()` instead of the phase-1 `ProfileScreen`. That is the only router change. |
| `/account/profile`, `password`, `organization` (`?edit=1` opens the form), `team`, `team/new`, `team/:membershipId`, `invoices`, `settings`, `help`, `delete-account` | M52, M54, M55, M56, M57, invoices, M59, M60, M61 | These are children of `/account` inside the tab navigator, so the bottom bar stays visible. `/billing` (M58) and `/legal/:code` (M13) are the existing root routes. |

- **Push explainer (M50):** call `showPushExplainerIfNeeded(context)` from `features/notifications/presentation/push_explainer_sheet.dart`.
  - It shows once per device, after the first join (participant P-10) or the first publish (issuer). It never shows at cold start.
  - "Allow" runs `PushPermissionCubit.request()`: OS prompt, then device token, then `POST /devices`. The device id goes to `PreferenceKeys.pushDeviceId`, which the sign-out cleanup reads.
  - With `NoopPushService` there is no prompt and no token, so no request is sent and the user sees «غير متاحة على هذا الجهاز حالياً».
- **Contact form:** `ContactRepository` / `ApiContactRepository` (`POST /contact`) is new in `features/account/data/`. The `help` route provides it from `ApiClient`, so `Repositories` did not change.
- **Superseded file:** `lib/features/profile/presentation/profile_screen.dart` (the phase-1 Account placeholder) is no longer routed. Its owner can delete it; nothing imports it.
- **ARB:** 203 keys use the `home*`, `notifications*` and `account*` prefixes, in both files. The five unused placeholder keys `homeWelcome*` and `homeLive*` were retired.

### 6.2 Client decisions

| # | Decision | Why |
|---|---|---|
| H-1 | The invoices list (`/account/invoices`, `billing.view`) and the plan card show «تُدار … من لوحة تحكم بافو على الويب» / «الفواتير متاحة في لوحة تحكم بافو على الويب» as text, with **no link**. Invoice rows show only the number, date, type and e-invoice status: no amount and no PDF. | The task brief asked for a "manage on web" link. SCREENS CD6/§3.2 ("no link, no button, no URL" on iOS and Android) and CONVENTIONS §5.1 win over the brief. A widget test asserts that no amount, no `SAR`/`ر.س`, no URL and no filled button appear. |
| H-2 | Settings has no theme choice. | SCREENS CD11 and M-6 make mobile light-only in v1. A theme setting would also need core changes (the `PreferenceKeys` allow-list and `themeMode` in `app.dart`), and the dark roles in `derived_colors.dart` are still PROPOSED. |
| H-3 | Activity texts use the passive voice («نُشرت «…»»), and the actor goes on the meta line («سارة · منذ ساعة»). The API actor `"system"` is shown as «النظام». | Arabic verbs agree with the actor's gender, which the app does not know. |
| H-4 | User-provided names and titles inside composed sentences (the greeting, activity titles) are wrapped in first-strong isolates (U+2068 … U+2069). | S1: an English title inside an Arabic sentence keeps its punctuation, and the other way round. |
| H-5 | Opening a notification: the bloc marks it read (CD9 allows this as an optimistic list edit). `DeepLinkRouter.open(route)` is called **without** `notificationId`, so only one `POST …/read` is sent. A route that maps to `/notifications` does not navigate. `/home` (integrations) switches tab with `go` instead of `push`. | This avoids a double request, and avoids pushing a tab root onto itself. |
| H-6 | Swipe to delete (towards the start edge, mirrored in RTL) and mark-read are applied at once and undone if the server refuses. "Mark all read" and "delete all" wait for the server; "delete all" asks for confirmation first, with a red button labelled «حذف كل الإشعارات». Screen readers get "mark read" and "delete" as semantics actions. | CD9, S7 and S9. |
| H-7 | Image picking (M53) uses `file_picker` (`FileType.image`): "choose an image" or "remove", with **no camera** option. | `image_picker` is not in `pubspec.yaml` (see the request in §6.4). |
| H-8 | Team: the owner's row and the viewer's own row are locked (W26). "Invite" stays enabled when the seats are full; a notice above it shows «اكتملت مقاعد باقتكم (3/3).» with the managed-on-web text, and the server has the final say. Role defaults follow ARCHITECTURE §8.1 (admin: both flags on; member: both off). | CONVENTIONS §7: the client never recomputes entitlement. |
| H-9 | The home page shows both stat sections (every organisation can take part, and the issuer section appears with `competitions.create`), with the more active role first. Quick actions follow the same order. Create is shown only with `competitions.create` **and** `can_issue`; with the permission but no plan, the S10 text and managed-on-web text appear instead of a button. | W10, S10 and SCREENS §3.2. |
| H-10 | Every cubit of these features ignores `emit` after `close()`, so a request that finishes after the user leaves the screen is dropped (a test covers this). Loading states are only emitted when they change something, so there are no redundant emissions. | Leaving a screen mid-request must not throw. |
| H-11 | The home billing-profile alert is tappable only with `organization.update`, and opens `/account/organization?edit=1`. | It is not a purchase action. |

### 6.3 Checked against the running API (local dev DB)

- **Opt-in live test.** `test/features/account/live_account_test.dart` takes its credentials from the environment and drives the real cubits with `ApiClient`: home, notifications (page 1 and page 2, no duplicates, badge count), organisation with lookups, team, invoices, account deletion, and push registration through a granting push service. The push step registers the device with `POST /devices` and removes it again with `DELETE /devices/{id}`. At the end, the test revokes its own token.
- **Results by user:**
  - `issuer.owner`: team and invoices loaded.
  - `issuer.member`: team and invoices are Forbidden, and no request was sent.
  - `supplier-b.owner`: the `plan_required` and `trial_available` alerts appeared.
- **With curl:**
  - `POST /contact` answers 201 with `data.id`.
  - `POST /devices` answers 201 with a `Device`, and `DELETE` answers 204.
  - `PATCH /organization` validation paths are top level (`name`, `vat_number`, `national_address.postal_code`) and are bound to the form fields.
  - The team and invoice endpoints answer 403 `forbidden` for a member.
- **Visual check.** No APK was built, to spare shared memory. Phone-size renders with the real fonts are in `apps/mobile/docs/screenshots/phase2_*.png` (home AR/EN and without a plan, notifications, account, team, organisation, invite, invoices, delete, settings EN). S9 is checked by `text_scale_test.dart`: 12 screens at 200% text on a 360 dp phone, in both languages. It found an overflow in the Settings push row, which is now fixed.

### 6.4 API notes and requests to other owners

| # | Owner | Finding | Client handling | Request |
|---|---|---|---|---|
| A-6 | Competitions (Home) | `activities[]` for `award.issued` has `subject.type = "award"` with the **award** id and no competition id. W10 asks for a link to the subject, and the app cannot build `/competitions/{id}` from this. | The row is shown without a link. | Put `competition_id` in award subjects, or use the competition as the subject. |
| A-7 | Competitions (Home) | `activities[].actor.name` is the untranslated string `"system"` for automatic actions (for example `competition.closed`), including in Arabic. | Shown as «النظام» / "System". | Return `actor: null` or a localised name for system actions. |
| A-8 | Mobile core (`app/dependencies.dart`) | The device name ("{model} · {platform}") is known at start-up but not exposed, so `POST /devices` sends `device_name: null`. | `PushPermissionCubit` accepts a `deviceName` argument. | Expose the device name, for example on `ClientInfo`, when Firebase is wired. |
| A-9 | Mobile (`pubspec.yaml`) | There is no camera capture for the avatar or logo. | Gallery and file picking only (H-7). | Add `image_picker` if the camera is wanted, plus `NSCameraUsageDescription` on iOS. |
| A-10 | Mobile core (`app.dart`) | SCREENS §3.6 asks for a foreground push toast with an "Open" action (`PushService.onMessage`). | Not built; `NoopPushService` emits nothing. | Add it in `app.dart` together with `FirebasePushService`. |
| – | Platform | A-1 (`alerts[].params` is `[]`) and A-4 (empty `support` contacts) are still present. | M60 shows «بيانات التواصل غير متاحة حالياً…» above the contact form. | As A-1 and A-4. |

## 7. Phase 2: issuer (M33–M48)

Written on 2026-09-29 by the issuer feature engineer. Code in `lib/features/issuer/**` (data: the result report repository; domain: the creation form, the pre-publish checklist, the schedule preview, staged invitees; presentation: the screens, cubits and blocs below); tests in `test/features/issuer/` (unit, bloc, widget, route resolution, and an opt-in test against the running API). ARB keys use the `issuer` prefix (291 keys, AR and EN).

### 7.1 Routes and integration

| Path | Screen | Bloc / cubit | Notes |
|---|---|---|---|
| `/my-competitions` (tab) | `MyCompetitionsScreen` (M33) | `IssuedListBloc` | `issuerTabRoute(rootKey)` in the shell branch. Active / Drafts / Ended, 300 ms search, infinite scroll, refetch on resume and on return. Create button per S10; without `can_issue` the plan notice with `billing.managed_on_web` and no button. |
| `/my-competitions/new` (root nav) | `CreateCompetitionScreen` (M34–M37) | `CreateCompetitionCubit` | Also opened by Home's "Create competition". On save the wizard is replaced by the draft (M38), whoever opened it. A deep link without the permission shows Forbidden; without `can_issue`, the plan notice. |
| `/competitions/:id` | `IssuerCompetitionScreen` (M38, M43–M45) | `IssuerCompetitionCubit`, `PublishCubit`, `CancelCompetitionCubit` | Reached through the participant feature's `viewer_role` dispatch: `participantRoutes(issuerDetail: issuerDetailView, …)`. `issuerDetailView` offers the push explainer (M50) after a successful publish. |
| `/competitions/:id/live` | `IssuerLiveMonitorScreen` (M46) | `IssuerLiveBloc` | Through `participantRoutes(issuerLive: issuerLiveView)`. |
| `/competitions/:id/qa` | the shared `QaScreen` (features/qa) | – | It serves the issuer (announcements, replies to every thread), so the issuer feature has no second Q&A screen. |
| `/competitions/:id/offers` | `OffersLogScreen` (M47) | `OffersLogBloc` | Top-level routes from `issuerCompetitionRoutes(rootKey)`, appended after the shell. The shared `:id` routes have no such child, so these paths fall through to them (`test/features/issuer/issuer_routes_test.dart` checks the real router). Each screen checks the loaded `viewer_role` and goes back to the detail otherwise (role guard). |
| `/competitions/:id/participants` | `ParticipantsScreen` (M41) | `InvitationsCubit` | As above. |
| `/competitions/:id/invite` | `InviteParticipantsScreen` (M40) | `InviteParticipantsCubit` | As above. |
| `/competitions/:id/attachments` | `ManageAttachmentsScreen` (M42) | `ManageAttachmentsCubit` | As above. |
| `/competitions/:id/edit` | `EditCompetitionScreen` (M39) | `EditCompetitionCubit` | As above. |
| `/competitions/:id/award` | `AwardScreen` (M48 and the result PDF) | `AwardCubit`, `ResultReportCubit` | As above. |

- `app_router.dart` edits (issuer lines only): the My-competitions branch now uses `issuerTabRoute(rootKey)`, and `...issuerCompetitionRoutes(rootKey)` is appended after the shell. `issuerCompetitionChildRoutes(rootKey)` gives the same screens as relative children, if the `:id` owner prefers to nest them.
- **Dead code for the competitions owner:** `features/competitions/presentation/issued_competitions_screen.dart` (the M33 placeholder) and its keys `competitionsIssuedEmptyTitle` / `competitionsIssuedEmptyMessage` are no longer used.

### 7.2 Client decisions

| # | Decision | Why |
|---|---|---|
| I-1 | The wizard follows the SCREENS order: type, format and preset (M34), basics (M35), prices and schedule (M36), review (M37). It then sends **one** `POST /competitions` with `preset_code` and the preset's full `rules` plus the prices (G4). No server draft exists before step 4, and an unsaved-changes guard asks before leaving. | CD3/CD4: the web creates its draft after step 2, but mobile has no rules editor, so an earlier POST gains nothing. |
| I-2 | A preset is required on mobile. A direction and format without a preset (for example a sealed auction on the seeded catalog) shows «لا يوجد إعداد مسبق لهذا الاختيار في التطبيق…» and cannot continue. `// CONTRACT-GAP`: the API accepts `preset_code: null` (column defaults), but those defaults can break R1 for sealed competitions, and mobile cannot edit rules. | CD4. |
| I-3 | The closing time is required to save the draft on mobile (the API allows null at save). "Opens" is «فور النشر» (null) or a Riyadh date and time. Client hints mirror R5, R6, R14–R16 (10 minutes to 90 days). The server's errors win and jump back to their step; paths without a control (for example `rules.min_step_bps`) are listed in the form alert. | S7, R16 at publish. |
| I-4 | The draft's derived times show as a preview labelled «تقديري، يُثبَّت عند النشر» (final window, join deadline, latest close). | G3. |
| I-5 | Edit (M39): a draft changes title, description, category, region, prices and schedule (type, format and rules are web-only, with a note); a scheduled competition changes basics and schedule; a live one the title and description; other statuses go back to the detail. Draft PATCHes carry the full `rules` with `preset_code`. | W16, G4. |
| I-6 | Extend, BAFO round, award, revoke award and close without award are listed, disabled, in the action sheet with «متاح في لوحة التحكم على الويب.». One notice repeats this on the detail, the live monitor (extend) and M48 (revoke). Cancel (M44) is on the detail action sheet only, not on the monitor. | CD5, CD12. |
| I-7 | Result PDF on M48, in Arabic or English: `GET …/report?locale=` (202: poll every 3 s for up to 2 minutes; `report_not_available`: hidden), then a bearer download to the temporary directory and the system viewer, from which it is shared. SCREENS §3.7 lists "Result PDF: not in the MVP app", but the phase-2 issuer task asked for it. No `share_plus` dependency was added (the pubspec is not owned). | Task scope. |
| I-8 | Invite (M40): one request for every staged row (suggestions, pasted e-mails split on commas, spaces and new lines, vendors). Row errors (`errors."invitations.{i}.*"` and `details.item_codes`) mark the staged rows, and nothing is created. In `selected` mode a per-row "Cover the participation fee" switch is available only within `counts.free_slots`. `sponsorship_payment_required` shows a web notice and, in `selected` mode, "Send without covering fees" (re-posted with `sponsored: false`). No payment, price, link or URL. | §3.2, CD6. |
| I-9 | Participants (M41): the status filter works on the loaded list (at most 200, not paginated) with `meta.counts`. `invitation.updated` upserts the row at once and refetches counts and sponsorship at most once per second. Resend, revoke and remove are confirmed (revoke and remove with a red, named button); a resend 429 shows the daily limit. | S4, W17. |
| I-10 | Offers log (M47) in ascending `seq` (the API order) with "load more" at the end. `offer.accepted` is appended only once every page is loaded (otherwise paging brings it). A `seq` gap fetches `after_seq=lastSeq` (bounded). A `void` or `status` change reloads in place, so a sealed unlock fills in the amounts. | S4. |
| I-11 | Live monitor (M46): the S4 connection states without the submit rules. After a 3 s grace it shows «جارٍ إعادة الاتصال…»; with no socket for 10 s it polls every 3 s in the last 5 minutes, else every 10 s. No heartbeat (issuer). A leader change is a polite live region throttled to 10 s. A status, award or BAFO change refetches the competition. | S4, S9, CD10. |
| I-12 | Documents (M42): kind `document` only, pre-checked for type and 100 MB. Uploads are allowed while draft, scheduled or live and deletes while draft or scheduled, both gated on `permissions.can_edit`. `// CONTRACT-GAP`: `permissions` has no attachments flag; `can_edit` matches the statuses API.md §1.4 allows. Links and invitation documents get a web notice. | API.md §1.4. |
| I-13 | Mutations (save, publish, cancel, delete, invite, resend and revoke, upload and delete) are disabled while offline; navigation stays available. Nothing is optimistic: publish, cancel, delete, invite and edits wait for the server's answer and apply it. | S8, CD9. |
| I-14 | The sponsorship card (M38, M41) shows the mode, the cap and the pass counters only. `unit_price_minor` is never rendered, and the card carries «تُدار رسوم المشاركة ومدفوعاتها من لوحة تحكم بافو على الويب.». | §3.2. |

### 7.3 Checked against the running API (local dev DB)

The opt-in test `test/features/issuer/issuer_live_api_test.dart` (`BAFO_LIVE_API`, `BAFO_ISSUER_EMAIL` and `BAFO_DEMO_PASSWORD` from the environment) drives the issuer cubits with the real `ApiClient`, as the Issuer Co owner:

- creates a draft from `standard_live_tender` with prices and a close (201, 11 rules-summary lines);
- edits it with the full rules (the start price changes, the other rules are kept);
- invites an e-mail (201), then re-invites it with another address (422, row 0 `invitation_duplicate`, nothing created);
- lists the invitations;
- publishes with one invitation of two (`min_participants_not_met`; `details.required` and `details.current` give "1 more");
- uploads a PDF document and deletes it, then deletes the draft (204);
- reads the live monitor snapshot, the offers log, the award with its standings, and the Arabic result PDF (ready, bearer download, a real `%PDF` file).

Phone-size RTL and LTR renders of M33, M34–M35, M38 (draft, live, English), M40, M41, M46, M47 and M48 were checked from widget renders with the real fonts. They showed one RTL overflow (the online-count row) and amounts, ranks and contacts that were not on the start edge; all are fixed. No APK was built, to spare shared memory.

### 7.4 API notes

No contract mismatch was found in the issuer endpoints and events used: `GET /competitions?role=issuer`, the issuer projection, `POST`/`PATCH`/`DELETE /competitions`, publish, cancel, suggestions, invitations (index, store, destroy, resend), attachments (index, store, destroy), sponsorship show, `GET …/live`, `GET …/offers`, `GET …/offers/log`, `GET …/award`, `GET …/report`, `GET /vendors`, `live.updated`, `offer.accepted`, `competition.updated` and `invitation.updated`. Observations only:

- `GET /competitions/{id}/live` on a **draft** answers 404 `not_found`; API.md §1.6 says "available from publish onward" without a code. The monitor never calls it for drafts.
- `GET …/offers/log` and `GET …/comments` on a draft answer 200 with empty lists.
- `GET …/report` answers 202 `pending` for a locale without a current file yet (for example `en` on a competition closed in Arabic), then 200 once generated, as documented.

## 8. Mobile review and hardening (2026-09-29)

Written by the mobile reviewer after phase 2. The whole app was checked against SCREENS.md and API.md: code review, l10n audit, and a run of the debug APK on the Pixel API 34 emulator against the local API and Reverb. The run signed in as Supplier A (participant) and Issuer Co's owner (issuer), in Arabic and in English. Screenshots are `apps/mobile/docs/screenshots/emulator_*.png` (the README lists them).

Result: `flutter analyze` finds no issues, `flutter test` passes 547 tests (4 opt-in live tests skipped), and `flutter build apk --debug` succeeds.

### 8.1 What the emulator run covered

- **Supplier A:** welcome, sign in, home, the participating list, an invitation (M17) and its join sheet (M18, not submitted), and the live tender «توريد مستلزمات مكتبية للعام المالي 2027» (M21).
  - In the live room (M23) it placed two accepted offers (248,000.00 then 245,000.00 SAR) through the confirm sheet (M24), with the received toast and the new bound.
  - Also: My offers (M28), Q&A (M31), notifications and a notification deep link into the auction room, the account hub, plan status (M58), settings, the language switch and sign-out.
- **Issuer Co owner:** home, My competitions (M33), the detail (M38) and its action sheet, the live monitor (M46), the offers log (M47), participants (M41), and the creation wizard (M34) up to its unsaved-changes guard, which discarded it. The same screens were then checked in English.

### 8.2 Defects fixed

| # | Defect (seen on the emulator unless noted) | Fix |
|---|---|---|
| R-1 | In Arabic, the amount typed in the offer composer sat on the far left while «ر.س» stayed on the far right. | `BafoTextField` aligns LTR content next to its affix: prefix → left, suffix → right. Other fields are unchanged. |
| R-2 | After an offer was confirmed, the closing sheet gave focus back to the amount field. The keyboard reopened and covered the room and the receipt. | The composer unfocuses before opening the confirm sheet. |
| R-3 | `Ltr` islands given the full width were left-aligned in Arabic, for example the timestamps in My offers. | `Ltr` now sits on the start edge of the surrounding direction. Unbounded constraints still shrink-wrap. |
| R-4 | `SectionHeader` added its own 16 dp start padding inside page-padded lists, so section titles were indented from their cards (detail, monitor, award). | The padding is removed; the header is documented for page-padded content. |
| R-5 | Composer hints were 8 dp off the field's helper text. | The start inset is now the content padding plus the input gap (20 dp). Measured on the device: the lines now start at the same position. |
| R-6 | English home: the actor's name in an activity line took the time into its run (`40 · سارة المالكة minutes ago`). | The actor is wrapped in a first-strong isolate. The issuer's `participantLabel` («المتنافس 7 · name») is isolated too. `bidiIsolate` is added to `widgets/ltr.dart`. |
| R-7 | English bottom bar: "My competitions" wrapped to two lines and pushed its icon up. | New key `navMyCompetitionsTab` (ar «منافساتي», en "Issuing") for the tab. The page title keeps `navMyCompetitions`. |
| R-8 | After a sign-out, the next user signed in on the previous user's page (`/login?from=/account`). | `SessionUnauthenticated(signedOut: true)` after an explicit sign-out, and `authRedirect` keeps no `from` then. An expired session still returns to its page. |
| R-9 | Settings mixed two heading styles. Language and push had large titles; legal and about had the grey group labels. | `AccountSectionLabel` is shared by `AccountSection` and the two settings headings. |
| R-10 | My competitions put search above the segments; the participating list does the reverse. | Both lists now show segments first, then search. |
| R-11 | (Code review.) The live room kept its 20 s heartbeat while My offers or the rules page covered it. S4 says to stop when the page is hidden. | The room follows `TickerMode`: the navigator turns tickers off under an opaque page, while the confirm sheet keeps the room visible. The heartbeat stops under another page and restarts on return. A widget test covers this. |
| R-12 | (Accessibility.) Screen readers heard names twice ("Issuer Co, Issuer Co, …"): the avatar's label came before the same name as text. | `OrgAvatar(decorative: true)` wherever the name is shown beside it (9 places). The standalone profile photo keeps its label. |
| R-13 | (iOS consistency.) `file_picker` image picking (avatar, logo) had no `NSPhotoLibraryUsageDescription`. | Added to `Info.plist`, localised in `ar.lproj` and `en.lproj`. |
| R-14 | (l10n.) Three unused ARB keys. | `commonActionsContinue`, `competitionsParticipatingOpenDetails` and `liveStandingPending` are removed from both files. |

Tests were added or updated: the redirect after a sign-out, the sign-out state, the heartbeat under another page, the isolated actor on home, and the isolated names in the issuer screens.

### 8.3 Left for the owner: dead files (deletion was not permitted to the reviewer)

These files are unreachable since phase 2 (the participant routes match `/competitions/:id`, `live` and `qa` first). They still compile and are still wired, so nothing breaks. Delete them together:

- `lib/features/competitions/presentation/competition_overview_screen.dart`, `competition_overview_cubit.dart` and `competition_routes.dart`;
- the `competitionDetailRoute(rootKey)` child of the `/competitions` shell branch in `lib/core/router/app_router.dart`;
- the `IssuerCompetitionNotIssuer` fallback in `issuer_competition_screen.dart`, which can render a `ForbiddenState` instead;
- the `CompetitionOverviewCubit` group in `test/features/overview_and_billing_cubits_test.dart` (keep the `SubscriptionStatusCubit` group);
- `lib/features/competitions/presentation/issued_competitions_screen.dart` and `participating_competitions_screen.dart` (phase-1 placeholders), with the ARB keys `competitionsIssuedEmptyTitle` and `competitionsIssuedEmptyMessage`;
- `lib/features/profile/presentation/profile_screen.dart` (the phase-1 Account placeholder).

### 8.4 Checked and consistent

- **l10n:** both ARB files have the same 1004 keys and placeholders. No «مناقص» as a noun, no old brand, no exclamation marks, and no hard-coded UI strings. «بـ» before "https://" (`validationUrlHttps`) is the only kashida, and it is the standard spelling before a Latin word.
- **RTL:** no `EdgeInsets.only(left/right)`, `Alignment.*Left/Right`, `TextAlign.left/right` (except the new affix rule) or `Positioned` in the UI. On the emulator, back arrows, chevrons, the reply icon, the wizard stepper and page dots mirror, and the direction glyphs do not. Amounts render as LTR islands («248,000.00 ر.س»).
- **Accessibility:** every `IconButton` has a tooltip, the bottom tabs read "name, Tab n of 5" with the unread count, and live regions cover the standing banner, the countdown thresholds and the monitor's leader.
- **States and entitlement:** the loading, error, empty, forbidden and not-found states are present on the lists and details. There is no purchase UI; plan status, the plan card and the home alerts show only the managed-on-web text.
- **Navigation:** a notification's route opens the live room directly (S11), and the unsaved-changes guard works in the wizard.

### 8.5 API observations (no contract change requested)

- **A-11 (Notifications):** the notification bodies compose user-provided titles and names without isolates, for example "Supplier C joined “صيانة وتشغيل المباني الإدارية 2027”." They render acceptably today. An Arabic organisation name inside an English body, or a Latin name inside an Arabic body, could be reordered by the bidi algorithm. Suggestion: wrap the interpolated values in U+2068 … U+2069 on the server, as the app does for its own sentences (H-4, R-6).
- A-1, A-4, A-6 and A-7 are still present: `alerts[].params` is `[]`, the `support` contacts are empty, and award activities carry no competition id and a `"system"` actor.

### 8.6 Known issues left

- The app still carries the Material Arabic "Dismiss" label «رفض» on the sheet drag handle and «تمويه» on the scrim. These are Flutter's own `MaterialLocalizations`; overriding them would need a custom delegate.
- ~~The participant list card has no "Live" format chip.~~ Fixed on 2026-09-30 (§9): every card shows its format.
- The emulator's session token for Supplier A was not revoked because the emulator was killed while signed in. It is a demo token on the local database only.

## 9. Content, glossary and loose ends (2026-09-30)

Written by the content and glossary engineer (final hardening round).

- **Dead phase-1 files deleted (§8.3):** `competition_overview_screen.dart`, `competition_overview_cubit.dart`, `competition_routes.dart`, `issued_competitions_screen.dart`, `participating_competitions_screen.dart` and `features/profile/presentation/profile_screen.dart`; the `competitionDetailRoute(rootKey)` child of the `/competitions` shell branch; the ARB keys `competitionsIssuedEmptyTitle` / `competitionsIssuedEmptyMessage`. `IssuerCompetitionNotIssuer` now renders `ForbiddenState` (the participant routes dispatch by `viewer_role` first, so it is a guard only). The `CompetitionOverviewCubit` tests are gone; the `SubscriptionStatusCubit` tests moved to `test/features/subscription_status_cubit_test.dart`.
- **Team FAB outline:** not a focus style. `flutter_test` sets `debugDisableShadows`, and a disabled shadow is painted as a solid stroke of `2 × elevation` in the theme's `shadowColor` (charcoal): the M3 default FAB elevation of 6 dp gave the thick dark ring in `phase2_team_ar.png`. `BafoTheme` now has a `floatingActionButtonTheme` (green container, 12 dp card radius, brand label, elevation 2 in every state, so focus adds no heavier shadow). The screenshot was re-rendered with real shadows (`debugDisableShadows = false`). For future renders, set `debugDisableShadows = false` in the harness (and back to `true` before the test ends).
- **Participant list cards (§8.6):** the format chip is always shown (live «مباشرة» or sealed), as on the issuer's cards.
- **API loose ends:** A-1 fixed (`alerts[].params` is `{}` when empty), A-3 documented (`activities[].id` is opaque, API.md §2.13), A-4 fixed for demos (`PlatformDemoSeeder` sets placeholder `store_links` and `support` on `bafo.example`; on an existing database run it with `php artisan db:seed --class='App\Modules\Platform\Database\Seeders\PlatformDemoSeeder'`). The seeded Arabic legal documents open with an Arabic draft notice.
- **Copy:** the reserve price is «السعر المستهدف» / "Target price" (tender) and «الحد الأدنى المقبول» / "Reserve price" (auction) in every app (server rules summary, validation, admin, PDF); direction-neutral texts say «السعر المستهدف أو الحد الأدنى المقبول» / "target or reserve price". The ARB `other` branches and `errorsAwardReserveConfirmationRequired` follow it. `qaAuthorUnknown` is «متنافس». Arabic tanween is written «اً» everywhere (as in SCREENS and the web), and «عرض» is no longer used for "show" (`liveStatusHidden`).
