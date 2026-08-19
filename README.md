# SIAGA KARTA

**Sistem Informasi Pelayanan Warga dan Monitoring Pengaduan Karang Taruna Kota Bandung**

SIAGA KARTA mengelola pengaduan warga melalui struktur Karang Taruna tingkat **Kelurahan → Kecamatan → Kota**. Warga tidak memerlukan akun internal. Setiap pengaduan ditempatkan pada Kelurahan tujuan, diverifikasi Kelurahan, divalidasi/cross-check oleh Kecamatan, lalu diteruskan ke tingkat Kota untuk monitoring dan tindak lanjut, termasuk rujukan ke OPD/instansi terkait.

## Struktur hak akses

| Tingkat | Hak akses utama |
|---|---|
| **Karang Taruna Kota** | Monitoring seluruh Kecamatan/Kelurahan, peta sebaran Kota Bandung, tindak lanjut laporan yang telah tervalidasi Kecamatan, rujukan OPD, operasional ambulans, keuangan, laporan, dan manajemen akun Kecamatan/Kelurahan. |
| **Karang Taruna Kecamatan** | Melihat pengaduan dalam Kecamatan sendiri dan melakukan **validasi / cross-check** terhadap pengaduan yang telah diajukan oleh Kelurahan. Tidak memiliki akses keuangan, manajemen pengguna, atau pengelolaan ambulans. |
| **Karang Taruna Kelurahan** | Menerima/mencatat pengaduan warga atau RT/RW, melakukan verifikasi awal, mengajukan ke Kecamatan, menangani perbaikan data, dan menyesuaikan jumlah RT/RW wilayah sendiri. |

Role internal yang digunakan hanya: `kota`, `kecamatan`, dan `kelurahan`.

## Alur pengaduan

```text
Warga / RT / RW
       ↓
Karang Taruna Kelurahan
(verifikasi awal dan kelengkapan data)
       ↓
Karang Taruna Kecamatan
(validasi dan cross-check)
       ↓
Karang Taruna Kota
(monitoring dan tindak lanjut)
       ↓
OPD / Instansi terkait bila diperlukan
```

Status workflow utama:

```text
menunggu_kelurahan
→ diajukan_kecamatan
→ diterima_kota
→ diteruskan_opd / selesai
```

Kecamatan juga dapat mengembalikan data ke Kelurahan (`perlu_perbaikan_kelurahan`) atau menolak pada tahap validasi (`ditolak_kecamatan`).

## Kategori dan prioritas

Kategori pengaduan:

1. Kesehatan
2. BPJS
3. Ambulans
4. Lansia / Disabilitas
5. Bantuan Sosial
6. Orang Terlantar
7. Anak & Keluarga
8. Data Sosial / Desil
9. Kebencanaan
10. Lainnya

Prioritas dipisahkan dari jenis layanan ambulans:

- `darurat`
- `prioritas`
- `reguler`

`type` (`darurat` / `terjadwal`) hanya dipakai untuk kategori **ambulans**. Field kondisi medis dan lokasi penjemputan juga hanya diwajibkan untuk ambulans. Kategori lain menggunakan isi/deskripsi pengaduan dan dapat menambahkan lokasi bila relevan.

## Kode pelacakan dan email warga

Setiap laporan baru menggunakan format:

```text
SKB-{KODE_KECAMATAN}-{TAHUN}-{NOMOR_URUT}
```

Contoh:

```text
SKB-ANDIR-2026-00001
```

Email warga wajib pada input publik maupun input manual oleh Kota/Kecamatan/Kelurahan. Setelah laporan tersimpan, sistem mengirim kode pelacakan melalui SMTP/Gmail. **WhatsApp tetap merupakan kanal/sumber pengaduan**, sedangkan Gmail/email digunakan untuk notifikasi kode pelacakan dan bukan sebagai sumber laporan.

## Dashboard Karang Taruna Kota

Dashboard Kota mempertahankan komponen dashboard yang sudah ada dan menambahkan monitoring berbasis peta:

- Leaflet.js + OpenStreetMap.
- GeoJSON batas Kelurahan Kota Bandung.
- Marker lokasi pengaduan dan marker clustering.
- Statistik Kota Bandung dari database.
- Statistik dan persentase kategori per Kelurahan.
- Filter kategori, prioritas, Kecamatan, Kelurahan, status, dan rentang tanggal.
- Detail Kelurahan saat wilayah diklik.
- Distribusi kategori dan Top 5 Kelurahan.
- Update tanpa refresh browser menggunakan mekanisme revision/sync yang sudah tersedia di project.
- Marker dibatasi dan di-cluster agar tetap ringan ketika jumlah data besar.

