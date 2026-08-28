<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\MitraProfile;
use App\Models\Task;
use App\Services\LevelCleaner;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Status pesanan BisaBersih setelah checkout.
 *
 * Halaman status customer memanggil `show` berulang sampai ada cleaner yang
 * menerima. Yang menentukan tampilan adalah STATUS DI DATABASE, bukan timer di
 * browser: selama `status` masih 'pending', pesanan memang belum diterima
 * siapa pun, dan layar tunggu tidak boleh berpura-pura sudah dapat orang.
 */
class BersihPesananController extends Controller
{
    /** Status yang berarti sudah ada cleaner memegang pesanan ini. */
    private const SUDAH_DITERIMA = ['accepted', 'in_progress', 'completed'];

    public function show(Request $request, string $nomor): JsonResponse
    {
        $task = $this->cariMilikCustomer($request, $nomor);

        return response()->json($this->bentuk($task));
    }

    /**
     * Cleaner menerima pesanan.
     *
     * Dua jalur yang sengaja dibedakan:
     * - Customer sudah memilih orangnya  → hanya mitra itu yang boleh menerima.
     * - Customer memilih "cleaner tercepat" → mitra mana pun boleh, dan yang
     *   pertama menekan terima langsung ditugaskan.
     *
     * Perebutan ditangani di dalam transaksi dengan penguncian baris, supaya
     * dua mitra yang menekan bersamaan tidak sama-sama merasa dapat.
     */
    public function terima(Request $request, string $nomor): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isMitra(), 403, 'Hanya mitra yang bisa menerima pesanan.');

        $profil = MitraProfile::where('user_id', $user->id)->first();
        abort_unless($profil !== null, 403, 'Profil mitra belum lengkap.');

        $task = DB::transaction(function () use ($nomor, $user) {
            $task = $this->cariBersih($nomor)->lockForUpdate()->firstOrFail();

            abort_if(
                in_array($task->status, self::SUDAH_DITERIMA, true),
                422,
                'Pesanan ini sudah diambil cleaner lain.',
            );
            abort_if($task->status === 'cancelled', 422, 'Pesanan sudah dibatalkan.');

            // Kalau customer menunjuk orang tertentu, orang lain tidak berhak.
            abort_if(
                $task->mitra_id !== null && $task->mitra_id !== $user->id,
                403,
                'Pesanan ini ditujukan untuk cleaner lain.',
            );

            $task->update([
                'mitra_id' => $user->id,
                'status' => 'accepted',
                'accepted_at' => now(),
            ]);

            return $task;
        });

        return response()->json($this->bentuk($task->fresh()));
    }

    /** Pesanan BisaBersih milik customer yang sedang login. */
    private function cariMilikCustomer(Request $request, string $nomor): Task
    {
        return $this->cariBersih($nomor)
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();
    }

    /**
     * Pencarian dasar berdasarkan nomor invoice.
     *
     * Nomor yang dipakai di URL adalah bagian belakang invoice, jadi pencocokan
     * menerima keduanya — nomor penuh maupun potongannya.
     */
    private function cariBersih(string $nomor)
    {
        $nomor = strtoupper(trim($nomor));

        return Task::query()
            ->where('judul', 'like', 'BisaBersih%')
            ->where(fn ($q) => $q
                ->where('nomor_invoice', $nomor)
                ->orWhere('nomor_invoice', 'like', '%-'.$nomor));
    }

    /**
     * Bentuk data untuk halaman status.
     *
     * Disusun eksplisit, bukan mengirim seluruh model: halaman ini dilihat
     * customer dan tidak perlu tahu isi internal tugas.
     */
    private function bentuk(Task $task): array
    {
        $detail = $task->detail_layanan ?? [];
        $profil = $task->mitra_id
            ? MitraProfile::with('user')->where('user_id', $task->mitra_id)->first()
            : null;

        return [
            'nomor' => $task->nomor_invoice,
            'task_id' => $task->id,
            'status' => $task->status,
            'diterima' => in_array($task->status, self::SUDAH_DITERIMA, true),
            'judul' => $task->judul,
            'deskripsi' => $task->deskripsi,
            'dijadwalkan_pada' => $task->dijadwalkan_pada?->toIso8601String(),
            'durasi_jam' => (int) ($detail['durasi_jam'] ?? 0),
            'jumlah_cleaner' => (int) ($detail['jumlah_cleaner'] ?? 1),
            'nama_level' => $detail['nama_level'] ?? null,
            'area' => $detail['area'] ?? [],
            'catatan' => $task->catatan,
            'lokasi' => [
                'alamat' => $task->lokasi_alamat,
                'lat' => $task->lokasi_lat !== null ? (float) $task->lokasi_lat : null,
                'lng' => $task->lokasi_lng !== null ? (float) $task->lokasi_lng : null,
                'nama_penerima' => $task->nama_penerima,
                'telepon_penerima' => $task->telepon_penerima,
            ],
            'total' => (float) $task->harga,
            'metode' => $task->payment?->metode,
            // null selama belum ada yang menerima — halaman menampilkan layar
            // tunggu, bukan kartu cleaner kosong.
            'cleaner' => $profil ? $this->cleaner($profil, $task) : null,
        ];
    }

    /**
     * Data cleaner untuk customer.
     *
     * Nomor telepon baru ikut SETELAH pesanan diterima. Sebelum itu customer
     * belum punya urusan untuk menghubungi orangnya, jadi nomornya tidak perlu
     * keluar dari server sama sekali.
     */
    private function cleaner(MitraProfile $profil, Task $task): array
    {
        $data = LevelCleaner::ringkas($profil);

        if (in_array($task->status, self::SUDAH_DITERIMA, true)) {
            $data['telepon'] = $profil->user?->phone;
        }

        return $data;
    }
}
