<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Rute jalan sebenarnya antara dua titik.
 *
 * Dipakai untuk DUA hal sekaligus, dan justru itu alasannya ditaruh di server:
 * garis yang digambar di peta dan jarak yang ditagih harus berasal dari
 * perhitungan yang sama. Kalau petanya digambar klien lewat layanan rute
 * sementara tagihannya memakai garis lurus, peta akan berkata 9,1 km sambil
 * nota berkata 7,4 km — dan yang salah di mata penumpang selalu notanya.
 *
 * Kalau layanan rutenya tidak bisa dihubungi, jawabannya null dan pemanggil
 * kembali memakai perkiraan garis lurus. Perjalanan tetap bisa dipesan;
 * yang hilang cuma ketepatan, dan itu disebutkan di layar.
 */
class RuteJalan
{
    /** Rute yang sama jarang berubah dalam hitungan menit. */
    private const SIMPAN_DETIK = 600;

    /** Layanan rute tidak boleh menahan checkout lebih lama dari ini. */
    private const BATAS_DETIK = 4;

    /**
     * @return array{km:float, menit:int, geometri:list<array{0:float,1:float}>}|null
     */
    public function cari(float $lat1, float $lng1, float $lat2, float $lng2): ?array
    {
        $token = config('services.mapbox.token');
        if (! $token) {
            return null;
        }

        // Dibulatkan lima desimal (~1 meter) supaya geseran pin sekecil itu
        // tidak memaksa panggilan baru ke penyedia rute.
        $kunci = sprintf('rute:%.5f,%.5f:%.5f,%.5f', $lat1, $lng1, $lat2, $lng2);

        return Cache::remember($kunci, self::SIMPAN_DETIK, function () use ($lat1, $lng1, $lat2, $lng2, $token) {
            try {
                $res = Http::timeout(self::BATAS_DETIK)
                    ->get("https://api.mapbox.com/directions/v5/mapbox/driving/{$lng1},{$lat1};{$lng2},{$lat2}", [
                        'geometries' => 'geojson',
                        'overview' => 'full',
                        'access_token' => $token,
                    ]);

                if (! $res->successful()) {
                    return null;
                }

                $rute = $res->json('routes.0');
                if (! $rute || ! isset($rute['distance'], $rute['geometry']['coordinates'])) {
                    return null;
                }

                return [
                    'km' => round($rute['distance'] / 1000, 2),
                    'menit' => max(1, (int) ceil(($rute['duration'] ?? 0) / 60)),
                    // GeoJSON menulis [lng, lat]; Leaflet membaca [lat, lng].
                    // Dibalik di sini supaya kekeliruan itu tidak menyebar ke
                    // tiap pemakai — dan peta yang tertukar sumbunya mendarat
                    // di Samudra Hindia tanpa satu pun galat muncul.
                    'geometri' => array_map(
                        fn ($t) => [(float) $t[1], (float) $t[0]],
                        $rute['geometry']['coordinates'],
                    ),
                ];
            } catch (\Throwable $e) {
                Log::warning('Rute jalan gagal diambil', ['pesan' => $e->getMessage()]);

                return null;
            }
        });
    }
}
