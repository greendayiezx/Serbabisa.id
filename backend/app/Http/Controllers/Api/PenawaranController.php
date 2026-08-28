<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Penawaran;
use App\Models\PenawaranPaket;
use App\Models\PenawaranRevisi;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Penawaran BisaBersih Kantor dari sisi pelanggan.
 *
 * Yang boleh dilakukan pelanggan: melihat, menyetujui, meminta perubahan, dan
 * mengunduh PDF-nya. Menyusun dan mengubah isi penawaran adalah pekerjaan tim
 * sales — tidak ada endpoint untuk itu di sini.
 */
class PenawaranController extends Controller
{
    /** Perubahan yang bisa diminta pelanggan tanpa menulis bebas. */
    public const PERMINTAAN_REVISI = [
        'ubah-frekuensi' => 'Ubah frekuensi layanan',
        'kurangi-area' => 'Kurangi area yang dibersihkan',
        'tambah-karpet' => 'Tambahkan cuci karpet',
        'tambah-supervisor' => 'Tambahkan supervisor',
        'ubah-jam' => 'Ubah jam layanan',
        'harga-kontrak' => 'Minta harga kontrak 3 atau 6 bulan',
    ];

    public function index(Request $request): JsonResponse
    {
        $daftar = Penawaran::with('paket')
            ->where('customer_id', $request->user()->id)
            ->whereIn('status', Penawaran::TERBUKA)
            ->latest('id')
            ->get()
            ->map(fn (Penawaran $p) => $this->bentuk($p));

        return response()->json(['penawaran' => $daftar]);
    }

    public function show(Request $request, string $nomor): JsonResponse
    {
        return response()->json($this->bentuk($this->milikPelanggan($request, $nomor)));
    }

    /**
     * Setujui penawaran pada salah satu paket.
     *
     * Paketnya WAJIB disebut: penawaran berisi tiga pilihan, jadi "setuju" tanpa
     * menyebut yang mana tidak bisa dijadikan dasar kontrak.
     */
    public function setujui(Request $request, string $nomor): JsonResponse
    {
        $penawaran = $this->milikPelanggan($request, $nomor);

        $data = $request->validate([
            'paket_id' => ['required', 'integer'],
        ]);

        if ($penawaran->status === 'disetujui') {
            return response()->json(['message' => 'Penawaran ini sudah disetujui.'], 422);
        }
        if ($penawaran->kedaluwarsa()) {
            return response()->json([
                'message' => 'Masa berlaku penawaran ini sudah lewat. Minta penawaran baru ya.',
            ], 422);
        }

        // Paket harus milik penawaran ini — id dari klien tidak dipercaya.
        $paket = PenawaranPaket::where('penawaran_id', $penawaran->id)
            ->where('id', $data['paket_id'])
            ->first();

        if (! $paket) {
            return response()->json([
                'message' => 'Paket itu bukan bagian dari penawaran ini.',
                'errors' => ['paket_id' => ['Paket tidak dikenal.']],
            ], 422);
        }

        $penawaran->update([
            'status' => 'disetujui',
            'paket_dipilih_id' => $paket->id,
            'disetujui_pada' => now(),
        ]);

        return response()->json($this->bentuk($penawaran->fresh('paket')));
    }

    /** Ajukan perubahan. Penawaran kembali ke tim, tidak batal. */
    public function revisi(Request $request, string $nomor): JsonResponse
    {
        $penawaran = $this->milikPelanggan($request, $nomor);

        $data = $request->validate([
            'permintaan' => ['required', 'array', 'min:1'],
            'permintaan.*' => [Rule::in(array_keys(self::PERMINTAAN_REVISI))],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        if ($penawaran->status === 'disetujui') {
            return response()->json([
                'message' => 'Penawaran sudah disetujui. Hubungi tim untuk perubahan kontrak.',
            ], 422);
        }

        DB::transaction(function () use ($penawaran, $data) {
            PenawaranRevisi::create([
                'penawaran_id' => $penawaran->id,
                'permintaan' => array_values(array_unique($data['permintaan'])),
                'catatan' => $data['catatan'] ?? null,
            ]);
            $penawaran->update(['status' => 'revisi']);
        });

        return response()->json($this->bentuk($penawaran->fresh(['paket', 'revisi'])));
    }

    /** Unduh dokumen penawaran sebagai PDF. */
    public function pdf(Request $request, string $nomor): Response
    {
        $penawaran = $this->milikPelanggan($request, $nomor);
        $data = $this->bentuk($penawaran);

        $pdf = Pdf::loadView('penawaran.pdf', ['p' => $data])->setPaper('a4');

        return $pdf->download("Penawaran-{$penawaran->nomor}.pdf");
    }

    private function milikPelanggan(Request $request, string $nomor): Penawaran
    {
        return Penawaran::with(['paket', 'revisi'])
            ->where('customer_id', $request->user()->id)
            ->where('nomor', strtoupper(trim($nomor)))
            ->firstOrFail();
    }

    /** @return array<string,mixed> */
    private function bentuk(Penawaran $p): array
    {
        // Kedaluwarsa dihitung dari tanggal, bukan menunggu ada yang mengubah
        // kolom status — tanggal lewat dengan sendirinya.
        $status = $p->status !== 'disetujui' && $p->kedaluwarsa() ? 'kedaluwarsa' : $p->status;

        return [
            'nomor' => $p->nomor,
            'task_id' => $p->task_id,
            'status' => $status,
            'nama_perusahaan' => $p->nama_perusahaan,
            'nama_pic' => $p->nama_pic,
            'telepon_pic' => $p->telepon_pic,
            'alamat' => $p->alamat,
            'ringkasan' => $p->ringkasan,
            'tanggal' => $p->created_at?->toDateString(),
            'berlaku_sampai' => $p->berlaku_sampai?->toDateString(),
            'kedaluwarsa' => $p->kedaluwarsa(),
            'paket_dipilih_id' => $p->paket_dipilih_id,
            'disetujui_pada' => $p->disetujui_pada?->toIso8601String(),
            'scope' => $p->scope ?? [],
            'biaya_tambahan' => $p->biaya_tambahan ?? [],
            'pengecualian' => $p->pengecualian ?? [],
            'paket' => $p->paket->map(fn (PenawaranPaket $k) => [
                'id' => $k->id,
                'kode' => $k->kode,
                'nama' => $k->nama,
                'ringkas' => $k->ringkas,
                'isi' => $k->isi,
                'harga_per_kunjungan' => $k->harga_per_kunjungan,
                'kunjungan_per_bulan' => $k->kunjungan_per_bulan,
                'harga_bulanan' => $k->harga_bulanan,
                'disarankan' => $k->disarankan,
            ])->values(),
            'revisi' => $p->relationLoaded('revisi')
                ? $p->revisi->map(fn (PenawaranRevisi $r) => [
                    'permintaan' => array_map(
                        fn ($k) => self::PERMINTAAN_REVISI[$k] ?? $k,
                        $r->permintaan ?? [],
                    ),
                    'catatan' => $r->catatan,
                    'tanggal' => $r->created_at?->toIso8601String(),
                ])->values()
                : [],
            'pilihan_revisi' => self::PERMINTAAN_REVISI,
        ];
    }
}
