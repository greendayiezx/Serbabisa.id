<script setup lang="ts">
/**
 * Servis AC — halaman masuk layanan.
 *
 * Isinya: banner promo, pemilihan keluhan ("AC Anda bermasalah apa?"), kategori
 * layanan, dan alur kerjanya. Keluhan yang dipilih di sini dititipkan ke store
 * dan langsung tercentang di form berikutnya — pengguna tidak ditanya dua kali
 * tentang hal yang sama.
 */
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { useServisACStore } from '@/stores/servisAC'
import { useLocationStore } from '@/stores/location'
import { KELUHAN_AWAL, PAKET_AC } from '@/lib/servis-ac/hargaAC'
import { BIAYA_PEMERIKSAAN } from '@/lib/servis-ac/hargaFreon'
import { PROMO_AC } from '@/lib/promo/promoAC'
import PromoCuciACArt from '@/components/servis-ac/PromoCuciACArt.vue'
import { rupiah } from '@/lib/rupiah'

const router = useRouter()
const kembali = useKembali()
const acStore = useServisACStore()
const locationStore = useLocationStore()

/*
 * Banner ini menjual CUCI AC, jadi promonya dicari menurut sifatnya — promo
 * cuci berbentuk persen — bukan menurut urutan katalog. Sempat memakai
 * PROMO_AC[0], dan begitu CEKAC20 (promo pemeriksaan freon, potongan rupiah)
 * masuk sebagai entri pertama, banner cuci ikut menampilkan angka promo yang
 * bukan miliknya.
 */
const promoUtama = PROMO_AC.find((p) => p.layanan === 'cuci' && p.diskonPersen) ?? PROMO_AC[0]

/** Kota/alamat singkat dari draf lokasi — sekadar penanda, bukan pilihan baru. */
const lokasiSingkat = computed(() => {
  const alamat = locationStore.draft?.alamat ?? ''
  if (!alamat) return 'Pilih lokasi'
  const bagian = alamat.split(',').map((x) => x.trim())
  return bagian.length > 2 ? bagian[bagian.length - 3] : bagian[0]
})

const KATEGORI = [
  {
    id: 'cuci',
    nama: 'Cuci AC',
    catatan: `Mulai dari ${rupiah(PAKET_AC[0].harga)}`,
    ikon: 'cleaning_services',
    garis: 'border-(--color-azure)',
  },
  {
    /*
     * Namanya "Cek & Tambah Freon", bukan "Tambah Freon".
     *
     * AC yang kurang dingin belum tentu kekurangan freon — bisa filter kotor,
     * kapasitor lemah, atau pipa bocor. Menamainya "Tambah Freon" membuat
     * pelanggan mengira pengisian sudah pasti dibutuhkan, padahal yang dijual
     * di muka adalah pemeriksaannya.
     */
    id: 'freon',
    nama: 'Cek & Tambah Freon',
    catatan: `Pemeriksaan mulai ${rupiah(BIAYA_PEMERIKSAAN)}`,
    ikon: 'ac_unit',
    garis: 'border-(--color-lime)',
  },
  {
    id: 'perbaikan',
    nama: 'Perbaikan & Pasang',
    catatan: 'Pengecekan teknisi',
    ikon: 'build',
    garis: 'border-(--color-error)',
  },
]

const LANGKAH = [
  { ikon: 'touch_app', judul: 'Pesan', catatan: 'Pilih layanan AC' },
  { ikon: 'local_shipping', judul: 'Teknisi Datang', catatan: 'Sesuai jadwal' },
  { ikon: 'handyman', judul: 'Servis', catatan: 'Kerja profesional' },
  { ikon: 'payments', judul: 'Bayar', catatan: 'Setelah selesai' },
]

function pilihKeluhan(id: string) {
  acStore.setKeluhan(id)
}

/**
 * Hanya "Cuci AC" yang punya alur pemesanan sendiri.
 *
 * Freon dan perbaikan butuh pengecekan teknisi lebih dulu — harganya tidak bisa
 * ditentukan dari layar. Keduanya diarahkan ke jalur tugas custom alih-alih
 * dibuatkan halaman yang menjanjikan harga yang belum bisa dihitung.
 */
