#!/usr/bin/env sh
# Start one BAFO role (hosted demo: Render / Railway). PORT is injected by the host;
# DB_URL / REDIS_URL come from its managed Postgres and Redis.
set -e
cd /app

# Render's generateValue gives a base64 256-bit string: turn it into a Laravel key.
case "$APP_KEY" in
  base64:*) ;;
  "") echo "APP_KEY is not set" >&2; exit 1 ;;
  *) export APP_KEY="base64:$APP_KEY" ;;
esac
# Reverb credentials travel in URLs: keep them alphanumeric (same result on every service).
[ -n "$REVERB_APP_KEY" ] && export REVERB_APP_KEY="$(printf '%s' "$REVERB_APP_KEY" | tr -dc 'A-Za-z0-9' | cut -c1-32)"
[ -n "$REVERB_APP_SECRET" ] && export REVERB_APP_SECRET="$(printf '%s' "$REVERB_APP_SECRET" | tr -dc 'A-Za-z0-9' | cut -c1-32)"

if [ ! -f storage/oauth-private.key ]; then
  if [ -n "$PASSPORT_PRIVATE_KEY" ]; then
    printf '%s' "$PASSPORT_PRIVATE_KEY" > storage/oauth-private.key
    printf '%s' "$PASSPORT_PUBLIC_KEY" > storage/oauth-public.key
  else
    php artisan passport:keys --force >/dev/null
  fi
  chmod 600 storage/oauth-private.key
fi

php artisan config:clear >/dev/null
php artisan storage:link >/dev/null 2>&1 || true

case "${ROLE:-api}" in
  api)
    php artisan migrate --force
    # Reference data on every boot (idempotent: lookups, tier presets, plans, settings defaults),
    # demo data only on the first boot (DemoSeeder refuses APP_ENV=production).
    FIRST_BOOT="$(php artisan tinker --execute='echo \App\Modules\Admin\Models\Admin::query()->count();' 2>/dev/null | tail -n1)"
    php artisan db:seed --force
    if [ "$FIRST_BOOT" = "0" ]; then
      php artisan db:seed --class=DemoSeeder --force || true
    fi
    # Free hosting has no separate worker: run the queue and scheduler alongside the API
    # unless a dedicated worker service exists (WITH_WORKER=0).
    if [ "${WITH_WORKER:-1}" = "1" ]; then
      php artisan schedule:work >/dev/null 2>&1 &
      php artisan queue:work redis --queue=live,default,notifications,mail,billing,webhooks,pdf,imports --tries=3 --sleep=1 &
    fi
    exec php artisan serve --host=0.0.0.0 --port="${PORT:-8000}" --no-reload
    ;;
  reverb)
    exec php artisan reverb:start --host=0.0.0.0 --port="${PORT:-8085}"
    ;;
  worker)
    php artisan schedule:work &
    exec php artisan queue:work redis --queue=live,default,notifications,mail,billing,webhooks,pdf,imports --tries=3 --sleep=1
    ;;
  *)
    echo "Unknown ROLE: $ROLE" >&2; exit 1
    ;;
esac
