<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Concerns\MenyimpanFotoTugas;
use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Category;
use App\Models\Task;
use App\Services\NomorInvoice;
use App\Services\TukangTarif;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * BisaTukang.
 *
 * Dua jalur masuk, karena uangnya memang berperilaku berbeda:
 *
 * - HARIAN (`checkout`) menagih biaya KUNJUNGAN saja. Harga perbaikannya
 *   menyusul lewat penawaran setelah tukang melihat kerusakannya, dan harus
 *   disetujui sebelum dikerjakan.
 * - BORONGAN (`permintaan`) tidak menagih apa pun dan tidak menyebut harga
 *   final. Nomornya REQ-, bukan invoice, supaya di riwayat pun terbaca sebagai
 *   permintaan yang masih menunggu RAB.
 *
 * Sesudah itu keduanya bertemu di jalur yang sama: `show` untuk melacak,
 * `setujui`/`revisi` untuk menjawab penawaran, `nilai` dan `tip` di ujungnya.
 *
 * SEMUA ANGKA DARI SERVER. Klien mengirim pilihan, bukan harga — kategori,
 * sub-kategori, dan jadwalnya divalidasi terhadap katalog, dan biayanya
 * dihitung ulang di sini.
 */
class TukangController extends Controller
{
    use MenyimpanFotoTugas;

    /** Urutan tahap; dipakai `show` dan perintah tukang:mitra. */
    public const URUTAN = ['mencari', 'menuju', 'tiba', 'penawaran', 'dikerjakan', 'selesai'];

    public function __construct(
        private readonly TukangTarif $tarif,
        private readonly NomorInvoice $nomorInvoice,
    ) {}

    /**
     * Katalog keahlian dan biaya kunjungannya.
     *
     * Dipanggil layar pemesanan sebelum apa pun dipilih. Sebelumnya daftar ini
     * hidup sebagai tetapan di sisi klien — yang berarti tarif yang dilihat
     * pemesan tidak pernah bisa diperbarui tanpa merilis ulang aplikasinya.
     */
    public function katalog(): JsonResponse
    {
        return response()->json([
            'kategori' => $this->tarif->katalog(),
            'tipe_properti' => TukangTarif::TIPE_PROPERTI,
            'penyediaan_material' => TukangTarif::PENYEDIAAN_MATERIAL,
            'lokasi_masalah' => TukangTarif::LOKASI_MASALAH,
            'borongan_mulai' => TukangTarif::BORONGAN_MULAI,
            'borongan_sampai' => TukangTarif::BORONGAN_SAMPAI,
            'garansi_hari' => TukangTarif::GARANSI_HARI,
        ]);
    }

    /* ==================== HARIAN ==================== */

    public function checkout(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate($this->aturanBersama() + [
            'jadwal_tipe' => ['required', Rule::in(TukangTarif::JADWAL_TIPE)],
            'jadwal_tanggal' => ['nullable', 'date'],
            'jadwal_jam' => ['nullable', 'string', 'max:20'],
            'metode' => ['nullable', 'string', 'max:30'],
        ]);

        $this->pastikanSubSah($data);

        $rincian = $this->tarif->kunjungan($data['kategori']);
        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);
        $jadwal = $this->parseJadwal($data);

