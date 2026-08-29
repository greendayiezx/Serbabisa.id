<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Category;
use App\Models\Task;
use App\Services\ACTarif;
use App\Services\FreonTarif;
use App\Services\NomorInvoice;
use App\Services\PerbaikanTarif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

/**
 * Perbaikan & Pasang AC.
 *
 * Dua jalur yang sengaja dipisah karena uangnya berperilaku berbeda:
 *
 * - PERBAIKAN menagih kunjungan diagnosisnya saja. Setelah teknisi memeriksa,
 *   rekomendasinya ditulis ke pesanan dan pelanggan menyetujui atau menolak —
 *   memakai jalur yang sama dengan Cek & Tambah Freon, karena mekanismenya
 *   memang persis sama.
 * - PASANG / PINDAH tidak menagih apa pun. Yang tercatat adalah PERMINTAAN
 *   PENAWARAN bernomor REQ-; harganya menyusul setelah foto diperiksa atau
 *   lokasi disurvei. Menagih di muka untuk pekerjaan yang belum diukur berarti
 *   menagih tebakan.
 */
class ACPerbaikanController extends Controller
{
    public function __construct(
        private readonly PerbaikanTarif $tarif,
        private readonly NomorInvoice $nomorInvoice,
    ) {}

    /* ==================== PERBAIKAN ==================== */

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'unit' => ['required', 'integer', 'min:1', 'max:10'],
            'keluhan' => ['required', 'array', 'min:1'],
            'keluhan.*' => [Rule::in(PerbaikanTarif::KELUHAN)],
            'menyala' => ['required', 'boolean'],
            'mulai_terjadi' => ['required', Rule::in(PerbaikanTarif::MULAI_TERJADI)],

            'merek' => ['required', Rule::in(FreonTarif::MEREK)],
            'tipe' => ['required', Rule::in(ACTarif::TIPE)],
            'kapasitas' => ['required', Rule::in(ACTarif::KAPASITAS)],
            'kode_error' => ['nullable', 'string', 'max:40'],
            'catatan' => ['nullable', 'string', 'max:500'],

            'tanggal' => ['required', 'date'],
            'slot' => ['required', Rule::in(FreonTarif::SLOT)],

            'nama_penerima' => ['required', 'string', 'max:100'],
            'telepon_penerima' => ['required', 'string', 'max:30'],

            'address_id' => ['nullable', 'integer'],
            'lokasi_alamat' => ['required_without:address_id', 'string', 'max:255'],
            'lokasi_lat' => ['required_without:address_id', 'numeric'],
            'lokasi_lng' => ['required_without:address_id', 'numeric'],
            'metode' => ['nullable', 'string', 'max:30'],

