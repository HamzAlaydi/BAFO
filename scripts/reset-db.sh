#!/usr/bin/env bash
#
# Drops every table in the local API database, re-runs all migrations, seeds the reference
# data (php artisan migrate:fresh --seed) and the demo data (php artisan db:seed
# --class=DemoSeeder, docs/build/DEMO.md), then clears caches.
#
#   scripts/reset-db.sh                 asks for confirmation
#   scripts/reset-db.sh --yes           no prompt (required when not run from a terminal)
#   scripts/reset-db.sh --yes --no-demo reference data only
#
# The test database (bafo_test) is managed by the test suite (RefreshDatabase).

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="$ROOT/apps/api"

cd "$API_DIR"

php artisan config:clear >/dev/null

APP_ENV_VALUE="$(php artisan tinker --execute="echo app()->environment();" 2>/dev/null | tail -n 1)"
DB_NAME="$(php artisan tinker --execute="echo config('database.connections.'.config('database.default').'.database');" 2>/dev/null | tail -n 1)"

if [[ "$APP_ENV_VALUE" == "production" ]]; then
  echo "Refusing to reset a production database." >&2
  exit 1
fi

ASSUME_YES=0
SEED_DEMO=1
for arg in "$@"; do
  case "$arg" in
    --yes|-y) ASSUME_YES=1 ;;
    --no-demo) SEED_DEMO=0 ;;
    *) echo "Unknown option: $arg" >&2; exit 1 ;;
  esac
done

if [[ "$ASSUME_YES" != 1 ]]; then
  if [[ ! -t 0 ]]; then
    echo "Not a terminal: pass --yes to reset database '$DB_NAME'." >&2
    exit 1
  fi
  read -r -p "Drop and re-seed database '$DB_NAME' ($APP_ENV_VALUE)? [y/N] " answer
  [[ "$answer" == "y" || "$answer" == "Y" ]] || { echo "Aborted."; exit 1; }
fi

php artisan migrate:fresh --seed --force
if [[ "$SEED_DEMO" == 1 ]]; then
  php artisan db:seed --class=DemoSeeder --force
fi
php artisan optimize:clear >/dev/null
php artisan cache:clear >/dev/null

echo "Database '$DB_NAME' reset and seeded."
