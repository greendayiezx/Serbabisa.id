<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Penawaran pemasangan AC — dibaca, disetujui, atau diminta revisinya.
 *
 * Persetujuan di sini mengikat: begitu ditekan, lingkup pekerjaan, harga, dan
 * pengecualiannya menjadi kesepakatan. Karena itu ia tidak boleh terjadi hanya
 * karena halamannya dibuka — server menuntut pernyataan setuju yang eksplisit
 * beserta nama penyetujunya, dan menolak penawaran yang sudah lewat masa
 * berlaku.
 *
 * Penawarannya sendiri hidup di dalam permintaan (detail_layanan.penawaran),
 * bukan tabel sendiri: ia selalu milik satu permintaan, dan tidak ada gunanya
 * dibaca terpisah dari konteks pekerjaannya.
 */
class PenawaranACController extends Controller
{
    public function show(Request $request, string $nomor): JsonResponse
    {
        $task = $this->cariMilikPengguna($request, $nomor);
        $penawaran = $task->detail_layanan['penawaran'] ?? null;

        abort_if($penawaran === null, 404, 'Penawaran untuk permintaan ini belum terbit.');

        return response()->json([
            'nomor_permintaan' => $task->nomor_invoice,
            'nomor_pekerjaan' => $task->detail_layanan['nomor_pekerjaan'] ?? null,
            'lokasi_alamat' => $task->lokasi_alamat,
            'penawaran' => [
                ...$penawaran,
                'kedaluwarsa' => $this->kedaluwarsa($penawaran),
            ],
        ]);
    }

    /**
     * Setujui penawaran.
     *
     * Yang dikirim klien hanya PERNYATAAN setuju — angkanya diambil dari
     * penawaran yang tersimpan, bukan dari badan permintaan. Kalau totalnya
     * ikut dikirim, siapa pun yang bisa memanggil API ini bisa menyetujui
     * pekerjaan dengan harga karangannya sendiri.
     */
    public function setujui(Request $request, string $nomor): JsonResponse
    {
        $task = $this->cariMilikPengguna($request, $nomor);
        $detail = $task->detail_layanan ?? [];
        $penawaran = $detail['penawaran'] ?? null;

        abort_if($penawaran === null, 404, 'Penawaran untuk permintaan ini belum terbit.');
        abort_if(
            ($penawaran['keputusan'] ?? null) !== null,
            422,
            'Penawaran ini sudah dijawab.',
        );
        abort_if(
            $this->kedaluwarsa($penawaran),
            422,
            'Penawaran ini sudah lewat masa berlaku. Minta penawaran baru ke tim kami.',
        );

        $data = $request->validate([
            // Bukan basa-basi: tanpa pernyataan ini, membuka halaman lalu
            // salah tekan sudah cukup untuk mengikat harga dan lingkup kerja.
            'setuju' => ['required', 'accepted'],
            'nama_penyetuju' => ['required', 'string', 'max:100'],
            'jabatan' => ['nullable', 'string', 'max:100'],
            'jadwal_id' => ['nullable', 'string', 'max:40'],
            'tanda_tangan' => ['nullable', 'string', 'max:2800000'],
        ]);

        $jadwal = null;
        if (! empty($data['jadwal_id'])) {
            $jadwal = collect($penawaran['jadwal'] ?? [])->firstWhere('id', $data['jadwal_id']);
            abort_if($jadwal === null, 422, 'Jadwal itu tidak ada di penawaran ini.');
        }

        $nomorPekerjaan = 'JOB-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));

        DB::transaction(function () use ($task, $detail, $penawaran, $data, $jadwal, $nomorPekerjaan) {
            $penawaran['keputusan'] = 'disetujui';
            $penawaran['disetujui_pada'] = now()->toIso8601String();
            $penawaran['nama_penyetuju'] = $data['nama_penyetuju'];
            $penawaran['jabatan'] = $data['jabatan'] ?? null;
            $penawaran['jadwal_dipilih'] = $jadwal;

            $baru = [
                ...$detail,
                'penawaran' => $penawaran,
                'nomor_pekerjaan' => $nomorPekerjaan,
                'status_pekerjaan' => 'menunggu-penjadwalan',
            ];

            $task->update([
                'detail_layanan' => $baru,
                // Baru sekarang ada harga: sebelum disetujui, angka penawaran
                // adalah usulan, bukan tagihan.
                'harga' => $penawaran['total'],
                'dijadwalkan_pada' => $jadwal ? $this->parseJadwal($jadwal) : $task->dijadwalkan_pada,
            ]);

            foreach ($penawaran['baris'] ?? [] as $b) {
                $task->items()->create([
                    'nama' => $b['nama'],
                    'kategori' => $b['kategori'] ?? 'layanan',
                    'satuan' => $b['satuan'] ?? 'paket',
                    'harga_satuan' => $b['nilai'],
                    'qty' => 1,
                    'subtotal' => $b['nilai'],
                ]);
            }

            $task->payment()->create([
                'jumlah' => $penawaran['total'],
                'subtotal_barang' => $penawaran['subtotal'] ?? $penawaran['total'],
                'ongkir' => 0,
                'ongkir_normal' => 0,
                'potongan' => $penawaran['potongan'] ?? 0,
                'cashback' => 0,
                'service_fee' => 0,
                'komisi_platform' => 0,
                'status' => 'pending',
                'metode' => null,
            ]);
        });

        // Tanda tangan disimpan setelah task diperbarui, dan kegagalannya tidak
        // membatalkan persetujuan: yang mengikat adalah pernyataan setuju
        // beserta namanya, bukan gambar coretannya.
        if (! empty($data['tanda_tangan'])) {
            $jalur = $this->simpanTandaTangan($task->id, $data['tanda_tangan']);
            if ($jalur) {
                $segar = $task->fresh();
                $d = $segar->detail_layanan;
                $d['penawaran']['tanda_tangan'] = $jalur;
                $segar->update(['detail_layanan' => $d]);
            }
        }

        return response()->json([
            'nomor_permintaan' => $task->nomor_invoice,
            'nomor_pekerjaan' => $nomorPekerjaan,
            'status_pekerjaan' => 'menunggu-penjadwalan',
            'total' => $penawaran['total'],
            'deposit' => $penawaran['deposit'] ?? 0,
            'jadwal_dipilih' => $jadwal,
        ]);
    }