            ...$this->aturanFoto(),
        ]);

        $rincian = $this->tarif->pemeriksaan((int) $data['unit']);
        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);
        $jadwal = $this->parseJadwal($data['tanggal'], $data['slot']);

        $task = DB::transaction(function () use ($user, $data, $rincian, $addressId, $alamat, $lat, $lng, $jadwal) {
            $task = Task::create([
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => Category::where('slug', 'bisatukang')->value('id'),
                'address_id' => $addressId,
                'tipe' => 'fixed',
                'judul' => "Servis AC — Perbaikan ({$rincian['unit']} unit)",
                'deskripsi' => $this->ringkasanPerbaikan($rincian, $data),
                'status' => 'pending',
                'fulfillment_status' => 'diproses',
                'lokasi_alamat' => $alamat,
                'lokasi_lat' => $lat,
                'lokasi_lng' => $lng,
                'harga' => $rincian['total'],
                'catatan' => $data['catatan'] ?? null,
                'dijadwalkan_pada' => $jadwal,
                'nama_penerima' => $data['nama_penerima'],
                'telepon_penerima' => $data['telepon_penerima'],
                'detail_layanan' => [
                    'layanan' => 'perbaikan',
                    'unit' => $rincian['unit'],
                    'keluhan' => $data['keluhan'],
                    'menyala' => (bool) $data['menyala'],
                    'mulai_terjadi' => $data['mulai_terjadi'],
                    'merek' => $data['merek'],
                    'tipe' => $data['tipe'],
                    'kapasitas' => $data['kapasitas'],
                    'kode_error' => $data['kode_error'] ?? null,
                    'slot' => $data['slot'],
                    'biaya_pemeriksaan' => $rincian['total'],
                    // Diisi teknisi setelah memeriksa. Selama null, layar hasil
                    // belum boleh menampilkan rekomendasi apa pun.
                    'diagnosis' => null,
                    'area' => ['Perbaikan AC'],
                    'jumlah_cleaner' => 1,
                ],
            ]);

            $task->items()->create([
                'nama' => 'Pemeriksaan & diagnosis AC',
                'kategori' => 'layanan',
                'satuan' => 'kunjungan',
                'harga_satuan' => PerbaikanTarif::BIAYA_PEMERIKSAAN,
                'qty' => 1,
                'subtotal' => PerbaikanTarif::BIAYA_PEMERIKSAAN,
            ]);

            if ($rincian['biaya_unit_tambahan'] > 0) {
                $task->items()->create([
                    'nama' => 'Unit tambahan',
                    'kategori' => 'layanan',
                    'satuan' => 'unit',
                    'harga_satuan' => PerbaikanTarif::BIAYA_UNIT_TAMBAHAN,
                    'qty' => $rincian['unit'] - 1,
                    'subtotal' => $rincian['biaya_unit_tambahan'],
                ]);
            }

            $task->payment()->create([
                'jumlah' => $rincian['total'],
                'subtotal_barang' => $rincian['total'],
                'ongkir' => 0,
                'ongkir_normal' => 0,
                'potongan' => 0,
                'cashback' => 0,
                'service_fee' => 0,
                'komisi_platform' => $rincian['total'] - $rincian['biaya'],
                'status' => 'pending',
                'metode' => $data['metode'] ?? null,
            ]);

            return $task;
        });

        $this->simpanFoto($task, $data);

        return response()->json([
            ...$task->fresh()->load(['items', 'payment'])->toArray(),
            'rincian' => $rincian,
        ], 201);
    }

    /* ==================== PASANG / PINDAH ==================== */

    /**
     * Permintaan penawaran pemasangan.
     *
     * Tidak membuat pembayaran dan tidak menyebut harga final. Nomornya REQ-,
     * bukan invoice, supaya di riwayat pun terbaca sebagai permintaan yang
     * masih menunggu penawaran.
     */
    public function permintaan(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'jenis_pekerjaan' => ['required', Rule::in(PerbaikanTarif::JENIS_PEKERJAAN)],
            'unit' => ['required', 'integer', 'min:1', 'max:20'],
            'ketersediaan_unit' => ['required', Rule::in(PerbaikanTarif::KETERSEDIAAN_UNIT)],
            'kebutuhan' => ['required', Rule::in(PerbaikanTarif::KEBUTUHAN)],

            'merek' => ['nullable', Rule::in(FreonTarif::MEREK)],
            'kapasitas' => ['required', Rule::in(ACTarif::KAPASITAS)],

            'lokasi_indoor' => ['required', Rule::in(PerbaikanTarif::LOKASI_INDOOR)],
            'lokasi_outdoor' => ['required', Rule::in(PerbaikanTarif::LOKASI_OUTDOOR)],
            'material' => ['nullable', 'array'],
            'material.*' => [Rule::in(PerbaikanTarif::MATERIAL)],

            'cara_penawaran' => ['required', Rule::in(PerbaikanTarif::CARA_PENAWARAN)],
            'catatan' => ['nullable', 'string', 'max:1000'],

            'nama_penerima' => ['required', 'string', 'max:100'],
            'telepon_penerima' => ['required', 'string', 'max:30'],

            'lokasi_alamat' => ['required', 'string', 'max:255'],
            'lokasi_lat' => ['nullable', 'numeric'],
            'lokasi_lng' => ['nullable', 'numeric'],

            ...$this->aturanFoto(),
        ]);

        /*
         * Pekerjaan tertentu tidak bisa dinilai dari foto — pindah AC dan
         * pemasangan banyak unit selalu butuh orang melihat lokasinya. Pilihan
         * "estimasi foto" di situ dinaikkan jadi survei, dan alasannya dicatat
         * supaya pelanggan tahu kenapa jawabannya berubah.
         */
        $cara = $data['cara_penawaran'];
        $dinaikkan = false;
        if ($cara === 'estimasi-foto' && in_array($data['jenis_pekerjaan'], PerbaikanTarif::WAJIB_SURVEI, true)) {
            $cara = 'survei-lokasi';
            $dinaikkan = true;
        }

        $spek = [
            'layanan' => 'pasang-ac',
            'permintaan_penawaran' => true,
            'jenis_pekerjaan' => $data['jenis_pekerjaan'],
            'unit' => (int) $data['unit'],
            'ketersediaan_unit' => $data['ketersediaan_unit'],
            'kebutuhan' => $data['kebutuhan'],
            'merek' => $data['merek'] ?? null,
            'kapasitas' => $data['kapasitas'],
            'lokasi_indoor' => $data['lokasi_indoor'],
            'lokasi_outdoor' => $data['lokasi_outdoor'],
            'material' => $data['material'] ?? [],
            'cara_penawaran' => $cara,
            'survei_diwajibkan' => $dinaikkan,
            'estimasi_mulai' => PerbaikanTarif::PASANG_MULAI,
            'estimasi_sampai' => PerbaikanTarif::PASANG_SAMPAI,
            'area' => ['Pemasangan AC'],
            'jumlah_cleaner' => 1,
        ];

        $task = Task::create([
            'nomor_invoice' => $this->nomorPermintaan(),
            'customer_id' => $user->id,
            'category_id' => Category::where('slug', 'bisatukang')->value('id'),
            'tipe' => 'custom',
            'judul' => 'Permintaan Penawaran — Pasang/Pindah AC',
            'deskripsi' => $this->ringkasanPasang($spek),
            'status' => 'pending',
            'fulfillment_status' => 'diproses',
            'lokasi_alamat' => $data['lokasi_alamat'],
            'lokasi_lat' => $data['lokasi_lat'] ?? 0,
            'lokasi_lng' => $data['lokasi_lng'] ?? 0,
            // Rentang masuk sebagai budget, bukan harga: angka final baru
            // ditentukan setelah foto diperiksa atau lokasi disurvei.
            'budget' => PerbaikanTarif::PASANG_MULAI,
            'catatan' => $data['catatan'] ?? null,
            'nama_penerima' => $data['nama_penerima'],
            'telepon_penerima' => $data['telepon_penerima'],
            'detail_layanan' => $spek,
        ]);

        $this->simpanFoto($task, $data);

        return response()->json([
            'id' => $task->id,
            'nomor' => $task->nomor_invoice,
            'cara_penawaran' => $cara,
            'survei_diwajibkan' => $dinaikkan,
            'estimasi_mulai' => PerbaikanTarif::PASANG_MULAI,
            'estimasi_sampai' => PerbaikanTarif::PASANG_SAMPAI,
        ], 201);
    }

    /* ==================== Bersama ==================== */

    /**
     * Foto dikirim sebagai data URL, pola yang sama dengan tanda tangan
     * permintaan kantor. Dibatasi jumlah dan ukurannya supaya satu permintaan
     * tidak bisa mengirim puluhan megabita.
     *
     * @return array<string, mixed>
     */
    private function aturanFoto(): array
    {
        return [
            'foto' => ['nullable', 'array', 'max:8'],
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
     */
    private function simpanFoto(Task $task, array $data): void
    {
        $daftar = $data['foto'] ?? [];
        if (! $daftar) {
            return;
        }

        $tersimpan = [];
        foreach (array_values($daftar) as $i => $f) {
            $jalur = $this->simpanSatuFoto($task->id, $i, (string) $f['data']);
            if ($jalur) {
                $tersimpan[] = ['label' => $f['label'], 'jalur' => $jalur];
            }
        }

        if ($tersimpan) {
            $task->update(['detail_layanan' => [...$task->detail_layanan, 'foto' => $tersimpan]]);
        }
    }

    private function simpanSatuFoto(int $taskId, int $urutan, string $dataUrl): ?string
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

        $jalur = "servis-ac/{$taskId}-{$urutan}.".($png ? 'png' : 'jpg');
        Storage::disk('public')->put($jalur, $biner);

        return $jalur;
    }

    /** Nomor permintaan penawaran; berbeda dari invoice supaya terbaca beda. */
    private function nomorPermintaan(): string
    {
        return 'REQ-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function ringkasanPerbaikan(array $r, array $d): string
    {
        return implode(' · ', [
            "{$r['unit']} unit AC {$d['tipe']}",
            "kapasitas {$d['kapasitas']} PK",
            "merek {$d['merek']}",
            'keluhan: '.implode(', ', $d['keluhan']),
            $d['menyala'] ? 'unit masih menyala' : 'unit tidak menyala',
            'mulai '.$d['mulai_terjadi'],
        ]);
    }

    private function ringkasanPasang(array $s): string
    {
        $bagian = [
            str_replace('-', ' ', $s['jenis_pekerjaan']),
            "{$s['unit']} unit",
            "kapasitas {$s['kapasitas']} PK",
            "indoor di {$s['lokasi_indoor']}",
            "outdoor di {$s['lokasi_outdoor']}",
            'penawaran lewat '.str_replace('-', ' ', $s['cara_penawaran']),
        ];

        if ($s['material']) {
            $bagian[] = 'material: '.implode(', ', $s['material']);
        }

        return implode(' · ', $bagian);
    }

    private function parseJadwal(string $tanggal, string $slot): ?Carbon
    {
        try {
            return Carbon::parse(trim($tanggal.' '.explode('-', $slot)[0]));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @return array{0:string,1:float,2:float,3:int|null}
     */
    private function resolveLokasi(array $data, int $userId): array
    {
        if (! empty($data['address_id'])) {
            $address = Address::where('id', $data['address_id'])->where('user_id', $userId)->firstOrFail();

            return [$address->alamat, (float) $address->lat, (float) $address->lng, $address->id];
        }

        return [$data['lokasi_alamat'], (float) $data['lokasi_lat'], (float) $data['lokasi_lng'], null];
    }
}
