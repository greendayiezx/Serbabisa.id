{{--
    Invoice pesanan.

    Berbeda dari bukti permintaan penawaran: dokumen ini memuat ANGKA YANG
    DITAGIHKAN, diambil dari baris item dan baris pembayaran yang tersimpan —
    bukan dihitung ulang di sini. Kalau harga di aplikasi dan di dokumen ini
    berbeda, itu berarti datanya memang berbeda, dan itu harus terlihat.
--}}
@php
    $rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');

    /*
     * Waktu disimpan UTC (APP_TIMEZONE=UTC) — itu benar dan tidak diubah. Yang
     * dikonversi adalah TAMPILANNYA; tanpa ini dokumen mencetak jam UTC lalu
     * melabelinya WIB, meleset 7 jam.
     */
    $tgl = function ($waktu, bool $denganJam = true) {
        if (! $waktu) {
            return '-';
        }

        return \Illuminate\Support\Carbon::parse($waktu)
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat($denganJam ? 'j F Y, H:i' : 'j F Y');
    };

    $bayar = $task->payment;

    $statusBayar = match ($bayar?->status) {
        'paid' => 'LUNAS',
        'released' => 'LUNAS',
        'refunded' => 'DIKEMBALIKAN',
        'failed' => 'GAGAL',
        default => 'BELUM DIBAYAR',
    };

    $statusKerja = match ($task->status) {
        'pending' => 'Menunggu cleaner',
        'accepted' => 'Sudah diterima cleaner',
        'in_progress' => 'Sedang dikerjakan',
        'completed' => 'Selesai',
        'cancelled' => 'Dibatalkan',
        default => $task->status,
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $task->nomor_invoice }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10.5px; color: #1b1c1b; margin: 0; }
        h1 { font-size: 19px; margin: 0 0 2px; color: #004a78; }
        h2 { font-size: 12.5px; margin: 18px 0 6px; color: #004a78; }
        .kepala { border-bottom: 2px solid #1E9BF0; padding-bottom: 10px; margin-bottom: 14px; }
        .kecil { font-size: 9.5px; color: #414750; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta td.k { color: #414750; width: 150px; }
        .cap { display: inline-block; border: 1.5px solid #1E9BF0; color: #004a78;
               padding: 3px 10px; font-size: 11px; font-weight: bold; }
        .rincian th { text-align: left; border-bottom: 1px solid #c0c7d1; padding: 6px 0; color: #414750; font-size: 9.5px; }
        .rincian td { padding: 6px 0; border-bottom: 1px solid #e4e8ee; vertical-align: top; }
        .kanan { text-align: right; }
        .total td { padding-top: 8px; font-size: 13px; font-weight: bold; border-bottom: none; }
        .kotak { border: 1px solid #c0c7d1; padding: 10px; margin-top: 4px; }
        .kaki { margin-top: 26px; font-size: 9px; color: #6a7280; border-top: 1px solid #e4e8ee; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="kepala">
        <h1>Invoice</h1>
        <div class="kecil">Serbabisa.id &middot; {{ $task->judul }}</div>
    </div>

    <table class="meta">
        <tr>
            <td class="k">Nomor invoice</td>
            <td><strong>{{ $task->nomor_invoice }}</strong></td>
        </tr>
        <tr>
            <td class="k">Tanggal terbit</td>
            <td>{{ $tgl($task->created_at) }} WIB</td>
        </tr>
        <tr>
            <td class="k">Status pembayaran</td>
            <td><span class="cap">{{ $statusBayar }}</span></td>
        </tr>
        <tr>
            <td class="k">Status pengerjaan</td>
            <td>{{ $statusKerja }}</td>
        </tr>
        <tr>
            <td class="k">Metode pembayaran</td>
            <td>{{ $bayar?->metode ? strtoupper($bayar->metode) : '-' }}</td>
        </tr>
    </table>

    <h2>Pemesan</h2>
    <table class="meta">
        <tr>
            <td class="k">Nama</td>
            <td>{{ $task->customer?->name ?? '-' }}</td>
        </tr>
        @if ($task->nama_penerima)
            <tr>
                <td class="k">PIC di lokasi</td>
                <td>{{ $task->nama_penerima }}{{ $task->telepon_penerima ? ' · ' . $task->telepon_penerima : '' }}</td>
            </tr>
        @endif
        <tr>
            <td class="k">Alamat pengerjaan</td>
            <td>{{ $task->lokasi_alamat ?: '-' }}</td>
        </tr>
        <tr>
            <td class="k">Jadwal pengerjaan</td>
            <td>{{ $tgl($task->dijadwalkan_pada) }} WIB</td>
        </tr>
        @if (! empty($detail['jumlah_cleaner']))
            <tr>
                <td class="k">Jumlah kru</td>
                <td>{{ $detail['jumlah_cleaner'] }} orang</td>
            </tr>
        @endif
    </table>

    <h2>Rincian</h2>
    <table class="rincian">
        <thead>
            <tr>
                <th>Item</th>
                <th class="kanan" style="width: 40px;">Qty</th>
                <th class="kanan" style="width: 90px;">Harga</th>
                <th class="kanan" style="width: 90px;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($task->items as $item)
                <tr>
                    <td>
                        {{ $item->nama }}
                        @if ($item->kategori)
                            <div class="kecil">{{ $item->kategori }}</div>
                        @endif
                    </td>
                    <td class="kanan">{{ $item->qty }}{{ $item->satuan ? ' ' . $item->satuan : '' }}</td>
                    <td class="kanan">{{ $rp($item->harga_satuan) }}</td>
                    <td class="kanan">{{ $rp($item->subtotal) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">{{ $task->deskripsi ?: 'Tidak ada rincian item.' }}</td>
                </tr>
            @endforelse

            @if ($bayar && (float) $bayar->ongkir > 0)
                <tr>
                    <td colspan="3">Ongkos kirim</td>
                    <td class="kanan">{{ $rp($bayar->ongkir) }}</td>
                </tr>
            @endif

            @if ($bayar && (float) $bayar->service_fee > 0)
                <tr>
                    <td colspan="3">Biaya layanan</td>
                    <td class="kanan">{{ $rp($bayar->service_fee) }}</td>
                </tr>
            @endif

            @if ($bayar && (float) $bayar->potongan > 0)
                <tr>
                    <td colspan="3">
                        Potongan
                        @if (! empty($detail['promo_kode']))
                            <div class="kecil">Termasuk promo {{ $detail['promo_kode'] }}</div>
                        @endif
                    </td>
                    <td class="kanan">&minus;{{ $rp($bayar->potongan) }}</td>
                </tr>
            @endif

            <tr class="total">
                <td colspan="3">Total tagihan</td>
                <td class="kanan">{{ $rp($bayar?->jumlah ?? $task->harga) }}</td>
            </tr>
        </tbody>
    </table>

    @if ($task->deskripsi)
        <h2>Keterangan layanan</h2>
        <div class="kotak">{{ $task->deskripsi }}</div>
    @endif

    @if ($task->catatan)
        <h2>Catatan pelanggan</h2>
        <div class="kotak">{{ $task->catatan }}</div>
    @endif

    <div class="kaki">
        Dokumen ini dibuat otomatis oleh Serbabisa.id pada
        {{ $tgl(now()) }} WIB dan sah tanpa tanda tangan basah.
    </div>
</body>
</html>
