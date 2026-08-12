# Security Notes

## Data pribadi

NIK dan nomor telepon merupakan data sensitif. Project ini mengenkripsi nilai tersebut sebelum penyimpanan. Hindari menambahkan NIK plaintext ke log, exception message, URL, analytics, atau frontend state yang tidak diperlukan.

## NIK anti copy paste

React mencegah event paste dan drop pada input NIK. Ini bukan boundary keamanan. Backend tetap memvalidasi setiap request karena client dapat dimodifikasi.

## KTP

KTP disimpan pada storage private. Jangan memindahkan folder KTP ke disk public. Endpoint akses KTP hanya tersedia untuk akun terautentikasi dengan role petugas atau admin dan aktivitas akses dicatat pada audit log.

## API token

Token login hanya dikirim sekali kepada frontend. Database hanya menyimpan SHA-256 token. Token berakhir setelah 12 jam. Untuk deployment dengan kebutuhan keamanan lebih tinggi, pertimbangkan Laravel Sanctum dengan HttpOnly cookie, CSRF protection, HTTPS, Content Security Policy, dan session hardening.

## Default account

Akun pada seeder hanya untuk bootstrap development. Ubah password dan, bila perlu, hapus kredensial seeder sebelum production.

## Penjadwalan ambulans

Status `tersedia` pada UI bukan satu-satunya kontrol penjadwalan. Backend memeriksa overlap `service_start_at` dan `service_end_at` di dalam transaksi database. Ambulans dan driver yang sudah memiliki interval aktif/terjadwal yang overlap tidak dapat ditugaskan kembali pada interval yang sama.

## Bukti infaq dan QR

QR infaq, bukti pembayaran, dan KTP disimpan pada private storage. Bukti infaq hanya dapat dibuka melalui endpoint Admin yang terautentikasi. Transaksi publik selalu masuk sebagai `pending`; upload gambar tidak pernah otomatis mengubah transaksi menjadi `verified`.

## Cloudflare / reverse proxy

Aplikasi mengaktifkan trusted proxies untuk deployment Cloudflare Tunnel. Docker Compose hanya mempublikasikan port origin ke loopback (`127.0.0.1`) agar origin tidak menjadi jalur publik yang dapat digunakan untuk memalsukan forwarded headers. Jika arsitektur deployment diubah dan origin diekspos langsung, tinjau kembali konfigurasi trusted proxy.
