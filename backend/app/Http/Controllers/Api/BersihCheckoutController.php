<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\MitraProfile;
use App\Models\Category;
use App\Models\Task;
use App\Services\BersihTarif;
use App\Services\LevelCleaner;
use App\Services\NomorInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Checkout BisaBersih.
 *
 * Klien hanya mengirim PILIHAN, tidak pernah harga. Seluruh tagihan dihitung
 * ulang oleh BersihTarif, termasuk promo pengguna baru — kalau browser mengirim
 * total Rp1, yang tersimpan tetap harga sebenarnya.
 *
 * Pesanan disimpan sebagai `tasks` berkategori bisabersih supaya otomatis muncul
 * di riwayat "Tugas Saya" bersama layanan lain. Rincian khas layanan ini masuk
 * ke kolom JSON `detail_layanan`; baris harga masuk ke `task_items` agar notanya
 * bisa direkonstruksi baris demi baris.
 */
class BersihCheckoutController extends Controller
{
    public function __construct(
        private readonly BersihTarif $tarif,
        private readonly NomorInvoice $nomorInvoice,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'kondisi' => ['nullable', Rule::in(BersihTarif::idKondisi())],
            'durasi_jam' => ['required', 'integer', Rule::in(BersihTarif::DURASI)],
            'jumlah_cleaner' => ['required', 'integer', 'min:1', 'max:'.BersihTarif::MAKS_CLEANER],
            'add_on' => ['array'],
            'add_on.*' => [Rule::in(BersihTarif::idAddOn())],
            'frekuensi' => ['required', Rule::in(BersihTarif::idFrekuensi())],
            'promo_kode' => ['nullable', 'string', 'max:40'],

            // Halaman pemesanan tidak lagi menanyakan detail properti dan akses
            // masuk, tapi validatornya tetap menerimanya: data lama masih punya
            // nilai-nilai ini, dan mitra tetap boleh mengisinya dari sisi lain.
            'tipe_properti' => ['nullable', 'string', 'max:40'],
            'kamar_tidur' => ['nullable', 'integer', 'min:0', 'max:20'],
            'kamar_mandi' => ['nullable', 'integer', 'min:0', 'max:20'],
            'luas_m2' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'ada_hewan' => ['boolean'],
            'area' => ['required', 'array', 'min:1'],
            'area.*' => ['string', 'max:120'],
            'akses_masuk' => ['nullable', 'string', 'max:60'],
            // Kosong = biarkan sistem memilih. Kalau diisi, harus mitra yang
            // benar-benar ada; level & tarifnya diambil dari datanya, bukan
            // dari apa yang dikirim browser.
            'cleaner_id' => ['nullable', 'integer', 'exists:mitra_profiles,user_id'],

            'tanggal' => ['required', 'date'],
            'waktu' => ['required', 'string', 'max:10'],
            'catatan' => ['nullable', 'string', 'max:500'],

            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'lokasi_alamat' => ['required_without:address_id', 'nullable', 'string', 'max:255'],
            'lokasi_lat' => ['required_without:address_id', 'nullable', 'numeric'],
            'lokasi_lng' => ['required_without:address_id', 'nullable', 'numeric'],
            'nama_penerima' => ['nullable', 'string', 'max:120'],
            'telepon_penerima' => ['nullable', 'string', 'max:30'],
            'metode' => ['nullable', 'string', 'max:40'],
        ]);

        $kategoriId = Category::where('slug', 'bisabersih')->value('id');

        // Promo pengguna baru hanya untuk pesanan BisaBersih PERTAMA. Ditentukan
        // dari data, bukan dari klaim klien.
        $pertamaKali = ! Task::where('customer_id', $user->id)
            ->when($kategoriId, fn ($q) => $q->where('category_id', $kategoriId))
            ->exists();

        // Cleaner yang dipilih menentukan tarifnya. Tanpa pilihan, pesanan
        // dihargai pada level terendah: pengguna tidak boleh ditagih tarif
        // cleaner senior untuk penugasan yang belum tentu jatuh ke mereka.
        $profil = ! empty($data['cleaner_id'])
            ? MitraProfile::where('user_id', $data['cleaner_id'])->first()
            : null;
        $level = $profil ? LevelCleaner::levelMitra($profil) : LevelCleaner::LEVEL_TERENDAH;

        $rincian = $this->tarif->hitung(
            $level,
            $data['kondisi'] ?? 'normal',
            (int) $data['durasi_jam'],
            (int) $data['jumlah_cleaner'],
            $data['add_on'] ?? [],
            $data['frekuensi'],
            $pertamaKali,
            $data['promo_kode'] ?? null,
        );

        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);
        $jadwal = $this->parseJadwal($data['tanggal'], $data['waktu']);

        $namaLevel = LevelCleaner::namaLevel($level);

        $task = DB::transaction(function () use (
            $user, $data, $rincian, $kategoriId, $addressId, $alamat, $lat, $lng,
            $jadwal, $namaLevel, $level, $profil
        ) {
            $task = Task::create([
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => $kategoriId,
                'address_id' => $addressId,
                'tipe' => 'fixed',
                'judul' => 'BisaBersih — Bersih Rumah',
                'mitra_id' => $profil?->user_id,
                'deskripsi' => $this->ringkasan($data),
                'status' => 'pending',
                'fulfillment_status' => 'diproses',
                'lokasi_alamat' => $alamat,
                'lokasi_lat' => $lat,
                'lokasi_lng' => $lng,
                'harga' => $rincian['total'],
                'catatan' => $data['catatan'] ?? null,
                'jumlah_helper' => (int) $data['jumlah_cleaner'],
                'dijadwalkan_pada' => $jadwal,
                'nama_penerima' => $data['nama_penerima'] ?? null,
                'telepon_penerima' => $data['telepon_penerima'] ?? null,
                'detail_layanan' => [
                    'kondisi' => $data['kondisi'] ?? 'normal',
                    'cleaner_id' => $profil?->user_id,
                    'level_cleaner' => $level,
                    'nama_level' => $namaLevel,
                    'durasi_jam' => (int) $data['durasi_jam'],
                    'jumlah_cleaner' => (int) $data['jumlah_cleaner'],
                    'frekuensi' => $data['frekuensi'],
                    'tipe_properti' => $data['tipe_properti'] ?? null,
                    'kamar_tidur' => isset($data['kamar_tidur']) ? (int) $data['kamar_tidur'] : null,
                    'kamar_mandi' => isset($data['kamar_mandi']) ? (int) $data['kamar_mandi'] : null,
                    'luas_m2' => isset($data['luas_m2']) ? (int) $data['luas_m2'] : null,
                    'ada_hewan' => (bool) ($data['ada_hewan'] ?? false),
                    'area' => array_values($data['area']),
                    'akses_masuk' => $data['akses_masuk'] ?? null,
                    'add_on' => array_column($rincian['baris_add_on'], 'id'),
                ],
            ]);

            // Satu baris untuk layanan utama, satu baris tiap add-on.
            $baris = [[
                'nama' => "Bersih Rumah · {$data['durasi_jam']} jam × {$data['jumlah_cleaner']} cleaner ({$namaLevel})",
                'kategori' => 'layanan',
                'satuan' => 'paket',
                'harga_satuan' => $rincian['layanan'],
                'qty' => 1,
                'subtotal' => $rincian['layanan'],
            ]];
            foreach ($rincian['baris_add_on'] as $a) {
                $baris[] = [
                    'nama' => $a['label'],
                    'kategori' => 'add-on',
                    'satuan' => 'paket',
                    'harga_satuan' => $a['harga'],
                    'qty' => 1,
                    'subtotal' => $a['harga'],
                ];
            }
            $task->items()->createMany($baris);

            $task->payment()->create([
                'jumlah' => $rincian['total'],
                'subtotal_barang' => $rincian['layanan'] + $rincian['add_on'],
                // Biaya perjalanan diperlakukan sebagai ongkir supaya
                // subtotal + ongkir − potongan = jumlah, sama seperti BisaBelanja.
                'ongkir' => $rincian['perjalanan'],
                'ongkir_normal' => $rincian['perjalanan'],
                'potongan' => $rincian['diskon_frekuensi'] + $rincian['potongan_promo'],
                'cashback' => $rincian['cashback'],
                'service_fee' => 0,
                // Markup adalah pendapatan platform yang sebenarnya; sisanya
                // upah cleaner yang akan diteruskan saat pesanan selesai.
                'komisi_platform' => $rincian['markup_platform'],
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

    /** Ringkasan yang bisa dibaca mitra tanpa membuka JSON. */
    private function ringkasan(array $d): string
    {
        $bagian = array_filter([
            $d['tipe_properti'] ?? null,
            isset($d['kamar_tidur']) ? "{$d['kamar_tidur']} kamar tidur" : null,
            isset($d['kamar_mandi']) ? "{$d['kamar_mandi']} kamar mandi" : null,
            isset($d['luas_m2']) ? "{$d['luas_m2']} m²" : null,
            'area: '.implode(', ', $d['area']),
            isset($d['akses_masuk']) ? 'akses: '.$d['akses_masuk'] : null,
            ! empty($d['ada_hewan']) ? 'ada hewan peliharaan' : null,
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
