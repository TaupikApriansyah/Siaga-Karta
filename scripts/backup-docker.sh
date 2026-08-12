#!/usr/bin/env sh
set -eu

umask 077
STAMP="$(date +%Y%m%d_%H%M%S)"
OUT_DIR="${1:-./backups/$STAMP}"
mkdir -p "$OUT_DIR"

if ! command -v docker >/dev/null 2>&1; then
  echo "Docker CLI tidak ditemukan." >&2
  exit 1
fi

printf '%s\n' "Membuat backup database..."
docker compose exec -T db sh -lc 'exec mysqldump --single-transaction --quick --lock-tables=false -u"$MYSQL_USER" -p"$MYSQL_PASSWORD" "$MYSQL_DATABASE"' \
  | gzip -9 > "$OUT_DIR/database.sql.gz"

printf '%s\n' "Membuat backup private storage..."
docker compose exec -T app sh -lc 'tar -C /var/www/html/storage/app -czf - .' > "$OUT_DIR/storage-app.tar.gz"

[ -s "$OUT_DIR/database.sql.gz" ] || { echo "Backup database kosong." >&2; exit 1; }
[ -s "$OUT_DIR/storage-app.tar.gz" ] || { echo "Backup storage kosong." >&2; exit 1; }

(
  cd "$OUT_DIR"
  sha256sum database.sql.gz storage-app.tar.gz > SHA256SUMS
)

printf '%s\n' "Backup selesai: $OUT_DIR"
printf '%s\n' "Uji restore secara berkala pada environment terpisah."
