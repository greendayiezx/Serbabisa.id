<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Task;
use App\Services\KirimTarif;
use App\Services\NomorInvoice;
use App\Services\PromoKirim;
use App\Services\RuteJalan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * BisaKirim — mengantar paket.
 *
 * Tiga penolakan yang dijaga DI SERVER, bukan hanya di layar:
 *
 * 1. Isi kiriman yang dilarang. Uang tunai, barang mudah meledak, cairan mudah
 *    terbakar, hewan hidup, dan barang terlarang tidak punya cara pengantaran
 *    yang aman lewat kurir motor — dan tidak ada ganti rugi yang sepadan.
 * 2. Paket yang tidak muat kendaraannya. Menerima 60 kg untuk motor berarti
 *    kurir datang lalu menolak di lokasi: waktu pengirim habis, kurir rugi
 *    jalan, dan pesanannya batal juga pada akhirnya.
 * 3. Nilai barang di atas plafon proteksi. Lebih baik dikatakan sekarang
 *    daripada baru ketahuan saat mengajukan klaim.
 */
class KirimController extends Controller
{
    public function __construct(
        private readonly KirimTarif $tarif,
        private readonly PromoKirim $promo,
        private readonly RuteJalan $rute,
        private readonly NomorInvoice $nomorInvoice,
    ) {}

    /**
     * Jarak yang menagih SEKALIGUS garis yang digambar.
     *
     * @return array{km:float, geometri:list<array{0:float,1:float}>|null, lewat_jalan:bool}
     */
    private function jarak(array $d): array
    {
        $rute = $this->rute->cari(
            (float) $d['ambil_lat'], (float) $d['ambil_lng'],
            (float) $d['antar_lat'], (float) $d['antar_lng'],
        );

        if ($rute) {
            return ['km' => $rute['km'], 'geometri' => $rute['geometri'], 'lewat_jalan' => true];
        }

        return [
            'km' => $this->tarif->jarakKm(
                (float) $d['ambil_lat'], (float) $d['ambil_lng'],
                (float) $d['antar_lat'], (float) $d['antar_lng'],
            ),
            'geometri' => null,
            'lewat_jalan' => false,
        ];
    }

