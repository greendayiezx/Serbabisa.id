<?php

use App\Http\Controllers\Api\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Api\Admin\UserController as AdminUserController;
use App\Http\Controllers\Api\AngkutCheckoutController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BelanjaCheckoutController;
use App\Http\Controllers\Api\BidController;
use App\Http\Controllers\Api\CategoryController;
use App\Http\Controllers\Api\DisputeController;
use App\Http\Controllers\Api\LanggananController;
use App\Http\Controllers\Api\MessageController;
use App\Http\Controllers\Api\PaymentController;
use App\Http\Controllers\Api\PayoutRequestController;
use App\Http\Controllers\Api\ReviewController;
use App\Http\Controllers\Api\TaskController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::get('/ping', fn () => response()->json(['status' => 'ok']));

// FR-01: Auth — dibatasi rate limit untuk mencegah brute force & spam.
// throttle:5,1 = maksimal 5 percobaan per menit per IP.
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');

// FR-16: Public category listing (kategori tetap, basis harga)
Route::get('/categories', [CategoryController::class, 'index']);

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    // FR-03/04/05/07/16: Task management (kategori tetap & custom, radius search, status)
    Route::apiResource('tasks', TaskController::class);

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
    });
});
