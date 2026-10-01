# BAFO build brief (source of truth for every implementer)

BAFO / بافو is a Saudi B2B **competition** platform. A company (the **issuer**, طارح المنافسة) runs a competition. Invited companies (**participants**, المتنافسون) submit offers. A competition runs in one of two directions:

- **Tender** (مناقصة, reverse): the lowest price wins.
- **Auction** (مزايدة, forward): the highest price wins.

BAFO replaces the offline product "Munaqes". We have no legacy code. The legacy behaviour is specified in `docs/01_findings/` (read `00_baseline_and_gaps.md` §4 first). The build scope is the **Launch MVP** in `docs/02_design/mvp_reconciled.md` §6.1 and in `docs/02_design/mvp_linecut.md`. The per-requirement designs, which cover data models, rules and flows, are:

| Design file | Covers |
|---|---|
| `docs/02_design/platform_baseline.md` | the core platform |
| `R1_erp_integration.md` | public API v1 |
| `R2R3_rebrand_content.md` | brand and copy |
| `R4_sponsored_participation.md` | issuer-paid participation fees |
| `R5_tender_auction_engine.md` | the engine |

When a design file's full scope exceeds the MVP, **implement the MVP level** stated in `mvp_reconciled.md` §6.1.

## Stack (fixed)
| Layer | Choice |
|---|---|
| API | **Laravel 13** (PHP 8.5 locally), PostgreSQL 17 (db `bafo`, test db `bafo_test`, socket host `/tmp`, user `hamzaalaydi`, no password), Redis (localhost:6379) for queue/cache, **Laravel Sanctum** personal access tokens for first-party clients (web + mobile), **Laravel Passport** *only* for the public API OAuth2 client-credentials grant (or a hand-rolled equivalent if Passport conflicts — document it), **Laravel Reverb** for realtime (private channels only), **Filament** (latest compatible) for the platform admin at `/admin`, Pest for tests. |
| Web | **Nuxt 4** (Vue 3, TypeScript), `@nuxtjs/i18n` (ar default + RTL, en LTR, `prefix_except_default` not required — use `prefix` strategy `/ar`, `/en`), **Tailwind CSS v4**, Pinia, `laravel-echo` + `pusher-js` for Reverb, Vitest + Playwright. SPA-style dashboard pages (ssr: false for `/dashboard/**` is fine), SSR for the landing page. |
| Mobile | **Flutter** (stable, Dart 3), Android + iOS, `flutter_bloc`, `go_router`, `dio`, gen-l10n (ARB, Arabic default), `flutter_secure_storage`, a Pusher-protocol client that supports a custom host for Reverb (e.g. `dart_pusher_channels`), `firebase_messaging` behind an interface with a no-op implementation (no Firebase project yet). |
| Fonts | IBM Plex Sans Arabic (Arabic + Latin fallback) and Inter for Latin UI where needed. Both are OFL. |
| Brand | tokens in `assets/brand/04_ui_color_tokens/bafo_colors.json`; logos and icons in `assets/brand/`; spec in `docs/01_findings/05_brand.md` |

## Repo layout
```
apps/api      Laravel 13 (serves on http://localhost:8000, Reverb ws on :8085)
apps/web      Nuxt 4 (http://localhost:3000)
apps/mobile   Flutter (Android emulator reaches the API at http://10.0.2.2:8000)
docs/build    ARCHITECTURE.md, API.md (contract), CONVENTIONS.md, STATUS.md
assets/brand  brand package
scripts/      dev.sh (start everything), seed/reset helpers
```

## Cross-cutting conventions
- **Language:** Arabic-first. Every user-facing string is in both AR and EN; there are no hard-coded strings in UI code. The API localises messages via `Accept-Language: ar|en` (default `ar`).
- **Glossary** (use exactly):
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

  Never use «مناقص» as a noun for people, and never use the old brand name.
- **Money:** integer halalas (SAR minor units) in the DB and API (`amount_minor`), with a currency field `SAR`. Display with the ر.س / SAR label. Prices exclude VAT; VAT is 15%.
- **Time:** UTC ISO-8601 in the API. **The server clock is authoritative** for bidding. Clients sync with the `server_time` field that every competition payload returns.
- **IDs:** ULIDs as public identifiers (`id` strings); bigint internal keys are allowed but not exposed.
- **API shape:** `/api/app/v1/...` for first-party clients (web + mobile) and `/api/public/v1/...` for the ERP public API (R1).
  - Responses: `{ "data": ..., "meta": {...} }`.
  - Errors: `{ "message": "...", "code": "snake_case_code", "errors": { field: [..] } }`.
  - Cursor or page pagination with `meta.pagination`.
- **Realtime:** private channel `competition.{id}` for issuer-side events; private per-participant channel `competition.{id}.participant.{orgId}`. Payloads must respect the visibility rules: never leak other participants' prices unless the competition allows it.
- **External services run as fake/sandbox drivers by default**, behind interfaces:
  - `PaymentGateway`: a `fake` driver that auto-succeeds via a local hosted-checkout page, plus a `moyasar` driver skeleton;
  - `EInvoicing` (ZATCA provider): `fake`;
  - `PushNotifier`: `log`;
  - `Mailer`: `log`;
  - `Sms`: none.

  No real keys ever go in the repo; `.env.example` carries placeholders only.
- **Security:**
  - Participants never see other participants' identities.
  - Prices are hidden unless `show_prices`.
  - Sealed offers are invisible until the close.
  - Authorisation goes through policies.
  - Public forms are rate-limited.
  - No tokens in URLs.
- **Demo data:** a seeder creates demo organisations, users, plans, competitions in every state, and the admin user. Credentials live in `apps/api/database/seeders/DemoSeeder.php` and `docs/build/DEMO.md`, never in chat.
- **Tests:** backend feature tests for every endpoint and for the engine rules (Pest), web unit tests for stores and composables, and Flutter widget/unit tests for blocs. Keep them passing.
- **Quality bar:** production-grade structure, strict typing (PHP `declare(strict_types=1)`, TS strict, Dart analysis with `flutter_lints`), and no dead scaffolding.
