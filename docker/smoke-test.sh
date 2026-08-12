#!/usr/bin/env sh
set -eu
BASE_URL=${1:-http://127.0.0.1:8080}

echo "[1/7] Health endpoint"
curl -fsS "$BASE_URL/up" >/dev/null

echo "[2/7] Landing SPA"
curl -fsS "$BASE_URL/" | grep -qi "Siaga Karta"

echo "[3/7] Portal SPA direct route"
curl -fsS "$BASE_URL/portal" | grep -qi "Siaga Karta"

echo "[4/7] Dashboard SPA direct route"
curl -fsS "$BASE_URL/dashboard" | grep -qi "Siaga Karta"

echo "[5/7] Nested SPA refresh route"
curl -fsS "$BASE_URL/dashboard/pelayanan" | grep -qi "Siaga Karta"

echo "[6/7] Public API"
curl -fsS -H 'Accept: application/json' "$BASE_URL/api/public/bootstrap" | grep -q 'ambulans_tersedia'

echo "[7/7] Unknown API must stay 404 and must NOT return SPA"
STATUS=$(curl -sS -o /tmp/siagakarta-api-404.out -w '%{http_code}' -H 'Accept: application/json' "$BASE_URL/api/__route_should_not_exist__")
[ "$STATUS" = "404" ] || { echo "Expected 404, got $STATUS" >&2; exit 1; }

echo "OK: health, SPA routes, nested refresh, public API, and API 404 isolation passed."
