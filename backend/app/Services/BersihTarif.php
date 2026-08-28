<?php

namespace App\Services;

/**
 * Tarif BisaBersih — port dari frontend/src/lib/hargaBersih.ts.
 *
 * Sumber kebenaran harga ada di server: klien hanya mengirim PILIHAN (durasi,
 * jumlah cleaner, add-on, frekuensi, dan cleaner mana). Total dihitung ulang di
 * sini, jadi harga yang dikirim browser tidak dipercaya sama sekali.
 *
 * Pendapatan platform seluruhnya berasal dari MARKUP di atas tarif cleaner —
 * lihat LevelCleaner. Tarif per jam tidak lagi tetap: ia mengikuti level cleaner
 * yang dipilih, sehingga cleaner berpengalaman menerima lebih banyak tanpa
 * mengurangi bagian platform.
 *
 * Kalau angka di sini diubah, ubah juga di lib TypeScript-nya — layar dan
 * tagihan harus menyebut angka yang sama. `npm run cek:laba` menjaga sisi TS.
 */
class BersihTarif
{
    public const BIAYA_PERJALANAN = 20000;

    public const DURASI = [2, 3, 4];

    public const MAKS_CLEANER = 2;

    private const KONDISI = [
        'normal' => ['label' => 'Normal', 'pengali' => 1.0],
        'cukup' => ['label' => 'Cukup Kotor', 'pengali' => 1.15],
        'sangat' => ['label' => 'Sangat Kotor', 'pengali' => 1.3],
    ];

    private const ADD_ON = [
        'kaca' => ['label' => 'Cuci Kaca', 'harga' => 30000],
        'sofa' => ['label' => 'Vacuum Sofa', 'harga' => 50000],
        'kulkas' => ['label' => 'Bersihkan Kulkas', 'harga' => 35000],
    ];

    private const FREKUENSI = [
        'sekali' => ['label' => 'Sekali panggil', 'diskon' => 0.0],
        'mingguan' => ['label' => 'Mingguan', 'diskon' => 0.15],
        'bulanan' => ['label' => 'Bulanan', 'diskon' => 0.05],
    ];

    /**
     * Katalog promo BisaBersih — cermin dari frontend/src/lib/promoBersih.ts.
     *
     * Sementara ditulis di sini, bukan di tabel `promos`. Tabel itu belum punya
     * kolom pembatas layanan, sehingga menaruh BERSIHBARU40 di sana membuatnya
     * ikut bisa dipakai di checkout BisaBelanja. Pindahkan ke DB begitu `promos`
     * punya cakupan layanan — kuota dan promo_redemptions baru berguna setelah
     * itu.
     *
     * `jenis`: 'potongan' mengurangi tagihan sekarang; 'cashback' TIDAK — ia
     * dicatat sebagai saldo yang cair setelah pesanan selesai. Membedakannya
     * penting: memperlakukan cashback sebagai potongan membuat tagihan berkurang
     * dua kali.
     *
     * `hanya_pertama`: hanya boleh dipakai pada pesanan BisaBersih pertama.
     */
    private const PROMO = [
        'BERSIHBARU60' => ['min' => 400000, 'jenis' => 'potongan', 'nilai' => 60000, 'hanya_pertama' => true],
        'BERSIHBARU50' => ['min' => 250000, 'jenis' => 'potongan', 'nilai' => 50000, 'hanya_pertama' => true],
        'BERSIHBARU40' => ['min' => 180000, 'jenis' => 'potongan', 'nilai' => 40000, 'hanya_pertama' => true],
        'TRAKTIR30' => ['min' => 200000, 'jenis' => 'potongan', 'nilai' => 30000, 'hanya_pertama' => true],
        'MERDEKA17' => ['min' => 180000, 'jenis' => 'potongan', 'nilai' => 17000, 'hanya_pertama' => false],
        'SAHURBERSIH' => ['min' => 200000, 'jenis' => 'potongan', 'nilai' => 25000, 'hanya_pertama' => false],
        'TAHUNBARU' => ['min' => 300000, 'jenis' => 'potongan', 'nilai' => 40000, 'hanya_pertama' => false],
        'CASHBACK10' => ['min' => 200000, 'jenis' => 'cashback', 'nilai' => 10, 'maks' => 30000, 'hanya_pertama' => false],
        'CASHBACK15' => ['min' => 400000, 'jenis' => 'cashback', 'nilai' => 15, 'maks' => 60000, 'hanya_pertama' => false],
    ];

    /** Promo pengguna baru, terbesar dulu — dipakai saat pengguna tidak memilih. */
    private const URUTAN_PROMO_BARU = ['BERSIHBARU60', 'BERSIHBARU50', 'BERSIHBARU40'];

    /** @return list<string> */
    public static function kodePromo(): array
    {
        return array_keys(self::PROMO);
    }

    /** @return list<string> */
    public static function idKondisi(): array
    {
        return array_keys(self::KONDISI);
    }

    /** @return list<string> */
    public static function idAddOn(): array
    {
        return array_keys(self::ADD_ON);
    }

    /** @return list<string> */
    public static function idFrekuensi(): array
    {
        return array_keys(self::FREKUENSI);
    }

    public function labelKondisi(string $id): string
    {
        return self::KONDISI[$id]['label'] ?? $id;
    }

    public function labelFrekuensi(string $id): string
    {
        return self::FREKUENSI[$id]['label'] ?? $id;
    }

    /** @return array{label:string,harga:int} */
    public function addOn(string $id): array
    {
        return self::ADD_ON[$id] ?? ['label' => $id, 'harga' => 0];
    }

