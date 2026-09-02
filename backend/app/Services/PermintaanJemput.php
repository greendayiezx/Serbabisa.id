<?php

namespace App\Services;

use App\Models\Task;

/**
 * Mengukur permintaan BisaJemput di sekitar satu titik jemput.
 *
 * Dipakai untuk menentukan pengali tarif, menggantikan jendela jam tetap.
 * Jendela jam hanya MENEBAK kapan ramai; kelas ini MENGHITUNG berapa banyak
 * orang yang benar-benar sedang memesan di sekitar situ.
 *
 * SATU HAL YANG SENGAJA TIDAK JADI PEMICU: beban server. Aplikasi yang lambat
 * karena servernya kewalahan adalah masalah yang harus diperbaiki, bukan
 * ditagihkan ke penumpang — menaikkan tarif pada saat itu berarti orang
 * membayar lebih mahal karena mesin kami kurang kuat, bukan karena
 * pengemudinya lebih sulit didapat. Yang membuat perjalanan benar-benar lebih
 * mahal untuk disediakan adalah banyaknya permintaan yang berebut pengemudi
 * yang sama, dan itulah yang diukur di sini.
 */
class PermintaanJemput
{
    /** Rentang waktu yang dihitung sebagai "sedang berlangsung". */
    public const JENDELA_MENIT = 15;

    /** Sekitar 5 km — cukup untuk satu kantong permintaan, bukan sekota. */
    public const RADIUS_KM = 5.0;

    /**
     * Ambang permintaan -> pengali.
     *
     * Dibatasi 1,25 dan tidak lebih. Pengali yang bisa melipatgandakan tarif
     * memang menarik pengemudi, tapi ia juga yang membuat orang membayar tiga
     * kali lipat untuk pulang saat hujan — dan itu cerita yang tidak ingin
     * dibawa layanan ini.
     *
     * @var list<array{minimal:int, pengali:float, tingkat:string}>
     */
    public const AMBANG = [
        ['minimal' => 15, 'pengali' => 1.25, 'tingkat' => 'tinggi'],
        ['minimal' => 8, 'pengali' => 1.15, 'tingkat' => 'sedang'],
    ];

    /**
     * @return array{pengali:float, tingkat:string|null, alasan:string|null, permintaan:int}
     */
    public function ukur(float $lat, float $lng): array
    {
        $jumlah = $this->hitungPermintaan($lat, $lng);

        foreach (self::AMBANG as $a) {
            if ($jumlah >= $a['minimal']) {
                return [
                    'pengali' => $a['pengali'],
                    'tingkat' => $a['tingkat'],
                    'alasan' => "Ada {$jumlah} orang memesan di sekitar sini dalam ".
                        self::JENDELA_MENIT.' menit terakhir, jadi pengemudinya lebih sulit didapat.',
                    'permintaan' => $jumlah,
                ];
            }
        }

        return ['pengali' => 1.0, 'tingkat' => null, 'alasan' => null, 'permintaan' => $jumlah];
    }

    /**
     * Permintaan yang belum selesai di sekitar titik itu.
     *
     * Kotak lintang-bujur, bukan lingkaran sejati: selisihnya beberapa ratus
     * meter di sudut kotak, dan itu tidak cukup berarti untuk menukarnya dengan
     * kueri yang tidak bisa memakai indeks.
     */
    private function hitungPermintaan(float $lat, float $lng): int
    {
        $dLat = self::RADIUS_KM / 111.0;
        $dLng = self::RADIUS_KM / (111.0 * max(0.1, cos(deg2rad($lat))));

        return Task::where('detail_layanan->layanan', 'jemput')
            ->where('created_at', '>=', now()->subMinutes(self::JENDELA_MENIT))
            ->whereNull('cancelled_at')
            ->whereBetween('lokasi_lat', [$lat - $dLat, $lat + $dLat])
            ->whereBetween('lokasi_lng', [$lng - $dLng, $lng + $dLng])
            ->count();
    }
}
