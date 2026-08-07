# PRODUCT REQUIREMENTS DOCUMENT (PRD)

## Tugasin
**"Suruh siapa aja, kerjain apa aja" — Platform Jasa On-Demand Serba Bisa**

Web App Responsif (Mobile-First / PWA)
Frontend: Vue 3 + TypeScript | Backend: Laravel 11 + PHP 8.2

Dokumen ini disusun sebagai bahan belajar end-to-end: Business Analyst → Developer → QA → DevOps

**Disusun untuk:** Aii
**Versi:** 1.0 — 6 Agustus 2026

---

## 1. Ringkasan Eksekutif & Latar Belakang

Tugasin adalah platform web on-demand yang mempertemukan Customer (pemberi tugas) dengan Mitra (pelaksana tugas) untuk berbagai jenis pekerjaan sehari-hari — mulai dari antar-jemput, antar barang, belanja titipan, bersih-bersih, hingga permintaan custom yang tidak masuk kategori baku. Berbeda dari Gojek yang berfokus pada layanan transportasi & logistik dengan katalog layanan tetap, Tugasin dirancang agar Customer bisa 'menyuruh' Mitra melakukan hampir apa saja yang legal dan wajar, melalui kombinasi kategori jasa tetap (untuk kecepatan & standar harga) dan permintaan custom (untuk fleksibilitas).

Karena keterbatasan budget untuk publikasi ke Play Store dan App Store, produk ini akan dibangun sebagai website responsif (mobile-first) yang dapat diakses langsung dari browser HP, dengan opsi ditingkatkan menjadi Progressive Web App (PWA) agar Customer maupun Mitra tetap bisa 'install ke Home Screen' dan menerima notifikasi tanpa melalui app store.

Proyek ini terpisah dari monorepo GMM Travel.id (Kliktrip) yang sudah berjalan (travel & tour booking), namun dapat memakai pola arsitektur dan pengalaman yang sama karena menggunakan stack backend yang serupa (Laravel + PHP) dan integrasi pembayaran (Midtrans).

**Tujuan dokumen:** menjadi acuan tunggal (single source of truth) untuk membangun MVP dari nol sampai rilis produksi, sekaligus kurikulum praktik peran Business Analyst, Developer, QA, dan DevOps bagi solo-builder.

### 1.1 Masalah yang Diselesaikan

- Banyak kebutuhan bantuan harian (titip beli, antar barang mendadak, bantu beres-beres, dsb.) tidak selalu cocok dengan kategori layanan baku aplikasi on-demand besar.
- Mitra informal (tukang, kurir lepas, ART lepas, mahasiswa) kesulitan mendapat pekerjaan sampingan yang terverifikasi dan dibayar aman.
- Customer butuh satu platform tepercaya untuk 'menyuruh siapa saja' tanpa harus tawar-menawar manual lewat grup WhatsApp/marketplace informal.

### 1.2 Tujuan Produk (Goals)

1. Menyediakan MVP web responsif yang memungkinkan Customer memposting tugas (kategori tetap atau custom) dan Mitra menerima/menegosiasikan tugas tersebut secara real-time.
2. Menjamin transaksi aman lewat sistem pembayaran terintegrasi (escrow sederhana) dan rating/review dua arah.
3. Memberi Admin visibilitas penuh untuk moderasi, penyelesaian sengketa (dispute), dan pemantauan operasional.
4. Menjadi wahana belajar praktik nyata: requirement gathering, sprint development, QA testing, dan CI/CD deployment ke production.

---

## 2. Target Pengguna & Persona

### 2.1 Persona Customer — "Rani, 27, Karyawan Kantoran"

- Sibuk kerja 9-to-5, sering butuh bantuan titip beli makan siang, antar dokumen, atau beres-beres rumah di akhir pekan.
- Ingin proses pemesanan cepat (< 2 menit), tahu estimasi biaya di awal, dan bisa pantau progres tugas secara real-time.
- Menggunakan HP Android kelas menengah, koneksi data terbatas — butuh web yang ringan dan cepat.

### 2.2 Persona Mitra — "Dimas, 22, Mahasiswa/Pekerja Lepas"

- Butuh penghasilan tambahan fleksibel, punya motor, dan familiar pakai HP untuk kerja (ojol, freelance).
- Ingin melihat daftar tugas terdekat, estimasi upah jelas sebelum menerima, dan pencairan dana yang cepat & transparan.

