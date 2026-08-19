# Blueprint Arsitektur Escrow & Pembayaran — Tugasin / Serbabisa.id

Dokumen desain keamanan untuk mengisi `PaymentController`, `PayoutRequestController`,
dan alur Wallet **sebelum menulis kode**. Tujuan: uang customer aman "dititipkan"
(escrow) sampai pekerjaan selesai, mitra dibayar dengan benar, dan tidak ada celah
double-charge / saldo negatif / manipulasi harga.

> Prinsip induk: **jangan pernah percaya angka dari client. Semua nilai uang
> dihitung dan divalidasi di server.**

---

## 1. Model Data & Skema Uang

Semua nominal uang disimpan sebagai **integer rupiah** (atau `decimal:2`) — jangan
`float`. Skema yang sudah ada (`payments`, `wallets`, `payout_requests`) sudah
memakai `decimal`, pertahankan.

Tabel `payments` (sudah ada): `task_id`, `jumlah`, `komisi_platform`, `service_fee`,
`status`, `metode`, `referensi_midtrans`, `paid_at`, `released_at`.

Tambahkan kolom pendukung keamanan:
- `idempotency_key` (unique) — cegah pembuatan pembayaran ganda.
- `gross_amount` — total yang ditagih ke customer (dihitung server).
- Index unik `task_id` (satu tugas = satu pembayaran aktif).

---

## 2. State Machine Escrow

Status pembayaran **hanya boleh** berpindah mengikuti transisi berikut. Tolak
transisi lain.

```
                 (customer bayar)         (webhook gateway: settlement)
  [pending] ───────────────────────▶ [awaiting_payment] ───────────────▶ [held]
                                                                            │
                        (customer konfirmasi selesai / auto-release)        │
                            ┌───────────────────────────────────────────────┤
                            ▼                                                ▼
                        [released]  (saldo masuk wallet mitra)          [refunded]
                            │                                     (dispute dimenangkan customer)
                            ▼
                        [settled]
```

Aturan transisi (contoh):
- `held → released` hanya jika `task.status = completed` DAN pemicunya customer
  pemilik atau admin (resolusi dispute), atau auto-release setelah tenggang waktu.
- `held → refunded` hanya lewat resolusi dispute oleh admin.
- Dari `released`/`refunded` tidak bisa kembali.

Implementasikan sebagai method eksplisit, mis. `Payment::markHeld()`,
`Payment::release()`, yang melempar exception jika status sumber tidak valid.

---

## 3. Alur Pembuatan Pembayaran (server-authoritative)

`POST /api/tasks/{task}/payments`

```
1. authorize('pay', $task)              // hanya customer pemilik tugas
2. Pastikan belum ada payment aktif      // cek unique task_id
3. Ambil Idempotency-Key dari header     // wajib; kalau key sudah ada → kembalikan
                                         //   payment lama, JANGAN buat baru
4. Hitung nominal DI SERVER:
     base   = task.harga (fixed) ATAU bid.harga_tawaran yang diterima (custom)
     fee    = aturan platform (mis. service_fee tetap + komisi %)
     gross  = base + service_fee
   → JANGAN memakai 'jumlah' dari body request
5. DB::transaction:
     - buat Payment(status = awaiting_payment, idempotency_key, gross_amount, ...)
     - minta Snap/charge ke Midtrans dgn order_id = payment.id
6. Kembalikan token pembayaran gateway ke client
```

Validasi bisnis wajib: `base > 0`, tidak ada diskon yang bisa membuat `gross <= 0`,
budget/harga sesuai kategori (`config` basis harga), tugas dalam status yang benar.

---

## 4. Verifikasi Webhook Gateway (kritis)

Callback dari Midtrans/Xendit **tidak boleh dipercaya mentah**. Endpoint webhook
harus:

```
1. Berada di route publik terpisah, TANPA auth:sanctum, tapi:
2. Verifikasi signature:
     Midtrans: sha512(order_id + status_code + gross_amount + ServerKey)
               harus == signature_key dari payload
3. Ambil ulang status transaksi via API gateway (server-to-server) untuk konfirmasi,
   jangan hanya percaya body callback.
4. Cocokkan gross_amount payload == payment.gross_amount di DB. Kalau beda → tolak.
5. Idempotent: webhook bisa dikirim berkali-kali → proses sekali saja
   (cek status saat ini sebelum transisi).
6. Baru setelah semua lolos: payment.markHeld() (masuk escrow).
```

