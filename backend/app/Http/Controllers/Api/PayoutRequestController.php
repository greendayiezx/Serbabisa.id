<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\PayoutRequest;
use App\Services\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class PayoutRequestController extends Controller
{
    /** Riwayat penarikan milik mitra yang sedang login. */
    public function index(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet()->firstOrFail();

        return response()->json(
            $wallet->payoutRequests()->latest()->get()
        );
    }

    /**
     * Mitra mengajukan penarikan saldo. Dana langsung dipotong dari saldo saat
     * pengajuan (lewat buku besar) supaya tidak bisa dibelanjakan ganda; bila
     * nanti ditolak, dana dikembalikan otomatis.
     */
    public function store(Request $request, WalletLedger $ledger): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isMitra(), 403, 'Hanya mitra yang dapat mengajukan penarikan.');

        $validated = $request->validate([
            'jumlah' => ['required', 'numeric', 'min:10000'],
            'bank_tujuan' => ['required', 'string', 'max:100'],
            'no_rekening' => ['required', 'string', 'max:50'],
        ]);

        $wallet = $user->wallet()->firstOrFail();

        $payout = DB::transaction(function () use ($wallet, $validated, $ledger, $user) {
            $payout = $wallet->payoutRequests()->create([
                'jumlah' => $validated['jumlah'],
                'status' => 'pending',
                'bank_tujuan' => $validated['bank_tujuan'],
                'no_rekening' => $validated['no_rekening'],
            ]);

            $ledger->debit(
                wallet: $wallet,
                tipe: 'payout',
                jumlah: $validated['jumlah'],
                referensi: $payout,
                keterangan: 'Pengajuan penarikan saldo',
                idempotencyKey: "payout-req:{$payout->id}",
                dibuatOleh: $user->id,
            );

            return $payout;
        });

        return response()->json($payout->fresh(), 201);
    }

    public function show(Request $request, PayoutRequest $payoutRequest): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin() || $payoutRequest->wallet->user_id === $user->id,
            403,
        );

        return response()->json($payoutRequest);
    }

    /**
     * Admin memproses penarikan. Transisi hanya boleh dari status belum final.
     * Bila ditolak, dana dikembalikan ke saldo mitra lewat buku besar.
     * Bila selesai, dana memang sudah keluar saat pengajuan — tak ada mutasi lagi.
     */
    public function update(Request $request, PayoutRequest $payoutRequest, WalletLedger $ledger): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['processing', 'completed', 'rejected'])],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        abort_if(
            in_array($payoutRequest->status, ['completed', 'rejected'], true),
            422,
            'Penarikan sudah final dan tidak bisa diubah.',
        );

        DB::transaction(function () use ($payoutRequest, $validated, $ledger, $request) {
            if ($validated['status'] === 'rejected') {
                $ledger->credit(
                    wallet: $payoutRequest->wallet,
                    tipe: 'payout_reversal',
                    jumlah: (float) $payoutRequest->jumlah,
                    referensi: $payoutRequest,
                    keterangan: 'Penarikan ditolak — dana dikembalikan',
                    idempotencyKey: "payout-reversal:{$payoutRequest->id}",
                    dibuatOleh: $request->user()->id,
                );
            }

            $payoutRequest->update([
                'status' => $validated['status'],
                'catatan' => $validated['catatan'] ?? $payoutRequest->catatan,
                'diproses_oleh' => $request->user()->id,
                'processed_at' => now(),
            ]);
        });

        return response()->json($payoutRequest->fresh());
    }
}
