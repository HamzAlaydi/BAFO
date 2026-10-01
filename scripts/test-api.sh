#!/usr/bin/env bash
# Run the API test suite against an isolated database so parallel workers don't collide.
# Usage: scripts/test-api.sh [suffix] [pest args...]   e.g. scripts/test-api.sh bidding --filter=Offer
set -euo pipefail
suffix="${1:-main}"; shift || true
db="bafo_${suffix}_test"
createdb -h /tmp "$db" 2>/dev/null || true
cd "$(dirname "$0")/../apps/api"
DB_DATABASE="$db" REDIS_PREFIX="bafo-test-${suffix}-" exec vendor/bin/pest "$@"
