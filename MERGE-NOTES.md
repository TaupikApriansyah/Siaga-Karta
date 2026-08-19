# SIAGA KARTA - Merge PRD + UI/UX terbaru

Basis UI/UX: `SiagaKarta_fixed_refresh`.
Basis fitur: PRD SIAGA KARTA revisi 18 Agustus 2026.
Kanal pengaduan yang menggantikan WhatsApp: Gmail `ujanggimenk@gmail.com`.

## Perubahan inti

- Landing page dan dashboard mempertahankan bahasa visual navy/cyan dari UI terbaru.
- Alur operasional mengikuti Kelurahan -> Kecamatan -> Kota, dengan eskalasi langsung untuk Darurat.
- Tiket otomatis berformat `SKB-[Kecamatan]-[Tahun]-[Nomor]`.
- Kanal warga: Gmail, QR Code, Form Online.
- Form PRD: data pelapor, domisili, jenis aduan, uraian, lokasi/GPS, lampiran, pihak terdampak, persetujuan data.
- Prioritas Darurat/Prioritas/Reguler ditentukan petugas, bukan warga.
- Dashboard: total, dalam proses, selesai, darurat, perlu eskalasi, kategori, sebaran kecamatan, Top 5 kecamatan.
- Role: Kelurahan, Kecamatan, Kota, Admin.
- Seeder demo idempotent berdasarkan email unik untuk mencegah `Duplicate entry admin@siagakarta.local` saat rebuild.
- HTTP healthcheck dinonaktifkan khusus service scheduler karena scheduler menjalankan `php artisan schedule:work`, bukan Apache.

## Docker

Jalankan:

```powershell
docker compose down
docker compose up -d --build
docker compose ps
```

Atau gunakan smoke test:

```powershell
powershell -ExecutionPolicy Bypass -File .\scripts\docker-smoke.ps1
```

Target:

- database: Healthy
- app: Healthy
- scheduler: Up
- aplikasi: http://127.0.0.1:8090

Jika gagal:

```powershell
docker compose logs app --tail=200
```

Catatan: SMTP Gmail belum diaktifkan karena membutuhkan Gmail App Password/OAuth. Alamat kanal Gmail dan sumber aduan Gmail sudah aktif tanpa menyimpan password Gmail di source code.
