# Patch Docker Startup — 18 Agustus 2026

Patch ini memperbaiki kasus `siagakarta-app-1 is unhealthy` yang muncul setelah migration `2026_08_18_000720_add_region_hierarchy_and_report_workflow` sempat berjalan sebagian.

Perubahan:

- Migration `000720` sekarang recoverable/idempotent pada MySQL. Jika `regions` atau sebagian kolom sudah terbuat, migration akan melanjutkan bagian yang belum selesai dan tidak mencoba membuat ulang objek yang sama.
- Entrypoint tidak lagi memakai `php artisan migrate` sebagai loop pengecekan database. Database ditunggu dengan koneksi PDO, lalu migration dijalankan satu kali sehingga error asli tidak tertutup oleh error `already exists`.
- Healthcheck Docker diberi `start-period` 180 detik agar first boot yang menjalankan migration, seeder demo, dan Laravel cache tidak dinilai `unhealthy` terlalu cepat.
- Seeder demo tetap aktif bila `DEMO_MODE=true` dan akun `kota`, `kecamatan`, `kelurahan` tetap menggunakan `DEMO_PASSWORD`.

## Setelah mengganti source dengan versi patch

Untuk tahap demo/testing tanpa data penting, lakukan reset database satu kali:

```powershell
docker compose down -v
docker compose up -d --build
```

Lalu cek:

```powershell
docker compose ps
docker compose logs app --tail=200
```

Akses lokal mengikuti `APP_PORT` di `.env`. Jika `APP_PORT=8080`:

```text
http://localhost:8080
```

Jika database sudah berisi data penting, jangan gunakan `down -v`. Cukup:

```powershell
docker compose down
docker compose up -d --build
```

Migration patch dirancang untuk melanjutkan keadaan DDL parsial tanpa menghapus data.
