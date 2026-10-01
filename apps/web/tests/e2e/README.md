# Web end-to-end tests (Playwright)

| Spec | What | Needs |
|---|---|---|
| `smoke.spec.ts` | Landing, locale, auth redirects | the built web app only |
| `screenshots.spec.ts` | Visual review pass at 360/768/1280 px (`E2E_SCREENSHOTS=1`) | web + seeded API |
| `live-auction.spec.ts` | Auction through the wizard, covered pass paid on the test checkout, sponsored join, live bidding over Reverb, outbid, auto-extension, award with a justification, results | full stack (below) |
| `live-tender.spec.ts` | Live tender with the issuer in English (ranks without prices, extension, award below target) and a sealed tender (no amount visible to anyone until the close) | full stack |
| `live-billing.spec.ts` | Register a company (e-mail OTP), subscribe through the test checkout, invoice, PDF download | full stack |
| `live-integrations.spec.ts` | API client and webhook endpoint; each secret shown once; the client secret gets a real OAuth token | full stack |

## Live specs (`E2E_LIVE=1`)

They drive a real stack: the web app, the app API with a queue worker and the scheduler (the tick opens
and closes competitions), and a Reverb server, on a database seeded with `DemoSeeder` (docs/build/DEMO.md).
Every live update is asserted without a reload while the connection indicator reads «مباشر» / "Live",
no polling request is made, and the Reverb frames are recorded (participants' frames are checked for
other participants' names and amounts).

Use a private stack so the shared dev processes and the `bafo` database are untouched, for example:

```sh
# API env (shell variables win over apps/api/.env)
export DB_DATABASE=bafo_e2e_dev REDIS_PREFIX=bafo-e2e- CACHE_PREFIX=bafo-e2e-cache- \
  APP_URL=http://localhost:8401 WEB_URL=http://localhost:3401 \
  BILLING_ALLOWED_RETURN_URLS=http://localhost:3401 CORS_ALLOWED_ORIGINS=http://localhost:3401 \
  REVERB_PORT=8501 REVERB_SERVER_PORT=8501 WEBHOOKS_ALLOW_PRIVATE_TARGETS=true OTP_FAKE_CODE=123456
createdb -h /tmp bafo_e2e_dev
(cd apps/api && php artisan migrate --seed --force && php artisan db:seed --class=DemoSeeder --force)
(cd apps/api/public && PHP_CLI_SERVER_WORKERS=6 php -S 127.0.0.1:8401 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php) &
(cd apps/api && php artisan reverb:start --host=127.0.0.1 --port=8501) &
(cd apps/api && php artisan queue:work redis --queue=live,default,notifications,mail,billing,webhooks,pdf,imports) &
(cd apps/api && php artisan schedule:work) &

# Web: a production build, configured at runtime
(cd apps/web && pnpm build && PORT=3401 NUXT_PUBLIC_API_BASE=http://localhost:8401/api/app/v1 \
  NUXT_PUBLIC_BROADCAST_AUTH_ENDPOINT=http://localhost:8401/broadcasting/auth node .output/server/index.mjs) &

# Run (the realtime settings come from GET /app-config, so Reverb follows REVERB_PORT)
cd apps/web && E2E_LIVE=1 E2E_BASE_URL=http://localhost:3401 E2E_API_BASE=http://localhost:8401/api/app/v1 \
  pnpm exec playwright test live- --project chromium --workers=4
```

- The demo password is read from `DemoSeeder::PASSWORD` unless `E2E_PASSWORD` is set; OTPs use
  `E2E_OTP_CODE` (default `123456`, the API's `OTP_FAKE_CODE`).
- `E2E_LOCALE=en` runs the auction, billing and integrations specs in English (the tender spec always
  mixes an English issuer with Arabic and English participants).
- Competitions run in real time: each opens 3 minutes after publishing (joining closes when offers
  open in a short competition) and runs the 10-minute minimum, so the live specs take 15–20 minutes;
  run them in parallel.
- Screenshots go to `apps/web/docs/screenshots/e2e/`.
- Stop the processes and `dropdb -h /tmp bafo_e2e_dev` afterwards.
