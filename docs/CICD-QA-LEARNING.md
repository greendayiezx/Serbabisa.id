# Belajar DevOps CI/CD untuk QA — di Project Tugasin

Dokumen ini adalah **peta belajar berbasis praktik**. Semua contohnya sudah
ada (atau akan Anda tambahkan) di repo ini, jadi Anda belajar sambil melihat
pipeline benar-benar hijau/merah di tab **Actions** GitHub.

Stack yang kita pakai:

- **Backend** — Laravel 11 / PHP 8.3, test dengan **PHPUnit**, gaya kode **Pint**
- **Frontend** — Vue 3 / TypeScript / Vite, test dengan **Vitest**
- **CI/CD** — **GitHub Actions** (`.github/workflows/`)

---

## Peta besar: apa itu CI/CD untuk QA?

> **CI (Continuous Integration):** setiap perubahan kode otomatis dibangun & diuji.
> **CD (Continuous Delivery/Deployment):** perubahan yang lolos uji otomatis dirilis.

Sebagai QA, tugas Anda bergeser dari "menguji manual di akhir" menjadi
**"merancang gerbang kualitas otomatis"** yang menjaga setiap PR. Alurnya:

```
push / PR  ──▶  CI: build + test + lint + audit  ──▶  merah? tolak merge
                                                  └─▶  hijau? boleh merge
merge ke develop ──▶ CD: deploy ke staging ──▶ smoke test ──▶ (promote ke prod)
```

---

## Tahap 1 — Continuous Integration (fondasi)

**Tujuan:** tidak ada kode masuk tanpa lolos test otomatis.

Sudah tersedia di repo: [`.github/workflows/ci.yml`](../.github/workflows/ci.yml)
menjalankan dua job paralel setiap push/PR ke `main`/`develop`:

| Job | Langkah | Yang diuji |
|-----|---------|-----------|
| `backend` | Pint `--test`, `php artisan test`, `composer audit` | gaya kode, PHPUnit, CVE |
| `frontend`| `vue-tsc`, `vitest`, `vite build`, `npm audit` | tipe, unit test, build, CVE |

**Latihan:**
1. Buka tab **Actions** di GitHub setelah push — lihat pipeline jalan.
2. Sengaja rusakkan satu test, push, lihat PR jadi merah.
3. Aktifkan **branch protection**: Settings → Branches → Add rule → require
   status check **"CI OK"** sebelum merge.

**Konsep kunci:** job vs step, caching dependency, matrix, `concurrency`,
required status check, fail-fast.

---

## Tahap 2 — Layered testing (jantung QA)

Prinsip **Test Pyramid**: banyak test cepat & murah di bawah, sedikit test
lambat & mahal di atas.

```
        /\        E2E (Playwright/Cypress)      ← paling sedikit
       /  \       Integration / API (Newman)
      /____\      Unit (PHPUnit / Vitest)       ← paling banyak
```

### 2a. Unit test — SUDAH ADA contohnya
- Backend: `backend/tests/Feature/*` (mis. `AuthTest`, `CategoryTest`).
  Teknik: `RefreshDatabase`, factory, `assertJsonStructure`, `assertDatabaseHas`.
- Frontend: `frontend/tests/unit/auth.store.spec.ts`.
  Teknik: mock API dengan `vi.mock`, uji Pinia store terisolasi.

Jalankan lokal:
```bash
cd backend  && php artisan test
cd frontend && npm test
```

### 2b. API / contract test (langkah berikutnya)
Buat koleksi **Postman**, ekspor, lalu jalankan di CI dengan **Newman**:
```bash
newman run tests/postman/tugasin.postman_collection.json \
  --environment tests/postman/staging.postman_environment.json
```
Tujuan: memastikan kontrak API (status code, skema JSON) tidak berubah diam-diam.

### 2c. E2E test (langkah lanjut)
Pasang **Playwright** di frontend, tulis skenario user asli
(register → login → buat tugas). Chromium sudah tersedia di runner CI.
```bash
npm create playwright@latest
npx playwright test
```

**Konsep kunci:** unit vs integration vs E2E, test fixture, mocking,
flaky test, data isolation.

---

## Tahap 3 — Quality gates (menaikkan standar)

Ubah CI dari "sekadar jalan" menjadi "menjaga ambang mutu":

- **Coverage minimum** — `vitest run --coverage` (sudah ada script
  `npm run test:coverage`) & PHPUnit `--coverage-text`; gagalkan bila di bawah target.
- **Static analysis** — tambah **Larastan/PHPStan** (backend) & **ESLint** (frontend).
- **Security** — naikkan `composer audit` / `npm audit` dari non-blocking jadi blocking.
- **Wajibkan** semua ini sebagai required check di branch protection.

**Konsep kunci:** coverage threshold, static analysis, shift-left security,
required checks.

---

## Tahap 4 — Continuous Delivery/Deployment

Kerangka sudah ada: [`.github/workflows/deploy-staging.yml`](../.github/workflows/deploy-staging.yml).
Setelah merge ke `develop`, artefak frontend di-build & siap dideploy.

Langkah mengaktifkan (perlu akun provider):
1. Buat **Environment** `staging` di GitHub (bisa diberi aturan approval).
2. Isi **secret** `STAGING_DEPLOY_TOKEN` + variable `STAGING_API_BASE_URL`, `STAGING_URL`.
3. Uncomment perintah deploy provider (Vercel/Netlify untuk frontend,
   Railway/Render untuk backend Laravel — sesuai PRD bab 8.1).
4. Tambahkan **smoke test** setelah deploy (`curl $STAGING_URL/api/ping`).

**Konsep kunci:** environment & secrets, artifact, promotion staging→prod,
smoke test, rollback, approval gate.

---

## Checklist perjalanan

- [x] CI dasar backend + frontend (`ci.yml`)
- [x] Unit/Feature test backend (PHPUnit)
- [x] Unit test frontend (Vitest) + coverage provider
- [x] Kerangka deploy staging (`deploy-staging.yml`)
- [ ] Aktifkan branch protection + required check "CI OK"
- [ ] Tambah API test (Postman + Newman)
- [ ] Tambah E2E test (Playwright)
- [ ] Tambah static analysis (Larastan + ESLint)
- [ ] Tegakkan coverage threshold
- [ ] Isi secrets & aktifkan deploy staging sungguhan
- [ ] Tambah smoke test pasca-deploy + strategi rollback

---

## Perintah harian (cheat sheet)

```bash
# Backend
cd backend
composer install
php artisan test                 # semua test
php artisan test --filter=Auth   # test tertentu
./vendor/bin/pint                # rapikan gaya kode
./vendor/bin/pint --test         # cek gaya kode (seperti di CI)

# Frontend
cd frontend
npm ci                           # install deterministik (seperti di CI)
npm run typecheck                # cek tipe TS
npm test                         # unit test Vitest
npm run test:coverage            # test + laporan coverage
npm run build                    # build produksi
```

## Sumber belajar
- GitHub Actions docs: https://docs.github.com/actions
- Laravel Testing: https://laravel.com/docs/testing
- Vitest: https://vitest.dev
- Playwright: https://playwright.dev
- Test Pyramid (Martin Fowler): https://martinfowler.com/articles/practical-test-pyramid.html
