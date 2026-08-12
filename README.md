# Siaga Karta

Siaga Karta adalah aplikasi pelayanan warga dengan frontend React dan backend Laravel 12. React berfungsi sebagai UI, sedangkan Laravel menangani autentikasi, validasi, database, penjadwalan ambulans, keuangan, upload privat, dan laporan.

## Fitur utama

### Pelayanan warga dan pengaduan

- Landing warga tetap memakai jumlah menu/kartu yang sama. Tidak ditambahkan kartu khusus BPJS atau bencana.
- Kartu `Layanan Warga` membuka satu form terpadu dengan kategori `Layanan Ambulans`, `Pengaduan BPJS`, atau `Laporan Bencana`.
- Pengaduan BPJS dan bencana masuk ke antrean `Pelayanan Warga` milik Karang Taruna.
- Kode pengaduan BPJS memakai prefix `BPJ`, sedangkan bencana memakai `BNC`.
- Pengaduan tidak melakukan assignment ambulans/driver. Petugas memproses lalu menyelesaikan pengaduan dari antrean yang sama.
- Admin dapat memverifikasi administrasi laporan yang sudah selesai.
- Laporan Pelayanan Warga PDF/CSV mencakup ambulans, BPJS, dan bencana dan langsung terunduh.

### Pelayanan ambulans

- Permintaan ambulans darurat dan terjadwal.
- Tracking laporan menggunakan kode acak.
- Upload KTP untuk layanan terjadwal ke private storage.
- Jadwal layanan memiliki `service_start_at` dan `service_end_at`.
- Durasi layanan terjadwal dapat dipilih 1 sampai 6 jam dari UI, dengan validasi backend 30 sampai 720 menit.
- Backend menolak penugasan bila interval ambulans overlap dengan penugasan lain.
- Driver memakai pemeriksaan konflik interval yang sama.
- Pemilihan unit dijalankan di dalam database transaction dan row lock untuk mengurangi risiko double booking akibat request bersamaan.
- Booking masa depan tidak langsung membuat status live ambulans `bertugas`.
- Saat penjemputan dimulai, unit menjadi `bertugas`; saat selesai, status disinkronkan kembali dengan layanan aktif lainnya.

Aturan overlap yang digunakan adalah:

```text
start_A < end_B AND end_A > start_B
```

Dua layanan dengan batas yang bersentuhan, misalnya layanan pertama selesai pukul 10:00 dan layanan berikutnya mulai pukul 10:00, tidak dianggap overlap.

### NIK dan data pribadi

- NIK 16 digit divalidasi di React dan Laravel.
- Paste/drop NIK diblokir di UI, tetapi backend tetap menjadi sumber validasi utama.
- NIK disimpan terenkripsi.
- Fingerprint HMAC digunakan untuk mendeteksi NIK yang sama tanpa query menggunakan plaintext.
- Nomor telepon warga disimpan terenkripsi.
- NIK plaintext tidak dikirim kembali ke UI admin/petugas.
- KTP berada di `storage/app/private`.

### Kas dan infaq QR

- Admin dapat mengunggah gambar QR Code pembayaran melalui dashboard.
- Admin dapat mengaktifkan/menonaktifkan infaq publik.
- Warga dapat membuka QR dari portal publik.
- Setelah membayar, warga hanya mengisi nama, WhatsApp, nominal, catatan opsional, dan upload gambar bukti pembayaran.
- Bukti pembayaran disimpan di private storage.
- Infaq warga masuk sebagai transaksi `pending`.
- Saldo tidak bertambah sampai Admin memverifikasi transaksi.
- Admin dapat melihat bukti, memverifikasi, atau menolak pembayaran.
- Nomor WhatsApp pembayar disimpan terenkripsi dan dashboard hanya menampilkan empat digit terakhir.

### Laporan

- Laporan pelayanan warga (ambulans, BPJS, dan bencana) dapat langsung di-download sebagai PDF/CSV.
- Laporan ambulans khusus tetap tersedia untuk kompatibilitas.
- Laporan keuangan/infaq dapat langsung di-download sebagai PDF oleh Admin.
- CSV UTF-8 tersedia untuk dibuka dengan Excel.
- Tombol PDF tidak lagi membuka halaman print baru.

### UI responsif

- Portal publik responsif untuk mobile, tablet, dan desktop.
- Dashboard memakai off-canvas sidebar pada mobile.
- Form menjadi satu kolom pada layar kecil.
- Tombol action menyesuaikan lebar layar.
- Tabel besar dibungkus horizontal scroll agar data tidak terpotong.
- Chat SiagaBot menyesuaikan lebar viewport mobile.
- Modal QR/infaq tetap dapat digunakan pada layar kecil.

