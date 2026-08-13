#!/usr/bin/env sh
set -eu
cd /var/www/html
mkdir -p storage/app/private storage/framework/cache storage/framework/sessions storage/framework/views bootstrap/cache
chown -R www-data:www-data storage bootstrap/cache

if [ "${1:-}" = "apache2-foreground" ]; then
  if [ -z "${APP_KEY:-}" ]; then
    echo "ERROR: APP_KEY wajib diisi dan harus persisten." >&2
    exit 1
  fi

  # Project production ini mengharuskan APP_KEY base64 Laravel 32-byte yang valid.
  if ! php -r '$k=getenv("APP_KEY"); if (!str_starts_with($k,"base64:")) exit(1); $v=base64_decode(substr($k,7), true); if ($v===false || strlen($v)!==32) exit(1);'; then
    echo "ERROR: APP_KEY tidak valid. Buat dengan: php artisan key:generate --show" >&2
    exit 1
  fi

  if [ -z "${DATA_FINGERPRINT_KEY:-}" ] || [ "${#DATA_FINGERPRINT_KEY}" -lt 32 ]; then
    echo "ERROR: DATA_FINGERPRINT_KEY wajib secret acak minimal 32 karakter dan harus persisten." >&2
    echo "Contoh generate: php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'" >&2
    exit 1
  fi

  if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    tries=0
    until php artisan migrate --force; do
      tries=$((tries+1))
      if [ "$tries" -ge 30 ]; then echo "Database belum siap setelah 30 percobaan." >&2; exit 1; fi
      echo "Menunggu database... ($tries/30)"; sleep 2
    done
  fi

  if [ "${DEMO_MODE:-false}" = "true" ]; then
    echo "DEMO_MODE aktif: menyinkronkan akun dan data demo..."
    php artisan db:seed --force
  fi

  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi
exec "$@"