### 2.3 Persona Admin/Ops — "Aii, Founder & Operator"

- Perlu dashboard untuk memantau transaksi, memverifikasi Mitra baru (KTP, foto), menangani komplain/dispute, dan melihat metrik bisnis harian.

---

## 3. Model Bisnis & Ruang Lingkup Layanan

Model bisnis MVP menggabungkan dua jalur permintaan agar cepat dipahami pengguna baru namun tetap fleksibel:

### 3.1 Kategori Jasa Tetap (Fixed Category)

Harga estimasi dihitung sistem berdasarkan jarak/durasi/berat, mirip model Gojek. Mitra tinggal menerima (accept) tugas yang masuk sesuai lokasi.

| Kategori | Contoh Permintaan | Basis Harga |
|---|---|---|
| Antar-Jemput | Antar orang dari titik A ke B | Jarak (km) + waktu |
| Antar Barang / Kurir | Kirim paket, dokumen, kunci | Jarak (km) + berat |
| Belanja / Titip Beli | Beli makanan, obat, kebutuhan harian | Ongkos + estimasi belanja |
| Bersih-bersih | Bersihkan rumah/kos per jam | Durasi (jam) |
| Angkut / Pindahan Ringan | Pindah barang skala kecil | Volume + jarak |
| Jasa Tukang Ringan | Perbaikan kecil (listrik, ledeng, dsb.) | Tarif per kunjungan + jam |

### 3.2 Permintaan Custom (Custom Request)

Untuk kebutuhan di luar kategori baku. Customer menulis deskripsi bebas + budget yang diinginkan, Mitra yang berminat mengajukan penawaran (bid), Customer memilih Mitra yang cocok. Fitur ini yang membedakan Tugasin dari aplikasi on-demand konvensional.

### 3.3 Model Monetisasi

| Sumber Pendapatan | Mekanisme | Prioritas MVP |
|---|---|---|
| Komisi per transaksi | Potongan 10-20% dari nilai transaksi yang dibayar Mitra | Must Have |
| Biaya layanan (service fee) ke Customer | Fee tetap kecil per transaksi (mis. Rp 2.000-5.000) | Should Have |
| Fitur boost/prioritas Mitra | Mitra bayar agar profil/tugas lebih terlihat | Could Have (v2) |
| Iklan UMKM lokal | Promosi toko/jasa mitra bisnis di halaman kategori | Won't Have (MVP) |

---

## 4. Ruang Lingkup MVP

### 4.1 In-Scope (v1.0)

- Registrasi & login Customer dan Mitra (email/HP + OTP, opsional login Google).
- Posting tugas kategori tetap maupun custom, lengkap lokasi (peta), foto, catatan.
- Pencarian & penerimaan tugas oleh Mitra berdasarkan radius lokasi.
- Sistem tawar-menawar (bid) untuk permintaan custom.
- Chat in-app Customer ↔ Mitra per tugas.
- Tracking status tugas real-time (menunggu → diterima → dikerjakan → selesai).
- Pembayaran non-tunai via payment gateway (Midtrans) dengan skema tahan dana (escrow sederhana) sampai tugas selesai.
- Rating & review dua arah setelah tugas selesai.
- Dompet Mitra (wallet) + pengajuan pencairan dana (payout) manual/otomatis.
- Dashboard Admin: manajemen user, verifikasi Mitra, monitoring transaksi, penanganan dispute.
- Desain responsif mobile-first + PWA (installable, web push notification).

### 4.2 Out-of-Scope (v1.0) — Dipertimbangkan untuk Versi Berikutnya

- Aplikasi native iOS/Android (App Store/Play Store).
- Live tracking lokasi Mitra dengan peta bergerak real-time (GPS streaming) — v1 cukup update status manual/berkala.
- Sistem lelang (bidding war) otomatis dengan algoritma matching kompleks.
- Multi-bahasa dan multi-mata uang.
- Program afiliasi / referral berjenjang.

---

## 5. Peran Pengguna & User Stories

### 5.1 Customer

