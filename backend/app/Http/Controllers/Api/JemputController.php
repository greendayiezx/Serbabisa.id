<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Task;
use App\Services\JemputTarif;
use App\Services\NomorInvoice;
use App\Services\PermintaanJemput;
use App\Services\PromoJemput;
use App\Services\RuteJalan;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * BisaJemput — mengantar orang.
 *
 * Dua hal yang dijaga di sini, dan keduanya tidak boleh hanya hidup di layar:
 *
 * 1. TITIK JEMPUT HARUS DIKONFIRMASI. Bukan basa-basi UX: titik GPS bisa
 *    meleset puluhan meter, dan di gang atau gedung bertingkat selisih itu
 *    berarti pengemudi menunggu di tempat yang salah lalu perjalanan dibatalkan.
 *    Server menolak pesanan yang tidak menyertakan konfirmasinya.
 * 2. JARAK DIHITUNG DARI KOORDINAT, bukan dari angka kiriman klien. Tarifnya
 *    ikut jarak, jadi membiarkan klien mengirim jaraknya sendiri sama saja
 *    membiarkan penumpang mengetik harganya sendiri.
 */
class JemputController extends Controller
{
    public function __construct(
        private readonly JemputTarif $tarif,
        private readonly PromoJemput $promo,
        private readonly PermintaanJemput $permintaan,
        private readonly RuteJalan $rute,
        private readonly NomorInvoice $nomorInvoice,
    ) {}

    /**
     * Jarak yang dipakai MENAGIH, sekaligus garis yang digambar di peta.
     *
     * Satu sumber untuk keduanya. Kalau peta digambar dari rute jalan
     * sementara tagihan dihitung dari garis lurus, peta akan berkata 9 km
     * sambil nota berkata 7 km — dan yang dianggap salah selalu notanya.
     *
     * @return array{km:float, geometri:list<array{0:float,1:float}>|null, lewat_jalan:bool}
     */
    private function jarak(array $data, string $awalan = 'jemput'): array
    {
        $lat1 = (float) $data["{$awalan}_lat"];
        $lng1 = (float) $data["{$awalan}_lng"];
        $lat2 = (float) $data['tujuan_lat'];
        $lng2 = (float) $data['tujuan_lng'];

        $rute = $this->rute->cari($lat1, $lng1, $lat2, $lng2);

        if ($rute) {
            return ['km' => $rute['km'], 'geometri' => $rute['geometri'], 'lewat_jalan' => true];
        }

        // Layanan rute tidak terjangkau: perjalanan tetap bisa dipesan dengan
        // perkiraan garis lurus, dan layar menyebutkan bahwa itu perkiraan.
        return [
            'km' => $this->tarif->jarakKm($lat1, $lng1, $lat2, $lng2),
            'geometri' => null,
            'lewat_jalan' => false,
        ];
    }

