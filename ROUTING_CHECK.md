# Routing Check - Siaga Karta v1.3

Expected production routes:

- `GET /` -> React landing warga.
- `GET /portal` -> React portal login.
- `GET /dashboard` -> React dashboard shell; token is revalidated against `/api/auth/me`.
- `GET /dashboard/*` and `/portal/*` -> React SPA on browser refresh.
- `/api/*` -> Laravel API only. Unknown API URLs must remain HTTP 404 and must never receive the SPA HTML.
- `GET /up` -> Laravel framework health endpoint used by Docker healthcheck.
- `/build/*` -> Vite production assets generated during Docker image build.
- `/storage/*` is excluded from the SPA fallback. Sensitive uploads are stored on the private local disk and are not exposed by a public storage symlink.

After deployment run:

```bash
./docker/smoke-test.sh http://127.0.0.1:8080
./docker/smoke-test.sh https://domain-anda.example
```

Cloudflare Tunnel origin must be `http://app:80` because `cloudflared` and `app` share the Docker network. The host port remains bound to `127.0.0.1` for local administration only.
