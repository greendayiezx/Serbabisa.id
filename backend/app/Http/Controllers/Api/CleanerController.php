<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\LevelCleaner;
use Illuminate\Http\JsonResponse;

/**
 * Daftar cleaner BisaBersih.
 *
 * Semuanya dari data nyata: nama diambil dari akun mitra, level DIHITUNG dari
 * ulasan yang benar-benar diterima, dan jumlah order dari tugas yang sudah
 * selesai. Tidak ada nilai contoh — kalau belum ada mitra terdaftar, daftarnya
 * memang kosong, dan halaman pemesanan menyatakannya apa adanya.
 */
class CleanerController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'cleaner' => LevelCleaner::daftarTersedia(),
            // Jenjangnya ikut dikirim supaya halaman bisa menerangkan cara naik
            // level tanpa menyalin ambangnya sendiri.
            'jenjang' => LevelCleaner::jenjang(),
            'markup_per_jam' => LevelCleaner::MARKUP_PER_JAM,
            'harga_terendah_per_jam' => LevelCleaner::hargaPerJam(LevelCleaner::LEVEL_TERENDAH),
        ]);
    }
}