- Sebagai Customer, saya ingin mendaftar & verifikasi HP, agar akun saya aman dan bisa dipercaya Mitra.
- Sebagai Customer, saya ingin memposting tugas dengan kategori & lokasi, agar Mitra terdekat bisa langsung melihatnya.
- Sebagai Customer, saya ingin melihat estimasi biaya sebelum konfirmasi, agar tidak ada biaya tersembunyi.
- Sebagai Customer, saya ingin chat dengan Mitra yang menangani tugas saya, agar bisa memberi instruksi tambahan.
- Sebagai Customer, saya ingin membayar lewat aplikasi (bukan tunai), agar transaksi tercatat & aman.
- Sebagai Customer, saya ingin memberi rating & ulasan setelah tugas selesai, agar membantu Mitra lain terpercaya terlihat.
- Sebagai Customer, saya ingin mengajukan komplain/dispute jika tugas tidak sesuai, agar dana saya terlindungi.

### 5.2 Mitra / Pelaksana

- Sebagai Mitra, saya ingin mendaftar dengan verifikasi KTP & foto, agar profil saya terpercaya di mata Customer.
- Sebagai Mitra, saya ingin melihat daftar tugas di sekitar lokasi saya, agar bisa memilih tugas yang paling menguntungkan.
- Sebagai Mitra, saya ingin mengajukan penawaran pada permintaan custom, agar bisa bersaing sehat berdasar harga & keahlian.
- Sebagai Mitra, saya ingin mengubah status tugas (diterima/dikerjakan/selesai), agar Customer tahu progresnya.
- Sebagai Mitra, saya ingin melihat riwayat penghasilan & mengajukan pencairan dana, agar bisa mengatur keuangan saya.

### 5.3 Admin / Ops

- Sebagai Admin, saya ingin memverifikasi dokumen Mitra baru, agar hanya Mitra sah yang aktif di platform.
- Sebagai Admin, saya ingin melihat seluruh transaksi & status pembayaran, agar bisa memantau kesehatan bisnis.
- Sebagai Admin, saya ingin menangani laporan/dispute antara Customer & Mitra, agar konflik terselesaikan adil.
- Sebagai Admin, saya ingin menonaktifkan akun yang melanggar aturan, agar platform tetap aman & tepercaya.

---

## 6. Functional Requirements (Prioritas MoSCoW)

| ID | Modul | Requirement | Prioritas |
|---|---|---|---|
| FR-01 | Auth | Registrasi/login Customer & Mitra via HP+OTP, opsional Google OAuth | Must |
| FR-02 | Auth | Verifikasi dokumen Mitra (KTP, selfie) oleh Admin sebelum aktif | Must |
| FR-03 | Task | Customer membuat tugas kategori tetap dengan estimasi harga otomatis | Must |
| FR-04 | Task | Customer membuat permintaan custom (deskripsi bebas + budget) | Must |
| FR-05 | Task | Mitra melihat daftar tugas terdekat & menerima tugas kategori tetap | Must |
| FR-06 | Task | Mitra mengajukan bid pada permintaan custom; Customer memilih pemenang | Must |
| FR-07 | Task | Update status tugas (pending, accepted, in_progress, completed, cancelled) | Must |
| FR-08 | Chat | Chat real-time per tugas antara Customer & Mitra | Must |
| FR-09 | Payment | Integrasi payment gateway (Midtrans) untuk pembayaran non-tunai | Must |
| FR-10 | Payment | Dana ditahan (escrow) sampai tugas dikonfirmasi selesai oleh Customer | Must |
| FR-11 | Payment | Dompet Mitra & pengajuan pencairan dana (payout request) | Must |
| FR-12 | Review | Rating & ulasan dua arah setelah tugas selesai | Should |
| FR-13 | Notifikasi | Push notification web (PWA) untuk tugas baru/perubahan status | Should |
| FR-14 | Admin | Dashboard admin: manajemen user, transaksi, laporan | Must |
| FR-15 | Admin | Modul penyelesaian dispute/komplain dengan riwayat percakapan | Should |
| FR-16 | Lokasi | Pencarian tugas berdasarkan radius dari lokasi Mitra (peta) | Must |
| FR-17 | Promo | Kode voucher/diskon sederhana untuk transaksi pertama | Could |
| FR-18 | Referral | Program referral berjenjang | Won't (v1) |

---

## 7. Non-Functional Requirements

