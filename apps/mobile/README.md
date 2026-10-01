# BAFO mobile (Flutter)

The BAFO / بافو app for Android and iOS. Issuers run competitions (tenders and auctions), and participants submit offers. The app is Arabic-first: Arabic and RTL are the default, and English (LTR) is available.

The binding documents are `docs/build/BRIEF.md`, `ARCHITECTURE.md`, `API.md` and `CONVENTIONS.md` (§5 covers Flutter).

| | |
|---|---|
| Application id / bundle id | `sa.bafo.app` |
| App label | `BAFO`, and `بافو` on Arabic devices (Android `values-ar`, iOS `ar.lproj/InfoPlist.strings`) |
| Flutter / Dart | 3.47 / 3.13 |
| Android | minSdk 24, target/compile SDK 36, `allowBackup=false` |
| iOS | Generated project. It is not built on this machine because the Xcode toolchain is missing. |

## Run it

```bash
cd apps/mobile
flutter pub get          # also runs gen-l10n
flutter run              # Android emulator: talks to http://10.0.2.2:8000
```

The defaults reach the local API (`apps/api` on :8000) and Reverb (:8085):

- **Android emulator:** through `10.0.2.2`.
- **iOS simulator:** through `localhost`.

Realtime settings (key, host, port, scheme) come from `GET /app-config`; a loopback host there (`localhost`) is replaced by the API host, so the emulator reaches Reverb at `10.0.2.2` with no extra configuration.

### Configuration (`--dart-define`)

Every value is compile-time and optional. Keep local values in a file:

```bash
cp env/dev.example.json env/dev.json     # env/*.json is git-ignored
flutter run --dart-define-from-file=env/dev.json
```

| Key | Default | Notes |
|---|---|---|
| `API_BASE_URL` | `http://10.0.2.2:8000/api/app/v1` (iOS: `http://localhost:8000/api/app/v1`) | First-party API root |
| `REVERB_AUTH_URL` | `{API origin}/broadcasting/auth` | Channel auth (API.md §0.1) |
| `REVERB_APP_KEY`, `REVERB_HOST`, `REVERB_PORT`, `REVERB_SCHEME` | empty: the values of `GET /app-config` | Set one only to override the server (a tunnel, a phone on the LAN). `https`/`wss` switches to TLS. |

You can also pass single values, for example `flutter run --dart-define=API_BASE_URL=http://192.168.1.20:8000/api/app/v1` for a physical phone on the LAN. Cleartext HTTP is allowed **only** in Android debug builds and **only** for `10.0.2.2` and `localhost` (`android/app/src/debug/res/xml/network_security_config.xml`). On a real device, use HTTPS or a debug build with an adjusted config. On iOS, `NSAllowsLocalNetworking` permits local HTTP.

## Verify

```bash
flutter analyze                      # must be clean
flutter test                         # unit, bloc and widget tests (fixtures from the real API)
flutter build apk --debug
dart run tool/generate_tokens.dart --check   # tokens.dart in sync with the brand JSON
```

`test/fixtures/api/` holds payloads captured from the running API on the demo seed (tokens redacted); `test/core/models/api_models_test.dart` parses every one. An opt-in end-to-end check runs against a live API:

```bash
BAFO_LIVE_API=http://localhost:8000/api/app/v1 BAFO_DEMO_EMAIL=… BAFO_DEMO_PASSWORD=… \
  flutter test test/live/live_api_test.dart      # add BAFO_LIVE_OFFER=1 to place one offer
```

`docs/screenshots/` holds screenshots from the Pixel API 34 emulator: the login screen in Arabic and in English, the adaptive launcher icon under a circular mask, and (phase 1, against the live API) the welcome slide, register step 1, the OTP screen and a legal page in Arabic. `phase2_*.png` are phone-size widget renders. `emulator_*.png` come from the mobile review (2026-09-29), with the debug APK against the local API and Reverb:
- `01`–`15`: Supplier A in Arabic: home, participating, an invitation and its join sheet, a live tender, the room, the composer, confirm, the received offer, My offers, Q&A, notifications, account, plan status and settings.
- `20`–`28`: Issuer Co in Arabic: home, My competitions, the detail and its action sheet, the live monitor, the offers log, participants and the creation wizard.
- `30`–`34`: the same app in English.

## Layout

