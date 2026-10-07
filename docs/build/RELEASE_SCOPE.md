# BAFO release scope (binding spec for the first public release)

Status: **binding** for every implementer (API, web, mobile, admin). It sits beside `BRIEF.md`, `ARCHITECTURE.md`, `API.md`, `CONVENTIONS.md` and `SCREENS.md`. Where this file narrows or re-arranges something those files describe, **this file wins for release scope `core`**, and the contract additions in §9 are the only changes the architect needs to fold back into the other files. BRIEF.md still wins on glossary, stack and cross-cutting rules.

## 0. Direction and non-negotiables

The client's direction: the product already has many complex features. The first public release **focuses on the tender/auction core and makes its input forms excellent**. Everything that needs a long explanation is **hidden behind a release-scope flag** and kept for later releases.

| Rule | Meaning |
|---|---|
| **Nothing is deleted** | Every route, controller, Action, component, screen, migration, seed and test stays in the repository and keeps working in scope `full`. Hiding is done by flags only. |
| **One switch** | One admin-editable setting `platform.release_scope` ∈ `core` \| `full`, default `core`. Flipping it to `full` brings everything back without a deploy. |
| **Clients read flags, never the scope** | `GET /app-config` exposes `features.release_scope` for display and a derived boolean map `features.flags`. Web and mobile branch on `flags.<name>` only. No client contains `if (scope === 'core')`. One mobile exception: the screen details no server flag covers derive from `features.release_scope` in a single table (`MobileSurfaces`, §4.2). |
| **Enforcement lives at the HTTP edge** | Route middleware, FormRequests, Resources/projections and lookup queries consult the flags. Actions, jobs, listeners, notifications and realtime never do. That is what keeps existing records rendering. The ops panel reads only the scope, through one check (`AdminScope`, §11), to hide its own pages; the module Actions it calls stay ungated. |
| **Existing records always render** | A competition created while `full` (sealed, with a BAFO round, with covered fees) stays fully readable and playable when the scope is `core`. Only *creating or configuring* the hidden feature is blocked. |
| **Ops panel at its minimum in `core`** | Filament (`/admin`) shows only Dashboard, Organizations, Users, Competitions, Subscriptions and Settings in `core` (§11); every other page is hidden (not deleted) and comes back unchanged in `full`. The panel still calls module Actions directly; the Actions are never gated. |
| **Glossary and bilingual rule** | Unchanged (BRIEF.md). Every new string exists in `ar` and `en`. |

---

## 1. Flag model

### 1.1 The setting

| Item | Value |
|---|---|
| Key | `platform.release_scope` (ARCHITECTURE §15.3 catalogue, Platform-owned; registered in `config('bafo.platform.settings')` → `PlatformServiceProvider`) |
| Type | string enum `core` \| `full`; PHP enum `App\Support\Features\ReleaseScope` |
| Default | `core` (every environment, including `testing`; see §1.6 for the test rule) |
| Admin | Settings page (`ManageSettings` / `SettingsForm`): rendered as a **select** with the two values (`SettingsForm` maps a key whose default is a backed enum, or this key by name, to `Select::make`). Saved through `UpdateAppSetting`, audited as `setting.updated`. Any other value is rejected (`InvalidArgumentException` → Filament validation error). |
| CLI | `php artisan platform:release-scope {core\|full}` (Platform `Console/Commands/ReleaseScopeCommand`) sets the value through `UpdateAppSetting` with `Actor::forSystem()`, for scripts, e2e set-up and support. `php artisan platform:release-scope` with no argument prints the current scope and the derived flags. |
| Cache | `Settings` already caches 60 s and flushes on `set`; nothing else is cached per scope. `GET /app-config` is `no-store`. `GET /lookups` computes its `ETag` over the serialised body, so a scope change produces a new ETag by construction. |

### 1.2 The service

`App\Support\Features\FeatureFlags` (Platform kernel, `app/Support`):

```php
final class FeatureFlags
{
    public function scope(): ReleaseScope;                 // from Settings
    public function enabled(Feature $feature): bool;      // §1.3 derivation
    /** @return array<string, bool> keyed by Feature value, in catalogue order */
    public function all(): array;
}
```

`App\Support\Features\Feature` is a string-backed enum with exactly the 22 cases of §1.3, values in snake_case as listed. The derivation table is code in `FeatureFlags::enabled()`, one `match` arm per case; there is no other place where scope logic lives on the server.

### 1.3 Flag catalogue (binding values)

Derivation legend: `full` = `scope == full`; `sp` = setting `sponsorship.enabled` (ARCHITECTURE §15.3). The per-organization flags (`organization.features.*`) stay separate and are still required where SCREENS S10 says so.

| Flag | core | full | Derivation | Applies to | What it covers | Rationale for hiding in core |
|---|---|---|---|---|---|---|
| `team_management` | false | true | `full` | api, web, mobile | W26 Team, M56–M57, `/team/members*`, seats meter on W26 | Roles, award/purchase flags and seat limits need explanation; a single-owner organization issues and bids without them. |
| `vendor_directory` | false | true | `full` | api, web, mobile | W25 Vendors, `/vendors*`, the Vendors tab of the invite picker (web and M40), `vendor_id` on invitations | Invitations by e-mail and suggestions cover the first release; the directory, blocking and external refs are ERP-era concepts. |
| `integrations_api` | false | true | `full` | api, web | W36–W40, `/integrations/api-clients*`, `/integrations/webhook-*`, deliveries, the whole public API `/api/public/v1/*`, the landing ERP section, `/docs/api` links | OAuth clients, scopes and webhooks are for ERP teams, not for the first self-serve users. |
| `csv_import_export` | false | true | `full` | api, web | W41 Import, W42 Exports, `/integrations/imports*`, `/integrations/exports*`, the Export buttons on W20 and W22, the export link on W25 | Needs the vendor directory and job polling UX. |
| `sponsorship` | false | true | `full && sp` | api, web, mobile | Issuer-paid participation fees (R4): wizard fees panel, W17 sponsorship card and "Pay and send", sponsorship checkout and quote changes, `sponsored` on invitations, vouchers list, landing "sponsored participation" section, FAQ item | The most explanation-heavy flow (passes, caps, vouchers, two checkout intents). `features.sponsorship` (existing key) is kept and now equals this flag. |
| `bafo_round` | false | true | `full` | api, web, mobile | The BAFO switch in the type/rules steps, `rules.bafo_round.enabled = true`, `POST …/bafo-round`, `permissions.can_start_bafo`, the shortlist dialog, preset chips | A second round after closing is an advanced procurement procedure. The brand name stays; the round comes back in a later release. |
| `sealed_format` | false | true | `full` | api, web, mobile | The format choice (live vs sealed), `format: sealed`, sealed presets in `GET /lookups`, the sealed explainer on the landing page | Core is the live-timed format only. Existing sealed competitions still render and accept offers. |
| `advanced_rules` | false | true | `full` | api, web | In the rules step: reserve price (target / reserve), amount granularity (halalas), result publication, minimum participants, untiered presets. Also the organization page rows for API and sponsorship features. | Each needs a paragraph to understand. The preset tiers carry sensible values for all of them. |
| `final_pricing_window` | false | true | `full` | api, web, mobile | The final window switch and minutes, `final_window_minutes != null`, the `initial` phase copy in the live room (kept for existing records) | Two-phase visibility is hard to explain next to auto-extend. |
| `deletion_approval` | false | false | reserved | – | Legacy deletion-approval flow (ARCHITECTURE §18) | Not built. The key exists so clients can gate it later. Draft soft-delete («حذف المسودة») is **core** and is not this flag. |
| `deleted_competitions` | false | false | reserved | – | Legacy "deleted" list, restore and force delete (§18) | Not built. |
| `offer_report` | false | false | reserved | – | Legacy per-offer evaluation report (findings §4 row 7); replaced by award + result PDF | Not built; the result PDF (`GET …/report`) is **core**. |
| `login_as` | false | false | reserved | – | Owner "log in as" a branch user (§18) | Not built; security-sensitive. |
| `google_signin` | false | false | reserved | – | Google sign-in (§18) | Not built. |
| `dark_mode` | false | true | `full` | web | `AppThemeMenu`, the `bafo_theme` cookie, `data-theme` | CD11 calls it optional polish; one theme makes QA and screenshots consistent. Mobile is light-only already. |
| `billing_invoices` | false | true | `full` | web, mobile | W34–W35, the invoices link on W30, mobile `InvoicesScreen` and its Account-hub row, the `/billing/invoices/{id}` deep link (maps to W30 / `/billing`) | Invoice pages need e-invoice status vocabulary. The invoice GET endpoints and the PDF stay reachable (legal records, §1.5). |
| `custom_plan_quote` | false | true | `full` | api, web | The custom plan card and seats input on W31, `GET /plans/custom-quote`, the custom plan in `GET /plans`, checkout of an `is_custom` plan | Seat-based quoting is a sales conversation in the first release. |
| `coupons` | false | true | `full` | api, web | `CouponField` on W32 and in the fees panel, `POST /billing/coupons/validate`, `coupon_code` on both checkouts | No campaigns at launch. |
| `qa_comments` | **true** | true | `true` | api, web, mobile | W18, M31–M32, `/competitions/{id}/comments` | Core. Questions and announcements are part of a fair competition. |
| `attachments` | **true** | true | `true` | api, web, mobile | W15 documents, W14 attachment list, M22, M42, `/competitions/{id}/attachments` | Core. Specifications and terms travel with the competition. |
| `extend_competition` | false | true | `full` | api, web | `ExtendDialog`, `POST …/extend`, `permissions.can_extend` | Manual extension needs a reason and an understanding of `min_new_close_at`; the auto-extend preset covers the need. Admin extension is unaffected. |
| `cancel_competition` | **true** | true | `true` | api, web, mobile | `CancelDialog`, M44, `POST …/cancel` | Core. An issuer must be able to stop a mistaken competition (CD12 keeps it on mobile). |