| Aspek | Target |
|---|---|
| Performa | First Contentful Paint < 2.5s dan Time to Interactive < 4s pada koneksi 4G di HP kelas menengah |
| Responsif | Mobile-first; breakpoint utama 360px, 768px (tablet), 1024px+ (desktop); mendukung PWA install & offline fallback dasar |
| Keamanan | HTTPS wajib, password hashing (bcrypt/argon2), rate limiting login/OTP, validasi input sisi server, proteksi OWASP Top 10 (XSS, SQLi, CSRF) |
| Ketersediaan | Target uptime 99% pada fase MVP (single region), monitoring & alert otomatis jika downtime |
| Skalabilitas | Arsitektur stateless API agar mudah horizontal scaling di kemudian hari; queue worker terpisah untuk notifikasi/payout |
| Aksesibilitas | Kontras warna memadai, ukuran tap target minimal 44x44px, label form yang jelas untuk pengguna awam |
| Kompatibilitas Browser | Chrome, Safari (iOS), Samsung Internet — 2 versi terakhir |
| Bahasa & Lokal | Bahasa Indonesia, mata uang Rupiah (IDR), format tanggal lokal |
| Audit & Log | Seluruh transaksi & perubahan status tugas tercatat (audit trail) untuk keperluan dispute |

---

## 8. Arsitektur Teknis

### 8.1 Ringkasan Stack

| Layer | Teknologi | Catatan |
|---|---|---|
| Frontend Web | Vue 3 (Composition API) + TypeScript | Vite sebagai build tool |
| State Management | Pinia | Store per domain: auth, task, chat, wallet |
| Routing | Vue Router 4 | Route guard berbasis role (customer/mitra/admin) |
| Styling/UI | Tailwind CSS + komponen custom | Mobile-first, desain sistem sederhana |
| PWA | vite-plugin-pwa | Manifest, service worker, web push |
| HTTP Client | Axios | Interceptor untuk token refresh & error handling |
| Peta/Lokasi | Leaflet + OpenStreetMap (atau Google Maps bila budget ada) | Pencarian radius & pin lokasi |
| Backend API | Laravel 11 + PHP 8.2 | REST API, arsitektur modular (Controller-Service-Repository) |
| Autentikasi API | Laravel Sanctum | Token-based, cocok untuk SPA |
| Database | MySQL/PostgreSQL | PostgreSQL disarankan agar konsisten dengan Kliktrip (Supabase) |
| Real-time | Laravel Reverb / Pusher | Untuk chat & update status tugas |
| Queue & Job | Redis + Laravel Queue | Notifikasi, payout, email |
| Payment Gateway | Midtrans | Snap/Core API, sudah familiar dari proyek Kliktrip |
| Storage File | S3-compatible / lokal + CDN | Foto KTP, foto tugas |
| Hosting Backend | Railway/Render (Docker) | Mengikuti pola Kliktrip yang sudah ada |
| Hosting Frontend | Vercel/Netlify | Auto-deploy dari branch main |

### 8.2 Alasan Web Responsif + PWA (Bukan Native App)

Karena tidak ada budget publikasi ke Play Store/App Store, pendekatan yang dipilih adalah Progressive Web App: dapat diakses langsung via URL dari browser HP, bisa 'Add to Home Screen' agar terasa seperti aplikasi native, mendukung notifikasi push dasar, dan tidak memerlukan proses review/akun developer berbayar. Pendekatan ini juga mempercepat siklus rilis karena update langsung berlaku tanpa menunggu approval store.

### 8.3 Modul Backend (Domain)

- Auth & User Management (Customer, Mitra, Admin, role & permission)
- Task Management (kategori tetap, custom request, bidding)
- Matching & Lokasi (radius search, geocoding)
- Chat & Notifikasi
- Payment & Wallet (escrow, payout, riwayat transaksi)
- Review & Rating
- Dispute & Moderasi
- Admin/Reporting

### 8.4 Entitas Data Utama (Ringkasan ERD)

| Entitas | Atribut Kunci | Relasi |
|---|---|---|
| users | id, nama, email, hp, role, status_verifikasi | 1—N ke tasks, wallets, reviews |
| mitra_profiles | user_id, no_ktp, foto_ktp, skill, rating_avg | 1—1 ke users |
| categories | id, nama, basis_harga, ikon | 1—N ke tasks |
| tasks | id, customer_id, mitra_id, category_id, tipe (fixed/custom), status, lokasi, harga | N—1 ke users & categories; 1—N ke bids, messages |
| bids | id, task_id, mitra_id, harga_tawaran, status | N—1 ke tasks & users |
| messages | id, task_id, sender_id, isi, dibaca_pada | N—1 ke tasks |
| payments | id, task_id, jumlah, status, metode, referensi_midtrans | 1—1 ke tasks |
| wallets | id, user_id, saldo | 1—1 ke users |
| payout_requests | id, wallet_id, jumlah, status, diproses_oleh | N—1 ke wallets |
| reviews | id, task_id, dari_user_id, ke_user_id, rating, komentar | N—1 ke tasks |
| disputes | id, task_id, dilaporkan_oleh, alasan, status, resolusi | 1—1 ke tasks |

