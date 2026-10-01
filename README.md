# BAFO / بافو

BAFO is a Saudi B2B **competition** platform. A company (the **issuer**, طارح المنافسة) runs a
competition, and the companies it invites (the **participants**, المتنافسون) submit offers. A
competition is either a **tender** (مناقصة, the lowest price wins) or an **auction** (مزايدة, the
highest price wins), in a **live** or **sealed** format.

The platform covers:

- the full competition lifecycle: draft, publish, invitations, Q&A, live bidding, close, award and an AR/EN result PDF;
- an optional **BAFO round** (best and final offer), anti-sniping extensions, minimum steps, reserve or target prices, and rank-only or price visibility;
- subscriptions, trials, coupons, tax invoices, and **sponsored participation** (رسوم مغطّاة): the issuer pays the participation fee for invitees;
- a public **ERP API** (OAuth2 client credentials or API keys, signed webhooks, CSV/XLSX import and export);
- a Filament **admin panel**.

Arabic is the default language (RTL) and English (LTR) is available everywhere.

## ملخص بالعربية

بافو منصة سعودية للمنافسات بين الشركات. تطرح الشركة (طارح المنافسة) منافسة وتدعو إليها شركات أخرى
(المتنافسون) لتقديم عروضها. للمنافسة اتجاهان: **مناقصة** يفوز فيها أقل سعر، و**مزايدة** يفوز فيها أعلى
سعر. ولها صيغتان: **مباشرة** يرى فيها المتنافس ترتيبه لحظة بلحظة، أو **بعروض مغلقة** لا تظهر مبالغها لأحد
قبل الإغلاق.

تدعم المنصة:

- جولة BAFO (العرض الأفضل والنهائي)، والتمديد التلقائي عند وصول عرض في الدقائق الأخيرة؛
- الترسية مع المبرر، وتقرير النتيجة بصيغة PDF بالعربية والإنجليزية؛
- الباقات والاشتراكات والفواتير الضريبية؛
- الرسوم المغطّاة: يدفع طارح المنافسة رسوم مشاركة المدعوين عبر «تصريح مشاركة مغطّاة»؛
- واجهة برمجية عامة لربط أنظمة ERP مع إشعارات Webhook موقّعة؛
- لوحة إدارة للمنصة.

يضم المستودع ثلاثة تطبيقات:

- واجهة برمجية بـ Laravel 13، ومعها لوحة الإدارة وخادم Reverb للتحديثات اللحظية؛
- تطبيق ويب بـ Nuxt 4؛
- تطبيق جوال بـ Flutter لأندرويد وiOS.

العربية هي اللغة الافتراضية، والإنجليزية متاحة في كل الشاشات.

- **التشغيل المحلي:** شغّل `scripts/dev.sh` من جذر المستودع بعد تثبيت المتطلبات.
- **الحسابات التجريبية:** في `docs/build/DEMO.md`.
- **الخدمات الخارجية:** بوابة الدفع والفوترة الإلكترونية والإشعارات الفورية تعمل بمحركات تجريبية في هذه النسخة، والرسائل النصية غير مفعّلة.
- **حالة البناء:** التفاصيل في `docs/build/STATUS.md`.

---

## Contents