Both `true`/`true` flags and reserved `false`/`false` flags are still emitted in `features.flags`, so client code has one lookup path for every feature.

### 1.4 `GET /app-config` additions (API.md §2.13)

```json
"features": {
  "release_scope": "core",
  "sponsorship": false,
  "flags": {
    "team_management": false, "vendor_directory": false, "integrations_api": false, "sponsorship": false,
    "bafo_round": false, "sealed_format": false, "advanced_rules": false, "final_pricing_window": false,
    "deletion_approval": false, "deleted_competitions": false, "offer_report": false, "login_as": false,
    "google_signin": false, "dark_mode": false, "csv_import_export": false, "billing_invoices": false,
    "custom_plan_quote": false, "coupons": false, "qa_comments": true, "attachments": true,
    "extend_competition": false, "cancel_competition": true
  }
}
```

- `features.sponsorship` is kept for compatibility and **always equals `features.flags.sponsorship`**.
- `features.flags` always contains all 22 keys, in the order of §1.3. Unknown keys sent by a newer server are ignored by clients; missing keys are read as `false`.
- Types: web `FeatureFlag` union and `AppConfig.features.flags: Record<FeatureFlag, boolean>` in `app/types/api/platform.ts`; mobile `enum Feature` and `AppConfig.flags` (`Map<Feature, bool>`) in `core/config/app_config.dart`.

### 1.5 Server behaviour per flag when it is **off**

New generic error code (CONVENTIONS §8.2, Platform `lang/*/errors.php`):

| Code | HTTP | Meaning | Details |
|---|---|---|---|
| `feature_disabled` | 404 | The feature is not available in this release scope | `details.feature = "<flag>"` |

AR «هذه الميزة غير متاحة في هذا الإصدار.» / EN "This feature is not available in this release." Field-level refusals use 422 `validation_failed` with the shared message `errors.feature_disabled_field` («هذا الخيار غير متاح في هذا الإصدار.» / "This option is not available in this release.") on the offending field path.

Mechanics:

- Route middleware alias `feature:<flag>` (`App\Support\Http\Middleware\EnsureFeatureEnabled`, Platform) throws `ApiException('feature_disabled', 404, details: ['feature' => $flag])`. Modules attach it in their `routes/app_v1/*.php` and `routes/public_v1/*.php` groups.
- Field-level rules live in the module FormRequests (`withValidator`), reading `FeatureFlags`.
- Projection changes live in `CompetitionPermissions` and the Catalog lookup query.
- Nothing inside `Actions/`, `Jobs/`, `Listeners/`, broadcasting or notifications reads the flags.

| Flag | 404 `feature_disabled` (app v1 unless stated) | 422 field refusal | Read-only kept (existing records) | Projection / list changes |
|---|---|---|---|---|
| `team_management` | `GET/POST /team/members`, `PATCH/DELETE /team/members/{m}`, `POST …/resend-invitation` | – | `POST /auth/team-invitations/{lookup,accept}` (a member invited while `full` can still accept); `Home.team` seats | – |
| `vendor_directory` | `/vendors*` (all verbs) | `invitations.*.vendor_id` present → 422 on that path (also for `sponsorship/checkout` rows) | Invitations that reference a vendor still render `vendor` in W17/M41 | Suggestions unchanged |
| `integrations_api` | `/integrations/api-clients*`, `/integrations/webhook-event-types`, `/integrations/webhook-endpoints*`, `/integrations/webhook-deliveries/*`; **every** `/api/public/v1/*` route including the OAuth token endpoint | – | Webhook deliveries already queued keep being delivered (background, unaffected) | – |
| `csv_import_export` | `/integrations/imports*`, `/integrations/imports/templates/*`, `/integrations/exports*` | – | A completed export's `file` stays downloadable through `/files/{file}/download` | – |
| `sponsorship` | `POST …/sponsorship/checkout`; `PUT …/sponsorship` with `mode != none` | `invitations.*.sponsored = true` → 422 on that path (invitations store and checkout rows) | `GET …/sponsorship`, `GET …/sponsorship/quote`, `GET /billing/vouchers`, the sponsored lines on W03/W14/M17 for existing passes, W17/M41 counters | `permissions.can_manage_sponsorship = false` |
| `bafo_round` | `POST …/bafo-round` | `rules.bafo_round.enabled = true` on `POST/PATCH /competitions` → 422 on `rules.bafo_round.enabled` | A competition in `bafo_round` plays to the end (offers, cutoff, award) | `permissions.can_start_bafo = false`; presets with `bafo_round.enabled` omitted from `GET /lookups` |
| `sealed_format` | – | `format = sealed` on `POST/PATCH /competitions` → 422 on `format` | Sealed competitions render and accept offers; unlock at close works | Presets with `format = sealed` omitted from `GET /lookups` |
| `advanced_rules` | – | On `POST/PATCH /competitions`: `rules.reserve_price_minor != null`, `rules.amount_granularity_minor = 1`, `rules.result_publication != preset value`, `rules.min_participants != preset value` → 422 on that path. (A `preset_code` is required in core; its values fill these keys.) | Existing competitions keep their values; the award screen still shows the reserve confirmation when `reserve_met = false` | Untiered presets (tier `null`, §2.2) omitted from `GET /lookups` |
| `final_pricing_window` | – | `rules.final_window_minutes != null` → 422 on that path | Existing competitions keep their window and the `initial` phase | Presets with a final window omitted from `GET /lookups` |
| `extend_competition` | `POST …/extend` | – | Past manual extensions stay in the timeline | `permissions.can_extend = false` |
| `custom_plan_quote` | `GET /plans/custom-quote`; `POST /billing/checkout/subscription` with an `is_custom` plan | – | An active custom subscription still shows on W30/M58 | `GET /plans` omits `is_custom` plans |
| `coupons` | `POST /billing/coupons/validate`; either checkout with a non-null `coupon_code` | – | Past discounts stay on invoices and payments | – |
| `billing_invoices` | – (no server gating: invoices are legal records) | – | `GET /billing/invoices*` and `…/pdf` stay reachable | – |
| `dark_mode` | – (web only) | – | – | – |
| reserved flags | – (no endpoints exist) | – | – | – |
| `qa_comments`, `attachments`, `cancel_competition` | never (true in both scopes); the middleware is still attached so a later release can flip them | | | |

Admin: the module Actions the panel calls ignore the flags entirely. The panel itself follows the scope (§11): in `core` the competition view offers Cancel and Force close; Extend and Void come back in `full`.

