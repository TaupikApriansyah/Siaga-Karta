# Revision Notes 2026-08-12

Paket ini merupakan hardening menyeluruh Siaga Karta.

## Auth

- Normalisasi username/email konsisten.
- Limiter login 3 lapis.
- Audit login gagal.
- Idle + absolute token expiry.
- Endpoint refresh session.
- Multi-tab sync dengan BroadcastChannel.
- Double dashboard fetch saat restore session dihapus.
- Logout revoke race diperbaiki.
- Trusted proxy wildcard dihapus.

## Role Karta dan pembayaran

- Role `karta` ditambahkan ke database, validation, permission, dashboard, dan UI.
- Karta hanya mendapat modul finance/payment/report yang relevan.
- QR pembayaran dapat upload/ganti/hapus.
- Nama bank, nomor rekening, dan nama pemilik dapat dikelola Karta/Admin.
- Landing warga menampilkan QR dan rekening aktif dari setting yang sama.

## Data integrity

- Idempotency UUID untuk laporan, infaq, dan transaksi manual.
- Status history untuk pelayanan dan transaksi.
- State transition pelayanan dibatasi backend.
- Saldo dihitung dari ledger verified.
- Verifikasi pengeluaran menolak saldo tidak cukup.
- Row lock dipakai pada operasi yang rawan race.
- Fingerprint NIK dipisah dari APP_KEY.

## Performa

- Pagination dan filter server-side pada pelayanan, finance, dan user.
- Index untuk query status/jadwal/finance.
- Candidate assignment memakai subquery, bukan conflict query berulang.
- Dashboard snapshot dibatasi.
- CSV memakai lazy chunk streaming.
- Live sync berbasis revision setiap 10 detik saat tab visible dan full refresh hanya saat data berubah.
- Notification center role-aware memakai signature/unread ringan pada endpoint sync.
- Slow-query profiling opsional tanpa mencatat query bindings.
- Scheduler harian memangkas token kedaluwarsa dan notifikasi lama agar tabel operasional tidak tumbuh tanpa batas.

## UX

- Field helper text pada form utama.
- Loading/submit lock.
- Dirty-form protection pada laporan warga dan input manual petugas.
- Error boundary mencegah white blank menjadi layar tanpa informasi.
- Modal penolakan pembayaran menggantikan browser prompt.
- Tabel memiliki aksi yang sesuai status.
- Kas menampilkan jenis pemasukan/pengeluaran.
- Landing page memakai animasi alur layanan.
- Detail pelayanan dan transaksi menampilkan status history.
- Bell notification center dapat membuka modul yang relevan dan mendukung read/read-all.

## Deployment

- Password seeder hardcoded dihapus.
- Seeder demo dilewati di production.
- `.env.docker.example` tidak berisi password production.
- `DATA_FINGERPRINT_KEY` diwajibkan pada Docker production.
- Static internal proxy address digunakan untuk profile Cloudflare.
- Production security headers ditambahkan.
- PHP upload limit diselaraskan dengan batas validasi QR/bukti pembayaran.
- Admin awal production dibuat dengan command interaktif, bukan password seed default.

## Validasi paket

Sebelum release final, paket diperiksa dengan PHP lint, JSX parser, shell syntax check, YAML parse, secret scan, dan source scan terhadap konfigurasi proxy/rate-limit lama. Full integration test/build tetap harus dijalankan pada mesin yang memiliki PHP `mbstring`, Composer dependencies, dan npm dependencies lengkap.
