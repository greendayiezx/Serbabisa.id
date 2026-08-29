<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Category;
use App\Models\Task;
use App\Services\ACTarif;
use App\Services\FreonTarif;
use App\Services\NomorInvoice;
use App\Services\PromoAC;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Cek & Tambah Freon.
 *
 * Alurnya sengaja dua tahap, dan itu bukan sekadar susunan layar:
 *
 * 1. Pelanggan memesan PEMERIKSAAN. Yang ditagih di muka hanya itu.
 * 2. Setelah teknisi memeriksa, hasilnya ditulis ke pesanan sebagai diagnosis
 *    beserta rekomendasi pekerjaan. Pelanggan MENYETUJUI atau MENOLAK.
 *
 * Tanpa persetujuan itu, tidak ada satu rupiah pun yang bertambah pada
 * tagihan — pekerjaan lanjutan hanya masuk setelah tombolnya ditekan.
 */
class FreonController extends Controller
{
    public function __construct(
        private readonly FreonTarif $tarif,
        private readonly NomorInvoice $nomorInvoice,
        private readonly PromoAC $promo,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'unit' => ['required', 'integer', 'min:1', 'max:10'],
            'keluhan' => ['required', 'array', 'min:1'],
            'keluhan.*' => [Rule::in(FreonTarif::KELUHAN)],
            'menyala' => ['required', 'boolean'],
            'tipe' => ['required', Rule::in(ACTarif::TIPE)],
            'kapasitas' => ['required', Rule::in(ACTarif::KAPASITAS)],
            'merek' => ['required', Rule::in(FreonTarif::MEREK)],
            'jenis_freon' => ['required', Rule::in(FreonTarif::JENIS_FREON)],
            'catatan' => ['nullable', 'string', 'max:500'],

            'tanggal' => ['required', 'date'],
            'slot' => ['required', Rule::in(FreonTarif::SLOT)],

            'address_id' => ['nullable', 'integer'],
            'lokasi_alamat' => ['required_without:address_id', 'string', 'max:255'],
            'lokasi_lat' => ['required_without:address_id', 'numeric'],
            'lokasi_lng' => ['required_without:address_id', 'numeric'],
            'metode' => ['nullable', 'string', 'max:30'],
            'promo_kode' => ['nullable', 'string', 'max:40'],
        ]);

        $rincian = $this->tarif->pemeriksaan((int) $data['unit']);

        $hasilPromo = $this->promo->hitung(
            $data['promo_kode'] ?? null,
            $rincian['total'],
            (int) $data['unit'],
            $user,
            'freon',
        );
        $potongan = $hasilPromo['potongan'];
        $totalDitagih = $rincian['total'] - $potongan;

        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);
        $jadwal = $this->parseJadwal($data['tanggal'], $data['slot']);
        $kategoriId = Category::where('slug', 'bisatukang')->value('id');

        $task = DB::transaction(function () use (
            $user, $data, $rincian, $kategoriId, $addressId, $alamat, $lat, $lng, $jadwal,
            $potongan, $totalDitagih
        ) {
            $task = Task::create([
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => $kategoriId,
                'address_id' => $addressId,
                'tipe' => 'fixed',
                'judul' => "Servis AC — Cek & Tambah Freon ({$rincian['unit']} unit)",
                'deskripsi' => $this->ringkasan($rincian, $data),
                'status' => 'pending',
                'fulfillment_status' => 'diproses',
                'lokasi_alamat' => $alamat,
                'lokasi_lat' => $lat,
                'lokasi_lng' => $lng,
                'harga' => $totalDitagih,
                'catatan' => $data['catatan'] ?? null,
                'dijadwalkan_pada' => $jadwal,
                'detail_layanan' => [
                    'layanan' => 'freon',
                    'unit' => $rincian['unit'],
                    'keluhan' => $data['keluhan'],
                    'menyala' => (bool) $data['menyala'],
                    'tipe' => $data['tipe'],
                    'kapasitas' => $data['kapasitas'],
                    'merek' => $data['merek'],
                    'jenis_freon' => $data['jenis_freon'],
                    'slot' => $data['slot'],
                    'biaya_pemeriksaan' => $rincian['total'],
                    'promo_kode' => $potongan > 0 ? strtoupper(trim($data['promo_kode'])) : null,
                    'potongan_promo' => $potongan,
                    // Diisi teknisi setelah memeriksa; null berarti belum ada
                    // hasil, dan layar hasil pemeriksaan belum boleh tampil.
                    'diagnosis' => null,
                    'area' => ['Pemeriksaan AC'],
                    'jumlah_cleaner' => 1,
                ],
            ]);

            $task->items()->create([
                'nama' => 'Pemeriksaan AC (tekanan & kebocoran)',
                'kategori' => 'layanan',
                'satuan' => 'kunjungan',
                'harga_satuan' => FreonTarif::BIAYA_PEMERIKSAAN,
                'qty' => 1,
                'subtotal' => FreonTarif::BIAYA_PEMERIKSAAN,
            ]);

            if ($rincian['biaya_unit_tambahan'] > 0) {
                $task->items()->create([
                    'nama' => 'Unit tambahan',
                    'kategori' => 'layanan',
                    'satuan' => 'unit',
                    'harga_satuan' => FreonTarif::BIAYA_UNIT_TAMBAHAN,
                    'qty' => $rincian['unit'] - 1,
                    'subtotal' => $rincian['biaya_unit_tambahan'],
                ]);
            }

            $task->payment()->create([
                'jumlah' => $totalDitagih,
                'subtotal_barang' => $rincian['total'],
                'ongkir' => 0,
                'ongkir_normal' => 0,
                'potongan' => $potongan,
                'cashback' => 0,
                'service_fee' => 0,
                'komisi_platform' => $totalDitagih - $rincian['biaya'],
                'status' => 'pending',
                'metode' => $data['metode'] ?? null,
            ]);

            return $task;
        });

        return response()->json([
            ...$task->load(['items', 'payment'])->toArray(),
            'rincian' => [
                ...$rincian,
                'promo_kode' => $potongan > 0 ? strtoupper(trim($data['promo_kode'])) : null,
                'potongan_promo' => $potongan,
                'promo_ditolak' => $hasilPromo['alasan'],
                'total_ditagih' => $totalDitagih,
            ],
        ], 201);
    }

    /**
     * Pelanggan menyetujui rekomendasi teknisi.
     *
     * Baru di sinilah tagihan bertambah. Harganya dihitung ulang dari katalog
     * pekerjaan — daftar yang dikirim klien hanya menyatakan PERSETUJUAN, bukan
     * nominalnya.
     */
    public function setujui(Request $request, string $nomor): JsonResponse
    {
        $task = $this->cariMilikPengguna($request, $nomor);
        $detail = $task->detail_layanan ?? [];
        $diagnosis = $detail['diagnosis'] ?? null;

        abort_if($diagnosis === null, 422, 'Pemeriksaan belum selesai.');
        abort_if(($diagnosis['keputusan'] ?? null) !== null, 422, 'Rekomendasi ini sudah dijawab.');

        $rekomendasi = $this->tarif->rekomendasi(
            $diagnosis['pekerjaan'] ?? [],
            (int) ($detail['biaya_pemeriksaan'] ?? 0),
        );

        DB::transaction(function () use ($task, $detail, $diagnosis, $rekomendasi) {
            foreach ($rekomendasi['baris'] as $b) {
                $task->items()->create([
                    'nama' => $b['nama'],
                    'kategori' => 'perbaikan',
                    'satuan' => $b['satuan'],
                    'harga_satuan' => $b['harga'],
                    'qty' => 1,
                    'subtotal' => $b['harga'],
                ]);
            }

            $diagnosis['keputusan'] = 'disetujui';
            $diagnosis['dijawab_pada'] = now()->toIso8601String();

            $task->update([
                'harga' => $rekomendasi['subtotal'],
                'detail_layanan' => [...$detail, 'diagnosis' => $diagnosis],
            ]);

            $task->payment?->update([
                'jumlah' => $rekomendasi['subtotal'],
                'subtotal_barang' => $rekomendasi['subtotal'],
                // Pemeriksaan yang sudah dibayar dikreditkan, bukan ditagih ulang.
                'potongan' => $rekomendasi['kredit_pemeriksaan'],
                'komisi_platform' => $rekomendasi['total'] - $rekomendasi['biaya'],
            ]);
        });

        return response()->json([
            'nomor' => $task->nomor_invoice,
            'keputusan' => 'disetujui',
            'rekomendasi' => $rekomendasi,
        ]);
    }

    /** Pelanggan menolak pekerjaan tambahan; yang dibayar tetap pemeriksaannya. */
    public function tolak(Request $request, string $nomor): JsonResponse
    {
        $task = $this->cariMilikPengguna($request, $nomor);
        $detail = $task->detail_layanan ?? [];
        $diagnosis = $detail['diagnosis'] ?? null;

        abort_if($diagnosis === null, 422, 'Pemeriksaan belum selesai.');
        abort_if(($diagnosis['keputusan'] ?? null) !== null, 422, 'Rekomendasi ini sudah dijawab.');

        $diagnosis['keputusan'] = 'ditolak';
        $diagnosis['dijawab_pada'] = now()->toIso8601String();

        $task->update(['detail_layanan' => [...$detail, 'diagnosis' => $diagnosis]]);

        return response()->json(['nomor' => $task->nomor_invoice, 'keputusan' => 'ditolak']);
    }

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

    private function ringkasan(array $r, array $d): string
    {
        $bagian = [
            "{$r['unit']} unit AC {$d['tipe']}",
            "kapasitas {$d['kapasitas']} PK",
            "merek {$d['merek']}",
            'freon '.($d['jenis_freon'] === 'tidak-tahu' ? 'belum diketahui' : strtoupper($d['jenis_freon'])),
            'keluhan: '.implode(', ', $d['keluhan']),
            $d['menyala'] ? 'unit masih menyala' : 'unit tidak menyala',
        ];

        return implode(' · ', $bagian);
    }

    /** Slot berupa rentang; yang disimpan jam mulainya. */
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
