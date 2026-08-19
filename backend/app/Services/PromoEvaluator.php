<?php

namespace App\Services;

use App\Models\Promo;
use App\Models\PromoRedemption;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Evaluasi promo di sisi server — port dari src/lib/promo.ts DITAMBAH penegakan
 * kuota yang mustahil dilakukan di frontend.
 *
 * Inilah lapisan anti-abuse: syarat "khusus transaksi pertama", kuota harian,
 * dan kuota per user dicek terhadap tabel promo_redemptions, bukan sekadar
 * dipercaya dari klien.
 *
 * $ctx yang diharapkan:
 *   - totalBarang       (float)
 *   - ongkir            (float)
 *   - subtotalKategori  (array<string,float>)
 *   - transaksiKe       (int)   transaksi Belanja ke berapa bagi user ini
 *   - waktu             (Carbon)
 */
class PromoEvaluator
{
    public function evaluate(Promo $promo, User $user, array $ctx): PromoResult
    {
        $waktu = $ctx['waktu'] ?? Carbon::now();

        if (! $promo->is_active) {
            return PromoResult::gagal('Promo tidak aktif.');
        }
        if ($promo->berlaku_mulai && $waktu->lt($promo->berlaku_mulai)) {
            return PromoResult::gagal('Promo belum berlaku.');
        }
        if ($promo->berlaku_sampai && $waktu->gt($promo->berlaku_sampai)) {
            return PromoResult::gagal('Promo sudah berakhir.');
        }

        if ($alasan = $this->cekSyarat($promo, $ctx, $waktu)) {
            return PromoResult::gagal($alasan);
        }
        if ($alasan = $this->cekKuota($promo, $user, $waktu)) {
            return PromoResult::gagal($alasan);
        }

        $dasar = $this->dasarBelanja($promo, $ctx);
        if ($dasar < (float) $promo->min_belanja) {
            $kurang = (float) $promo->min_belanja - $dasar;

            return PromoResult::gagal('Belanja Rp'.number_format($kurang, 0, ',', '.').' lagi.');
        }

        $totalBarang = (float) ($ctx['totalBarang'] ?? 0);
        $potonganBarang = min((float) $promo->potongan_tetap, $totalBarang);
        $potonganOngkir = $promo->gratis_ongkir ? (float) ($ctx['ongkir'] ?? 0) : 0.0;

        $cashback = 0.0;
        if ($promo->cashback_persen) {
            $cashback = round($dasar * (float) $promo->cashback_persen / 100);
            if ($promo->cashback_maks !== null) {
                $cashback = min($cashback, (float) $promo->cashback_maks);
            }
        }

        return PromoResult::sukses($potonganBarang, $potonganOngkir, $cashback);
    }

    /** Dasar belanja untuk cek minimum: total keranjang, atau subtotal kategori untuk Bundle. */
    private function dasarBelanja(Promo $promo, array $ctx): float
    {
        $kategoriBundle = $promo->kategori_bundle;
        if (! $kategoriBundle) {
            return (float) ($ctx['totalBarang'] ?? 0);
        }

        $subtotal = $ctx['subtotalKategori'] ?? [];

        return collect($kategoriBundle)->sum(fn ($k) => (float) ($subtotal[$k] ?? 0));
    }

    /** Syarat non-nominal (waktu/tanggal/status user/isi keranjang). Null = lolos. */
    private function cekSyarat(Promo $promo, array $ctx, Carbon $waktu): ?string
    {
        $syarat = $promo->syarat ?? [];

        if (isset($syarat['transaksi_ke'])) {
            if ((int) ($ctx['transaksiKe'] ?? 0) !== (int) $syarat['transaksi_ke']) {
                return 'Khusus transaksi pertama.';
            }
        }

        if (isset($syarat['jam_emas'])) {
            if ($this->slotJamEmas($waktu) !== $syarat['jam_emas']) {
                return $syarat['jam_emas'] === 'siang'
                    ? 'Berlaku jam 12.00–14.00.'
                    : 'Berlaku jam 18.00–20.00.';
            }
        }

        if (isset($syarat['payday'])) {
            if ($this->periodePayday($waktu) !== $syarat['payday']) {
                return 'Berlaku tanggal '.$syarat['payday'].'.';
            }
        }

        if (! empty($syarat['weekend']) && ! $this->akhirPekan($waktu)) {
            return 'Hanya Sabtu & Minggu.';
        }

        if (isset($syarat['min_kategori'])) {
            $jumlahKategori = count(array_filter($ctx['subtotalKategori'] ?? [], fn ($v) => $v > 0));
            if ($jumlahKategori < (int) $syarat['min_kategori']) {
                return 'Perlu barang dari minimal '.$syarat['min_kategori'].' kategori berbeda.';
            }
        }

        return null;
    }

    /** Penegakan kuota terhadap promo_redemptions. Null = masih ada jatah. */
    private function cekKuota(Promo $promo, User $user, Carbon $waktu): ?string
    {
        if ($promo->kuota_per_user !== null) {
            $pakaiUser = PromoRedemption::where('promo_id', $promo->id)
                ->where('user_id', $user->id)
                ->count();
            if ($pakaiUser >= $promo->kuota_per_user) {
                return 'Kamu sudah memakai promo ini.';
            }
        }

        if ($promo->kuota_harian !== null) {
            $pakaiHariIni = PromoRedemption::where('promo_id', $promo->id)
                ->whereDate('redeemed_at', $waktu->toDateString())
                ->count();
            if ($pakaiHariIni >= $promo->kuota_harian) {
                return 'Kuota promo hari ini sudah habis.';
            }
        }

        if ($promo->kuota_total !== null) {
            $pakaiTotal = PromoRedemption::where('promo_id', $promo->id)->count();
            if ($pakaiTotal >= $promo->kuota_total) {
                return 'Kuota promo sudah habis.';
            }
        }

        return null;
    }

    private function slotJamEmas(Carbon $waktu): ?string
    {
        $jam = $waktu->hour;
        if ($jam >= 12 && $jam < 14) {
            return 'siang';
        }
        if ($jam >= 18 && $jam < 20) {
            return 'malam';
        }

        return null;
    }

    private function periodePayday(Carbon $waktu): ?string
    {
        $t = $waktu->day;
        if ($t >= 25 && $t <= 27) {
            return '25-27';
        }
        if ($t >= 28 && $t <= 30) {
            return '28-30';
        }
        if ($t === 31 || $t === 1) {
            return '31-1';
        }

        return null;
    }

    private function akhirPekan(Carbon $waktu): bool
    {
        return $waktu->isWeekend();
    }
}
