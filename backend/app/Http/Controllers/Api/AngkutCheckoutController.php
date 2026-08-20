<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Models\Category;
use App\Models\Task;
use App\Services\AngkutTarif;
use App\Services\NomorInvoice;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Checkout BisaAngkut. Total dihitung ulang di server dari AngkutTarif — harga
 * yang tampil di layar tidak dipercaya. Pesanan disimpan sebagai `task`
 * berkategori bisaangkut sehingga otomatis muncul di riwayat "Tugas Saya".
 */
class AngkutCheckoutController extends Controller
{
    public function __construct(
        private readonly AngkutTarif $tarif,
        private readonly NomorInvoice $nomorInvoice,
    ) {}

    public function store(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'vehicle_id' => ['required', Rule::in(AngkutTarif::idKendaraan())],
            'delivery_id' => ['required', Rule::in(AngkutTarif::idLayanan())],
            'protection_id' => ['required', Rule::in(AngkutTarif::idProteksi())],
            'helper_count' => ['required', 'integer', 'min:0', 'max:'.AngkutTarif::MAKS_HELPER],
            'berat_total' => ['required', 'numeric', 'min:0.1', 'max:100000'],
            'tanggal' => ['required', 'date'],
            'waktu' => ['required', 'string', 'max:10'],
            'catatan' => ['required', 'string', 'max:1000'],
            'nama_penerima' => ['required', 'string', 'max:120'],
            'telepon_penerima' => ['required', 'string', 'max:30'],
            'address_id' => ['nullable', 'integer', 'exists:addresses,id'],
            'lokasi_alamat' => ['required_without:address_id', 'nullable', 'string', 'max:255'],
            'lokasi_lat' => ['required_without:address_id', 'nullable', 'numeric'],
            'lokasi_lng' => ['required_without:address_id', 'nullable', 'numeric'],
            'patokan' => ['nullable', 'string', 'max:255'],
            'metode' => ['nullable', 'string', 'max:40'],
        ]);

        // --- Harga otoritatif ---
        $transport = $this->tarif->hargaTransport($data['vehicle_id'], $data['delivery_id']);
        $biayaHelper = $this->tarif->hargaHelper((int) $data['helper_count']);
        $proteksi = $this->tarif->proteksi($data['protection_id']);
        $total = $transport + $biayaHelper + $proteksi['harga'];

        $labelKendaraan = $this->tarif->labelKendaraan($data['vehicle_id']);
        $labelLayanan = $this->tarif->labelLayanan($data['delivery_id']);

        [$alamat, $lat, $lng, $addressId] = $this->resolveLokasi($data, $user->id);
        $jadwal = $this->parseJadwal($data['tanggal'], $data['waktu']);
        $kategoriId = Category::where('slug', 'bisaangkut')->value('id');

        $task = DB::transaction(function () use (
            $user, $data, $kategoriId, $addressId, $alamat, $lat, $lng, $jadwal,
            $labelKendaraan, $labelLayanan, $proteksi, $transport, $biayaHelper, $total
        ) {
            $task = Task::create([
                'nomor_invoice' => $this->nomorInvoice->terbitkan()['invoice'],
                'customer_id' => $user->id,
                'category_id' => $kategoriId,
                'address_id' => $addressId,
                'tipe' => 'fixed',
                'judul' => "BisaAngkut — {$labelKendaraan} · {$labelLayanan}",
                'deskripsi' => $data['catatan'],
                'status' => 'pending',
                'fulfillment_status' => 'diproses',
                'lokasi_alamat' => $alamat,
                'lokasi_lat' => $lat,
                'lokasi_lng' => $lng,
                'harga' => $total,
                'catatan' => $data['patokan'] ?? null,
                'kendaraan' => $labelKendaraan,
                'jumlah_helper' => (int) $data['helper_count'],
                'berat_total' => $data['berat_total'],
                'proteksi_label' => $proteksi['label'],
                'proteksi_harga' => $proteksi['harga'],
                'dijadwalkan_pada' => $jadwal,
                'nama_penerima' => $data['nama_penerima'],
                'telepon_penerima' => $data['telepon_penerima'],
            ]);

            $task->payment()->create([
                'jumlah' => $total,
                'ongkir' => $transport,
                // Helper + proteksi disatukan sebagai biaya layanan agar
                // ongkir + service_fee = jumlah (nota bisa direkonstruksi).
                'service_fee' => $biayaHelper + $proteksi['harga'],
                'status' => 'pending',
                'metode' => $data['metode'] ?? null,
            ]);

            return $task;
        });

        return response()->json($task->load('payment'), 201);
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
     * @return array{0:string,1:float,2:float,3:int|null} [alamat, lat, lng, address_id]
     */
    private function resolveLokasi(array $data, int $userId): array
    {
        if (! empty($data['address_id'])) {
            $address = Address::where('id', $data['address_id'])
                ->where('user_id', $userId)
                ->firstOrFail();

            return [$address->alamat, (float) $address->lat, (float) $address->lng, $address->id];
        }

        return [$data['lokasi_alamat'], (float) $data['lokasi_lat'], (float) $data['lokasi_lng'], null];
    }
}
