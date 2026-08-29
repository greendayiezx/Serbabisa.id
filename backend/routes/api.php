<?php

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\PermintaanController as AdminPermintaanController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AngkutCheckoutController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BelanjaCheckoutController;
use App\Http\Controllers\Api\BersihCheckoutController;
use App\Http\Controllers\Api\BersihPesananController;
use App\Http\Controllers\Api\BidController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\CleanerController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\ACCheckoutController;
use App\Http\Controllers\Api\ACPerbaikanController;
use App\Http\Controllers\Api\PenawaranACController;
use App\Http\Controllers\Api\DeepCheckoutController;
use App\Http\Controllers\Api\FreonController;
use App\Http\Controllers\Api\InvoiceController;
use App\Http\Controllers\Api\KantorCheckoutController;
use App\Http\Controllers\Api\KantorPermintaanController;
use App\Http\Controllers\Api\LanggananController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PesananController;
use App\Http\Controllers\Api\PayoutRequestController;
use App\Http\Controllers\Api\PenawaranController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\TaskFotoController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['status' => 'ok']));

// FR-01: Auth — dibatasi rate limit untuk mencegah brute force & spam.
// throttle:5,1 = maksimal 5 percobaan per menit per IP.
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// FR-16: Public category listing (kategori tetap, basis harga)
Route::get('/categories', [CategoryController::class, 'index']);

/*
 * Berkas yang dibuka langsung peramban.
 *
 * Di luar auth:sanctum karena perpindahan halaman tidak bisa membawa header
 * Authorization. Yang jadi bukti akses adalah tanda tangan pada URL-nya —
 * berlaku 15 menit dan diperiksa middleware `signed`.
 */
Route::get('/berkas/permintaan/{task}', [KantorPermintaanController::class, 'berkasPdf'])
    ->name('permintaan.berkas')
    ->middleware('signed:relative');