---

## 9. Prinsip UX & Desain Responsif

- Mobile-first: rancang untuk layar 360-414px terlebih dulu, baru diperluas ke tablet/desktop.
- Maksimal 3 langkah untuk memposting tugas (pilih kategori → isi detail & lokasi → konfirmasi harga).
- Navigasi bawah (bottom navigation bar) khas aplikasi mobile untuk kemudahan jempol satu tangan.
- Status tugas ditampilkan dengan visual timeline yang jelas (progress stepper).
- Gunakan skeleton loading, bukan spinner polos, untuk perceived performance yang lebih baik pada koneksi lambat.
- Form input minim ketikan: gunakan pilihan (chip/dropdown) dan deteksi lokasi otomatis (geolocation API).

---

## 10. Roadmap Kerja Lintas Peran (BA → Dev → QA → DevOps)

Karena tujuan proyek ini juga untuk belajar end-to-end, roadmap dibagi menjadi fase berurutan dengan output konkret di tiap peran. Estimasi asumsi solo-builder part-time (~15-20 jam/minggu).

### Fase 0 — Business Analyst: Discovery & Requirement (Minggu 1-2)

- Validasi masalah & kompetitor (Gojek, TaskRabbit, Fiverr, Sejasa, RUMA/lainnya) — buat competitive analysis singkat.
- Finalisasi PRD ini: persona, user stories, prioritas MoSCoW (dokumen ini).
- Buat user journey map untuk 3 alur utama: posting tugas, terima tugas, penyelesaian & pembayaran.
- Output: PRD final, backlog awal (epic → user story), wireframe low-fidelity (bisa pakai Figma gratis).

### Fase 1 — Design & Arsitektur (Minggu 2-3)

- Desain database schema & ERD detail dari tabel di Bab 8.4.
- Desain API contract (OpenAPI/Postman collection) untuk tiap modul.
- Wireframe/mockup UI responsif per halaman utama (login, home, buat tugas, detail tugas, chat, wallet, admin dashboard).
- Setup repo: struktur monorepo/2 repo (frontend Vue + backend Laravel), branching strategy (main/develop/feature).

### Fase 2 — Development Sprint (Minggu 4-10)

- Sprint 1: Auth & user management (Customer/Mitra/Admin), setup proyek Vue+TS dan Laravel.
- Sprint 2: Modul Task — posting tugas kategori tetap & custom, listing & detail tugas.
- Sprint 3: Matching lokasi, bidding, penerimaan tugas oleh Mitra.
- Sprint 4: Chat real-time & notifikasi.
- Sprint 5: Payment (Midtrans), escrow, wallet & payout.
- Sprint 6: Review/rating, dashboard admin, dispute handling.
- Praktik: buat backlog di tool sederhana (Trello/GitHub Projects), daily self-standup, definisi "Done" per story (kode + unit test + review sendiri).

### Fase 3 — Quality Assurance (Berjalan paralel mulai Sprint 2, intensif di Minggu 10-12)

- Buat test plan & test case per modul (functional, negative case, edge case).
- Unit test backend dengan PHPUnit/Pest; unit test frontend dengan Vitest + Vue Test Utils.
- API testing dengan Postman/Newman untuk seluruh endpoint (happy path & error handling).
- E2E testing alur kritikal (posting tugas → bayar → selesai) dengan Playwright/Cypress.
- Regression testing sebelum setiap rilis; catat bug di tracker (GitHub Issues) dengan severity & prioritas.
- User Acceptance Testing (UAT) dengan beberapa calon pengguna nyata sebelum go-live.

### Fase 4 — DevOps & Rilis (Minggu 12-14)

