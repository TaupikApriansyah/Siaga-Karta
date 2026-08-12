# Frontend dependency fix

Docker build previously failed because `@tailwindcss/vite@4.0.0` only accepts Vite 5.2 or 6, while this project uses `laravel-vite-plugin@2.0.0`, which requires Vite 7.

The compatible set used by this package is:

- `vite`: `7.0.7`
- `laravel-vite-plugin`: `2.0.0`
- `@vitejs/plugin-react`: `4.7.0`
- `tailwindcss`: `4.3.3`
- `@tailwindcss/vite`: `4.3.3`

Rebuild after changing dependencies:

```bash
docker compose build --no-cache --pull
docker compose up -d
```
