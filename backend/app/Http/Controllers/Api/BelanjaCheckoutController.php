<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use App\Models\Promo;
use App\Models\Referral;
use App\Models\Subscription;
use App\Models\Task;
use App\Models\Wallet;
use App\Services\OngkirCalculator;
use App\Services\PromoEvaluator;
use App\Services\PromoResult;
use App\Services\NomorInvoice;
use App\Services\WalletLedger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Checkout BisaBelanja. Semua angka dihitung ULANG di server dari harga
 * katalog yang otoritatif — harga yang dikirim klien untuk item berkatalog
 * diabaikan agar tidak bisa dimanipulasi. Promo divalidasi ulang (termasuk
 * kuota) sebelum dipakai, lalu pemakaiannya dicatat di promo_redemptions.
 */
class BelanjaCheckoutController extends Controller
{
    public function __construct(
        private readonly OngkirCalculator $ongkir,
        private readonly PromoEvaluator $promoEvaluator,
        private readonly WalletLedger $ledger,
        private readonly NomorInvoice $nomorInvoice,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['nullable', 'integer', 'exists:products,id'],
            'items.*.sku' => ['nullable', 'string', 'max:80'],
            'items.*.nama' => ['required_without_all:items.*.product_id,items.*.sku', 'nullable', 'string', 'max:255'],
            'items.*.kategori' => ['nullable', 'string', 'max:60'],
            'items.*.satuan' => ['nullable', 'string', 'max:30'],
            'items.*.harga' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:999'],
            'items.*.catatan' => ['nullable', 'string', 'max:255'],
            'jarak_km' => ['required', 'numeric', 'min:0', 'max:100'],
            'promo_id' => ['nullable', 'integer', 'exists:promos,id'],
            'promo_slug' => ['nullable', 'string', 'max:60', 'exists:promos,slug'],
            'subscription_id' => ['nullable', 'integer', 'exists:subscriptions,id'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'lokasi_alamat' => ['required_without:address_id', 'nullable', 'string', 'max:255'],
            'lokasi_lat' => ['required_without:address_id', 'nullable', 'numeric'],
            'lokasi_lng' => ['required_without:address_id', 'nullable', 'numeric'],
            'toko' => ['nullable', 'string', 'max:120'],
            'catatan' => ['nullable', 'string', 'max:500'],
            'nama_penerima' => ['nullable', 'string', 'max:120'],
            'telepon_penerima' => ['nullable', 'string', 'max:30'],
            'metode' => ['nullable', 'string', 'max:40'],
        ]);

        // --- Harga otoritatif: item berkatalog memakai harga_jual dari DB ---
        // Klien mengirim `sku` (= id katalog frontend); `product_id` tetap diterima
        // untuk pemanggil lain. Keduanya diresolusi ke baris Product yang sama.
        $items = collect($data['items']);
        $olehId = Product::with('category')
            ->whereIn('id', $items->pluck('product_id')->filter()->all())
            ->get()->keyBy('id');
        $olehSku = Product::with('category')
            ->whereIn('sku', $items->pluck('sku')->filter()->all())
            ->get()->keyBy('sku');

        $baris = [];
        $totalBarang = 0.0;
        $subtotalKategori = [];

        foreach ($data['items'] as $it) {
            $p = isset($it['product_id']) ? $olehId->get($it['product_id']) : null;
            $p ??= isset($it['sku']) ? $olehSku->get($it['sku']) : null;
            $harga = $p ? (float) $p->harga_jual : (isset($it['harga']) ? (float) $it['harga'] : null);
            $nama = $p->nama ?? ($it['nama'] ?? 'Item');
            $kategori = $p->category->slug ?? ($it['kategori'] ?? 'lainnya');
            $satuan = $p->satuan ?? ($it['satuan'] ?? null);
            $qty = (int) $it['qty'];
            $subtotal = $harga !== null ? round($harga * $qty, 2) : null;

            if ($subtotal !== null) {
                $totalBarang += $subtotal;
                $subtotalKategori[$kategori] = ($subtotalKategori[$kategori] ?? 0) + $subtotal;
            }

            $baris[] = [
                'product_id' => $p?->id,
                // Id katalog frontend. Untuk item "Lainnya" yang diketik manual
                // tidak ada padanannya di products, jadi ikut null.
                'sku' => $p?->sku,
                'nama' => $nama,
                'kategori' => $kategori,
                'satuan' => $satuan,
                'harga_satuan' => $harga,
                'qty' => $qty,
                'catatan' => $it['catatan'] ?? null,
                'subtotal' => $subtotal,
            ];
        }

        $ongkirDasar = $this->ongkir->untuk((float) $data['jarak_km']);

