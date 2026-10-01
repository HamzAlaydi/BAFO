# BAFO API

Laravel 13 modular monolith for the BAFO competition platform. The binding contract is in
`docs/build/BRIEF.md` and `docs/build/ARCHITECTURE.md` at the repository root.

## Requirements

PHP 8.4+ (8.5 locally) with `pdo_pgsql`, `intl`, `pcntl`; Composer; PostgreSQL 17 with the
databases `bafo` and `bafo_test`; Redis on `127.0.0.1:6379`. Redis is reached through `predis`,
so the `phpredis` extension is optional.

## Setup

```sh
composer setup            # install, .env from .env.example, app key, Passport keys, migrate
```

Fill the `REVERB_APP_ID`, `REVERB_APP_KEY` and `REVERB_APP_SECRET` values in `.env` with local random
values. `.env.example` only carries placeholders.

## Run

```sh
../../scripts/dev.sh      # API :8000, Reverb :8085, queue worker, scheduler, Nuxt :3000 (if present)
../../scripts/reset-db.sh # migrate:fresh --seed on the dev database (asks first; --yes to skip)
```

| URL | What |
|---|---|
| `GET /api/app/v1/health` | database, Redis and queue checks (200, or 503 when degraded) |
| `GET /api/app/v1/app-config` | `AppConfig` (API.md §2.13): versions, maintenance, support, realtime connection, legal versions, features, server time |
| `GET /api/app/v1/time` | server time |
| `/admin` | Filament admin panel |
| `POST /broadcasting/auth` | Reverb private-channel auth (Sanctum bearer token) |

## Quality

```sh
php artisan test          # Pest, against bafo_test (refuses any non *_test database)
composer lint             # Pint (Laravel preset + declare(strict_types=1))
composer analyse          # Larastan level 6 over app/
```

## Layout

| Path | Contents |
|---|---|
| `app/Support` | Shared kernel: API envelopes, error renderer, locale and JSON middleware, `HasPublicId`, module base provider, route loader |
| `app/Modules/<Module>` | Identity, Catalog, Competitions, Bidding, Billing, Notifications, Integrations, Admin, Platform. Providers are auto-discovered |
| `routes/app_v1/<module>.php` | First-party API, `/api/app/v1`, group `app_v1` (Sanctum by default) |
| `routes/public_v1/<module>.php` | Public ERP API, `/api/public/v1`, group `public_v1` (Integrations, Identity, Catalog, Competitions, Bidding; client auth appended by Integrations) |
| `lang/{ar,en}` | Arabic (default) and English messages; `errors.php` holds the error codes |
