<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

/**
 * Unggah foto pendukung sebuah tugas.
 *
 * Dipakai form "Minta Penawaran" BisaBersih Kantor: foto area membuat tim bisa
 * menaksir pekerjaan sebelum survei. Terpisah dari POST /tasks karena tugasnya
 * dibuat lebih dulu — fotonya menyusul dan boleh gagal tanpa membatalkan
 * permintaan penawaran yang sudah tercatat.
 */
class TaskFotoController extends Controller
{
    /** Maksimal foto yang disimpan per tugas. */
    private const MAKS_FOTO = 6;

    public function store(Request $request, Task $task): JsonResponse
    {
        // Hanya pemiliknya. Mitra tidak boleh menambah foto ke tugas orang.
        abort_unless($task->customer_id === $request->user()->id, 403);

        $data = $request->validate([
            'foto' => ['required', 'array', 'max:'.self::MAKS_FOTO],
            // Tipe dibatasi di sisi server juga: nama berkas dari klien tidak
            // bisa dipercaya menentukan isinya.
            'foto.*' => ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);

        $tersimpan = $task->foto ?? [];

        foreach ($data['foto'] as $berkas) {
            if (count($tersimpan) >= self::MAKS_FOTO) {
                break;
            }
            // store() memberi nama acak — nama asli dari klien tidak dipakai
            // supaya tidak ada yang bisa menabrak berkas lain atau keluar
            // dari foldernya.
            $tersimpan[] = $berkas->store("tasks/{$task->id}", 'public');
        }

        $task->update(['foto' => $tersimpan]);

        return response()->json([
            'foto' => $tersimpan,
            'url' => array_map(fn ($p) => Storage::disk('public')->url($p), $tersimpan),
        ], 201);
    }
}