    /**
     * Perkiraan tarif semua pilihan untuk satu rute.
     *
     * Tidak membuat apa pun — hanya menjawab "berapa". Dipanggil ulang tiap
     * kali titik jemput atau tujuan berubah.
     */
    public function estimasi(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'jemput_lat' => ['required', 'numeric', 'between:-90,90'],
            'jemput_lng' => ['required', 'numeric', 'between:-180,180'],
            'tujuan_lat' => ['required', 'numeric', 'between:-90,90'],
            'tujuan_lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        ['km' => $km, 'geometri' => $geometri, 'lewat_jalan' => $lewatJalan] = $this->jarak($data);

        if ($km <= 0.0) {
            throw ValidationException::withMessages([
                'tujuan_lat' => 'Titik jemput dan tujuannya sama. Pilih tujuan yang berbeda.',
            ]);
        }

        if ($km > JemputTarif::JARAK_MAKS_KM) {
            throw ValidationException::withMessages([
                'tujuan_lat' => 'Jaraknya di atas '.(int) JemputTarif::JARAK_MAKS_KM.' km. '.
                    'Perjalanan sejauh itu belum bisa dipesan lewat aplikasi.',
            ]);
        }

        $saat = now();

        /*
         * Pengali tarif datang dari permintaan SUNGGUHAN di sekitar titik
         * jemput, bukan dari jendela jam yang menebak kapan ramai.
         */
        $ramai = $this->permintaan->ukur((float) $data['jemput_lat'], (float) $data['jemput_lng']);
        $pilihan = $this->tarif->semuaPilihan($km, $ramai);
        $pertama = $this->perjalananPertama($user->id);

        // Promo dilekatkan ke tiap pilihan, bukan dihitung sekali di ujung:
        // batas potongan mengikuti komisi, dan komisi berbeda tiap tarif.
        $pilihan = array_map(function (array $p) use ($pertama, $saat) {
            $promo = $this->promo->tersedia($p['tarif'], $p['komisi'], $pertama, $saat);
            $terbaik = collect($promo)->where('bisa_dipakai', true)->sortByDesc('potongan')->first();

            return [
                ...$p,
                'promo' => $promo,
                'promo_terbaik' => $terbaik,
                'tarif_setelah_promo' => $p['tarif'] - (int) ($terbaik['potongan'] ?? 0),
            ];
        }, $pilihan);

        return response()->json([
            'km' => $km,
            'geometri' => $geometri,
            'lewat_jalan' => $lewatJalan,
            'sibuk' => $pilihan[0]['sibuk'] ?? null,
            'sibuk_alasan' => $pilihan[0]['sibuk_alasan'] ?? null,
            'sibuk_pengali' => $pilihan[0]['sibuk_pengali'] ?? 1.0,
            'perjalanan_pertama' => $pertama,
            'pilihan' => $pilihan,
        ]);
    }

    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'tipe' => ['required', Rule::in(JemputTarif::idTipe())],
            'varian' => ['required', Rule::in(JemputTarif::idVarian())],

            /*
             * Bukan kolom formalitas. Selama titik jemputnya belum benar-benar
             * dikonfirmasi penumpang, pesanan ini tidak boleh terbentuk —
             * pengemudi akan berangkat ke koordinat yang belum tentu benar.
             */
            'titik_jemput_dikonfirmasi' => ['required', 'accepted'],

            'jemput_alamat' => ['required', 'string', 'max:255'],
            'jemput_lat' => ['required', 'numeric', 'between:-90,90'],
            'jemput_lng' => ['required', 'numeric', 'between:-180,180'],
            'jemput_catatan' => ['nullable', 'string', 'max:255'],

            'tujuan_alamat' => ['required', 'string', 'max:255'],
            'tujuan_lat' => ['required', 'numeric', 'between:-90,90'],
            'tujuan_lng' => ['required', 'numeric', 'between:-180,180'],

            'penumpang' => ['required', 'integer', 'min:1', 'max:6'],
            'metode' => ['required', Rule::in(JemputTarif::METODE_BAYAR)],
            'kode_promo' => ['nullable', 'string', 'max:30'],

            // Dipesan untuk orang lain: yang naik bukan pemilik akun, jadi
            // pengemudi butuh nama dan nomor yang bisa dihubungi di lokasi.
            'untuk_orang_lain' => ['nullable', 'boolean'],
            'nama_penumpang' => ['required_if:untuk_orang_lain,true', 'nullable', 'string', 'max:100'],
            'telepon_penumpang' => ['required_if:untuk_orang_lain,true', 'nullable', 'string', 'max:30'],