Endpoint khusus dashboard Kota:

```text
GET /api/dashboard/kota/map
GET /api/dashboard/kota/kelurahan/{region}
```

## Master wilayah

Migration membuat struktur awal:

- Kota Bandung
- Kecamatan Andir
- Kelurahan Dungus Cariang

Dungus Cariang memiliki nilai awal **11 RT dan 11 RW**. Nilai tersebut dapat disesuaikan oleh akun Kelurahan melalui dashboardnya sendiri.

Untuk memuat master Kecamatan dan Kelurahan Kota Bandung dari sumber API wilayah administratif, jalankan:

```bash
php artisan siagakarta:sync-bandung-regions
```

Perintah ini hanya menyinkronkan master wilayah dan **tidak membuat akun pengguna baru**.

## Akun awal/demo

Seeder hanya membuat tiga akun role sesuai skenario pilot apabila `DEMO_MODE=true`:

- `kota` — Karang Taruna Kota Bandung
- `kecamatan` — Karang Taruna Kecamatan Andir
- `kelurahan` — Karang Taruna Kelurahan Dungus Cariang

Password demo berasal dari `DEMO_PASSWORD`. Jangan aktifkan credential demo pada production.

Untuk membuat akun Kota production secara interaktif tanpa password default:

```bash
php artisan siagakarta:create-kota
```

Akun Kecamatan dan Kelurahan selanjutnya dibuat oleh role Kota melalui menu **Manajemen Pengguna**, dan wajib diikat ke wilayah dengan level yang sesuai.

## Konfigurasi email

Contoh `.env`:

```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=akun@gmail.com
MAIL_PASSWORD=APP_PASSWORD_GMAIL
MAIL_FROM_ADDRESS=akun@gmail.com
MAIL_FROM_NAME="SIAGA KARTA"
```

Gunakan App Password apabila akun Gmail menggunakan verifikasi dua langkah.

## Konfigurasi peta dan master wilayah

```env
BANDUNG_KELURAHAN_GEOJSON_URL=https://github.com/tryfatur/geojson-bandung/raw/refs/heads/master/3273-kota-bandung-level-kelurahan.json
BANDUNG_REGION_API_BASE_URL=https://wilayah.id/api
```

URL dapat diganti melalui `.env` tanpa mengubah source code.

## Instalasi / update deployment

```bash
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan siagakarta:sync-bandung-regions
npm install
npm run build
php artisan optimize:clear
php artisan config:cache
```

Pastikan konfigurasi database dan SMTP production sudah benar sebelum menjalankan perintah di atas.

## Sinkronisasi dashboard

Project tetap menggunakan mekanisme revision/sync yang sudah tersedia. Frontend memeriksa revision secara ringan setiap beberapa detik. Ketika `operations` berubah, dashboard mengambil data terbaru dan komponen peta/statistik ikut diperbarui tanpa reload browser. Dengan demikian tidak dibuat infrastruktur realtime kedua yang berpotensi bentrok dengan mekanisme existing.

## Keamanan dan akuntabilitas

- Permission diperiksa pada backend.
- Query laporan dibatasi berdasarkan wilayah role.
- Kota dapat memonitor seluruh wilayah.
- Kecamatan tidak dapat memvalidasi laporan di luar Kecamatan sendiri.
- Kelurahan tidak dapat mengakses laporan Kelurahan lain.
- Aksi Kota yang mengubah operasional hanya dapat dilakukan setelah laporan lolos validasi Kecamatan.
- Pembuatan/perubahan pengguna dan perubahan workflow tercatat dalam audit log.
- Revision tracking memicu pembaruan dashboard dan notifikasi.
- NIK/telepon/email warga menggunakan mekanisme perlindungan data yang sudah tersedia pada project.

## Verifikasi setelah deployment

1. Jalankan migration.
2. Sinkronkan master wilayah Bandung.
3. Konfigurasi SMTP/Gmail.
4. Login sebagai Kota, Kecamatan Andir, dan Kelurahan Dungus Cariang.
5. Buat pengaduan warga dengan email valid.
6. Pastikan kode `SKB-ANDIR-YYYY-00001` diterima melalui email.
7. Login Kelurahan dan ajukan ke Kecamatan.
8. Login Kecamatan dan validasi ke Kota.
9. Login Kota dan periksa marker/statistik peta tanpa refresh browser.
10. Pastikan role Kecamatan/Kelurahan tidak dapat mengakses keuangan, manajemen pengguna, atau operasi Kota yang tidak menjadi kewenangannya.
