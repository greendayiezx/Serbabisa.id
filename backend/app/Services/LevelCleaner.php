<?php

namespace App\Services;

use App\Models\MitraProfile;
use App\Models\Task;
use App\Models\Wallet;

/**
 * Jenjang level cleaner BisaBersih.
 *
 * Model pendapatan platform: cleaner menetapkan tarifnya menurut level, dan
 * Serbabisa MENAMBAH markup tetap di atasnya. Jadi keuntungan per jam sama besar
 * untuk semua level — cleaner yang naik level menerima lebih banyak tanpa
 * mengurangi bagian platform.
 *
 * Level TIDAK diisi manual. Ia dihitung dari data nyata: berapa ulasan yang
 * sudah diterima cleaner dan berapa rata-ratanya. Customer memberi rating,
 * levelnya naik sendiri.
 */
class LevelCleaner
{
    /** Ditambahkan ke tarif cleaner, sama untuk semua level. */
    public const MARKUP_PER_JAM = 10_000;

    /** Hadiah sekali tiap cleaner naik satu tingkat. Ditanggung platform. */
    public const BONUS_NAIK_LEVEL = 100_000;

    /**
     * Jenjangnya.
     *
     * `min_ulasan` memakai jumlah ulasan, bukan jumlah pesanan: pesanan yang
     * tidak dinilai tidak membuktikan apa-apa tentang mutu kerjanya.
     *
     * Angka tarif dan ambangnya adalah ASUMSI awal — yang ditetapkan pemilik
     * baru tarif level tertinggi (Rp70.000/jam). Sisanya diturunkan dengan
     * jarak Rp10.000 antarlevel supaya kenaikannya terasa rata.
     */
    private const LEVEL = [
        1 => ['nama' => 'Pemula', 'tarif' => 40_000, 'min_ulasan' => 0, 'min_rating' => 0.0],
        2 => ['nama' => 'Terampil', 'tarif' => 50_000, 'min_ulasan' => 20, 'min_rating' => 4.0],
        3 => ['nama' => 'Ahli', 'tarif' => 60_000, 'min_ulasan' => 60, 'min_rating' => 4.5],
        4 => ['nama' => 'Master', 'tarif' => 70_000, 'min_ulasan' => 150, 'min_rating' => 4.8],
    ];

    public const LEVEL_TERENDAH = 1;

    public static function levelTertinggi(): int
    {
        return max(array_keys(self::LEVEL));
    }

    /** @return array<int, array{level:int,nama:string,tarif:int,harga:int,min_ulasan:int,min_rating:float}> */
    public static function jenjang(): array
    {
        $keluar = [];
        foreach (self::LEVEL as $lv => $d) {
            $keluar[] = [
                'level' => $lv,
                'nama' => $d['nama'],
                'tarif' => $d['tarif'],
                'harga' => $d['tarif'] + self::MARKUP_PER_JAM,
                'min_ulasan' => $d['min_ulasan'],
                'min_rating' => $d['min_rating'],
            ];
        }

        return $keluar;
    }

    /** Upah cleaner per jam pada level tertentu. */
    public static function tarifCleaner(int $level): int
    {
        return (self::LEVEL[$level] ?? self::LEVEL[self::LEVEL_TERENDAH])['tarif'];
    }

    /** Yang dibayar customer per jam: tarif cleaner + markup platform. */
    public static function hargaPerJam(int $level): int
    {
        return self::tarifCleaner($level) + self::MARKUP_PER_JAM;
    }

    public static function namaLevel(int $level): string
    {
        return (self::LEVEL[$level] ?? self::LEVEL[self::LEVEL_TERENDAH])['nama'];
    }

    /**
     * Level yang layak untuk seorang cleaner, dari ulasan yang benar-benar ada.
     *
     * Dihitung dari jenjang tertinggi ke bawah supaya cleaner selalu mendapat
     * level terbaik yang syaratnya sudah ia penuhi.
     */
    public static function levelDari(int $jumlahUlasan, float $rataRating): int
    {
        foreach (array_reverse(self::LEVEL, true) as $lv => $d) {
            if ($jumlahUlasan >= $d['min_ulasan'] && $rataRating >= $d['min_rating']) {
                return $lv;
            }
        }

        return self::LEVEL_TERENDAH;
    }