### 1.6 Tests for the flag behaviour

| Where | Test | Owner |
|---|---|---|
| `tests/TestCase.php` | `setUp()` sets `platform.release_scope = full` through `Settings` so the existing 2 239 tests run against the full product. This is the one place where tests set the scope implicitly. | Platform |
| `tests/Feature/Platform/ReleaseScopeTest.php` | `GET /app-config` exposes `features.release_scope` and all 22 `features.flags` for both scopes with the exact §1.3 values; `features.sponsorship` equals `flags.sponsorship` and follows `sponsorship.enabled`; the `feature:` middleware answers 404 `feature_disabled` with `details.feature`; the artisan command sets and prints the scope; `UpdateAppSetting` rejects an unknown value. | Platform |
| `tests/Feature/<Module>/ReleaseScopeTest.php` | Dataset-driven: for every gated route of the module, scope `core` → 404 `feature_disabled`; scope `full` → not 404. Field refusals: each 422 of §1.5 with the exact path. Read-only exceptions: each "kept" GET answers 200 in `core` on a record created in `full`. Projection: `permissions.can_extend / can_start_bafo / can_manage_sponsorship` false in `core`; `GET /lookups` omits the right presets and `GET /plans` omits the custom plan. | Each module |
| `tests/Feature/Admin/ReleaseScopeTest.php` | Settings page shows the select and saves it; cancel works in `core`, extend is hidden in `core` and works in `full`. | Admin |
| `tests/Feature/Admin/OpsPanelScopeTest.php` | §11: the core sidebar (exact groups and items, super admin and operator), every hidden page and record page answers 403 in `core` and 200 in `full`, global search, the four dashboard counters, the competition / organization / user / subscription / settings details of §11.3. | Admin |
| Web `tests/unit`, `tests/nuxt` | `useFeature` and the app-config fixture (`tests/fixtures/api.ts` gains `flags`, default **full**); nav filtering by `feature`; wizard `wizardStepKeys`, legacy step aliases, `firstIncompleteStep` with 5 steps; gated blocks of `StepRules` absent in core and present in full; quick-pick maths; theme forced light in core; landing SEO head (canonical, hreflang incl. `x-default`, JSON-LD types); `sitemap.xml` and `robots.txt` routes. | Web |
| Mobile `test/` | `AppConfig.fromJson` parses `flags` (missing → false) and derives the mobile surfaces; every hidden item of §4.1 is absent in core and present in full (list in §4.3); invite screen hides the Vendors segment; `/billing/invoices/{id}` deep link maps to `/billing` in core; `app_test.dart` still shows 5 tabs in both scopes. | Mobile |

---

## 2. Simplified creation wizard (core and full)

### 2.1 Shape

The wizard has **5 steps in both scopes**. Scope `full` does not restore the 8-step layout; it shows the gated controls inside these steps. CD3 changes accordingly: **step 1 runs client-side and creates the draft** (`POST /competitions`), steps 2–5 edit the draft with `PATCH` or their own endpoints. Routes stay `/dashboard/competitions/new` (step 1 of a new draft) and `/dashboard/competitions/{id}/setup/{step}`.

