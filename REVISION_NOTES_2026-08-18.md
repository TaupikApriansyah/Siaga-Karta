# Catatan Revisi SIAGA KARTA — 18 Agustus 2026

Revisi ini mengubah arsitektur pengaduan menjadi hierarki Karang Taruna Kota Bandung tanpa melakukan redesign menyeluruh terhadap aplikasi.

## Implementasi utama

- Role resmi: `kota`, `kecamatan`, `kelurahan`.
- Relasi wilayah: `Pengaduan → Kelurahan → Kecamatan → Kota`.
- Kota dapat memonitor seluruh Kecamatan/Kelurahan.
- Kecamatan berwenang melakukan validasi/cross-check laporan yang diajukan Kelurahan.
- Kelurahan menjadi penerima/verifikator pertama bagi laporan warga/RT/RW.
- Akun pengguna terikat ke master wilayah.
- Pilot akun: Kota Bandung, Kecamatan Andir, Kelurahan Dungus Cariang.
- Default struktur Dungus Cariang: 11 RT / 11 RW dan dapat diubah oleh role Kelurahan sendiri.
- 10 kategori pengaduan dan 3 level prioritas.
- Field ambulans dibuat kondisional; kategori non-ambulans tidak dipaksa mengisi kondisi medis atau lokasi penjemputan.
- Kode tiket baru: `SKB-ANDIR-2026-00001`.
- Email warga wajib untuk pengiriman kode pelacakan; WhatsApp tetap menjadi kanal pengaduan.
- Workflow Kelurahan → Kecamatan → Kota → OPD.
- Notifikasi internal diperjelas dan disesuaikan dengan wilayah penerima.
- Dashboard Kota memiliki peta Leaflet/OpenStreetMap, GeoJSON, marker clustering, statistik per Kelurahan, filter, chart, Top 5 Kelurahan, dan detail Kelurahan.
- Data peta/statistik menggunakan database, bukan dummy.
- Mekanisme sync/revision existing digunakan untuk pembaruan dashboard tanpa refresh browser.
- Endpoint map hanya dapat diakses role Kota.
- Keuangan, manajemen pengguna, ambulans, dan resource operasional Kota tidak tersedia untuk role Kecamatan/Kelurahan.

## Langkah deployment penting

```bash
php artisan migrate --force
php artisan siagakarta:sync-bandung-regions
npm install
npm run build
php artisan optimize:clear
php artisan config:cache
```

Isi konfigurasi SMTP/Gmail pada `.env` agar kode pelacakan benar-benar terkirim ke warga.