        // --- Promo: evaluasi ulang di server ---
        $promo = null;
        $hasil = new PromoResult(false);
        if (! empty($data['promo_id']) || ! empty($data['promo_slug'])) {
            $promo = ! empty($data['promo_id'])
                ? Promo::findOrFail($data['promo_id'])
                : Promo::where('slug', $data['promo_slug'])->firstOrFail();
            $hasil = $this->promoEvaluator->evaluate($promo, $user, [
                'totalBarang' => $totalBarang,
                'ongkir' => $ongkirDasar,
                'subtotalKategori' => $subtotalKategori,
                'transaksiKe' => $this->transaksiKe($user),
                'waktu' => Carbon::now(),
            ]);

            if (! $hasil->berlaku) {
                throw ValidationException::withMessages([
                    'promo_id' => $hasil->alasan ?? 'Promo tidak dapat dipakai.',
                ]);
            }
        }

        // --- Langganan: potongan tetap + gratis ongkir bila masih ada kuota ---
        $diskonLangganan = 0.0;
        $ongkirGratisLangganan = false;
        $langganan = $this->langgananAktif($data['subscription_id'] ?? null, $user->id);
        if ($langganan) {
            $plan = $langganan->plan;
            if ($plan->diskon_per_transaksi > 0 && $langganan->sisa_kuota_diskon > 0) {
                $diskonLangganan = min((float) $plan->diskon_per_transaksi, $totalBarang);
            }
            if ($langganan->sisa_kuota_ongkir > 0 && $totalBarang >= (float) $plan->min_ongkir_gratis) {
                $ongkirGratisLangganan = true;
            }
        }

        // --- Total tagihan (mengikuti rumus checkout frontend) ---
        $potonganBarang = $hasil->potonganBarang + $diskonLangganan;
        $ongkirGratis = $ongkirGratisLangganan || $hasil->potonganOngkir >= $ongkirDasar;
        $biayaOngkir = $ongkirGratis ? 0.0 : $ongkirDasar;
        $hargaBarangAkhir = max(0, $totalBarang - $potonganBarang);
        $totalTagihan = max(0, $hargaBarangAkhir + OngkirCalculator::BIAYA_LAYANAN + $biayaOngkir);
        $cashback = $hasil->cashback;

