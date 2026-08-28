{{--
    Bukti permintaan penawaran.

    Bukan penawaran: dokumen ini hanya merekam APA yang diminta pelanggan dan
    kapan, lengkap dengan tanda tangannya. Harga di sini disebut estimasi
    aplikasi, bukan tagihan — penawaran resminya menyusul dari tim.
--}}
@php
    $rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');

    /*
     * Waktu disimpan dalam UTC (APP_TIMEZONE=UTC) — itu benar dan tidak diubah.
     * Yang harus dikonversi adalah TAMPILANNYA: tanpa ini dokumen mencetak jam
     * UTC lalu melabelinya WIB, meleset 7 jam.
     *
     * Nama bulan dipaksa ke locale Indonesia; locale aplikasi masih 'en',
     * sedangkan seluruh isi dokumen ini berbahasa Indonesia.
     */
    $tgl = function (?string $iso, bool $denganJam = true) {
        if (! $iso) {
            return '-';
        }

        return \Illuminate\Support\Carbon::parse($iso)
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat($denganJam ? 'j F Y, H:i' : 'j F Y');
    };
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Permintaan {{ $task->nomor_invoice }}</title>
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
        .kotak { border: 1px solid #c0c7d1; padding: 10px; margin-top: 4px; }
        .ttd { margin-top: 26px; }
        .ttd .area { border: 1px solid #c0c7d1; width: 260px; height: 110px; text-align: center; }
        .ttd img { max-width: 250px; max-height: 100px; }
        .garis { border-top: 1px solid #1b1c1b; width: 260px; margin-top: 4px; padding-top: 3px; }
        ul { margin: 4px 0 0; padding-left: 14px; }
        li { margin-bottom: 2px; }
    </style>
</head>
<body>
    <div class="kepala">
        <h1>Bukti Permintaan Penawaran</h1>
        <div class="kecil">BisaBersih Kantor &middot; Serbabisa.id</div>
    </div>

    <table class="meta">
        <tr><td class="k">Nomor permintaan</td><td><strong>{{ $task->nomor_invoice }}</strong></td></tr>
        <tr><td class="k">Tanggal</td><td>{{ $tgl($task->created_at?->toIso8601String()) }}</td></tr>
        <tr><td class="k">Nama perusahaan</td><td>{{ $spek['nama_perusahaan'] ?? '-' }}</td></tr>
        <tr><td class="k">PIC</td><td>{{ $spek['nama_pic'] ?? '-' }}@if(!empty($spek['telepon_pic'])) &middot; {{ $spek['telepon_pic'] }}@endif</td></tr>
        <tr><td class="k">Alamat kantor</td><td>{{ $task->lokasi_alamat }}</td></tr>
    </table>

    <h2>Detail Kantor</h2>
    <table class="meta">
        <tr><td class="k">Jenis kantor</td><td>{{ $spek['nama_jenis'] ?? '-' }}</td></tr>
        <tr><td class="k">Luas area</td><td>{{ !empty($spek['luas_m2']) ? $spek['luas_m2'] . ' m²' : 'belum disebutkan' }}</td></tr>
        <tr><td class="k">Jumlah lantai</td><td>{{ $spek['jumlah_lantai'] ?? '-' }}</td></tr>
        <tr><td class="k">Workstation</td><td>{{ $spek['workstation'] ?? 0 }}</td></tr>
        <tr><td class="k">Ruang meeting</td><td>{{ $spek['ruang_meeting'] ?? 0 }}</td></tr>
        <tr><td class="k">Toilet</td><td>{{ $spek['toilet'] ?? 0 }}</td></tr>
        <tr><td class="k">Pantry</td><td>{{ $spek['pantry'] ?? 0 }}</td></tr>
        @if (!empty($spek['lainnya']))
            <tr><td class="k">Area lainnya</td><td>{{ $spek['lainnya'] }}</td></tr>
        @endif
        <tr><td class="k">Frekuensi layanan</td><td>{{ $spek['label_frekuensi'] ?? '-' }}</td></tr>
        @if (!empty($spek['estimasi_aplikasi']))
            <tr>
                <td class="k">Estimasi Harga</td>
                <td>{{ $rp($spek['estimasi_aplikasi']) }} per kunjungan</td>
            </tr>
        @endif
    </table>

    @if (!empty($task->catatan))
        <h2>Catatan Khusus</h2>
        <div class="kotak">{{ $task->catatan }}</div>
    @endif

    <h2>Langkah Berikutnya</h2>
    <ul>
        <li>Tim BisaBersih meninjau kebutuhan kantor Anda.</li>
        <li>PIC dihubungi lewat WhatsApp, maksimal 1 hari kerja.</li>
        <li>Survei lokasi dijadwalkan bila diperlukan.</li>
        <li>Penawaran resmi berisi tiga pilihan paket dikirim setelahnya.</li>
    </ul>

    <div class="ttd">
        <div class="kecil">Diajukan oleh,</div>
        <div class="area">
            @if ($ttdDataUrl)
                <img src="{{ $ttdDataUrl }}" alt="Tanda tangan">
            @endif
        </div>
        <div class="garis">
            <strong>{{ $spek['ditandatangani_oleh'] ?? ($spek['nama_pic'] ?? '-') }}</strong><br>
            <span class="kecil">
                {{ $spek['nama_perusahaan'] ?? '' }}
                @if (!empty($spek['ditandatangani_pada']))
                    &middot; {{ $tgl($spek['ditandatangani_pada']) }} WIB
                @endif
            </span>
        </div>
    </div>

    <div class="kecil" style="margin-top: 22px; border-top: 1px solid #c0c7d1; padding-top: 8px;">
        Dokumen ini merekam permintaan penawaran beserta tanda tangan pengaju. Dokumen ini BUKAN
        penawaran harga dan bukan kontrak; harga final ditentukan setelah tim meninjau dan, bila
        perlu, melakukan survei lokasi.
    </div>
</body>
</html>
