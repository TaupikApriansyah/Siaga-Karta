# SIAGA KARTA - Revision Demo 2026-08-19

Revision ini berfokus pada stabilitas create/update, master wilayah Bandung, role Kecamatan/Kelurahan, dan performa modul Pelayanan.

## Perubahan utama

1. Validasi
   - Menambahkan locale validasi Indonesia, termasuk pesan `min.string`.
   - Menambahkan fallback frontend agar key mentah `validation.*` tidak tampil kepada pengguna.
   - `APP_FALLBACK_LOCALE=en` sebagai fallback aman bila suatu key Indonesia belum tersedia.

2. Stabilitas create/update
   - Kegagalan audit log, notifikasi internal, atau revision counter tidak lagi membuat transaksi bisnis yang sudah berhasil terlihat sebagai HTTP 500.
   - Counter sinkronisasi `operations`, `finance`, `users`, dan `settings` dipastikan tersedia melalui migration.

3. Master wilayah Kota Bandung
   - Master terdiri dari 30 Kecamatan dan 151 Kelurahan.
   - Migration melakukan update/upsert sehingga database yang sudah sehat tidak perlu dihapus.
   - Akun Kecamatan memilih Kecamatan dari dropdown.
   - Akun Kelurahan memilih Kecamatan lalu Kelurahan dari dropdown bertingkat.

4. Hak akses wilayah
   - Kecamatan: dashboard, pelayanan, input laporan, validasi laporan, pengelolaan struktur lokal untuk Kelurahan di bawah Kecamatan tersebut.
   - Kelurahan: dashboard, pelayanan, input laporan, meneruskan laporan ke Kecamatan, dan mengelola struktur lokal wilayah sendiri.
   - Kota: akses lintas wilayah dan modul administrasi Kota.
   - Data laporan tetap disaring berdasarkan wilayah role, bukan hanya disembunyikan di UI.

5. Performa
   - Pelayanan menggunakan pagination 20 data per halaman dan hanya mengambil kolom tabel yang dibutuhkan.
   - Daftar Kelurahan pada form dimuat secara lazy, hanya saat form dibuka.
   - Public portal juga tidak memuat 151 Kelurahan sebelum form laporan dibuka.
   - Sinkronisasi berkala hanya mengirim event scope yang berubah. Dashboard penuh tidak lagi diambil pada setiap perubahan pelayanan ketika dashboard tidak sedang dibuka.
   - Interval sync staf menjadi 20 detik.
   - Dashboard tidak lagi mengirim list laporan/transaksi besar karena kedua modul memiliki endpoint pagination sendiri.

## Cara upgrade tanpa menghapus data

Gunakan cara ini jika database Docker sekarang sudah sehat dan data ingin dipertahankan:

```powershell
docker compose down --remove-orphans
docker compose up -d --build
docker compose ps
```

Jangan gunakan `docker compose down -v` untuk upgrade normal karena opsi `-v` menghapus volume database.

## Jika database lama masih rusak dari percobaan demo sebelumnya

Hanya untuk database demo yang memang boleh dihapus, jalankan:

```text
RESET-AND-START-DEMO.bat
```

Untuk startup normal berikutnya:

```text
START-DEMO.bat
```

Launcher melakukan health check aplikasi serta memverifikasi master 30 Kecamatan dan 151 Kelurahan.

## Demo

URL: `http://localhost:8080`

Akun demo:
- `kota` / `Rajawali21`
- `kecamatan` / `Rajawali21`
- `kelurahan` / `Rajawali21`