function bukaKategori(id: string) {
  if (id === 'cuci') {
    router.push({ name: 'servis-ac-pesan' })
    return
  }
  if (id === 'freon') {
    router.push({ name: 'servis-ac-freon' })
    return
  }

  router.push({ name: 'task-create', query: { category: 'bisatukang', service: `ac-${id}` } })
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
    <!-- Header: lokasi servis -->
    <header class="sticky top-0 z-30 bg-(--color-surface-0)/90 backdrop-blur-md border-b border-(--color-outline)/15">
      <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center justify-between gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 -ml-2 rounded-full flex items-center justify-center active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>

        <div class="flex flex-col items-center min-w-0">
          <span class="text-[10.5px] uppercase tracking-wider text-(--color-on-surface-variant)">
            Lokasi Servis
          </span>
          <button
            type="button"
            class="flex items-center gap-1 text-[13px] font-bold text-(--color-azure) max-w-[200px]"
            @click="router.push({ name: 'task-location' })"
          >
            <Icon name="pin" class="w-4 h-4 shrink-0" />
            <span class="truncate">{{ lokasiSingkat }}</span>
            <Icon name="chevron-down" class="w-3.5 h-3.5 shrink-0" />
          </button>
        </div>

        <span class="w-10 h-10"></span>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-6">
      <!-- Banner promo -->
      <section>
        <PromoCuciACArt :persen="promoUtama.diskonPersen ?? 0" />

        <!--
          Kode dan batas potongannya tetap ditulis di luar gambar: keduanya yang
          menentukan berapa sebenarnya yang dipotong, dan itu tidak boleh cuma
          terbaca oleh yang memperbesar banner.
        -->
        <p class="mt-2 px-1 text-[12px] text-(--color-on-surface-variant)">
          Kode <span class="font-bold text-(--color-on-surface)">{{ promoUtama.kode }}</span> ·
          potongan maksimal {{ rupiah(promoUtama.diskonMaks ?? 0) }}
        </p>
      </section>

      <!-- Diagnosa keluhan -->
      <section>
        <h3 class="text-[16px] font-display font-extrabold mb-3">AC Anda bermasalah apa?</h3>
        <div class="grid grid-cols-2 gap-3">
          <button
            v-for="k in KELUHAN_AWAL"
            :key="k.id"
            type="button"
            class="rounded-2xl p-4 flex flex-col items-center text-center gap-3 border-2 transition-colors"
            :class="
              acStore.keluhanAwal.includes(k.id)
                ? 'bg-(--color-primary-container)/50 border-(--color-azure)'
                : 'bg-(--color-surface-0) border-(--color-outline)/30'
            "
            :aria-pressed="acStore.keluhanAwal.includes(k.id)"
            @click="pilihKeluhan(k.id)"
          >
            <span
              class="w-14 h-14 rounded-full flex items-center justify-center"
              :class="
                acStore.keluhanAwal.includes(k.id)
                  ? 'bg-(--color-azure) text-white'
                  : 'bg-(--color-surface-container) text-(--color-on-surface-variant)'
              "
            >
              <span class="material-symbols-outlined text-[26px]" :data-icon="k.ikon">{{ k.ikon }}</span>
            </span>
            <span class="text-[13px] font-bold">{{ k.nama }}</span>
          </button>
        </div>
        <p class="mt-2 text-[11.5px] text-(--color-on-surface-variant)">
          Keluhannya langsung tercatat di form berikutnya.
        </p>
      </section>

      <!-- Kategori layanan -->
      <section>
        <div class="flex items-end justify-between mb-3">
          <h3 class="text-[16px] font-display font-extrabold">Kategori Layanan</h3>
        </div>

        <div class="flex flex-col gap-3">
          <button
            v-for="k in KATEGORI"
            :key="k.id"
            type="button"
            class="relative overflow-hidden bg-(--color-surface-0) rounded-2xl p-5 h-32 flex flex-col justify-between text-left border-l-4"
            :class="k.garis"
            @click="bukaKategori(k.id)"
          >
            <span class="relative z-10">
              <span class="block text-[17px] font-display font-extrabold">{{ k.nama }}</span>
              <span class="block text-[12.5px] text-(--color-on-surface-variant) mt-0.5">
                {{ k.catatan }}
              </span>
            </span>

            <span
              class="relative z-10 w-9 h-9 rounded-full bg-(--color-surface-container) text-(--color-azure) flex items-center justify-center"
            >
              <Icon name="arrow-right" class="w-4 h-4" />
            </span>

            <span
              class="material-symbols-outlined absolute -bottom-4 -right-4 text-[92px] text-(--color-outline)/40 rotate-12 pointer-events-none"
              :data-icon="k.ikon"
            >
              {{ k.ikon }}
            </span>
          </button>
        </div>
      </section>

      <!-- Cara kerja -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[16px] font-display font-extrabold text-center mb-5">Cara Kerja Mudah</h3>

        <ol class="flex flex-col gap-4">
          <li v-for="(l, i) in LANGKAH" :key="l.judul" class="flex items-center gap-3.5">
            <span class="relative shrink-0">
              <span
                class="w-12 h-12 rounded-full bg-(--color-primary-container) text-(--color-on-primary-container) flex items-center justify-center"
              >
                <span class="material-symbols-outlined text-[24px]" :data-icon="l.ikon">{{ l.ikon }}</span>
              </span>
              <span
                class="absolute -right-1 -top-1 w-5 h-5 rounded-full bg-(--color-lime) text-[#33430b] text-[11px] font-extrabold flex items-center justify-center"
              >
                {{ i + 1 }}
              </span>
            </span>
            <span>
              <span class="block text-[13.5px] font-bold">{{ l.judul }}</span>
              <span class="block text-[11.5px] text-(--color-on-surface-variant)">{{ l.catatan }}</span>
            </span>
          </li>
        </ol>
      </section>
    </main>

    <!-- Aksi utama -->
    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div class="max-w-[430px] mx-auto px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-lime) text-[#33430b] text-[15px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
          @click="router.push({ name: 'servis-ac-pesan' })"
        >
          <Icon name="sparkle" class="w-5 h-5" />
          Pesan Servis AC Sekarang
        </button>
      </div>
    </footer>
  </div>
</template>