```
lib/
  main.dart                 bootstrap: edge-to-edge, font licences, Western digits, AppDependencies, BafoApp
  app/                      composition root (dependencies.dart: services + Repositories) and the root
                            widget (app.dart: providers, router, lifecycle, deep links, offline frame)
  core/
    api/                    ApiClient (Dio), interceptors, ApiException, AppGate (426/503/account gate),
                            json.dart (typed readers, WireEnum), pagination.dart, idempotency.dart
    config/                 Env (dart-defines, resolveRealtime), AppConfig + repository + cubit
    files/                  FileDownloadService (bearer download to the temp dir, system viewer)
    l10n/                   LocaleCubit, context.l10n, errorMessage()/errorText() (errors.<code>)
    lookups/                LookupsRepository (per language, ETag)
    models/                 shared domain: enums, Rules, lookups, FileRef, OrganizationSummary, Me
    money/                  Money (integer halalas), Vat, MoneyFormat, parseAmountInput
    network/                NetworkStatusCubit + interceptor (offline banner)
    push/                   PushService + NoopPushService (no Firebase yet)
    realtime/               RealtimeClient + ReverbRealtimeClient, CompetitionChannelHub, UnreadCountCubit
    router/                 go_router (auth/gate redirects, 5-tab shell, root-navigator routes),
                            DeepLinkRouter (S11), gate screens
    session/                SessionCubit (token + Me)
    storage/                TokenStore (Keychain/Keystore), PreferencesStore
    theme/                  tokens, derived colours, semantic ThemeExtension, typography, spacing, ThemeData
    time/                   ServerClock, KsaTime, BafoDateFormat
    utils/                  digits normalisation, validators (S7 rules)
  features/<feature>/{data,domain,presentation}
                            data + domain for every app v1 module (auth, profile, team, home,
                            competitions, invitations, live, billing, notifications);
                            screens: auth (M02, M06–M13), competition overview (/competitions/:id),
                            plan status (M58); placeholders for the phase-2 tabs
  l10n/                     app_ar.arb (template), app_en.arb, generated/
  widgets/                  UI kit (import widgets/widgets.dart)
assets/brand/mark.svg       vector mark (copied from assets/brand/02_logo_files/vector-source)
assets/fonts/               IBM Plex Sans Arabic + Inter (OFL 1.1), bundled
assets/splash/              native splash art (generated)
tool/generate_tokens.dart   brand JSON → lib/core/theme/tokens.dart
tool/brand/generate_brand_assets.py   launcher icons, notification icon, splash art
```

## How the core works

- **HTTP.** `ApiClient` wraps Dio and never leaks a `DioException`. Every call returns `ApiResponse` (`data`, `meta`) or throws `ApiException`, which has these fields:
  - `code`: a server or client code;
  - `message`: already localised by the server;
  - `fieldErrors`: the envelope's `errors`;
  - `details`;
  - `statusCode`.

  Every request sends these headers:
  - `Accept: application/json`;
  - `Accept-Language` (the app language);
  - `Authorization: Bearer` (when signed in);
  - `X-Platform`;
  - `X-App-Version` (from `package_info_plus`);
  - `X-Request-Id`.

  Client-side failures use the codes `network_error`, `timeout`, `cancelled` and `bad_response`. `errorMessage(l10n, error)` picks the server message first, then a local string.
- **Session.** `SessionCubit` owns the Sanctum token and `Me`.
  - Start-up restores a stored token with `GET /me` (a 401 → signed out as expired; offline → signed in without `Me`).
  - Login and OTP verification return `AuthTokenPayload` (`Me` + token); `device_name` is "{model} · {platform}".
  - A 401 on a request that carried a token expires the session. The login screen then says why.
  - Sign-out removes the registered push device, revokes the token (best effort) and always forgets it locally.
  - A language switch is saved with `PATCH /me {locale}` and refreshes server-localised data.
- **Router.** `appRedirect` first applies the app gate: 426 leads to `/update-required`, and 503 `maintenance` leads to `/maintenance`. Then it applies auth: unknown → `/splash`, signed out → `/login?from=…`, signed in → away from login.
  - `from` only accepts in-app paths.
  - Tabs: Home `/home`, Competitions (participant) `/competitions`, My competitions (issuer) `/my-competitions`, Notifications `/notifications`, Account `/account`.
  - Nest feature routes under their tab. Follow the canonical deep links in CONVENTIONS §4.3, for example `/competitions/:id/live`.
- **Time.** `ServerClock` takes `meta.server_time` from every response (`ServerTimeInterceptor`). It uses the midpoint of the round trip, takes the median of the last 3 samples and flags round trips over 2 s.
  - Use `clock.now()` and `remainingUntil()`, never `DateTime.now()`, for anything about bidding.
  - `KsaTime` formats instants in Asia/Riyadh with Western digits.