    /** Perkiraan ongkir semua kendaraan untuk satu rute. */
    public function estimasi(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'ambil_lat' => ['required', 'numeric', 'between:-90,90'],
            'ambil_lng' => ['required', 'numeric', 'between:-180,180'],
            'antar_lat' => ['required', 'numeric', 'between:-90,90'],
            'antar_lng' => ['required', 'numeric', 'between:-180,180'],
            'ukuran' => ['required', Rule::in(KirimTarif::idUkuran())],
            'nilai_barang' => ['nullable', 'integer', 'min:0', 'max:100000000'],
        ]);

        ['km' => $km, 'geometri' => $geometri, 'lewat_jalan' => $lewatJalan] = $this->jarak($data);

        if ($km <= 0.0) {
            throw ValidationException::withMessages([
                'antar_lat' => 'Titik ambil dan titik antarnya sama. Pilih tujuan yang berbeda.',
            ]);
        }
        if ($km > KirimTarif::JARAK_MAKS_KM) {
            throw ValidationException::withMessages([
                'antar_lat' => 'Jaraknya di atas '.(int) KirimTarif::JARAK_MAKS_KM.' km. '.
                    'Kiriman sejauh itu belum bisa dipesan lewat aplikasi.',
            ]);
        }

        $nilai = (int) ($data['nilai_barang'] ?? 0);
        $pertama = $this->kirimanPertama($user->id);

        $pilihan = array_map(function (array $p) use ($pertama) {
            $promo = $this->promo->tersedia($p['ongkir'], $p['komisi'], $pertama);
            $terbaik = collect($promo)->where('bisa_dipakai', true)->sortByDesc('potongan')->first();

            return [
                ...$p,
                'promo' => $promo,
                'promo_terbaik' => $terbaik,
                'total_setelah_promo' => $p['total'] - (int) ($terbaik['potongan'] ?? 0),
            ];
        }, $this->tarif->semuaKendaraan($km, $data['ukuran'], $nilai));

        return response()->json([
            'km' => $km,
            'geometri' => $geometri,
            'lewat_jalan' => $lewatJalan,
            'kiriman_pertama' => $pertama,
            'proteksi_plafon' => KirimTarif::PROTEKSI_PLAFON,
            'pilihan' => $pilihan,
        ]);
    }

    /**
     * Katalog voucher, tanpa angka potongan.
     *
     * Dipakai layar beranda, di mana rute dan ongkirnya belum ada — dan tanpa
     * ongkir, potongannya memang belum bisa dihitung. Yang dikirim di sini
     * SYARATNYA saja; angka rupiahnya baru muncul di layar detail, tempat
     * ongkirnya sudah diketahui.
     *
     * Katalognya tetap satu di server. Menyalinnya ke klien hanya untuk layar
     * ini berarti dua daftar yang akan mulai berbeda pada perubahan pertama.
     */
    public function voucher(Request $request): JsonResponse
    {
        $pertama = $this->kirimanPertama($request->user()->id);

        $daftar = collect(PromoKirim::KATALOG)
            ->map(fn ($p) => [
                'kode' => $p['kode'],
                'nama' => $p['nama'],
                'deskripsi' => $p['deskripsi'],
                'minimum' => $p['minimum'],
                'jenis' => $p['jenis'],
                // Voucher sekali pakai yang sudah terpakai tetap ditampilkan,
                // tapi ditandai — menghilangkannya membuat orang mengira
                // vouchernya tidak pernah ada.
                'terpakai' => ($p['sekali_seumur_hidup'] ?? false) && ! $pertama,
            ])
            ->values();

        return response()->json([
            'kiriman_pertama' => $pertama,
            'jumlah' => $daftar->where('terpakai', false)->count(),
            'voucher' => $daftar,
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'kendaraan' => ['required', Rule::in(KirimTarif::idKendaraan())],
            'ukuran' => ['required', Rule::in(KirimTarif::idUkuran())],
            'isi' => ['required', 'string', 'max:120'],

            /*
             * Pernyataan isi kiriman. Dikirim sebagai daftar centang, dan
             * satu pun yang tercentang membatalkan pesanan — bukan menaikkan
             * harganya.
             */
            'dilarang' => ['nullable', 'array'],
            'dilarang.*' => [Rule::in(KirimTarif::DILARANG)],

            'nilai_barang' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'pakai_kode_terima' => ['nullable', 'boolean'],

            'ambil_alamat' => ['required', 'string', 'max:255'],
            'ambil_lat' => ['required', 'numeric', 'between:-90,90'],
            'ambil_lng' => ['required', 'numeric', 'between:-180,180'],
            'ambil_nama' => ['required', 'string', 'max:100'],
            'ambil_telepon' => ['required', 'string', 'max:30'],
            'ambil_catatan' => ['nullable', 'string', 'max:255'],

            'antar_alamat' => ['required', 'string', 'max:255'],
            'antar_lat' => ['required', 'numeric', 'between:-90,90'],
            'antar_lng' => ['required', 'numeric', 'between:-180,180'],
            'antar_nama' => ['required', 'string', 'max:100'],
            'antar_telepon' => ['required', 'string', 'max:30'],
            'antar_catatan' => ['nullable', 'string', 'max:255'],

            'metode' => ['required', 'string', 'max:30'],
            'kode_promo' => ['nullable', 'string', 'max:30'],
        ]);

        $dilarang = $data['dilarang'] ?? [];
        if ($dilarang) {
            throw ValidationException::withMessages([
                'dilarang' => 'Kiriman berisi barang yang belum bisa kami antar: '.
                    implode(', ', array_map(fn ($d) => str_replace('-', ' ', $d), $dilarang)).
                    '. Kurir tidak punya cara membawanya dengan aman, dan proteksi paket tidak '.
                    'menanggungnya.',
            ]);
        }

        if ($alasan = $this->tarif->tidakSanggup($data['kendaraan'], $data['ukuran'])) {
            throw ValidationException::withMessages(['kendaraan' => $alasan]);
        }

        $nilai = (int) ($data['nilai_barang'] ?? 0);
        if ($nilai > KirimTarif::PROTEKSI_PLAFON) {
            throw ValidationException::withMessages([
                'nilai_barang' => 'Proteksi paket hanya sampai Rp'.
                    number_format(KirimTarif::PROTEKSI_PLAFON, 0, ',', '.').
                    '. Untuk barang di atas itu, sebaiknya pakai jasa kirim berasuransi khusus.',
            ]);
        }

        ['km' => $km, 'geometri' => $geometri, 'lewat_jalan' => $lewatJalan] = $this->jarak($data);

        if ($km <= 0.0 || $km > KirimTarif::JARAK_MAKS_KM) {
            throw ValidationException::withMessages([
                'antar_lat' => 'Rute itu tidak bisa dipesan. Periksa titik ambil dan tujuannya.',
            ]);
        }

        $rincian = $this->tarif->hitung($data['kendaraan'], $km, $data['ukuran'], $nilai);

        /* ---------- Voucher diperiksa ulang di sini ---------- */
        $potongan = 0;
        $promoDipakai = null;

        if (! empty($data['kode_promo'])) {
            $promo = $this->promo->cari($data['kode_promo']);
            if (! $promo) {
                throw ValidationException::withMessages(['kode_promo' => 'Kode voucher tidak dikenal.']);
            }

            $alasanPromo = $this->promo->kenapaTidakBisa(
                $promo, $rincian['ongkir'], $this->kirimanPertama($user->id),
            );
            if ($alasanPromo) {
                throw ValidationException::withMessages(['kode_promo' => $alasanPromo]);
            }

            $potongan = $this->promo->potongan($promo, $rincian['ongkir'], $rincian['komisi']);
            $promoDipakai = ['kode' => $promo['kode'], 'nama' => $promo['nama'], 'potongan' => $potongan];
        }

        $total = max(0, $rincian['total'] - $potongan);

        /*
         * Kode terima paket: dibuat server, bukan klien. Kode yang dibuat di
         * peramban bisa dibaca siapa pun yang membuka layarnya, dan gunanya
         * justru supaya hanya penerima yang tahu.
         */
        $kodeTerima = ! empty($data['pakai_kode_terima'])
            ? str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT)
            : null;

        $task = DB::transaction(function () use (
            $user, $data, $rincian, $km, $geometri, $lewatJalan, $total, $potongan, $promoDipakai, $nilai, $kodeTerima
        ) {
            $task = Task::create([
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => Category::where('slug', 'bisakirim')->value('id'),
                'tipe' => 'fixed',
                'judul' => 'BisaKirim — '.$rincian['label'],
                'deskripsi' => $data['ambil_alamat'].' → '.$data['antar_alamat'],
                'status' => 'pending',
                'fulfillment_status' => 'diproses',

                // Lokasi tugas = titik AMBIL: itu yang didatangi kurir lebih dulu.
                'lokasi_alamat' => $data['ambil_alamat'],
                'lokasi_lat' => $data['ambil_lat'],
                'lokasi_lng' => $data['ambil_lng'],

                'harga' => $total,
                'catatan' => $data['ambil_catatan'] ?? null,
                'kendaraan' => $rincian['label'],
                'nama_penerima' => $data['antar_nama'],
                'telepon_penerima' => $data['antar_telepon'],
                'detail_layanan' => [
                    'layanan' => 'kirim',
                    'kendaraan' => $rincian['kendaraan'],
                    'label' => $rincian['label'],
                    'ukuran' => $data['ukuran'],
                    'isi' => $data['isi'],
                    'km' => $km,
                    'geometri' => $geometri,
                    'lewat_jalan' => $lewatJalan,

                    'ambil' => [
                        'alamat' => $data['ambil_alamat'],
                        'lat' => (float) $data['ambil_lat'],
                        'lng' => (float) $data['ambil_lng'],
                        'nama' => $data['ambil_nama'],
                        'telepon' => $data['ambil_telepon'],
                        'catatan' => $data['ambil_catatan'] ?? null,
                    ],
                    'antar' => [
                        'alamat' => $data['antar_alamat'],
                        'lat' => (float) $data['antar_lat'],
                        'lng' => (float) $data['antar_lng'],
                        'nama' => $data['antar_nama'],
                        'telepon' => $data['antar_telepon'],
                        'catatan' => $data['antar_catatan'] ?? null,
                    ],

                    'baris' => $rincian['baris'],
                    'ongkir' => $rincian['ongkir'],
                    'nilai_barang' => $nilai,
                    'premi_proteksi' => $rincian['premi'],
                    'proteksi_plafon' => $nilai > 0 ? min($nilai, KirimTarif::PROTEKSI_PLAFON) : 0,
                    'potongan' => $potongan,
                    'promo' => $promoDipakai,
                    'metode' => $data['metode'],
                    'kode_terima' => $kodeTerima,

                    'tahap' => 'mencari',
                    'kurir' => null,

                    'area' => ['Antar paket'],
                    'jumlah_cleaner' => 1,
                ],
            ]);

            $task->payment()->create([
                'jumlah' => $total,
                'subtotal_barang' => 0,
                'ongkir' => $rincian['ongkir'],
                'ongkir_normal' => $rincian['ongkir'],
                'potongan' => $potongan,
                'cashback' => 0,
                // Premi proteksi bukan jasa antar; ditaruh terpisah supaya nota
                // bisa direkonstruksi dan preminya tidak terbaca sebagai margin.
                'service_fee' => $rincian['premi'],
                'komisi_platform' => $rincian['ongkir'] - $potongan - $this->tarif->biaya($rincian['ongkir']),
                'status' => 'pending',
                'metode' => $data['metode'],
            ]);

            return $task;
        });

        return response()->json([
            ...$task->load('payment')->toArray(),
            'rincian' => [...$rincian, 'potongan' => $potongan, 'total' => $total],
            'kode_terima' => $kodeTerima,
        ], 201);
    }

    public function show(Request $request, string $nomor): JsonResponse
    {
        $task = Task::where('nomor_invoice', strtoupper(trim($nomor)))
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();

        $d = $task->detail_layanan ?? [];
        if (($d['layanan'] ?? null) !== 'kirim') {
            abort(404);
        }

        return response()->json([
            'id' => $task->id,
            'nomor' => $task->nomor_invoice,
            'tahap' => $d['tahap'] ?? 'mencari',
            'label' => $d['label'] ?? null,
            'ukuran' => $d['ukuran'] ?? null,
            'isi' => $d['isi'] ?? null,
            'km' => $d['km'] ?? null,
            'geometri' => $d['geometri'] ?? null,
            'ambil' => $d['ambil'] ?? null,
            'antar' => $d['antar'] ?? null,
            'baris' => $d['baris'] ?? [],
            'ongkir' => $d['ongkir'] ?? 0,
            'premi_proteksi' => $d['premi_proteksi'] ?? 0,
            'proteksi_plafon' => $d['proteksi_plafon'] ?? 0,
            'potongan' => $d['potongan'] ?? 0,
            'total' => (float) $task->harga,
            'promo' => $d['promo'] ?? null,
            'metode' => $d['metode'] ?? null,
            'kode_terima' => $d['kode_terima'] ?? null,
            'kurir' => $d['kurir'] ?? null,
        ]);
    }

    private function kirimanPertama(int $userId): bool
    {
        return ! Task::where('customer_id', $userId)
            ->where('detail_layanan->layanan', 'kirim')
            ->exists();
    }
}