- Containerize backend (Dockerfile sudah ada polanya dari Kliktrip) dan siapkan environment staging & production.
- Setup CI/CD (GitHub Actions): lint → test → build → deploy otomatis ke staging saat push ke develop, ke production saat merge ke main.
- Konfigurasi environment variables & secrets terpisah per environment (.env staging/production).
- Setup domain, SSL (Let's Encrypt/hosting bawaan), dan monitoring (Sentry untuk error tracking, UptimeRobot untuk availability).
- Migrasi database production, seed data kategori awal, smoke test pasca-deploy.
- Rencana rollback sederhana jika deployment gagal (versi image sebelumnya / migration down).

### Fase 5 — Post-Launch (Minggu 15+)

- Pantau metrik: jumlah tugas terposting, tingkat penyelesaian, waktu rata-rata Mitra menerima tugas, retensi mingguan.
- Kumpulkan feedback pengguna awal → masukkan ke backlog v1.1 (mis. live tracking GPS, referral).
- Iterasi rutin: rilis kecil tiap 1-2 minggu mengikuti feedback & bug produksi.

### 10.1 Ringkasan Timeline

| Fase | Peran Utama | Durasi (estimasi) | Output Utama |
|---|---|---|---|
| 0. Discovery | BA | Minggu 1-2 | PRD, backlog, wireframe low-fi |
| 1. Design | BA + Dev | Minggu 2-3 | ERD, API contract, mockup UI |
| 2. Development | Dev | Minggu 4-10 | Fitur MVP berfungsi di local/staging |
| 3. QA | QA (paralel) | Minggu 2-12 | Test plan, bug report, hasil UAT |
| 4. DevOps & Rilis | DevOps | Minggu 12-14 | CI/CD, staging & production live |
| 5. Post-Launch | Semua peran | Minggu 15+ | Metrik, backlog v1.1 |

---

## 11. Metrik Keberhasilan (KPI)

| Metrik | Target MVP (3 bulan pasca-launch) |
|---|---|
| Jumlah tugas terposting/minggu | ≥ 50 |
| Tingkat penyelesaian tugas (completion rate) | ≥ 80% |
| Rata-rata waktu Mitra menerima tugas | ≤ 10 menit (kategori tetap) |
| Rating rata-rata platform | ≥ 4.3 / 5 |
| Tingkat dispute terhadap total transaksi | ≤ 5% |
| Retensi Customer mingguan | ≥ 25% |

---

## 12. Risiko & Mitigasi

| Risiko | Dampak | Mitigasi |
|---|---|---|
| Mitra fiktif/penipuan | Kepercayaan platform turun | Verifikasi KTP wajib, rating, escrow dana |
| Supply Mitra rendah di awal | Tugas tidak terisi, Customer kecewa | Rekrut Mitra manual di area pilot, insentif awal |
| Kompleksitas custom request sulit di-standardisasi | Sengketa harga/hasil kerja | Wajib deskripsi + budget jelas, admin mediasi dispute |
| Keterbatasan tanpa app native (push notif terbatas di iOS Safari) | Engagement lebih rendah di iOS | Optimalkan web push Android dahulu, fallback SMS/email untuk update penting |
| Beban solo-builder di semua peran | Timeline molor | Prioritas MoSCoW ketat, MVP kecil dulu, iterasi bertahap |
| Keamanan pembayaran/data pribadi | Risiko hukum & kepercayaan | Pakai payment gateway resmi (Midtrans), enkripsi data sensitif, HTTPS wajib |

---

## 13. Lampiran

### 13.1 Glosarium

- **Customer**: pengguna yang memposting/memberi tugas.
- **Mitra**: pengguna yang menerima/mengerjakan tugas.
- **Escrow**: dana yang ditahan sistem sampai tugas dikonfirmasi selesai.
- **PWA**: Progressive Web App, website yang bisa diinstal & berfungsi seperti aplikasi native.
- **Bid**: penawaran harga yang diajukan Mitra untuk permintaan custom.

### 13.2 Referensi Kompetitor (untuk riset lanjutan)

- Gojek — layanan on-demand kategori tetap (transportasi, logistik, layanan rumah).
- Sejasa/Rekan Jasa — marketplace jasa lokal Indonesia.
- TaskRabbit — marketplace tugas & odd jobs (referensi model custom request).
- Fiverr/Upwork — model bidding untuk jasa (referensi alur bid/nego harga).

### 13.3 Catatan Versi Dokumen

| Versi | Tanggal | Perubahan |
|---|---|---|
| 1.0 | 6 Agustus 2026 | Draf awal PRD — hasil sesi requirement gathering bersama Aii |