    public static function levelMitra(MitraProfile $profil): int
    {
        return self::levelDari((int) $profil->rating_count, (float) $profil->rating_avg);
    }

    /**
     * Bayar bonus untuk tiap tingkat yang baru dilewati cleaner.
     *
     * Idempoten lewat kunci per level: memanggil ini dua kali setelah pesanan
     * yang sama tidak menggandakan bonus, dan cleaner yang melompat dua level
     * sekaligus tetap menerima haknya untuk kedua tingkat itu.
     */
    public function bayarBonusNaikLevel(MitraProfile $profil, WalletLedger $ledger): int
    {
        $level = self::levelMitra($profil);
        if ($level <= self::LEVEL_TERENDAH) {
            return 0;
        }

        $wallet = Wallet::firstOrCreate(['user_id' => $profil->user_id], ['saldo' => 0]);
        $dibayar = 0;

        for ($lv = self::LEVEL_TERENDAH + 1; $lv <= $level; $lv++) {
            $sebelum = $wallet->saldo;
            $ledger->credit(
                wallet: $wallet,
                // Kolom tipe di wallet_transactions adalah enum tertutup dan belum
                // punya nilai 'bonus'. Yang membedakan bonus ini dari penyesuaian
                // lain adalah idempotency key-nya, yang berawalan 'naik-level:'.
                tipe: 'adjustment',
                jumlah: self::BONUS_NAIK_LEVEL,
                referensi: null,
                keterangan: 'Bonus naik ke level '.self::namaLevel($lv),
                idempotencyKey: "naik-level:{$profil->user_id}:{$lv}",
            );
            $wallet->refresh();
            if ($wallet->saldo != $sebelum) {
                $dibayar += self::BONUS_NAIK_LEVEL;
            }
        }

        return $dibayar;
    }

    /**
     * Berapa jam kerja yang dibutuhkan untuk menutup satu bonus naik level.
     *
     * Dipakai laporan ekonomi: markup per jam adalah satu-satunya pemasukan,
     * jadi bonus ini harus terbayar dari jam kerja cleaner itu sendiri.
     */
    public static function jamUntukMenutupBonus(): int
    {
        return (int) ceil(self::BONUS_NAIK_LEVEL / self::MARKUP_PER_JAM);
    }

    /**
     * Ringkasan satu cleaner untuk ditampilkan di aplikasi.
     *
     * Semua angkanya turunan dari data nyata: level dihitung dari ulasan yang
     * diterima, order dihitung dari tugas yang benar-benar selesai. Tidak ada
     * yang disimpan sebagai nilai tetap yang bisa basi.
     */
    public static function ringkas(MitraProfile $p): array
    {
        $level = self::levelMitra($p);

        return [
            'id' => (string) $p->user_id,
            'nama' => $p->user?->name ?? '',
            // Menentukan ilustrasi avatar; null = avatar netral.
            'gender' => $p->gender,
            'level' => $level,
            'nama_level' => self::namaLevel($level),
            'harga_per_jam' => self::hargaPerJam($level),
            // Nol berarti benar-benar belum ada ulasan — bukan nilai
            // sementara yang dikarang.
            'rating' => round((float) $p->rating_avg, 2),
            'jumlah_ulasan' => (int) $p->rating_count,
            'order_selesai' => Task::where('mitra_id', $p->user_id)
                ->where('status', 'completed')->count(),
        ];
    }

    /** Cleaner yang siap ditugaskan, berikut level yang dihitung dari datanya. */
    public static function daftarTersedia(): array
    {
        return MitraProfile::with('user')
            ->get()
            ->filter(fn ($p) => $p->user !== null)
            ->map(fn (MitraProfile $p) => self::ringkas($p))
            ->values()
            ->all();
    }
}