Simpan `referensi_midtrans` dan log setiap callback (VULN-009: audit).

---

## 5. Release Dana ke Wallet Mitra (anti race condition)

Saat tugas selesai / dispute selesai:

```php
DB::transaction(function () use ($payment) {
    $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

    // idempotent: hanya proses jika masih 'held'
    abort_unless($payment->status === 'held', 409);

    $wallet = Wallet::where('user_id', $payment->task->mitra_id)
        ->lockForUpdate()          // kunci baris → cegah double-credit
        ->firstOrFail();

    $mitraShare = $payment->gross_amount
        - $payment->service_fee
        - $payment->komisi_platform;

    $wallet->increment('saldo', $mitraShare);
    $payment->release();           // status → released, released_at = now()
    // catat WalletTransaction (ledger) untuk audit
});
```

Gunakan **`lockForUpdate()`** pada wallet & payment agar dua request bersamaan tidak
meng-kredit dua kali. Idealnya buat tabel **ledger** `wallet_transactions`
(append-only) sehingga saldo = hasil penjumlahan mutasi yang bisa diaudit, bukan
sekadar angka yang di-`increment`.

---

## 6. Payout / Penarikan Dana (anti saldo negatif)

`POST /api/payout-requests`

```
1. authorize: hanya mitra pemilik wallet
2. Validasi: jumlah > minimum, <= saldo saat ini
3. DB::transaction + wallet->lockForUpdate():
     - re-cek: jumlah <= wallet.saldo   // WAJIB di dalam lock
     - kurangi saldo (increment negatif) + catat ledger 'payout_hold'
     - buat PayoutRequest(status = pending)
4. Admin approve → transfer manual/gateway → status = processed
   Admin reject → kembalikan saldo (ledger 'payout_refund')
```

Kritis: pengecekan `jumlah <= saldo` **harus di dalam transaksi ber-lock**, bukan
sebelum, agar tidak bisa ditarik dua kali secara paralel (double-spend).

---

## 7. Otorisasi (Policy) untuk Alur Uang

Buat policy dan pasang `authorize()` sebelum setiap aksi:

| Aksi | Siapa yang boleh |
|------|------------------|
| `payment.pay` | customer pemilik tugas |
| `payment.view` | peserta tugas + admin |
| `payment.release` | customer pemilik / admin (resolusi dispute) |
| `payment.refund` | admin saja |
| `payout.create` | mitra pemilik wallet |
| `payout.process` | admin saja |

---

## 8. Checklist Keamanan Sebelum Go-Live Fitur Uang

- [ ] Semua nominal dihitung server, `jumlah` dari client diabaikan.
- [ ] `Idempotency-Key` wajib pada pembuatan pembayaran.
- [ ] Signature webhook diverifikasi + konfirmasi ulang ke gateway.
- [ ] `gross_amount` payload dicocokkan dengan DB.
- [ ] Semua mutasi saldo dalam `DB::transaction` + `lockForUpdate`.
- [ ] Saldo tidak mungkin negatif (re-cek di dalam lock).
- [ ] Tabel ledger append-only untuk audit setiap mutasi.
- [ ] State machine menolak transisi ilegal.
- [ ] Setiap aksi uang punya policy + `authorize()`.
- [ ] Semua event uang tercatat di audit log (aktor, IP, waktu, nominal).
- [ ] ServerKey/secret gateway hanya di `.env`, tidak pernah ke frontend.
- [ ] Rate limit pada endpoint payout untuk cegah spam/abuse.

---

## Referensi
- OWASP — Business Logic Vulnerabilities
- CWE-840 (Business Logic Errors), CWE-362 (Race Condition), CWE-799 (Improper Control of Interaction Frequency)
- Midtrans — HTTP Notification / Signature Key verification
- Laravel — Database Transactions, Pessimistic Locking (`lockForUpdate`)
