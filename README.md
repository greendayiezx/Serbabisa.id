# Tugasin

"Suruh siapa aja, kerjain apa aja" — platform jasa on-demand serba bisa. Lihat [PRD.md](PRD.md) untuk detail produk lengkap (persona, scope, roadmap, KPI).

Repo ini berisi dua proyek terpisah mengikuti stack di PRD bab 8.1:

- [`backend/`](backend/) — Laravel 11 + PHP 8.3, REST API, autentikasi Sanctum (token-based).
- [`frontend/`](frontend/) — Vue 3 + TypeScript (Vite), Pinia, Vue Router, Tailwind CSS 4, PWA.

## Status

Fase 1 (Design & Arsitektur) — repo scaffolding selesai: migrasi & model DB mengikuti ERD di PRD bab 8.4, skeleton route API mengikuti daftar functional requirement di PRD bab 6, dan struktur frontend dasar (auth, routing berbasis role, store per domain). Implementasi logika bisnis penuh (bidding, chat real-time, payment Midtrans, dsb.) adalah pekerjaan Fase 2 (Sprint 1-6) sesuai roadmap PRD bab 10.

## Menjalankan secara lokal

### Backend

```bash
cd backend
composer install
cp .env.example .env   # sudah ada; DB_CONNECTION=sqlite untuk dev lokal
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

API akan berjalan di `http://127.0.0.1:8000/api`. Endpoint `GET /api/ping` untuk healthcheck cepat.

### Frontend

```bash
cd frontend
npm install
cp .env.example .env   # atur VITE_API_BASE_URL jika backend tidak di port default
npm run dev
```

## Struktur Backend (`backend/app`)

- `Models/` — entitas ERD: `User`, `MitraProfile`, `Category`, `Task`, `Bid`, `Message`, `Payment`, `Wallet`, `PayoutRequest`, `Review`, `Dispute`.
- `Http/Controllers/Api/` — controller per modul (Auth, Category, Task, Bid, Message, Payment, Wallet, PayoutRequest, Review, Dispute) + `Api/Admin/` untuk dashboard & manajemen user.
- `Http/Middleware/EnsureUserHasRole.php` — guard peran (`customer` / `mitra` / `admin`) di sisi API, alias `role:` di `routes/api.php`.
- `routes/api.php` — daftar endpoint dikelompokkan per FR sesuai PRD bab 6.
- `database/seeders/CategorySeeder.php` — 6 kategori jasa tetap dari PRD bab 3.1.

Auth memakai Sanctum personal access token (Bearer), bukan cookie SPA session — cocok untuk frontend & backend yang di-deploy di domain terpisah (Vercel/Netlify + Railway/Render) sesuai PRD bab 8.1.

## Struktur Frontend (`frontend/src`)

- `stores/` — Pinia store per domain: `auth`, `task`, `chat`, `wallet`.
- `router/` — route guard berbasis role, redirect ke login bila belum terautentikasi.
- `api/client.ts` — instance Axios dengan interceptor token & auto-redirect saat 401.
- `layouts/AppLayout.vue` — shell mobile-first dengan bottom navigation (PRD bab 9).
- `views/` — dipisah per peran: root untuk auth bersama, `customer/`, `mitra/`, `admin/`.

## Testing & CI/CD

Repo ini punya pipeline **GitHub Actions** ([`.github/workflows/ci.yml`](.github/workflows/ci.yml))
yang berjalan otomatis tiap push/PR ke `main`/`develop`: test + code style backend,
serta typecheck + unit test + build frontend. Contoh deploy staging ada di
[`.github/workflows/deploy-staging.yml`](.github/workflows/deploy-staging.yml).

Menjalankan test secara lokal:

```bash
cd backend  && php artisan test        # PHPUnit (Feature: Auth, Category, HealthCheck)
cd frontend && npm test                # Vitest (unit test Pinia store)
```

Panduan belajar CI/CD untuk QA bertahap: [`docs/CICD-QA-LEARNING.md`](docs/CICD-QA-LEARNING.md).

## Yang belum dikerjakan (lihat roadmap PRD bab 10)

- Business logic penuh di controller (saat ini sebagian besar masih resource-stub kosong; `Auth` dan `Category` sudah fungsional).
- Integrasi Midtrans, escrow release, payout processing.
- Chat real-time (Laravel Reverb/Pusher) — endpoint REST sudah ada, belum ada broadcasting.
- Radius search berbasis lokasi Mitra (query jarak belum diimplementasi di `TaskController`).
- Ikon PWA (`public/pwa-192x192.png`, `public/pwa-512x512.png`) — masih placeholder path, belum ada asetnya.
- Unit test, API test (Postman/Newman), E2E test (Fase 3 QA di roadmap).
- CI/CD, containerization, deployment ke staging/production (Fase 4 DevOps).
