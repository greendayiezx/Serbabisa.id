<script setup lang="ts">
/**
 * Lembar invoice untuk dicetak / disimpan sebagai PDF.
 *
 * Di layar komponen ini disembunyikan sepenuhnya; ia hanya muncul saat dialog
 * cetak dibuka. Pendekatannya memakai `window.print()` dan aturan `@media
 * print`, bukan pustaka pembuat PDF, karena:
 *   - tidak menambah dependensi (jsPDF & sejenisnya ratusan KB),
 *   - hasilnya teks sungguhan yang bisa dicari & disalin, bukan gambar,
 *   - "Simpan sebagai PDF" sudah tersedia di dialog cetak semua browser modern.
 */
import { formatTanggalInvoice } from '@/lib/invoice'
import type { Pesanan } from '@/stores/pesanan'

defineProps<{ pesanan: Pesanan }>()

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}
</script>

<template>
  <section class="invoice-cetak" aria-hidden="true">
    <!-- Kepala -->
    <header class="ic-kepala">
      <div>
        <p class="ic-merek">Serbabisa<span>.id</span></p>
        <p class="ic-layanan">BisaBelanja &mdash; Jasa Titip Belanja</p>
      </div>
      <div class="ic-kanan">
        <p class="ic-label">INVOICE</p>
        <p class="ic-nomor">{{ pesanan.invoice }}</p>
      </div>
    </header>

    <!-- Meta -->
    <div class="ic-meta">
      <div>
        <p class="ic-label">Ditagihkan kepada</p>
        <p class="ic-tebal">{{ pesanan.penerima }}</p>
        <p v-if="pesanan.telepon">{{ pesanan.telepon }}</p>
        <p class="ic-alamat">{{ pesanan.alamat }}</p>
      </div>
      <div>
        <p class="ic-label">Tanggal</p>
        <p>{{ formatTanggalInvoice(pesanan.dibuatPada) }}</p>
        <p class="ic-label ic-spasi">Metode Pembayaran</p>
        <p>{{ pesanan.metodePembayaran }}</p>
        <p class="ic-label ic-spasi">Toko</p>
        <p>{{ pesanan.toko || '-' }}</p>
      </div>
    </div>

    <!-- Rincian barang -->
    <table class="ic-tabel">
      <thead>
        <tr>
          <th class="ic-kiri">Barang</th>
          <th class="ic-tengah">Qty</th>
          <th class="ic-kanan-sel">Harga</th>
          <th class="ic-kanan-sel">Jumlah</th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="item in pesanan.items" :key="item.id">
          <td>
            {{ item.nama }}
            <span v-if="item.catatan" class="ic-catatan">{{ item.catatan }}</span>
          </td>
          <td class="ic-tengah">{{ item.qty }}</td>
          <td class="ic-kanan-sel">{{ item.harga != null ? rp(item.harga) : '—' }}</td>
          <td class="ic-kanan-sel">{{ item.harga != null ? rp(item.harga * item.qty) : '—' }}</td>
        </tr>
      </tbody>
    </table>

    <!-- Ringkasan biaya -->
    <div class="ic-ringkas">
      <div class="ic-baris">
        <span>Total Harga Barang</span><span>{{ rp(pesanan.biaya.totalBarang) }}</span>
      </div>
      <div v-if="pesanan.biaya.potongan" class="ic-baris">
        <span>Potongan Promo</span><span>&minus;{{ rp(pesanan.biaya.potongan) }}</span>
      </div>
      <div class="ic-baris">
        <span>Biaya Layanan</span><span>{{ rp(pesanan.biaya.biayaLayanan) }}</span>
      </div>
      <div class="ic-baris">
        <span>Ongkos Kirim</span>
        <span>
          <template v-if="pesanan.biaya.ongkirGratis">
            <s>{{ rp(pesanan.biaya.ongkirNormal) }}</s> Gratis
          </template>
          <template v-else>{{ rp(pesanan.biaya.ongkir) }}</template>
        </span>
      </div>
      <div class="ic-baris ic-total">
        <span>Total Dibayar</span><span>{{ rp(pesanan.biaya.totalTagihan) }}</span>
      </div>
      <p v-if="pesanan.biaya.cashback" class="ic-cashback">
        Cashback {{ rp(pesanan.biaya.cashback) }} masuk ke saldo untuk transaksi berikutnya.
      </p>
    </div>

    <p v-if="pesanan.catatan" class="ic-instruksi">
      <strong>Catatan:</strong> {{ pesanan.catatan }}
    </p>

    <footer class="ic-kaki">
      <p>Terima kasih sudah belanja di Serbabisa.id</p>
      <p class="ic-halus">
        Dokumen ini dibuat otomatis dan sah tanpa tanda tangan. Nomor invoice memuat karakter
        pemeriksa, jadi kesalahan ketik satu karakter akan terdeteksi.
      </p>
    </footer>
  </section>
