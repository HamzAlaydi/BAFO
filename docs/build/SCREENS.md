# BAFO client screens and flows (web and mobile)

Status: **binding for `apps/web` and `apps/mobile`**, inside the contract. Written 2026-09-29 against `BRIEF.md`, `ARCHITECTURE.md`, `API.md`, `CONVENTIONS.md` and the scaffolds in `apps/web` and `apps/mobile`.

**Order of precedence:** `BRIEF.md` > `ARCHITECTURE.md` = `API.md` = `CONVENTIONS.md` > this file > `docs/02_design/*`. This file never changes an endpoint, a payload, a permission, an error code or an i18n rule. Where it needed something the contract does not provide, it says so in §6 and chooses the simplest client-side option.

| Section | Contents |
|---|---|
| §0 | Client decisions |
| §1 | Shared UX rules (S1–S11): RTL, mode-aware copy and colour, server time, live data, offers, formatting, forms, page states, accessibility, permissions, notification routing |
| §2 | Web (Nuxt 4): architecture, route map, navigation, page specs, end-to-end flows |
| §3 | Mobile (Flutter): shell, route tree, entitlement-only rule, screen inventory, blocs, realtime, push deep links |
| §4 | Component inventory: token roles, web UI kit, domain components, Flutter widget kit |
| §5 | i18n namespaces and the direction-variant keys |
| §6 | Contract gaps and requests to other owners |
| §7 | Traceability: legacy screens, MVP scope, deferred items |

**How to read this file**

- IDs: `W##` is a web page, `M##` a mobile unit (screen, sheet or dialog), `S#` a shared rule, `CD#` a client decision.
- API calls are written `METHOD /path`, relative to `/api/app/v1`. Resource names (`Competition`, `ParticipantLiveSnapshot`, …) are those of API.md §2.
- Realtime events use their Echo name without the leading dot, on the channel named in API.md §5.
- Page states: **L** loading, **E** empty, **X** error, **F** forbidden, **N** not found.
- i18n namespaces are the CONVENTIONS §6.2 areas. Web keys are dot paths in `apps/web/i18n/locales/{ar,en}.json`; ARB keys are the same paths in lowerCamelCase.
- Copy shown here in «Arabic» / "English" is draft wording for the R3 writer. Key names are binding; wording is not.

---

## 0. Client decisions

| # | Decision | Why |
|---|---|---|
| CD1 | **One competition detail route per surface for every viewer.** The page loads `GET /competitions/{id}` and switches on `viewer_role` (`issuer`, `participant`, `invitee`). | Notification `route` values are role-free (CONVENTIONS §4.3), and the server owns the projection (ARCHITECTURE §8.2). |
| CD2 | **Web auth pages live under `/{locale}/auth/*`.** `/login` and `/register` from the scaffold become redirects to `/auth/login` and `/auth/register`. | The e-mail links of ARCHITECTURE §11.5 and CONVENTIONS §4.3 point at `/auth/verify` and `/auth/accept-invite`. |
| CD3 | **The web creation wizard has 8 steps.** Steps 1–2 (type, basics) run client-side on `/dashboard/competitions/new`; finishing step 2 calls `POST /competitions`, and steps 3–8 edit the draft with `PATCH`. | `POST /competitions` requires `title`, `category_id`, `region_id`, `direction` and `format`. A server draft makes the wizard resumable. |
| CD4 | **Full rules on the web only.** Mobile creates a draft from a preset plus prices and schedule. | mvp_linecut R5-21. |
| CD5 | **Mobile issuer scope:** create, edit, invite (suggestions, e-mail, vendors), attach documents (kind `document` only), publish, cancel, delete draft; a read-only live monitor, offers log and award view. **Web only:** extend, BAFO round, award, revoke award, close without award, advanced rules, external links, invitation documents, sponsorship configuration, every payment, integrations, vendors management. | mvp_linecut "Flutter app" row, B-MOB-06, R5-23 to R5-25, R4-28. |
| CD6 | **Mobile is entitlement-only on iOS and Android alike.** No plan list, price, coupon, checkout, trial button, store link to billing, or URL to the web billing pages. Any action that needs payment shows `billing.managed_on_web`. | CONVENTIONS §5.1, R2R3 D14, R4 §13. Treating both platforms the same keeps one code path. |
| CD7 | **Tokens from e-mail links** are read from the URL fragment, removed from the address bar with `history.replaceState`, kept only in `sessionStorage` (`bafo.pending_invitation`, `bafo.pending_team_invitation`) for the duration of the flow, and sent in JSON bodies. They never go into a query string, a log, an analytics event or a store that persists across sessions. | ARCHITECTURE D11. |
| CD8 | **A page the user lacks permission for renders a Forbidden state instead of redirecting.** Navigation hides entries the user cannot use. | Deep links from notifications and shared URLs stay predictable. |
| CD9 | **No optimistic UI** for offers, awards, payments, publish or any state transition. The UI shows a busy state and waits for the response (and the realtime snapshot). Lists and simple edits (mark notification read, rename) may update optimistically. | The server clock and state machine are authoritative. |
| CD10 | **Live data is applied only from server snapshots, ordered by `v`.** The client never derives rank, leader, visibility, entitlement or bound amounts. | CONVENTIONS §7. |
| CD11 | **Dark mode:** the web supports it through the existing tokens (optional polish); mobile is light-only in v1. | ARCHITECTURE §18, R2R3 D11. |
| CD12 | **Mobile issuer cancel** stays available (legacy "close bid" parity) from the competition detail action sheet, in `scheduled`, `live` and `bafo_round`. It is not offered on the live monitor. | B-MOB-06 kept "close"; the monitor is read-only (R5-23). |
| CD13 | **Participant offer bounds are pre-checked** against the server-given `required_next_amount_minor`, `start_price_minor` and `amount_granularity_minor` of the latest snapshot, to show an inline error before sending. The server still decides; a server rejection always wins. | Faster feedback without recomputing any rule. |

---

## 1. Shared UX rules (web and mobile)

### S1 RTL-first layout and bidi