            'dijadwalkan' => ['nullable', 'boolean'],
            'jadwal_pada' => ['required_if:dijadwalkan,true', 'nullable', 'date', 'after:now'],
            'catatan' => ['nullable', 'string', 'max:500'],
        ]);

        if (! isset(JemputTarif::TIPE[$data['tipe']]['varian'][$data['varian']])) {
            throw ValidationException::withMessages([
                'varian' => 'Pilihan itu tidak tersedia untuk kendaraan tersebut.',
            ]);
        }

        $kapasitas = JemputTarif::TIPE[$data['tipe']]['kapasitas'];
        if ((int) $data['penumpang'] > $kapasitas) {
            throw ValidationException::withMessages([
                'penumpang' => JemputTarif::TIPE[$data['tipe']]['label'].' hanya muat '.$kapasitas.
                    ' penumpang. Pilih kendaraan yang lebih besar.',
            ]);
        }

        // Dihitung ulang di sini, tidak memakai angka dari langkah estimasi:
        // hasilnya sudah tersimpan sebentar di cache, jadi murah, dan yang
        // menagih tetap perhitungan server sendiri.
        ['km' => $km, 'geometri' => $geometri, 'lewat_jalan' => $lewatJalan] = $this->jarak($data);

        if ($km <= 0.0 || $km > JemputTarif::JARAK_MAKS_KM) {
            throw ValidationException::withMessages([
                'tujuan_lat' => 'Rute itu tidak bisa dipesan. Periksa titik jemput dan tujuannya.',
            ]);
        }

        $saat = now();
        $ramai = $this->permintaan->ukur((float) $data['jemput_lat'], (float) $data['jemput_lng']);
        $rincian = $this->tarif->hitung($data['tipe'], $data['varian'], $km, $ramai);

        /* ---------- Promo: diperiksa ulang di sini, bukan dipercaya dari klien ---------- */
        $potongan = 0;
        $promoDipakai = null;

        if (! empty($data['kode_promo'])) {
            $promo = $this->promo->cari($data['kode_promo']);
            if (! $promo) {
                throw ValidationException::withMessages(['kode_promo' => 'Kode promo tidak dikenal.']);
            }

            $alasan = $this->promo->kenapaTidakBisa(
                $promo, $rincian['tarif'], $this->perjalananPertama($user->id), $saat,
            );
            if ($alasan) {
                throw ValidationException::withMessages(['kode_promo' => $alasan]);
            }

            $potongan = $this->promo->potongan($promo, $rincian['tarif'], $rincian['komisi']);
            $promoDipakai = ['kode' => $promo['kode'], 'nama' => $promo['nama'], 'potongan' => $potongan];
        }

        $total = max(0, $rincian['tarif'] - $potongan);

        $jadwal = ! empty($data['dijadwalkan']) && ! empty($data['jadwal_pada'])
            ? Carbon::parse($data['jadwal_pada'])
            : null;

        $task = DB::transaction(function () use ($user, $data, $rincian, $km, $geometri, $lewatJalan, $total, $potongan, $promoDipakai, $jadwal) {
            $task = Task::create([
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => Category::where('slug', 'bisajemput')->value('id'),
                'tipe' => 'fixed',
                'judul' => 'BisaJemput — '.$rincian['label'],
                'deskripsi' => $data['jemput_alamat'].' → '.$data['tujuan_alamat'],
                'status' => 'pending',
                'fulfillment_status' => 'diproses',

                // Lokasi tugas adalah titik JEMPUT: itu yang harus didatangi
                // pengemudi, dan itu yang dicari orang saat membuka pesanannya.
                'lokasi_alamat' => $data['jemput_alamat'],
                'lokasi_lat' => $data['jemput_lat'],
                'lokasi_lng' => $data['jemput_lng'],

                'harga' => $total,
                'catatan' => $data['catatan'] ?? null,
                'kendaraan' => $rincian['label'],
                'dijadwalkan_pada' => $jadwal,
                'nama_penerima' => $data['nama_penumpang'] ?? $user->name,
                'telepon_penerima' => $data['telepon_penumpang'] ?? $user->phone,
                'detail_layanan' => [
                    'layanan' => 'jemput',
                    'tipe' => $rincian['tipe'],
                    'varian' => $rincian['varian'],
                    'kelas' => $rincian['kelas'],
                    'label' => $rincian['label'],
                    'km' => $km,
                    'menit' => $rincian['menit'],
                    // Rute yang dipakai menghitung jaraknya, disimpan supaya
                    // layar perjalanan menggambar garis yang sama dengan yang
                    // ditagih — bukan menggambar ulang dengan cara lain.
                    'geometri' => $geometri,
                    'lewat_jalan' => $lewatJalan,
                    'penumpang' => (int) $data['penumpang'],
                    'untuk_orang_lain' => (bool) ($data['untuk_orang_lain'] ?? false),

                    'jemput' => [
                        'alamat' => $data['jemput_alamat'],
                        'lat' => (float) $data['jemput_lat'],
                        'lng' => (float) $data['jemput_lng'],
                        'catatan' => $data['jemput_catatan'] ?? null,
                        'dikonfirmasi' => true,
                    ],
                    'tujuan' => [
                        'alamat' => $data['tujuan_alamat'],
                        'lat' => (float) $data['tujuan_lat'],
                        'lng' => (float) $data['tujuan_lng'],
                    ],

                    'baris' => $rincian['baris'],
                    'tarif' => $rincian['tarif'],
                    'potongan' => $potongan,
                    'promo' => $promoDipakai,
                    'sibuk' => $rincian['sibuk'],
                    'sibuk_alasan' => $rincian['sibuk_alasan'],
                    'metode' => $data['metode'],
                    'dijadwalkan' => (bool) ($data['dijadwalkan'] ?? false),

                    /*
                     * Tahap perjalanan. Diisi sistem/pengemudi, bukan penumpang.
                     * Selama 'mencari', belum ada pengemudi yang boleh
                     * ditampilkan — kartu pengemudi kosong lebih jujur daripada
                     * kartu berisi nama yang belum tentu jadi menjemput.
                     */
                    'tahap' => 'mencari',
                    'pengemudi' => null,
                    'penilaian' => null,

                    'area' => ['Antar penumpang'],
                    'jumlah_cleaner' => 1,
                ],
            ]);

            $task->payment()->create([
                'jumlah' => $total,
                'subtotal_barang' => $rincian['tarif'],
                'ongkir' => 0,
                'ongkir_normal' => 0,
                'potongan' => $potongan,
                'cashback' => 0,
                'service_fee' => 0,
                'komisi_platform' => $total - $this->tarif->biaya($rincian['tarif']),
                'status' => 'pending',
                'metode' => $data['metode'],
            ]);

            return $task;
        });

        return response()->json([
            ...$task->load('payment')->toArray(),
            'rincian' => [...$rincian, 'potongan' => $potongan, 'total' => $total],
        ], 201);
    }

    /** Status perjalanan; dipanggil berulang oleh layar pelacakan. */
    public function show(Request $request, string $nomor): JsonResponse
    {
        $task = $this->milikSaya($request, $nomor);
        $d = $task->detail_layanan;
        $d['pengemudi'] = $this->lengkapiRutePengemudi($d);

        return response()->json([
            'id' => $task->id,
            'nomor' => $task->nomor_invoice,
            'tahap' => $d['tahap'] ?? 'mencari',
            'label' => $d['label'] ?? null,
            // Teks lencana varian (CEPAT/HEMAT/COMFORT/…) tidak ikut disimpan
            // saat checkout; dihitung ulang dari tipe+varian yang tersimpan.
            // Dengan begitu perjalanan lama pun tetap punya lencananya tanpa
            // menambah kolom yang perlu diisi surut.
            'label_varian' => JemputTarif::TIPE[$d['tipe'] ?? '']['varian'][$d['varian'] ?? '']['label'] ?? null,
            'kelas' => $d['kelas'] ?? null,
            'km' => $d['km'] ?? null,
            'menit' => $d['menit'] ?? null,
            'geometri' => $d['geometri'] ?? null,
            'lewat_jalan' => $d['lewat_jalan'] ?? false,
            'penumpang' => $d['penumpang'] ?? 1,
            'untuk_orang_lain' => $d['untuk_orang_lain'] ?? false,
            'nama_penumpang' => $task->nama_penerima,
            'telepon_penumpang' => $task->telepon_penerima,
            'jemput' => $d['jemput'] ?? null,
            'tujuan' => $d['tujuan'] ?? null,
            'baris' => $d['baris'] ?? [],
            'tarif' => $d['tarif'] ?? 0,
            'potongan' => $d['potongan'] ?? 0,
            'total' => (float) $task->harga,
            // Tip yang sudah diberikan, dari kedua pintunya: selama perjalanan
            // dan saat menilai. Keduanya menambah tagihan yang sama, jadi layar
            // menerimanya sebagai satu angka — dan menuliskannya sebagai baris
            // sendiri, bukan melebur ke tarif yang tidak berubah.
            'tip' => (int) ($d['tip'] ?? 0) + (int) ($d['penilaian']['tip'] ?? 0),
            'promo' => $d['promo'] ?? null,
            'metode' => $d['metode'] ?? null,
            'sibuk' => $d['sibuk'] ?? null,
            'sibuk_alasan' => $d['sibuk_alasan'] ?? null,
            'dijadwalkan_pada' => $task->dijadwalkan_pada?->toIso8601String(),
            'pengemudi' => $d['pengemudi'] ?? null,
            'penilaian' => $d['penilaian'] ?? null,
        ]);
    }

    /**
     * Batalkan perjalanan.
     *
     * Setelah pengemudi berangkat menjemput, pembatalan tetap diizinkan — yang
     * tidak boleh adalah membatalkan diam-diam tanpa penumpang tahu bahwa
     * pengemudi sudah terlanjur jalan. Karena itu jawabannya menyebutkan
     * keadaannya, bukan cuma "berhasil".
     */
    public function batal(Request $request, string $nomor): JsonResponse
    {
        $task = $this->milikSaya($request, $nomor);
        $d = $task->detail_layanan;
        $tahap = $d['tahap'] ?? 'mencari';

        if (in_array($tahap, ['jalan', 'selesai'], true)) {
            throw ValidationException::withMessages([
                'tahap' => $tahap === 'jalan'
                    ? 'Perjalanan sedang berlangsung dan tidak bisa dibatalkan dari sini.'
                    : 'Perjalanan sudah selesai.',
            ]);
        }

        $task->update([
            'detail_layanan' => [...$d, 'tahap' => 'batal'],
            'status' => 'cancelled',
            'cancelled_at' => now(),
        ]);

        return response()->json([
            'tahap' => 'batal',
            'pengemudi_sudah_jalan' => $tahap !== 'mencari',
        ]);
    }

    /**
     * Katalog promo BisaJemput, tanpa angka potongan.
     *
     * Dipakai halaman promo yang dibuka dari mana saja — termasuk dari layar
     * perjalanan yang sedang berlangsung, tempat tidak ada lagi tarif yang bisa
     * dijadikan dasar hitungan. Potongan rupiahnya baru muncul di layar
     * pemesanan, tempat tarifnya sudah diketahui; menampilkan angka di sini
     * berarti menjanjikan potongan yang belum tentu berlaku untuk perjalanan
     * berikutnya.
     */
    public function voucher(Request $request): JsonResponse
    {
        $pertama = $this->perjalananPertama($request->user()->id);

        $daftar = array_map(fn (array $p) => [
            'kode' => $p['kode'],
            'nama' => $p['nama'],
            'deskripsi' => $p['deskripsi'],
            'jenis' => $p['jenis'],
            'minimum' => $p['minimum'],
            // Promo sekali seumur hidup yang sudah lewat masanya tetap
            // ditampilkan, dengan keterangannya — menyembunyikannya membuat
            // orang mengira promonya tidak pernah ada.
            'terpakai' => ! empty($p['sekali_seumur_hidup']) && ! $pertama,
        ], PromoJemput::KATALOG);

        return response()->json([
            'perjalanan_pertama' => $pertama,
            'jumlah' => count($daftar),
            'voucher' => $daftar,
        ]);
    }

    /**
     * Tip untuk pengemudi, diberikan SELAMA perjalanan.
     *
     * Terpisah dari penilaian, yang baru bisa diisi setelah selesai. Orang yang
     * ingin berterima kasih saat masih di dalam kendaraan tidak punya jalan
     * lain, dan tombol yang menunggu sampai perjalanan berakhir sering
     * terlewat begitu penumpang turun.
     *
     * Seperti tip di penilaian: seluruhnya milik pengemudi, platform tidak
     * mengambil komisi dari uang terima kasih.
     */
    public function tip(Request $request, string $nomor): JsonResponse
    {
        $data = $request->validate([
            'tip' => ['required', 'integer', 'min:1000', 'max:100000'],
        ]);

        $task = $this->milikSaya($request, $nomor);
        $d = $task->detail_layanan;
        $tahap = $d['tahap'] ?? 'mencari';

        if (! in_array($tahap, ['dijemput', 'tiba', 'jalan'], true)) {
            throw ValidationException::withMessages([
                'tip' => $tahap === 'selesai'
                    ? 'Perjalanan sudah selesai. Tipnya diberikan lewat penilaian.'
                    : 'Belum ada pengemudi yang bisa diberi tip.',
            ]);
        }

        // Ditambahkan, bukan diganti: orang boleh menambah tip lebih dari sekali,
        // dan yang kedua tidak boleh menghapus yang pertama.
        $total = (int) ($d['tip'] ?? 0) + (int) $data['tip'];

        $task->update(['detail_layanan' => [...$d, 'tip' => $total]]);
        $task->payment()?->increment('jumlah', (int) $data['tip']);

        return response()->json(['tip' => $total]);
    }

    /** Penilaian pengemudi setelah perjalanan selesai. */
    public function nilai(Request $request, string $nomor): JsonResponse
    {
        $data = $request->validate([
            'bintang' => ['required', 'integer', 'min:1', 'max:5'],
            'tag' => ['nullable', 'array', 'max:6'],
            'tag.*' => ['string', 'max:40'],
            'ulasan' => ['nullable', 'string', 'max:500'],
            'tip' => ['nullable', 'integer', 'min:0', 'max:100000'],
        ]);

        $task = $this->milikSaya($request, $nomor);
        $d = $task->detail_layanan;

        if (($d['tahap'] ?? null) !== 'selesai') {
            throw ValidationException::withMessages([
                'bintang' => 'Perjalanannya belum selesai.',
            ]);
        }
        if (! empty($d['penilaian'])) {
            throw ValidationException::withMessages([
                'bintang' => 'Perjalanan ini sudah dinilai.',
            ]);
        }

        $task->update([
            'detail_layanan' => [
                ...$d,
                'penilaian' => [
                    'bintang' => (int) $data['bintang'],
                    'tag' => $data['tag'] ?? [],
                    'ulasan' => $data['ulasan'] ?? null,
                    'tip' => (int) ($data['tip'] ?? 0),
                    'dinilai_pada' => now()->toIso8601String(),
                ],
            ],
        ]);

        // Tip masuk sebagai tambahan tagihan yang seluruhnya milik pengemudi;
        // platform tidak mengambil komisi dari uang terima kasih.
        if (! empty($data['tip'])) {
            $task->payment()?->increment('jumlah', (int) $data['tip']);
        }

        return response()->json(['penilaian' => $task->fresh()->detail_layanan['penilaian']]);
    }

    /**
     * Lengkapi rute pengemudi menuju titik jemput kalau belum tersimpan.
     *
     * Rutenya ditulis saat pengemudi ditugaskan. Perjalanan yang pengemudinya
     * ditugaskan SEBELUM rute itu ada — atau saat penyedia rute kebetulan mati
     * — hanya punya koordinat, dan layar menariknya sebagai garis lurus. Garis
     * lurus melintasi gedung dan sungai; yang membacanya menyimpulkan
     * pengemudinya masih jauh atau aplikasinya rusak, dan dua-duanya keliru.
     *
     * Diisi di sini, bukan lewat migrasi data, karena posisi pengemudi memang
     * berubah terus di sistem yang sungguhan — yang perlu dijamin adalah
     * jawabannya selalu lengkap, bukan barisnya pernah ditambal sekali.
     * RuteJalan menyimpan hasilnya sepuluh menit, jadi pemanggilan berulang
     * dari layar pelacakan tidak menjadi panggilan berulang ke penyedia rute.
     *
     * @param  array<string, mixed>  $d
     * @return array<string, mixed>|null
     */
    private function lengkapiRutePengemudi(array $d): ?array
    {
        $p = $d['pengemudi'] ?? null;
        $jemput = $d['jemput'] ?? null;

        if (! is_array($p) || ! $jemput) {
            return $p;
        }
        if (($d['tahap'] ?? null) !== 'dijemput' || ! empty($p['rute'])) {
            return $p;
        }
        if (! isset($p['lat'], $p['lng'])) {
            return $p;
        }

        $rute = $this->rute->cari((float) $p['lat'], (float) $p['lng'], (float) $jemput['lat'], (float) $jemput['lng']);
        if (! $rute) {
            return $p;
        }

        // Jaraknya ikut diperbarui: yang benar adalah jarak yang ditempuh di
        // jalan, bukan garis lurus yang tersimpan sebelumnya.
        return [...$p, 'rute' => $rute['geometri'], 'jarak_km' => round($rute['km'], 2)];
    }

    private function milikSaya(Request $request, string $nomor): Task
    {
        $task = Task::where('nomor_invoice', strtoupper(trim($nomor)))
            ->where('customer_id', $request->user()->id)
            ->firstOrFail();

        if (($task->detail_layanan['layanan'] ?? null) !== 'jemput') {
            abort(404);
        }

        return $task;
    }

    private function perjalananPertama(int $userId): bool
    {
        return ! Task::where('customer_id', $userId)
            ->where('detail_layanan->layanan', 'jemput')
            ->exists();
    }
}