## Instalasi non-Docker

Persyaratan minimum:

- PHP 8.2+
- Composer
- Node.js 20+
- npm
- PDO SQLite atau PDO MySQL

```bash
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Akun bootstrap development:

- Admin: `admin` / `AdminSiagaKarta!2026`
- Petugas: `petugas` / `PetugasSiagaKarta!2026`

**Wajib ganti password sebelum production.**

## Docker

Project sudah dilengkapi:

- `Dockerfile`
- `docker-compose.yml`
- `.dockerignore`
- `.env.docker.example`
- `docker/apache-vhost.conf`
- `docker/entrypoint.sh`
- service MySQL 8.4
- optional service `cloudflared`
- health check `/up`
- migration otomatis ketika container aplikasi start

Mulai:

```bash
cp .env.docker.example .env
```

Buat `APP_KEY` yang persisten:

```bash
printf 'base64:' && openssl rand -base64 32
```

Masukkan key tersebut ke `.env`, ganti seluruh password database, lalu:

```bash
docker compose build --pull
docker compose up -d
docker compose ps
curl http://127.0.0.1:8080/up
```

Port aplikasi dibind ke `127.0.0.1` secara default, bukan `0.0.0.0`.

Untuk detail Cloudflare baca `CLOUDFLARE_DEPLOY.md`.

## Cloudflare Tunnel

Laravel/PHP tetap berjalan pada container origin. Cloudflare Tunnel menghubungkan origin ke Cloudflare melalui `cloudflared`. Untuk Docker Compose ini, published application diarahkan ke service internal:

```text
http://app:80
```

Set `APP_URL=https://domain-anda` dan masukkan token tunnel ke `.env`, lalu:

```bash
docker compose --profile cloudflare up -d
```

Laravel dikonfigurasi untuk trusted proxy agar skema HTTPS dari reverse proxy dikenali saat aplikasi berada di belakang Cloudflare.

## Endpoint tambahan

Public:

- `GET /api/public/infaq`
- `GET /api/public/infaq/qr`
- `POST /api/public/infaq/payments`

Admin:

- `GET /api/infaq/settings`
- `POST /api/infaq/settings`
- `GET /api/transactions/{id}/proof`
- `POST /api/transactions/{id}/verify`
- `POST /api/transactions/{id}/reject`

Laporan:

- `GET /api/exports/pelayanan.pdf`
- `GET /api/exports/pelayanan.csv`
- `GET /api/exports/ambulans.pdf`
- `GET /api/exports/ambulans.csv`
- `GET /api/exports/keuangan.pdf`
- `GET /api/exports/keuangan.csv`

## Production checklist

- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL` harus memakai domain HTTPS production.
- APP_KEY harus persisten dan tidak boleh berubah setelah NIK/nomor telepon terenkripsi disimpan.
- Ganti password semua akun seed.
- Jangan expose MySQL ke Internet.
- Jangan expose `storage/app/private`.
- Backup volume database dan private storage.
- Jangan cache endpoint `/api/*` yang berisi data dinamis atau autentikasi.
- Gunakan kebijakan retensi untuk NIK, KTP, QR, dan bukti pembayaran.
- Test restore backup, bukan hanya membuat backup.

## Pemeriksaan source pada paket ini

- PHP lint seluruh backend: lolos.
- 41 API route didefinisikan pada `routes/api.php`; route SPA dibuat eksplisit pada `routes/web.php`.
- JSX/React parsing melalui TypeScript transpiler: lolos tanpa error syntax.
- Generator laporan PDF menghasilkan signature `%PDF-1.4`.
- Docker CLI tidak tersedia pada environment pembuatan, sehingga `docker compose build` tidak dapat dieksekusi di sini.
- Full `npm run build` tidak dapat dijalankan karena dependency Vite tidak terpasang di environment pembuatan. Dockerfile melakukan `npm install` pada build stage sebelum `npm run build`.
- Migrasi database perlu diuji pada mesin Docker/MySQL tujuan sebelum go-live.

## Landing Page Warga v1.3
Landing page publik menggunakan desain navy/blue responsif dari frontend referensi yang digabungkan ke `PublicView` SIAGA KARTA. Seluruh branding lama pada template diganti menjadi SIAGA KARTA. Tombol layanan terhubung ke API Laravel yang sama untuk ambulans darurat, penjadwalan anti-bentrok, pengaduan BPJS/bencana melalui form yang sama, pelacakan laporan, program sosial, infaq QR, upload bukti pembayaran, dan Portal Petugas.

Asset landing berada di `public/hero-ambulance.png` dan `public/siaga-karta-community.png`. Frontend menggunakan `framer-motion` untuk animasi ringan dan `lucide-react` untuk ikon.
