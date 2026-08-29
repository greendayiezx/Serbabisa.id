<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Category;
use App\Models\Task;
use App\Services\ACTarif;
use App\Services\NomorInvoice;
use App\Services\PromoAC;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Checkout Servis AC.
 *
 * Klien mengirim PILIHAN — paket, jumlah unit, tipe, kapasitas, kondisi, jadwal
 * — dan seluruh angka uang dihitung ulang ACTarif. Total yang ditampilkan
 * browser tidak pernah jadi dasar tagihan.
 */
class ACCheckoutController extends Controller
{
    public function __construct(
        private readonly ACTarif $tarif,
        private readonly NomorInvoice $nomorInvoice,
        private readonly PromoAC $promo,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'paket' => ['required', Rule::in(array_keys(ACTarif::PAKET))],
            'unit' => ['required', 'integer', 'min:1', 'max:20'],
            'tipe' => ['required', Rule::in(ACTarif::TIPE)],
            'kapasitas' => ['required', Rule::in(ACTarif::KAPASITAS)],
            'terakhir_cuci' => ['nullable', Rule::in(ACTarif::TERAKHIR_CUCI)],
            'kondisi' => ['nullable', 'array'],
            'kondisi.*' => [Rule::in(ACTarif::KONDISI)],
            'rutin' => ['nullable', Rule::in(ACTarif::RUTIN)],
            'catatan' => ['nullable', 'string', 'max:500'],

            'tanggal' => ['required', 'date'],
            'waktu' => ['required', 'string'],

            /*
             * Kontak yang ditemui teknisi di lokasi — belum tentu pemilik akun.
             * Wajib: teknisi yang sudah berangkat dan tidak menemukan siapa pun
             * berarti satu kunjungan terbuang, dan biayanya nyata.
             */
            'nama_penerima' => ['required', 'string', 'max:100'],
            'telepon_penerima' => ['required', 'string', 'max:30'],

            'address_id' => ['nullable', 'integer'],
            'lokasi_alamat' => ['required_without:address_id', 'string', 'max:255'],
            'lokasi_lat' => ['required_without:address_id', 'numeric'],
            'lokasi_lng' => ['required_without:address_id', 'numeric'],
            'metode' => ['nullable', 'string', 'max:30'],
            'promo_kode' => ['nullable', 'string', 'max:40'],
        ]);

        $rincian = $this->tarif->hitung($data['paket'], (int) $data['unit']);

        $hasilPromo = $this->promo->hitung(
            $data['promo_kode'] ?? null,
            $rincian['total'],
            (int) $data['unit'],
            $user,
        );
        $potonganPromo = $hasilPromo['potongan'];
        $totalDitagih = $rincian['total'] - $potonganPromo;

        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);
        $jadwal = $this->parseJadwal($data['tanggal'], $data['waktu']);

        /*
         * Servis AC masuk kategori BisaTukang: pekerjaannya teknis, bukan
         * kebersihan rumah. Kalau kelak ada kategori sendiri, cukup slug ini
         * yang berubah.
         */
        $kategoriId = Category::where('slug', 'bisatukang')->value('id');

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
                'judul' => "Servis AC — {$rincian['nama_paket']} ({$rincian['unit']} unit)",
                'deskripsi' => $this->ringkasan($rincian, $data),
                'status' => 'pending',
                'fulfillment_status' => 'diproses',
                'lokasi_alamat' => $alamat,
                'lokasi_lat' => $lat,
                'lokasi_lng' => $lng,
                'harga' => $totalDitagih,
                'catatan' => $data['catatan'] ?? null,
                'dijadwalkan_pada' => $jadwal,
                'nama_penerima' => $data['nama_penerima'],
                'telepon_penerima' => $data['telepon_penerima'],
                'detail_layanan' => [
                    'layanan' => 'servis-ac',
                    'paket' => $rincian['paket'],
                    'nama_paket' => $rincian['nama_paket'],
                    'unit' => $rincian['unit'],
                    'tipe' => $data['tipe'],
                    'kapasitas' => $data['kapasitas'],
                    'terakhir_cuci' => $data['terakhir_cuci'] ?? null,
                    'kondisi' => $data['kondisi'] ?? [],
                    // Jadwal rutin dicatat, tapi potongannya berlaku untuk
                    // kunjungan BERIKUTNYA — bukan yang ini.
                    'rutin' => $data['rutin'] ?? null,
                    'promo_kode' => $potonganPromo > 0 ? strtoupper(trim($data['promo_kode'])) : null,
                    'potongan_promo' => $potonganPromo,
                    'area' => ['Servis AC'],
                    'jumlah_cleaner' => 1,
                ],
            ]);

            $baris = [[
                'nama' => "{$rincian['nama_paket']} · AC {$data['tipe']}",
                'kategori' => 'layanan',
                'satuan' => 'unit',
                'harga_satuan' => $rincian['harga_per_unit'],
                'qty' => $rincian['unit'],
                'subtotal' => $rincian['layanan'],
            ]];

            if ($rincian['biaya_kunjungan'] > 0) {
                $baris[] = [
                    'nama' => 'Biaya kunjungan teknisi',
                    'kategori' => 'biaya',
                    'satuan' => 'kunjungan',
                    'harga_satuan' => $rincian['biaya_kunjungan'],
                    'qty' => 1,
                    'subtotal' => $rincian['biaya_kunjungan'],
                ];
            }

            $task->items()->createMany($baris);

            $task->payment()->create([
                'jumlah' => $totalDitagih,
                'subtotal_barang' => $rincian['layanan'],
                'ongkir' => $rincian['biaya_kunjungan'],
                'ongkir_normal' => ACTarif::BIAYA_KUNJUNGAN,
                'potongan' => $rincian['diskon_bundling'] + $potonganPromo,
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
                'promo_ditolak' => $hasilPromo['alasan'],
                'total_ditagih' => $totalDitagih,
            ],
        ], 201);
    }

    /** Ringkasan yang bisa dibaca teknisi tanpa membuka JSON. */
    private function ringkasan(array $r, array $d): string
    {
        $bagian = [
            "{$r['unit']} unit AC {$d['tipe']}",
            "kapasitas {$d['kapasitas']} PK",
            $r['nama_paket'],
        ];

        if (! empty($d['terakhir_cuci'])) {
            $bagian[] = "terakhir dicuci {$d['terakhir_cuci']}";
        }
        if (! empty($d['kondisi'])) {
            $bagian[] = 'keluhan: '.implode(', ', $d['kondisi']);
        }
        if (! empty($d['rutin'])) {
            $bagian[] = "jadwal rutin tiap {$d['rutin']}";
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
