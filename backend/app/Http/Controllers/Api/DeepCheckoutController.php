<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Category;
use App\Models\Task;
use App\Services\DeepTarif;
use App\Services\NomorInvoice;
use App\Services\PromoDeep;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Checkout BisaBersih Deep Cleaning.
 *
 * Seperti checkout lain di aplikasi ini, klien hanya mengirim PILIHAN: paket,
 * luas, jumlah ruangan, layanan tambahan, jadwal, dan lokasi. Seluruh angka
 * uang dihitung ulang DeepTarif — total yang ditampilkan browser tidak pernah
 * jadi dasar tagihan.
 */
class DeepCheckoutController extends Controller
{
    public function __construct(
        private readonly DeepTarif $tarif,
        private readonly NomorInvoice $nomorInvoice,
        private readonly PromoDeep $promo,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'paket' => ['required', Rule::in(array_keys(DeepTarif::PAKET))],
            'luas_m2' => ['required', 'integer', 'min:10', 'max:1000'],
            'jumlah_ruangan' => ['required', 'integer', 'min:1', 'max:30'],
            'add_on' => ['nullable', 'array'],
            'add_on.*' => [Rule::in(array_keys(DeepTarif::ADD_ON))],
            'catatan' => ['nullable', 'string', 'max:500'],

            'tanggal' => ['required', 'date'],
            'waktu' => ['required', 'string'],

            'address_id' => ['nullable', 'integer'],
            'lokasi_alamat' => ['required_without:address_id', 'string', 'max:255'],
            'lokasi_lat' => ['required_without:address_id', 'numeric'],
            'lokasi_lng' => ['required_without:address_id', 'numeric'],
            'nama_penerima' => ['nullable', 'string', 'max:100'],
            'telepon_penerima' => ['nullable', 'string', 'max:30'],
            'metode' => ['nullable', 'string', 'max:30'],
            'promo_kode' => ['nullable', 'string', 'max:40'],
        ]);

        $rincian = $this->tarif->hitung(
            $data['paket'],
            (int) $data['luas_m2'],
            (int) $data['jumlah_ruangan'],
            $data['add_on'] ?? [],
        );

        /*
         * Potongan dihitung ULANG di sini, bukan diambil dari klien: layar boleh
         * menampilkan estimasi, tapi yang menagih adalah baris ini.
         */
        $hasilPromo = $this->promo->hitung(
            $data['promo_kode'] ?? null,
            $rincian['total'],
            $data['paket'],
            $user,
        );
        $potonganPromo = $hasilPromo['potongan'];
        $totalDitagih = $rincian['total'] - $potonganPromo;

        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);
        $jadwal = $this->parseJadwal($data['tanggal'], $data['waktu']);
        $kategoriId = Category::where('slug', 'bisabersih')->value('id');

        $task = DB::transaction(function () use (
            $user, $data, $rincian, $kategoriId, $addressId, $alamat, $lat, $lng, $jadwal,
            $potonganPromo, $totalDitagih
        ) {
            $task = Task::create([
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => $kategoriId,
                'address_id' => $addressId,
                'tipe' => 'fixed',
                'judul' => "BisaBersih — Deep Cleaning ({$rincian['nama_paket']})",
                'deskripsi' => $this->ringkasan($rincian),
                'status' => 'pending',
                'fulfillment_status' => 'diproses',
                'lokasi_alamat' => $alamat,
                'lokasi_lat' => $lat,
                'lokasi_lng' => $lng,
                'harga' => $totalDitagih,
                'catatan' => $data['catatan'] ?? null,
                'dijadwalkan_pada' => $jadwal,
                'nama_penerima' => $data['nama_penerima'] ?? null,
                'telepon_penerima' => $data['telepon_penerima'] ?? null,
                'detail_layanan' => [
                    'layanan' => 'deep',
                    'paket' => $rincian['paket'],
                    'nama_paket' => $rincian['nama_paket'],
                    'luas_m2' => $rincian['luas_m2'],
                    'jumlah_ruangan' => $rincian['jumlah_ruangan'],
                    'add_on' => $rincian['add_on_dipakai'],
                    'termasuk_paket' => $rincian['termasuk'],
                    // Dibaca halaman status: jumlah kru & lama pengerjaan.
                    'jumlah_cleaner' => $rincian['jumlah_kru'],
                    'durasi_jam' => $rincian['durasi_jam'],
                    'area' => ['Deep cleaning menyeluruh'],
                    'promo_kode' => $potonganPromo > 0 ? strtoupper(trim($data['promo_kode'])) : null,
                    'potongan_promo' => $potonganPromo,
                ],
            ]);

            $baris = [[
                'nama' => "Deep Cleaning · {$rincian['nama_paket']}",
                'kategori' => 'layanan',
                'satuan' => 'paket',
                'harga_satuan' => $rincian['harga_paket'],
                'qty' => 1,
                'subtotal' => $rincian['harga_paket'],
            ]];

            if ($rincian['biaya_luas'] > 0) {
                $baris[] = [
                    'nama' => "Kelebihan luas {$rincian['kelebihan_luas']} m²",
                    'kategori' => 'penyesuaian',
                    'satuan' => 'm²',
                    'harga_satuan' => DeepTarif::TARIF_LUAS,
                    'qty' => $rincian['kelebihan_luas'],
                    'subtotal' => $rincian['biaya_luas'],
                ];
            }

            if ($rincian['biaya_ruangan'] > 0) {
                $baris[] = [
                    'nama' => "Ruangan tambahan {$rincian['kelebihan_ruangan']}",
                    'kategori' => 'penyesuaian',
                    'satuan' => 'ruangan',
                    'harga_satuan' => DeepTarif::TARIF_RUANGAN,
                    'qty' => $rincian['kelebihan_ruangan'],
                    'subtotal' => $rincian['biaya_ruangan'],
                ];
            }

            foreach ($rincian['baris_add_on'] as $a) {
                $baris[] = [
                    'nama' => $a['nama'],
                    'kategori' => 'add-on',
                    'satuan' => $a['satuan'],
                    'harga_satuan' => $a['harga_satuan'],
                    'qty' => $a['qty'],
                    'subtotal' => $a['subtotal'],
                ];
            }

            $task->items()->createMany($baris);

            $task->payment()->create([
                'jumlah' => $totalDitagih,
                'subtotal_barang' => $rincian['total'],
                'ongkir' => 0,
                'ongkir_normal' => 0,
                'potongan' => $potonganPromo,
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
                'promo_kode' => $potonganPromo > 0 ? strtoupper(trim($data['promo_kode'])) : null,
                'potongan_promo' => $potonganPromo,
                // Diisi kalau kodenya dikirim tapi ditolak — layar berikutnya
                // menampilkannya apa adanya alih-alih diam.
                'promo_ditolak' => $hasilPromo['alasan'],
                'total_ditagih' => $totalDitagih,
            ],
        ], 201);
    }

    /** Ringkasan yang bisa dibaca kru tanpa membuka JSON. */
    private function ringkasan(array $r): string
    {
        $bagian = [
            $r['nama_paket'],
            "{$r['luas_m2']} m²",
            "{$r['jumlah_ruangan']} ruangan",
            "{$r['jumlah_kru']} kru · ±{$r['durasi_jam']} jam",
        ];

        foreach ($r['baris_add_on'] as $a) {
            $bagian[] = $a['nama'];
        }

        return implode(' · ', $bagian);
    }

    private function parseJadwal(string $tanggal, string $waktu): ?Carbon
    {
        try {
            return Carbon::parse(trim($tanggal.' '.$waktu));
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