        $task = DB::transaction(function () use ($user, $data, $rincian, $addressId, $alamat, $lat, $lng, $jadwal) {
            $task = Task::create([
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => Category::where('slug', 'bisatukang')->value('id'),
                'address_id' => $addressId,
                'tipe' => 'fixed',
                'judul' => "BisaTukang — {$rincian['nama_kategori']}",
                'deskripsi' => $this->ringkasan($data, $rincian),
                'status' => 'pending',
                'fulfillment_status' => 'diproses',
                'lokasi_alamat' => $alamat,
                'lokasi_lat' => $lat,
                'lokasi_lng' => $lng,
                // Yang ditagih hari ini KUNJUNGANNYA. Harga perbaikan menyusul
                // lewat penawaran, dan baru masuk sini setelah disetujui.
                'harga' => $rincian['biaya_kunjungan'],
                'catatan' => $data['deskripsi_masalah'],
                'dijadwalkan_pada' => $jadwal,
                'nama_penerima' => $data['nama_penerima'],
                'telepon_penerima' => $data['telepon_penerima'],
                'detail_layanan' => $this->spek($data, $rincian, 'harian') + [
                    'tahap' => 'mencari',
                    'jadwal_tipe' => $data['jadwal_tipe'],
                    'jadwal_jam' => $data['jadwal_jam'] ?? null,
                    'biaya_kunjungan' => $rincian['biaya_kunjungan'],
                    // Diisi tukang setelah memeriksa. Selama null, layar
                    // penawaran belum boleh menampilkan angka apa pun.
                    'penawaran' => null,
                    'tukang' => null,
                    'area' => ['Perbaikan rumah'],
                    'jumlah_cleaner' => 1,
                ],
            ]);

            $task->items()->create([
                'nama' => "Kunjungan & pemeriksaan — {$rincian['nama_kategori']}",
                'kategori' => 'layanan',
                'satuan' => 'kunjungan',
                'harga_satuan' => $rincian['biaya_kunjungan'],
                'qty' => 1,
                'subtotal' => $rincian['biaya_kunjungan'],
            ]);

            $task->payment()->create([
                'jumlah' => $rincian['biaya_kunjungan'],
                'subtotal_barang' => $rincian['biaya_kunjungan'],
                'ongkir' => 0,
                'ongkir_normal' => 0,
                'potongan' => 0,
                'cashback' => 0,
                'service_fee' => 0,
                'komisi_platform' => $rincian['komisi'],
                'status' => 'pending',
                'metode' => $data['metode'] ?? null,
            ]);

            return $task;
        });

        $this->simpanFoto($task, $data, 'tukang');

