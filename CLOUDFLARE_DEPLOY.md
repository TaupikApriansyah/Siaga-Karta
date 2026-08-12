# Deploy Siaga Karta dengan Docker + Cloudflare Tunnel

Arsitektur produksi yang dipakai:

`Internet -> Cloudflare -> Cloudflare Tunnel -> container app (Apache + PHP/Laravel + React build) -> MySQL`

Laravel tetap berjalan di container PHP. Cloudflare menjadi edge proxy/TLS dan tunnel, bukan runtime PHP.

## 1. Persiapan

```bash
cp .env.docker.example .env
```

Buat APP_KEY persisten sekali saja:

```bash
printf 'base64:' && openssl rand -base64 32
```

Salin hasilnya ke `APP_KEY=`. Ganti password MySQL dengan password acak yang kuat. Set `APP_URL=https://domain-anda`.

## 2. Jalankan lokal terlebih dahulu

```bash
docker compose build --pull
docker compose up -d
docker compose ps
curl http://127.0.0.1:8080/up
```

Aplikasi sengaja hanya dibind ke `127.0.0.1`, sehingga origin tidak terekspos langsung ke Internet.

Untuk production baru, jangan seed akun demo. Buat Admin pertama secara interaktif setelah container sehat:

```bash
docker compose exec app php artisan siagakarta:create-admin --name="Administrator" --email=admin@example.com --username=admin
```

Password diminta tersembunyi di terminal dan tidak disimpan di command history.

## 3. Cloudflare Tunnel

Di Cloudflare Zero Trust buat **remotely-managed Tunnel**, lalu tambahkan Public Hostname untuk domain aplikasi. Service/origin yang dituju dari tunnel adalah:

```text
http://app:80
```

Salin tunnel token ke `CLOUDFLARE_TUNNEL_TOKEN` dalam `.env`, lalu jalankan:

```bash
docker compose --profile cloudflare up -d
```

Cek log:

```bash
docker compose logs -f cloudflared app scheduler
```

## 4. Setting Cloudflare yang disarankan

- SSL/TLS mode: Full (strict) bila origin memakai TLS sendiri. Untuk Cloudflare Tunnel ke `http://app:80`, koneksi publik tetap HTTPS dan tunnel terenkripsi.
- Jangan cache `/api/*`.
- Jangan cache response yang memiliki `Authorization` atau data admin.
- Aktifkan WAF/rate limiting sesuai kebutuhan, tetapi Laravel tetap mempertahankan rate limiter internal.
- Batasi ukuran upload sesuai kebijakan. Aplikasi membatasi KTP 4 MB dan bukti infaq/QR 5 MB.

## 5. Update aplikasi

```bash
git pull
docker compose build --pull
docker compose --profile cloudflare up -d
```

Migration otomatis berjalan saat container `app` start. Service `scheduler` menjalankan maintenance Laravel harian setelah app sehat. Backup database dan volume storage sebelum update produksi.

## 6. Backup penting

Backup kedua volume berikut secara rutin:

- `siagakarta_mysql`
- `siagakarta_storage`

`siagakarta_storage` berisi KTP privat, QR infaq, dan bukti pembayaran. Jangan pindahkan folder ini ke direktori public.

## Routing yang diverifikasi

Aplikasi memakai satu origin Laravel untuk UI dan API:

- `/` -> landing warga React
- `/portal` -> portal login petugas/admin React
- `/dashboard` -> dashboard React, session token diverifikasi kembali ke `/api/auth/me`
- `/api/*` -> API Laravel dan tidak pernah ditangkap SPA fallback
- `/up` -> health check Laravel

`routes/web.php` memakai `Route::view` agar SPA fallback kompatibel dengan `php artisan route:cache`.

Karena `cloudflared` berada di network Docker yang sama dengan service `app`, published application route di Cloudflare Tunnel harus diarahkan ke `http://app:80`, bukan `http://127.0.0.1:8080`. `127.0.0.1` dari dalam container cloudflared menunjuk ke container cloudflared sendiri.

Token tunnel diteruskan lewat environment `TUNNEL_TOKEN`, bukan ditaruh di command line container.

Setelah deploy, jalankan smoke test:

```bash
./docker/smoke-test.sh http://127.0.0.1:8080
```

Lalu uji juga domain Cloudflare:

```bash
./docker/smoke-test.sh https://domain-anda.example
```

## Build asset

Dockerfile tidak bergantung pada `node_modules`, `vendor`, atau `public/build` dari host. Dependency Composer dan Vite dibangun ulang di build stage image. Gunakan:

```bash
docker compose build --pull
```

lalu:

```bash
docker compose --profile cloudflare up -d
```

