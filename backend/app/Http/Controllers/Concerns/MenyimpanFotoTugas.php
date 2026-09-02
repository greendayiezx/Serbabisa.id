<?php

namespace App\Http\Controllers\Concerns;

use App\Models\Task;
use Illuminate\Support\Facades\Storage;

/**
 * Foto lampiran pesanan, dikirim sebagai data URL di permintaan yang sama.
 *
 * Pola yang sama dipakai Servis AC dan Disinfektan, jadi aturannya tinggal satu
 * tempat: menyalinnya ke tiap controller berarti pemeriksaan angka ajaib bisa
 * ketinggalan diperbarui di salah satunya, dan itu justru pemeriksaan yang
 * paling tidak boleh berbeda-beda.
 */
trait MenyimpanFotoTugas
{
    /**
     * Dibatasi jumlah dan ukurannya supaya satu permintaan tidak bisa
     * mengirim puluhan megabita.
     *
     * @return array<string, mixed>
     */
    protected function aturanFoto(int $maks = 8): array
    {
        return [
            'foto' => ['nullable', 'array', 'max:'.$maks],
            'foto.*.label' => ['required_with:foto', 'string', 'max:40'],
            'foto.*.data' => ['required_with:foto', 'string', 'max:4000000'],
        ];
    }

    /**
     * Simpan foto yang lolos pemeriksaan; yang gagal dilewati diam-diam.
     *
     * Kegagalan satu foto tidak boleh membatalkan pesanan yang sudah dibuat —
     * pekerjaannya tetap bisa dikerjakan tanpa lampiran, dan membatalkan
     * seluruhnya karena satu berkas rusak merugikan pelanggan.
     *
     * @param  array<string, mixed>  $data
     */
    protected function simpanFoto(Task $task, array $data, string $folder): void
    {
        $daftar = $data['foto'] ?? [];
        if (! $daftar) {
            return;
        }

        $tersimpan = [];
        foreach (array_values($daftar) as $i => $f) {
            $jalur = $this->simpanSatuFoto($folder, $task->id, $i, (string) $f['data']);
            if ($jalur) {
                $tersimpan[] = ['label' => $f['label'], 'jalur' => $jalur];
            }
        }

        if ($tersimpan) {
            $task->update(['detail_layanan' => [...$task->detail_layanan, 'foto' => $tersimpan]]);
        }
    }

    private function simpanSatuFoto(string $folder, int $taskId, int $urutan, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/(png|jpeg);base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            return null;
        }

        $biner = base64_decode(preg_replace('/\s+/', '', $m[2]), true);
        if ($biner === false || $biner === '') {
            return null;
        }

        // Diperiksa dari angka ajaibnya, bukan dari yang ditulis klien: header
        // data URL bisa mengaku apa saja.
        $png = substr($biner, 0, 8) === "\x89PNG\r\n\x1a\n";
        $jpeg = substr($biner, 0, 3) === "\xFF\xD8\xFF";
        if (! $png && ! $jpeg) {
            return null;
        }

        $jalur = "{$folder}/{$taskId}-{$urutan}.".($png ? 'png' : 'jpg');
        Storage::disk('public')->put($jalur, $biner);

        return $jalur;
    }
}