    /**
     * Ajukan revisi.
     *
     * Tidak mengubah angka apa pun — permintaannya dicatat dan penawarannya
     * dikembalikan ke keadaan "menunggu penawaran baru". Membiarkan pelanggan
     * mengubah harga sendiri berarti tidak ada lagi yang namanya penawaran.
     */
    public function revisi(Request $request, string $nomor): JsonResponse
    {
        $task = $this->cariMilikPengguna($request, $nomor);
        $detail = $task->detail_layanan ?? [];
        $penawaran = $detail['penawaran'] ?? null;

        abort_if($penawaran === null, 404, 'Penawaran untuk permintaan ini belum terbit.');
        abort_if(
            ($penawaran['keputusan'] ?? null) === 'disetujui',
            422,
            'Penawaran ini sudah disetujui. Perubahan setelahnya diajukan sebagai pekerjaan tambahan.',
        );

        $data = $request->validate([
            'kategori' => ['required', 'array', 'min:1'],
            'kategori.*' => ['string', 'max:40'],
            'alasan' => ['nullable', 'string', 'max:40'],
            'paket_alternatif' => ['nullable', 'string', 'max:40'],
            'catatan' => ['required', 'string', 'max:1000'],
            'per_item' => ['nullable', 'array', 'max:20'],
            'per_item.*.item' => ['required_with:per_item', 'string', 'max:80'],
            'per_item.*.permintaan' => ['required_with:per_item', 'string', 'max:300'],
        ]);

        $riwayat = $penawaran['revisi'] ?? [];
        $riwayat[] = [
            'diajukan_pada' => now()->toIso8601String(),
            'kategori' => $data['kategori'],
            'alasan' => $data['alasan'] ?? null,
            'paket_alternatif' => $data['paket_alternatif'] ?? null,
            'catatan' => $data['catatan'],
            'per_item' => $data['per_item'] ?? [],
        ];

        $penawaran['revisi'] = $riwayat;
        $penawaran['keputusan'] = 'revisi';
        $penawaran['direvisi_pada'] = now()->toIso8601String();

        $task->update(['detail_layanan' => [...$detail, 'penawaran' => $penawaran]]);

        return response()->json([
            'nomor_permintaan' => $task->nomor_invoice,
            'keputusan' => 'revisi',
            'jumlah_revisi' => count($riwayat),
        ]);
    }

    /* ==================== Bersama ==================== */

    private function cariMilikPengguna(Request $request, string $nomor): Task
    {
        $nomor = strtoupper(trim($nomor));

        return Task::query()
            ->where('customer_id', $request->user()->id)
            ->where(fn ($q) => $q
                ->where('nomor_invoice', $nomor)
                ->orWhere('nomor_invoice', 'like', '%-'.$nomor))
            ->firstOrFail();
    }

    /**
     * Masa berlaku dihitung SERVER, bukan dipercayakan pada tanggal yang
     * dikirim klien — dan bukan pula dianggap kedaluwarsa saat tanggalnya tidak
     * ada, karena penawaran tanpa batas waktu memang sah.
     */
    private function kedaluwarsa(array $penawaran): bool
    {
        $sampai = $penawaran['berlaku_sampai'] ?? null;
        if (! $sampai) {
            return false;
        }

        try {
            return Carbon::parse($sampai)->endOfDay()->isPast();
        } catch (\Throwable) {
            return false;
        }
    }

    private function parseJadwal(array $jadwal): ?Carbon
    {
        try {
            return Carbon::parse(trim(($jadwal['tanggal'] ?? '').' '.explode('-', $jadwal['jam'] ?? '09:00')[0]));
        } catch (\Throwable) {
            return null;
        }
    }

    private function simpanTandaTangan(int $taskId, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            return null;
        }

        $biner = base64_decode(preg_replace('/\s+/', '', $m[1]), true);
        if ($biner === false || $biner === '' || substr($biner, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        $jalur = "tanda-tangan/penawaran-{$taskId}.png";
        Storage::disk('public')->put($jalur, $biner);

        return $jalur;
    }
}
