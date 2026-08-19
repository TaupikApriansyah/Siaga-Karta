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

  # Jangan mengulang migration sebagai mekanisme readiness. Pada MySQL, DDL dapat
  # ter-commit sebagian sehingga retry migration yang sama justru menghasilkan
  # error "table/column already exists" dan menutupi error pertama.
  if [ "${RUN_MIGRATIONS:-true}" = "true" ]; then
    echo "Menunggu koneksi database..."
    tries=0
    until php -r '
      $host=getenv("DB_HOST") ?: "db";
      $port=getenv("DB_PORT") ?: "3306";
      $db=getenv("DB_DATABASE") ?: "siagakarta";
      $user=getenv("DB_USERNAME") ?: "siagakarta";
      $pass=getenv("DB_PASSWORD") ?: "";
      try {
        $pdo=new PDO("mysql:host={$host};port={$port};dbname={$db};charset=utf8mb4", $user, $pass, [PDO::ATTR_TIMEOUT=>2]);
        $pdo->query("SELECT 1");
      } catch (Throwable $e) { exit(1); }
    '; do
      tries=$((tries+1))
      if [ "$tries" -ge 60 ]; then
        echo "ERROR: Database belum dapat dihubungi setelah 60 percobaan." >&2
        exit 1
      fi
      echo "Database belum siap... ($tries/60)"
      sleep 2
    done

    echo "Database siap. Menjalankan migration satu kali..."
    php artisan optimize:clear >/dev/null 2>&1 || true
    if ! php artisan migrate --force; then
      echo "ERROR: Migration gagal. Lihat error di atas; migration tidak akan di-retry otomatis." >&2
      echo "Untuk diagnosis: docker compose logs app --tail=200" >&2
      exit 1
    fi
  fi

  if [ "${DEMO_MODE:-false}" = "true" ]; then
    echo "DEMO_MODE aktif: menyinkronkan akun dan data demo..."
    if ! php artisan db:seed --force; then
      echo "ERROR: Seeder demo gagal. Lihat error di atas." >&2
      exit 1
    fi
  fi

  echo "Membangun cache Laravel..."
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
fi

exec "$@"
