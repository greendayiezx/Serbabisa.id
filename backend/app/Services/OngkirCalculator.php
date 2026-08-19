<?php

namespace App\Services;

/**
 * Tarif ongkir & biaya layanan BisaBelanja — port dari frontend src/lib/ongkir.ts
 * agar backend menghitung angka yang persis sama. `sampaiKm` inklusif: jarak
 * tepat 5 km masuk tier pertama.
 */
class OngkirCalculator
{
    /** Biaya layanan flat per pesanan. */
    public const BIAYA_LAYANAN = 2500;

    /** [sampaiKm, tarif]. */
    private const TARIF = [[5, 3500], [10, 6500], [15, 9500]];

    private const KENAIKAN_PER_BAND = 3000;
    private const BAND_KM = 5;

    public function untuk(float $km): float
    {
        if ($km <= 0) {
            return self::TARIF[0][1];
        }
        foreach (self::TARIF as [$sampai, $tarif]) {
            if ($km <= $sampai) {
                return $tarif;
            }
        }
        $bandEkstra = (int) ceil(($km - 15) / self::BAND_KM);

        return self::TARIF[count(self::TARIF) - 1][1] + $bandEkstra * self::KENAIKAN_PER_BAND;
    }
}
