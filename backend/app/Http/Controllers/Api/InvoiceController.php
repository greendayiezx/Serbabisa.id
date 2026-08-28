<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * Invoice pesanan dalam bentuk PDF.
 *
 * Berlaku untuk semua layanan: yang dicetak adalah tugas beserta baris item dan
 * pembayarannya, bukan sesuatu yang khusus BisaBersih.
 *
 * Dua endpoint karena satu saja tidak cukup:
 *
 * - `tautan` dipanggil aplikasi dengan token Sanctum, dan hanya mengembalikan
 *   URL bertanda tangan.
 * - `berkas` dibuka peramban lewat URL itu. Perpindahan halaman tidak bisa
 *   membawa header Authorization, jadi yang jadi bukti akses adalah tanda
 *   tangan pada URL-nya.
 */
class InvoiceController extends Controller
{
    public function tautan(Request $request, string $nomor): JsonResponse
    {
        $task = $this->milikPengguna($request, $nomor);

        return response()->json([
            // RELATIF, bukan absolut: APP_URL bisa berbeda dari alamat server
            // yang sebenarnya dipakai, dan tanda tangannya ikut menghitung URL
            // penuh — yang absolut gagal verifikasi begitu hostnya beda.
            'url' => URL::temporarySignedRoute(
                'invoice.berkas',
                now()->addMinutes(60),
                ['task' => $task->id],
                absolute: false,
            ),
        ]);
    }

    public function berkas(Task $task)
    {
        return $this->buat($task)
            // `stream` memakai Content-Disposition: inline, jadi peramban
            // MENAMPILKAN berkasnya alih-alih langsung mengunduhnya.
            ->stream("Invoice-{$task->nomor_invoice}.pdf");
    }

    /**
     * Nomor invoice boleh ditulis penuh maupun potongan belakangnya —
     * sama seperti nomor yang dipakai di URL halaman status.
     */
    private function milikPengguna(Request $request, string $nomor): Task
    {
        $nomor = strtoupper(trim($nomor));

        return Task::query()
            ->where('customer_id', $request->user()->id)
            ->where(fn ($q) => $q
                ->where('nomor_invoice', $nomor)
                ->orWhere('nomor_invoice', 'like', '%-'.$nomor))
            ->firstOrFail();
    }

    private function buat(Task $task)
    {
        $task->loadMissing(['items', 'payment', 'customer']);

        return Pdf::loadView('invoice.pdf', [
            'task' => $task,
            'detail' => $task->detail_layanan ?? [],
        ])->setPaper('a4');
    }
}
