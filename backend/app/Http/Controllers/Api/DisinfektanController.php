<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Category;
use App\Models\Task;
use App\Services\DisinfektanTarif;
use App\Services\NomorInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Layanan Disinfektan.
 *
 * Dua penolakan disengaja di sini, dan keduanya lebih penting daripada
 * pesanan yang berhasil dibuat:
 *
 * 1. Ada darah, cairan tubuh, atau limbah berisiko → DITOLAK, diarahkan ke
 *    penyedia khusus. Pekerjaan itu butuh SOP, personel, dan perlengkapan yang
 *    belum dimiliki; menerimanya lalu mengerjakannya seadanya membahayakan
 *    petugas sekaligus pelanggan.
 * 2. Luas di atas 300 m² → DITOLAK sebagai pesanan langsung, diarahkan ke
 *    permintaan penawaran. Menagihnya dari satu angka wakil berarti menagih
 *    terlalu murah untuk gedung yang jauh lebih besar.
 */
class DisinfektanController extends Controller
{
    public function __construct(
        private readonly DisinfektanTarif $tarif,
        private readonly NomorInvoice $nomorInvoice,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'properti' => ['required', Rule::in(DisinfektanTarif::PROPERTI)],
            'luas' => ['required', Rule::in(DisinfektanTarif::LUAS)],
            'ruangan' => ['required', 'integer', 'min:1', 'max:60'],
            'toilet' => ['required', 'integer', 'min:0', 'max:30'],
            'kondisi' => ['required', Rule::in(DisinfektanTarif::KONDISI)],

            'perhatian' => ['nullable', 'array'],
            'perhatian.*' => [
                Rule::in([...DisinfektanTarif::PERHATIAN, DisinfektanTarif::PERHATIAN_DITOLAK]),
            ],
            'catatan' => ['nullable', 'string', 'max:500'],

            'tanggal' => ['required', 'date'],
            'waktu' => ['required', 'string', 'max:20'],

            'nama_penerima' => ['required', 'string', 'max:100'],
            'telepon_penerima' => ['required', 'string', 'max:30'],

            'address_id' => ['nullable', 'integer'],
            'lokasi_alamat' => ['required_without:address_id', 'string', 'max:255'],
            'lokasi_lat' => ['required_without:address_id', 'numeric'],
            'lokasi_lng' => ['required_without:address_id', 'numeric'],
            'metode' => ['nullable', 'string', 'max:30'],
        ]);

        $perhatian = $data['perhatian'] ?? [];

        if (in_array(DisinfektanTarif::PERHATIAN_DITOLAK, $perhatian, true)) {
            throw ValidationException::withMessages([
                'perhatian' => 'Untuk area dengan darah, cairan tubuh, atau limbah berisiko, '.
                    'layanan ini belum bisa menanganinya. Hubungi penyedia jasa dekontaminasi khusus '.
                    'agar penanganannya aman untuk Anda dan petugas.',
            ]);
        }

        if ($data['luas'] === DisinfektanTarif::LUAS_PENAWARAN) {
            throw ValidationException::withMessages([
                'luas' => 'Area di atas 300 m² dihitung lewat penawaran, bukan harga pasang. '.
                    'Ajukan permintaan penawaran supaya harganya sesuai kondisi lokasinya.',
            ]);
        }

        $rincian = $this->tarif->hitung(
            $data['properti'],
            $data['luas'],
            (int) $data['ruangan'],
            (int) $data['toilet'],
            $data['kondisi'],
        );

        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);
        $jadwal = $this->parseJadwal($data['tanggal'], $data['waktu']);

        $task = DB::transaction(function () use ($user, $data, $rincian, $perhatian, $addressId, $alamat, $lat, $lng, $jadwal) {
            $task = Task::create([
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => Category::where('slug', 'bisabersih')->value('id'),
                'address_id' => $addressId,
                'tipe' => 'fixed',
                'judul' => "BisaBersih — Disinfektan ({$rincian['luas']} m²)",
                'deskripsi' => $this->ringkasan($rincian, $perhatian),
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
                    'layanan' => 'disinfektan',
                    'properti' => $rincian['properti'],
                    'golongan' => $rincian['golongan'],
                    'luas' => $rincian['luas'],
                    'ruangan' => $rincian['ruangan'],
                    'toilet' => $rincian['toilet'],
                    'kondisi' => $rincian['kondisi'],
                    'perhatian' => $perhatian,
                    /*
                     * Waktu kontak sengaja TIDAK diisi angka di sini. Tiap
                     * produk punya labelnya sendiri; petugas yang mencatat
                     * produk dan waktu kontaknya di laporan setelah pekerjaan.
                     */
                    'metode' => 'aplikasi permukaan',
                    'produk' => null,
                    'waktu_kontak' => null,
                    'area' => DisinfektanTarif::AREA[$rincian['golongan']],
                    'jumlah_cleaner' => 1,
                ],
            ]);

            foreach ($rincian['baris'] as $b) {
                $task->items()->create([
                    'nama' => $b['label'],
                    'kategori' => 'layanan',
                    'satuan' => 'paket',
                    'harga_satuan' => $b['nilai'],
                    'qty' => 1,
                    'subtotal' => $b['nilai'],
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

        return response()->json([
            ...$task->load(['items', 'payment'])->toArray(),
            'rincian' => $rincian,
        ], 201);
    }

    /**
     * Permintaan penawaran — untuk kantor besar dan area di atas 300 m².
     *
     * Tidak membuat pembayaran dan tidak menyebut harga final.
     */
    public function permintaan(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'properti' => ['required', Rule::in(DisinfektanTarif::PROPERTI)],
            'luas' => ['required', Rule::in(DisinfektanTarif::LUAS)],
            'ruangan' => ['required', 'integer', 'min:1', 'max:500'],
            'toilet' => ['required', 'integer', 'min:0', 'max:200'],
            'kondisi' => ['required', Rule::in(DisinfektanTarif::KONDISI)],
            'frekuensi' => ['nullable', 'string', 'max:40'],
            'catatan' => ['nullable', 'string', 'max:1000'],

            'nama_penerima' => ['required', 'string', 'max:100'],
            'telepon_penerima' => ['required', 'string', 'max:30'],

            'lokasi_alamat' => ['required', 'string', 'max:255'],
            'lokasi_lat' => ['nullable', 'numeric'],
            'lokasi_lng' => ['nullable', 'numeric'],
        ]);

        $spek = [
            'layanan' => 'disinfektan',
            'permintaan_penawaran' => true,
            'properti' => $data['properti'],
            'golongan' => DisinfektanTarif::golongan($data['properti']),
            'luas' => $data['luas'],
            'ruangan' => (int) $data['ruangan'],
            'toilet' => (int) $data['toilet'],
            'kondisi' => $data['kondisi'],
            'frekuensi' => $data['frekuensi'] ?? null,
            'area' => DisinfektanTarif::AREA[DisinfektanTarif::golongan($data['properti'])],
            'jumlah_cleaner' => 1,
        ];

        $task = Task::create([
            'nomor_invoice' => 'REQ-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3))),
            'customer_id' => $user->id,
            'category_id' => Category::where('slug', 'bisabersih')->value('id'),
            'tipe' => 'custom',
            'judul' => 'Permintaan Penawaran — Disinfektan',
            'deskripsi' => $this->ringkasan(
                ['luas' => $data['luas'], 'ruangan' => $data['ruangan'], 'toilet' => $data['toilet'],
                    'kondisi' => $data['kondisi'], 'properti' => $data['properti']],
                [],
            ),
            'status' => 'pending',
            'fulfillment_status' => 'diproses',
            'lokasi_alamat' => $data['lokasi_alamat'],
            'lokasi_lat' => $data['lokasi_lat'] ?? 0,
            'lokasi_lng' => $data['lokasi_lng'] ?? 0,
            'catatan' => $data['catatan'] ?? null,
            'nama_penerima' => $data['nama_penerima'],
            'telepon_penerima' => $data['telepon_penerima'],
            'detail_layanan' => $spek,
        ]);

        return response()->json([
            'id' => $task->id,
            'nomor' => $task->nomor_invoice,
            'luas' => $data['luas'],
        ], 201);
    }

    private function ringkasan(array $r, array $perhatian): string
    {
        $bagian = [
            "properti {$r['properti']}",
            "luas {$r['luas']} m²",
            "{$r['ruangan']} ruangan",
            "{$r['toilet']} toilet",
            'kondisi '.str_replace('-', ' ', $r['kondisi']),
        ];

        if ($perhatian) {
            $bagian[] = 'perhatian: '.implode(', ', array_map(
                fn ($p) => str_replace('-', ' ', $p),
                $perhatian,
            ));
        }

        return implode(' · ', $bagian);
    }

    private function parseJadwal(string $tanggal, string $waktu): ?Carbon
    {
        try {
            return Carbon::parse(trim($tanggal.' '.explode('-', $waktu)[0]));
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