| # | Step key (`{step}`) | Title AR / EN | Content | API on Continue | Flags that add controls |
|---|---|---|---|---|---|
| 1 | `basics` | النوع والأساسيات / Type and basics | **Direction** as two plain-language cards (tender: «أنت المشتري، الأقل سعراً يفوز»; auction: «أنت البائع، الأعلى سعراً يفوز»; auction gated by `organization.features.auction_enabled`, S10). Then title, category (auction filter, "Other" text), region, description (helper text with an example). | New draft: `POST /competitions {title, description, category_id, category_other_text, region_id, direction, format: "live", preset_code: <direction standard tier>, rules: preset.rules}` → replace the URL with `…/setup/rules`. Existing draft: `PATCH` (direction change asks «ستُعاد القواعد إلى الإعداد المسبق المختار.»). | `sealed_format` → the format cards (live / sealed) appear under the direction cards; `bafo_round` → nothing here (it moves to step 2's disclosure). |
| 2 | `rules` | القواعد / Rules | **Three preset tier cards** (§2.2) with one human sentence each; **start price** and **minimum step** (none / amount / percent) fields; disclosure **«إعدادات متقدمة»** (collapsed by default, state remembered per draft in `sessionStorage`) with must-beat, auto-extend (switch + window, by, max; "latest possible close" preview), rank visibility, show prices. The side «ملخص القواعد» (RulesAside) stays. | `PATCH {preset_code, rules}` (full `rules` object, G4) | `advanced_rules` → reserve price, granularity, result publication, minimum participants, «قوالب أخرى» list of untiered presets; `final_pricing_window` → the final window switch; `bafo_round` → the BAFO switch and duration; `sealed_format` → the sealed note and disabled live-only controls. |
| 3 | `schedule` | الجدول الزمني / Schedule | Opens: «فور النشر» or a date-time. **Quick picks** for the duration: ساعة، 3 ساعات، يوم، 3 أيام، أسبوع، مخصص (§2.3). Close date-time (shown for «مخصص», read-only summary otherwise). Relative hint under the close («يُغلق بعد 3 أيام: الخميس 12 نوفمبر 2026، 4:00 م بتوقيت الرياض»). Live timeline (SchedulePreview, «تقديري، يُثبَّت عند النشر»). Self-explaining validation (§2.3). | `PATCH {bidding_opens_at, scheduled_close_at}` | none (the timeline shows final window / latest close only when the rules have them) |
| 4 | `participants` | المتنافسون والمستندات / Participants and documents | **Invite**: `InvitePicker` with Suggestions and E-mail tabs (e-mail chips with paste-many; optional name), staged rows → «إضافة {n} دعوات», current list with inline name edit and remove, counter against the minimum. **Documents** (optional, collapsed section «مستندات اختيارية»): `AttachmentManager` from `StepDocuments`. **Fees** panel (`StepFees`) at the end, only when `flags.sponsorship && organization.features.sponsorship_enabled`. | Invitations and attachments save through their own endpoints as the issuer works; Continue only navigates. | `vendor_directory` → the Vendors tab; `sponsorship` → the fees panel and the per-row «تغطية الرسوم» switch. |
| 5 | `review` | المراجعة والنشر / Review and publish | Five summary cards with Edit links (type and basics; rules = server `rules_summary`; schedule = `SchedulePreview`; participants and documents = count vs minimum, attachments count, fees summary when the flag is on); the pre-publish checklist; **Publish** or **Pay and publish** (sponsorship flag). Publish errors as SCREENS W15 step 8 with the new step mapping (§2.5). | `POST …/publish` or `POST …/sponsorship/checkout {intent: publish}` | `sponsorship` → fees card, Pay and publish |

Chrome: `CompetitionsWizardShell` unchanged (5 items in `UiStepper`; the ≥ 1280 px clipping noted in STATUS disappears with 5 steps). Back/Save/Continue semantics unchanged. The stepper's step n of 5 copy uses `competitions.setup.steps.*`.

Legacy step keys are **aliases**: `/setup/type` → `/setup/basics`, `/setup/documents` and `/setup/fees` → `/setup/participants` (`navigateTo(…, { replace: true })`). `competitions.setup.steps.{type,documents,fees}` keys stay in the locale files (used by nothing new, kept for the aliases' titles and for mobile parity).

### 2.2 Preset tiers (Catalog)

Presets gain a nullable `tier` (`simple` \| `standard` \| `protected`; migration in the Catalog range, Preset resource and the admin lookups form add the field). Six new reference presets, all `format = live`, `bafo_round.enabled = false`, `final_window_minutes = null`, `result_publication = outcome_only`, `amount_granularity_minor = 100`, `start_price_minor`/`reserve_price_minor` absent (they are prices, not rules):

| Code | Tier | `must_beat` | `min_step` | `rank_visibility` | `show_prices` | `auto_extend` | `min_participants` |
|---|---|---|---|---|---|---|---|
| `tender_live_simple`, `auction_live_simple` | simple | own | none | leading_flag | false | off | 1 |
| `tender_live_standard`, `auction_live_standard` | standard | own | 50 bps (0.5%) | leading_flag | false | on: 180 s / 180 s / 10 | 2 |
| `tender_live_protected`, `auction_live_protected` | protected | own | 100 bps (1%) | none | false | on: 300 s / 300 s / 20 | 2 |

Card copy is the preset's `name` and `description` from `GET /lookups` (server-owned, so the admin can edit it). Seeded text:

| Tier | Name AR / EN | Description AR (tender · auction variant where different) | Description EN |
|---|---|---|---|
| simple | بسيطة / Simple | يحسّن كل متنافس عرضه بحرّية ويعرف فقط إن كان متصدراً. تُغلق المنافسة في موعدها دون تمديد. | Each participant improves their own offer freely and only knows whether they are leading. The competition closes on time without extensions. |
| standard | قياسية / Standard | حد أدنى للتحسين 0.5%. إذا تغيّر العرض المتصدر في آخر 3 دقائق يُمدَّد الإغلاق 3 دقائق (حتى 10 مرات). يعرف المتنافس إن كان متصدراً فقط. | Minimum improvement 0.5%. If the leading offer changes in the last 3 minutes, closing extends by 3 minutes (up to 10 times). Participants only know whether they are leading. |
| protected | حماية قصوى / Maximum protection | لا يرى المتنافسون ترتيبهم ولا أسعار غيرهم. حد أدنى للتحسين 1%، وتمديد 5 دقائق عند أي تغيير في العرض المتصدر خلال آخر 5 دقائق (حتى 20 مرة). | Participants see neither their rank nor other prices. Minimum improvement 1%, and closing extends by 5 minutes whenever the leading offer changes in the last 5 minutes (up to 20 times). |

Rules of the tier cards:

- The cards show tiered presets for the current direction and format, ordered simple → standard → protected. Default on a new draft: **standard**.
- Choosing a card sends the full preset `rules` (prices kept). Editing a control afterwards keeps the card selected and shows the existing «مخصّص» badge; «إعادة الضبط إلى {tier}» restores the preset rules.
- The existing presets `standard_live_tender`, `sealed_rfq`, `surplus_sale_auction` keep `tier = null` and stay active. They appear only when `advanced_rules` is on, under «قوالب أخرى» below the tier cards (a `UiSelect`, not cards). Demo competitions referencing them are untouched.
- `editorRulesCustomised` and `RulesAside` work unchanged.

### 2.3 Schedule quick picks and self-explaining validation

| Pick | Duration | Close = |
|---|---|---|
| ساعة / 1 hour | 60 min | `opens + 60 min` |
| 3 ساعات / 3 hours | 180 min | `opens + 3 h` |
| يوم / 1 day | 24 h | `opens + 24 h` |
| 3 أيام / 3 days | 72 h | `opens + 72 h` |
| أسبوع / 1 week | 7 d | `opens + 7 d` |
| مخصص / Custom | – | the date-time picker |

- `opens` = `bidding_opens_at`, or the server time rounded **up** to the next 5 minutes when «فور النشر» is selected; the hint says «يُحتسب من لحظة النشر». Changing `opens` while a pick is selected recomputes the close.
- A pick is shown selected while `close − opens` equals its duration within 60 s; otherwise «مخصص» is selected and the picker is visible.
- Pure helpers in `stores/competition-editor-schedule.ts`: `quickPickCloseAt(opensIso | null, pick, nowMs)`, `activeQuickPick(opensIso, closeIso, nowMs)`. Vitest-covered.
- Validation messages (existing `competitions.setup.schedule.issues.*`, extended) state the value and the bound: «المدة 4 دقائق فقط؛ الحد الأدنى 10 دقائق.» / "The duration is only 4 minutes; the minimum is 10 minutes." · «المدة 95 يوماً؛ الحد الأقصى 90 يوماً.» · «موعد الفتح مضى؛ اختر وقتاً لاحقاً أو «فور النشر».» · «الإغلاق قبل الفتح.» Bounds come from `competitions.min_duration_minutes` / `max_duration_days` as mirrored today in the editor store (the server re-checks).

### 2.4 Component mapping (nothing removed)

| Existing component | New role |
|---|---|
| `wizard/Shell.vue` | unchanged; receives 5 steps |
| `wizard/StepType.vue` | reused inside step 1. New props: `showFormat` (= `flags.sealed_format`), `showBafo` (= false here; the switch moves to StepRules), `showPresets` (= false; presets move to step 2). Defaults keep today's behaviour so the component still renders fully when all props are true. |
| `wizard/StepBasics.vue` | reused inside step 1, after StepType |
| new `wizard/StepTypeBasics.vue` | composes StepType + StepBasics; owns the direction-change confirmation on existing drafts |
| new `wizard/PresetTierCards.vue` | the three tier cards on `ChoiceCards`, plus the «قوالب أخرى» select when `advanced_rules` |
| `wizard/StepRules.vue` | step 2: PresetTierCards on top; prices card shows start price always, reserve/granularity only with `advanced_rules`; the disclosure is collapsed by default; result publication and min participants only with `advanced_rules`; final window only with `final_pricing_window`; BAFO block only with `bafo_round`; sealed handling only reachable when `sealed_format` |
| `wizard/RulesAside.vue`, `NumberField.vue`, `ChoiceCards.vue` | unchanged |
| new `wizard/DurationQuickPicks.vue` | the chips of §2.3 (`UiSegmented`-like, wraps on narrow screens) |
| `wizard/StepSchedule.vue` | step 3: quick picks above the pickers; relative hint; unchanged preview |
| `wizard/SchedulePreview.vue` | unchanged |
| `wizard/StepParticipants.vue` | reused inside step 4 (top); `InvitePicker` hides the Vendors tab when `!flags.vendor_directory` and the sponsored switch when `!flags.sponsorship` |
| `wizard/StepDocuments.vue` | reused inside step 4 as the collapsed «مستندات اختيارية» section |
| `wizard/StepFees.vue` | reused inside step 4 as the fees panel when the flag and the org feature are on |
| new `wizard/StepParticipantsDocs.vue` | composes the three above |
| `wizard/StepReview.vue` | step 5: five cards, `stepPath()` uses the new keys |
| `stores/competition-editor-steps.ts` | `WizardStepKey = 'basics' \| 'rules' \| 'schedule' \| 'participants' \| 'review'`; `LEGACY_STEP_ALIASES`; `wizardStepKeys()` returns the 5 keys; `WIZARD_CLIENT_STEPS = ['basics']`; `publishFieldStep`: `title/description/category_*/region_id/direction/format/preset_code` → `basics`, `start_price_minor/reserve_price_minor/rules.*` → `rules`, `bidding_opens_at/scheduled_close_at` → `schedule`, `invitations*` → `participants` |
| `pages/dashboard/competitions/new.vue` | one client step, then `editor.create()` |
| `pages/dashboard/competitions/[id]/setup/[step].vue` | resolves aliases, renders the 5 steps; `showAside` for `rules` and `schedule` |

### 2.5 Draft resume rule (replaces SCREENS §2.5 F2)

First of: basics missing (title, category, region) → `basics`; auction without a start price → `rules`; no close time → `schedule`; invitations < `rules.min_participants` → `participants`; fees flag on, mode ≠ none and quote `passes_to_buy > 0` → `participants`; otherwise `review`. `firstIncompleteStep()` encodes it; `draftChecklist()` keeps its items with the new step keys.

### 2.6 Mobile wizard (M34–M37)

Stays 4 screens (CD4): type (direction cards; format cards only with `sealed_format`; **tier cards** instead of the preset list, same source), basics, prices and schedule (start price, quick picks of §2.3, Riyadh pickers; reserve only with `advanced_rules`), review. «الإعدادات المتقدمة متاحة في لوحة التحكم على الويب.» stays.

---

## 3. Form quality checklist (binding for every web and mobile form)

Applies to: the wizard, register, organization, account, offer composer, award, invite picker, contact form, admin-facing forms excluded.

| # | Rule | Web | Mobile | Verify |
|---|---|---|---|---|
| FQ1 | **Label + helper + example.** Every field has a visible label, one line of helper text, and an example value where the format is not obvious (CR «مثال: 1010123456», price «مثال: 125,000.00», e-mail list «مثال: a@co.sa, b@co.sa»). Placeholders never replace labels. | `UiInput`/`UiTextarea`/`UiSelect` `label` + `hint`; examples in `hint` | `BafoTextField` `labelText` + `helperText` | Visual review; a Vitest structure test asserts every wizard field has a label |
| FQ2 | **Inline validation on blur, Arabic-first messages.** Client rules mirror the API (S7) for feedback only; messages name the field and the fix («أدخل عنوان المنافسة (حتى 200 حرفاً).»). Validate on blur and on Continue; never while typing the first character. | `touched` sets as in StepBasics | `autovalidateMode: onUserInteraction` + blur | Unit tests of the issue lists |
| FQ3 | **Money fields**: thousands separators while not focused, 2 decimals, Western digits, `ر.س` / `SAR` on the locale's side, `inputmode="decimal"`, Arabic-Indic digits normalised, VAT note where S6 requires it. | `UiMoneyInput` (exists) | `MoneyInputField` | Existing money tests; add the grouping-on-blur case |
| FQ4 | **Dates in Riyadh time with relative hints.** Every date-time shows «بتوقيت الرياض» and a relative phrase («بعد 3 أيام», «منذ ساعتين»); inputs convert with `zonedInputToUtcIso`. | `UiDateTimePicker` + `UiRelativeTime` | `BafoDateFormat` + relative helper | Formatting tests (S6) |
| FQ5 | **Keyboard types.** `inputmode`: numeric (OTP, CR, counts), decimal (money, percent), email, tel, url; `autocomplete` on identity fields; `dir="ltr"` on e-mail, URL, numbers. | per field | `TextInputType.*`, `AutofillHints.*` | Structure test |
| FQ6 | **Autosave of the draft.** Wizard steps 1–3 save on Continue (as today) **and** auto-save 1.5 s after the last change when the section has no blocking issues; the footer shows «تم الحفظ {time}» / «حفظ تلقائي…». Steps 4–5 already save per action. Register and offer forms do not autosave. | `useDebounceFn` in `[step].vue`, `editor.save(section)` | `CreateCompetitionCubit` keeps the local draft in `PreferencesStore` until `POST` | Nuxt test with fake timers |
| FQ7 | **Disabled-with-reason buttons.** A disabled primary action always carries the reason as visible text or a tooltip («أضف دعوتين على الأقل», «تحتاج إلى باقة فعّالة»). Never a silent disabled button. | `UiButton` + `UiTooltip`/caption | `BafoButton` + caption | Review |
| FQ8 | **Error summary at submit.** On a failed submit, an alert at the top lists every field error as a link that focuses the field (`#id`), plus unmatched server errors. | `UiAlert` with anchors (publish errors already do this) | `ErrorSummary` widget + `Scrollable.ensureVisible` | Nuxt test |
| FQ9 | **Focus management.** On step change focus the step heading; on error focus the summary; dialogs trap and restore focus; the skip link stays. | `Shell.vue` heading `tabindex="-1"` | `FocusNode` on the step title | Playwright smoke |
| FQ10 | **Targets ≥ 44 px** (48 dp Android). Chips, radios, segmented controls and icon buttons included. | kit tokens | `minimumSize` | Lighthouse accessibility ≥ 95 |
| FQ11 | **RTL-correct icons.** Directional icons flip (`flip-icons`), progress and steppers run start → end, numbers stay LTR islands (`<bdi>`), e-mail chips LTR. | `flip-icons` prop, logical properties only | `Directionality`, `matchTextDirection` | RTL screenshot spec |
| FQ12 | **Counters and limits** visible on bounded text fields («120 / 200»). | `maxlength` + counter | `maxLength` + counter | Review |
| FQ13 | **No destructive action without a named confirmation** (S7). | `UiConfirmDialog` | `BafoConfirmSheet` | Existing tests |

---

## 4. Mobile core tabs and hidden surfaces

Five tabs stay in both scopes: Home `/home`, Participating `/competitions`, My competitions `/my-competitions`, Notifications `/notifications`, Account `/account`. Scope `core` is the **minimal app** of §4.1: every surface hidden there keeps its code, route, screen and tests, and scope `full` shows it exactly as before.

### 4.1 Minimal core app: what is visible

| Area | core (visible) | full adds back |
|---|---|---|
| Signed out | Welcome / onboarding, sign-in, register (3 steps), OTP, forgot / reset password, legal pages (M13) | same |
| Home (M14) | Greeting and organisation name, alerts (no purchase action), participant tiles (pending invitations, active participations, offers in 30 days, awards won), issuer tiles (active, live now, awaiting award, drafts) **only when the organisation can issue (`can_issue` + `competitions.create`) or has issued before**, quick actions | the 30-day offers-received tile, the plan card, recent activity, the billing-profile alert with its link to M55; issuer tiles also for a creator without a plan |
| Competitions (M16) | Active / Ended / All segments, the list with Join and Decline | title search, the direction chips |
| Competition detail (M17 / M21) | overview, schedule, rules summary, documents read-only and downloadable (`attachments`), Q&A (`qa_comments`), join / decline, live room, offer composer, my offers, result | same |
| My competitions (M33) | Active / Drafts / Ended segments, the list, New competition, the 4-screen wizard (§2.6) | title search |
| Issuer detail (M38) | header, countdown, checklist, Publish, Invite, leading offer, rows Live monitor, Participants, Q&A, Award (read-only M48), counts, schedule, documents read-only with «تُرفع المستندات وتُدار من لوحة تحكم بافو على الويب.»; action sheet: Publish, Edit, Invite, Cancel, Delete draft, and the award decisions as web-only rows («الترسية وإلغاؤها والإغلاق دون ترسية متاحة في لوحة التحكم على الويب.») | Offers log row (M47), Documents row and buttons (M42 upload), the «Manage» link on documents; Extend (`extend_competition`) and BAFO (`bafo_round`) web-only rows and the longer web-only notice; sponsorship counters (`sponsorship`) |
| Live monitor (M46) | countdown, online count, leader, metrics, ranking | the «Offers log» link on the ranking, the extend-on-web notice (`extend_competition`) |
| Invite (M40) / Participants (M41) | Suggestions, E-mail; rows, resend, revoke | + Vendors (`vendor_directory`), + sponsored rows and counters (`sponsorship`) |
| Notifications (M49) | the list, tap to open (marks read), mark read on an unread row, mark all read | the all / unread filter, swipe and menu delete, delete all |
| Account hub (M51) | user card → Profile; organisation name **read-only** (role and verified pills, no link); plan card (read-only, opens M58; no seats); «حسابي»: Profile, Password; «التطبيق»: Help, Legal documents (a sheet: terms, privacy, competition rules), Delete account; Sign out; version. No empty section is built. | the tappable organisation card, the «المنشأة» section (Organisation M55, Team M56 with `team_management`, Plan and subscription row, Invoices with `billing_invoices`), Settings M59 instead of the Legal row, seats on the plan card (`team_management`) |
| Profile (M52) | name, e-mail (read-only), mobile number, language, avatar (shown) | the photo upload |
| M58 plan status | plan, source, status, ends, days left, upcoming plan, `billing.managed_on_web`, invoices-on-web notice | + seats (`team_management`) |
| Theme | light only (mobile has no theme setting in either scope; `dark_mode` is web-only) | same |
| Deep links | `/billing/invoices/{id}` → `/billing`; `/integrations` → `/home` | `/billing/invoices/{id}` → invoices screen |
| Advanced rules editing | never on mobile (CD4) | never |

Hidden routes stay registered and land on `FeatureUnavailableScreen` («غير متاح في هذا الإصدار») in core: `/account/team*`, `/account/invoices` («الفواتير متاحة في لوحة تحكم بافو على الويب.»), `/account/organization` («تُدار بيانات المنشأة من لوحة تحكم بافو على الويب.»), `/account/settings`, `/competitions/{id}/offers`, `/competitions/{id}/attachments` (documents message above), and `/competitions/{id}/qa` if `qa_comments` is ever off. The push explainer (M50) is not offered after a join or publish in core (push is a no-op service today) and is not marked as shown, so a later `full` release still explains it once.

### 4.2 Mechanism

- Server flags first: `AppConfig.flags` on the cached config; `FeatureGate(feature, child, fallback)` and `context.flags.enabled(Feature.x)`. Rows, segments and actions are built conditionally, never greyed out.
- **Mobile surfaces (the one sanctioned exception to "clients never read the scope")**: screen details no server flag covers are `enum MobileSurface` in `core/config/app_config.dart` — `organizationManagement`, `appSettings`, `pushPrompts`, `listFilters`, `notificationCleanup`, `homeExtras`, `issuerOffersLog`, `documentUpload`, `profilePhoto`. `MobileSurfaces.forScope(features.release_scope)` is their single derivation table (all on in `full`, all off in `core`, an unknown or missing scope reads as `core`). Screens use `context.surfaces.enabled(MobileSurface.x)` / `SurfaceGate(surface, child, fallback)` and never compare the scope string. No config yet (first offline start) reads as the minimal app.
- Nothing calls the server differently: a hidden surface makes no request (e.g. no `GET /organization` behind the gated route, no offers-log fetch).

### 4.3 Verification (mobile)

| Command | Must hold |
|---|---|
| `flutter analyze` | 0 issues |
| `flutter test` | green; gating tests per hidden item in core **and** visible in full: `test/core/config/feature_flags_test.dart` and `feature_gate_test.dart` (surfaces derivation, `SurfaceGate`), `features/home/home_screen_test.dart`, `features/account/account_screens_test.dart` (hub, legal sheet, organisation / settings routes, profile photo, seats), `features/notifications/notifications_screen_test.dart` and `push_explainer_test.dart`, `features/participant/participant_screens_test.dart` (filters, Q&A and documents flags), `features/issuer/issuer_screens_test.dart` (search, documents, offers log, extend / BAFO, web-only notice, gated routes); `app_test.dart` keeps 5 tabs |
| `flutter build apk --debug` | builds |
| `flutter drive --driver=test_driver/integration_test.dart --target=integration_test/minimal_core_screenshots_test.dart -d emulator-5554` | the real app on the Pixel API 34 emulator against the captured API fixtures; screenshots in `apps/mobile/docs/screenshots/minimal/` (core: home as issuer and as participant, My competitions, Account hub top and end, Notifications, Competitions; full: home and hub for comparison) |

---

## 5. Web navigation and pages in core

`DashboardNavItem` gains `feature?: FeatureFlag`; `SidebarNav` filters by permission **and** flag.

| Nav item | core | Gate |
|---|---|---|
| overview, my_competitions, participating, organization, billing | shown | permission only |
| vendors | hidden | `vendor_directory` |
| team | hidden | `team_management` |
| integrations | hidden | `integrations_api \|\| csv_import_export` |

Direct URLs to a hidden page render `UiNotFoundState` (same wording as a 404, CD8 style; no redirect). Page meta `definePageMeta({ feature: 'team_management' })` + `middleware/feature.ts` set the state. Top bar: `AppThemeMenu` only with `dark_mode`; `useTheme().dataTheme` returns `'light'` when the flag is off. W27 organization: the API and sponsorship feature rows only with `advanced_rules`. W30: invoices link only with `billing_invoices`; trial and plans unchanged. W31: custom card only with `custom_plan_quote`. W32: `CouponField` only with `coupons`. W17: sponsorship card, Pay and send, sponsored switches only with `sponsorship`. W19/W22: Extend only with `extend_competition`; BAFO shortlist only with `bafo_round`; Export only with `csv_import_export`. Detail header `FormatChip` always renders (existing records).

---

## 6. Landing page (W01) and SEO

### 6.1 Sections (in order; `landing.*` namespace)

| # | Section | Content | Data | Flags |
|---|---|---|---|---|
| 1 | Hero | h1 «اطرح منافستك، واحصل على أفضل عرض نهائي» / "Run your competition, get the best and final offer"; one-paragraph value proposition; CTAs «أنشئ حساب منشأتك» → W05, «تسجيل الدخول» → W04; **live demo card**: a sample tender (title, issuer, countdown ticking from a server-fixed end, participants count, standing banner «عرضك هو العرض المتصدر», direction chip) — static content, no API | – | – |
| 2 | How it works | Tabs «لطارح المنافسة» / «للمتنافسين», **3 steps each**: issuer اطرح → ادعُ → رسِّ (create with a preset, invite by e-mail, award with the result PDF); participant استلم الدعوة → قدّم عرضك → اعرف النتيجة | – | – |
| 3 | Tender vs auction | Two cards: مناقصة (you buy, lowest wins, examples) · مزايدة (you sell, highest wins, examples); one line on the live-timed format | – | `sealed_format` adds the sealed line |
| 4 | Fairness and integrity | Four tiles: ساعة الخادم (offers timed on receipt by the server), منع القنص (auto-extend when the leading offer changes in the last minutes), العروض المغلقة (only with the flag), سجل التدقيق (every action logged; identities of participants hidden from each other) | – | `sealed_format` |
| 5 | Who it is for | Three audience cards: procurement teams buying goods and services; companies selling surplus and assets; suppliers invited to compete — each with one sentence and the glossary term | – | – |
| 6 | Pricing teaser | Up to 3 plan cards from `GET /plans` (SSR `useAsyncData`, 10 s timeout): name, seats, monthly/annual toggle, price excl. VAT with struck list price, 3 feature lines, CTA → W05; «الأسعار لا تشمل ضريبة القيمة المضافة»; **hidden entirely** on error or empty list | `GET /plans` | `custom_plan_quote` is server-side (custom plan omitted) |
| 7 | FAQ | Accordion (`<details>`/`<summary>`, one open at a time, keyboard-operable) with the items of §6.2; FAQPage JSON-LD built from the **rendered** items | – | `sponsorship`, `integrations_api` |
| 8 | Final CTA | «ابدأ منافستك الأولى اليوم» + register CTA + «أسئلة؟ تواصل معنا» → contact (existing `POST /contact` form stays, honeypot) | `AppConfig.support` (client) | – |
| 9 | Footer | Legal links W02: الشروط، الخصوصية، الاسترداد، قواعد المنافسات (+ شروط واجهة البرمجة with `integrations_api`); language switch; support contacts; «© بافو» without a hard-coded year | – | `integrations_api` |

Removed from the current landing in core (kept in code, flag-gated): the "sponsored participation" section (`sponsorship`), the "ERP integration" section (`integrations_api`).

### 6.2 FAQ items (AR / EN; keys `landing.faq.items.<key>.{question,answer}`)

Always shown (10):

| Key | Question AR | Answer AR | Question EN | Answer EN |
|---|---|---|---|---|
| `what` | ما هو بافو؟ | منصة سعودية تتيح للمنشآت طرح المناقصات والمزايدات بين الشركات، ودعوة المتنافسين، واستلام العروض ومقارنتها، ثم الترسية، بقواعد معلنة وتوقيت يحدده الخادم. | What is BAFO? | A Saudi platform where companies run tenders and auctions between businesses, invite participants, receive and compare offers, and award, under published rules and server-controlled timing. |
| `tender_auction` | ما الفرق بين المناقصة والمزايدة؟ | في المناقصة أنت المشتري ويفوز العرض الأقل سعراً. في المزايدة أنت البائع ويفوز العرض الأعلى سعراً. تختار الاتجاه عند إنشاء المنافسة. | What is the difference between a tender and an auction? | In a tender you are buying and the lowest offer wins. In an auction you are selling and the highest offer wins. You choose the direction when you create the competition. |
| `who_can_join` | من يستطيع المشاركة في منافستي؟ | المنشآت التي تدعوها فقط، بالبريد الإلكتروني أو من الاقتراحات. لا توجد منافسات عامة؛ كل منافسة بدعوة. | Who can take part in my competition? | Only the companies you invite, by e-mail or from suggestions. There are no public competitions; every competition is by invitation. |
| `identities` | هل يرى المتنافسون بعضهم؟ | لا. يظهر كل متنافس للآخرين برقم مستعار فقط، ولا تُعرض الأسعار إلا إذا سمحت قواعد المنافسة بذلك. | Can participants see each other? | No. Each participant appears to the others by an alias number only, and prices are shown only when the competition rules allow it. |
| `clock` | كيف يُحسب وقت الإغلاق؟ | يعتمد التوقيت على ساعة خادم بافو لا على جهاز المتنافس. يُسجَّل وقت كل عرض عند استلامه، ويُعرض العد التنازلي بتوقيت الرياض. | How is the closing time calculated? | Timing follows the BAFO server clock, not the participant's device. Each offer is timed when it is received, and countdowns are shown in Riyadh time. |
| `anti_sniping` | ماذا يحدث إذا وصل عرض في اللحظات الأخيرة؟ | إذا فعّلت التمديد التلقائي، يُمدَّد الإغلاق لدقائق محددة كلما تغيّر العرض المتصدر في آخر الدقائق، حتى عدد مرات تحدده أنت. | What happens if an offer arrives at the last second? | If auto-extend is on, closing extends by a set number of minutes whenever the leading offer changes in the final minutes, up to a limit you set. |
| `rules` | هل أحتاج إلى فهم كل القواعد؟ | لا. اختر أحد الإعدادات الثلاثة (بسيطة، قياسية، حماية قصوى) وسيشرح لك بافو أثره بجملة واحدة. يمكنك تعديل التفاصيل لاحقاً إن أردت. | Do I need to understand every rule? | No. Pick one of three presets (simple, standard, maximum protection) and BAFO explains its effect in one sentence. You can adjust the details later if you wish. |
| `award` | كيف تتم الترسية؟ | بعد الإغلاق تظهر لك النتائج النهائية، تختار العرض الفائز، وتوثّق المبرر إن لم يكن الأفضل، ثم تنزّل تقرير النتائج بصيغة PDF. | How does the award work? | After closing you see the final standings, choose the winning offer, record a justification if it is not the best, and download the result report as a PDF. |
| `plans` | ما تكلفة الاستخدام؟ | يحتاج طارح المنافسة إلى باقة فعّالة، مع فترة تجريبية مجانية للمنشآت الجديدة. المشاركة في المنافسات تتطلب حساب منشأة. الأسعار لا تشمل ضريبة القيمة المضافة. | How much does it cost? | Issuers need an active plan, with a free trial for new companies. Taking part in competitions requires a company account. Prices exclude VAT. |
| `mobile` | هل يتوفر تطبيق للجوال؟ | نعم، لنظامي iOS وأندرويد: متابعة المنافسات وتقديم العروض وإدارة الدعوات. تُدار الاشتراكات والإعدادات المتقدمة من لوحة التحكم على الويب. | Is there a mobile app? | Yes, for iOS and Android: follow competitions, submit offers and manage invitations. Subscriptions and advanced settings are managed from the web dashboard. |

Flag-gated (shown when the flag is on; `full` shows 12):

| Key | Flag | Question AR | Answer AR | Question EN | Answer EN |
|---|---|---|---|---|---|
| `sponsored` | `sponsorship` | هل يمكنني تغطية رسوم مشاركة الموردين؟ | نعم. يمكن لطارح المنافسة تغطية رسوم المشاركة لجميع المدعوين أو لبعضهم في منافسة محددة، ولا يرى المتنافسون من تُغطّى رسومه. | Can I cover my suppliers' participation fees? | Yes. An issuer can cover participation fees for all or some invitees in a specific competition, and participants cannot see whose fees are covered. |
| `erp` | `integrations_api` | هل يتكامل بافو مع أنظمة تخطيط الموارد؟ | نعم، عبر واجهة برمجة تطبيقات عامة وإشعارات Webhook لإنشاء المنافسات ومزامنة الترسية مع نظامكم. | Does BAFO integrate with ERP systems? | Yes, through a public API and webhooks to create competitions and sync awards with your system. |

### 6.3 SEO requirements

| Requirement | Implementation | Verify |
|---|---|---|
| Per-locale title and description | `landing.meta.title/description` (exist); `titleTemplate` «… · بافو» | view source for `/ar` and `/en` |
| Canonical + hreflang (`ar`, `en`, `x-default`) | `useLocaleHead({ seo: true })` in `app.vue` (exists) with `i18n.baseUrl` from the new runtime config `public.siteUrl` (`NUXT_PUBLIC_SITE_URL`, default `http://localhost:3000`); assert `x-default` → `/ar` | Nuxt test on the rendered head |
| OpenGraph / Twitter with a static OG image | `public/og/bafo-og-ar.png` and `bafo-og-en.png`, **1200×630**, brand charcoal background, colour mark, lockup, tagline; produced once by `apps/web/scripts/og-image.ts` (Playwright, already a dev dependency) from `scripts/og-template.html`; `og:image:width/height`, `twitter:card summary_large_image` | file dimensions in a unit test; `og:image` absolute URL |
| JSON-LD | `Organization`, `WebSite` (`url`, `name`, `inLanguage`), `SoftwareApplication`, `FAQPage` (rendered items only) as one `application/ld+json` array | parse the script in a Nuxt test; Google Rich Results test manually |
| `sitemap.xml` | Nitro route `server/routes/sitemap.xml.ts`: `/ar`, `/en`, and `/{locale}/legal/{code}` for the codes that `GET /legal/{code}` answers 200 (`Accept-Language` per locale, results cached 1 h with `defineCachedEventHandler`); on API failure the locale roots only; `<xhtml:link rel="alternate" hreflang>` per URL; `lastmod` from `published_at` for legal pages | `curl /sitemap.xml` is valid XML; test |
| `robots.txt` with Sitemap | Nitro route `server/routes/robots.txt.ts` (the static `public/robots.txt` is removed so it does not shadow the route): `Allow: /`, `Disallow: /ar/dashboard /en/dashboard /ar/auth /en/auth /ar/invitations /en/invitations`, `Sitemap: {siteUrl}/sitemap.xml` | `curl /robots.txt` |
| `noindex` on private pages | `robots: noindex, nofollow` meta from the `auth` and `dashboard` layouts and the invitation landing | view source |
| `lang` / `dir` | from `useLocaleHead` (exists) | – |
| Semantic headings | one `h1` (hero), `h2` per section, `h3` inside cards; landmark `<main id="main">`, `<nav>`, `<footer>`; skip link kept | axe in Playwright smoke |
| Lighthouse SEO ≥ 95 | run against the production build (`pnpm build && node .output/server/index.mjs`) for `/ar` and `/en`, desktop and mobile presets; record the four scores in STATUS.md | Lighthouse |
| LCP-friendly | no new runtime libraries (no carousel, no animation library, no charting); fonts already self-hosted; hero image/mark as inline SVG or a ≤ 40 KB WebP with explicit `width`/`height`; plans fetched server-side; `loading="lazy"` below the fold; `fetchpriority="high"` on the hero visual | Lighthouse performance ≥ 90 on desktop (target, not gate) |

---

## 7. i18n keys to add (both `ar.json`/`en.json`; ARB for mobile)

Owners append; nobody rewrites a shared file.

| Prefix | Owner | Contents |
|---|---|---|
| `errors.feature_disabled`, `errors.feature_disabled_field` | Web, Mobile (`errorsFeatureDisabled`) | §1.5 texts |
| `competitions.setup.steps.{basics,rules,schedule,participants,review}`, `competitions.setup.titles.*`, `competitions.setup.descriptions.*` | Web | §2.1 titles («النوع والأساسيات», «القواعد», «الجدول الزمني», «المتنافسون والمستندات», «المراجعة والنشر»); the old `type/documents/fees` keys stay |
| `rules.tiers.{legend,hint,other_presets,reset_to}` | Web | tier cards chrome (card text comes from the API) |
| `competitions.setup.schedule.quick.{hour,hours_3,day,days_3,week,custom,computed_from_publish}`, `competitions.setup.schedule.relative_close`, `competitions.setup.schedule.issues.*` additions | Web | §2.3 |
| `competitions.setup.participants.{documents_title,documents_optional,fees_title}` | Web | §2.1 step 4 |
| `common.autosave.{saving,saved_at}` | Web | FQ6 |
| `landing.hero.*`, `landing.how.*` (3 steps), `landing.modes.*`, `landing.fairness.*`, `landing.audience.*`, `landing.plans.*`, `landing.faq.items.*` (§6.2), `landing.cta.*`, `landing.footer.*` | Web | §6 |
| ARB `issuerPresetTier*`, `issuerScheduleQuick*`, `accountInvoicesOnWeb`, `errorsFeatureDisabled` | Mobile | §2.6, §4 |

---

## 8. Verification matrix (what "done" means per area)

| Area | Commands | Must hold |
|---|---|---|
| API | `scripts/test-api.sh scope` (serial and `--parallel`), `vendor/bin/phpstan analyse`, `vendor/bin/pint --test` | all green; every §1.5 row has a test; `GET /app-config` matches §1.4 in both scopes |
| Admin | included in the API suite (`OpsPanelScopeTest`) | settings select saves both values; the §11 sidebar in `core`, everything back in `full`; screenshots in `apps/api/docs/screenshots/ops/` |
| Web | `pnpm lint`, `pnpm typecheck`, `pnpm test`, `pnpm build`, `pnpm test:e2e smoke` | 5-step wizard with aliases; nav and pages gated; landing sections, FAQ count 10 (core) / 12 (full), head tags, sitemap and robots routes; Lighthouse SEO ≥ 95 recorded |
| Mobile | `flutter analyze`, `flutter test` | flags parsed; Account hub, invite segments and deep links gated; 5 tabs |
| Manual | admin → Settings → `platform.release_scope` = full → reload web and mobile | everything hidden in `core` is back, including the wizard's gated controls, nav items, landing sections and endpoints |

---

## 9. Contract additions for the architect (fold into the other files)

| File | Addition |
|---|---|
| CONVENTIONS §8.2 | `feature_disabled` · 404 · `details.feature` |
| API.md §2.13 | `features.release_scope`, `features.flags` (§1.4); `features.sponsorship` = `flags.sponsorship` |
| API.md §1.2 | `GET /lookups` presets filtered by scope (§1.5); Preset gains `tier` |
| API.md §1.7 | `GET /plans` omits custom plans when `custom_plan_quote` is off |
| ARCHITECTURE §15.3 | `platform.release_scope` (default `core`), Platform |
| ARCHITECTURE §4 | `App\Support\Features\{FeatureFlags, Feature, ReleaseScope}` and the `feature:` middleware |
| ARCHITECTURE §16 | Settings page renders the scope as a select; `platform:release-scope` command |
| SCREENS CD3, §2.4 W12/W15, §2.5 F2, §3.3 M34–M37 | the 5-step wizard, the draft created after step 1, the resume rule of §2.5, the tier cards |
| SCREENS §2.3 | nav `feature` gates (§5) |
| SCREENS §2.4 W01 | the section list and SEO table of §6 |

## 10. Known limitations accepted for `core`

- Invoices are reachable through the API and the admin, not through a page; the notification deep link lands on W30 (`billing_invoices`).
- A competition created in `full` with sealed format, a final window or a BAFO round shows controls the core wizard cannot produce; this is by design (existing records always render).
- Reserved flags (`deletion_approval`, `deleted_competitions`, `offer_report`, `login_as`, `google_signin`) are `false` in both scopes until those features ship; the derivation table is the only place to change when they do.

## 11. Ops panel (`/admin`) in core

The client's direction for the operations panel: **keep it at the minimum in `core`, hide only, never delete.** Every resource, page, action, relation manager, route and test stays; `php artisan platform:release-scope full` (or Settings → `platform.release_scope`) brings the whole panel back exactly as before, with no deploy.

### 11.1 Mechanism

| Item | Value |
|---|---|
| Catalogue | `App\Modules\Admin\Enums\OpsSurface`: one case per resource/page and per hidden detail inside a core page. `OpsSurface::inCore()` lists the six core cases; every other case is `full` only. |
| The one check | `App\Modules\Admin\Support\AdminScope::visible(OpsSurface $surface)` = `$surface->inCore() \|\| FeatureFlags::scope()->isFull()`. Nothing else in the panel reads the scope. |
| Resources | `AdminResource::$opsSurface` on all 21 resources; `AdminResource::canAccess()` = `AdminScope::visible(...)` **and** the existing panel policy. A hidden resource therefore leaves the sidebar and global search, and every one of its pages (list, create, view, edit) answers **403** on a direct URL. |
| Pages | `ManageSettings::canAccess()` = `AdminScope::visible(Settings)` and super admin (unchanged rule). Dashboard and the admin's own profile are always reachable. |
| Relation managers | `AdminRelationManager::$opsSurface` (null = always shown), checked in `canViewForRecord()`. |
| Actions, columns, entries | `->visible(fn () => AdminScope::visible(OpsSurface::…))` on the item. Read-only rows of a feature an existing record uses (BAFO round, final window, sponsorship, voided offers) still show in `core` on that record. |
| Navigation groups | Groups are the `AdminNavigationGroup` cases; Filament drops a group with no visible item, so `core` shows no empty group (an operator, who cannot open Settings, sees three groups). |
| Module Actions | Untouched: `ExtendCompetition`, `VoidOffer`, `GrantSponsoredPass`, … are only not offered in `core`. |
| Languages | Arabic (RTL) default and English; new keys `admin.dashboard.stats.competitions_this_month`, `admin.settings.core_hint` in both files. |

### 11.2 Sidebar

| Group | `core` | `full` (as before) |
|---|---|---|
| (none) | Dashboard | Dashboard |
| Customers · العملاء | Organizations, Users | + Account deletions |
| Competitions · المنافسات | Competitions | Competitions |
| Billing · الفوترة | Subscriptions | + Plans, Coupons, Vouchers, Payments, Invoices, Sponsorships |
| Integrations · التكامل | – (group hidden) | API clients, Webhook endpoints |
| Lookups · القوائم المرجعية | – (group hidden) | Regions, Categories, Close reasons, Presets |
| Content and settings · المحتوى والإعدادات | Settings (super admin) | + Legal documents, Contact inbox |
| System · النظام | – (group hidden) | Audit log, Admins |

There is no Horizon item in the panel (Horizon stays at `/horizon` behind the `viewHorizon` gate, outside Filament); nothing to hide there.

### 11.3 Inside the core pages

| Page | `core` | Added in `full` (`OpsSurface`) |
|---|---|---|
| Dashboard | Counters: organizations, live competitions, competitions this month (published since the 1st of the Riyadh month), active subscriptions; the live competitions table | Payments today, failed e-invoices, sponsorships awaiting a voucher (`DashboardBillingStats`) |
| Competitions | List with filters; view with summary, rules, timeline, outcome; relation managers Invitations, Participants, Offers, Awards; actions **Cancel** and **Force close** | Extend (`CompetitionExtend`); Void offer and the void columns (`CompetitionVoidOffer`); Revoke invitation (`CompetitionRevokeInvitation`); Grant pass, sponsored columns, sponsorship row (`CompetitionSponsorship`); Extensions and Rejections relation managers (`CompetitionExtensions`, `CompetitionRejections`); BAFO round row (`CompetitionBafoRound`); final window rows (`CompetitionFinalWindow`); award ERP sync column (`CompetitionErpSync`) |
| Organizations | List, view (profile, status, subscription, members, subscriptions, competitions); Verify / Unverify, Suspend / Reactivate, Resend verification, Grant subscription; Features with the **auction** switch only (saves only that switch) | API and sponsorship switches in the list, filters, view and Features form (`OrganizationAdvancedFeatures`) |
| Users | List, view, Deactivate / Reactivate (also on the organization's members) | Award / purchase permission columns (`TeamPermissions`) |
| Subscriptions | List with filters; Grant subscription (plan, seats, period, reason) | – |
| Settings | Groups «الإصدار» (release scope select) and «التطبيق» (maintenance switch and message, minimum / latest app versions, store links, support contacts), with a note that the rest appear in `full`. Hidden keys are neither rendered nor saved. | Competitions, bidding engine, billing and sponsorship groups (`AdvancedSettings`) |

The organization page has no edit form for its profile fields today (only the actions above); none was added. Categories are not editable from the organization page in either scope.

### 11.4 Verification

- `scripts/test-api.sh <suffix> tests/Feature/Admin` — `OpsPanelScopeTest` (core sidebar, 403 on every hidden page and record page, 200 again in `full`, dashboard, competition/organization/user/subscription/settings details) and the rest of the Admin suite, which runs in `full` (tests/TestCase.php).
- Screenshots of the sidebar on the running local panel in both scopes: `apps/api/docs/screenshots/ops/` (`core-*.png`, `full-*.png`), taken with Playwright after `php artisan platform:release-scope full`, then the scope set back to `core`.