- **Money.** `Money(amountMinor, currency: 'SAR')` stays in integers.
  - Display: `MoneyFormat.format` gives `1,250.50 ر.س` in Arabic and `SAR 1,250.50` in English, always with two decimals.
  - Input: `Money.tryParse` accepts Arabic-Indic digits and at most two decimals.
  - VAT: `Vat.of` rounds half up, exactly like the API.
- **Realtime.** `RealtimeClient.subscribePrivate(RealtimeChannels.…)` returns a subscription whose `events([name])` stream carries Laravel `broadcastAs` names (`RealtimeEvents.liveUpdated`, …).
  - Settings come from app-config (`configure`); channel auth posts form fields to `/broadcasting/auth` through `ApiClient`.
  - Subscriptions survive reconnects, reconfiguration and `suspend`/`resume` (30 s in the background, then the socket closes; resume reconnects).
  - It disconnects on sign-out.
  - **`CompetitionChannelHub`** shares one subscription per competition (issuer or participant channel; none for invitees). Each `acquire` handle has its own `v` gate: `liveSnapshots` only carries newer snapshots, and REST snapshots go through `acceptSnapshot`. `resyncRequests` fires after a reconnect or an app resume (ARCHITECTURE §9.4).
  - **`UnreadCountCubit`** follows `user.{id}` for the shell badge and republishes `notification.created`.
- **App config and gates.** `AppConfigCubit` loads `GET /app-config` at start and on resume (cached for offline starts): `min_version` → update screen, `maintenance` → maintenance screen (retried every 60 s), realtime settings → the client. 403 `account_inactive`/`organization_suspended` on an authenticated request opens the account gate (support contacts, sign out).
- **Network.** `NetworkStatusCubit` goes offline on transport failures, probes `GET /time` every 10 s, and comes back on any answer or a connected socket; the offline banner sits above every screen.
- **Deep links.** `DeepLinkRouter.map(route)` is the S11 table (unknown → `/notifications`); `open` marks the notification read and pushes the location (signed out, it goes through the login `from`).
- **Files.** `FileDownloadService` downloads `download_path` with the bearer header to the temporary directory and opens it with the system viewer (`AttachmentTile`).
- **Dates.** `BafoDateFormat`: long date, time, date-time, deadline («… بتوقيت الرياض»), relative (< 7 days), machine (`… (KSA)`), Riyadh picker conversions; Western digits always (R-M3).
- **Push.** Only `PushService` / `NoopPushService` exist until a Firebase project exists. Ask for notification permission in context, never at start-up.

## Design system

- **Tokens.** `core/theme/tokens.dart` is generated from `assets/brand/04_ui_color_tokens/bafo_colors.json`. Never edit it by hand. `derived_colors.dart` holds the roles the package lacks: dark mode and AA text variants. These are PROPOSED in `docs/01_findings/05_brand.md` and still need sign-off.
- **Accessibility.** Filled primary buttons use green-dark `#0B7A55` (5.34:1 with white). Brand green `#0E9F6E` is kept for the mark only (option B, strict AA, §2.3).
- **Semantic colours.** `context.semanticColors` provides `leading` (green), `outbid` (amber, never red), `destructive` (red), `sponsored`, `warning` and `info`. Always pair a colour with an icon and a label.
- **Type.** IBM Plex Sans Arabic for Arabic, Inter for English, each as the other's fallback. Letter spacing is always 0. Numbers that change live (`MoneyText`, `CountdownText`) use tabular figures.
- **Kit (`lib/widgets`).** Phase 1 added: direction/format/status chips (S2, with the closing-soon and extended pills), access/result chips, `FeesCoveredBadge`, `StandingBadge`, `StandingBanner` (throttled live region), `CompetitionCountdown` (targets, extension banner, «جارٍ الإغلاق…», announcements), `MoneyInputField`, `RulesSummaryCard`, `KeyValueList`, `InfoNotice`, `StatTile`, `MeterBar`, `AttachmentTile`, `PhoneField`, `OtpField`, `PasswordField` (rule checklist), `DigitsField`, `SearchField`, `DateTimeField` (Riyadh), `ConsentCheckbox`, `SegmentedFilter`, `StepperHeader`, `CooldownButton`, reason/image/multi-select sheets, `ForbiddenState`, `NotFoundState`, `OfflineBanner`, `MarkdownView`, `LanguageSwitchButton`, `Ltr`/`ltrIsolate`. The scaffold kit:
  - `BafoButton`: filled, tonal, outline, text and destructive variants, plus a loading state.
  - Form and layout: `BafoTextField`, `BafoDropdown`, `BafoCard`, `SectionHeader`, `BafoAppBar` (mark only; the wordmark is never live text).
  - Status and numbers: `StatusPill`, `CountdownText` (ServerClock; turns amber in the last 5 minutes), `MoneyText`.
  - States: `EmptyState`, `ErrorState`, `LoadingSkeleton` / `LoadingSkeletonList`.
  - Overlays: `showBafoBottomSheet`, `showConfirmDialog` (a themed Material dialog on both platforms), `BafoToast`.
  - Brand: `OrgAvatar`, `BrandMark`.