        // --- Lokasi antar ---
        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);

        $kategoriLayanan = Category::where('slug', 'bisabelanja')->value('id');

        $task = DB::transaction(function () use (
            $user, $data, $baris, $kategoriLayanan, $addressId, $promo, $langganan,
            $alamat, $lat, $lng, $totalTagihan, $totalBarang, $biayaOngkir, $ongkirDasar, $potonganBarang,
            $cashback, $hasil, $diskonLangganan, $ongkirGratisLangganan
        ) {
            $task = Task::create([
                // Nomor invoice diterbitkan server, bukan browser: kolomnya unik
                // sehingga dua perangkat tidak mungkin memakai nomor yang sama.
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => $kategoriLayanan,
                'address_id' => $addressId,
                'promo_id' => $promo?->id,
                'subscription_id' => $langganan?->id,
                'tipe' => 'fixed',
                'judul' => 'Pesanan BisaBelanja'.($data['toko'] ?? '' ? ' — '.$data['toko'] : ''),
                'deskripsi' => 'Pesanan belanja titip',
                'toko' => $data['toko'] ?? null,
                'status' => 'pending',
                'fulfillment_status' => 'diproses',
                'lokasi_alamat' => $alamat,
                'lokasi_lat' => $lat,
                'lokasi_lng' => $lng,
                'harga' => $totalTagihan,
                'catatan' => $data['catatan'] ?? null,
                'nama_penerima' => $data['nama_penerima'] ?? null,
                'telepon_penerima' => $data['telepon_penerima'] ?? null,
            ]);

            $task->items()->createMany($baris);

            $task->payment()->create([
                'promo_id' => $promo?->id,
                'jumlah' => $totalTagihan,
                'subtotal_barang' => $totalBarang,
                'ongkir' => $biayaOngkir,
                'ongkir_normal' => $ongkirDasar,
                'potongan' => $potonganBarang,
                'cashback' => $cashback,
                'service_fee' => OngkirCalculator::BIAYA_LAYANAN,
                'komisi_platform' => 0,
                'status' => 'pending',
                'metode' => $data['metode'] ?? null,
            ]);

            if ($promo && $hasil->berlaku) {
                $promo->redemptions()->create([
                    'user_id' => $user->id,
                    'task_id' => $task->id,
                    'potongan_barang' => $hasil->potonganBarang,
                    'potongan_ongkir' => $hasil->potonganOngkir,
                    'cashback' => $hasil->cashback,
                    'redeemed_at' => now(),
                ]);
                $promo->increment('kuota_terpakai');
            }

            if ($langganan) {
                if ($diskonLangganan > 0) {
                    $langganan->decrement('sisa_kuota_diskon');
                }
                if ($ongkirGratisLangganan) {
                    $langganan->decrement('sisa_kuota_ongkir');
                }
            }

            return $task;
        });

        return response()->json($task->load(['items', 'payment']), 201);
    }

    /**
     * Riwayat pesanan BisaBelanja milik user, terbaru dulu.
     *
     * Dibatasi 20 baris karena halaman riwayat di aplikasi memang hanya
     * menampilkan sebanyak itu; kalau nanti perlu lebih, ganti ke paginate().
     */
    public function index(Request $request): JsonResponse
    {
        $kategoriId = Category::where('slug', 'bisabelanja')->value('id');

        $pesanan = Task::with(['items', 'payment', 'promo', 'category'])
            ->where('customer_id', $request->user()->id)
            ->when($kategoriId, fn ($q) => $q->where('category_id', $kategoriId))
            ->whereNotNull('nomor_invoice')
            ->latest('id')
            ->limit(20)
            ->get();

        return response()->json($pesanan);
    }

    /**
     * Satu pesanan berdasarkan nomor invoice.
     *
     * Menerima dua bentuk: nomor invoice utuh (SBB-260819-5D9C5H0) atau bagian
     * kodenya saja (5D9C5H0), karena URL halaman status memakai bentuk pendek.
     * Pencarian bentuk pendek memakai akhiran nomor, bukan LIKE bebas, supaya
     * tidak bisa dipakai menyapu nomor pesanan milik orang lain — lagipula
     * hasilnya tetap disaring ke customer yang sedang login.
     */
    public function show(Request $request, string $nomor): JsonResponse
    {
        $nomor = strtoupper(trim($nomor));

        $task = Task::with(['items', 'payment', 'promo', 'category'])
            ->where('customer_id', $request->user()->id)
            ->where(fn ($q) => $q
                ->where('nomor_invoice', $nomor)
                ->orWhere('nomor_invoice', 'like', '%-'.$nomor))
            ->firstOrFail();

        return response()->json($task);
    }

    /**
     * Tandai pesanan selesai. Di sinilah cashback promo benar-benar masuk saldo
     * customer — lewat buku besar, idempoten, jadi menandai selesai dua kali
     * tidak menggandakan cashback.
     */
    public function complete(Request $request, Task $task): JsonResponse
    {
        $user = $request->user();
        abort_unless(
            $user->isAdmin() || $task->mitra_id === $user->id || $task->customer_id === $user->id,
            403,
        );
        abort_if($task->fulfillment_status === 'selesai', 422, 'Pesanan sudah selesai.');

        DB::transaction(function () use ($task) {
            $task->update([
                'fulfillment_status' => 'selesai',
                'status' => 'completed',
                'completed_at' => now(),
            ]);

            $cashback = (float) ($task->payment?->cashback ?? 0);
            if ($cashback > 0) {
                $wallet = Wallet::firstOrCreate(['user_id' => $task->customer_id], ['saldo' => 0]);
                $this->ledger->credit(
                    wallet: $wallet,
                    tipe: 'cashback',
                    jumlah: $cashback,
                    referensi: $task,
                    keterangan: 'Cashback pesanan BisaBelanja',
                    idempotencyKey: "cashback:task:{$task->id}",
                );
            }

            $this->cairkanBonusReferral($task);
        });

        return response()->json($task->fresh()->load(['items', 'payment']));
    }

    /**
     * Cairkan bonus pengundang saat pesanan pertama yang direferal selesai.
     * Idempoten lewat kunci ledger + status referral, jadi aman dipanggil ulang.
     */
    private function cairkanBonusReferral(Task $task): void
    {
        $referral = Referral::where('referred_id', $task->customer_id)
            ->where('status', 'pending')
            ->first();

        if (! $referral) {
            return;
        }

        $reward = (float) ($referral->reward_cashback ?: Referral::DEFAULT_REWARD);
        if ($reward > 0) {
            $wallet = Wallet::firstOrCreate(['user_id' => $referral->referrer_id], ['saldo' => 0]);
            $this->ledger->credit(
                wallet: $wallet,
                tipe: 'cashback',
                jumlah: $reward,
                referensi: $referral,
                keterangan: 'Bonus referral — teman yang diundang belanja pertama kali',
                idempotencyKey: "referral:{$referral->id}",
            );
        }

        $referral->update([
            'status' => 'rewarded',
            'task_id' => $task->id,
            'rewarded_at' => now(),
        ]);
    }

    /** Transaksi Belanja ke berapa bagi user (untuk syarat "transaksi pertama"). */
    private function transaksiKe(\App\Models\User $user): int
    {
        $kategoriId = Category::where('slug', 'bisabelanja')->value('id');

        return Task::where('customer_id', $user->id)
            ->when($kategoriId, fn ($q) => $q->where('category_id', $kategoriId))
            ->count() + 1;
    }

    private function langgananAktif(?int $subscriptionId, int $userId): ?Subscription
    {
        if (! $subscriptionId) {
            return null;
        }

        return Subscription::with('plan')
            ->where('id', $subscriptionId)
            ->where('user_id', $userId)
            ->where('status', 'active')
            ->first();
    }

    /**
     * @return array{0:string,1:float,2:float,3:int|null} [alamat, lat, lng, address_id]
     */
    private function resolveLokasi(array $data, int $userId): array
    {
        if (! empty($data['address_id'])) {
            $address = \App\Models\Address::where('id', $data['address_id'])
                ->where('user_id', $userId)
                ->firstOrFail();

            return [$address->alamat, (float) $address->lat, (float) $address->lng, $address->id];
        }

        return [$data['lokasi_alamat'], (float) $data['lokasi_lat'], (float) $data['lokasi_lng'], null];
    }
}