</template>

<style scoped>
/* Di layar lembar ini tidak pernah tampil — hanya untuk media cetak. */
.invoice-cetak {
  display: none;
}

@media print {
  .invoice-cetak {
    display: block;
    color: #111;
    font-family: Arial, Helvetica, sans-serif;
    font-size: 11pt;
    line-height: 1.45;
  }

  .ic-kepala {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    border-bottom: 2px solid #111;
    padding-bottom: 10pt;
    margin-bottom: 14pt;
  }
  .ic-merek {
    font-size: 20pt;
    font-weight: 800;
    letter-spacing: -0.4pt;
  }
  .ic-merek span {
    color: #63c21c;
  }
  .ic-layanan {
    font-size: 9.5pt;
    color: #444;
  }
  .ic-kanan {
    text-align: right;
  }
  .ic-nomor {
    font-size: 12pt;
    font-weight: 700;
    letter-spacing: 0.6pt;
  }

  .ic-label {
    font-size: 8pt;
    text-transform: uppercase;
    letter-spacing: 0.6pt;
    color: #666;
  }
  .ic-spasi {
    margin-top: 7pt;
  }
  .ic-tebal {
    font-weight: 700;
  }
  .ic-alamat {
    max-width: 62mm;
  }

  .ic-meta {
    display: flex;
    justify-content: space-between;
    gap: 16pt;
    margin-bottom: 16pt;
  }
  .ic-meta > div:last-child {
    text-align: right;
  }

  .ic-tabel {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 14pt;
  }
  .ic-tabel th {
    font-size: 8.5pt;
    text-transform: uppercase;
    letter-spacing: 0.5pt;
    color: #555;
    border-bottom: 1pt solid #999;
    padding: 4pt 0;
  }
  .ic-tabel td {
    padding: 5pt 0;
    border-bottom: 0.5pt solid #ddd;
    vertical-align: top;
  }
  .ic-kiri {
    text-align: left;
  }
  .ic-tengah {
    text-align: center;
    width: 12mm;
  }
  .ic-kanan-sel {
    text-align: right;
    width: 26mm;
  }
  .ic-catatan {
    display: block;
    font-size: 9pt;
    color: #666;
  }

  .ic-ringkas {
    margin-left: auto;
    width: 78mm;
  }
  .ic-baris {
    display: flex;
    justify-content: space-between;
    padding: 3pt 0;
  }
  .ic-total {
    border-top: 1.5pt solid #111;
    margin-top: 4pt;
    padding-top: 6pt;
    font-size: 13pt;
    font-weight: 800;
  }
  .ic-cashback {
    font-size: 9pt;
    color: #3e8f12;
    text-align: right;
    margin-top: 4pt;
  }

  .ic-instruksi {
    margin-top: 14pt;
    font-size: 10pt;
  }

  .ic-kaki {
    margin-top: 22pt;
    padding-top: 8pt;
    border-top: 0.5pt solid #ccc;
    font-size: 9.5pt;
  }
  .ic-halus {
    font-size: 8pt;
    color: #777;
    margin-top: 3pt;
  }

  /* Baris barang jangan terpotong di tengah antar halaman. */
  .ic-tabel tr,
  .ic-ringkas {
    break-inside: avoid;
  }
}
</style>