        return response()->json([
            ...$task->fresh()->load(['items', 'payment'])->toArray(),
            'nomor' => $task->nomor_invoice,
            'rincian' => $rincian,
        ], 201);
    }

    /* ==================== BORONGAN ==================== */

    /**
     * Permintaan survei borongan.
     *
     * Tidak membuat pembayaran dan tidak menyebut harga final. Rentangnya masuk
     * sebagai `budget`, bukan `harga`: angka yang mengikat baru ada di RAB
     * setelah lokasinya dilihat.
     */
    public function permintaan(Request $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validate($this->aturanBersama() + [
            'jadwal_tanggal' => ['nullable', 'date'],
            'jadwal_jam' => ['nullable', 'string', 'max:20'],
        ]);

        $this->pastikanSubSah($data);

        $k = TukangTarif::KATEGORI[$data['kategori']];
        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);

        $task = Task::create([
            'nomor_invoice' => $this->nomorPermintaan(),
            'customer_id' => $user->id,
            'category_id' => Category::where('slug', 'bisatukang')->value('id'),
            'address_id' => $addressId,
            'tipe' => 'custom',
            'judul' => "Permintaan Survei — {$k['nama']}",
            'deskripsi' => $this->ringkasan($data, null),
            'status' => 'pending',
            'fulfillment_status' => 'diproses',
            'lokasi_alamat' => $alamat,
            'lokasi_lat' => $lat,
            'lokasi_lng' => $lng,
            'budget' => TukangTarif::BORONGAN_MULAI,
            'catatan' => $data['deskripsi_masalah'],
            'nama_penerima' => $data['nama_penerima'],
            'telepon_penerima' => $data['telepon_penerima'],
            'detail_layanan' => $this->spek($data, null, 'borongan') + [
                'tahap' => 'mencari',
                'permintaan_survei' => true,
                'jadwal_jam' => $data['jadwal_jam'] ?? null,
                'biaya_kunjungan' => 0,
                'estimasi_mulai' => TukangTarif::BORONGAN_MULAI,
                'estimasi_sampai' => TukangTarif::BORONGAN_SAMPAI,
                'penawaran' => null,
                'tukang' => null,
                'area' => ['Renovasi & borongan'],
                'jumlah_cleaner' => 1,
            ],
        ]);

        $this->simpanFoto($task, $data, 'tukang');

        return response()->json([
            'id' => $task->id,
            'nomor' => $task->nomor_invoice,
            'model_kerja' => 'borongan',
            'survei_gratis' => true,
            'estimasi_mulai' => TukangTarif::BORONGAN_MULAI,
            'estimasi_sampai' => TukangTarif::BORONGAN_SAMPAI,
        ], 201);
    }

    /* ==================== Status ==================== */

    /** Status pekerjaan; dipanggil berulang oleh layar pelacakan. */
    public function show(Request $request, string $nomor): JsonResponse
    {
        $task = $this->milikSaya($request, $nomor);
        $d = $task->detail_layanan ?? [];
        $penawaran = $d['penawaran'] ?? null;

        return response()->json([
            'id' => $task->id,
            'nomor' => $task->nomor_invoice,
            'model_kerja' => $d['model_kerja'] ?? 'harian',
            'tahap' => $d['tahap'] ?? 'mencari',
            'kategori' => $d['kategori'] ?? null,
            'nama_kategori' => $d['nama_kategori'] ?? null,
            'sub_kategori' => $d['sub_kategori'] ?? null,
            'tipe_properti' => $d['tipe_properti'] ?? null,
            'penyediaan_material' => $d['penyediaan_material'] ?? null,
            'lokasi_masalah' => $d['lokasi_masalah'] ?? null,
            'deskripsi_masalah' => $d['deskripsi_masalah'] ?? null,
            'foto' => $d['foto'] ?? [],
            'lokasi_alamat' => $task->lokasi_alamat,
            'lokasi_lat' => (float) $task->lokasi_lat,
            'lokasi_lng' => (float) $task->lokasi_lng,
            'dijadwalkan_pada' => $task->dijadwalkan_pada?->toIso8601String(),
            'jadwal_tipe' => $d['jadwal_tipe'] ?? null,
            'jadwal_jam' => $d['jadwal_jam'] ?? null,
            'biaya_kunjungan' => (int) ($d['biaya_kunjungan'] ?? 0),
            'estimasi_mulai' => $d['estimasi_mulai'] ?? null,
            'estimasi_sampai' => $d['estimasi_sampai'] ?? null,
            'tukang' => $d['tukang'] ?? null,
            // Penawaran ikut membawa status kedaluwarsanya, dihitung server:
            // tanggal yang dibandingkan di browser mengikuti jam perangkatnya,
            // dan jam yang salah membuat penawaran hidup terbaca mati.
            'penawaran' => $penawaran === null
                ? null
                : [...$penawaran, 'kedaluwarsa' => $this->kedaluwarsa($penawaran)],
            'garansi_hari' => TukangTarif::GARANSI_HARI,
            'total' => (float) $task->harga,
            'tip' => (int) ($d['tip'] ?? 0),
            'metode' => $d['metode'] ?? $task->payment?->metode,
            'penilaian' => $d['penilaian'] ?? null,
            'dibatalkan' => $task->cancelled_at !== null,
        ]);
    }

    /* ==================== Penawaran ==================== */

    /**
     * Setujui penawaran.
     *
     * Yang dikirim klien hanya PERNYATAAN setuju — angkanya diambil dari
     * penawaran yang tersimpan, bukan dari badan permintaan. Kalau totalnya
     * ikut dikirim, siapa pun yang bisa memanggil API ini bisa menyetujui
     * pekerjaan dengan harga karangannya sendiri.
     */
    public function setujui(Request $request, string $nomor): JsonResponse
    {
        $data = $request->validate([
            // Bukan basa-basi: tanpa pernyataan ini, membuka halaman lalu salah
            // tekan sudah cukup untuk mengikat harga dan lingkup kerja.
            'setuju' => ['required', 'accepted'],
            'nama_penyetuju' => ['required', 'string', 'max:100'],
        ]);

        $task = $this->milikSaya($request, $nomor);
        $d = $task->detail_layanan ?? [];
        $penawaran = $d['penawaran'] ?? null;

        if ($penawaran === null) {
            throw ValidationException::withMessages([
                'setuju' => 'Penawaran untuk pekerjaan ini belum terbit.',
            ]);
        }
        if (($penawaran['keputusan'] ?? null) === 'disetujui') {
            throw ValidationException::withMessages(['setuju' => 'Penawaran ini sudah disetujui.']);
        }
        if ($this->kedaluwarsa($penawaran)) {
            throw ValidationException::withMessages([
                'setuju' => 'Penawaran ini sudah lewat masa berlaku. Minta penawaran baru ke tukangnya.',
            ]);
        }

        DB::transaction(function () use ($task, $d, $penawaran, $data) {
            $penawaran['keputusan'] = 'disetujui';
            $penawaran['disetujui_pada'] = now()->toIso8601String();
            $penawaran['nama_penyetuju'] = $data['nama_penyetuju'];

            $task->update([
                'detail_layanan' => [...$d, 'penawaran' => $penawaran, 'tahap' => 'dikerjakan'],
                // Baru sekarang ada harga pekerjaan: sebelum disetujui, angka
                // penawaran adalah usulan, bukan tagihan. Kunjungan yang sudah
                // ditagih ikut dijumlahkan supaya totalnya satu angka.
                'harga' => (int) ($d['biaya_kunjungan'] ?? 0) + (int) $penawaran['total'],
            ]);

            foreach ($penawaran['baris'] ?? [] as $b) {
                $task->items()->create([
                    'nama' => $b['nama'],
                    'kategori' => $b['kategori'] ?? 'layanan',
                    'satuan' => $b['satuan'] ?? 'paket',
                    'harga_satuan' => $b['nilai'],
                    'qty' => 1,
                    'subtotal' => $b['nilai'],
                ]);
            }

            // Pembayaran sudah ada sejak kunjungan (model harian); yang naik
            // tagihannya, bukan dibuat baris pembayaran kedua yang membuat satu
            // pekerjaan punya dua tagihan berbeda.
            $bayar = $task->payment;
            if ($bayar) {
                $bayar->increment('jumlah', (int) $penawaran['total']);
                $bayar->increment('subtotal_barang', (int) $penawaran['total']);
            } else {
                $task->payment()->create([
                    'jumlah' => $penawaran['total'],
                    'subtotal_barang' => $penawaran['total'],
                    'ongkir' => 0,
                    'ongkir_normal' => 0,
                    'potongan' => $penawaran['potongan'] ?? 0,
                    'cashback' => 0,
                    'service_fee' => 0,
                    'komisi_platform' => 0,
                    'status' => 'pending',
                    'metode' => null,
                ]);
            }
        });

        $segar = $task->fresh();

        return response()->json([
            'nomor' => $segar->nomor_invoice,
            'tahap' => 'dikerjakan',
            'total' => (float) $segar->harga,
            'garansi_hari' => TukangTarif::GARANSI_HARI,
        ]);
    }

    /**
     * Ajukan revisi penawaran.
     *
     * Tidak mengubah angka apa pun — permintaannya dicatat dan penawarannya
     * kembali menunggu yang baru. Membiarkan pemesan mengubah harga sendiri
     * berarti tidak ada lagi yang namanya penawaran.
     */
    public function revisi(Request $request, string $nomor): JsonResponse
    {
        $data = $request->validate([
            'catatan' => ['required', 'string', 'max:1000'],
            'alasan' => ['nullable', 'string', 'max:60'],
        ]);

        $task = $this->milikSaya($request, $nomor);
        $d = $task->detail_layanan ?? [];
        $penawaran = $d['penawaran'] ?? null;

        if ($penawaran === null) {
            throw ValidationException::withMessages([
                'catatan' => 'Penawaran untuk pekerjaan ini belum terbit.',
            ]);
        }
        if (($penawaran['keputusan'] ?? null) === 'disetujui') {
            throw ValidationException::withMessages([
                'catatan' => 'Penawaran ini sudah disetujui. Perubahan sesudahnya diajukan sebagai pekerjaan tambahan.',
            ]);
        }

        $riwayat = $penawaran['revisi'] ?? [];
        $riwayat[] = [
            'diajukan_pada' => now()->toIso8601String(),
            'alasan' => $data['alasan'] ?? null,
            'catatan' => $data['catatan'],
        ];

        $penawaran['revisi'] = $riwayat;
        $penawaran['keputusan'] = 'revisi';
        $penawaran['direvisi_pada'] = now()->toIso8601String();

        $task->update(['detail_layanan' => [...$d, 'penawaran' => $penawaran]]);

        return response()->json(['keputusan' => 'revisi', 'jumlah_revisi' => count($riwayat)]);
    }

    /* ==================== Pembatalan ==================== */

    /**
     * Batalkan sebelum tukangnya berangkat.
     *
     * Sesudah tukang di jalan, membatalkan lewat tombol berarti ada orang yang
     * sudah menempuh perjalanan tanpa dibayar. Dari tahap itu jalurnya lewat
     * bantuan, bukan sekali ketuk.
     */
    public function batal(Request $request, string $nomor): JsonResponse
    {
        $task = $this->milikSaya($request, $nomor);
        $d = $task->detail_layanan ?? [];
        $tahap = $d['tahap'] ?? 'mencari';

        if ($tahap !== 'mencari') {
            return response()->json([
                'dibatalkan' => false,
                'tukang_sudah_jalan' => true,
                'pesan' => 'Tukangnya sudah dalam perjalanan. Hubungi bantuan untuk membatalkan.',
            ], 422);
        }

        // `fulfillment_status` sengaja TIDAK disentuh: enumnya hanya mengenal
        // alur pemenuhan yang berjalan, dan pembatalan sudah punya tempatnya
        // sendiri di `status` + `cancelled_at`. Sepola dengan BisaJemput.
        $task->update([
            'status' => 'cancelled',
            'cancelled_at' => now(),
            'detail_layanan' => [...$d, 'tahap' => 'batal'],
        ]);
        $task->payment()?->update(['status' => 'cancelled']);

        return response()->json(['dibatalkan' => true, 'tukang_sudah_jalan' => false]);
    }

    /* ==================== Terima kasih ==================== */

    /**
     * Tip untuk tukang; seluruhnya miliknya.
     *
     * Sepola dengan BisaJemput dan BisaKirim: ditambahkan ke tagihan, TIDAK
     * menambah komisi platform. Uang terima kasih bukan omzet.
     */
    public function tip(Request $request, string $nomor): JsonResponse
    {
        $data = $request->validate([
            'tip' => ['required', 'integer', 'min:1000', 'max:500000'],
        ]);

        $task = $this->milikSaya($request, $nomor);
        $d = $task->detail_layanan ?? [];
        $tahap = $d['tahap'] ?? 'mencari';

        if (empty($d['tukang']) || ! in_array($tahap, ['tiba', 'penawaran', 'dikerjakan', 'selesai'], true)) {
            throw ValidationException::withMessages([
                'tip' => 'Belum ada tukang yang bisa diberi tip.',
            ]);
        }

        // Ditambahkan, bukan diganti: orang boleh menambah tip lebih dari sekali.
        $total = (int) ($d['tip'] ?? 0) + (int) $data['tip'];

        $task->update(['detail_layanan' => [...$d, 'tip' => $total]]);
        $task->payment()?->increment('jumlah', (int) $data['tip']);

        return response()->json(['tip' => $total]);
    }

    /** Penilaian tukang setelah pekerjaan selesai. */
    public function nilai(Request $request, string $nomor): JsonResponse
    {
        $data = $request->validate([
            'bintang' => ['required', 'integer', 'min:1', 'max:5'],
            'tag' => ['nullable', 'array', 'max:6'],
            'tag.*' => ['string', 'max:40'],
            'ulasan' => ['nullable', 'string', 'max:500'],
        ]);

        $task = $this->milikSaya($request, $nomor);
        $d = $task->detail_layanan ?? [];

        if (($d['tahap'] ?? null) !== 'selesai') {
            throw ValidationException::withMessages(['bintang' => 'Pekerjaannya belum selesai.']);
        }
        if (! empty($d['penilaian'])) {
            throw ValidationException::withMessages(['bintang' => 'Pekerjaan ini sudah dinilai.']);
        }

        $task->update([
            'detail_layanan' => [
                ...$d,
                'penilaian' => [
                    'bintang' => (int) $data['bintang'],
                    'tag' => $data['tag'] ?? [],
                    'ulasan' => $data['ulasan'] ?? null,
                    'dinilai_pada' => now()->toIso8601String(),
                ],
            ],
        ]);

        return response()->json(['penilaian' => $task->fresh()->detail_layanan['penilaian']]);
    }

    /* ==================== Bersama ==================== */

    /**
     * Aturan yang sama untuk kedua jalur masuk.
     *
     * @return array<string, mixed>
     */
    private function aturanBersama(): array
    {
        return [
            'kategori' => ['required', Rule::in(TukangTarif::idKategori())],
            'sub_kategori' => ['required', 'string', 'max:80'],
            'tipe_properti' => ['required', Rule::in(TukangTarif::TIPE_PROPERTI)],
            'penyediaan_material' => ['required', Rule::in(TukangTarif::PENYEDIAAN_MATERIAL)],
            'lokasi_masalah' => ['required', Rule::in(TukangTarif::LOKASI_MASALAH)],
            // Delapan aksara: batas yang sama dengan layar, dan alasannya sama —
            // "rusak" tidak cukup untuk disurvei orang.
            'deskripsi_masalah' => ['required', 'string', 'min:8', 'max:1000'],

            'nama_penerima' => ['required', 'string', 'max:100'],
            'telepon_penerima' => ['required', 'string', 'max:30'],

            'address_id' => ['nullable', 'integer'],
            'lokasi_alamat' => ['required_without:address_id', 'string', 'max:255'],
            'lokasi_lat' => ['required_without:address_id', 'numeric'],
            'lokasi_lng' => ['required_without:address_id', 'numeric'],

            ...$this->aturanFoto(5),
        ];
    }

    /**
     * Sub-kategori diperiksa terhadap keahlian yang dipilih, bukan terhadap
     * seluruh daftar: "Atap bocor" di bawah kategori Listrik akan mengirim
     * tukang yang salah ke rumah orang.
     *
     * @param  array<string, mixed>  $data
     */
    private function pastikanSubSah(array $data): void
    {
        if (! $this->tarif->subSah($data['kategori'], $data['sub_kategori'])) {
            throw ValidationException::withMessages([
                'sub_kategori' => 'Jenis masalah itu tidak ada di kategori yang dipilih.',
            ]);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $rincian
     * @return array<string, mixed>
     */
    private function spek(array $data, ?array $rincian, string $model): array
    {
        $k = TukangTarif::KATEGORI[$data['kategori']];

        return [
            'layanan' => 'tukang',
            'model_kerja' => $model,
            'kategori' => $data['kategori'],
            'nama_kategori' => $rincian['nama_kategori'] ?? $k['nama'],
            'sub_kategori' => $data['sub_kategori'],
            'tipe_properti' => $data['tipe_properti'],
            'penyediaan_material' => $data['penyediaan_material'],
            'lokasi_masalah' => $data['lokasi_masalah'],
            'deskripsi_masalah' => $data['deskripsi_masalah'],
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<string, mixed>|null  $rincian
     */
    private function ringkasan(array $data, ?array $rincian): string
    {
        $k = TukangTarif::KATEGORI[$data['kategori']];
        $bagian = [
            $rincian['nama_kategori'] ?? $k['nama'],
            $data['sub_kategori'],
            'properti '.$data['tipe_properti'],
            'lokasi '.strtolower($data['lokasi_masalah']),
            'material dari '.$data['penyediaan_material'],
        ];

        return implode(' · ', $bagian);
    }

    /** Nomor permintaan survei; berbeda dari invoice supaya terbaca beda. */
    private function nomorPermintaan(): string
    {
        return 'REQ-'.now()->format('ymd').'-'.strtoupper(bin2hex(random_bytes(3)));
    }

    private function milikSaya(Request $request, string $nomor): Task
    {
        $nomor = strtoupper(trim($nomor));

        $task = Task::query()
            ->where('customer_id', $request->user()->id)
            ->where(fn ($q) => $q
                ->where('nomor_invoice', $nomor)
                ->orWhere('nomor_invoice', 'like', '%-'.$nomor))
            ->firstOrFail();

        if (($task->detail_layanan['layanan'] ?? null) !== 'tukang') {
            abort(404);
        }

        return $task;
    }

    /**
     * Masa berlaku dihitung SERVER, dan penawaran tanpa tanggal batas memang
     * sah — tidak ada tanggal bukan berarti sudah lewat.
     *
     * @param  array<string, mixed>  $penawaran
     */
    private function kedaluwarsa(array $penawaran): bool
    {
        $sampai = $penawaran['berlaku_sampai'] ?? null;
        if (! $sampai) {
            return false;
        }

        try {
            return Carbon::parse($sampai)->endOfDay()->isPast();
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Jadwal kunjungan.
     *
     * "Secepatnya" sengaja TIDAK diberi jam: menuliskan jam untuk sesuatu yang
     * belum dijanjikan siapa-siapa membuat layar menampilkan janji yang tidak
     * pernah dibuat.
     *
     * @param  array<string, mixed>  $data
     */
    private function parseJadwal(array $data): ?Carbon
    {
        if (($data['jadwal_tipe'] ?? null) === 'secepatnya') {
            return null;
        }

        $tanggal = $data['jadwal_tanggal'] ?? null;
        if (! $tanggal) {
            return null;
        }

        try {
            $jam = explode('-', (string) ($data['jadwal_jam'] ?? '09:00'))[0];

            return Carbon::parse(trim($tanggal.' '.$jam));
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $data
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
