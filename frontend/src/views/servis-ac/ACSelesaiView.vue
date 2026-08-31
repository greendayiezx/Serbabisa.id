<script setup lang="ts">
/**
 * Servis AC — pesanan terkirim.
 *
 * Mockup halaman ini terpotong saat dikirim, jadi susunannya mengikuti pola
 * konfirmasi yang sudah dipakai layanan lain di aplikasi ini: penanda berhasil,
 * nomor pesanan, ringkasan yang benar-benar tercatat di server, dan langkah
 * berikutnya.
 *
 * Datanya dibaca ULANG dari server, bukan dari draf yang tadi dikirim: yang
 * ditampilkan di sini harus yang tersimpan, termasuk kalau server menolak
 * promonya dan menagih harga penuh.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import PemuatBerputar from '@/components/ui/PemuatBerputar.vue'
import PesananTerkirimArt from '@/components/servis-ac/PesananTerkirimArt.vue'
import apiClient from '@/api/client'
import { rupiah } from '@/lib/rupiah'
import { tautanInvoicePdf } from '@/api/invoice'
import { labelMetode } from '@/lib/metodeBayar'
import { KAPASITAS_AC, KONDISI_AC, RUTIN_AC, TIPE_AC } from '@/lib/servis-ac/hargaAC'

interface BarisPesanan {
  nama: string
  qty: number
  satuan: string | null
  subtotal: number
}

interface PesananUmum {
  nomor: string | null
  task_id: number
  judul: string
  deskripsi: string | null
  status: string
  detail_layanan: Record<string, unknown>
  dijadwalkan_pada: string | null
  catatan: string | null
  lokasi: { alamat: string | null }
  items: BarisPesanan[]
  total: number
  potongan: number
  metode: string | null
}

const route = useRoute()
const router = useRouter()
const nomor = String(route.params.nomor ?? '')

const pesanan = ref<PesananUmum | null>(null)
const memuat = ref(true)
const galat = ref<string | null>(null)

onMounted(async () => {
  try {
    const { data } = await apiClient.get<PesananUmum>(`/pesanan/${encodeURIComponent(nomor)}`)
    pesanan.value = data
  } catch {
    galat.value = 'Pesanan tidak ditemukan atau gagal dimuat.'
  } finally {
    memuat.value = false
  }

  void siapkanInvoice()
})

/* ---------------- Invoice PDF ---------------- */

/**
 * Tautan bertanda tangan disiapkan lebih dulu lalu dipasang pada <a> sungguhan:
 * membuka tab dari JavaScript setelah menunggu jaringan kehilangan "gerakan
 * pengguna" yang dibutuhkan peramban, dan tabnya diblokir.
 */
const tautanInvoice = ref<string | null>(null)

async function siapkanInvoice() {
  try {
    tautanInvoice.value = await tautanInvoicePdf(nomor)
  } catch {
    tautanInvoice.value = null
  }
}

/* ---------------- Tampilan ---------------- */
const detail = computed(() => (pesanan.value?.detail_layanan ?? {}) as Record<string, unknown>)

const namaTipe = computed(
  () => TIPE_AC.find((t) => t.id === detail.value.tipe)?.nama ?? String(detail.value.tipe ?? '-'),
)

const namaKapasitas = computed(
  () =>
    KAPASITAS_AC.find((k) => k.id === detail.value.kapasitas)?.nama ??
    String(detail.value.kapasitas ?? '-'),
)

const daftarKondisi = computed(() => {
  const isi = (detail.value.kondisi as string[] | undefined) ?? []
  return isi.map((k) => KONDISI_AC.find((x) => x.id === k)?.nama ?? k)
})

const jadwalRutin = computed(() => {
  const r = detail.value.rutin as string | null | undefined
  if (!r) return null
  return RUTIN_AC.find((x) => x.id === r)?.nama ?? r
})

const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

/**
 * Jadwal dibaca di zona WIB. Server menyimpan UTC; tanpa konversi ini, jadwal
 * pagi bisa tercetak sebagai hari sebelumnya.
 */
