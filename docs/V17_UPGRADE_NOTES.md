# SIAGA KARTA V17 Upgrade

- Pengaduan diarahkan langsung ke Kota. Kecamatan hanya monitoring.
- Ambulans memiliki status operasional dan endpoint ketersediaan.
- Ditambahkan dukungan koordinat terakhir ambulans untuk tracking realtime.
- Peta tetap berjalan walau GeoJSON gagal dimuat.
- Struktur tetap kompatibel mobile, tablet, dan desktop.

Endpoint tambahan:
- PATCH /api/ambulances/{id}/location
- GET /api/ambulances/{id}/location