Route::get('/berkas/invoice/{task}', [InvoiceController::class, 'berkas'])
    ->name('invoice.berkas')
    ->middleware('signed:relative');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // FR-03/04/05/07/16: Task management (kategori tetap & custom, radius search, status)
    Route::apiResource('tasks', TaskController::class);
    // Foto pendukung tugas (mis. foto area di form Minta Penawaran kantor).
    Route::post('/tasks/{task}/foto', [TaskFotoController::class, 'store']);

    // Riwayat lokasi (Tersimpan / Baru-Baru Ini) untuk form pembuatan tugas
    Route::get('/locations/recent', [TaskController::class, 'recentLocations']);

    // FR-06: Bidding pada permintaan custom
    Route::apiResource('tasks.bids', BidController::class)->shallow();

    // FR-08: Chat per tugas
    Route::apiResource('tasks.messages', MessageController::class)->shallow()->only(['index', 'store']);

    // FR-09/FR-10: Payment & escrow per tugas
    Route::apiResource('tasks.payments', PaymentController::class)->shallow()->only(['index', 'store', 'show']);
    Route::post('/payments/{payment}/konfirmasi', [PaymentController::class, 'confirm']);

    // BisaBelanja: checkout (hitung tagihan + promo tervalidasi) & selesai (cashback ke saldo)
    // Langganan BisaBelanja: paket, berlangganan, berhenti
    Route::get('/belanja/langganan', [LanggananController::class, 'index']);
    Route::post('/belanja/langganan', [LanggananController::class, 'store']);
    Route::delete('/belanja/langganan', [LanggananController::class, 'destroy']);

    Route::get('/belanja/orders', [BelanjaCheckoutController::class, 'index']);
    Route::get('/belanja/orders/{nomor}', [BelanjaCheckoutController::class, 'show']);
    Route::post('/belanja/checkout', [BelanjaCheckoutController::class, 'store']);
    Route::patch('/belanja/orders/{task}/selesai', [BelanjaCheckoutController::class, 'complete']);

    // BisaAngkut: checkout (total dihitung server, tersimpan sebagai task → muncul di riwayat)
    Route::post('/angkut/checkout', [AngkutCheckoutController::class, 'store']);

    // BisaBersih: checkout (harga & promo dihitung ulang server)
    Route::get('/bersih/cleaner', [CleanerController::class, 'index']);
    Route::post('/bersih/checkout', [BersihCheckoutController::class, 'store']);
    // Status pesanan: halaman customer memanggil ulang sampai ada yang menerima.
    Route::get('/bersih/pesanan/{nomor}', [BersihPesananController::class, 'show']);
    Route::post('/bersih/pesanan/{nomor}/terima', [BersihPesananController::class, 'terima']);

    // Satu pesanan milik pengguna, layanan apa pun, dicari lewat nomor invoice.
    Route::get('/pesanan/{nomor}', [PesananController::class, 'show']);

    // Invoice PDF — berlaku untuk semua layanan, dicari lewat nomor invoice.
    Route::get('/invoice/{nomor}/tautan', [InvoiceController::class, 'tautan']);

    // Servis AC: dijual per unit; biaya kunjungan & potongan bundling dihitung
    // ulang server.
    Route::post('/servis-ac/checkout', [ACCheckoutController::class, 'store']);

    // Cek & Tambah Freon: yang ditagih di muka hanya PEMERIKSAAN. Pekerjaan
    // lanjutan baru masuk tagihan setelah pelanggan menyetujui hasil diagnosis.
    // Perbaikan AC: yang ditagih hanya kunjungan diagnosisnya. Hasil
    // pemeriksaan dan persetujuannya memakai jalur freon — mekanismenya sama.
    Route::post('/servis-ac/perbaikan/checkout', [ACPerbaikanController::class, 'store']);

    // Pasang/pindah AC: TIDAK menagih apa pun. Yang tercatat permintaan
    // penawaran bernomor REQ-, harganya menyusul setelah foto atau survei.
    Route::post('/servis-ac/pasang/permintaan', [ACPerbaikanController::class, 'permintaan']);

    // Penawaran pemasangan: dibaca, disetujui, atau diminta revisinya.
    // Persetujuannya mengikat, jadi server menuntut pernyataan setuju yang
    // eksplisit — bukan sekadar halaman yang terbuka.
    Route::get('/servis-ac/penawaran/{nomor}', [PenawaranACController::class, 'show']);
    Route::post('/servis-ac/penawaran/{nomor}/setujui', [PenawaranACController::class, 'setujui']);
    Route::post('/servis-ac/penawaran/{nomor}/revisi', [PenawaranACController::class, 'revisi']);

    Route::post('/servis-ac/freon/checkout', [FreonController::class, 'store']);
    Route::post('/servis-ac/freon/{nomor}/setujui', [FreonController::class, 'setujui']);
    Route::post('/servis-ac/freon/{nomor}/tolak', [FreonController::class, 'tolak']);

    // BisaBersih Deep Cleaning: dijual per paket; luas & ruangan standar sudah
    // termasuk, kelebihannya dan layanan tambahan dihitung ulang server.
    Route::post('/bersih/deep/checkout', [DeepCheckoutController::class, 'store']);

    // BisaBersih Kantor: pesan langsung (kantor kecil/menengah).
    Route::post('/bersih/kantor/checkout', [KantorCheckoutController::class, 'store']);
    // Permintaan penawaran — dapat nomor REQ- sendiri.
    Route::post('/bersih/kantor/permintaan', [KantorPermintaanController::class, 'store']);
    Route::get('/bersih/kantor/permintaan/{nomor}', [KantorPermintaanController::class, 'show']);
    Route::get('/bersih/kantor/permintaan/{nomor}/pdf', [KantorPermintaanController::class, 'pdf']);
    Route::get('/bersih/kantor/permintaan/{nomor}/tautan-pdf', [KantorPermintaanController::class, 'tautanPdf']);

    // Penawaran BisaBersih Kantor: lihat, setujui, ajukan revisi, unduh PDF.
    Route::get('/penawaran', [PenawaranController::class, 'index']);
    Route::get('/penawaran/{nomor}', [PenawaranController::class, 'show']);
    Route::get('/penawaran/{nomor}/pdf', [PenawaranController::class, 'pdf']);
    Route::post('/penawaran/{nomor}/setujui', [PenawaranController::class, 'setujui']);
    Route::post('/penawaran/{nomor}/revisi', [PenawaranController::class, 'revisi']);

    // FR-11: Dompet Mitra & payout
    Route::get('/wallet', [WalletController::class, 'show']);
    Route::get('/wallet/transactions', [WalletController::class, 'transactions']);
    Route::apiResource('payout-requests', PayoutRequestController::class)->only(['index', 'store', 'show']);

    // FR-12: Rating & ulasan dua arah
    Route::apiResource('tasks.reviews', ReviewController::class)->shallow()->only(['index', 'store']);

    // FR-15: Dispute/komplain
    Route::apiResource('tasks.disputes', DisputeController::class)->shallow()->only(['index', 'store', 'show']);

    // FR-02/FR-14/FR-15: Admin — dibatasi role admin
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index']);
        Route::apiResource('users', AdminUserController::class)->only(['index', 'show', 'update']);
        Route::patch('/disputes/{dispute}', [DisputeController::class, 'update']);
        Route::patch('/payout-requests/{payoutRequest}', [PayoutRequestController::class, 'update']);
        Route::apiResource('categories', CategoryController::class)->except(['index']);

        // Permintaan penawaran BisaBersih Kantor: tinjau & majukan tahapnya.
        Route::get('/permintaan', [AdminPermintaanController::class, 'index']);
        Route::get('/permintaan/{nomor}', [AdminPermintaanController::class, 'show']);
        Route::patch('/permintaan/{nomor}/tahap', [AdminPermintaanController::class, 'majukan']);
    });
});
