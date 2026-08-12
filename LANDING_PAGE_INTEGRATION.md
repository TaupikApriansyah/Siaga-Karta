# SIAGA KARTA Landing Page Integration v1.2

Landing page publik SIAGA KARTA sekarang menggunakan desain navy/blue dari frontend referensi yang diberikan pengguna.

## Yang diganti
- `PublicView` lama diganti dengan landing page baru yang responsif.
- Branding template lama dihapus dan diganti menjadi `SIAGA KARTA`.
- Asset publik: `public/hero-ambulance.png` dan `public/siaga-karta-community.png`.

## Fitur publik yang tetap terhubung ke Laravel
- Ambulans darurat: `POST /api/public/reports`
- Ambulans terjadwal anti-bentrok: `POST /api/public/reports`
- Tracking laporan: `GET /api/public/reports/{code}`
- Statistik dan program sosial: `GET /api/public/bootstrap`
- Informasi QR infaq: `GET /api/public/infaq`
- Upload bukti infaq: `POST /api/public/infaq/payments`
- SiagaBot: `POST /api/public/bot`
- Portal Petugas: login Laravel API yang sama

## Responsiveness
Navbar berubah menjadi mobile menu pada layar kecil. CTA dibuat bertumpuk di mobile. Kartu layanan dan program memakai grid adaptif. Semua form publik dibuka dalam modal dengan scroll internal sehingga tetap dapat digunakan pada layar ponsel.

## Dependency
Landing memakai `framer-motion` yang dipin ke versi `12.43.0` untuk menghindari perubahan dependency tak terduga saat Docker build.
