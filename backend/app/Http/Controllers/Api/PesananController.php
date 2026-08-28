<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Satu pesanan milik pengguna, dicari lewat nomor invoice.
 *
 * Berlaku untuk layanan APA PUN. BersihPesananController hanya melayani
 * pesanan yang judulnya diawali "BisaBersih", jadi layanan lain — Servis AC,
 * misalnya — tidak punya jalan membaca pesanannya sendiri tanpa mengetahui id
 * internalnya.
 *
 * Bentuk datanya disusun eksplisit, bukan mengirim seluruh model: halaman ini
 * dilihat pelanggan dan tidak perlu tahu isi internal tugas.
 */
class PesananController extends Controller
{
    public function show(Request $request, string $nomor): JsonResponse
    {
        $nomor = strtoupper(trim($nomor));

        $task = Task::query()
            ->with(['items', 'payment', 'mitra'])
            ->where('customer_id', $request->user()->id)
            ->where(fn ($q) => $q
                ->where('nomor_invoice', $nomor)
                ->orWhere('nomor_invoice', 'like', '%-'.$nomor))
            ->firstOrFail();

        $detail = $task->detail_layanan ?? [];

        return response()->json([
            'nomor' => $task->nomor_invoice,
            'task_id' => $task->id,
            'judul' => $task->judul,
            'deskripsi' => $task->deskripsi,
            'status' => $task->status,
            'layanan' => $detail['layanan'] ?? null,
            'detail_layanan' => $detail,
            'dijadwalkan_pada' => $task->dijadwalkan_pada?->toIso8601String(),
            'dibuat_pada' => $task->created_at?->toIso8601String(),
            'catatan' => $task->catatan,
            'lokasi' => [
                'alamat' => $task->lokasi_alamat,
                'lat' => $task->lokasi_lat !== null ? (float) $task->lokasi_lat : null,
                'lng' => $task->lokasi_lng !== null ? (float) $task->lokasi_lng : null,
            ],
            'items' => $task->items->map(fn ($i) => [
                'nama' => $i->nama,
                'kategori' => $i->kategori,
                'satuan' => $i->satuan,
                'qty' => $i->qty,
                'harga_satuan' => (float) $i->harga_satuan,
                'subtotal' => (float) $i->subtotal,
            ]),
            'total' => (float) $task->harga,
            'potongan' => (float) ($task->payment?->potongan ?? 0),
            'metode' => $task->payment?->metode,
            'mitra' => $task->mitra ? ['nama' => $task->mitra->name] : null,
        ]);
    }
}
