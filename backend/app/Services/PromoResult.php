<?php

namespace App\Services;

/**
 * Hasil evaluasi sebuah promo terhadap satu keranjang. `cashback` TIDAK memotong
 * tagihan sekarang — ia masuk saldo untuk transaksi berikutnya.
 */
class PromoResult
{
    public function __construct(
        public readonly bool $berlaku,
        public readonly float $potonganBarang = 0,
        public readonly float $potonganOngkir = 0,
        public readonly float $cashback = 0,
        public readonly ?string $alasan = null,
    ) {}

    public static function gagal(string $alasan): self
    {
        return new self(false, alasan: $alasan);
    }

    public static function sukses(float $potonganBarang, float $potonganOngkir, float $cashback): self
    {
        return new self(true, $potonganBarang, $potonganOngkir, $cashback);
    }
}
