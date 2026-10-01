#!/usr/bin/env bash
#
# Starts the BAFO local stack in one terminal; Ctrl+C stops everything.
#
#   api      php artisan serve          http://localhost:8000
#   reverb   php artisan reverb:start   ws://localhost:8085
#   queue    php artisan queue:work     redis, all queues by priority
#   schedule php artisan schedule:work
#   web      Nuxt dev server            http://localhost:3000   (only if apps/web exists)
#
# Env overrides: API_HOST (default 127.0.0.1; use 0.0.0.0 for devices on the LAN),
# API_PORT (8000), REVERB_PORT (8085), WEB_PORT (3000), SKIP_WEB=1.
#
# Works with macOS /bin/bash 3.2.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
API_DIR="$ROOT/apps/api"
WEB_DIR="$ROOT/apps/web"

API_HOST="${API_HOST:-127.0.0.1}"
API_PORT="${API_PORT:-8000}"
REVERB_PORT="${REVERB_PORT:-8085}"
WEB_PORT="${WEB_PORT:-3000}"
QUEUES="live,default,notifications,mail,billing,webhooks,pdf,imports"

NAMES=()
PIDS=()

log() { printf '\033[1;32m[dev]\033[0m %s\n' "$*"; }
warn() { printf '\033[1;33m[dev]\033[0m %s\n' "$*" >&2; }

# Kill a process and all of its descendants (artisan serve forks php -S workers).
kill_tree() {
  local pid="$1" child
  for child in $(pgrep -P "$pid" 2>/dev/null || true); do
    kill_tree "$child"
  done
  kill "$pid" 2>/dev/null || true
}

cleanup() {
  trap - INT TERM EXIT
  log "stopping..."
  local pid
  for pid in ${PIDS[@]+"${PIDS[@]}"}; do
    kill_tree "$pid"
  done
  # php -S workers orphaned by an external kill of their master still hold the port.
  pkill -f "php -S $API_HOST:$API_PORT " 2>/dev/null || true
  wait 2>/dev/null || true
  log "stopped"
}
on_signal() {
  cleanup
  exit 0
}
trap on_signal INT TERM
trap cleanup EXIT

# start <name> <dir> <command...>: run in the background with prefixed output.
# $! is the command itself (the subshell execs into it), so kill_tree reaches it
# and every worker it forks.
start() {
  local name="$1" dir="$2"
  shift 2
  (cd "$dir" && exec "$@") > >(awk -v prefix="[$name]" '{ print prefix, $0; fflush() }') 2>&1 &
  NAMES+=("$name")
  PIDS+=("$!")
  log "started $name (pid $!)"
}

port_busy() { lsof -nP -iTCP:"$1" -sTCP:LISTEN >/dev/null 2>&1; }

# --- preflight -------------------------------------------------------------

[[ -f "$API_DIR/artisan" ]] || { warn "apps/api is missing"; exit 1; }
[[ -f "$API_DIR/.env" ]] || { warn "apps/api/.env is missing: cp apps/api/.env.example apps/api/.env && php artisan key:generate"; exit 1; }
[[ -d "$API_DIR/vendor" ]] || { warn "run: (cd apps/api && composer install)"; exit 1; }

# DB_HOST from apps/api/.env: /tmp (unix socket, the default) or 127.0.0.1.
DB_HOST_VALUE="$(sed -n 's/^DB_HOST=//p' "$API_DIR/.env" | tail -n 1 | tr -d '"')"
DB_HOST_VALUE="${DB_HOST_VALUE:-127.0.0.1}"
if command -v pg_isready >/dev/null 2>&1 && ! pg_isready -q -h "$DB_HOST_VALUE" -p 5432; then
  warn "PostgreSQL is not accepting connections on $DB_HOST_VALUE:5432"
fi
if command -v redis-cli >/dev/null 2>&1 && ! redis-cli -h 127.0.0.1 -p 6379 ping >/dev/null 2>&1; then
  warn "Redis is not answering on 127.0.0.1:6379"
fi

for port in "$API_PORT" "$REVERB_PORT"; do
  if port_busy "$port"; then
    warn "port $port is already in use; stop the other process first"
    exit 1
  fi
done

# A cached config would pin old .env values.
(cd "$API_DIR" && php artisan config:clear >/dev/null && php artisan route:clear >/dev/null)

# Passport signing keys for the public API token endpoint (gitignored; ARCHITECTURE §14.2).
if [[ ! -f "$API_DIR/storage/oauth-private.key" || ! -f "$API_DIR/storage/oauth-public.key" ]]; then
  log "generating Passport keys"
  (cd "$API_DIR" && php artisan passport:keys --force >/dev/null)
fi

# --- processes -------------------------------------------------------------

export PHP_CLI_SERVER_WORKERS="${PHP_CLI_SERVER_WORKERS:-4}"

start api "$API_DIR" php artisan serve --host="$API_HOST" --port="$API_PORT" --no-reload
start reverb "$API_DIR" php artisan reverb:start --port="$REVERB_PORT"
start queue "$API_DIR" php artisan queue:work redis --queue="$QUEUES" --tries=3 --sleep=1
start schedule "$API_DIR" php artisan schedule:work

if [[ "${SKIP_WEB:-0}" != "1" && -f "$WEB_DIR/package.json" ]]; then
  if [[ ! -d "$WEB_DIR/node_modules" ]]; then
    warn "apps/web/node_modules is missing; skipping the web app (run the package install in apps/web)"
  elif port_busy "$WEB_PORT"; then
    warn "port $WEB_PORT is already in use; skipping the web app"
  else
    if [[ -f "$WEB_DIR/pnpm-lock.yaml" ]]; then
      start web "$WEB_DIR" pnpm dev --port "$WEB_PORT"
    elif [[ -f "$WEB_DIR/yarn.lock" ]]; then
      start web "$WEB_DIR" yarn dev --port "$WEB_PORT"
    elif [[ -f "$WEB_DIR/bun.lock" || -f "$WEB_DIR/bun.lockb" ]]; then
      start web "$WEB_DIR" bun run dev --port "$WEB_PORT"
    else
      start web "$WEB_DIR" npm run dev -- --port "$WEB_PORT"
    fi
  fi
fi

log "API     http://localhost:$API_PORT  (health: /api/app/v1/health, admin: /admin)"
log "Reverb  ws://localhost:$REVERB_PORT"
[[ " ${NAMES[*]} " == *" web "* ]] && log "Web     http://localhost:$WEB_PORT"
log "Ctrl+C to stop"

# Stop everything as soon as one process exits.
while true; do
  i=0
  for pid in "${PIDS[@]}"; do
    if ! kill -0 "$pid" 2>/dev/null; then
      warn "${NAMES[$i]} exited; shutting down"
      exit 1
    fi
    i=$((i + 1))
  done
  sleep 2
done
