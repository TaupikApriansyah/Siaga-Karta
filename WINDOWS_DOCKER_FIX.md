# Windows + Docker Vite Entrypoint Fix

## Root cause
Previous archives contained both `resources/js/App.jsx` and `resources/js/app.jsx`.
Windows normally uses a case-insensitive filesystem, so those two names collide during extraction.
The Docker Linux build is case-sensitive, so Vite could then fail to resolve `resources/js/app.jsx`.

## Fix in v1.3.2
- Main React component: `resources/js/SiagaKartaApp.jsx`
- Vite entrypoint: `resources/js/main.jsx`
- `vite.config.js` points to `resources/js/main.jsx`
- Blade `@vite` points to `resources/js/main.jsx`

No project files now depend on two filenames that differ only by letter case.