1. [Repository layout](#repository-layout)
2. [Architecture](#architecture)
3. [Prerequisites](#prerequisites)
4. [First-time setup](#first-time-setup)
5. [Run everything locally](#run-everything-locally)
6. [Demo accounts](#demo-accounts)
7. [Tests and quality checks](#tests-and-quality-checks)
8. [Build the Android APK](#build-the-android-apk)
9. [iOS](#ios)
10. [Environment variables](#environment-variables)
11. [What is fake and what is real](#what-is-fake-and-what-is-real)
12. [Deferred scope (Release 1.1, 1.2, Phase 2)](#deferred-scope-release-11-12-phase-2)
13. [Documentation map](#documentation-map)

## Repository layout

| Path | What |
|---|---|
| `apps/api` | Laravel 13 modular monolith: first-party API, public ERP API, Reverb, queues, Filament admin ([apps/api/README.md](apps/api/README.md)) |
| `apps/web` | Nuxt 4 web app: SSR landing page and legal pages, SPA dashboard ([apps/web/README.md](apps/web/README.md)) |
| `apps/mobile` | Flutter app for Android and iOS ([apps/mobile/README.md](apps/mobile/README.md)) |
| `docs/build` | The build contract (BRIEF, ARCHITECTURE, API, SCREENS, CONVENTIONS), STATUS, DEMO, SECURITY_REVIEW, handoff notes |
| `docs/01_findings`, `docs/02_design`, `docs/03_proposal` | Analysis of the legacy product, per-requirement designs, MVP scope and estimates |
| `assets/brand` | Logos, app icons and colour tokens |
| `scripts` | `dev.sh` (start the stack), `reset-db.sh` (reseed the dev database), `test-api.sh` (API tests on an isolated database) |

## Architecture

```
  apps/web (Nuxt 4, :3000)     apps/mobile (Flutter)      ERP / iPaaS               Browser
     │  Bearer token (Sanctum)     │  Bearer token (Sanctum)  │  OAuth2 client            │  admin session
     │                             │                          │  credentials or API key   │
     ▼                             ▼                          ▼                           ▼
 ┌─────────────────────────────── apps/api  (Laravel 13, :8000) ───────────────────────────────┐
 │  /api/app/v1/*        first-party API (web + mobile)                                         │
 │  /api/public/v1/*     public ERP API + OpenAPI 3.1 (/docs/api, /api/public/v1/openapi.yaml)  │
 │  /admin               Filament admin panel                                                   │
 │  /broadcasting/auth   private-channel auth for Reverb                                        │
 │  /pay/fake/{payment}  fake hosted checkout (non-production only)                             │
 │                                                                                              │
 │  app/Modules: Platform · Catalog · Identity · Integrations · Competitions · Bidding ·         │
 │               Billing · Notifications · Admin          shared kernel: app/Support            │
 └───────┬──────────────────────┬───────────────────────┬──────────────────────┬────────────────┘
         │                      │                       │                      │
   PostgreSQL 17          Redis :6379            queue worker +          Reverb :8085
   db "bafo"              queues, cache,         scheduler               WebSocket (Pusher protocol),
   (tests: bafo_*_test)   rate limits            (live, notifications,   private channels only
                                                 billing, webhooks,      ──▶ live updates to web
                                                 pdf, imports)               and mobile clients
                                                     │
                                                     └──▶ signed webhooks to ERP endpoints
```

- **Competitions** owns the lifecycle, rules and invitations.
- **Bidding** is the engine. Offers are accepted under a row lock with the database clock and written to an append-only, hash-chained ledger. Every payload that leaves the server goes through one visibility projector: no other participant's identity, no prices unless the competition shows them, and nothing from sealed offers before the close.
- **Billing** holds plans, checkout, invoices and sponsored passes behind the `PaymentGateway` and `EInvoicing` interfaces.
- **Integrations** owns the public API, webhooks, the vendor directory and import/export.

The full design is in [docs/build/ARCHITECTURE.md](docs/build/ARCHITECTURE.md). The endpoint contract is in [docs/build/API.md](docs/build/API.md).

## Prerequisites

| Tool | Version | Notes |
|---|---|---|
| PHP | 8.4+ (8.5 used) | Extensions `pdo_pgsql`, `intl`, `pcntl`. Redis goes through `predis`, so `phpredis` is optional |
| Composer | 2.x | |
| PostgreSQL | 17 | Unix socket in `/tmp`, current OS user, no password (change `DB_*` otherwise). `createdb` must be on the `PATH` for the test script |
| Redis | 8.x used | `127.0.0.1:6379` |
| Node.js + pnpm | Node ≥ 22.12, pnpm 9 | |
| Flutter | stable 3.47 (Dart 3.13) | Android SDK 36 and a JDK 17 or newer (Android Studio's bundled JBR works) |
| Xcode + CocoaPods | Xcode 27 and CocoaPods 1.17 used | iOS only, on macOS (see [iOS](#ios)) |

## First-time setup

```sh
# 1. API
createdb -h /tmp bafo      # the dev database
cd apps/api
composer setup             # composer install, .env from .env.example, APP_KEY, Passport keys, migrate
# Then edit apps/api/.env:
#   REVERB_APP_ID, REVERB_APP_KEY, REVERB_APP_SECRET  -> any local random values,
#                                                        e.g. php -r 'echo bin2hex(random_bytes(10));'
#   OTP_FAKE_CODE -> optional fixed one-time code for local work (see docs/build/DEMO.md)
cd ../..
scripts/reset-db.sh --yes  # migrate:fresh --seed + the demo data (DemoSeeder); refuses production

# 2. Web
cd apps/web
pnpm install
cp .env.example .env       # optional: the defaults already target http://localhost:8000

# 3. Mobile
cd ../mobile
flutter pub get            # also generates the l10n classes
cp env/dev.example.json env/dev.json   # optional overrides; env/*.json is git-ignored
```

## Run everything locally

One command starts the whole stack in one terminal; Ctrl+C stops it:

```sh
scripts/dev.sh
```

| Process | Address |
|---|---|
| API (`php artisan serve`) | http://localhost:8000 (health: `/api/app/v1/health`) |
| Admin panel | http://localhost:8000/admin |
| Public API reference | http://localhost:8000/docs/api |
| Reverb (`php artisan reverb:start`) | ws://localhost:8085 |
| Queue worker (`queue:work`, all queues by priority) and scheduler (`schedule:work`) | – |
| Web (`pnpm dev`, only if `apps/web/node_modules` exists) | http://localhost:3000 (redirects to `/ar`; `/en` for English) |

Before it starts, the script clears the config and route caches. It also generates the Passport keys if they are missing, and it refuses to start if port 8000 or 8085 is taken.

Overrides:

| Variable | Default | Meaning |
|---|---|---|
| `API_HOST` | `127.0.0.1` | `0.0.0.0` to reach the API from a phone on the LAN |
| `API_PORT` | `8000` | API port |
| `REVERB_PORT` | `8085` | Reverb port |
| `WEB_PORT` | `3000` | Nuxt dev server port |
| `SKIP_WEB` | – | `1` skips the web app |

Run the mobile app against it:

```sh
cd apps/mobile
flutter run                                          # Android emulator -> http://10.0.2.2:8000, Reverb 10.0.2.2:8085
flutter run --dart-define-from-file=env/dev.json     # with local overrides
```

The Reverb key, host and port reach the web and mobile apps from `GET /api/app/v1/app-config`, so
the clients need no Reverb configuration by default. E-mails (OTP codes, invitations) go to the
`log` mailer, in `apps/api/storage/logs/laravel.log`.

> The queue worker keeps the code it loaded at start. After you pull API changes, restart
> `scripts/dev.sh` so queued jobs run the new code.

## Demo accounts

`scripts/reset-db.sh --yes` loads a demo scenario:

- a platform super admin;
- five organizations: an issuer, three suppliers and a buyer, on different plans;
- twelve competitions, one in every state (tenders and auctions, live, sealed, BAFO round, awarded, sponsored);
- an ERP API client, an API key and a webhook endpoint.

The e-mail addresses, credentials and the fixed OTP code are in **[docs/build/DEMO.md](docs/build/DEMO.md)**. They are for local and demo environments only, and `DemoSeeder` refuses to run in production.

## Tests and quality checks

### API (`apps/api`)

Run the API tests **only** through the script. It creates an isolated database `bafo_<suffix>_test`
and a separate Redis prefix, so it never touches the dev database.

```sh
scripts/test-api.sh <suffix>                          # full Pest suite, serial (about 100 s)
scripts/test-api.sh <suffix> --parallel               # full suite in parallel (about 25 s)
scripts/test-api.sh <suffix> tests/Feature/Bidding    # one area; any Pest argument works (--filter=…)

cd apps/api
vendor/bin/phpstan analyse --memory-limit=1G          # Larastan level 6 over app/
vendor/bin/pint --test                                # code style
```

Notable groups:

- `tests/Feature/E2E` has two full flows over HTTP.
- `tests/Feature/Security` has the security regression suite (see [SECURITY_REVIEW.md](docs/build/SECURITY_REVIEW.md)).
- `tests/Unit/Schema` checks the schema.

### Web (`apps/web`)

```sh
cd apps/web
pnpm lint          # ESLint (also rejects bare strings in templates)
pnpm typecheck     # vue-tsc, strict
pnpm test          # Vitest: unit + Nuxt runtime projects
pnpm build         # production build in .output/

pnpm exec playwright install chromium   # once
pnpm test:e2e smoke                     # Playwright smoke test against the production build
```

The live end-to-end specs (`live-auction`, `live-tender`, `live-billing`, `live-integrations`) drive a full stack with real Reverb. They are opt-in (`E2E_LIVE=1`). How to start a private stack for them is in [apps/web/tests/e2e/README.md](apps/web/tests/e2e/README.md).

### Mobile (`apps/mobile`)

```sh
cd apps/mobile
flutter analyze
flutter test                                     # unit, bloc and widget tests; opt-in live tests are skipped
dart run tool/generate_tokens.dart --check       # theme tokens in sync with the brand JSON
```

The opt-in tests against a running API are described in [apps/mobile/README.md](apps/mobile/README.md). They are enabled with `BAFO_LIVE_API` and the demo credentials in the environment.

Current totals are in [docs/build/STATUS.md](docs/build/STATUS.md#0-final-snapshot).

## Build the Android APK

```sh
cd apps/mobile
# macOS with Android Studio: use its bundled JDK
export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"

flutter build apk --debug                                   # -> build/app/outputs/flutter-apk/app-debug.apk
flutter build apk --debug --dart-define-from-file=env/dev.json   # with compile-time overrides
adb install -r build/app/outputs/flutter-apk/app-debug.apk  # install on a device or emulator
```

Debug builds allow cleartext HTTP only to `10.0.2.2` and `localhost`. On a physical phone, pass
`--dart-define=API_BASE_URL=http://<your LAN IP>:8000/api/app/v1`, start the stack with
`API_HOST=0.0.0.0`, and use HTTPS or adjust the debug network security config.

Release builds (`flutter build apk --release` / `appbundle`) are configured to sign with the
**debug** key (`android/app/build.gradle.kts`), because there is no release keystore in the repository.
Add a signing config (`key.properties`, git-ignored) before a store upload.

## iOS

The iOS project is generated and configured: bundle id `sa.bafo.app`, iOS 15.0 minimum, and Arabic and English display names. Before the first iOS build on a Mac, **run these yourself** (they need `sudo`):

```sh
sudo xcode-select --switch /Applications/Xcode.app/Contents/Developer
sudo xcodebuild -runFirstLaunch
```

Also install an iOS Simulator runtime (Xcode › Settings › Components). Then:

```sh
cd apps/mobile
flutter pub get
cd ios && pod install && cd ..
flutter build ios --debug --no-codesign    # compile check without a signing team
open -a Simulator && flutter run           # iOS simulator -> http://localhost:8000
```

A device or App Store build needs an Apple developer team set in Xcode (`ios/Runner.xcworkspace`, Signing & Capabilities). On iOS, `NSAllowsLocalNetworking` permits plain HTTP to the local API.

## Environment variables

Only placeholders are committed (`apps/api/.env.example`, `apps/web/.env.example`,
`apps/mobile/env/dev.example.json`). Never commit real keys. The full list with meanings is in
ARCHITECTURE §15.2.

### API (`apps/api/.env`)

| Variable | Default | Purpose |
|---|---|---|
| `APP_URL`, `WEB_URL` | `http://localhost:8000`, `http://localhost:3000` | API origin, and the web origin used in e-mail links |
| `DB_HOST`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | `/tmp`, `bafo`, your user, empty | PostgreSQL |
| `REDIS_HOST`, `REDIS_PORT`, `REDIS_PREFIX` | `127.0.0.1`, `6379`, `bafo-database-` | Queues, cache, rate limits |
| `CORS_ALLOWED_ORIGINS` | `http://localhost:3000` | First-party web origins |
| `REVERB_APP_ID`, `REVERB_APP_KEY`, `REVERB_APP_SECRET` | empty: **set them** | Reverb credentials |
| `REVERB_PORT`, `REVERB_SCHEME`, `REVERB_PUBLIC_HOST` | `8085`, `http`, `localhost` | Handed to the clients through `GET /app-config` |
| `MAIL_MAILER` | `log` | E-mail transport |
| `OTP_FAKE_CODE` | empty | Fixed OTP code, honoured in `local` and `testing` only |
| `PAYMENT_GATEWAY` | `fake` | `fake` or `moyasar`; the fake driver is refused in production |
| `PAYMENT_FAKE_AUTO_APPROVE` | `false` | Skip the fake checkout page and approve at once |
| `MOYASAR_SECRET_KEY`, `MOYASAR_PUBLISHABLE_KEY`, `MOYASAR_WEBHOOK_SECRET` | empty | Real gateway keys (without them: 503 `gateway_not_configured`) |
| `BILLING_ALLOWED_RETURN_URLS` | `http://localhost:3000` | Where checkout may return to |
| `EINVOICING_DRIVER` | `fake` | ZATCA e-invoicing provider |
| `SELLER_*` | placeholders | Seller details printed on tax invoices |
| `PUSH_DRIVER` | `log` | Push notifications |
| `PDF_DRIVER` | `mpdf` | PDF rendering |
| `API_KEY_ENV` | `test` | Prefix of issued API keys (`bafo_test_…` / `bafo_live_…`) |
| `WEBHOOKS_ALLOW_PRIVATE_TARGETS` | `false` in code, `true` in `.env.example` | Allows webhooks to local addresses; keep `false` in production |
| `ADMIN_MFA_REQUIRED` | `false` locally | **Must be `true` in production** |
| `ADMIN_SEED_EMAIL`, `ADMIN_SEED_PASSWORD` | empty (demo credentials locally) | The seeded super admin |

### Web (`apps/web/.env`, all optional)

| Variable | Default | Purpose |
|---|---|---|
| `NUXT_PUBLIC_API_BASE` | `http://localhost:8000/api/app/v1` | First-party API root (can be set at runtime for a production build) |
| `NUXT_PUBLIC_BROADCAST_AUTH_ENDPOINT` | `http://localhost:8000/broadcasting/auth` | Reverb channel auth |
| `NUXT_PUBLIC_REVERB_KEY`, `…_HOST`, `…_PORT`, `…_SCHEME` | empty / from `GET /app-config` | Development-only override of the realtime settings |
| `NUXT_PUBLIC_I18N_BASE_URL` | `http://localhost:3000` | Canonical and hreflang links |

### Mobile (`--dart-define`, all optional)

| Key | Default | Purpose |
|---|---|---|
| `API_BASE_URL` | Android `http://10.0.2.2:8000/api/app/v1`, iOS `http://localhost:8000/api/app/v1` | First-party API root |
| `REVERB_AUTH_URL` | `{API origin}/broadcasting/auth` | Channel auth |
| `REVERB_APP_KEY`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | from `GET /app-config` | Override for tunnels or a phone on the LAN |

## What is fake and what is real

External services sit behind interfaces. By default they run on local fake or sandbox drivers, so the whole product works offline with no keys.

| Service | This build | For production |
|---|---|---|
| **Payment gateway** | `fake`: a local hosted checkout page (`/pay/fake/{payment}`) with Approve and Decline, which returns to the app like a real gateway. It is refused when `APP_ENV=production` | `moyasar` driver skeleton (hosted invoices, webhook check). It needs real keys and an end-to-end test against the Moyasar sandbox |
| **E-invoicing (ZATCA)** | `fake`: clears every invoice at once with ZATCA-shaped ids and a base64 TLV QR code. The tax invoice PDFs are real | A real ZATCA provider driver (onboarding, clearance and reporting) |
| **Push notifications** | API: the `log` driver writes to the `push` log channel. Mobile: `NoopPushService` (no Firebase project), so device tokens are never registered. In-app notifications and realtime updates are real | A Firebase project, `FirebasePushService` in the app and an FCM/APNs driver on the API |
| **SMS** | None. OTP codes go by e-mail only | SMS OTP and a sender id are Release 1.2 items |
| **E-mail** | `log` mailer, in `storage/logs/laravel.log` | Any Laravel mail transport (SES, SMTP) |
| **File storage** | Local disks (`private`, `public`) | S3-compatible storage (`AWS_*` placeholders exist) |
| Realtime (Reverb), OAuth2 (Passport), signed webhooks with the SSRF guard, PDF rendering (mPDF, Arabic shaping), the bidding engine, rate limits, audit log | **Real** | – |

## Deferred scope (Release 1.1, 1.2, Phase 2)

This repository implements the **Launch MVP** of [docs/02_design/mvp_reconciled.md](docs/02_design/mvp_reconciled.md) §6.1. The scope deferred after launch is set out in [§8 of the same document](docs/02_design/mvp_reconciled.md#8-after-launch-release-11-the-backlog-and-phase-2):

- **Release 1.1, "Enterprise and integration"** (§8.1):
  - **R1:** a sandbox tenant with simulated bidders, a developer portal and Arabic guides, a 30-day event feed, the full event catalogue, dual-secret rotation, the remaining ≈ 45 API operations, ETag/`expand`/tombstones, IP allow-lists and metering.
  - **R4 finance:** a credit ledger, a settlement engine, on-account billing, refund automation, and the issuer's sponsorship panel on mobile.
  - **R5 governance:** maker-checker award, winner acknowledgement, suspend/resume, disqualify and relaunch, a live-ops console, PDF verification codes, and award and advanced rules on mobile.
  - **Platform:** 6-role RBAC.
- **Release 1.2, "Parity and polish"** (§8.2):
  - **Parity:** Google sign-in, owner login-as, broadcasts, KPI reports, browser web push, SMS OTP and a sender id, a status page and self-service data export.
  - **Content:** a larger marketing site and help centre.
- **Phase 2** (§8.3): SSO; Oracle, SAP and Microsoft connectors; BOQ.

Deliberate limits of this MVP build:

- On mobile, extend, BAFO round, award, revoke, close without award, advanced rules and every purchase are web-only (SCREENS CD5). The app shows the result and explains that billing is managed on the web.
- The MVP's cross-cutting delivery items are not in this repository: hosting in a KSA landing zone, IaC, CI/CD, observability, backups, penetration test and the store release. There is no CI configuration yet.

The open items per area are listed in [docs/build/STATUS.md](docs/build/STATUS.md).

## Documentation map

| Document | What |
|---|---|
| [docs/build/BRIEF.md](docs/build/BRIEF.md) | Scope, stack, glossary, cross-cutting rules (start here) |
| [docs/build/ARCHITECTURE.md](docs/build/ARCHITECTURE.md) | Modules, schema, state machines, bidding engine, access policy, realtime, jobs |
| [docs/build/API.md](docs/build/API.md) | App v1 and public v1 endpoint contract, error codes, webhooks |
| [docs/build/SCREENS.md](docs/build/SCREENS.md) | Web and mobile screens and states |
| [docs/build/CONVENTIONS.md](docs/build/CONVENTIONS.md) | Code, copy, i18n and test conventions |
| [docs/build/STATUS.md](docs/build/STATUS.md) | What is done, what is partial, test totals |
| [docs/build/DEMO.md](docs/build/DEMO.md) | Demo data and sign-ins |
| [docs/build/SECURITY_REVIEW.md](docs/build/SECURITY_REVIEW.md) | Security audit, fixes and accepted residual risks |
| [docs/build/handoff/](docs/build/handoff/) | Per-module handoff notes |
| [docs/build/bafo-public-v1.postman_collection.json](docs/build/bafo-public-v1.postman_collection.json) | Postman collection of the public API |
