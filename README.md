# Siaga Karta

Siaga Karta adalah aplikasi Laravel 12 + React untuk pelayanan warga, ambulans, pengaduan sosial, kas, infaq, dan administrasi Karang Taruna. Backend menjadi sumber kebenaran untuk autentikasi, otorisasi, validasi, status layanan, saldo, penjadwalan, audit, dan file privat. React menangani pengalaman pengguna dan live sync ringan.

## Role dan batas akses

| Role | Akses utama |
|---|---|
| Admin | Pelayanan, ambulans, program, keuangan, pembayaran, user, laporan, health check |
| Petugas | Pelayanan warga, pengaduan, penugasan ambulans, laporan operasional |
| Karta | Kas, transaksi, verifikasi pembayaran, QR, rekening, laporan keuangan |

Role pada UI hanya untuk navigasi. Setiap endpoint sensitif tetap diperiksa lagi melalui permission middleware di backend.

## Perubahan keamanan dan reliabilitas

- Username dan email dinormalisasi `trim + lowercase` pada penyimpanan dan login.
- Database unique constraint tetap menjadi pengaman terakhir terhadap duplikasi/race condition.
- Login memakai rate limit berlapis: kombinasi IP + identitas, identitas, dan IP global.
- Proxy tidak lagi dipercaya dengan wildcard. `TRUSTED_PROXIES` harus berisi proxy yang benar-benar digunakan.
- Login gagal dicatat ke audit log dengan reason internal tanpa membocorkan reason tersebut ke client.
- API token memiliki idle expiry dan absolute expiry. Frontend memperpanjang idle session saat aktif dan memberi peringatan sebelum absolute expiry.
- Token tetap berada di `sessionStorage`. Sinkronisasi tab memakai `BroadcastChannel`, bukan token persisten di `localStorage`.
- Logout menunggu request revoke selesai sebelum token lokal dibersihkan.
- Password change atau deactivation user mencabut seluruh token aktif user tersebut.
- Production memakai CSP, HSTS saat HTTPS, `X-Content-Type-Options`, `X-Frame-Options`, `Referrer-Policy`, COOP, dan CORP.
- Setiap request mendapat `X-Request-Id` untuk korelasi audit.

## Pelayanan warga

- Satu form untuk ambulans, pengaduan BPJS, dan bencana.
- Form memiliki helper text, validasi browser + backend, loading lock, idempotency UUID, dan proteksi data belum tersimpan.
- NIK 16 digit divalidasi backend, disimpan terenkripsi, dan pencarian memakai HMAC fingerprint.
- Fingerprint memakai `DATA_FINGERPRINT_KEY` terpisah dari `APP_KEY`, sehingga rotasi key enkripsi tidak merusak lookup NIK.
- KTP layanan terjadwal dan file sensitif disimpan di private storage.
- Detail petugas menampilkan NIK ter-mask, bukan NIK penuh.
- Riwayat perubahan status tersimpan dan tampil pada detail pelayanan.

State ambulans yang diizinkan:

```text
menunggu -> diproses -> dijemput -> selesai
     \          \
      -> ditolak -> ditolak
```

Transisi yang tidak sah ditolak backend, walaupun request dibuat manual dari luar UI.

## Penjadwalan ambulans

- Jadwal memakai interval `service_start_at` dan `service_end_at`.
- Konflik menggunakan aturan `start_A < end_B AND end_A > start_B`.
- Pencarian kandidat memakai subquery terindeks, bukan query konflik berulang per unit/driver.
- Penugasan diserialisasi singkat melalui mutex row `system_revisions` dan row lock untuk mencegah double assignment pada request bersamaan.
- Unit/driver dilepas kembali secara konsisten saat layanan selesai atau ditolak.

## Kas, QR, dan rekening Karta

Role `karta` dan Admin dapat:

- melihat transaksi pemasukan/pengeluaran;
- menambah transaksi internal;
- melihat bukti pembayaran privat;
- memverifikasi atau menolak transaksi pending;
- mengunggah, mengganti, atau menghapus QR pembayaran;
- mengatur nama bank, nomor rekening, dan nama pemilik rekening;
- mengaktifkan kanal pembayaran publik;
- mengunduh laporan keuangan.

Saldo tidak dapat diedit manual. Saldo berasal dari ledger transaksi `verified`:

```text
saldo = total pemasukan verified - total pengeluaran verified
```

Pengeluaran tidak dapat diverifikasi bila saldo terverifikasi tidak mencukupi. Transaksi tidak memiliki endpoint hard delete. Perubahan status dicatat pada history dan audit log.

Upload QR memakai urutan aman: validasi -> simpan file baru -> commit database -> hapus file lama. Jadi kegagalan validasi atau database tidak membuat setting menunjuk file yang sudah hilang.

## Performa dan live sync

