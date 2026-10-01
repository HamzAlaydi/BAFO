# BAFO web (Nuxt 4)

Arabic-first web app for BAFO: the public landing page (SSR) and the dashboard (client-rendered SPA).
Binding brief: `docs/build/BRIEF.md`.

## Commands

```bash
pnpm install          # also runs `nuxt prepare`
pnpm dev              # http://localhost:3000 → redirects to /ar
pnpm build            # production build in .output/
pnpm lint             # ESLint (@nuxt/eslint, stylistic, no bare strings in templates)
pnpm typecheck        # vue-tsc, TypeScript strict
pnpm test             # Vitest: `unit` (pure TS) + `nuxt` (components, stores, composables in a Nuxt runtime)
pnpm test:e2e         # Playwright smoke test against `.output` (run `pnpm build` and
                      # `pnpm exec playwright install chromium` first)
```

Configuration comes from `.env` (see `.env.example`; placeholders only). The API base defaults to
`http://localhost:8000/api/app/v1`. Realtime (Reverb) settings come from `GET /app-config`
(`AppConfig.realtime`); a non-empty `NUXT_PUBLIC_REVERB_KEY` overrides them in development only.
Fonts (Inter, IBM Plex Sans Arabic) are self-hosted from the OFL `@fontsource` packages.

## Layout

| Path | What |
|---|---|
| `app/assets/css/main.css` | Design tokens: brand palette, semantic light/dark roles, Tailwind v4 theme, `.markdown` |
| `app/types/api/<module>.ts` | API resource types, one file per module, same snake_case names as API.md §2 (`~/types/api` re-exports all) |
| `app/services/<module>.ts` | API calls per module (`platform`, `catalog`, `identity`, `competitions`, `bidding`, `billing`, `notifications`, `integrations`), plain functions over `useApi()` |
| `app/stores/` | `auth` (token + `Me`, `can()`, account gate), `appConfig`, `lookups` (ETag per locale), `notifications` (badge, bell, user channel), `home` |
| `app/composables/` | `useApi`, `useServerTime` (median of 3 samples, `keepSynced`), `useCountdown`, `provideCompetition`/`useCompetitionContext`, `useLiveReducer`, `useOfferSubmit`, `useDirectionCopy`, `usePaymentPoll`, `useJobPoll`, `usePendingToken`, `useFileDownload`, `useErrorMessage`, `useCooldown`, `useLiveHeartbeat`, `useUnsavedChangesGuard`, … |
| `app/components/ui/` | UI kit (`Ui*`, SCREENS §4.2) |
| `app/components/<area>/` | Domain components: `competitions/` (status, direction, format chips, rules summary), `invitations/`, `home/`, `notifications/`, `organization/`, `team/`, `profile/` |
| `app/components/app/` | App chrome: logo, brand mark, sidebar, bell, user menu, account gate, maintenance, offline banner |
| `app/layouts/` | `default` (public), `auth` (split), `dashboard` (sidebar + top bar + S8 states) |
| `app/pages/` | Landing, `legal/[code]`, `invitations`, `auth/*`, `dashboard/*` |
| `app/plugins/` | `api` (ofetch client + S8 session effects), `app-config.client`, `echo.client` |
| `app/middleware/` | `auth`, `guest` |
| `app/utils/` | Pure, unit-tested helpers: money, dates, countdown, competition status visual, live reducer, connection state, API errors, error mapping, notification routes, validation, markdown, downloads |
| `app/config/navigation.ts` | Side navigation and `FEATURE_PAGES` flags; flip your entries to available when your page ships |
| `i18n/locales/{ar,en}.json` | All user-facing copy, CONVENTIONS §6.2 namespaces, lower_snake_case keys |
| `public/brand/` | Brand assets (see below) |
| `tests/unit`, `tests/nuxt`, `tests/fixtures`, `tests/e2e` | Vitest unit, Vitest Nuxt runtime, API fixtures, Playwright |

## Conventions

