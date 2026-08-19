<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionPlan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Langganan BisaBelanja.
 *
 * Sebelum ini status langganan hanya hidup di localStorage browser, sehingga
 * halaman checkout memotong Rp1.500 dan menggratiskan ongkir untuk langganan
 * yang tidak diketahui server sama sekali — layar menampilkan satu angka,
 * server menagih angka lain. Endpoint ini yang menutup selisih itu: setelah
 * berlangganan, `subscription_id` dikirim saat checkout dan potongannya
 * dihitung ulang di server dari kuota yang sebenarnya tersisa.
 */
class LanggananController extends Controller
{
    /** Paket yang tersedia + langganan aktif milik user (kalau ada). */
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'paket' => SubscriptionPlan::where('is_active', true)->orderBy('harga')->get(),
            'aktif' => $this->aktif($request->user()->id),
        ]);
    }

    /**
     * Berlangganan sebuah paket.
     *
     * Pembayaran paket BELUM ada — `harga_dibayar` dicatat sebagai harga paket
     * supaya angkanya bisa direkonsiliasi nanti, tapi tidak ada uang yang benar-
     * benar berpindah dan saldo tidak disentuh. Saat gateway pembayaran masuk,
     * status awal diubah jadi 'pending' dan baru jadi 'active' setelah lunas.
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'kode' => ['required', 'string', Rule::exists('subscription_plans', 'kode')->where('is_active', true)],
        ]);

        $user = $request->user();
        $plan = SubscriptionPlan::where('kode', $data['kode'])->firstOrFail();

        $langganan = DB::transaction(function () use ($user, $plan) {
            // Satu langganan aktif per user: yang lama dibatalkan, bukan
            // ditumpuk, supaya kuota tidak bisa digandakan dengan berlangganan
            // berkali-kali.
            Subscription::where('user_id', $user->id)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            return Subscription::create([
                'user_id' => $user->id,
                'subscription_plan_id' => $plan->id,
                'status' => 'active',
                'harga_dibayar' => $plan->harga,
                'mulai_pada' => now(),
                'berakhir_pada' => now()->addMonth(),
                'sisa_kuota_diskon' => $plan->kuota_diskon,
                'sisa_kuota_ongkir' => $plan->kuota_ongkir,
            ]);
        });

        return response()->json($langganan->load('plan'), 201);
    }

    /** Berhenti berlangganan. */
    public function destroy(Request $request): JsonResponse
    {
        Subscription::where('user_id', $request->user()->id)
            ->where('status', 'active')
            ->update(['status' => 'cancelled']);

        return response()->json(['aktif' => null]);
    }

    /**
     * Langganan aktif yang belum lewat masa berlakunya.
     *
     * Yang kedaluwarsa ditandai 'expired' di sini, bukan lewat penjadwal, supaya
     * tidak ada langganan mati yang masih dipakai memotong harga.
     */
    private function aktif(int $userId): ?Subscription
    {
        $langganan = Subscription::with('plan')
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->latest('id')
            ->first();

        if ($langganan && $langganan->berakhir_pada && $langganan->berakhir_pada->isPast()) {
            $langganan->update(['status' => 'expired']);

            return null;
        }

        return $langganan;
    }
}
