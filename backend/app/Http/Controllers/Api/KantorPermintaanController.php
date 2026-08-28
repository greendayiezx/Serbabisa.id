<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Admin\PermintaanController as AdminPermintaanController;
use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Task;
use App\Services\KantorTarif;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\Rule;

/**
 * Permintaan penawaran BisaBersih Kantor.
 *
 * Dibuat lewat endpoint sendiri, bukan POST /tasks umum, karena dua hal yang
 * tidak bisa dilakukan endpoint umum:
 *
 * 1. NOMOR PERMINTAAN. Pelanggan butuh satu nomor untuk menanyakan statusnya
 *    (REQ-000001). Tanpa itu, satu-satunya rujukan adalah id internal.
 * 2. SPESIFIKASI TERSTRUKTUR. Luas, lantai, jumlah toilet, dan seterusnya
 *    disimpan sebagai data di `detail_layanan` — bukan hanya teks di deskripsi.
 *    Menyusun penawaran dari teks berarti mengurai kalimat kembali jadi angka,
 *    dan itu akan salah begitu kalimatnya sedikit berubah.
 */
class KantorPermintaanController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'nama_perusahaan' => ['required', 'string', 'max:150'],
            'nama_pic' => ['required', 'string', 'max:100'],
            'telepon_pic' => ['required', 'string', 'max:30'],

            'jenis_kantor' => ['required', Rule::in(array_keys(KantorTarif::JENIS))],
            'paket' => ['nullable', Rule::in(array_keys(KantorTarif::PAKET))],
            'frekuensi' => ['required', Rule::in(array_keys(KantorTarif::FREKUENSI))],

            'luas_m2' => ['nullable', 'integer', 'min:0', 'max:100000'],
            'jumlah_lantai' => ['nullable', 'integer', 'min:1', 'max:200'],
            'workstation' => ['nullable', 'integer', 'min:0', 'max:5000'],
            'ruang_meeting' => ['nullable', 'integer', 'min:0', 'max:500'],
            'toilet' => ['nullable', 'integer', 'min:0', 'max:500'],
            'pantry' => ['nullable', 'integer', 'min:0', 'max:200'],
            'lainnya' => ['nullable', 'string', 'max:255'],
            'add_on' => ['nullable', 'array'],
            'add_on.*' => [Rule::in(array_keys(KantorTarif::ADD_ON))],
            'catatan' => ['nullable', 'string', 'max:1000'],

            'estimasi' => ['nullable', 'numeric', 'min:0'],
            'promo_kode' => ['nullable', 'string', 'max:40'],

            // PNG data URL dari kanvas tanda tangan. Dibatasi ~2 MB teks
            // base64 supaya coretan yang wajar lolos tapi kiriman besar tidak.
            'tanda_tangan' => ['nullable', 'string', 'max:2800000'],

            'lokasi_alamat' => ['required', 'string', 'max:255'],
            'lokasi_lat' => ['nullable', 'numeric'],
            'lokasi_lng' => ['nullable', 'numeric'],
        ]);

        $spek = [
            'layanan' => 'kantor',
            'permintaan_penawaran' => true,
            'nama_perusahaan' => $data['nama_perusahaan'],
            'nama_pic' => $data['nama_pic'],
            'telepon_pic' => $data['telepon_pic'],
            'jenis_kantor' => $data['jenis_kantor'],
            'nama_jenis' => KantorTarif::JENIS[$data['jenis_kantor']]['nama'],
            'paket' => $data['paket'] ?? null,
            'frekuensi' => $data['frekuensi'],
            'label_frekuensi' => KantorTarif::FREKUENSI[$data['frekuensi']]['label'],
            'luas_m2' => $data['luas_m2'] ?? null,
            'jumlah_lantai' => $data['jumlah_lantai'] ?? null,
            'workstation' => (int) ($data['workstation'] ?? 0),
            'ruang_meeting' => (int) ($data['ruang_meeting'] ?? 0),
            'toilet' => (int) ($data['toilet'] ?? 0),
            'pantry' => (int) ($data['pantry'] ?? 0),
            'lainnya' => $data['lainnya'] ?? null,
            'add_on' => array_values($data['add_on'] ?? []),
            'estimasi_aplikasi' => isset($data['estimasi']) ? (int) $data['estimasi'] : null,
            'promo_kode' => $data['promo_kode'] ?? null,
        ];

        $task = Task::create([
            'nomor_invoice' => $this->nomorPermintaan(),
            'customer_id' => $user->id,
            'category_id' => Category::where('slug', 'bisabersih')->value('id'),
            'tipe' => 'custom',
            'judul' => "Permintaan Penawaran — Bersih Kantor ({$data['nama_perusahaan']})",
            'deskripsi' => $this->ringkasan($spek, $data['catatan'] ?? null),
            'status' => 'pending',
            'fulfillment_status' => 'diproses',
            'lokasi_alamat' => $data['lokasi_alamat'],
            'lokasi_lat' => $data['lokasi_lat'] ?? 0,
            'lokasi_lng' => $data['lokasi_lng'] ?? 0,
            // Estimasi masuk sebagai budget, bukan harga: angka final baru
            // ditentukan tim setelah meninjau (dan bila perlu survei).
            'budget' => $data['estimasi'] ?? null,
            'catatan' => $data['catatan'] ?? null,
            'nama_penerima' => $data['nama_pic'],
            'telepon_penerima' => $data['telepon_pic'],
            'detail_layanan' => $spek,
        ]);

        // Tanda tangan disimpan setelah task ada, karena nama berkasnya memakai
        // id task. Kegagalan menyimpannya tidak membatalkan permintaan.
        if (! empty($data['tanda_tangan'])) {
            $jalur = $this->simpanTandaTangan($task->id, $data['tanda_tangan']);
            if ($jalur) {
                $task->update([
                    'detail_layanan' => [
                        ...$spek,
                        'tanda_tangan' => $jalur,
                        'ditandatangani_pada' => now()->toIso8601String(),
                        'ditandatangani_oleh' => $data['nama_pic'],
                    ],
                ]);
            }
        }

        return response()->json([
            'id' => $task->id,
            'nomor' => $task->nomor_invoice,
            'nama_perusahaan' => $spek['nama_perusahaan'],
            'alamat' => $task->lokasi_alamat,
            'jenis_layanan' => 'BisaBersih Kantor — '.$spek['nama_jenis'],
            'frekuensi' => $spek['label_frekuensi'],
            'estimasi' => $spek['estimasi_aplikasi'],
            'bertanda_tangan' => ! empty($task->fresh()->detail_layanan['tanda_tangan']),
        ], 201);
    }

    /**
     * Simpan PNG data URL jadi berkas.
     *
     * Hanya PNG yang diterima, dan isinya didekode sendiri — bukan disimpan
     * mentah sebagai teks. Data URL dari klien bisa saja berisi tipe lain atau
     * base64 rusak, jadi keduanya diperiksa sebelum ditulis ke disk.
     */
    private function simpanTandaTangan(int $taskId, string $dataUrl): ?string
    {
        if (! preg_match('#^data:image/png;base64,([A-Za-z0-9+/=\s]+)$#', $dataUrl, $m)) {
            return null;
        }

        $biner = base64_decode(preg_replace('/\s+/', '', $m[1]), true);
        if ($biner === false || $biner === '') {
            return null;
        }

        // Periksa berkasnya benar-benar PNG lewat angka ajaib di 8 bita awal.
        if (substr($biner, 0, 8) !== "\x89PNG\r\n\x1a\n") {
            return null;
        }

        $jalur = "tanda-tangan/permintaan-{$taskId}.png";
        Storage::disk('public')->put($jalur, $biner);

        return $jalur;
    }

    /** Bukti permintaan sebagai PDF, lengkap dengan tanda tangannya. */
    public function pdf(Request $request, string $nomor)
    {
        $task = Task::where('customer_id', $request->user()->id)
            ->where('nomor_invoice', strtoupper(trim($nomor)))
            ->firstOrFail();

        return $this->buatPdf($task)->download("Permintaan-{$task->nomor_invoice}.pdf");
    }

    /**
     * Tautan sementara untuk MEMBUKA PDF-nya di penampil peramban.
     *
     * Peramban tidak bisa mengirim header Authorization saat berpindah halaman,
     * jadi tautan biasa ke endpoint di atas akan ditolak. Yang dikirim di sini
     * adalah URL bertanda tangan: berlaku singkat, tidak bisa ditebak, dan tidak
     * memerlukan token.
     *
     * Ini juga sekaligus menghindari `blob:` — Chrome menolak menampilkan PDF
     * dari blob URL di sebagian lingkungan (termasuk emulasi ponsel), dan yang
     * muncul hanya "This page has been blocked by Chrome".
     */
    public function tautanPdf(Request $request, string $nomor): JsonResponse
    {
        $task = Task::where('customer_id', $request->user()->id)
            ->where('nomor_invoice', strtoupper(trim($nomor)))
            ->firstOrFail();

        return response()->json([
            // RELATIF, bukan absolut: APP_URL bisa berbeda dari alamat server
            // yang sebenarnya dipakai (port dev, domain di balik proxy), dan
            // tanda tangannya ikut menghitung URL penuh — yang absolut akan
            // gagal verifikasi begitu hostnya tidak persis sama.
            'url' => URL::temporarySignedRoute(
                'permintaan.berkas',
                // Cukup panjang karena tautannya disiapkan saat halaman
                // dibuka, bukan saat tombol ditekan.
                now()->addMinutes(60),
                ['task' => $task->id],
                absolute: false,
            ),
        ]);
    }

    /**
     * PDF yang dibuka langsung peramban lewat tautan bertanda tangan.
     *
     * Tidak memakai auth:sanctum — tanda tangan URL-nya yang jadi bukti akses,
     * dan itu diperiksa middleware `signed`.
     */
    public function berkasPdf(Task $task)
    {
        abort_unless(str_starts_with((string) $task->nomor_invoice, 'REQ-'), 404);

        return $this->buatPdf($task)
            // `stream` memakai Content-Disposition: inline, jadi peramban
            // MENAMPILKAN berkasnya alih-alih langsung mengunduhnya.
            ->stream("Permintaan-{$task->nomor_invoice}.pdf");
    }

    private function buatPdf(Task $task)
    {
        $spek = $task->detail_layanan ?? [];
        $ttd = $spek['tanda_tangan'] ?? null;

        return Pdf::loadView('permintaan.pdf', [
            'task' => $task,
            'spek' => $spek,
            // dompdf tidak bisa mengambil berkas lewat URL di lingkungan ini,
            // jadi gambarnya disematkan langsung sebagai data URL.
            'ttdDataUrl' => $ttd && Storage::disk('public')->exists($ttd)
                ? 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($ttd))
                : null,
        ])->setPaper('a4');
    }

    /**
     * Status satu permintaan, dibaca halaman konfirmasi.
     *
     * Ikut menyertakan nomor penawaran kalau tim sudah menyusunnya, supaya
     * halaman konfirmasi bisa menautkan ke dokumennya tanpa menebak.
     */
    public function show(Request $request, string $nomor): JsonResponse
    {
        $task = Task::where('customer_id', $request->user()->id)
            ->where('nomor_invoice', strtoupper(trim($nomor)))
            ->firstOrFail();

        $spek = $task->detail_layanan ?? [];
        $penawaran = \App\Models\Penawaran::where('task_id', $task->id)->latest('id')->first();

        return response()->json([
            'id' => $task->id,
            'nomor' => $task->nomor_invoice,
            'dibuat_pada' => $task->created_at?->toIso8601String(),
            'nama_perusahaan' => $spek['nama_perusahaan'] ?? null,
            'nama_pic' => $spek['nama_pic'] ?? null,
            'telepon_pic' => $spek['telepon_pic'] ?? null,
            'alamat' => $task->lokasi_alamat,
            'jenis_layanan' => 'BisaBersih Kantor'.(isset($spek['nama_jenis']) ? " — {$spek['nama_jenis']}" : ''),
            'frekuensi' => $spek['label_frekuensi'] ?? null,
            'estimasi' => $spek['estimasi_aplikasi'] ?? null,
            // null selama tim belum menyusun penawarannya.
            'nomor_penawaran' => $penawaran?->nomor,
            // Tahap yang benar-benar sudah dikerjakan tim. Permintaan lama yang
            // belum punya penanda dianggap baru masuk.
            'tahap' => $spek['tahap'] ?? AdminPermintaanController::TAHAP_AWAL,
        ]);
    }

    /**
     * Nomor permintaan berurutan: REQ-000001.
     *
     * Sengaja beda awalan dari nomor penawaran (OFF-) supaya pelanggan dan tim
     * tidak tertukar menyebut "permintaan" dengan "penawaran" — keduanya hidup
     * berdampingan dalam satu percakapan.
     */
    private function nomorPermintaan(): string
    {
        $terakhir = Task::where('nomor_invoice', 'like', 'REQ-%')
            ->orderByRaw('LENGTH(nomor_invoice) DESC')
            ->orderByDesc('nomor_invoice')
            ->value('nomor_invoice');

        $angka = $terakhir ? (int) substr($terakhir, 4) : 0;

        return 'REQ-'.str_pad((string) ($angka + 1), 6, '0', STR_PAD_LEFT);
    }

    /** Teks yang bisa dibaca tim tanpa membuka JSON. */
    private function ringkasan(array $s, ?string $catatan): string
    {
        $baris = [
            "Nama perusahaan: {$s['nama_perusahaan']}",
            "PIC: {$s['nama_pic']}",
            "WhatsApp: {$s['telepon_pic']}",
            "Jenis kantor: {$s['nama_jenis']}",
            'Luas area: '.($s['luas_m2'] ? "{$s['luas_m2']} m²" : 'belum disebutkan'),
            'Jumlah lantai: '.($s['jumlah_lantai'] ?? '-'),
            "Ruang meeting: {$s['ruang_meeting']}",
            "Workstation: {$s['workstation']}",
            "Toilet: {$s['toilet']}",
            "Pantry: {$s['pantry']}",
            "Frekuensi: {$s['label_frekuensi']}",
        ];

        if ($s['lainnya']) {
            $baris[] = "Area lainnya: {$s['lainnya']}";
        }
        if ($s['add_on']) {
            $nama = array_map(fn ($id) => KantorTarif::ADD_ON[$id]['nama'], $s['add_on']);
            $baris[] = 'Layanan tambahan: '.implode(', ', $nama);
        }
        if ($catatan) {
            $baris[] = "Catatan khusus: {$catatan}";
        }

        return implode("\n", $baris);
    }
}