- **Arabic is the default and the design reference.** Screens are drawn RTL first and checked LTR second.
- **Logical layout only.** Web: `ms-* me-* ps-* pe-* start-* end-* text-start text-end`, never `left`/`right` (the scaffold's `rtl-guard.spec.ts` enforces it). Flutter: `EdgeInsetsDirectional`, `AlignmentDirectional`, `BorderDirectional`, `PositionedDirectional`.
- **Mirror** in RTL: back and forward arrows, list chevrons, stepper order, progress bars, carousels, send and reply icons, the side navigation (start edge: right in Arabic), drawers (open from the end edge).
- **Never mirror:** the logo and mark; the direction glyphs ↓ (tender) and ↑ (auction); up/down arrows in offer history; clocks and timers; checkmarks; charts' time axis (left to right).
- **LTR islands** inside Arabic text: amounts, percentages, `reference_no` (`BAFO-T-2026-000123`), invoice numbers, e-mails, URLs, phones (`+9665…`), CR and VAT numbers, OTP codes, client ids, API keys, secrets, JSON. Web: `<bdi>`, and `dir="ltr"` on the inputs of those types (the field keeps the page's alignment). Flutter: `Directionality(textDirection: TextDirection.ltr)` for widgets, Unicode isolates (U+2066 … U+2069) inside composed strings.
- **Text length:** budget ±35% between AR and EN. Never truncate amounts, dates or codes. Titles may ellipsize with the full title in a tooltip (web) or semantics label (Flutter).
- **Arabic typography:** no letter-spacing, no tatweel, line-height ≥ 1.5, body text ≥ 14 px / 14 sp. Fonts: Inter first with IBM Plex Sans Arabic as fallback on the web (scaffold `--font-sans`); the same pair on mobile (scaffold `typography.dart`).
- **Switching language** keeps the user on the same page: web swaps the `/{locale}` prefix; mobile updates `LocaleCubit`. When signed in, both also send `PATCH /me {locale}`. Server-localised content (rules summaries, lookup names, notification titles, legal text) is refetched after the switch, because `Accept-Language` produces it.

### S2 Mode-aware copy and colour (tender versus auction)

**Glyph = direction. Colour = the viewer's standing.** Nothing is coloured by whether a price goes up or down.

| Element | Rule |
|---|---|
| Direction chip | Tender: ↓ glyph + «مناقصة · الأقل سعراً يفوز» / "Tender · Lowest offer wins". Auction: ↑ glyph + «مزايدة · الأعلى سعراً يفوز» / "Auction · Highest offer wins". **Both use the same neutral container** (`neutral-soft`), glyph in charcoal. Always glyph and text. |
| Format chip | Live: «مباشرة» / "Live". Sealed: lock icon + «بظرف مغلق» / "Sealed". |
| Standing: leading | `leading` tone (green-700 on green-50) + check icon + text «عرضك هو العرض المتصدر» / "Your offer is the leading offer". |
| Standing: not leading | `outbid` tone (amber-700 `#8C5802` on amber-50) + alert icon + the direction hint (`live.status.not_leading.tender|auction`). **Never red.** |
| Standing: rank | Neutral tone: «ترتيبك 2 من 5» / "You are ranked 2 of 5". |
| Standing: hidden | Neutral info text: «استُلم عرضك. لا تعرض هذه المنافسة الترتيب.» / "Your offer was received. This competition does not show standings." |
| Price movement (history, ladder, issuer tables) | Neutral grey, the numeric arrow (↓ or ↑) and a signed percentage. The issuer's `change_ratio_bps` and `improvement_vs_start_bps` are server-signed (positive = better for the issuer) and are labelled "Improvement" / «التحسّن», so the label never depends on client arithmetic. |
| Red | Destructive buttons, errors, and the `cancelled` status only. Never "outbid", "closing soon" or a price move. |
| Copy variants | Direction-dependent keys have `.tender` and `.auction` sub-keys (web) or one ICU `select` on `direction` (ARB). The client picks by `competition.direction`, never by comparing numbers. |
| Nouns | UI chrome uses the glossary: «المتنافسون» / "Participants". Explanatory copy may use the mode noun: tender «الموردون» / "Suppliers", auction «المزايدون» / "Bidders". Forbidden: «مناقص» for people, the old brand names, "Bid" for a competition, «أقل سعر» / "Lowest price" as a generic label. |

**Competition status visual.** One pure function per client maps the server state to a chip. Web: `competitionStatusVisual(status, phase, effectiveCloseAt, extensionCount, serverNow)` in `app/utils/competition-status.ts`. Flutter: `CompetitionStatusVisual.of(...)` in `lib/features/competitions/domain/`. Both are unit-tested against this table.

| `status` | `phase` | Key (`competitions.status.*`) | AR | EN | Tone | Icon |
|---|---|---|---|---|---|---|
| `draft` | – | `draft` | مسودة | Draft | neutral | pencil |
| `scheduled` | – | `scheduled` | مجدولة | Scheduled | info | calendar-clock |
| `live` | `initial`, `open` | `live` | مفتوحة للعروض | Open for offers | primary-soft | circle-dot |
| `live` | `final_window` | `final_window` | فترة التسعير النهائية | Final pricing window | primary-soft, pulsing dot (static under reduced motion) | timer |
| `live` | `sealed` | `live_sealed` | مفتوحة · عروض مغلقة | Open · sealed offers | primary-soft | lock |
| `closed` | – | `closed` | قيد التقييم | Evaluation | neutral | clipboard-check |
| `bafo_round` | – | `bafo_round` | جولة العرض النهائي | BAFO round | inverse (charcoal, white text) with the mark | brand mark |
| `awarded` | – | `awarded` | تمت الترسية | Awarded | primary solid | trophy |
| `not_awarded` | – | `not_awarded` | أُغلقت دون ترسية | Closed without award | neutral | circle-slash |
| `cancelled` | – | `cancelled` | ملغاة | Cancelled | danger-soft | ban |

Two **overlay pills** sit next to the status chip while `live`:

- `competitions.status.closing_soon` «تُغلق قريباً» / "Closing soon", warning tone, when `effective_close_at − serverNow < 10 min` (client constant `CLOSING_SOON_SECONDS = 600`, the first value of setting `bidding.closing_soon_minutes`).
- `competitions.status.extended` «مُدّد الإغلاق» / "Extended", info tone, when `extension_count > 0`.

**Other chips**

| Chip | Values (key → AR / EN, tone) |
|---|---|
| Invitation status (issuer, `invitations.status.*`) | `draft` مسودة / Draft (neutral) · `sent` أُرسلت / Sent (info) · `viewed` اطُّلع عليها / Viewed (info) · `joined` انضم / Joined (primary-soft) · `declined` اعتذر / Declined (neutral) · `revoked` أُلغيت / Revoked (neutral) · `expired` انتهت / Expired (neutral) |
| Access state (participant and invitee, `invitations.access.*`) | `join_required` بانتظار انضمامك / Join required (info) · `plan_required` تتطلب باقة / Plan required (warning) · `full` منضم / Joined (primary-soft) · `read_only` للاطلاع فقط / Read only (neutral) · `unavailable` غير متاحة / Unavailable (neutral) |
| Fees covered (participant only, `sponsorship.badge.fees_covered`) | «رسوم مغطّاة» / "Fees covered" (info tone, ticket icon). Shown only to the covered participant, never to others. |
| Coverage (issuer, `sponsorship.coverage.*`) | `sponsored` مغطّاة منكم / Covered by you (info) · `own_plan` باقة المتنافس / Own plan (neutral) · `none` غير مغطّاة / Not covered (neutral) |
| Pass status (issuer, `sponsorship.pass_status.*`) | `pending`, `reserved`, `joined`, `released`, `unused`, `void` (neutral, `joined` primary-soft) |
| Result (participant, `award.outcome.*`) | `won` تمت الترسية عليكم / Awarded to you (primary solid) · `not_selected` لم يتم اختياركم / Not selected (neutral) · `not_awarded` أُغلقت دون ترسية / Closed without award (neutral) |
| Offer stage (`offers.stage.*`) | `sealed` مغلق · `initial` أولي · `live` مباشر · `bafo` نهائي (neutral) |

### S3 Server time and countdowns

The same algorithm on both clients (ARCHITECTURE §9.6). Web: `useServerTime()` (scaffold). Flutter: `ServerClock` (scaffold).

1. **Samples.** The HTTP layer records `t_send` and `t_recv` for every app v1 call and reads `meta.server_time`. `GET /time` is also called when a detail or live screen opens, every 60 s while a countdown is on screen, and on resume (`visibilitychange` → visible; `AppLifecycleState.resumed`).
2. **Offset** = `server_time − (t_send + t_recv) / 2`, using the **median of the last 3 samples**. Realtime payloads carry `server_time` too, but they are **not** offset samples: their one-way latency is unknown.
3. **Now** = device time + offset. Flutter measures elapsed time with a monotonic `Stopwatch` so that a device clock change does not jump the countdown.
4. **Remaining** = target − now, floored at 0. Live screens re-render every 250 ms and only repaint when the displayed second changes. List rows may tick every second.
5. **Targets:** scheduled → `bidding_opens_at` («تبدأ خلال» / "Opens in"); live → `effective_close_at` («تُغلق خلال» / "Closes in"); BAFO round → `bafo.cutoff_at`; invitee → `invitation_cutoff_at` («الانضمام قبل» / "Join before"). When auto-extend is on, `hard_stop_at` is shown as «أقصى موعد للإغلاق» / "Latest possible close".
6. **Format** (CONVENTIONS §9.1): ≥ 24 h "N days HH:MM" with the Arabic 6-form plural; < 24 h `HH:MM:SS`; tabular figures; warning tone in the last 5 minutes; no flashing, no red.
7. **Slow link:** if the last sample's round trip exceeds 2 s, show `live.hint.slow_connection` «الاتصال بطيء، فقدّم عرضك مبكراً.» / "Your connection is slow. Allow extra time."
8. **Last 10 seconds:** show `live.hint.server_timing` «يُعتمد وقت استلام العرض على خادم بافو.» / "Offers are timed on receipt by the BAFO server."
9. **At zero:** show «جارٍ الإغلاق…» / "Closing…" and disable the composer. **Do not show "Closed" until the server says so** (a snapshot with a new status, an `offer_closed` error, or a refetch), because an extension can still arrive.
10. **Accessibility:** the countdown has `role="timer"` and is not announced every second. Announcements at 10 min and 5 min (polite), 1 min and "closed" (assertive).

### S4 Live data (both clients)

**Channels** (API.md §5, ARCHITECTURE §9.2):

| Viewer | Channel | Events |
|---|---|---|
| Issuer member | `private-competition.{competitionId}` | `live.updated` (IssuerLiveSnapshot), `offer.accepted`, `competition.updated`, `comment.created`, `invitation.updated` |
| Participant member | `private-competition.{competitionId}.participant.{me.organization.id}` | `live.updated` (ParticipantLiveSnapshot), `competition.updated`, `comment.created` |
| Invitee | none (channel auth would fail) | refetch on focus and after join |
| Every signed-in user | `private-user.{me.user.id}` | `notification.created`, `notifications.unread_count` |

**One subscription per open competition.** The detail parent (web) or a ref-counted `CompetitionChannelHub` (Flutter, in `lib/core/realtime/`) owns it; the tabs and screens of that competition share it.

**Resync** (ARCHITECTURE §9.4), on first connect, reconnect and resume:

1. Subscribe and buffer incoming events.
2. `GET /competitions/{id}/live` (issuer or participant snapshot).
3. Apply the snapshot; then apply buffered snapshots with `v > snapshot.v`.
4. Keep `lastAppliedV`. Drop any snapshot with `v ≤ lastAppliedV`. The `live` object in a `POST …/offers` response goes through the same guard.

**Other events**

- `offer.accepted` (issuer): append by `seq`, de-duplicate by `id`. If `seq > lastSeq + 1`, call `GET /competitions/{id}/offers/log?after_seq={lastSeq}` and merge.
- `competition.updated`: refetch `GET /competitions/{id}`; also refetch attachments when `fields` contains `attachments`, and invitations when it contains `invitations`.
- `comment.created`: insert into the Q&A tree (de-duplicate by `id`; a reply goes under its `parent_id`).
- `invitation.updated` (issuer): upsert the invitation row; refetch `GET /competitions/{id}/sponsorship` at most once per second.
- A snapshot whose `status` differs from the loaded competition's `status`, or whose `last_change.kind` is `status`, `award` or `bafo`, triggers a competition refetch, because `permissions` change with the status.

**Connection states** (shown by `ConnectionIndicator` / `ConnectionBanner`):

| State | Enters when | UI | Submit |
|---|---|---|---|
| `connecting` | screen opens | small spinner in the indicator | enabled after the first snapshot |
| `connected` | subscribed and resynced | green dot, «مباشر» / "Live" | enabled |
| `grace` | socket dropped, < 3 s | nothing new | enabled |
| `reconnecting` | dropped ≥ 3 s, fallback not yet running | banner «جارٍ إعادة الاتصال…» / "Reconnecting…" | **disabled** |
| `polling` | no socket for 10 s | banner «التحديثات المباشرة متأخرة، ونحدّث كل {n} ثوانٍ.» / "Live updates are delayed. Refreshing every {n} seconds." Poll `GET /live` every 3 s when fewer than 5 minutes remain, otherwise every 10 s. | enabled while the last successful poll is younger than 2 intervals |
| `offline` | browser offline / no network | banner «لا يوجد اتصال بالإنترنت.» / "You are offline." | **disabled** |

Submitting is always safe (the server decides and requests are idempotent), so the disabled states only avoid offers built on stale bounds.

**Heartbeat.** While a participant's live screen is visible: `POST /competitions/{id}/live/heartbeat` on open and every 20 s; stop when the page is hidden or the app is paused.

### S5 Offer submission (participant)

**Composer content**

- Bound hint from `required_next_amount_minor` when not null: `offers.hint.required_next.tender` «يجب ألا يزيد عرضك التالي عن {amount}.» / "Your next offer must be {amount} or lower." — `.auction` «يجب ألا يقل عرضك التالي عن {amount}.» / "Your next offer must be {amount} or higher."
- First offer with a start price: `offers.hint.start_price.tender` «لا يتجاوز سعر السقف {amount}.» / "Must not exceed the ceiling price of {amount}." — `.auction` «لا يقل عن سعر الافتتاح {amount}.» / "Must not be below the opening price of {amount}."
- Granularity: `amount_granularity_minor = 100` → «بالريال دون هللات» / "Whole riyals only"; `1` → two decimals allowed.
- `common.prices_exclude_vat` under the input.
- A "Use {amount}" chip fills the input with `required_next_amount_minor` (no stepper arrows, which would imply a good direction).

**Input.** Parse with `parseAmountToMinor` (web `utils/money.ts`; Flutter `core/money`): Arabic-Indic and Persian digits accepted, commas allowed, at most 2 decimals, no floats. Inline pre-checks (CD13): > 0; a multiple of `amount_granularity_minor`; on the right side of `required_next_amount_minor` and of `start_price_minor` for the viewer's direction as given by the server bound rule (API.md §2.8: tender ≤, auction ≥).

**Confirm step (always).** A dialog (web) or bottom sheet (mobile) shows the amount large (LTR island), "excl. VAT", the direction reminder, and:

- sealed: «عرضك مغلق ولا يراه غيرك، ويمكنك تعديله حتى الإغلاق.» / "Your offer is sealed. Nobody else can see it, and you can revise it until closing.";
- BAFO: «هذا عرضك النهائي الوحيد ولا يمكن تعديله.» / "This is your only final offer. It cannot be changed."

**Idempotency** (CONVENTIONS §4.2). An `Idempotency-Key` (UUID v4: `crypto.randomUUID()` / `Uuid().v4()`) is created when the confirm step opens. It is reused for every retry of that intent (timeout, network error, 5xx). A new intent (different amount, dialog reopened) gets a new key. After a network failure the client retries once after 1 s with the same key, then shows «لم نتأكد من استلام عرضك. أعد المحاولة.» / "We could not confirm your offer. Try again." with the same key.

**Responses**

| Result | UX |
|---|---|
| 201, or 200 with `Idempotent-Replayed: true` | Apply `data.live` (v-guarded). Toast `offers.submitted` «استُلم عرضك في {time}» / "Offer received at {time}", with `accepted_at` as `HH:mm:ss.SSS` Riyadh time. |
| `offer_step_not_met` | Inline error with `details.required_amount_minor` and a "Use this amount" action. |
| `offer_start_price` | Inline error with `details.start_price_minor` (ceiling or opening price wording by direction). |
| `offer_granularity` | Inline error with `details.granularity_minor`. |
| `offer_amount_invalid`, `offer_amount_too_large` | Inline error (`details.max_amount_minor`). |
| `offer_outlier_confirm_required` | Outlier dialog: «هذا العرض {أقل/أعلى} من عرضك الحالي بنسبة {pct}.» / "This offer is {pct} {lower/higher} than your current offer." (`pct = change_bps / 100`, lower/higher from the two amounts). Confirm re-sends with `confirm_outlier: true` and a **new** key. |
| `offer_bafo_worse_than_reference` | Inline error with `details.reference_amount_minor` (`bafo.rule.tender|auction`). |
| `offer_not_accepting` | Banner: before opening, «تبدأ العروض في {time}» / "Offers open at {time}" (from `details.opens_at`); otherwise the status message. Refetch. |
| `offer_closed` | Closed state; refetch the competition. |
| `offer_not_shortlisted`, `offer_bafo_already_submitted` | Refetch; show the BAFO panel state. |
| `too_many_requests` | Disable submit for `details.retry_after_seconds` (or `Retry-After`) with a countdown on the button. |
| `not_a_participant` | Refetch the competition (the viewer role changed). |
| `idempotency_key_reused` | Should not happen. Log it, create a new key and ask the user to confirm again. |

The composer never shows other participants' amounts unless the snapshot carries them (`leading_amount_minor`, `ladder`).

### S6 Formatting

**Digits.** Western 0–9 in both languages, everywhere (CONVENTIONS §6.1). Every input normalises Arabic-Indic (٠–٩) and Persian (۰–۹) digits (web `utils/digits.ts`; the same on mobile).

**Dates and times** (Asia/Riyadh, Gregorian; CONVENTIONS §9.1). Web uses the scaffold `formatDate` / `formatTime` / `formatDateTime`; Flutter uses one `BafoDateFormat` helper. Both must produce exactly:

| Format | Arabic | English |
|---|---|---|
| Long date | «9 نوفمبر 2026» | "9 Nov 2026" |
| Time | «12:59 م» | "12:59 PM" |
| Date and time | «9 نوفمبر 2026، 12:59 م» | "9 Nov 2026, 12:59 PM" |
| Deadline | «… بتوقيت الرياض» | "… Riyadh time" |
| Relative (< 7 days; notifications, activity) | «منذ 5 دقائق» | "5 minutes ago" |
| Machine (offer log, ms) | `2026-11-09 14:59:58.412 (KSA)` | same |
| Countdown ≥ 24 h | «3 أيام 04:10» | "3 days 04:10" |
| Countdown < 24 h | `04:10:59` | `04:10:59` |

- Arabic month names: يناير، فبراير، مارس، أبريل، مايو، يونيو، يوليو، أغسطس، سبتمبر، أكتوبر، نوفمبر، ديسمبر.
- **Web pitfall:** `Intl` with plain `ar-SA` defaults to the Umm al-Qura calendar and Arabic-Indic digits. Formatters must use `ar-SA-u-ca-gregory-nu-latn` (or an equivalent option set).
- **Flutter pitfall:** call `DateFormat.useNativeDigitsByDefaultFor('ar', false)` at start-up, and never format money or counts with an `ar` `NumberFormat`. A unit test asserts that formatted output contains only `[0-9]` digits.
- Date and time inputs are entered and shown in Riyadh time and converted with `zonedInputToUtcIso` / `utcIsoToZonedInput` (web scaffold) or their Dart twins.

**Money** (CONVENTIONS §9.2)

- `formatMoney(minor, locale)`: Arabic `12,500.00 ر.س`, English `SAR 12,500.00`. Always 2 decimals, comma grouping, Western digits.
- Always rendered as an LTR island with tabular figures: web `<Amount>` (a `<bdi class="tabular-nums">`), Flutter `MoneyText`.
- Discounts and credits show as a minus inside the island: `− SAR 300.00` / `− 300.00 ر.س`.
- Every price list, composer and plan card carries `common.prices_exclude_vat` «الأسعار لا تشمل ضريبة القيمة المضافة». Checkout and invoices show subtotal, credit, discount, VAT 15%, total.
- Amount inputs: `MoneyInput` (web) / `MoneyInputField` (Flutter) with the currency label on the locale's side, `inputmode="decimal"`, the parse rules of S5.

**Other values**

- Percentages from basis points: `bps / 100`, up to 2 decimals, trailing zeros trimmed (`50` → `0.5%`, `6080` → `60.8%`). Signed values carry `+` or `−`.
- Counts and plurals: vue-i18n with the custom Arabic 6-form rule (`utils/plural.ts`); ICU `plural` in ARB.
- Phone display `+966 50 123 4567`; phone input with a fixed `+966` prefix, a numeric keypad and 9 digits starting with 5.
- File sizes: `fileSizeParts` plus a localised unit.
- ULIDs are never shown, except the API `client_id` in integrations.

### S7 Forms and errors

- **Visible labels** on every input (no placeholder-only fields), a required marker, helper text, and errors linked to the field (`aria-describedby` / Flutter `errorText` + semantics).
- **Client validation mirrors the API field rules for feedback only:** required, lengths, e-mail, phone `^\+9665\d{8}$`, CR `^\d{10}$`, VAT `^3\d{13}3$`, the password rule (≥ 8, lower, upper, digit, symbol) shown as a live checklist. Validate on blur and on submit.
- **Server field errors** (`422 validation_failed`) are bound by path: `organization.cr_number` → CR field; `rules.min_step_bps` → that control; `invitations.2.email` → the third staged row. Paths without a matching field go into a form-level alert.
- **Error text:** `t('errors.<code>')` when the key exists, otherwise the server `message` (CONVENTIONS §4.1). Business errors (403/409) appear in an alert at the top of the form or in the action's dialog, with their `details` rendered (amounts, dates, blockers, missing fields).
- **Submit buttons** show a busy state and block double submits. A 429 shows the `Retry-After` countdown on the button.
- **Honeypot** `website_url` on register, contact and invitation lookup: visually hidden, `tabindex="-1"`, `autocomplete="off"`, `aria-hidden="true"`, always sent empty.
- **Unsaved-changes guard** on wizard steps and long forms.
- **Destructive actions** always go through a confirmation dialog or sheet whose red button names the action («إلغاء المنافسة» / "Cancel competition", never «نعم» / "Yes"), with the reason fields the API requires.

### S8 Page states

| State | Trigger | Web | Mobile | Copy |
|---|---|---|---|---|
| **L** Loading | first load | skeletons shaped like the content; buttons show an inline spinner | `LoadingSkeleton` / `LoadingSkeletonList` | – |
| **E** Empty | list with no rows | `UiEmptyState`: illustration, title, body, optional CTA | `EmptyState` | `<area>.<list>.empty.{title,body,cta}` |
| **X** Error | network error, 5xx | `ErrorState` with Retry; when a refetch fails, stale data stays visible under a warning banner | `ErrorState` | `errors.network`, `errors.server_error` |
| **F** Forbidden | 403 `forbidden`, or a missing permission in `me.permissions` | `ForbiddenState`: «ليست لديك صلاحية لهذه الصفحة. تواصل مع مالك الحساب.» / "You do not have permission for this page. Contact your account owner." | same | `errors.forbidden` |
| **N** Not found | 404 | «هذه المنافسة غير موجودة أو ليست لديك صلاحية الوصول إليها.» / "This competition does not exist or you do not have access to it." (never reveals existence) | same | `errors.not_found` |
| Session expired | 401 `unauthenticated` | clear the token; go to `/auth/login?redirect=<path>`; toast `auth.session_expired` | clear the token; `/login?from=<path>` | – |
| Account gate | 403 `account_inactive`, `organization_suspended` | full-page state with the support contacts from `AppConfig.support` and Sign out | same | `errors.account_inactive`, `errors.organization_suspended` |
| E-mail not verified | 403 `email_not_verified` | go to `/auth/verify?email=` | go to `/verify` | – |
| Maintenance | 503 `maintenance` | full-page state with `AppConfig.maintenance.message`; retry every 60 s | `/maintenance` (M04) | `errors.maintenance` |
| Offline | no network | top banner; mutations disabled | same | `errors.offline` |
| Force update | 426 (mobile only) | – | `/update-required` (M03) with the store link from `AppConfig.store_links` | `errors.app_version_unsupported` |

**Empty states with their own illustration** (R2R3 §7.1): no competitions, no invitations, no offers yet, no Q&A, no notifications, no team members, no API clients, no webhooks, no invoices, no search results.

### S9 Accessibility

- **Target:** WCAG 2.1 AA minimum (CONVENTIONS §4.2), designing to 2.2 AA where it costs nothing (R2R3 §5.5).
- **Contrast:** filled primary buttons use the `primary` role (green-700 `#0B7A55`, 5.34:1 with white). The brand green `#0E9F6E` is for the mark, icons, focus rings and display text ≥ 24 px only. Input borders use `line-strong` (gray-500).
- **Targets:** ≥ 44×44 px on the web, 48 dp on Android, 44 pt on iOS.
- **Focus:** visible ring (`ring` token), order follows the reading direction, a skip-to-content link, dialogs trap focus and restore it on close.
- **Live regions:** standing changes `aria-live="polite"`, throttled to one announcement per 10 s; "closing in 1 minute" and "closed" `assertive`; toasts polite. Flutter: `Semantics(liveRegion: true)` on the standing banner, `SemanticsService.announce` for the thresholds.
- **Colour is never the only signal:** every chip has an icon and text.
- **Motion:** a 300 ms highlight when a live value changes, no bouncing; off under `prefers-reduced-motion` / `MediaQuery.disableAnimations`.
- **Text scaling** to 200% without loss (web zoom; Flutter `textScaler` up to 2.0, layouts wrap or scroll instead of clipping).
- **Tables:** `<th scope>`, a caption, `aria-sort` on sortable headers; below 768 px rows stack as labelled cards (scaffold `UiTable`).
- **OTP:** one input with `autocomplete="one-time-code"` and `inputmode="numeric"` (web), `AutofillHints.oneTimeCode` (Flutter), drawn as six boxes.
- **Language:** `<html lang dir>` per locale; embedded fragments in the other language get a `lang` attribute.

### S10 Permissions and entitlement → controls

The UI renders from server decisions only: `me.permissions`, `me.entitlements`, `me.organization.features`, the competition's `permissions`, `access` and the live `accepting_offers` (CONVENTIONS §7).

| Control | Shown / enabled when |
|---|---|
| Create competition | `me.permissions` has `competitions.create` **and** `me.entitlements.can_issue`. Without `can_issue`: web shows a disabled button with «تحتاج إلى باقة فعّالة لطرح المنافسات» / "An active plan is required to issue competitions" and a link to plans; mobile shows the same text and `billing.managed_on_web`. |
| Auction option in the wizard | `me.organization.features.auction_enabled`; otherwise disabled with «المزايدات غير مفعّلة لمنشأتك. تواصل مع بافو.» / "Auctions are not enabled for your organisation. Contact BAFO." Categories with `auction_allowed = false` are disabled when auction is chosen. |
| Participation-fees step, fees panel | `me.organization.features.sponsorship_enabled` **and** `AppConfig.features.sponsorship` |
| Edit, delete draft, publish, invite, extend, cancel, start BAFO, award, revoke, close without award, manage fees, comment, join, decline, submit offer | the matching competition `permissions.can_*` flag. Hidden when false, except `can_submit_offer`, which disables the composer with the reason from the snapshot state. |
| "Pay and publish", "Pay and send", checkout, trial | web only; `billing.purchase`. Without it: «يحتاج الدفع إلى صلاحية الشراء. تواصل مع مالك الحساب.» / "Payment needs the purchase permission. Contact your account owner." |
| Team page | `team.manage` |
| Organisation edit | `organization.update` (others see it read-only) |
| Billing pages | `billing.view` for the overview and invoices; the plans page is open to every user; checkout needs `billing.purchase`; the payment return page is open to the payer |
| Integrations | `integrations.manage`; API clients, keys and webhooks also need `me.organization.features.api_enabled` (import and export do not) |
| Vendors | `competitions.create` |
| Delete organisation | `account.delete_organization` (owner); other users delete their own user account |

### S11 Notification routing

A notification's `route` is locale-free (ARCHITECTURE §11.1). Both clients map it with one table; unknown routes fall back to the notifications list.

| `route` | Web | Mobile |
|---|---|---|
| `/competitions/{id}` | `/{locale}/dashboard/competitions/{id}` | `/competitions/:id` |
| `/competitions/{id}/live` | `…/competitions/{id}/live` | `/competitions/:id/live` |
| `/competitions/{id}/qa` | `…/competitions/{id}/qa` | `/competitions/:id/qa` |
| `/billing` | `/{locale}/dashboard/billing` | `/billing` (plan status only) |
| `/billing/invoices/{id}` | `/{locale}/dashboard/billing/invoices/{id}` | `/billing`, with `billing.invoices_on_web` «الفواتير متاحة في لوحة تحكم بافو على الويب.» / "Invoices are available in the BAFO web dashboard." |
| `/integrations` | `/{locale}/dashboard/integrations` | `/home` |
| `/notifications` | `/{locale}/dashboard/notifications` | `/notifications` |

Opening a notification calls `POST /notifications/{id}/read` (unless already read) and then navigates. Type icons (`notifications.type.*`): invitation (envelope), competition update (refresh), opened/final window/closing/extended (timer), closed/cancelled/not awarded (flag), offers (tag), standing (trending), BAFO (brand mark), award (trophy), Q&A (message), billing (receipt), integrations (plug).

---

## 2. Web (Nuxt 4, `apps/web`)

### 2.1 Page architecture

**Rendering and layouts**

| Layout | Pages | Rendering | Middleware |
|---|---|---|---|
| `default` | landing, legal, invitation landing | SSR (the invitation landing is an SSR shell; the token is read client-side) | – |
| `auth` | `/auth/*` | SSR shell | `guest` (except `verify`, `accept-invite` and `reset`, which work in both states) |
| `dashboard` | `/dashboard/**` | client-only (`ssr: false`, already in `nuxt.config.ts` `routeRules`) | `auth` |

`/` redirects to `/ar` (the default locale). `/{locale}/login` and `/{locale}/register` (scaffold) redirect to `/{locale}/auth/login` and `/{locale}/auth/register` (CD2).

**Data layer** (CONVENTIONS §4.1)

- All HTTP goes through `useApi()`. Per-module functions live in `app/services/{platform,catalog,identity,competitions,bidding,billing,notifications,integrations}.ts`. Types mirror API.md §2 in `app/types/api/<module>.ts`.
- Pinia setup stores:

  | Store | Holds | Loaded |
  |---|---|---|
  | `useAuthStore` (exists) | token cookie, `Me`, `can(permission)`, `login`, `logout`, `fetchMe` | dashboard entry; after login, OTP verify, team accept, plan changes |
  | `useAppConfigStore` | `AppConfig` (realtime, legal versions, features, support, maintenance) | app start (`GET /app-config`) |
  | `useLookupsStore` | regions, categories, close reasons, presets | first use; `GET /lookups` with `If-None-Match`; re-fetched when the locale changes |
  | `useNotificationsStore` | `unread_count`, the latest 10 notifications for the bell menu | dashboard entry; realtime user channel |

- Composables to add:

  | Composable | Purpose |
  |---|---|
  | `provideCompetition(id)` / `useCompetitionContext()` | Loads `Competition`, owns the realtime subscription (S4), the live snapshot reducer, the connection state, and `refetch()`. Provided by the detail parent page, injected by every tab. |
  | `useLiveReducer()` | Pure `v`-ordered apply of snapshots (Vitest-covered, CONVENTIONS §4.4) |
  | `useOfferSubmit(competitionId)` | S5: idempotency key per intent, retry, error mapping |
  | `useCountdown(targetIso)` | S3 on top of `useServerTime()` |
  | `useDirectionCopy(direction)` | `td('live.status.not_leading')` → the `.tender` or `.auction` key |
  | `usePaymentPoll(paymentId)` | `GET /billing/payments/{id}` every 2 s for up to 60 s, then `POST …/verify` once (CONVENTIONS §7) |
  | `useJobPoll(fetcher)` | import, export and report polling (2 s; report 3 s), stops on a terminal status |
  | `usePendingToken(kind)` | CD7 fragment handling and `sessionStorage` access |
  | `useFileDownload()` | fetch a private file as a blob with the bearer header, then save it (API.md §0.7) |

**Realtime.** `useEcho()` (scaffold) builds Echo from `AppConfig.realtime`, with `authEndpoint` `/broadcasting/auth` and the bearer header. The dashboard layout subscribes to `private-user.{id}`; `provideCompetition` subscribes to the competition channel of the viewer's role.

### 2.2 Route map

All paths are prefixed with `/{locale}` (`ar` or `en`). Files are under `apps/web/app/pages/`.

| ID | Path | File | Layout | Access | Main i18n namespaces |
|---|---|---|---|---|---|
| W01 | `/` | `index.vue` | default | public, SSR | `landing` |
| W02 | `/legal/{code}` | `legal/[code].vue` | default | public, SSR | `legal` |
| W03 | `/invitations` (`#t=…`) | `invitations/index.vue` | default | public | `invitations` |
| W04 | `/auth/login` | `auth/login.vue` | auth | guest | `auth` |
| W05 | `/auth/register` | `auth/register.vue` | auth | guest | `auth`, `organization` |
| W06 | `/auth/verify?email=` | `auth/verify.vue` | auth | any | `auth` |
| W07 | `/auth/forgot` | `auth/forgot.vue` | auth | guest | `auth` |
| W08 | `/auth/reset?email=` | `auth/reset.vue` | auth | any | `auth` |
| W09 | `/auth/accept-invite` (`#t=…`) | `auth/accept-invite.vue` | auth | any | `auth`, `team` |
| W10 | `/dashboard` | `dashboard/index.vue` | dashboard | user | `home` |
| W11 | `/dashboard/competitions` | `dashboard/competitions/index.vue` | dashboard | user | `competitions` |
| W12 | `/dashboard/competitions/new` | `dashboard/competitions/new.vue` | dashboard | `competitions.create` + `can_issue` | `competitions`, `rules` |
| W13 | `/dashboard/competitions/{id}` (parent) | `dashboard/competitions/[id].vue` | dashboard | issuer / participant / invitee | `competitions` |
| W14 | `/dashboard/competitions/{id}` (overview) | `dashboard/competitions/[id]/index.vue` | – | all viewers | `competitions`, `invitations`, `award` |
| W15 | `/dashboard/competitions/{id}/setup/{step}` | `dashboard/competitions/[id]/setup/[step].vue` | – | issuer, draft, `can_edit` | `competitions`, `rules`, `invitations`, `sponsorship` |
| W16 | `/dashboard/competitions/{id}/edit` | `dashboard/competitions/[id]/edit.vue` | – | issuer, scheduled or live, `can_edit` | `competitions` |
| W17 | `/dashboard/competitions/{id}/participants` | `dashboard/competitions/[id]/participants.vue` | – | issuer | `invitations`, `sponsorship` |
| W18 | `/dashboard/competitions/{id}/qa` | `dashboard/competitions/[id]/qa.vue` | – | issuer, participant | `qa` |
| W19 | `/dashboard/competitions/{id}/live` | `dashboard/competitions/[id]/live.vue` | – | issuer (console), participant (live room) | `live`, `offers`, `bafo` |
| W20 | `/dashboard/competitions/{id}/offers` | `dashboard/competitions/[id]/offers.vue` | – | issuer | `offers` |
| W21 | `/dashboard/competitions/{id}/my-offers` | `dashboard/competitions/[id]/my-offers.vue` | – | participant | `offers` |
| W22 | `/dashboard/competitions/{id}/award` | `dashboard/competitions/[id]/award.vue` | – | issuer | `award`, `bafo` |
| W23 | `/dashboard/participating` | `dashboard/participating.vue` | dashboard | user | `competitions`, `invitations` |
| W24 | `/dashboard/invitations/claim` | `dashboard/invitations/claim.vue` | dashboard | user | `invitations` |
| W25 | `/dashboard/vendors` | `dashboard/vendors.vue` | dashboard | `competitions.create` | `vendors` |
| W26 | `/dashboard/team` | `dashboard/team.vue` | dashboard | `team.manage` | `team` |
| W27 | `/dashboard/organization` | `dashboard/organization.vue` | dashboard | user (edit: `organization.update`) | `organization` |
| W28 | `/dashboard/account` | `dashboard/account.vue` | dashboard | user | `profile` |
| W29 | `/dashboard/notifications` | `dashboard/notifications.vue` | dashboard | user | `notifications` |
| W30 | `/dashboard/billing` | `dashboard/billing/index.vue` | dashboard | `billing.view` | `billing` |
| W31 | `/dashboard/billing/plans` | `dashboard/billing/plans.vue` | dashboard | user (plans are public; the CTA needs `billing.purchase`) | `billing` |
| W32 | `/dashboard/billing/checkout?plan=&interval=&seats=` | `dashboard/billing/checkout/index.vue` | dashboard | `billing.purchase` | `billing` |
| W33 | `/dashboard/billing/checkout/return?payment=` | `dashboard/billing/checkout/return.vue` | dashboard | the payer or `billing.view` | `billing`, `sponsorship` |
| W34 | `/dashboard/billing/invoices` | `dashboard/billing/invoices/index.vue` | dashboard | `billing.view` | `billing` |
| W35 | `/dashboard/billing/invoices/{id}` | `dashboard/billing/invoices/[id].vue` | dashboard | `billing.view` | `billing` |
| W36 | `/dashboard/integrations` | `dashboard/integrations/index.vue` | dashboard | `integrations.manage` | `integrations` |
| W37 | `/dashboard/integrations/api-clients` | `dashboard/integrations/api-clients/index.vue` | dashboard | `integrations.manage` + `api_enabled` | `integrations` |
| W38 | `/dashboard/integrations/api-clients/{id}` | `dashboard/integrations/api-clients/[id].vue` | dashboard | same | `integrations` |
| W39 | `/dashboard/integrations/webhooks` | `dashboard/integrations/webhooks/index.vue` | dashboard | same | `integrations` |
| W40 | `/dashboard/integrations/webhooks/{id}` | `dashboard/integrations/webhooks/[id].vue` | dashboard | same | `integrations` |
| W41 | `/dashboard/integrations/import` | `dashboard/integrations/import.vue` | dashboard | `integrations.manage` | `integrations`, `vendors` |
| W42 | `/dashboard/integrations/exports` | `dashboard/integrations/exports.vue` | dashboard | `integrations.manage` | `integrations` |
| W43 | error page (404, 500, maintenance) | `app/error.vue` (exists) | – | – | `errors` |

The static segment `new` wins over `[id]` in vue-router, so W12 needs no special handling. The parent W13 sets the competition chrome from each child's page meta: `definePageMeta({ competitionChrome: 'tabs' | 'wizard' | 'bare' })`.

### 2.3 Navigation and the dashboard frame

**Side navigation** (start edge; collapses to a drawer below 1024 px). Replaces the scaffold `config/navigation.ts` entries (§6).

| Group | Item (`nav.*`) | AR / EN | Path | Shown when |
|---|---|---|---|---|
| – | `overview` | الرئيسية / Overview | `/dashboard` | always |
| Competitions | `my_competitions` | منافساتي / My competitions | `/dashboard/competitions` | `competitions.create` |
| | `participating` | مشاركاتي / Participating | `/dashboard/participating` | always |
| | `vendors` | الموردون والجهات / Vendors | `/dashboard/vendors` | `competitions.create` |
| Organisation | `team` | الفريق / Team | `/dashboard/team` | `team.manage` |
| | `organization` | المنشأة / Organisation | `/dashboard/organization` | always |
| | `billing` | الاشتراك والفواتير / Subscription and billing | `/dashboard/billing` | `billing.view` |
| | `integrations` | التكامل / Integrations | `/dashboard/integrations` | `integrations.manage` |

A primary "Create competition" button sits above the list (S10 rules).

**Top bar:** organisation logo and name (`OrgLogo`), the notifications bell (`NotificationsMenu`: unread badge, latest 10, "Mark all as read", "View all"), `LanguageSwitch`, `ThemeMenu`, `UserMenu` (Account, Sign out).

**Global banners** under the top bar, from `GET /home` `alerts` (loaded once per session and refreshed on `notification.created` of billing types):

| `alerts[].code` | Tone | Text (`home.alerts.*`) | Action |
|---|---|---|---|
| `subscription_expiring` | warning | «ينتهي اشتراككم خلال {days_left} …» / "Your subscription ends in {days_left} days" | Renew → W31 (`billing.purchase`) |
| `subscription_expired` | warning | «انتهى اشتراككم» / "Your subscription has ended" | View plans → W31 |
| `plan_required` | info | «لا توجد باقة فعّالة. يمكنكم المشاركة في المنافسات المغطّاة رسومها.» / "No active plan. You can still take part in competitions whose fees are covered." | View plans → W31 |
| `trial_available` | info | «جرّبوا بافو مجاناً لمدة 30 يوماً» / "Try BAFO free for 30 days" | Start trial → `POST /billing/trial` (`billing.purchase`), then `fetchMe` |
| `billing_profile_incomplete` | info | «أكملوا بيانات الفوترة قبل أول عملية دفع» / "Complete your billing details before your first payment" | Complete → W27 |

Banners are dismissible for the session (per-viewer convenience in `sessionStorage`).

### 2.4 Page specs

#### Public and auth

**W01 Landing** · `/`

- Purpose: one bilingual marketing page (mvp_reconciled cross-cutting row).
- Sections: hero (lockup, tagline «أفضل عرض نهائي» / "Best and Final Offer", CTAs Create account → W05 and Sign in → W04); how it works with Tender / Auction tabs (4 steps: create → invite → compete live → award); modes (direction chips, S2); sponsored participation; ERP integration (link to `{API}/docs/api`); security and trust; pricing (plan cards from `GET /plans`, SSR-fetched, hidden on failure); FAQ; contact form (`POST /contact` with honeypot; `too_many_requests` → «تجاوزت عدد المحاولات، حاول لاحقاً.»); footer (legal links W02, language switch).
- States: pricing **X** → section hidden; contact success → inline confirmation.
- SEO: `hreflang` alternates, localised title and description, OG image per locale.

**W02 Legal** · `/legal/{code}` (`terms`, `privacy`, `refund`, `competition_rules`, `api_terms`)

- `GET /legal/{code}` (SSR, `Accept-Language` = route locale). Renders sanitised Markdown with the version and published date.
- States: **N** (`not_found`, no published version) → «هذه الوثيقة غير متاحة حالياً.»; unknown code → 404 page.

**W03 Invitation landing** · `/invitations#t={token}` (optionally `&action=decline`)

- Purpose: the competition invitation e-mail target (ARCHITECTURE §11.5); the invitation fallback page.
- Flow: read the fragment (CD7) → `POST /invitations/lookup {token, website_url: ""}` → `InvitationLookup`.
- Components: `InvitationTeaser` (issuer logo and name with verified badge, title, `DirectionChip`, `FormatChip`, category, region, join deadline with countdown, masked e-mail, `FeesCoveredBadge` when `invitation.sponsored`), rules summary lines, action buttons.
- Actions by `next_step`: `register` → «أنشئ حساب منشأتك وانضم» / "Create your company account and join" → W05 (token kept as pending); `login` → «سجّل الدخول للانضمام» / "Sign in to join" → W04 then W24. When already signed in: go straight to W24. "Decline" opens `DeclineDialog` (reason optional) → `POST /invitations/decline {token, reason}`; `action=decline` opens it directly.
- States: **L**; `invitation_invalid` → «رابط الدعوة غير صالح أو منتهي.» / "This invitation link is invalid or has expired."; declined → confirmation; `invalid_state_transition` on decline → «لم يعد بالإمكان الاعتذار عن هذه الدعوة.».
- The sponsored line: «تغطي {issuer} رسوم مشاركتكم في هذه المنافسة فقط، ويلزمكم حساب منشأة مجاني للانضمام.» / "{issuer} covers your participation fees for this competition only. You need a free company account to join."

**W04 Sign in** · `/auth/login?redirect=`

- Fields: e-mail, password (show/hide). `device_name` = "Web · {browser} on {OS}" (≤ 120). `POST /auth/login` → store the token → `fetchMe` → `safeRedirect(redirect)` or W10; a pending invitation token → W24.
- Errors: `invalid_credentials` (one generic message); `email_not_verified` → W06 (an OTP was sent; `details.otp_expires_at`); `account_inactive`, `organization_suspended` → full-page gate (S8); 429 → countdown.
- Links: Forgot password (W07), Create account (W05).

**W05 Register** · `/auth/register`

- One page with four sections (a stepper on narrow screens):
  1. Contact person: name, e-mail, phone (+966), password and confirmation (live rule checklist), preferred language.
  2. Company: name, CR (10 digits), region (lookups), city, VAT registered switch + VAT number, legal names AR/EN (optional), website (https, optional).
  3. National address (collapsed, optional): building number, street, district, postal code, additional number, short address. Categories multi-select (≤ 20), "Show my company in issuers' suggestions" switch (default on).
  4. Consent: terms and privacy checkboxes linking to W02 (open in a new tab).
- Hidden: honeypot; `invitation_token` from the pending invitation (the e-mail field shows a hint with the masked invited e-mail).
- `POST /auth/register` → W06 with `?email=`.
- Errors: field errors including `identity.validation.email_taken` and `cr_number_taken` (with a "Sign in instead" link); `invitation_email_mismatch` → «يجب التسجيل بالبريد المدعو {masked}.»; unknown or expired `invitation_token` → drop the pending token and explain.

**W06 Verify e-mail** · `/auth/verify?email=`

- `OtpInput` (6 digits) with `otp_expires_at` countdown; "Resend code" → `POST /auth/otp/send {email, purpose: email_verification}` with a 60 s cooldown (`otp_resend_cooldown` → `details.retry_after_seconds`).
- `POST /auth/otp/verify {email, code, purpose, device_name}` → token → `fetchMe` → pending invitation → W24, otherwise W10.
- Errors: `otp_invalid`; `otp_expired` → highlight Resend; `otp_too_many_attempts` → «أُبطل الرمز بعد محاولات كثيرة. اطلب رمزاً جديداً.».

**W07 Forgot password** · `/auth/forgot`

- E-mail → `POST /auth/password/forgot` (always 202) → W08 with `?email=` and the neutral message «إذا كان البريد مسجلاً فستصلك رسالة برمز التحقق.».

**W08 Reset password** · `/auth/reset?email=`

- `OtpInput`; when 6 digits are entered, `POST /auth/otp/check {purpose: password_reset}` validates early (optional). New password and confirmation. `POST /auth/password/reset` (204) → toast «تم تغيير كلمة المرور. سجّل الدخول.» → W04. Resend as W06 with `purpose: password_reset`.

**W09 Accept team invitation** · `/auth/accept-invite#t={token}`

- `POST /auth/team-invitations/lookup {token}` → organisation logo and name, invited name, e-mail, role, expiry.
- Form: password, confirmation, accept terms. `POST /auth/team-invitations/accept {token, password, password_confirmation, device_name, accept_terms}` → token → W10.
- A signed-in visitor sees «أنت مسجّل الدخول بحساب آخر» with "Sign out and continue".
- Errors: `team_invitation_invalid` → «رابط الدعوة غير صالح أو منتهي. اطلب من مدير الحساب إعادة الإرسال.».

#### Dashboard home

**W10 Overview** · `/dashboard`

- Purpose: the combined issuer and participant home (legacy "Insights" parity).
- API: `GET /home` (plus `me` from the store).
- Components: greeting; `StatTile` rows: issuer (`active_competitions`, `draft_competitions`, `live_now`, `awaiting_award`, `offers_received_30d`), each linking to W11 with the matching filter; participant (`pending_invitations`, `active_participations`, `offers_submitted_30d`, `awards_won`) linking to W23; `SubscriptionCard` (plan, status, `days_left` of `total_days` meter, `ends_at`), team seats (`members` / `seats_total`); `ActivityList` (`activities`, rendered with `home.activity.<action with . → _>`, relative time, link to the subject); quick actions (Create competition per S10; View invitations).
- Realtime: refetch on `notification.created` (debounced 2 s) and on window focus.
- States: **L** skeleton tiles; **X** retry; empty activity → `home.activity.empty`.

#### Competitions: issuer

**W11 My competitions** · `/dashboard/competitions`

- API: `GET /competitions?role=issuer&status_group=&direction=&q=&sort=&page=`.
- Components: `PageHeader` + Create button; tabs Active / Drafts / Ended / All (`status_group`); `FilterBar` (direction segmented control, debounced search, sort: recently updated, closing soonest, newest); `UiTable` columns: reference, title, `DirectionChip`, `CompetitionStatusChip` (+ overlay pills), opens / closes (date-time, countdown when live), invited · joined · with offers, leading offer (`leading_amount_minor`, "—" when null), updated; `UiPagination`.
- Row click: draft → W15 at the first incomplete step (§2.5 F2 rule); otherwise W14.
- Filters live in the query string (non-sensitive), so the view is shareable.
- States: **L**; **E** per tab (`competitions.list.empty.{active,drafts,ended}` with Create CTA); **X**.

**W12 New competition (steps 1–2)** · `/dashboard/competitions/new`

- Step 1 **Type** (`competitions.setup.type`): `DirectionRadioCards` (Tender: ↓, «الأقل سعراً يفوز», «أنت المشتري» / "You are buying"; Auction: ↑, «الأعلى سعراً يفوز», «أنت البائع» / "You are selling"; auction gated per S10); format radio cards (Live: «عروض مباشرة قابلة للتحسين حتى الإغلاق»; Sealed: «عرض مغلق لكل متنافس يُفتح عند الإغلاق»); preset cards filtered by direction and format (`useLookupsStore().presets`), each with name, description and 3 rule chips.
- Step 2 **Basics** (`competitions.setup.basics`): title (≤ 200), description (plain text with line breaks, ≤ 20 000; required at publish), category (`auction_allowed` filtering; "Other" requires `category_other_text`), region.
- Continue → `POST /competitions {title, description, category_id, category_other_text, region_id, direction, format, preset_code, rules: preset.rules}` → replace the URL with W15 `setup/rules`.
- Errors: `issuer_plan_required` → plans prompt; `auction_not_enabled`; 422 on `category_id`.

**W15 Setup wizard (steps 3–8, and 1–2 of an existing draft)** · `/dashboard/competitions/{id}/setup/{step}`

- Chrome `wizard`: `UiStepper` (8 steps; step 7 hidden when sponsorship is not enabled), a sticky footer with Back, Save and Continue (every step saves with `PATCH` or its own endpoint), an unsaved-changes guard, and a side "Rules summary" card from the last `Competition.rules_summary`.
- Guards: `viewer_role = issuer` and `status = draft` and `permissions.can_edit`; otherwise redirect to W14.

| Step (`{step}`) | Content | API | Notes |
|---|---|---|---|
| 1 `type` | as W12 step 1 | `PATCH /competitions/{id} {direction, format, preset_code, rules}` | Changing direction or format asks «ستُعاد القواعد إلى الإعداد المسبق المختار.» and sends the full preset `rules` plus the current prices, so the result never depends on PATCH merge rules. |
| 2 `basics` | as W12 step 2 | `PATCH` | – |
| 3 `rules` | **Prices:** start price (tender «سعر السقف» / "Ceiling price", optional; auction «سعر الافتتاح» / "Opening price", required at publish); reserve (tender «السعر المستهدف» / "Target price"; auction «الحد الأدنى المقبول» / "Reserve price"; note «مخفي عن المتنافسين ويؤثر في الترسية فقط»); granularity (whole riyals / halalas). **Advanced rules** (disclosure `rules.advanced`): must beat own / leading (`best` forces `show_prices` on, R3); minimum step: none / amount / percent (0.01–50%) with direction labels «الحد الأدنى للتخفيض» / «الحد الأدنى للزيادة»; standing visibility: full rank / leading or not / hidden, plus show prices (disabled by R1, forced by R3 and R4); result publication: none / outcome / outcome and amount; final pricing window (off or 30–600 min); auto-extend (switch; window and extension 1–30 min; max 1–50) with a "Latest possible close" preview; BAFO round (switch; 15–4320 min, default 60); minimum participants (1–50). Sealed disables every live-only control with «غير متاح في المنافسات المغلقة». A "Customised" badge appears when rules differ from the preset. | `PATCH {preset_code, rules}` | Client hints mirror R1–R13 for feedback; server `rules.*` errors bind to the controls. |
| 4 `schedule` | Opens: «فور النشر» / "When published" (null) or a date and time; closes: date and time (Riyadh time). Duration, and a timeline preview: opens → final window start → invitation cutoff → close → latest close. | `PATCH {bidding_opens_at, scheduled_close_at}` | Derived times are only set at publish (ARCHITECTURE §7.2), so the preview is computed client-side and labelled «تقديري، يُثبَّت عند النشر» (§6 G3). |
| 5 `documents` | `AttachmentUploader` (multi-file; per file: kind «مستندات المنافسة (بعد الانضمام)» `document` or «مستند الدعوة (قبل الانضمام)» `invitation_document`, title); external link form (https URL, title); `AttachmentList` with type icon, size, kind badge, move up/down, delete. Limits: 100 MB; pdf, doc, docx, xls, xlsx, png, jpg, jpeg, zip. | `GET/POST/PATCH/DELETE /competitions/{id}/attachments` | Upload progress per file; `file_type_not_allowed`, `file_too_large` per row. |
| 6 `participants` | `InvitePicker`: tabs Suggestions (`GET …/suggestions?q=&category_id=&region_id=`, rows with logo, verified badge, region, categories, match chips «نفس الفئة» / «نفس المنطقة», «لديه باقة فعّالة» chip, Add), E-mail (paste a list; split on comma, space or newline; per-chip validation; optional name), Vendors (`GET /vendors?q=`; blocked vendors disabled). Staged rows → "Add {n} invitations". Current table: invitee (e-mail, organisation or vendor), name (inline edit), remove. Counter «{n} من الحد الأدنى {min}». | `POST /competitions/{id}/invitations`, `PATCH`/`DELETE …/invitations/{invitation}`, `GET …/invitations` | All-or-nothing: per-row `errors."invitations.{i}.*"` and `details.item_codes` (`invitation_duplicate`, `cannot_invite_own_organization`, `vendor_blocked`, `vendor_not_found`) mark the staged rows; nothing is created. `max_participants_exceeded`. |
| 7 `fees` | «رسوم المشاركة» / "Participation fees": mode radio cards (none «يستخدم المتنافسون باقاتهم» / all «تغطية الرسوم لجميع المدعوين» / selected «تغطية الرسوم لمدعوين محددين»), cap «الحد الأقصى للمتنافسين المغطّين» (1–200); `SponsorshipQuoteTable` per invitation (coverage chip and reason: `not_selected`, `cap_reached`, `own_plan`); in `selected` mode a switch per row; `CouponField`; `OrderSummary` (passes to buy × unit price, discount, VAT 15%, total; `passes_to_reserve` note). Policy text: «تغطي رسومكم هذه المنافسة فقط. لا يرى المتنافسون الآخرون من تُغطّى رسومه. تُصدر بافو قسيمة بقيمة التصاريح غير المستخدمة بعد انتهاء التقديم.» | `PUT /competitions/{id}/sponsorship`, `GET …/sponsorship/quote?coupon_code=`, `PATCH …/invitations/{inv} {sponsored}`, `POST /billing/coupons/validate {purpose: sponsorship, competition_id}` | Hidden when not enabled (S10). `sponsorship_not_enabled`, `sponsorship_locked`. |
| 8 `review` | Summary cards per step with Edit links; server `rules_summary`; schedule preview; invitations vs minimum; fees summary; a pre-publish checklist (description, schedule, start price for auctions, "Other" category text, invitation count) as hints. Primary: **Publish**, or **Pay and publish** when the quote has `passes_to_buy > 0`. | `POST /competitions/{id}/publish`; or `POST …/sponsorship/checkout {intent: publish, coupon_code, return_url}` with an `Idempotency-Key` | See flow F3 (§2.5) for payment and the error table below. |

- **Publish errors** (step 8):

  | Code | UX |
  |---|---|
  | `validation_failed` | Each field error links to its step (`description` → 2, `bidding_opens_at` / `scheduled_close_at` → 4, `start_price_minor` / `rules.*` → 3, `category_other_text` → 2). |
  | `min_participants_not_met` | «أضف {required − current} مدعوين على الأقل» with a link to step 6. |
  | `issuer_plan_required` | Plan prompt → W31. |
  | `live_event_capacity_reached` | «بلغ عدد الفعاليات المباشرة المتزامنة حدّه في هذه الفترة. اختر موعداً آخر.» with a link to step 4. |
  | `sponsorship_payment_required` | Show `details.quote` in the fees summary and switch the button to **Pay and publish**. |
  | `invalid_state_transition` | Refetch; the draft was already published. |

- Success (`scheduled` or `live`) → W14 with a toast «نُشرت المنافسة وأُرسلت الدعوات» / "Competition published. Invitations sent."

**W16 Edit after publish** · `/dashboard/competitions/{id}/edit`

- Fields by status (API.md §1.4 PATCH): `scheduled` → title, description, category (+ other text), region, opens, closes; `live` → title and description. Other fields are shown read-only with «القواعد ثابتة بعد النشر» / "Rules are fixed after publishing".
- Note: «سيُبلَّغ المتنافسون بالتحديث.» / "Participants will be notified of the update."
- `PATCH /competitions/{id}`; `competition_not_editable` lists `details.fields`.

#### Competition detail (all viewers)

**W13 Detail parent** · `/dashboard/competitions/{id}`

- Loads `GET /competitions/{id}` through `provideCompetition(id)` and renders the chrome:
  - **Header:** `reference_no`, title, `DirectionChip`, `FormatChip`, `CompetitionStatusChip` + overlay pills, category, region, issuer (participants and invitees), countdown (S3 targets), `FeesCoveredBadge` (participant with `access.coverage = sponsored`: «رسوم مغطّاة · تغطيها {sponsor_name}»), `ConnectionIndicator` (published competitions).
  - **Action bar** (issuer, from `permissions`): Continue setup (draft), Publish (draft → W15 review), Edit (W16), Invite (W17 drawer), Extend (`ExtendDialog`), Cancel (`CancelDialog`), Delete draft (`ConfirmDialog` → `DELETE /competitions/{id}` → W11).
  - **Tabs:**

    | Viewer | Tabs (route) | Shown when |
    |---|---|---|
    | issuer | Overview (`/`), Participants (`/participants`), Q&A (`/qa`), Live (`/live`), Offers log (`/offers`), Evaluation and award (`/award`) | Q&A, Live, Offers log from `scheduled`; Evaluation and award from `closed` |
    | participant | Overview, Live room (`/live`), Q&A (`/qa`), My offers (`/my-offers`) | always |
    | invitee | no tabs: the overview renders the invitation view | – |

- Tab routes the viewer cannot use redirect to the overview (issuer-only tabs for participants and the reverse).
- States: **L** header skeleton; **N** (404) per S8; **X**.
- Realtime: S4, by `viewer_role`.

**W14 Overview**

- **Issuer:** `ScheduleTimeline` (published, opens, final window start, invitation cutoff, scheduled close, effective close, hard stop, extensions with kind and reason, closed, awarded); `RulesSummary` (server lines, including `reserve_hidden`); `CountsGrid` (`counts.*`); `SponsorshipSummary` (mode, status, funded passes, free slots; link to W17); `AttachmentList` (download; "addendum" badge for `is_addendum`; add documents while `draft`/`scheduled`/`live` through `AttachmentUploader`; delete only in `draft`/`scheduled`); `created_by` and a «أُنشئت عبر واجهة برمجة التطبيقات» badge when `source = api`; cancellation or not-awarded reason panel; award summary (`award`) with a link to W22.
  - Draft: a setup checklist (each step: complete or missing) and "Continue setup".
- **Participant:** issuer card (logo, name, verified); my standing summary (from the live snapshot: S2 standing, my current offer, required next); `RulesSummary`; `AttachmentList` (documents and links); `ResultPanel` when `result.outcome` is set (won with `winning_amount_minor` when present; not selected; not awarded; `null` → status only); cancellation reason.
- **Invitee:** `AccessStateCard`:

  | `access.state` / `coverage` | Content | Actions |
  |---|---|---|
  | `join_required` / `sponsored` | `FeesCoveredBadge` + «تغطي {sponsor_name} رسوم مشاركتكم في هذه المنافسة فقط.» | Join, Decline |
  | `join_required` / `own_plan` | «تنضمون بباقتكم الحالية.» / "You join with your current plan." | Join, Decline |
  | `plan_required` | «تحتاجون إلى باقة فعّالة للانضمام إلى هذه المنافسة.» | View plans → W31 (`?return=/dashboard/competitions/{id}`), Decline |
  | `unavailable` | reason from the invitation status (declined, expired, revoked) or the competition status | – |

  Plus `InvitationTeaser` details, `rules_summary`, `invitation_documents` (download), and the join deadline countdown.
  - **Join** opens `JoinDialog`: rules summary, a link to W02 `competition_rules`, a required «أوافق على شروط المنافسة» checkbox → `POST /invitations/{invitation.id}/join {accept_terms: true}` → the response is the participant projection → re-render as participant and subscribe (toast «انضممتم إلى المنافسة»).
  - Join errors: `plan_required` (show `details.access`), `join_deadline_passed`, `already_participating`, `invalid_state_transition`, `terms_not_accepted`.
  - **Decline** opens `DeclineDialog` (reason) → `POST /invitations/{id}/decline`.
  - The first view marks the invitation `viewed` server-side; nothing to do client-side.

**W17 Participants (issuer)** · `/participants`

- API: `GET /competitions/{id}/invitations?status=` (+ `meta.counts`), `GET …/sponsorship`.
- Components: status filter chips with counts; `UiTable`: invitee (e-mail, organisation with verified badge, or vendor), name, `InvitationStatusChip`, alias («المتنافس 7» when joined), coverage and pass status, sent / viewed / joined / declined (with reason) timestamps; row actions: Resend (`sent`/`viewed`, before the cutoff; `POST …/resend`; 429 after 3 per day), Revoke (`sent`/`viewed`; `ConfirmDialog` → `DELETE …/invitations/{inv}` → `revoked`), Remove (draft), Edit name or sponsored (draft).
- `SponsorshipCard` (when not `none`): mode, cap, funded passes, counters (`pending`, `reserved`, `joined`, `released`, `unused`, `void`, `free_slots`), status, `unused_count` and the voucher code once issued; "Change" (mode and cap while allowed → `PUT …/sponsorship`; `sponsorship_locked`).
- **Invite more** (`permissions.can_invite`, before `invitation_cutoff_at`): `InviteDrawer` with the step-6 picker and, when sponsorship is on, a sponsored switch per row (`selected` mode). `POST …/invitations`:
  - 201 → rows appear (status `sent`); toast.
  - 409 `sponsorship_payment_required` → a quote panel with **Pay and send** (`POST …/sponsorship/checkout {intent: invite, invitations, coupon_code, return_url}`) and, in `selected` mode only, **Send without covering fees** (re-post with `sponsored: false`).
  - `invitation_cutoff_passed`, `max_participants_exceeded`, per-row errors as step 6.
- Realtime: `invitation.updated` upserts rows and refreshes the sponsorship counters.
- States: **L**; **E** «لم تُرسل دعوات بعد» with Invite; **X**.

**W18 Q&A** · `/qa` (issuer and participant)

- API: `GET /competitions/{id}/comments?page=` (top-level newest first, replies oldest first); `POST …/comments {body, parent_id}`.
- Components: `QaComposer` (top: participant «اطرح سؤالاً» / issuer «انشر إعلاناً لجميع المتنافسين»); `QaThread` items with author rendering: issuer → issuer organisation name with an «طارح المنافسة» badge; participant seen by the issuer → «المتنافس {alias_no} · {organization_name}»; `kind = me` → «أنتم» / "You"; other participants → «المتنافس {alias_no}». Reply buttons: the issuer on every thread; a participant only on its own organisation's threads.
- `comments_closed` (not `scheduled`/`live`) → read-only notice «أُغلقت الاستفسارات» and no composer.
- Realtime: `comment.created` inserts and highlights new items; a «{n} رسائل جديدة» pill when the user has scrolled away.
- States: **L**; **E** «لا توجد استفسارات بعد»; **X**.

**W19 Live** · `/live`

*Issuer console* (IssuerLiveSnapshot):

- Header strip: status and phase, `CountdownPanel` (effective close, extension count, latest close), `online_participants_count` «{n} متصلون الآن», `ConnectionIndicator`.
- `LeaderCard`: alias, organisation name, amount, `accepted_at`; `ReserveMetIndicator` («تحقق السعر المستهدف» / «لم يتحقق», issuer only, when `reserve_met` is not null); metric tile `improvement_vs_start_bps` («التوفير مقارنة بسعر السقف» for tenders, «الزيادة مقارنة بسعر الافتتاح» for auctions).
- `RankingTable`: rank, alias, organisation, current amount, first amount, offers, last offer time, BAFO flags. **Sealed before unlock:** amounts, ranks and the leader are `null` → a `SealedLockPanel` «تُفتح العروض عند الإغلاق» and a "Submitted / Not yet" column.
- `OfferFeed`: the latest 20 `offer.accepted` entries (time with ms, alias, amount or «مغلق», stage).
- `metrics` tiles: offers, joined, with offers, invited.
- Actions (from `permissions`): Extend → `ExtendDialog` (new close ≥ effective close + 5 min, reason 5–1000 → `POST …/extend`; `extend_invalid` shows `details.min_new_close_at`); Cancel → `CancelDialog`; Invite more → W17.
- `scheduled`: a pre-open panel (opens-in countdown, invitations and joins) instead of the ranking.
- `bafo_round`: `BafoRoundCard` (cutoff countdown, `shortlist_count`, `submitted_count`) above the ranking.

*Participant live room* (ParticipantLiveSnapshot):

- Header: countdown, phase, extension pill; the 10 s server-timing hint; the slow-connection hint; `ConnectionBanner`.
- `StandingBanner` (S2): leading, not leading with the direction hint, rank «{rank} من {ranked_count}», or hidden. In the `initial` phase: «قبل فترة التسعير النهائية يظهر عرضك فقط.» / "Before the final pricing window only your own offer is shown."
- `leading_amount_minor` when `show_prices` («العرض المتصدر: {amount}»); `Ladder` (alias, amount, `is_me` highlighted as «أنتم») when present.
- `MyOfferCard`: current amount, `accepted_at` (ms, Riyadh), stage, offers count; link to W21.
- `OfferComposer` (S5) when `accepting_offers`; otherwise the reason: before opening «تبدأ العروض خلال {countdown}»; closed «أُغلق استقبال العروض»; not shortlisted during BAFO «يجري طارح المنافسة جولة عرض نهائي مع قائمة مختصرة، ويبقى عرضكم الأخير قائماً.».
- Sealed format: `SealedLockPanel` «عرضكم مغلق، وتُفتح العروض عند الإغلاق» + `SealedReceipt` (seq, time) after each submit; revisions allowed until close.
- BAFO (`bafo.shortlisted`): `BafoBanner` (charcoal, mark) «أنتم مدعوون لتقديم أفضل وآخر عرض قبل {cutoff}», the reference amount, the rule (`bafo.rule.tender|auction`), a one-shot composer; after submit: «استُلم عرضكم النهائي».
- `ResultPanel` after `awarded` or `not_awarded` (from `result`).
- `RulesDrawer`: rules summary lines.
- Heartbeat: S4.
- Invitees are redirected to the overview (the API answers 403 `not_a_participant`).

**W20 Offers log (issuer)** · `/offers`

- API: `GET /competitions/{id}/offers/log?after_seq=&limit=200` (load more while `meta.has_more`).
- `OfferLogTable`: seq, time (`yyyy-MM-dd HH:mm:ss.SSS (KSA)`), alias, organisation, amount («مغلق» while sealed), stage, channel (web, ios, android, api), a «ملغى» / "Voided" badge.
- Also a per-participant view: `GET /competitions/{id}/offers` (`ParticipantStandingRow`: current, first, change ratio, offers count, last offer, contact e-mail and phone, CR, coverage).
- Export button (only with `integrations.manage`): `POST /integrations/exports {type: offer_log, format, competition_id}` → poll → download.
- Realtime: `offer.accepted` append with gap detection (S4).
- States: **L**; **E** «لم تصل عروض بعد»; **X**.

**W21 My offers (participant)** · `/my-offers`

- API: `GET /competitions/{id}/my-offers`. Table: seq, time (ms), amount, stage, voided badge, the numeric change against the previous own offer (neutral arrow and %).
- States: **E** «لم تقدّموا عروضاً بعد» with a link to W19.

**W22 Evaluation and award (issuer)** · `/award`

- Shown from `closed`. API: `GET /competitions/{id}/offers` (final standings), `GET …/award`, `GET /lookups/close-reasons?kind=award_justification|not_awarded`, `GET …/report`.
- `closed` without a round:
  - `AwardCandidateTable`: rank, alias, organisation (CR, VAT), current amount, first amount, change ratio, offers, BAFO offer; a radio per participant with a current offer.
  - When the selection is not rank 1 or the reserve is not met, `JustificationFields` appear (reason of kind `award_justification`, note when `requires_note`) and, for the reserve, a «أؤكد الترسية دون تحقق السعر المستهدف» checkbox.
  - Message to the winner (≤ 2000) and internal notes (≤ 5000).
  - **Award** → `AwardConfirmDialog` (winner, amount, justification) → `POST …/award`.
  - Errors: `award_justification_required` (`details.reason`), `award_reserve_confirmation_required`, `award_participant_has_no_offer`, `invalid_state_transition`.
- **BAFO round** (`permissions.can_start_bafo`): `BafoShortlistDialog`: multi-select of participants with offers (1–50), duration (15–4320, default the rules value) → `POST …/bafo-round`. Errors: `bafo_not_enabled`, `bafo_already_used`, `bafo_shortlist_invalid` (`details.invalid_participant_ids` marked in the list).
- `bafo_round`: `BafoRoundCard` (cutoff countdown, submitted count); award and close are disabled until the round ends.
- **Close without award** (`permissions.can_close_without_award`): `CloseWithoutAwardDialog` (reason of kind `not_awarded`, note) → `POST …/close`.
- `awarded`: `AwardDetailCard` (winner, amount, leading or not, rank at award, reserve met, justification, message, notes, awarded by and at, ERP sync status and references, ledger hash); **Revoke award** (`RevokeAwardDialog`, reason 5–1000 → `POST …/award/revoke`) → back to `closed`.
- `not_awarded`: reason panel.
- `ReportButton` (from the first close): locale choice (AR/EN) → `GET …/report?locale=` → 200 ready → download the `file`; 202 → poll every 3 s; `report_not_available` → hidden.
- Results export (with `integrations.manage`): `POST /integrations/exports {type: results}`.

#### Competitions: participant

**W23 Participating** · `/dashboard/participating`

- API: `GET /competitions?role=participant&status_group=&direction=&q=&page=`.
- Tabs: Active (`active`), Ended (`ended`), All.
- `CompetitionCard` list: issuer logo and name, title, `DirectionChip`, status chip, access chip (S2), `FeesCoveredBadge` (sponsored invitations), my offer (`my_offer_amount_minor`), a standing chip when `is_leading` is not null (leading or not leading), a countdown (join deadline while the invitation is pending; close while live), the result outcome.
- Cards needing action (`access.state` `join_required` or `plan_required`) are sorted first within the loaded page and show Join / Decline buttons (the same dialogs as W14).
- States: **L**; **E** «لا توجد منافسات مدعو إليها بعد» / "No competitions yet. Invitations from issuers appear here."; **X**.

**W24 Claim invitation** · `/dashboard/invitations/claim`

- Reads the pending token (CD7); no token → W23.
- `POST /invitations/claim {token}`:
  - 200 `Invitation` → clear the token → W14 of `invitation.competition.id`.
  - 202 `{otp_sent_to, otp_expires_at}` → `OtpInput` «أرسلنا رمزاً إلى {otp_sent_to} للتحقق من ملكية البريد المدعو.» → `POST /invitations/claim {token, code}`.
- Errors: `invitation_belongs_to_another_organization` → «هذه الدعوة مرتبطة بمنشأة أخرى.» / "This invitation belongs to another organisation."; `invitation_invalid`; OTP errors as W06.

#### Organisation and account

**W25 Vendors** · `/dashboard/vendors`

- API: `GET /vendors?q=&status=&category_id=&region_id=&page=`, `POST`, `PATCH`, `DELETE /vendors/{vendor}` (archive).
- `UiTable`: name (AR/EN), e-mail, CR, region, categories, status (active, blocked, archived), «مسجّل في بافو» badge when `linked_organization` is set, source (manual, import, api), external refs count.
- `VendorDrawer` (create or edit): the §1.9 fields, plus an `ExternalRefsEditor` (system, type, id, number, url). Errors: `vendor_email_taken`, `external_ref_conflict` (`details.existing_id` → "Open existing").
- Links: Import (W41, with `integrations.manage`), Export vendors (W42).
- States: **E** «لا توجد جهات في دليلكم بعد» with Add and Import.

**W26 Team** · `/dashboard/team`

- API: `GET /team/members?status=` (+ `meta.seats`), `POST`, `PATCH`, `DELETE /team/members/{m}`, `POST …/resend-invitation`.
- `SeatsMeter` «{used} من {total} مقاعد»; `UiTable`: name, e-mail, role (owner, admin, member), can award, can purchase, status, invited or joined date. The owner row and the viewer's own row are locked.
- `MemberDrawer`: name, e-mail, phone, role; `can_award` and `can_purchase` default to on for admin and off for member.
- Row actions: edit role and flags, deactivate or reactivate, remove (`ConfirmDialog`), resend invitation (invited only).
- Errors: `seat_limit_reached` (`details.seats`) → «اكتملت مقاعد باقتكم ({used}/{total}).» with "Upgrade plan" (W31, `billing.purchase`); `cannot_modify_owner`, `cannot_modify_self`; 422 `email` taken.
- States: **F** without `team.manage`; **E** «لم تضيفوا أعضاء بعد».

**W27 Organisation** · `/dashboard/organization`

- API: `GET /organization`, `PATCH /organization`, `POST/DELETE /organization/logo`, `POST/DELETE /organization/profile-document`.
- Sections: identity (logo upload with 2 MB image limit, name, legal names AR/EN, CR read-only, verified badge read-only); tax (VAT registered, VAT number); location (region, city, national address); contact (website; e-mail and phone read-only); activity (categories, suggestions visibility); company profile PDF (≤ 20 MB); features (API, auction, sponsorship: read-only with «لتفعيلها تواصل مع بافو»).
- `BillingProfileBanner` when `billing_profile_complete` is false, listing `billing_profile_missing` and highlighting those fields. The page accepts `?return=` (a same-origin path) and returns there after a save that completes the profile.
- Read-only for users without `organization.update`.
- Errors: 422 on `cr_number` if ever sent (it is never sent).

**W28 Account** · `/dashboard/account`

- Profile: name, phone, language (`PATCH /me`); avatar (`POST/DELETE /me/avatar`).
- Security: change password (`PUT /me/password`; «سيُسجَّل خروجكم من الأجهزة الأخرى.»; `password_incorrect`).
- **Delete account** (store requirement, ARCHITECTURE §13.8):
  - `GET /account/deletion` → if pending: «سيُحذف الحساب في {scheduled_for}» + "Cancel deletion" (`DELETE /account/deletion`).
  - Otherwise: the scope explained (owner: «سيُحذف حساب المنشأة وجميع أعضائها بعد 14 يوماً»; others: «سيُحذف حسابكم الشخصي فقط»), what is kept (invoices, competitions and offer records), password + optional reason → `POST /account/deletion`.
  - `account_deletion_blocked` → a list of `details.blockers` (issued competition or participation, title, link to W14); `account_deletion_pending`; `password_incorrect`.
- Sign out: `POST /auth/logout`.

**W29 Notifications** · `/dashboard/notifications`

- API: `GET /notifications?unread=&page=`, `POST /notifications/{id}/read`, `POST /notifications/read-all`, `DELETE /notifications/{id}`, `DELETE /notifications`.
- Filter All / Unread; `NotificationItem` (type icon, title, body, relative time, unread dot); click → S11; row menu: mark read, delete; header: mark all read, delete all (`ConfirmDialog`).
- Realtime: `notification.created` prepends (page 1) and updates the count; `notifications.unread_count` syncs the badge.
- States: **E** «لا توجد إشعارات»; **X**.

#### Billing (web only)

**W30 Subscription and billing** · `/dashboard/billing`

- API: `GET /billing/subscription` (`SubscriptionOverview`), `GET /billing/vouchers`.
- `SubscriptionCard` (current: plan, source paid/trial/grant, interval, seats, status, `starts_at`–`ends_at`, days-left meter, amounts); upcoming subscription (a renewal starting at `ends_at`); `SeatsMeter`; actions: Change plan / Renew (W31), Start trial (`trial_available`, `billing.purchase`); history table; `VoucherList` (code with a copy button, balance, valid until, reason, source competition); link to invoices (W34).
- States: no subscription → «لا توجد باقة فعّالة» with plans CTA; **F** without `billing.view`.

**W31 Plans** · `/dashboard/billing/plans`

- API: `GET /plans`, `GET /plans/custom-quote?seats=&interval=`.
- `IntervalToggle` (monthly / annual); `PlanCard` per plan (name, features, seats, price excl. VAT with the list price struck through when higher, «يُحتسب 15% ضريبة قيمة مضافة», "Current plan" badge, CTA); custom plan with `SeatsInput` (min–max) and a live quote (debounced 300 ms; `seats_out_of_range`).
- CTA → W32 with `plan`, `interval`, `seats`. An optional `?return=` is carried through checkout (used by invitee plan prompts).
- Users without `billing.purchase` see the plans with the CTA replaced by «يحتاج الدفع إلى صلاحية الشراء. تواصل مع مالك الحساب.» (S10).

**W32 Checkout** · `/dashboard/billing/checkout?plan=&interval=&seats=`

- **Billing profile gate:** when `billing_profile_complete` is false, `BillingProfileGate` lists the missing fields with "Complete now" → W27 `?return=` back here.
- `OrderSummary` before payment: plan, interval, seats, unit price, `CouponField` (`POST /billing/coupons/validate {purpose: subscription, plan_id, interval, seats}` → discount line); upgrade credit and VAT are computed by the server at payment creation, so the pre-summary says «يُحتسب رصيد الترقية والضريبة في الخطوة التالية».
- **Continue** → `POST /billing/checkout/subscription {plan_id, interval, seats, coupon_code, return_url}` with an `Idempotency-Key` → `Payment`:
  - `redirect_url` null and `succeeded` (zero total) → W33 with the payment id;
  - otherwise a confirmation panel with the server amounts (lines, subtotal, credit, discount, VAT, total, `expires_at`) and **Pay {total}** → `window.location.assign(redirect_url)`.
- `return_url` = `{origin}/{locale}/dashboard/billing/checkout/return` (no query; CONVENTIONS §4.3).
- Errors: `billing_profile_incomplete`, `subscription_downgrade_not_allowed`, `subscription_renewal_too_early` («يمكن التجديد ابتداءً من {renewable_from}»), `plan_not_available`, `seats_out_of_range`, coupon errors, `gateway_error`, `gateway_not_configured`, `return_url_not_allowed` (a configuration bug: generic message + log).

**W33 Payment return** · `/dashboard/billing/checkout/return?payment={id}`

- `usePaymentPoll(id)`: `GET /billing/payments/{id}` every 2 s for up to 60 s, then `POST /billing/payments/{id}/verify` once.
- `PaymentStatusPanel` by status and `purpose`:

  | Status | Subscription | Sponsorship, `intent: publish` | Sponsorship, `intent: invite` |
  |---|---|---|---|
  | `pending` | «نتحقق من الدفع…» spinner | same | same |
  | `succeeded` | «تم تفعيل {plan} حتى {ends_at}» → `fetchMe`; CTA the `return` path or W30 | Fetch the competition: `scheduled`/`live` → «تم الدفع ونُشرت المنافسة» → W14; still `draft` → «استلمنا الدفع ولم تُنشر المنافسة. راجعوها ثم انشروا دون دفع إضافي.» → W15 `review` | «تم الدفع وأُرسلت الدعوات» → W17 |
  | `failed` | `failure_message` + Try again → W32 | Try again → W15 `review` | Back → W17 |
  | `expired` | «انتهت مهلة الدفع» + Try again | same | same |
  | still `pending` after verify | «ما زلنا نتحقق. سيصلكم إشعار عند الاكتمال.» → W30 or W14 | same | same |

- The page never displays a card number or gateway detail; it only shows BAFO's payment amounts.

**W34 Invoices** · `/dashboard/billing/invoices`

- API: `GET /billing/invoices?page=`. `UiTable`: number, issue date, type (tax invoice, credit note), total incl. VAT, e-invoice status chip (cleared, reported, pending, rejected, failed), PDF download (`GET /billing/invoices/{id}/pdf` as a blob; `invoice_pdf_not_ready` → «الفاتورة قيد الإصدار»).
- States: **E** «لا توجد فواتير بعد».

**W35 Invoice detail** · `/dashboard/billing/invoices/{id}`

- `GET /billing/invoices/{id}`: number, dates, lines, subtotal, discount, VAT, total, ZATCA UUID, e-invoice status, payment reference, PDF download.

#### Integrations

**W36 Integrations overview** · `/dashboard/integrations`

- Cards: API clients (W37), Webhooks (W39), Import vendors (W41), Exports (W42), Documentation (`{API origin}/docs/api`, `openapi.yaml`, Postman collection).
- When `features.api_enabled` is false: API clients and Webhooks cards show «واجهة برمجة التطبيقات غير مفعّلة لمنشأتكم. تواصلوا مع بافو لتفعيلها.»; import and export stay available.
- The pages W37–W40 render that callout instead of calling the API (which would answer `api_access_disabled`).

**W37 API clients** · `/dashboard/integrations/api-clients`

- API: `GET /integrations/api-clients`, `POST …` (name, description, scopes).
- `ScopeChecklist`: every scope of ARCHITECTURE §14.4, defaults checked (all read scopes, `vendors:write`, `competitions:write`, `invitations:write`, `awards:sync`); `competitions:publish`, `competitions:manage`, `webhooks:manage` opt-in with a warning.
- After create: `SecretReveal` (client id + `client_secret`, copy buttons, «لن يظهر السر مرة أخرى», a required «حفظتُ السر» checkbox before closing).
- Table: name, status, scopes count, keys count, last used, created by.
- States: **E** «لا توجد عملاء واجهة برمجية».

**W38 API client detail** · `/dashboard/integrations/api-clients/{id}`

- Edit name, description, scopes (`PATCH`); keys table (prefix, masked key, expires, last used, revoked); Create key (`expires_in_days` 1–730, default 365) → `SecretReveal` with the key; Revoke key (`ConfirmDialog`); Rotate secret (`ConfirmDialog` → `SecretReveal`); Revoke client (danger `ConfirmDialog` → `DELETE` → W37).
- A usage snippet: the token request of API.md §3.1 with placeholders only.

**W39 Webhook endpoints** · `/dashboard/integrations/webhooks`

- API: `GET /integrations/webhook-endpoints`, `GET …/webhook-event-types`, `POST …` (url, event types or `*`, description) → `SecretReveal` (`whsec_…`).
- Table: URL, events, status (active, disabled with `disabled_reason`), `failing_since` warning, last success, last failure.
- Errors: `webhook_url_invalid` («يجب أن يكون الرابط https وعلى عنوان عام»).

**W40 Webhook endpoint detail** · `/dashboard/integrations/webhooks/{id}`

- Edit URL, events, description; enable or disable (`PATCH {status}`); Send test (`POST …/test` → 202 → refresh deliveries after 3 s); Rotate secret; Delete.
- `DeliveryLogTable`: event type, occurred at, status, attempts, last HTTP status, duration, next attempt; filter by status; row drawer with `last_error`, `last_response_excerpt` (a monospace `JsonPane`) and **Redeliver** (`POST /integrations/webhook-deliveries/{id}/redeliver`).
- The signature verification snippets of API.md §4.3 (static).

**W41 Import vendors** · `/dashboard/integrations/import`

- `ImportWizard`:
  1. Download a template (`GET /integrations/imports/templates/vendors?format=csv|xlsx` as a blob).
  2. Upload (`FileDrop`, csv or xlsx, ≤ 20 MB) → `POST /integrations/imports {file, type: vendors, mode: validate}` → poll `GET /integrations/imports/{job}` (2 s).
  3. Results: total, valid and error rows; `ImportErrorsTable` (`errors_preview`: row, column, code, message with localised code labels `integrations.import.row_codes.*`); download the errors file.
  4. **Import valid rows** → re-post the same file with `mode: commit` → poll → created and updated counts → link to W25.
- The file stays in memory between steps 2 and 4; leaving the page asks for confirmation.

**W42 Exports** · `/dashboard/integrations/exports`

- Form: type (results, offer log, awards, vendors), format (csv, xlsx), competition picker (results and offer log: `GET /competitions?role=issuer&q=`), date range (awards) → `POST /integrations/exports` → poll `GET /integrations/exports/{job}` → download `file`.
- The page lists the export jobs started in this browser session (§6 G2).
- Note: «المبالغ بالريال السعودي دون ضريبة القيمة المضافة، ومبالغ المنافسات المغلقة لا تظهر قبل فتح العروض.»

**W43 Error page** (`app/error.vue`)

- 404: «الصفحة غير موجودة» + Home; 500: «حدث خطأ غير متوقع» + Retry; maintenance: the `AppConfig` message. All pages keep the language switch.

### 2.5 End-to-end flows (web)

**F1 New issuer: register → verify → first competition**
W05 `POST /auth/register` → W06 `POST /auth/otp/verify` (token) → W10 with the `trial_available` alert → Start trial → `fetchMe` (`can_issue` true) → W12 → W15 steps 3–8 → Publish → W14.

**F2 Draft resume rule.** The first incomplete step is the first of: basics missing (title, category, region) → 2; auction without start price → 3; no close time → 4; invitations < `rules.min_participants` → 6; sponsorship enabled with mode not `none` and quote `passes_to_buy > 0` → 7; otherwise 8. The rule only picks where the wizard opens; publish checks stay on the server.

**F3 Publish with covered participation fees**
Step 7 (mode, cap, selection) → step 8 **Pay and publish** → `POST …/sponsorship/checkout {intent: publish}`:

- 409 `sponsorship_already_funded` → call `POST …/publish` directly;
- `billing_profile_incomplete` → W27 `?return=` step 8;
- 201 `Payment` → the confirmation panel → hosted page (`/pay/fake/{id}` locally) → W33 → published (or publish failed, which returns to step 8 with no second charge).

**F4 Invited supplier without an account (sponsored)**
E-mail → W03 (lookup, «رسوم مغطّاة») → «أنشئ حساب منشأتك» → W05 (pending token sent as `invitation_token`) → W06 → W24 (claim: the e-mail matches, 200) → W14 invitee view (`join_required`, `sponsored`) → Join → participant view → W19 live room.

**F5 Existing user, different e-mail than the invited one**
W03 → Sign in (W04) → W24 → 202 OTP to the invited e-mail → code → 200 → W14.

**F6 Participant offer**
W19 → amount → confirm (new key) → `POST …/offers` → 201 → snapshot applied (standing banner updates) → further snapshots through `live.updated`.

**F7 Close → BAFO → award**
`live.updated` with status `closed` → issuer W22 → Start BAFO round (shortlist) → `bafo_round` → cutoff → `closed` → choose the winner (justification when not rank 1) → Award → report regenerated → download the PDF.

**F8 Checkout for a plan from an invitation**
W14 invitee `plan_required` → View plans (`?return=` the competition) → W31 → W32 → hosted page → W33 → back to W14 → `access.state` is now `join_required` / `own_plan` → Join.

---

## 3. Mobile (Flutter, `apps/mobile`)

### 3.1 App structure

- **Features** (CONVENTIONS §5.1): `auth`, `home`, `competitions`, `live`, `invitations`, `team`, `profile`, `billing`, `notifications`, each with `data/`, `domain/`, `presentation/`.
- **Core services** (scaffold): `SessionCubit`, `LocaleCubit`, `AppGateCubit` (426 and maintenance), `ApiClient` with its interceptors, `TokenStore`, `PreferencesStore`, `ServerClock`, `RealtimeClient` (`ReverbRealtimeClient`), `PushService` (`NoopPushService` by default).
- **Core additions:** `AppConfigRepository` (cached `AppConfig`), `LookupsRepository` (cached per locale with the ETag), `CompetitionChannelHub` (ref-counted competition subscriptions, S4), `UnreadCountCubit` (user channel, shell badge), `NetworkStatusCubit` (fed by failed requests and the realtime state), `DeepLinkRouter` (S11 mapping), `BafoDateFormat`.
- **Shell** (scaffold `AppShell`): five tabs in this order: Home `/home`, Participating `/competitions` («مشاركاتي»; the scaffold label `navCompetitions` «المنافسات» should become «مشاركاتي» to match the web, §6), My competitions `/my-competitions` («منافساتي»), Notifications `/notifications` (unread badge), Account `/account`.
- **Competition screens open above the shell** on the root navigator (`parentNavigatorKey: rootNavigatorKey`), so `/competitions/:id` works from any tab and from push, and the bottom bar is hidden in the live room.

**Route tree** (`go_router`; paths of CONVENTIONS §4.3):

```
/splash                                   M01
/welcome                                  M02  (first run only)
/update-required                          M03
/maintenance                              M04
/login                                    M06
/register                                 M07–M09
/verify              (extra: email)       M10
/forgot-password                          M11
/reset-password      (extra: email)       M12
/legal/:code                              M13
/billing                                  M58  (root navigator; canonical deep link)
StatefulShellRoute (AppShell)
├─ /home                                  M14
├─ /competitions                          M16  participating list
│   └─ :id                  (root nav)    M17 / M21 / M38  detail by viewer_role
│       ├─ live                           M23 (participant) / M46 (issuer)
│       ├─ qa                             M31
│       ├─ my-offers                      M28  participant
│       ├─ offers                         M47  issuer
│       ├─ participants                   M41  issuer
│       ├─ invite                         M40  issuer
│       ├─ attachments                    M42  issuer
│       ├─ edit                           M39  issuer
│       └─ award                          M48  issuer (read-only)
├─ /my-competitions                       M33
│   └─ new                  (root nav)    M34–M37
├─ /notifications                         M49
└─ /account                               M51
    ├─ profile                            M52
    ├─ password                           M54
    ├─ organization                       M55
    ├─ team                               M56
    │   ├─ new                            M57
    │   └─ :membershipId                  M57
    ├─ settings                           M59
    ├─ help                               M60
    └─ delete-account                     M61
```

- **Redirects** (scaffold `authRedirect`): no session → `/login?from=<path>` (a path only, never a token); a restored session returns to `from`. `AppGateCubit` states override everything: update required → `/update-required`, maintenance → `/maintenance`. A first run without a session goes to `/welcome`.
- **Role guards:** issuer-only child routes (`offers`, `participants`, `invite`, `attachments`, `edit`, `award`) and participant-only ones (`my-offers`) check the loaded `viewer_role` and fall back to `/competitions/:id`.

### 3.2 Entitlement-only rule (no purchase UI)

Mobile reflects the plan the organisation already has; it never sells one (CD6).

| Never on mobile | Instead |
|---|---|
| Plan list, prices, custom plan quote, coupons, checkout, payment web views, trial start | `/billing` (M58) shows the current plan, status, days left and seats, with `billing.managed_on_web` «تُدار الاشتراكات والمدفوعات من لوحة تحكم بافو على الويب.» / "Subscriptions and payments are managed from the BAFO web dashboard." **No link, no button, no URL**, on iOS and Android. |
| The sponsorship (participation fees) step and checkout | The draft is created without it. If publish or invite returns `sponsorship_payment_required`, show `sponsorship.managed_on_web` «تُدفع رسوم المشاركة لهذه المنافسة من لوحة تحكم بافو على الويب. حُفظت المسودة.» / "Participation fees for this competition are paid from the BAFO web dashboard. Your draft is saved." In `selected` mode the invite screen also offers "Send without covering fees". |
| "Upgrade" after `seat_limit_reached` | «اكتملت مقاعد باقتكم.» + `billing.managed_on_web` |
| "View plans" after `plan_required` on join | `AccessInfoSheet` (M20): «تحتاج منشأتكم إلى باقة فعّالة للانضمام.» + `billing.managed_on_web`; Decline stays available |
| "Subscribe" after `issuer_plan_required` | «تحتاج منشأتكم إلى باقة فعّالة لطرح المنافسات.» + `billing.managed_on_web` |
| Home alerts with purchase CTAs | The same alert texts with no action button |
| Invoice PDF | `billing.invoices_on_web` (S11) |

The app never sends a request that could return `purchase_not_available_on_platform`. A widget test asserts that no billing screen contains a price, a URL or a purchase button.

### 3.3 Screen inventory (61 units)

Columns: **Bloc/Cubit** (feature) · **API** (app v1) · **RT** realtime · **States and notes**. Every screen implements L / E / X / F / N per S8 with `LoadingSkeleton`, `EmptyState` and `ErrorState`, and maps `ApiException.code` to `errors<Code>` ARB keys.

**A. Launch and system**

| # | Unit | Path | Bloc/Cubit | API | RT | States and notes |
|---|---|---|---|---|---|---|
| M01 | Splash | `/splash` | `SessionCubit`, `AppGateCubit` (core) | `GET /app-config`, `GET /me` | – | Native splash (charcoal, colour mark), then routing. Offline with a stored token: continue to the cached home with the offline banner. |
| M02 | Welcome and language | `/welcome` | `OnboardingCubit` (auth) | – | – | Slide 1 has the language switch (العربية / English). Three slides (create → invite → compete live and award), text-free art, captions as ARB strings. CTAs Sign in / Create account. Shown once (`PreferencesStore`). |
| M03 | Update required | `/update-required` | `AppGateCubit` | `GET /app-config` | – | From 426 `app_version_unsupported` (`details.min_version`) or `min_version`. Button opens `store_links.{platform}` (not a purchase). |
| M04 | Maintenance | `/maintenance` | `AppGateCubit` | `GET /app-config` | – | `maintenance.message`; retry every 60 s and on tap. |
| M05 | Offline banner (component) | all screens | `NetworkStatusCubit` (core) | – | realtime state | Mutations disabled while offline. |

**B. Authentication**

| # | Unit | Path | Bloc/Cubit | API | RT | States and notes |
|---|---|---|---|---|---|---|
| M06 | Sign in | `/login` | `LoginCubit` (exists) | `POST /auth/login` | – | `device_name` = "{model} · {platform}". Errors as W04; `email_not_verified` → M10. Links: forgot, register, legal. Language switch in the app bar. |
| M07 | Register · account | `/register` (step 1 of 3) | `RegisterCubit` | – | – | Name, e-mail (LTR), phone (`+966` fixed prefix, numeric keypad), password with the rule checklist, confirmation. |
| M08 | Register · company | `/register` (step 2) | `RegisterCubit` | `GET /lookups` | – | Company name, CR (numeric, 10), region, city, VAT switch + VAT (numeric, 15), legal names, website. |
| M09 | Register · address and consent | `/register` (step 3) | `RegisterCubit` | `POST /auth/register` | – | National address (optional), categories (multi-select sheet), suggestions switch, terms and privacy checkboxes (open M13). Server field errors jump back to their step. → M10. |
| M10 | Verify e-mail | `/verify` | `OtpCubit` | `POST /auth/otp/verify`, `POST /auth/otp/send` | – | `AutofillHints.oneTimeCode`; expiry countdown; resend cooldown; errors as W06. Success → token in `TokenStore` → `SessionCubit` authenticated → `/home`. |
| M11 | Forgot password | `/forgot-password` | `ForgotPasswordCubit` | `POST /auth/password/forgot` | – | Neutral 202 message → M12. |
| M12 | Reset password | `/reset-password` | `ResetPasswordCubit` | `POST /auth/otp/check`, `POST /auth/password/reset`, `POST /auth/otp/send` | – | → M06 with a toast. |
| M13 | Legal document | `/legal/:code` | `LegalCubit` | `GET /legal/{code}` | – | Markdown rendering, version and date; **N** when unpublished. |

Team invitations (`/auth/accept-invite`) and competition invitation links open on the **web** (no app links in the MVP). Members accept there, then sign in on mobile.

**C. Home**

| # | Unit | Path | Bloc/Cubit | API | RT | States and notes |
|---|---|---|---|---|---|---|
| M14 | Home | `/home` | `HomeCubit` | `GET /home` | user channel (via `UnreadCountCubit`); refetch on `notification.created` | Stat tiles (issuer and participant sets as W10), subscription card (read-only), alerts without purchase actions (§3.2), recent activity, quick actions (Create competition per S10, Participating). Pull to refresh. |
| M15 | Plan-status notice (component and sheet) | – | – | – | – | Shared `InfoNotice` for every entitlement message of §3.2. |

**D. Participant**

| # | Unit | Path | Bloc/Cubit | API | RT | States and notes |
|---|---|---|---|---|---|---|
| M16 | Participating list | `/competitions` | `ParticipatingListBloc` (competitions) | `GET /competitions?role=participant&status_group=&q=&page=` | – | Segmented filter Active / Ended / All; search; infinite scroll (`has_more`). `CompetitionCard` as W23 with Join / Decline on actionable cards. Refetch on resume. **E** «لا توجد منافسات مدعو إليها بعد». |
| M17 | Detail · invitee | `/competitions/:id` | `CompetitionDetailCubit` | `GET /competitions/{id}`, `GET …/attachments` | – (refetch on resume) | `InvitationTeaser`, `AccessStateCard` (W14 table), `FeesCoveredBadge`, rules summary, invitation documents, join-deadline countdown. |
| M18 | Join sheet | sheet on M17 | `InvitationActionCubit` (invitations) | `POST /invitations/{id}/join` | – | Rules summary, a link to M13 `competition_rules`, the terms checkbox, the sponsored line when covered. Success → reload as participant → then offer the push explainer (M50) once. |
| M19 | Decline sheet | sheet on M17 / M16 | `InvitationActionCubit` | `POST /invitations/{id}/decline` | – | Optional reason. |
| M20 | Access info sheet | sheet | – | – | – | `plan_required` and `unavailable` explanations (§3.2). |
| M21 | Detail · participant | `/competitions/:id` | `CompetitionDetailCubit` | `GET /competitions/{id}`, `GET …/attachments` | participant channel: `competition.updated` | Header (chips, countdown, fees covered), my standing summary, buttons Live room / Q&A / My offers, rules, documents, result panel (M29), cancellation reason. |
| M22 | Attachments section | in M17, M21, M38 | `AttachmentsCubit` | `GET …/attachments`, `GET /files/{id}/download` | – | Download with the bearer header to the app's temporary directory, then open with the system viewer or share sheet. External links open in the browser. |
| M23 | Live room | `/competitions/:id/live` | `LiveRoomBloc` (live) | `GET …/live`, `POST …/live/heartbeat`, `GET /time` | participant channel: `live.updated`, `competition.updated` | W19 participant content, single column: countdown, standing banner, leading amount and ladder when present, my offer card, composer pinned to the bottom above the keyboard. Connection states S4. |
| M24 | Offer confirm sheet | sheet on M23 | `OfferSubmitCubit` (live) | `POST …/offers` | – | S5 (key per intent, retry, error mapping). Haptic feedback on success. |
| M25 | Outlier confirm dialog | dialog on M24 | `OfferSubmitCubit` | `POST …/offers` (`confirm_outlier: true`, new key) | – | S5 wording. |
| M26 | Sealed submit and receipt | state of M23 | `LiveRoomBloc` + `OfferSubmitCubit` | same | same | Lock panel, receipt sheet (seq, time with ms). |
| M27 | BAFO final offer | state of M23 | same | same | same | Charcoal `BafoBanner`, reference amount, one-shot confirm. Not shortlisted → info state. |
| M28 | My offers | `/competitions/:id/my-offers` | `MyOffersCubit` | `GET …/my-offers` | refresh on `live.updated` of kind `offer` or `void` | List with time (ms), amount, stage, voided badge. |
| M29 | Result panel | in M21 | – | – | – | Won (with `winning_amount_minor` when published), not selected, not awarded, cancelled with reason. |
| M30 | Rules sheet | sheet | – | – | – | `rules_summary` lines. |

**E. Q&A (issuer and participant)**

| # | Unit | Path | Bloc/Cubit | API | RT | States and notes |
|---|---|---|---|---|---|---|
| M31 | Q&A | `/competitions/:id/qa` | `QaBloc` (competitions) | `GET …/comments?page=` | `comment.created` on the viewer's channel | Author rendering as W18; `comments_closed` → read-only. **E** «لا توجد استفسارات بعد». |
| M32 | Ask or reply sheet | sheet on M31 | `QaBloc` | `POST …/comments` | – | 1–2000 characters with a counter. |

**F. Issuer**

| # | Unit | Path | Bloc/Cubit | API | RT | States and notes |
|---|---|---|---|---|---|---|
| M33 | My competitions | `/my-competitions` | `IssuedListBloc` (competitions) | `GET /competitions?role=issuer&status_group=&q=&page=` | – | Segmented Active / Drafts / Ended; FAB Create (S10). **E** per segment. |
| M34 | Create · type, format, preset | `/my-competitions/new` (step 1 of 4) | `CreateCompetitionCubit` | `GET /lookups` | – | Direction radio cards (auction gated), format, preset cards. Advanced rules: «الإعدادات المتقدمة متاحة في لوحة التحكم على الويب.» |
| M35 | Create · basics | step 2 | `CreateCompetitionCubit` | – | – | Title, description, category (auction filter, "Other" text), region. |
| M36 | Create · prices and schedule | step 3 | `CreateCompetitionCubit` | – | – | Start price (required for auctions), reserve (issuer-only note), opens (on publish or a date), closes; Riyadh time pickers. |
| M37 | Create · review and save | step 4 | `CreateCompetitionCubit` | `POST /competitions` | – | Preset rules summary; Save draft → M38, which then suggests Invite (M40) and Attachments (M42). Errors as W12. |
| M38 | Detail · issuer | `/competitions/:id` | `CompetitionDetailCubit` | `GET /competitions/{id}`, `GET …/attachments`, `GET …/sponsorship` | issuer channel: `competition.updated`, `invitation.updated` | Header, schedule timeline, rules, counts, sponsorship counters (read-only), documents; action sheet from `permissions`: Edit, Invite, Attachments, Publish (draft), Cancel (CD12), Delete draft; links to Live monitor, Offers log, Participants, Q&A, Award. Extend, BAFO, award, revoke and close-without-award show «متاح في لوحة التحكم على الويب.» |
| M39 | Edit | `/competitions/:id/edit` | `EditCompetitionCubit` | `PATCH /competitions/{id}` | – | Fields by status as W16; drafts also edit prices and schedule. `competition_not_editable`. |
| M40 | Invite participants | `/competitions/:id/invite` | `InviteParticipantsCubit` (invitations) | `GET …/suggestions`, `GET /vendors?q=`, `POST …/invitations` | – | Segmented Suggestions / E-mail / Vendors; staged chips; all-or-nothing errors per row; sponsored rows only within free slots (§3.2). |
| M41 | Participants | `/competitions/:id/participants` | `InvitationsCubit` (invitations) | `GET …/invitations`, `GET …/sponsorship`, `POST …/resend`, `DELETE …/invitations/{inv}` | `invitation.updated` | Status filter with counts, rows as W17, resend and revoke with confirmation; sponsorship counters read-only. |
| M42 | Attachments (manage) | `/competitions/:id/attachments` | `AttachmentsCubit` | `GET/POST/DELETE …/attachments` | `competition.updated` | Upload from files (kind `document`, 100 MB, allowed types) with progress; delete in draft or scheduled. Links and invitation documents are web-only. |
| M43 | Publish sheet | sheet on M38 | `PublishCubit` | `POST …/publish` | – | Checklist hints; errors as W15 step 8, but `sponsorship_payment_required` and `issuer_plan_required` show §3.2 texts. Success → push explainer (M50) once. |
| M44 | Cancel sheet | sheet on M38 | `CancelCompetitionCubit` | `GET /lookups/close-reasons?kind=cancel`, `POST …/cancel` | – | Reason (radio), note when `requires_note`, red confirm «إلغاء المنافسة». |
| M45 | Delete draft dialog | dialog on M38 | `CompetitionDetailCubit` | `DELETE /competitions/{id}` | – | → M33. |
| M46 | Live monitor (read-only) | `/competitions/:id/live` | `IssuerLiveBloc` (live) | `GET …/live`, `GET /time` | issuer channel: `live.updated` | Countdown, leader card, reserve indicator, ranking list (sealed lock before unlock), metrics, online count, connection banner. No actions. |
| M47 | Offers log | `/competitions/:id/offers` | `OffersLogBloc` (competitions) | `GET …/offers/log?after_seq=` | `offer.accepted` (gap fetch) | Infinite list by `seq`; sealed amounts shown as «مغلق». |
| M48 | Award (read-only) | `/competitions/:id/award` | `AwardCubit` | `GET …/award`, `GET …/offers` | refresh on `live.updated` kind `award` | Final standings; award detail when awarded; in `closed`: «تتم الترسية من لوحة التحكم على الويب.» |

**G. Notifications**

| # | Unit | Path | Bloc/Cubit | API | RT | States and notes |
|---|---|---|---|---|---|---|
| M49 | Notifications | `/notifications` | `NotificationsBloc` | `GET /notifications?unread=&page=`, `POST …/{id}/read`, `POST …/read-all`, `DELETE …/{id}`, `DELETE /notifications` | user channel: `notification.created`, `notifications.unread_count` | All / Unread; swipe to delete; tap → S11 route; mark all read. **E** «لا توجد إشعارات». |
| M50 | Push permission explainer | sheet | `PushPermissionCubit` | `POST /devices` | – | Shown once, after the first successful join (M18) or publish (M43), never at cold start (CONVENTIONS §5.1). "Allow" → OS prompt → token → `POST /devices {token, platform, device_name, app_version}`. With `NoopPushService` no token exists and no request is sent. |

**H. Account**

| # | Unit | Path | Bloc/Cubit | API | RT | States and notes |
|---|---|---|---|---|---|---|
| M51 | Account hub | `/account` | `AccountCubit` (profile) | `GET /me` | – | User card, organisation card, plan card (→ M58), links to M52–M61, Sign out (`POST /auth/logout`, then `DELETE /devices/{id}` when registered, then clear the token). |
| M52 | Profile | `/account/profile` | `ProfileCubit` | `PATCH /me`, `POST/DELETE /me/avatar` | – | Name, phone, language. |
| M53 | Image source sheet | sheet on M52, M55 | – | – | – | Themed camera / gallery / remove (replaces the legacy purple dialog). |
| M54 | Change password | `/account/password` | `ChangePasswordCubit` | `PUT /me/password` | – | «سيُسجَّل خروجكم من الأجهزة الأخرى.» |
| M55 | Organisation | `/account/organization` | `OrganizationCubit` | `GET/PATCH /organization`, `POST/DELETE /organization/logo` | – | Fields as W27 (profile PDF upload is web-only); read-only without `organization.update`; billing-profile banner without a purchase action. |
| M56 | Team | `/account/team` | `TeamCubit` (team) | `GET /team/members` | – | Seats meter, member tiles, **F** without `team.manage`. |
| M57 | Team member form | `/account/team/new`, `/account/team/:membershipId` | `TeamMemberFormCubit` | `POST/PATCH/DELETE /team/members/{m}`, `POST …/resend-invitation` | – | As W26; `seat_limit_reached` → §3.2. |
| M58 | Plan and subscription | `/billing` | `SubscriptionStatusCubit` (billing) | `GET /billing/subscription` (`billing.view`), otherwise `me.subscription` | – | Plan, source, status, days left, seats, vouchers count; `billing.managed_on_web`. No prices, links or buttons. |
| M59 | Settings | `/account/settings` | `SettingsCubit`, `LocaleCubit` | `PATCH /me {locale}` | – | Language, notification permission status (opens the OS settings), app version, legal links (M13), open-source licences. |
| M60 | Help and contact | `/account/help` | `ContactCubit` | `POST /contact` | – | Support e-mail, phone and WhatsApp from `AppConfig.support` (tap to e-mail or call); the contact form. |
| M61 | Delete account | `/account/delete-account` | `AccountDeletionCubit` (profile) | `GET/POST/DELETE /account/deletion` | – | As W28 (scope, blockers, pending with cancel). Store requirement. |

### 3.4 Bloc and cubit catalogue

States follow CONVENTIONS §5.1 (`sealed class XState` with `XInitial`, `XLoading`, `XLoaded`, `XFailure`); blocs call repositories, never Dio.

| Feature | Bloc / Cubit | Kind | Main events or methods | Notes |
|---|---|---|---|---|
| core | `SessionCubit` (exists) | Cubit | `restore`, `signedIn(AuthTokenPayload)`, `refreshMe`, `signOut` | Holds `Me` |
| core | `AppGateCubit` (exists) | Cubit | from the interceptor: 426, 503 `maintenance` | – |
| core | `LocaleCubit` (exists) | Cubit | `setLocale` | Persists; `PATCH /me` when signed in |
| core | `UnreadCountCubit` | Cubit | user channel events, `refresh` | Shell badge |
| core | `NetworkStatusCubit` | Cubit | request failures, realtime state | Offline banner |
| auth | `OnboardingCubit`, `LoginCubit` (exists), `RegisterCubit`, `OtpCubit`, `ForgotPasswordCubit`, `ResetPasswordCubit`, `LegalCubit` | Cubit | form submit | `RegisterCubit` keeps the 3 steps |
| home | `HomeCubit` | Cubit | `load`, `refresh` | – |
| competitions | `ParticipatingListBloc`, `IssuedListBloc` | Bloc | `Started`, `FilterChanged`, `QueryChanged` (300 ms debounce), `NextPageRequested`, `Refreshed` | Page pagination, `has_more` |
| competitions | `CompetitionDetailCubit` | Cubit | `load`, `refetch`, channel `competition.updated` | Exposes `viewer_role`, `permissions`, `access` |
| competitions | `CreateCompetitionCubit`, `EditCompetitionCubit`, `PublishCubit`, `CancelCompetitionCubit`, `AttachmentsCubit`, `AwardCubit`, `MyOffersCubit` | Cubit | – | – |
| competitions | `QaBloc` | Bloc | `Started`, `NextPageRequested`, `CommentReceived`, `CommentPosted` | De-duplicates by id |
| competitions | `OffersLogBloc` | Bloc | `Started`, `OfferReceived(seq)`, `GapDetected`, `NextPageRequested` | Seq ordering |
| invitations | `InvitationActionCubit` | Cubit | `join`, `decline` | – |
| invitations | `InviteParticipantsCubit`, `InvitationsCubit` | Cubit | – | `invitation.updated` upserts |
| live | `LiveRoomBloc` | Bloc | `Opened`, `SnapshotReceived(v)`, `ConnectionChanged`, `Resumed`, `Paused`, `HeartbeatTick`, `PollTick`, `ClockTick` | S4 resync, `v` guard, connection states, heartbeat, polling fallback |
| live | `OfferSubmitCubit` | Cubit | `amountChanged`, `openConfirm` (creates the key), `submit`, `confirmOutlier`, `retry` | S5; emits the snapshot to `LiveRoomBloc` |
| live | `IssuerLiveBloc` | Bloc | `Opened`, `SnapshotReceived(v)`, `ConnectionChanged`, `Resumed` | Read-only |
| team | `TeamCubit`, `TeamMemberFormCubit` | Cubit | – | – |
| profile | `AccountCubit`, `ProfileCubit`, `ChangePasswordCubit`, `OrganizationCubit`, `SettingsCubit`, `ContactCubit`, `AccountDeletionCubit` | Cubit | – | – |
| billing | `SubscriptionStatusCubit` | Cubit | `load` | Read-only |
| notifications | `NotificationsBloc` | Bloc | `Started`, `FilterChanged`, `NextPageRequested`, `NotificationReceived`, `MarkedRead`, `MarkedAllRead`, `Deleted` | – |
| notifications | `PushPermissionCubit` | Cubit | `explainIfNeeded`, `request` | Uses `PushService` |

Tests (CONVENTIONS §5.2): `bloc_test` for every bloc; `LiveRoomBloc` covers `v` ordering, buffered resync, reconnect and polling transitions; `OfferSubmitCubit` covers the idempotent retry and the outlier re-send with a new key.

### 3.5 Realtime and app lifecycle

- `CompetitionChannelHub.acquire(competitionId, viewerRole, orgId)` returns a shared stream; the last `release` unsubscribes. M21/M38, M23/M46, M31, M41 and M47 all acquire the same channel.
- The authoriser posts to `/broadcasting/auth` with the bearer header. The Android dev flavour uses host `10.0.2.2` (ARCHITECTURE §9.1).
- **Paused:** stop the heartbeat and polling at once; disconnect the socket after 30 s in the background (the OS drops it anyway).
- **Resumed:** `GET /time`, reconnect, resync every acquired channel (S4), refetch the visible list screens.
- Critical changes while in the background arrive by push, without amounts (ARCHITECTURE §11.1).

### 3.6 Push notifications and deep links

- `PushService` (abstract) with `NoopPushService` now and `FirebasePushService` behind a flavour flag later (CONVENTIONS §5.1).
- Payload data: `{type, notification_id, subject_type, subject_id, route}` (all strings).
- **Tap handling:** `DeepLinkRouter.open(route, notificationId)`:
  1. map the route with S11 (unknown → `/notifications`);
  2. `POST /notifications/{notification_id}/read` (fire and forget);
  3. `context.push(mapped)`; for `/competitions/:id/...` the detail screen resolves the viewer role.
- **Cold start:** the initial message is held until `SessionCubit` is authenticated; without a session the mapped path becomes `from` on `/login`.
- **Foreground:** no system banner; `BafoToast` shows the title with an "Open" action; `UnreadCountCubit` increments.
- **Sign out:** `DELETE /devices/{id}` for the registered device.
- Android notification channel names are localised («تنبيهات بافو» / "BAFO alerts"); the small icon is `ic_stat_bafo` (R2R3 §4).

### 3.7 Web-only features and what mobile shows

| Feature | Mobile shows |
|---|---|
| Advanced rules (step, visibility, auto-extend, final window, BAFO, publication) | Preset summary + «الإعدادات المتقدمة متاحة في لوحة التحكم على الويب.» |
| Extend, BAFO round, award, revoke, close without award | Read-only states + «متاح في لوحة التحكم على الويب.» |
| Participation fees (config and payment) | Read-only counters on M41; `sponsorship.managed_on_web` |
| Plans, checkout, invoices, trial, coupons, vouchers | M58 status only |
| External links and invitation documents (upload) | Listed and downloadable; upload on the web |
| Organisation profile PDF (upload) | Downloadable only |
| Vendors directory, integrations, exports, import | Not shown; `/integrations` deep links go to `/home` |
| Invitation and team-invitation landing pages | The web handles e-mail links; the app lists bound invitations in M16 |
| Result PDF | Not in the MVP app |

---

## 4. Component inventory

### 4.1 Token roles

Source: `assets/brand/04_ui_color_tokens/bafo_colors.json` plus the approved gap-fillers (05_brand §2.1, R2R3 D1 strict AA). Web roles are the CSS variables and Tailwind utilities of `apps/web/app/assets/css/main.css`; Flutter roles are `ColorScheme` and `BafoSemanticColors` (`lib/core/theme/`). **Components use roles only, never hex values.**

| Role | Web utility | Flutter | Light value | Used for |
|---|---|---|---|---|
| Page | `bg-page` | `ColorScheme.surface` | `#FFFFFF` | page background |
| Canvas | `bg-canvas` | `surfaceContainer` | `#F3F4F5` | dashboard content area |
| Surface | `bg-surface` + `border-line` | `surface` + `outlineVariant` | `#FFFFFF` / `#E5E7EB` | cards, tables, dialogs |
| Muted surface | `bg-surface-muted` | `surfaceContainerLow` | `#F9FAFA` | table headers, read-only fields |
| Text | `text-fg` | `onSurface` | `#1C1F26` | primary text |
| Muted text | `text-fg-muted` | `onSurfaceVariant` | `#6B7280` | secondary text (never on `#F3F4F5` below 14 px) |
| Input border | `border-line-strong` | `outline` | `#6B7280` | inputs (≥ 3:1) |
| Primary action | `bg-primary text-primary-fg`, hover `bg-primary-hover` | `primary` / `onPrimary` | `#0B7A55` / `#FFFFFF`, hover `#025D3E` | one primary button per view |
| Link | `text-link` / `.link` | `BafoSemanticColors.link` | `#0B7A55` | inline links |
| Brand (non-text) | `text-brand`, focus `ring` | `brandGreen` | `#0E9F6E` | mark, icons ≥ 24 px, focus ring, progress |
| Leading / success | `bg-primary-soft text-primary-soft-fg` (`success-*`) | `leading`, `success` | `#E6F7F1` / `#0B7A55` | leading standing, live status, joined |
| Outbid / warning | `bg-warning-soft text-warning-soft-fg` | `outbid`, `warning` | `#FEF3E2` / `#8C5802` | not leading, closing soon, plan required |
| Sponsored / info | `bg-info-soft text-info-soft-fg` | `sponsored`, `info` | `#EAF1FE` / `#1D4ED8` (web), `#2563EB` (Flutter; use ≥ 13 sp) | fees covered, scheduled, extended, invitation sent |
| Neutral | `bg-neutral-soft text-neutral-soft-fg` | `neutral` | `#F3F4F5` / `#374151` | draft, evaluation, direction chips, not selected |
| Danger | `bg-danger text-danger-fg`; soft `bg-danger-soft text-danger-soft-fg` | `destructive` / `onDestructive`; `destructiveContainer` | `#B3261E` / `#FFFFFF`; `#FBEAE9` / `#8C1E17` | destructive buttons, errors, cancelled |
| Inverse | `bg-fg text-fg-inverse` | `inverseSurface` / `onInverseSurface` | `#1C1F26` / `#FFFFFF` | BAFO round banner, toasts |
| Overlay | `bg-overlay` | barrier colour | `rgb(20 22 27 / 0.55)` | modal backdrops |

Shape and type: button radius 10 (`radius-md`), cards 14 (`radius-lg`), 48 px/dp control height; numbers use `tabular-nums` / `FontFeature.tabularFigures()`; Arabic line-height 1.6, Latin 1.5 (web base layer; Flutter `typography.dart`).

### 4.2 Web UI kit (`apps/web/app/components/ui`, prefix `Ui`)

**Existing in the scaffold** (keep; extend where noted): `UiAlert`, `UiAvatar`, `UiBadge` (tones neutral, primary, success, warning, danger, info; `solid`), `UiButton` (primary, secondary, ghost, danger, danger-ghost, link), `UiCard`, `UiCheckbox`, `UiCountdown` (see §6 R-W5), `UiDateTimePicker` (Riyadh time), `UiDrawer`, `UiDropdownMenu`, `UiEmptyState`, `UiField`, `UiFileDrop`, `UiIconButton`, `UiInput`, `UiModal`, `UiMoneyInput`, `UiOrgLogo`, `UiPagination`, `UiRadioGroup`, `UiSegmented`, `UiSelect`, `UiSkeleton`, `UiStatusPill` (see §6 R-W3), `UiStepper`, `UiSwitch`, `UiTable` (stacked cards on narrow screens), `UiTabs`, `UiTextarea`, `UiToaster`, `UiTooltip`. App frame (`components/app`): `AppLogo`, `AppTagline`, `AppSidebarNav`, `AppNotificationsMenu`, `AppLanguageSwitch`, `AppThemeMenu`, `AppUserMenu`, `AppOrgSwitcher` (shows the single organisation).

**To add**

| Component | Purpose | Tokens |
|---|---|---|
| `UiAmount` | `formatMoney` in `<bdi class="tabular-nums">`; `sign`, `size` props | `text-fg` |
| `UiDateTime`, `UiRelativeTime` | S6 formats; `title` with the full date and «بتوقيت الرياض» | `text-fg-muted` |
| `UiPhoneInput` | fixed `+966`, 9 digits, LTR | `border-line-strong` |
| `UiOtpInput` | one input, six drawn boxes, `one-time-code` | `border-line-strong`, `ring` |
| `UiPasswordInput` | show/hide + live rule checklist | success / muted icons |
| `UiSearchInput` | 300 ms debounce, clear button | – |
| `UiCombobox` (multi) | categories, vendors, participants shortlist | – |
| `UiEmailChipsInput` | paste-split e-mails with per-chip validation | danger-soft on invalid chips |
| `UiCopyButton` | copy with a polite "Copied" announcement | – |
| `UiSecretReveal` | reveal-once secret or key with copy and a required acknowledgement | warning-soft notice |
| `UiJsonPane` | monospace, LTR, scrollable | `bg-surface-muted` |
| `UiMarkdown` | sanitised Markdown (legal) | – |
| `UiConfirmDialog` | destructive confirmation on `UiModal` | `bg-danger` button |
| `UiForbiddenState`, `UiErrorState`, `UiNotFoundState` | S8 | – |
| `UiBanner` | global alert strip (home alerts, offline, maintenance) | warning / info soft |
| `UiMeter` | seats and days-left meters | `bg-brand` fill on `bg-neutral-soft` |
| `UiStatTile` | number + label + link | – |
| `UiKeyValueList` | definition lists (award, invoice) | – |
| `UiPageHeader`, `UiFilterBar` | page title, actions, filters | – |
| `UiProgress` | upload and job progress | `bg-brand` |
| `UiConnectionIndicator` | S4 connection states | leading / warning / neutral |

### 4.3 Web domain components (`apps/web/app/components/<area>`)

| Area | Components |
|---|---|
| `competitions/` | `DirectionChip` (neutral-soft, charcoal chevron, never mirrored), `FormatChip`, `CompetitionStatusChip` + `CompetitionOverlayPills` (S2), `CompetitionRow`, `CompetitionCard`, `RulesSummary`, `ScheduleTimeline`, `SchedulePreview`, `CountsGrid`, `AttachmentList`, `AttachmentUploader`, `DirectionRadioCards`, `PresetCard`, `RulesForm`, `SetupChecklist`, `ReasonPicker`, `ExtendDialog`, `CancelDialog`, `CloseWithoutAwardDialog`, `RevokeAwardDialog` |
| `live/` | `CountdownPanel`, `StandingBanner` (leading = primary-soft + check; outbid = warning-soft + alert; rank and hidden = neutral), `Ladder`, `MyOfferCard`, `OfferComposer`, `OfferConfirmDialog`, `OutlierConfirmDialog`, `SealedLockPanel`, `SealedReceipt`, `BafoBanner` (inverse + mark), `BafoRoundCard`, `ResultPanel`, `LeaderCard`, `ReserveMetIndicator` (issuer only), `RankingTable`, `OfferFeed`, `MetricTile`, `ConnectionBanner`, `ServerTimingHint` |
| `offers/` | `OfferLogTable`, `StandingTable` |
| `award/` | `AwardCandidateTable`, `JustificationFields`, `AwardConfirmDialog`, `AwardDetailCard`, `BafoShortlistDialog`, `ReportButton` |
| `invitations/` | `InvitationTeaser`, `AccessStateCard`, `FeesCoveredBadge` (info-soft, ticket icon), `JoinDialog`, `DeclineDialog`, `InvitePicker` (`SuggestionList`, `VendorPicker`, `UiEmailChipsInput`), `InviteDrawer`, `InvitationStatusChip`, `InvitationTable` |
| `sponsorship/` | `SponsorshipModeCards`, `SponsorshipQuoteTable`, `SponsorshipCard`, `CoverageChip` |
| `qa/` | `QaThread`, `QaComposer` |
| `billing/` | `PlanCard`, `IntervalToggle`, `SeatsInput`, `OrderSummary`, `CouponField`, `BillingProfileGate`, `BillingProfileBanner`, `PaymentStatusPanel`, `SubscriptionCard`, `VoucherList`, `InvoiceTable`, `EinvoiceStatusChip` |
| `integrations/` | `ScopeChecklist`, `ApiKeyTable`, `EventTypePicker`, `DeliveryLogTable`, `ImportWizard`, `ImportErrorsTable`, `JobProgress`, `ExportForm` |
| `team/` | `SeatsMeter`, `MemberTable`, `MemberDrawer`, `RolePicker` |
| `vendors/` | `VendorTable`, `VendorDrawer`, `ExternalRefsEditor` |
| `home/` | `ActivityList`, `AlertBanner` |
| `notifications/` | `NotificationItem`, `NotificationTypeIcon` |

### 4.4 Flutter widget kit (`apps/mobile/lib/widgets`, exported by `widgets.dart`)

**Existing in the scaffold:** `BafoAppBar` (logo by locale), `BafoBottomSheet`, `BafoButton` (primary, secondary, text, destructive), `BafoCard`, `BafoDropdown`, `BafoTextField`, `BafoToast`, `BrandMark`, `ConfirmDialog`, `CountdownText` (S3 format), `EmptyState`, `ErrorState`, `LoadingSkeleton` / `LoadingSkeletonList`, `MoneyText`, `OrgAvatar`, `SectionHeader`, `StatusPill` with `StatusTone` (neutral, success, warning, info, danger, leading, outbid, sponsored).

**To add** (shared ones in `lib/widgets`, feature-specific ones in `features/<f>/presentation/widgets`):

| Widget | Mirrors web | Tokens |
|---|---|---|
| `DirectionChip`, `FormatChip`, `CompetitionStatusChip` (+ overlay pills) | same | `StatusTone.neutral` for direction; S2 table for status; chevrons outside `Directionality` mirroring |
| `CompetitionCard` | `CompetitionCard` | `BafoCard`, `outlineVariant` |
| `RulesSummaryList`, `ScheduleTimeline`, `KeyValueList` | same | `onSurfaceVariant` labels |
| `AttachmentTile` | `AttachmentList` row | – |
| `StandingBanner` | same | `leading` / `outbid` / `neutral` + icon; `Semantics(liveRegion: true)` |
| `LadderList`, `MyOfferCard`, `LeaderCard`, `RankingTile`, `OfferLogTile` | same | `MoneyText` inside LTR islands |
| `OfferComposer` with `MoneyInputField` | same | `outline`, `primary` |
| `OfferConfirmSheet`, `OutlierConfirmDialog` | same | – |
| `SealedLockPanel`, `BafoBanner`, `ResultPanel` | same | `neutral`; `inverseSurface` for BAFO |
| `ConnectionBanner` | same | `warning`, `neutral` |
| `FeesCoveredBadge`, `AccessStateCard`, `InvitationTile`, `SuggestionTile`, `EmailChipsField` | same | `sponsored` |
| `QaThreadTile`, `NotificationTile`, `StatTile`, `ActivityTile` | same | – |
| `PlanStatusCard`, `SeatsMeter`, `MemberTile`, `InfoNotice` | same (no purchase actions) | `info`, `brandGreen` meter |
| `PhoneField`, `OtpField`, `PasswordField` (rule checklist), `CrField`, `VatField`, `DateTimeField` (Riyadh), `SearchField` | web inputs | `outline` borders, numeric keyboards, LTR text |
| `SegmentedFilter`, `StepperHeader` | `UiSegmented`, `UiStepper` | `primaryContainer` selection |
| `ForbiddenState`, `OfflineBanner` | S8 | – |
| `ImageSourceSheet`, `ReasonPickerSheet` | – | themed (replaces the legacy purple dialog) |

---

## 5. i18n namespaces and keys

**Namespaces per screen** are in the route map (§2.2) and the mobile inventory (the feature name). Shared kit strings live in `common.*`; statuses in `competitions.status.*`; error codes in `errors.<code>` (every code of CONVENTIONS §8 that a client can receive gets a key; the server `message` is the fallback). Empty states use `<area>.<list>.empty.{title,body,cta}`.

**Web ↔ ARB.** `live.status.not_leading.tender` / `.auction` (web) become one ARB key `liveStatusNotLeading` with `{direction, select, tender{…} auction{…} other{…}}`. Plurals use 6 pipe-separated forms on the web and ICU `plural` in ARB.

**Direction-variant keys (binding names).** Draft copy from R5 §10.2, adapted to the glossary:

| Key (web; add `.tender` / `.auction`) | Tender AR / EN | Auction AR / EN |
|---|---|---|
| `competitions.direction.rule` | الأقل سعراً يفوز / Lowest offer wins | الأعلى سعراً يفوز / Highest offer wins |
| `competitions.setup.type.issuer_role` | أنت المشتري / You are buying | أنت البائع / You are selling |
| `competitions.participants_noun` | الموردون / Suppliers | المزايدون / Bidders |
| `rules.start_price.label` | سعر السقف / Ceiling price | سعر الافتتاح / Opening price |
| `rules.reserve_price.label` | السعر المستهدف / Target price | الحد الأدنى المقبول / Reserve price |
| `rules.min_step.label` | الحد الأدنى للتخفيض / Minimum decrement | الحد الأدنى للزيادة / Minimum increment |
| `rules.must_beat.own` | يجب أن يكون كل عرض أقل من عرضك السابق / Each offer must be lower than your previous offer | يجب أن يكون كل عرض أعلى من عرضك السابق / Each offer must be higher than your previous offer |
| `offers.hint.required_next` | يجب ألا يزيد عرضك التالي عن {amount}. / Your next offer must be {amount} or lower. | يجب ألا يقل عرضك التالي عن {amount}. / Your next offer must be {amount} or higher. |
| `offers.hint.start_price` | لا يتجاوز سعر السقف {amount}. / Must not exceed the ceiling price of {amount}. | لا يقل عن سعر الافتتاح {amount}. / Must not be below the opening price of {amount}. |
| `live.status.not_leading` | عرضك ليس العرض المتصدر. خفّض عرضك لتنافس. / Your offer is not leading. Lower your offer to compete. | عرضك ليس العرض المتصدر. ارفع عرضك لتنافس. / Your offer is not leading. Raise your offer to compete. |
| `bafo.rule` | لا يمكن أن يكون عرضك النهائي أعلى من عرضك الأخير. / Your final offer cannot be higher than your last offer. | لا يمكن أن يكون عرضك النهائي أقل من عرضك الأخير. / Your final offer cannot be lower than your last offer. |
| `offers.error.start_price` | لا يمكن أن يتجاوز عرضك سعر السقف {amount}. / Your offer cannot exceed the ceiling price of {amount}. | لا يمكن أن يقل عرضك عن سعر الافتتاح {amount}. / Your offer cannot be below the opening price of {amount}. |
| `offers.error.step_not_met` | يجب ألا يزيد عرضك عن {amount}. / Your offer must be {amount} or lower. | يجب ألا يقل عرضك عن {amount}. / Your offer must be {amount} or higher. |
| `live.metric.improvement` | التوفير مقارنة بسعر السقف / Savings against the ceiling price | الزيادة مقارنة بسعر الافتتاح / Uplift against the opening price |

- **`errors.<code>` keys stay plain strings**, never objects with sub-keys: the generic mapper (CONVENTIONS §4.1) looks up `errors.<code>` and expects a string. `errors.offer_start_price` and `errors.offer_step_not_met` are therefore direction-neutral («العرض لا يستوفي سعر البداية.» / «العرض لا يستوفي الحد الأدنى للتحسين.»); the offer composer shows the direction-specific `offers.error.*` variants above, with the amount from `details`.
- **`offers.confirm.outlier.lower` / `.higher`** vary by the sign of the change, not by direction (an outlier can go either way in both modes): «هذا العرض أقل/أعلى من عرضك الحالي بنسبة {pct}.» / "This offer is {pct} lower/higher than your current offer."

Mode-neutral live keys: `live.status.leading`, `live.status.rank` («ترتيبك {rank} من {count}»), `live.status.hidden`, `live.leading_amount`, `live.extended` («مُدّد وقت الإغلاق {minutes} بسبب عرض في الدقائق الأخيرة»), `live.hint.server_timing`, `live.hint.slow_connection`, `live.connection.*`, `offers.sealed.received`, `bafo.invite`, `award.outcome.*`, `sponsorship.badge.fees_covered`, `billing.managed_on_web`, `sponsorship.managed_on_web`, `billing.invoices_on_web`.

---

## 6. Contract gaps and requests to other owners

### 6.1 Contract gaps (the contract is silent; the client choice is stated)

| # | Gap | Client choice | Optional contract addition (architect) |
|---|---|---|---|
| G1 | `GET /competitions?role=participant` has no filter on the invitation status or access state, so a server-side "Needs action" tab is not possible. | Tabs by `status_group`; cards needing action (`access.state` `join_required` / `plan_required`) sort first within the loaded page; Home shows `pending_invitations`. | A `needs_action=1` (or `invitation_status`) filter. |
| G2 | There is no list endpoint for export jobs. | W42 lists the jobs started in this browser session only. | `GET /integrations/exports` (paginated). |
| G3 | Drafts have no derived times (`final_window_starts_at`, `invitation_cutoff_at`, `hard_stop_at`) until publish (ARCHITECTURE §7.2). | The wizard computes a display-only preview with the §7.2 formulas and labels it «تقديري، يُثبَّت عند النشر». | A `schedule_preview` block in the draft projection. |
| G4 | PATCH in draft does not say whether absent `rules` keys keep their values when `preset_code` changes. | The client always sends the full `rules` object with `preset_code` (W15 step 1). | State the merge rule in API.md §1.4. |
| G5 | Subscription checkout has no pre-payment quote (upgrade credit and VAT are computed at payment creation). | W32 creates the `Payment` and shows its server amounts before redirecting. | None needed. |
| G6 | No app links or universal links in the MVP (no confirmed domain). | E-mail links (invitation, team invitation, verification) open on the web; mobile lists bound invitations. | App links once the domain exists (R2R3 §9.3). |

### 6.2 Requests to the Web owner (`apps/web`)

| # | Request |
|---|---|
| R-W1 | Move the auth pages to `/auth/*` (CD2) and point `middleware/auth.ts` at `/auth/login`; keep `/login` and `/register` as redirects. |
| R-W2 | Replace `config/navigation.ts` entries (`offers`, `subscription`, `settings`) with the §2.3 list (`my_competitions`, `participating`, `vendors`, `team`, `organization`, `billing`, `integrations`). |
| R-W3 | `types/ui.ts` `CompetitionStatus` and `UiStatusPill` use `open` and `closing_soon`, which are not API statuses. Drive the chip from `competitionStatusVisual(status, phase, …)` (S2) and render "Closing soon" and "Extended" as overlay pills. |
| R-W4 | The scaffold locale files use camelCase segments and namespaces outside CONVENTIONS §6.2 (`app`, `brand`, `dashboard`, `ui`, `status`, `theme`, `time`, `lang`, `footer`). Move kit strings to `common.*`, status labels to `competitions.status.*` and the dashboard home to `home.*`, with lower_snake_case segments. |
| R-W5 | `UiCountdown` ticks every second and formats "2d 14h 05m". Live pages need the 250 ms tick of S3 (repaint on a second change) and the CONVENTIONS §9.1 format "N days HH:MM". |
| R-W6 | Optional: self-host Inter and IBM Plex Sans Arabic instead of loading Google Fonts CSS at runtime (R2R3 D2), so the dashboard has no third-party font dependency. |

### 6.3 Requests to the Mobile owner (`apps/mobile`)

| # | Request |
|---|---|
| R-M1 | Rename the tab label `navCompetitions` «المنافسات» to «مشاركاتي» / "Participating", so it cannot be confused with «منافساتي». |
| R-M2 | Add the `/competitions/:id` subtree on the root navigator and `/billing` as a root route (§3.1). |
| R-M3 | Call `DateFormat.useNativeDigitsByDefaultFor('ar', false)` at start-up and add the Western-digits test (S6). |

---

## 7. Traceability

### 7.1 Legacy screens → BAFO units (02_apk_ui_content §3.2)

| Legacy | BAFO web | BAFO mobile |
|---|---|---|
| Splash, intro (4 pages) | – | M01, M02 (3 slides, language first) |
| Login, register, OTP, forgot, reset | W04–W08 | M06–M12 |
| Main container (4 tabs), home, unsubscribed home | W10 (alerts replace the unsubscribed variant) | shell, M14 |
| Available bids, unsubscribed available bids | W23 (the paywall becomes `access.state`) | M16, M17, M20 |
| My bids, unsubscribed bids | W11 | M33 |
| Create bid, edit bid | W12, W15, W16 | M34–M37, M39 |
| Bid details (issuer and participant) | W13, W14 | M17, M21, M38 |
| Close bid (reason) | W14 cancel, W22 close without award | M44 |
| Invite sellers | W15 step 6, W17 | M40, M41 |
| Bid offers + offer logs sheet | W19 console, W20 | M46, M47 |
| Add offer, edit offer | W19 live room | M23–M27 |
| Comments | W18 | M31, M32 |
| Notifications | W29, bell menu | M49 |
| Profile, profile details, edit profile | W27, W28 | M51, M52, M55 |
| Plans, custom plan, payment web view, success/error sheets | W31–W33 | M58 (status only) |
| Branch users, edit branch user | W26 | M56, M57 |
| Language, terms, about, share app | W28, W02 | M59, M13 (share app and about video dropped) |

### 7.2 MVP scope coverage (mvp_reconciled §6.1)

| MVP item | Where |
|---|---|
| Registration with CR, VAT, national address | W05, M07–M09 |
| Branch users and seats | W26, M56–M57 |
| Create, edit, invite, suggestions, attachments incl. private | W12, W15–W17, M34–M42 |
| Q&A | W18, M31 |
| Offers and logs; close and extend | W19–W21, W14 dialogs; M23–M28, M46–M47 |
| Plans, custom seats, coupons, trial, pro-rata upgrade | W30–W35 |
| Notifications; home stats; account deletion | W29, W10, W28; M49, M14, M61 |
| R4: cover all or selected invitees at publish or invite more; fees covered badge; apps sell nothing | W15 step 7, W17, W33; M17, M21, §3.2 |
| R5: tender and auction; live and sealed; BAFO round; presets + full rules on the web; award with justification, close without award, revoke; result PDF | W12, W15, W19, W22; M23–M27, M46–M48 |
| R1 dashboard: API clients, keys, webhooks, delivery log, CSV/XLSX vendor import, exports | W36–W42, W25 |
| One-page AR/EN landing, legal pages, invitation fallback | W01–W03 |

### 7.3 Deferred (not designed here; ARCHITECTURE §18 and the MVP line cut)

Deletion-approval flow and restore; owner "log in as"; Google sign-in; share app and the about video; suspend, resume, disqualify, relaunch; winner acknowledgement and award-next; maker-checker award; award decision, extend and BAFO start on mobile; issuer sponsorship panel on mobile; credit ledger, bank transfer and on-account billing; issuer offers chart; participant PDF; local closing reminders on mobile; web push; iPad layouts; dark mode on mobile.