    /**
     * Tentukan promo yang benar-benar berlaku.
     *
     * Kalau pengguna memilih promo tapi syaratnya tidak terpenuhi, pesanan TIDAK
     * ditolak — promonya dilepas dan alasannya dikembalikan, supaya pengguna
     * tahu kenapa tagihannya berbeda dari yang tadi ia lihat. Menolak seluruh
     * pesanan karena satu voucher hangus hanya membuat orang batal memesan.
     *
     * @return array{0:?string,1:int,2:int,3:?string} [kode, potongan, cashback, alasan ditolak]
     */
    private function terapkanPromo(?string $diminta, int $nilai, bool $pesananPertama): array
    {
        if ($diminta !== null) {
            $diminta = strtoupper(trim($diminta));
            $p = self::PROMO[$diminta] ?? null;

            if ($p === null) {
                return [null, 0, 0, 'Kode promo tidak dikenal.'];
            }
            if ($p['hanya_pertama'] && ! $pesananPertama) {
                return [null, 0, 0, 'Promo ini khusus pesanan BisaBersih pertama.'];
            }
            if ($nilai < $p['min']) {
                $kurang = number_format($p['min'] - $nilai, 0, ',', '.');

                return [null, 0, 0, "Belanja Rp{$kurang} lagi untuk memakai {$diminta}."];
            }

            if ($p['jenis'] === 'cashback') {
                // Cashback tidak memotong tagihan sekarang; dicatat sebagai saldo.
                return [$diminta, 0, (int) min($nilai * $p['nilai'] / 100, $p['maks']), null];
            }

            return [$diminta, (int) min($p['nilai'], $nilai), 0, null];
        }

        // Tidak memilih: promo pengguna baru terbesar yang layak dipasang otomatis.
        if ($pesananPertama) {
            foreach (self::URUTAN_PROMO_BARU as $kode) {
                if ($nilai >= self::PROMO[$kode]['min']) {
                    return [$kode, (int) min(self::PROMO[$kode]['nilai'], $nilai), 0, null];
                }
            }
        }

        return [null, 0, 0, null];
    }

    /**
     * Harga jasa pembersihan.
     *
     * Dasarnya tarif per jam menurut LEVEL cleaner (sudah termasuk markup
     * platform), dikali jam kerja dan jumlah cleaner. Pengali kondisi ruangan
     * dipertahankan walau halaman tidak lagi menanyakannya — rumusnya tetap utuh
     * kalau nanti dihidupkan lagi.
     */
    public function hargaLayanan(int $level, string $kondisi, int $durasiJam, int $jumlahCleaner): int
    {
        $pengali = self::KONDISI[$kondisi]['pengali'] ?? 1.0;

        return (int) round(LevelCleaner::hargaPerJam($level) * $durasiJam * $jumlahCleaner * $pengali);
    }

    /** Bagian yang menjadi hak cleaner — sisanya markup platform. */
    public function upahCleaner(int $level, int $durasiJam, int $jumlahCleaner): int
    {
        return LevelCleaner::tarifCleaner($level) * $durasiJam * $jumlahCleaner;
    }

    /**
     * Hitung seluruh tagihan.
     *
     * @param  list<string>  $addOnDipilih
     * @return array{
     *   layanan:int, add_on:int, perjalanan:int, subtotal:int,
     *   diskon_frekuensi:int, nilai_transaksi:int,
     *   promo_kode:?string, potongan_promo:int, total:int,
     *   baris_add_on: list<array{id:string,label:string,harga:int}>
     * }
     */
    public function hitung(
        int $level,
        string $kondisi,
        int $durasiJam,
        int $jumlahCleaner,
        array $addOnDipilih,
        string $frekuensi,
        bool $pesananPertama,
        ?string $promoDiminta = null,
    ): array {
        $hargaLayanan = $this->hargaLayanan($level, $kondisi, $durasiJam, $jumlahCleaner);
        $upahCleaner = $this->upahCleaner($level, $durasiJam, $jumlahCleaner);

        $barisAddOn = [];
        $hargaAddOn = 0;
        foreach (array_unique($addOnDipilih) as $id) {
            if (! isset(self::ADD_ON[$id])) {
                continue;
            }
            $a = self::ADD_ON[$id];
            $barisAddOn[] = ['id' => $id, 'label' => $a['label'], 'harga' => $a['harga']];
            $hargaAddOn += $a['harga'];
        }

        $subtotal = $hargaLayanan + $hargaAddOn + self::BIAYA_PERJALANAN;
        $diskon = (int) round($hargaLayanan * (self::FREKUENSI[$frekuensi]['diskon'] ?? 0.0));
        $nilai = $subtotal - $diskon;

        [$promoKode, $potongan, $cashback, $alasan] = $this->terapkanPromo($promoDiminta, $nilai, $pesananPertama);

        return [
            'layanan' => $hargaLayanan,
            'add_on' => $hargaAddOn,
            'perjalanan' => self::BIAYA_PERJALANAN,
            'subtotal' => $subtotal,
            'diskon_frekuensi' => $diskon,
            'nilai_transaksi' => $nilai,
            'promo_kode' => $promoKode,
            'potongan_promo' => $potongan,
            'cashback' => $cashback,
            'promo_ditolak' => $alasan,
            'level_cleaner' => $level,
            'upah_cleaner' => $upahCleaner,
            // Markup adalah SATU-SATUNYA pemasukan platform dari jasa ini.
            'markup_platform' => $hargaLayanan - $upahCleaner,
            'total' => $nilai - $potongan,
            'baris_add_on' => $barisAddOn,
        ];
    }
}
