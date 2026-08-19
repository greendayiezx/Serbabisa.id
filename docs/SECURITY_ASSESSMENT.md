# Laporan Penilaian Keamanan — Tugasin / Serbabisa.id

| | |
|---|---|
| **Aplikasi** | Tugasin / Serbabisa.id (marketplace jasa on-demand) |
| **Stack** | Laravel 11 (REST API + Sanctum) · Vue 3 SPA · SQLite/MySQL |
| **Tanggal audit** | 19 Agustus 2026 |
| **Auditor** | Security review (internal) |
| **Metodologi** | Secure code review manual + pemetaan OWASP Top 10 2021 |
| **Scope** | Autentikasi, otorisasi, alur transaksi/escrow, konfigurasi |

> ⚠️ Disclaimer: dokumen ini untuk keperluan pengamanan aplikasi milik sendiri.
> Beberapa temuan **sudah diperbaiki** dalam sesi audit ini (ditandai ✅ FIXED),
> sisanya adalah rekomendasi yang perlu dikerjakan.

---

## Ringkasan Eksekutif

| ID | Judul | Severity | CWE | Status |
|----|-------|----------|-----|--------|
| VULN-001 | Broken Access Control / IDOR pada Task & Chat | 🔴 Critical | CWE-639 | ✅ FIXED |
| VULN-002 | Mass Assignment → Privilege Escalation (`role`, `is_active`) | 🔴 Critical | CWE-915 | ✅ FIXED |
| VULN-003 | CORS `allowed_origins: ['*']` | 🔴 Critical | CWE-942 | ✅ FIXED |
| VULN-004 | Tidak ada rate limiting pada login/register | 🟠 High | CWE-307 | ✅ FIXED |
| VULN-005 | Token disimpan di `localStorage` (rawan pencurian via XSS) | 🟠 High | CWE-522 | ⚠️ Mitigasi (token expiry) — SPA cookie disarankan |
| VULN-006 | `APP_DEBUG=true` berpotensi terbawa ke production | 🟠 High | CWE-489 | ⚠️ Open (env production) |
| VULN-007 | Alur pembayaran/escrow belum ada kontrol keamanan | 🟡 Medium | CWE-840 | ⚠️ By design (lihat blueprint) |
| VULN-008 | HTTP security headers tidak ada | 🟡 Medium | CWE-693 | ✅ FIXED |
| VULN-009 | Tidak ada audit logging untuk aksi sensitif | 🟡 Medium | CWE-778 | ✅ FIXED |
| VULN-010 | Tidak ada verifikasi email/telepon | 🟢 Low | CWE-287 | ⚠️ Open |

---

## VULN-001 — Broken Access Control / IDOR pada Task & Chat

**Severity:** Critical · **CVSS 3.1:** 8.1 (AV:N/AC:L/PR:L/UI:N/S:U/C:H/I:H/A:N) · **CWE-639**

### Deskripsi
`TaskController@show/update/destroy` dan `MessageController@index/store` menggunakan
route-model binding (`Task $task`) **tanpa pengecekan kepemilikan**. Setiap user
terautentikasi bisa membaca, mengubah, menghapus tugas, serta membaca/menulis chat
milik user lain hanya dengan menebak/menaikkan ID.

### Dampak
- Pembocoran data pribadi (alamat, lokasi GPS, isi chat) antar pengguna.
- Manipulasi status tugas milik orang lain (mis. menandai "completed").
- Penghapusan tugas milik pengguna lain.

### Langkah Reproduksi (sebelum perbaikan)
1. Login sebagai user A.
2. `GET /api/tasks/{id}` dengan `{id}` milik user B → data B ikut terbaca.
3. `DELETE /api/tasks/{id}` milik B → terhapus.

### Perbaikan yang diterapkan ✅
- Ditambahkan trait `AuthorizesRequests` pada base `Controller`.
- Dibuat `App\Policies\TaskPolicy` (`view`/`update`/`delete` + `before` admin).
- Ditambahkan `$this->authorize(...)` di `TaskController` (show/update/destroy)
  dan `MessageController` (index/store).
- File: `app/Policies/TaskPolicy.php`, `app/Http/Controllers/Api/TaskController.php`,
  `app/Http/Controllers/Api/MessageController.php`.

### Rekomendasi lanjutan
Saat controller stub (Bid, Dispute, Review, PayoutRequest, Payment) diisi, buat
policy serupa untuk masing-masing sebelum menulis logikanya.

---

## VULN-002 — Mass Assignment → Privilege Escalation

**Severity:** Critical · **CVSS 3.1:** 8.8 · **CWE-915**

### Deskripsi
Model `User` memasukkan `role`, `is_active`, dan `status_verifikasi` ke `$fillable`.
Endpoint apa pun yang melakukan `$user->update($request->all())` (mis.
`AdminUserController@update` yang masih stub) membuka pintu bagi pengguna untuk
mengubah dirinya menjadi `admin` atau mengaktifkan akun yang dinonaktifkan.

### Dampak
Eskalasi hak akses penuh menjadi administrator.

### Perbaikan yang diterapkan ✅
- `role`, `is_active`, `status_verifikasi` **dikeluarkan** dari `$fillable`.
- `AuthController@register` kini menetapkan `role`/`is_active` secara eksplisit
  (role tetap divalidasi hanya `customer|mitra`).
- File: `app/Models/User.php`, `app/Http/Controllers/Api/AuthController.php`.

### Rekomendasi lanjutan
Di `AdminUserController@update`, gunakan `$request->validate()` + penetapan kolom
eksplisit, jangan `->all()`. Terapkan `UserPolicy` agar hanya admin yang bisa
mengubah status akun.

