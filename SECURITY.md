# Security Notes

## Secret yang tidak boleh masuk repository atau ZIP distribusi

- `.env`
- `APP_KEY`
- `DATA_FINGERPRINT_KEY`
- password database
- token Cloudflare Tunnel
- database production
- `storage/app/private` production
- log production yang mengandung metadata operasional

Gunakan `.env.example` atau `.env.docker.example` sebagai template.

## Authentication

Username dan email bersifat case-insensitive pada domain Siaga Karta. Nilai disimpan dalam bentuk lowercase setelah trim. Password tidak pernah dicatat ke audit log.

Login dilindungi tiga limiter selama 60 detik:

1. 6 percobaan untuk pasangan IP + identitas.
2. 12 percobaan untuk identitas yang sama lintas IP.
3. 30 percobaan untuk satu IP lintas identitas.

Pesan client tetap generik. Audit server membedakan `not_found`, `bad_password`, `inactive`, dan `rate_limited`.

## Token

Bearer token disimpan sebagai SHA-256 hash di database dan plaintext hanya berada pada `sessionStorage` browser. Idle expiry default 60 menit. Absolute expiry default 12 jam. Frontend melakukan refresh idle session setiap 10 menit hanya saat tab aktif. Absolute lifetime tidak diperpanjang tanpa batas.

## Trusted proxy

Jangan gunakan:

```env
TRUSTED_PROXIES=*
```

Origin sebaiknya tidak dipublish ke Internet. Untuk Docker + profile Cloudflare bawaan, gunakan IP internal `cloudflared` yang sudah ditentukan pada contoh environment.

## Private uploads

KTP dan bukti pembayaran berada pada local/private storage. File diberikan melalui endpoint yang memerlukan permission, bukan melalui `/storage` publik. Validasi meliputi image MIME, ekstensi yang diizinkan, ukuran, dimensi, dan filename acak.

## Finance integrity

- Saldo dihitung dari transaksi verified.
- Pengeluaran memakai row lock + mutex finance sebelum pemeriksaan saldo.
- Status transaksi hanya `pending -> verified` atau `pending -> rejected`.
- Bukti pembayaran duplikat dicegah dengan SHA-256 file hash.
- Request finansial sensitif memakai idempotency UUID.
- Tidak ada endpoint hard delete transaksi.

## Audit

Audit menyimpan actor, request ID, action, subject, metadata relevan, old/new values, IP, user-agent, dan timestamp. Jangan menambahkan plaintext password, NIK, KTP, atau token ke metadata audit.

## Production baseline

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://domain.example
```

Wajib HTTPS. Backup database dan private storage harus dibuat bersama dan restore harus diuji. Endpoint admin `/api/system/health` dapat dipakai untuk pemeriksaan database, storage, dan queue tanpa membuka detail sensitif ke publik.


## Notification isolation

Notifikasi internal dibuat per user aktif sesuai role. Endpoint baca/read-all selalu memeriksa user pemilik notifikasi. Notifikasi hanya berisi ringkasan operasional dan tidak menyimpan plaintext NIK, nomor telepon penuh, token, atau path file privat.

## Query profiling dan retensi

`SLOW_QUERY_MS` dapat digunakan untuk mendeteksi query lambat. Logger hanya menulis SQL template dan durasi, tanpa bindings. Scheduler harian memangkas token kedaluwarsa serta notifikasi lama. Audit log sengaja tidak dipangkas otomatis agar kebijakan retensinya dapat ditentukan organisasi.