- **Copy:** every string lives in `i18n/locales`; ESLint rejects bare strings in templates. Keys must
  exist in both locales with the same placeholders, use the CONVENTIONS §6.2 namespaces and
  lower_snake_case segments, and every static `t('…')` key must exist (all enforced by
  `tests/unit/locales.spec.ts`). Add keys only under your own namespace with a JSON-aware edit. Use the
  glossary exactly. No exclamation marks. Arabic plurals use six forms
  (`zero | one | two | few | many | other`); call `t(key, params, count)`. Write `@` as `{'@'}`
  (vue-i18n linked-message syntax). Direction-dependent copy uses `.tender` / `.auction` sub-keys
  through `useDirectionCopy()`.
- **RTL:** use logical utilities only (`ms-/me-/ps-/pe-`, `start-/end-`, `text-start`, `border-s`).
  `tests/unit/rtl-guard.spec.ts` fails on physical `ml-/pl-/left-/text-left/...`. Mirror directional
  icons with `rtl:-scale-x-100` (`flip-icon` on `UiButton`/`UiIconButton`); never mirror the logo.
- **Colour:** use semantic utilities (`bg-surface`, `text-fg-muted`, `bg-primary`, `text-danger` …);
  they switch for dark mode through CSS variables (`prefers-color-scheme`, overridden by
  `html[data-theme]`). Filled primary buttons use green-700 `#0B7A55` with white text (strict AA,
  decision D1); brand green `#0E9F6E` is for the mark, icons and focus rings. Red is for destructive and
  error states only. In auctions, never encode "price up" as red: use green for leading and amber for
  outbid.
- **Money:** integer halalas everywhere (`amount_minor`); format with `useMoney()`; input with `<UiMoneyInput>`.
- **Time:** the API sends UTC ISO-8601; display Riyadh time with `useDate()`. Countdowns use
  `useServerTime()`, which `useApi()` syncs from any `server_time` in a response.
- **API:** call the functions in `app/services/<module>.ts`; they go through `useApi()`, which throws
  `ApiError` (`message`, `code`, `errors`, `details`, `status`, `retryAfterSeconds`). Show errors with
  `useErrorMessage()` (`errors.<code>` first, then the server message) and bind 422 field errors by path.
  The client applies the S8 states itself: a 401 on a request that carried a token ends the session
  (`endedReason = 'expired'`), `account_inactive` / `organization_suspended` raise the account gate,
  and `503 maintenance` switches the app to the maintenance page. Test pages and stores by mocking the
  service modules (`vi.mock('~/services/<module>')`) with the fixtures in `tests/fixtures/api.ts`.
- **Session:** the Sanctum token is in the `bafo_token` cookie (SameSite=Lax), read through
  `useAuthToken()` so every consumer shares one ref. The store exposes it as a getter only, so it never
  lands in the SSR payload. Render controls from `auth.can(permission)`, `auth.entitlements` and the
  competition's `permissions` only.
- **E-mail link tokens:** `usePendingToken(kind)` reads `#t=…`, strips it from the address bar and keeps
  it in `sessionStorage` for the flow (SCREENS CD7). Never put a token in a query string.
- **Live data:** the competition detail parent calls `provideCompetition(id)`; tabs use
  `useCompetitionContext()`. Snapshots are applied only by `v`; submit offers with `useOfferSubmit()`.

## Brand assets (`public/brand/`)

Copied from `assets/brand/` without changes: `mark.svg` (= `icon_master.svg`), the colour/mono marks,
the app icon, the Latin mono lockups and the Arabic lockups; favicons and touch icons (dark variant, D10)
sit in `public/`.

Derived here (the package has no stand-alone full-colour wordmark files, see 05_brand.md §1):

- `wordmark-ar-{charcoal,white}.png`: the package's Arabic wordmarks with transparent padding trimmed.
- `wordmark-en-{charcoal,white}.png`: the "BAFO" wordmark cut from the mono lockups, trimmed, and
  recoloured to charcoal `#1C1F26` / white (the guide's wordmark colours).
- `favicon.svg`: the mark on a charcoal rounded square.

Replace these with the outlined vector lockups once brand finishing (BF-02/BF-03) delivers them.
