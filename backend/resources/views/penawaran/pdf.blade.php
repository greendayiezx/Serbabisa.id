{{--
    Dokumen penawaran untuk diunduh.

    Sengaja memakai CSS sederhana: dompdf tidak mendukung flexbox/grid modern,
    jadi tata letaknya bertumpu pada tabel dan blok biasa. Angkanya diformat di
    sini agar PDF dan layar menampilkan hal yang sama persis.
--}}
@php
    $rp = fn ($n) => 'Rp' . number_format((float) $n, 0, ',', '.');
    /*
     * Waktu disimpan UTC; yang dikonversi adalah tampilannya. Tanpa ini,
     * tanggal yang dibuat menjelang tengah malam WIB tercetak mundur sehari.
     * Nama bulan dipaksa ke locale Indonesia — locale aplikasi masih 'en'.
     */
    $tgl = function (?string $iso) {
        if (! $iso) return '-';

        return \Illuminate\Support\Carbon::parse($iso)
            ->timezone('Asia/Jakarta')
            ->locale('id')
            ->translatedFormat('j F Y');
    };
    $dipilih = collect($p['paket'])->firstWhere('id', $p['paket_dipilih_id']);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Penawaran {{ $p['nomor'] }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 10.5px; color: #1b1c1b; margin: 0; }
        h1 { font-size: 20px; margin: 0 0 2px; color: #004a78; }
        h2 { font-size: 12.5px; margin: 18px 0 6px; color: #004a78; }
        .kepala { border-bottom: 2px solid #1E9BF0; padding-bottom: 10px; margin-bottom: 14px; }
        .kecil { font-size: 9.5px; color: #414750; }
        table { width: 100%; border-collapse: collapse; }
        .meta td { padding: 2px 0; vertical-align: top; }
        .meta td.k { color: #414750; width: 130px; }
        .scope th, .scope td { border: 1px solid #c0c7d1; padding: 6px 8px; text-align: left; }
        .scope th { background: #f0edec; font-size: 9.5px; text-transform: uppercase; letter-spacing: .4px; }
        .paket { margin-top: 6px; }
        .paket td { border: 1px solid #c0c7d1; padding: 8px; vertical-align: top; width: 33.33%; }
        .paket .nama { font-size: 12px; font-weight: bold; }
        .paket .harga { font-size: 14px; font-weight: bold; color: #004a78; margin: 4px 0 2px; }
        .paket ul { margin: 6px 0 0; padding-left: 14px; }
        .paket li { margin-bottom: 2px; }
        .disarankan { background: #eaf5ff; }
        .lencana { display: inline-block; background: #FFD600; color: #3f3000; font-size: 8px;
                   font-weight: bold; padding: 1px 5px; border-radius: 6px; }
        ul.polos { margin: 4px 0 0; padding-left: 14px; }
        ul.polos li { margin-bottom: 3px; }
        .catatan { margin-top: 16px; border-top: 1px solid #c0c7d1; padding-top: 8px; }
    </style>
</head>
<body>
    <div class="kepala">
        <h1>Penawaran Layanan Kebersihan Kantor</h1>
        <div class="kecil">BisaBersih &middot; Serbabisa.id</div>
    </div>

    <table class="meta">
        <tr><td class="k">Nomor penawaran</td><td><strong>{{ $p['nomor'] }}</strong></td></tr>
        <tr><td class="k">Nama perusahaan</td><td>{{ $p['nama_perusahaan'] }}</td></tr>
        <tr><td class="k">PIC</td><td>{{ $p['nama_pic'] ?: '-' }}@if($p['telepon_pic']) &middot; {{ $p['telepon_pic'] }}@endif</td></tr>
        <tr><td class="k">Alamat lokasi</td><td>{{ $p['alamat'] }}</td></tr>
        <tr><td class="k">Tanggal penawaran</td><td>{{ $tgl($p['tanggal']) }}</td></tr>
        <tr><td class="k">Berlaku sampai</td><td>{{ $tgl($p['berlaku_sampai']) }}</td></tr>
    </table>

    <h2>Ringkasan Kebutuhan</h2>
    <div>{{ $p['ringkasan'] }}</div>

    <h2>Scope of Work</h2>
    <table class="scope">
        <tr><th>Area</th><th>Pekerjaan</th><th>Frekuensi</th></tr>
        @foreach ($p['scope'] as $s)
            <tr><td>{{ $s['area'] }}</td><td>{{ $s['pekerjaan'] }}</td><td>{{ $s['frekuensi'] }}</td></tr>
        @endforeach
    </table>

    <h2>Pilihan Paket</h2>
    <table class="paket">
        <tr>
            @foreach ($p['paket'] as $k)
                <td class="{{ $k['disarankan'] ? 'disarankan' : '' }}">
                    <div class="nama">{{ $k['nama'] }}
                        @if ($k['disarankan'])<span class="lencana">DISARANKAN</span>@endif
                    </div>
                    <div class="harga">{{ $rp($k['harga_bulanan']) }}<span class="kecil"> /bulan</span></div>
                    <div class="kecil">
                        {{ $rp($k['harga_per_kunjungan']) }} &times; {{ $k['kunjungan_per_bulan'] }} kunjungan
                    </div>
                    <ul>@foreach ($k['isi'] as $i)<li>{{ $i }}</li>@endforeach</ul>
                </td>
            @endforeach
        </tr>
    </table>

    @if ($dipilih)
        <div class="catatan">
            <strong>Paket disetujui: {{ $dipilih['nama'] }}</strong> &mdash;
            {{ $rp($dipilih['harga_bulanan']) }} per bulan,
            disetujui {{ $tgl($p['disetujui_pada']) }}.
        </div>
    @endif

    <h2>Biaya Tambahan</h2>
    <ul class="polos">@foreach ($p['biaya_tambahan'] as $b)<li>{{ $b }}</li>@endforeach</ul>

    <h2>Pengecualian Layanan</h2>
    <ul class="polos">@foreach ($p['pengecualian'] as $x)<li>{{ $x }}</li>@endforeach</ul>

    <div class="catatan kecil">
        Harga berlaku sampai {{ $tgl($p['berlaku_sampai']) }}. Perubahan luas area, jumlah lantai,
        atau frekuensi layanan dapat mengubah harga dan akan dikonfirmasi ulang sebelum kontrak dibuat.
    </div>
</body>
</html>
