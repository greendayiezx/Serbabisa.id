<?php

namespace App\Services;

use App\Models\Task;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * Penerbit nomor invoice Serbabisa — port persis dari frontend/src/lib/invoice.ts.
 *
 * Format: SBB-260818-7K4M2X6
 *   SBB      Serbabisa BisaBelanja
 *   260818   tanggal YYMMDD
 *   7K4M2X   6 karakter acak
 *   6        checksum
 *
 * Algoritmanya sengaja dikembarkan dengan versi TypeScript, bukan diganti dengan
 * auto-increment: nomor yang diterbitkan server tetap lolos invoiceValid() di
 * browser, jadi halaman invoice bisa memeriksa salah ketik tanpa memanggil API.
 * Kalau salah satu sisi diubah, ubah keduanya — ada tes yang menjaganya.
 *
 * Yang berubah dari versi frontend: keunikan tidak lagi bergantung pada peluang.
 * terbitkan() mencoba ulang sampai dapat nomor yang belum ada di tabel, dan
 * kolom `tasks.nomor_invoice` unik jadi jaring pengaman terakhir kalau dua
 * request menerbitkan nomor sama pada saat bersamaan.
 */
class NomorInvoice
{
    /** 32 karakter Crockford tanpa I, L, O, U — gampang dibacakan lewat telepon. */
    public const ALFABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

    public const PANJANG_ACAK = 6;

    private const MAKS_PERCOBAAN = 12;

    /**
     * @return array{invoice:string,pesanan:string}
     */
    public function terbitkan(?Carbon $waktu = null): array
    {
        $waktu ??= Carbon::now();

        for ($i = 0; $i < self::MAKS_PERCOBAAN; $i++) {
            $nomor = $this->buat($waktu);
            if (! Task::where('nomor_invoice', $nomor['invoice'])->exists()) {
                return $nomor;
            }
        }

        throw new RuntimeException('Gagal menerbitkan nomor invoice unik setelah '.self::MAKS_PERCOBAAN.' percobaan.');
    }

    /** @return array{invoice:string,pesanan:string} */
    public function buat(?Carbon $waktu = null): array
    {
        $tanggal = ($waktu ?? Carbon::now())->format('ymd');
        $acak = $this->acak(self::PANJANG_ACAK);
        $kode = $acak.self::checksum($tanggal.$acak);

        return ['invoice' => "SBB-{$tanggal}-{$kode}", 'pesanan' => $kode];
    }

    public static function valid(string $invoice): bool
    {
        if (! preg_match('/^SBB-(\d{6})-([0-9A-Z]{7})$/', strtoupper(trim($invoice)), $m)) {
            return false;
        }

        [, $tanggal, $kode] = $m;

        return self::checksum($tanggal.substr($kode, 0, self::PANJANG_ACAK)) === $kode[self::PANJANG_ACAK];
    }

    /** Checksum berbobot posisi; karakter di luar alfabet dilewati. */
    public static function checksum(string $inti): string
    {
        $jumlah = 0;
        $panjang = strlen($inti);

        for ($i = 0; $i < $panjang; $i++) {
            $nilai = strpos(self::ALFABET, $inti[$i]);
            if ($nilai !== false) {
                $jumlah += $nilai * ($i + 1);
            }
        }

        return self::ALFABET[$jumlah % strlen(self::ALFABET)];
    }

    private function acak(int $panjang): string
    {
        $keluar = '';
        $batas = strlen(self::ALFABET) - 1;

        for ($i = 0; $i < $panjang; $i++) {
            $keluar .= self::ALFABET[random_int(0, $batas)];
        }

        return $keluar;
    }
}
