<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Category;
use App\Models\Task;
use App\Services\KantorTarif;
use App\Services\PromoKantor;
use App\Services\NomorInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Pesan langsung BisaBersih Kantor — jalur "Pesan Sekarang".
 *
 * Untuk kantor kecil/menengah yang harganya sudah bisa ditentukan dari data di
 * aplikasi. Kantor besar TIDAK lewat sini: luasnya tidak berbatas, jadi harus
 * melalui penawaran setelah survei (lihat KantorTarif::bisaDipesanLangsung).
 *
 * Seperti checkout lain di aplikasi ini, klien hanya mengirim PILIHAN. Seluruh
 * tagihan dihitung ulang KantorTarif — total yang diklaim browser tidak pernah
 * dipakai.
 */
class KantorCheckoutController extends Controller
{
    public function __construct(
        private readonly KantorTarif $tarif,
        private readonly NomorInvoice $nomorInvoice,
        private readonly PromoKantor $promo,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'jenis_kantor' => ['required', Rule::in(array_keys(KantorTarif::JENIS))],
            'paket' => ['required', Rule::in(array_keys(KantorTarif::PAKET))],
            'frekuensi' => ['required', Rule::in(array_keys(KantorTarif::FREKUENSI))],
            'workstation' => ['required', 'integer', 'min:0', 'max:500'],
            'ruang_meeting' => ['required', 'integer', 'min:0', 'max:100'],
            'toilet' => ['required', 'integer', 'min:0', 'max:100'],
            'pantry' => ['required', 'integer', 'min:0', 'max:50'],
            'add_on' => ['nullable', 'array'],
            'add_on.*' => [Rule::in(array_keys(KantorTarif::ADD_ON))],
            'lainnya' => ['nullable', 'string', 'max:255'],
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

        // Penjagaan utama jalur ini. Ditolak di server, bukan hanya disembunyikan
        // tombolnya di layar.
        if (! KantorTarif::bisaDipesanLangsung($data['jenis_kantor'])) {
            return response()->json([
                'message' => 'Kantor besar perlu survei dulu. Silakan pakai Kirim Penawaran.',
                'errors' => ['jenis_kantor' => ['Kantor besar tidak bisa dipesan langsung.']],
            ], 422);
        }

        $rincian = $this->tarif->hitung(
            $data['jenis_kantor'],
            $data['paket'],
            (int) $data['workstation'],
            (int) $data['ruang_meeting'],
            (int) $data['toilet'],
            (int) $data['pantry'],
            $data['add_on'] ?? [],
            $data['frekuensi'],
        );

        /*
         * Promo dihitung ULANG di sini, bukan diambil dari klien.
         *
         * Sebelumnya kode promo tidak pernah sampai ke server sama sekali:
         * layar konfirmasi menampilkan total setelah potongan, sedangkan yang
         * ditagih adalah harga penuh.
         */
        $hasilPromo = $this->promo->hitung($data['promo_kode'] ?? null, $rincian['total_per_kunjungan'], $user);
        $potonganPromo = $hasilPromo['potongan'];
        $totalDitagih = $rincian['total_per_kunjungan'] - $potonganPromo;

        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);
        $jadwal = $this->parseJadwal($data['tanggal'], $data['waktu']);
        $kategoriId = Category::where('slug', 'bisabersih')->value('id');

        $task = DB::transaction(function () use (
            $user, $data, $rincian, $kategoriId, $addressId, $alamat, $lat, $lng, $jadwal,
            $potonganPromo, $totalDitagih, $hasilPromo
        ) {
            $task = Task::create([
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => $kategoriId,
                'address_id' => $addressId,
                'tipe' => 'fixed',
                'judul' => "BisaBersih — Bersih Kantor ({$rincian['nama_jenis']})",
                'deskripsi' => $this->ringkasan($rincian, $data),
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
                    'layanan' => 'kantor',
                    'jenis_kantor' => $rincian['jenis_kantor'],
                    'nama_jenis' => $rincian['nama_jenis'],
                    'luas_acuan' => $rincian['luas_acuan'],
                    'paket' => $rincian['paket'],
                    'frekuensi' => $rincian['frekuensi'],
                    'workstation' => (int) $data['workstation'],
                    'ruang_meeting' => (int) $data['ruang_meeting'],
                    'toilet' => (int) $data['toilet'],
                    'pantry' => (int) $data['pantry'],
                    'lainnya' => $data['lainnya'] ?? null,
                    'add_on' => array_column($rincian['baris_add_on'], 'id'),
                    // Dipakai halaman detail order untuk menampilkan susunan kru.
                    'jumlah_cleaner' => $rincian['jumlah_kru'],
                    'promo_kode' => $potonganPromo > 0 ? strtoupper(trim($data['promo_kode'])) : null,
                    'potongan_promo' => $potonganPromo,
                ],
            ]);

            $baris = [[
                'nama' => "Bersih Kantor · {$rincian['nama_paket']} · {$rincian['nama_jenis']}",
                'kategori' => 'layanan',
                'satuan' => 'kunjungan',
                'harga_satuan' => $rincian['layanan'],
                'qty' => 1,
                'subtotal' => $rincian['layanan'],
            ]];
            foreach ($rincian['baris_add_on'] as $a) {
                $baris[] = [
                    'nama' => $a['nama'],
                    'kategori' => 'add-on',
                    'satuan' => 'paket',
                    'harga_satuan' => $a['harga'],
                    'qty' => 1,
                    'subtotal' => $a['harga'],
                ];
            }
            $task->items()->createMany($baris);

            $task->payment()->create([
                'jumlah' => $totalDitagih,
                'subtotal_barang' => $rincian['layanan'] + $rincian['add_on'],
                'ongkir' => 0,
                'ongkir_normal' => 0,
                'potongan' => $rincian['diskon_frekuensi'] + $potonganPromo,
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
                // Diisi kalau kodenya dikirim tapi ditolak — halaman status
                // menampilkannya apa adanya alih-alih diam.
                'promo_ditolak' => $hasilPromo['alasan'],
                'total' => $totalDitagih,
            ],
        ], 201);
    }

    /** Ringkasan yang bisa dibaca kru tanpa membuka JSON. */
    private function ringkasan(array $r, array $d): string
    {
        $bagian = array_filter([
            "{$r['nama_jenis']} (~{$r['luas_acuan']} m²)",
            "paket {$r['nama_paket']}",
            "frekuensi {$r['label_frekuensi']}",
            "{$d['workstation']} workstation",
            "{$d['ruang_meeting']} ruang meeting",
            "{$d['toilet']} toilet",
            "{$d['pantry']} pantry",
            ! empty($d['lainnya']) ? "area lain: {$d['lainnya']}" : null,
        ]);

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