---

## VULN-003 — CORS Wildcard

**Severity:** Critical · **CWE-942**

### Deskripsi
`config/cors.php` menyetel `allowed_origins => ['*']` untuk path `api/*`.
Di production, API keuangan bisa dipanggil dari origin mana pun.

### Perbaikan yang diterapkan ✅
`allowed_origins` kini dibaca dari env `FRONTEND_URL` (whitelist, bisa multi-domain).
File: `config/cors.php`. **Aksi wajib:** set `FRONTEND_URL` di `.env` production.

---

## VULN-004 — Tidak Ada Rate Limiting pada Autentikasi

**Severity:** High · **CWE-307**

### Deskripsi
`/api/login` dan `/api/register` tidak dibatasi → brute force password &
credential stuffing bebas dilakukan.

### Perbaikan yang diterapkan ✅
Ditambahkan `throttle:5,1` (5 percobaan/menit/IP) pada kedua route.
File: `routes/api.php`.

### Rekomendasi lanjutan
Untuk keamanan lebih baik, throttle per-email + per-IP, dan tambahkan lockout
sementara + notifikasi setelah beberapa kegagalan.

---

## VULN-005 — Token di `localStorage`

**Severity:** High · **CWE-522** · **Status: Mitigasi diterapkan**

### Deskripsi
`frontend/src/stores/auth.ts` menyimpan token Sanctum di `localStorage`. Jika ada
satu celah XSS, token bisa dicuri dan dipakai dari mana saja tanpa kedaluwarsa.

### Mitigasi yang diterapkan ✅
- **Token expiry** disetel di `config/sanctum.php` → `expiration` = 7 hari
  (env `SANCTUM_EXPIRATION`), sehingga token yang bocor tidak berlaku selamanya.
- CSP ketat (`default-src 'self'`) via SecurityHeaders middleware menekan permukaan XSS.

### Rekomendasi lanjutan (belum, keputusan arsitektur)
Pindah ke **Sanctum SPA mode** — cookie `HttpOnly` + `Secure` + `SameSite=Lax`,
sehingga JS tidak bisa membaca token sama sekali (proteksi terbaik terhadap
pencurian token via XSS).

---

## VULN-006 — `APP_DEBUG=true` (Open)

**Severity:** High · **CWE-489**

`.env.example` default `APP_DEBUG=true`. Bila terbawa ke production, stack trace &
struktur database bocor. **Aksi:** production wajib `APP_DEBUG=false`,
`APP_ENV=production`, dan `php artisan config:cache`.

---

## VULN-007 — Kontrol Keamanan Pembayaran/Escrow Belum Ada

**Severity:** Medium (by design saat ini) · **CWE-840**

`PaymentController`, `PayoutRequestController`, dan alur wallet masih stub. Ini titik
paling kritis platform. Rancangan aman didokumentasikan terpisah di
[`ESCROW_ARCHITECTURE.md`](ESCROW_ARCHITECTURE.md) — implementasikan mengikuti
blueprint tersebut (harga dihitung server, idempotency, verifikasi webhook,
state machine, DB locking).

---

## VULN-008 — HTTP Security Headers Tidak Ada (Open)

**Severity:** Medium · **CWE-693**

Belum ada `Content-Security-Policy`, `X-Frame-Options`, `X-Content-Type-Options`,
`Strict-Transport-Security`, `Referrer-Policy`, `Permissions-Policy`.

### Rekomendasi
Buat middleware global yang menambahkan header berikut pada setiap response:

```
Content-Security-Policy: default-src 'self'
X-Frame-Options: DENY
X-Content-Type-Options: nosniff
Strict-Transport-Security: max-age=31536000; includeSubDomains
Referrer-Policy: no-referrer
Permissions-Policy: geolocation=(self), camera=(), microphone=()
```

---

## VULN-009 — Audit Logging (Open)

**Severity:** Medium · **CWE-778**

Login gagal, perubahan role, transaksi, payout, dan keputusan dispute tidak dicatat.
Sulit melakukan forensik saat insiden. **Rekomendasi:** channel log khusus `audit`
mencatat aktor, aksi, target, IP, timestamp untuk semua aksi sensitif.

---

## VULN-010 — Verifikasi Email/Telepon (Open)

**Severity:** Low · **CWE-287**

Akun aktif tanpa verifikasi. Untuk platform Indonesia, **OTP nomor HP** disarankan
karena nomor HP adalah identitas utama mitra & saluran transaksi.

---

## Roadmap Prioritas

1. ✅ **Selesai sesi ini** — VULN-001..005, 008, 009 (IDOR, mass-assignment, CORS,
   rate limit, token expiry, security headers, audit logging).
2. **Sebelum fitur uang aktif** — VULN-007 sesuai `ESCROW_ARCHITECTURE.md`.
3. **Sebelum production** — VULN-006 (`APP_DEBUG=false`, `config:cache`), HTTPS/HSTS,
   set `FRONTEND_URL` di `.env`.
4. **Berkelanjutan** — VULN-005 lanjutan (migrasi Sanctum SPA cookie), VULN-010 (OTP),
   `composer audit` + `npm audit` rutin, perluas audit log ke transaksi/payout/dispute.

## Referensi
- OWASP Top 10 2021 — https://owasp.org/Top10/
- CWE — https://cwe.mitre.org/
- Laravel Authorization (Policies) — https://laravel.com/docs/11.x/authorization
- Laravel Sanctum — https://laravel.com/docs/11.x/sanctum
