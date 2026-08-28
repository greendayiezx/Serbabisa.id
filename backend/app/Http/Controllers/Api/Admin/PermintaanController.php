<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Penawaran;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Permintaan penawaran BisaBersih Kantor — sisi tim.
 *
 * Tahap permintaan digerakkan DARI SINI, bukan berjalan sendiri karena waktu
 * berlalu. Halaman pelanggan menampilkan tahap yang benar-benar sudah dikerjakan
 * tim; kalau belum ada yang menghubungi, langkah "Tim menghubungi PIC" memang
 * belum boleh tercentang.
 */
class PermintaanController extends Controller
{
    /**
     * Urutan tahap. Yang terakhir — "penawaran dikirim" — TIDAK ada di sini:
     * tahap itu tidak diklik siapa pun, melainkan tercapai sendiri begitu
     * dokumen penawarannya benar-benar tersusun.
     */
    public const TAHAP = [
        'ditinjau' => 'Sedang ditinjau',
        'dihubungi' => 'PIC sudah dihubungi',
        'survei' => 'Survei lokasi dijadwalkan',
    ];

    public const TAHAP_AWAL = 'ditinjau';

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'tahap' => ['nullable', Rule::in(array_keys(self::TAHAP))],
        ]);

        $daftar = Task::where('nomor_invoice', 'like', 'REQ-%')
            ->with('customer:id,name,email')
            ->latest('id')
            ->get()
            ->map(fn (Task $t) => $this->bentuk($t))
            ->when(
                ! empty($data['tahap']),
                fn ($c) => $c->where('tahap', $data['tahap']),
            )
            ->values();

        return response()->json([
            'permintaan' => $daftar,
            'tahap' => self::TAHAP,
            // Hitungan per tahap untuk lencana di dashboard.
            'jumlah' => collect(self::TAHAP)
                ->map(fn ($_, $k) => $daftar->where('tahap', $k)->count())
                ->all(),
        ]);
    }

    public function show(string $nomor): JsonResponse
    {
        return response()->json($this->bentuk($this->cari($nomor), lengkap: true));
    }

    /**
     * Majukan tahap permintaan.
     *
     * Hanya boleh MAJU: mundur berarti memberi tahu pelanggan bahwa sesuatu
     * yang sudah terjadi ternyata belum, dan itu tidak masuk akal di layar
     * status mereka.
     */
    public function majukan(Request $request, string $nomor): JsonResponse
    {
        $task = $this->cari($nomor);

        $data = $request->validate([
            'tahap' => ['required', Rule::in(array_keys(self::TAHAP))],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        $urutan = array_keys(self::TAHAP);
        $sekarang = array_search($this->tahap($task), $urutan, true);
        $tujuan = array_search($data['tahap'], $urutan, true);

        if ($tujuan <= $sekarang) {
            return response()->json([
                'message' => 'Tahap hanya bisa dimajukan, tidak bisa dimundurkan.',
                'errors' => ['tahap' => ['Permintaan ini sudah berada di tahap itu atau lebih.']],
            ], 422);
        }

        $riwayat = $task->detail_layanan['riwayat_tahap'] ?? [];
        $riwayat[] = [
            'tahap' => $data['tahap'],
            'pada' => now()->toIso8601String(),
            'oleh' => $request->user()->name,
            'catatan' => $data['catatan'] ?? null,
        ];

        $task->update([
            'detail_layanan' => [
                ...$task->detail_layanan,
                'tahap' => $data['tahap'],
                'riwayat_tahap' => $riwayat,
            ],
        ]);

        return response()->json($this->bentuk($task->fresh(), lengkap: true));
    }

    private function cari(string $nomor): Task
    {
        return Task::where('nomor_invoice', strtoupper(trim($nomor)))
            ->where('nomor_invoice', 'like', 'REQ-%')
            ->firstOrFail();
    }

    /** Tahap tersimpan; permintaan lama yang belum punya dianggap baru masuk. */
    private function tahap(Task $task): string
    {
        return $task->detail_layanan['tahap'] ?? self::TAHAP_AWAL;
    }

    /** @return array<string,mixed> */
    private function bentuk(Task $task, bool $lengkap = false): array
    {
        $spek = $task->detail_layanan ?? [];
        $penawaran = Penawaran::where('task_id', $task->id)->latest('id')->first();

        $dasar = [
            'nomor' => $task->nomor_invoice,
            'task_id' => $task->id,
            'tahap' => $this->tahap($task),
            'label_tahap' => self::TAHAP[$this->tahap($task)] ?? $this->tahap($task),
            'nama_perusahaan' => $spek['nama_perusahaan'] ?? '-',
            'nama_pic' => $spek['nama_pic'] ?? null,
            'telepon_pic' => $spek['telepon_pic'] ?? null,
            'alamat' => $task->lokasi_alamat,
            'jenis_kantor' => $spek['nama_jenis'] ?? null,
            'frekuensi' => $spek['label_frekuensi'] ?? null,
            'estimasi' => $spek['estimasi_aplikasi'] ?? null,
            'bertanda_tangan' => ! empty($spek['tanda_tangan']),
            'dibuat_pada' => $task->created_at?->toIso8601String(),
            'nomor_penawaran' => $penawaran?->nomor,
            'pelanggan' => $task->customer?->only(['id', 'name', 'email']),
        ];

        if (! $lengkap) {
            return $dasar;
        }

        return [
            ...$dasar,
            'luas_m2' => $spek['luas_m2'] ?? null,
            'jumlah_lantai' => $spek['jumlah_lantai'] ?? null,
            'workstation' => $spek['workstation'] ?? 0,
            'ruang_meeting' => $spek['ruang_meeting'] ?? 0,
            'toilet' => $spek['toilet'] ?? 0,
            'pantry' => $spek['pantry'] ?? 0,
            'lainnya' => $spek['lainnya'] ?? null,
            'catatan' => $task->catatan,
            'foto' => $task->foto ?? [],
            'riwayat_tahap' => $spek['riwayat_tahap'] ?? [],
        ];
    }
}