## Localisation

- The template is `lib/l10n/app_ar.arb`. `app_en.arb` must have the same keys; `test/l10n_test.dart` enforces this.
- Keys are the canonical dot path in lowerCamelCase (CONVENTIONS §6.2), for example `auth.login.title` becomes `authLoginTitle`.
- Direction-dependent text uses one key with an ICU `select` on `direction`. Plurals use ICU `plural`; Arabic needs its 6 forms.
- There are no hard-coded UI strings, and product copy has no exclamation marks.
- `flutter pub get` (or `flutter gen-l10n`) regenerates `lib/l10n/generated/`.
- The language is Arabic unless the user picked English. A switch is on the login screen and on the Account tab, and the choice is stored in `PreferencesStore`.

## Brand assets

`python3 tool/brand/generate_brand_assets.py` regenerates the following from the brand package. It needs Pillow and macOS `sips`.

- **Android legacy icons:** the delivered dark icons.
- **Android adaptive icon:**
  - background `#1C1F26` (a colour resource);
  - a **corrected foreground**, re-drawn from `icon_master.svg`. Its farthest point is 31 dp from the centre, inside the 33 dp safe zone. The delivered foreground reached 38 dp and was clipped by circular masks (05_brand.md §2.7).
  - a **monochrome** themed layer: a white silhouette with a knock-out gap between the chevrons.
- **Notification icon:** `@drawable/ic_stat_bafo`, a white silhouette at 24 dp, plus `@color/notification_color`. Firebase will use it once added.
- **iOS AppIcon:** every size in `Contents.json`. It uses the delivered 1024, 180, 152 and 120 files and resizes the rest from 1024 with `sips`. All icons are opaque RGB.
- **Splash art:** under `assets/splash/`. Then run `dart run flutter_native_splash:create` (config in `flutter_native_splash.yaml`): charcoal `#1C1F26` with the colour mark in light and dark mode, including the Android 12+ splash API.

## Not done yet

Every screen of SCREENS.md §3.3 is built (phase 2). `docs/build/handoff/mobile.md` has the routes per feature, the client decisions, the API notes and the review (§8).

- Dead files to delete (unreachable since phase 2; the review could not delete them): `lib/features/competitions/presentation/competition_overview_screen.dart`, `competition_overview_cubit.dart`, `competition_routes.dart`, `issued_competitions_screen.dart`, `participating_competitions_screen.dart`, and `lib/features/profile/presentation/profile_screen.dart`. With them go the `CompetitionOverviewCubit` group of `test/features/overview_and_billing_cubits_test.dart`, `competitionDetailRoute` in the router, the overview fallback of `IssuerCompetitionScreen`, and the ARB keys only they use (handoff §8.3).
- `FirebasePushService` (CONVENTIONS §5.1) waits for a Firebase project. When it lands, also add the FCM meta-data for `ic_stat_bafo`, register the device (`DevicesRepository`) and store its id under `PreferenceKeys.pushDeviceId`.
- **Secure storage.** `flutter_secure_storage` 11 has no `encryptedSharedPreferences` option any more. Its default is Keystore-wrapped AES-GCM, and backups are excluded by the manifest.
- `file_picker` picks the avatar and the logo from the photo library: `NSPhotoLibraryUsageDescription` is in `ios/Runner/Info.plist` (localised in `ar.lproj`/`en.lproj`). A camera option would need `image_picker` and `NSCameraUsageDescription`.
- iOS is not built on this machine; the new plugins (`device_info_plus`, `path_provider`, `open_filex`) need a first `pod install`/SPM resolution on a Mac with Xcode.
- Release signing (Android keystore, iOS team) is not configured. Debug keys sign release builds for now.
