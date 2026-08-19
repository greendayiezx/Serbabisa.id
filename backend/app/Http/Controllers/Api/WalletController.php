<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WalletController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json(
            $request->user()->wallet()->with('payoutRequests')->firstOrFail()
        );
    }

    /** Riwayat mutasi saldo (buku besar), terbaru dulu, dipaginasi. */
    public function transactions(Request $request): JsonResponse
    {
        $wallet = $request->user()->wallet()->firstOrFail();

        return response()->json(
            $wallet->transactions()->latest()->paginate(20)
        );
    }
}
