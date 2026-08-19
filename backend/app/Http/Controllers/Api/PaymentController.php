<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Task;
use App\Support\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /** Pembayaran untuk sebuah tugas (pemilik pesanan atau admin). */
    public function index(Request $request, Task $task): JsonResponse
    {
        $this->pastikanBoleh($request, $task);

        return response()->json($task->payment()->get());
    }

    public function show(Request $request, Payment $payment): JsonResponse
    {
        $this->pastikanBoleh($request, $payment->task);

        return response()->json($payment->load('task'));
    }

    /**
     * Konfirmasi pembayaran (callback sukses dari payment gateway).
     *
     * Idempoten: pembayaran yang sudah 'paid' dikembalikan apa adanya, jadi
     * callback yang diulang gateway tidak menandai bayar dua kali.
     *
     * CATATAN: di produksi, endpoint webhook Midtrans harus memverifikasi
     * signature (order_id + status_code + gross_amount + server_key) sebelum
     * dipercaya. Di sini masih dibatasi pemilik/admin sebagai penampung awal.
     */
    public function confirm(Request $request, Payment $payment): JsonResponse
    {
        $this->pastikanBoleh($request, $payment->task);

        if ($payment->status === 'paid') {
            return response()->json($payment);
        }

        abort_if(
            ! in_array($payment->status, ['pending'], true),
            422,
            'Pembayaran tidak dalam status yang bisa dikonfirmasi.',
        );

        $payment->update([
            'status' => 'paid',
            'paid_at' => now(),
        ]);

        AuditLog::record('payment.confirmed', $request, [
            'payment_id' => $payment->id,
            'jumlah' => $payment->jumlah,
        ], $payment);

        return response()->json($payment->fresh());
    }

    private function pastikanBoleh(Request $request, ?Task $task): void
    {
        $user = $request->user();
        abort_unless(
            $task && ($user->isAdmin() || $task->customer_id === $user->id || $task->mitra_id === $user->id),
            403,
        );
    }
}