const jadwalTeks = computed(() => {
  const iso = pesanan.value?.dijadwalkan_pada
  if (!iso) return '-'

  const bagian = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hour12: false,
  }).formatToParts(new Date(iso))
  const ambil = (t: string) => bagian.find((b) => b.type === t)?.value ?? ''

  return `${ambil('day')} ${BULAN[Number(ambil('month')) - 1]} ${ambil('year')}, ${ambil('hour')}:${ambil('minute')} WIB`
})

const LANGKAH = [
  { ikon: 'search', judul: 'Mencari teknisi', catatan: 'Kami carikan teknisi terdekat yang cocok jadwalnya.' },
  { ikon: 'local_shipping', judul: 'Teknisi menuju lokasi', catatan: 'Anda dapat pemberitahuan sebelum ia berangkat.' },
  { ikon: 'handyman', judul: 'Servis dikerjakan', catatan: 'Sekitar 45–60 menit untuk tiap unit.' },
  { ikon: 'payments', judul: 'Bayar setelah selesai', catatan: 'Pembayaran dilakukan setelah pekerjaan beres.' },
]
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
    <main class="max-w-[430px] mx-auto px-4 pt-6 flex flex-col gap-3.5">
      <PemuatBerputar v-if="memuat" label="Memuat pesanan…" />

      <div v-else-if="galat" class="text-center py-16">
        <Icon name="alert" class="w-9 h-9 mx-auto text-(--color-on-surface-variant) mb-2" />
        <p class="text-[13px] text-(--color-on-surface-variant)">{{ galat }}</p>
        <button
          type="button"
          class="mt-4 px-5 py-2.5 rounded-full bg-(--color-azure) text-white text-[13px] font-bold"
          @click="router.replace({ name: 'task-list' })"
        >
          Ke Tugas Saya
        </button>
      </div>

      <template v-else-if="pesanan">
        <!-- Penanda berhasil -->
        <section class="bg-(--color-surface-0) rounded-2xl p-6 text-center">
          <PesananTerkirimArt />

          <h1 class="mt-4 text-[19px] font-display font-extrabold">Pesanan servis terkirim</h1>
          <p class="mt-1.5 text-[12.5px] leading-relaxed text-(--color-on-surface-variant)">
            Kami sedang mencarikan teknisi untuk jadwal yang Anda pilih.
          </p>

          <p class="mt-4 text-[11.5px] text-(--color-on-surface-variant)">Nomor pesanan</p>
          <p class="text-[15px] font-extrabold tracking-wide">{{ pesanan.nomor }}</p>
        </section>

        <!-- Ringkasan servis -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <h2 class="text-[14px] font-display font-extrabold mb-3">Ringkasan Servis</h2>

          <dl class="flex flex-col gap-2.5 text-[12.5px]">
            <div class="flex justify-between gap-3">
              <dt class="text-(--color-on-surface-variant)">Layanan</dt>
              <dd class="font-bold text-right">{{ detail.nama_paket ?? pesanan.judul }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-(--color-on-surface-variant)">Jumlah unit</dt>
              <dd class="font-bold">{{ detail.unit }} unit</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-(--color-on-surface-variant)">Tipe &amp; kapasitas</dt>
              <dd class="font-bold text-right">{{ namaTipe }} · {{ namaKapasitas }}</dd>
            </div>
            <div v-if="daftarKondisi.length" class="flex justify-between gap-3">
              <dt class="text-(--color-on-surface-variant) shrink-0">Keluhan</dt>
              <dd class="font-bold text-right">{{ daftarKondisi.join(', ') }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-(--color-on-surface-variant) shrink-0">Jadwal</dt>
              <dd class="font-bold text-right">{{ jadwalTeks }}</dd>
            </div>
            <div class="flex justify-between gap-3">
              <dt class="text-(--color-on-surface-variant) shrink-0">Lokasi</dt>
              <dd class="font-bold text-right leading-snug">{{ pesanan.lokasi.alamat }}</dd>
            </div>
            <div v-if="jadwalRutin" class="flex justify-between gap-3">
              <dt class="text-(--color-on-surface-variant)">Jadwal rutin</dt>
              <dd class="font-bold">{{ jadwalRutin }}</dd>
            </div>
            <div v-if="pesanan.catatan" class="flex justify-between gap-3">
              <dt class="text-(--color-on-surface-variant) shrink-0">Catatan</dt>
              <dd class="font-bold text-right leading-snug">{{ pesanan.catatan }}</dd>
            </div>
          </dl>
        </section>

        <!-- Rincian tagihan -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <div class="flex items-center justify-between gap-3 mb-3">
            <h2 class="text-[14px] font-display font-extrabold">Rincian Tagihan</h2>
            <!--
              <a> sungguhan dengan tautan yang sudah disiapkan: peramban ponsel
              memblokir tab yang dibuka di luar gerakan pengguna.
            -->
            <a
              v-if="tautanInvoice"
              :href="tautanInvoice"
              target="_blank"
              rel="noopener"
              class="text-[12px] font-bold text-(--color-azure)"
            >
              Lihat PDF
            </a>
          </div>

          <div class="flex flex-col gap-2 text-[12.5px]">
            <div
              v-for="(i, idx) in pesanan.items"
              :key="idx"
              class="flex justify-between gap-3 text-(--color-on-surface-variant)"
            >
              <span>{{ i.nama }}<template v-if="i.qty > 1"> × {{ i.qty }}</template></span>
              <span class="font-bold text-(--color-on-surface) whitespace-nowrap">
                {{ rupiah(i.subtotal) }}
              </span>
            </div>

            <div v-if="pesanan.potongan" class="flex justify-between gap-3">
              <span class="text-(--color-on-surface-variant)">Potongan</span>
              <span class="font-bold text-(--color-error) whitespace-nowrap">
                &minus;{{ rupiah(pesanan.potongan) }}
              </span>
            </div>

            <div v-if="pesanan.metode" class="flex justify-between gap-3 text-(--color-on-surface-variant)">
              <span>Metode Pembayaran</span>
              <span class="font-bold text-(--color-on-surface)">
                {{ labelMetode(pesanan.metode) }}
              </span>
            </div>

            <div class="flex justify-between gap-3 pt-2.5 mt-1 border-t border-(--color-outline)/12">
              <span class="text-[13px] font-extrabold">Total</span>
              <span class="text-[15px] font-extrabold">{{ rupiah(pesanan.total) }}</span>
            </div>
          </div>

          <p class="mt-3 text-[11px] text-(--color-on-surface-variant)">
            Dibayar setelah pekerjaan selesai
          </p>
        </section>

        <!-- Langkah berikutnya -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <h2 class="text-[14px] font-display font-extrabold mb-4">Langkah Selanjutnya</h2>

          <ol class="flex flex-col gap-4">
            <li v-for="(l, i) in LANGKAH" :key="l.judul" class="flex items-start gap-3.5">
              <span
                class="w-9 h-9 shrink-0 rounded-full flex items-center justify-center"
                :class="
                  i === 0
                    ? 'bg-(--color-azure) text-white'
                    : 'bg-(--color-surface-container) text-(--color-on-surface-variant)'
                "
              >
                <span class="material-symbols-outlined text-[18px]" :data-icon="l.ikon">{{ l.ikon }}</span>
              </span>
              <span class="min-w-0">
                <span class="block text-[13px] font-bold">{{ l.judul }}</span>
                <span class="block text-[11.5px] leading-snug text-(--color-on-surface-variant)">
                  {{ l.catatan }}
                </span>
              </span>
            </li>
          </ol>
        </section>
      </template>
    </main>

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div
        class="max-w-[430px] mx-auto px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] flex items-center gap-3"
      >
        <button
          type="button"
          class="flex-1 h-12 rounded-full border border-(--color-outline)/40 text-[13.5px] font-bold active:scale-95 transition-transform"
          @click="router.replace({ name: 'home' })"
        >
          Beranda
        </button>
        <button
          type="button"
          class="flex-1 h-12 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold active:scale-95 transition-transform"
          @click="router.replace({ name: 'task-list' })"
        >
          Lihat Pesanan
        </button>
      </div>
    </footer>
  </div>
</template>