- Tabel pelayanan dan transaksi memakai pagination 25 baris dan filter server-side.
- Tabel user juga dipaginasi.
- Dashboard hanya memuat snapshot terbatas dan aggregate yang memang ditampilkan.
- Relasi laporan memakai eager loading untuk menghindari N+1.
- Kolom status, jadwal, foreign key, dan filter utama memiliki index database.
- CSV besar di-stream per chunk agar tidak memuat seluruh dataset ke RAM.
- CSV dinetralisasi dari spreadsheet formula injection.
- Public bootstrap memakai cache singkat dan di-invalidate setelah perubahan relevan.
- Live sync memakai revision counter kecil. Saat tab terlihat, frontend mengecek revision setiap 10 detik. Dashboard/tabel hanya di-fetch ulang jika revision berubah. Aksi dari user sendiri memicu refresh langsung.
- Notification center tersedia untuk Admin, Petugas, dan Karta. `/sync` hanya membawa signature + unread count; daftar notifikasi di-fetch ulang hanya ketika berubah.
- Slow-query profiler dapat diaktifkan dengan `SLOW_QUERY_MS`. SQL bindings sengaja tidak dicatat agar parameter sensitif tidak masuk log.

Pendekatan ini sengaja tidak memakai polling seluruh dataset dan tidak menambah WebSocket/Redis sebagai dependency wajib. Sinkronisasi saat ini bersifat near-real-time, bukan push sub-second. Jika deployment nanti benar-benar membutuhkan push sub-second, Laravel Reverb dapat ditambahkan tanpa mengubah sumber kebenaran database.

## Instalasi development

Persyaratan:

- PHP 8.2+ dengan `mbstring`, PDO SQLite atau PDO MySQL
- Composer
- Node.js 20+
- npm
- PHP upload limit minimal 6 MB (`upload_max_filesize`) dan `post_max_size` minimal 12 MB untuk QR/bukti pembayaran. Dockerfile paket ini sudah mengaturnya.

```bash
cp .env.example .env
php artisan key:generate
```

Generate secret fingerprint yang stabil:

```bash
openssl rand -hex 32
```

Masukkan hasilnya ke `DATA_FINGERPRINT_KEY`, lalu:

```bash
php artisan migrate --seed
npm install
npm run build
php artisan serve
```

Seeder development tidak lagi mempunyai password default hardcoded. Saat akun development pertama dibuat, password acak akan dicetak di terminal. Jika ingin menentukan sendiri, isi `SEED_ADMIN_PASSWORD`, `SEED_PETUGAS_PASSWORD`, dan `SEED_KARTA_PASSWORD` sebelum menjalankan seed. Demo seeder dilewati di environment production.

## Upgrade database lama

Migration hardening menormalisasi username/email lama. Jika database sudah memiliki collision seperti `Admin` dan `admin`, migration akan berhenti dengan pesan yang jelas. Selesaikan duplikasi secara manual terlebih dahulu. Sistem sengaja tidak memilih atau menggabungkan akun secara otomatis.

Setelah upgrade:

```bash
php artisan migrate
php artisan optimize:clear
npm install
npm run build
```

## Docker production

```bash
cp .env.docker.example .env
```

Isi minimal:

- `APP_KEY`
- `DATA_FINGERPRINT_KEY` minimal 32 karakter dan persisten
- password database yang unik
- `APP_URL=https://...`
- `TRUSTED_PROXIES` sesuai proxy yang benar

Kemudian:

```bash
docker compose build --pull
docker compose up -d
docker compose ps
```

Container aplikasi hanya dipublish ke `127.0.0.1` secara default. Profile Cloudflare memakai IP internal tetap untuk `cloudflared`, sehingga Laravel tidak perlu mempercayai semua proxy.

Untuk deployment production baru, buat Admin pertama secara interaktif setelah container aktif:

```bash
docker compose exec app php artisan siagakarta:create-admin --name="Administrator" --email=admin@example.com --username=admin
```

Password diminta secara tersembunyi dan tidak menjadi argument command. Setelah itu, akun Petugas/Karta dapat dibuat dari menu Manajemen User.

Backup Docker dapat dibuat dengan:

```bash
./scripts/backup-docker.sh
```

Script menyimpan dump database, backup `storage/app`, dan checksum SHA-256 dengan permission ketat. Jadwalkan dari host menggunakan mekanisme scheduler server dan selalu uji restore pada environment terpisah. Container `scheduler` menjalankan Laravel Scheduler untuk maintenance ringan, termasuk prune token kedaluwarsa dan notifikasi lama. Audit log tidak dihapus otomatis.

## Rotasi key

`APP_KEY` melindungi data terenkripsi. Gunakan `APP_PREVIOUS_KEYS` sesuai mekanisme Laravel ketika melakukan rotasi bertahap.

`DATA_FINGERPRINT_KEY` adalah secret terpisah dan sebaiknya tidak dirotasi rutin. Jika memang harus diganti, setelah konfigurasi key baru gunakan:

```bash
php artisan siagakarta:rekey-fingerprints
```

Pastikan data masih dapat didekripsi dengan current/previous APP key sebelum menjalankan perintah tersebut.

## Pemeriksaan dan test

Regression test tambahan tersedia untuk:

- login case-insensitive;
- audit login gagal;
- login rate limit;
- permission role Karta terhadap finance vs data warga.

Jalankan:

```bash
php artisan test
npm run build
```

Pada environment pembuatan paket ini, lint PHP dan parser JSX dapat dijalankan. Full Laravel test membutuhkan PHP `mbstring`, dan full Vite build membutuhkan dependency npm terpasang.

Baca `SECURITY.md` dan `REVISION_NOTES_2026-08-12.md` sebelum deployment production.
