<script setup lang="ts">
/**
 * Servis AC — halaman masuk layanan.
 *
 * Isinya: hero layanan, banner promo, kategori layanan, dan alur kerjanya. Keluhannya
 * ditanyakan di form pemesanan masing-masing layanan, bukan di sini: yang
 * relevan berbeda antara cuci AC dan pemeriksaan freon, dan satu daftar di
 * halaman masuk hanya cocok untuk salah satunya.
 */
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { useLocationStore } from '@/stores/location'
import { PAKET_AC } from '@/lib/servis-ac/hargaAC'
import { BIAYA_PEMERIKSAAN } from '@/lib/servis-ac/hargaFreon'
import { PROMO_AC } from '@/lib/promo/promoAC'
import HeroCuciACArt from '@/components/servis-ac/HeroCuciACArt.vue'
import PromoCuciACArt from '@/components/servis-ac/PromoCuciACArt.vue'
import { rupiah } from '@/lib/rupiah'

const router = useRouter()
const kembali = useKembali()
const locationStore = useLocationStore()

/*
 * Banner ini menjual CUCI AC, jadi promonya dicari menurut sifatnya — promo
 * cuci berbentuk persen — bukan menurut urutan katalog. Sempat memakai
 * PROMO_AC[0], dan begitu CEKAC20 (promo pemeriksaan freon, potongan rupiah)
 * masuk sebagai entri pertama, banner cuci ikut menampilkan angka promo yang
 * bukan miliknya.
 */
const promoUtama = PROMO_AC.find((p) => p.layanan === 'cuci' && p.diskonPersen) ?? PROMO_AC[0]

/*
 * Label podium hero diambil dari katalog paket yang benar-benar dijual, bukan
 * ditulis lepas di gambar. Podium tengah memajang paket menengah sebagai yang
 * disarankan, dan podium kanan menunjuk layanan pemeriksaan freon.
 */
const labelPodium = {
  kiri: PAKET_AC[0].nama,
  tengah: PAKET_AC[1].nama,
  kanan: 'Cek Freon',
}

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
    warnaIkon: 'text-(--color-azure)/20',
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
    warnaIkon: 'text-[#8BC53F]/30',
    garis: 'border-(--color-lime)',
  },
  {
    id: 'perbaikan',
    nama: 'Perbaikan & Pasang',
    catatan: 'Pengecekan teknisi',
    ikon: 'build',
    warnaIkon: 'text-(--color-error)/20',
    garis: 'border-(--color-error)',
  },
]

const LANGKAH = [
  { ikon: 'touch_app', judul: 'Pesan', catatan: 'Pilih layanan AC' },
  { ikon: 'local_shipping', judul: 'Teknisi Datang', catatan: 'Sesuai jadwal' },
  { ikon: 'handyman', judul: 'Servis', catatan: 'Kerja profesional' },
  { ikon: 'payments', judul: 'Bayar', catatan: 'Setelah selesai' },
]

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
  <div class="relative min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
    <!--
      Hero selebar layar, jadi navbarnya dilepas. Tombol kembali dan pemilih
      lokasi melayang di atasnya — menempel pada HALAMAN, bukan pada layar:
      dengan posisi tetap (fixed) keduanya ikut turun saat digulung dan
      berakhir menutupi isi di bawahnya.
    -->
    <div class="absolute top-4 inset-x-4 z-50 flex items-center justify-between gap-2">
      <button
        type="button"
        aria-label="Kembali"
        class="w-10 h-10 shrink-0 rounded-full bg-white/90 dark:bg-[#1b2126]/90 text-slate-800 dark:text-white flex items-center justify-center shadow-md active:scale-95 transition-transform"
        @click="kembali"
      >
        <Icon name="arrow-left" class="w-5 h-5" />
      </button>

      <button
        type="button"
        class="min-w-0 inline-flex items-center gap-1 max-w-[62%] px-3 py-1.5 rounded-full bg-white/90 dark:bg-[#1b2126]/90 text-(--color-azure) text-[11.5px] font-bold shadow-md backdrop-blur-xs active:scale-95 transition-transform"
        @click="router.push({ name: 'task-location' })"
      >
        <Icon name="pin" class="w-3.5 h-3.5 shrink-0" />
        <span class="truncate">{{ lokasiSingkat }}</span>
        <Icon name="chevron-down" class="w-3 h-3 shrink-0" />
      </button>
    </div>

    <!-- Hero layanan: menyentuh tepi kiri-kanan dan tepi atas layar -->
    <HeroCuciACArt
      penuh
      :label-kiri="labelPodium.kiri"
      :label-tengah="labelPodium.tengah"
      :label-kanan="labelPodium.kanan"
    />

    <main class="max-w-[430px] mx-auto px-4 pt-6 flex flex-col gap-6">
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

            <Icon name="arrow-right" class="relative z-10 w-5 h-5 text-(--color-azure)" />

            <!-- Watermark Icon transparan di pojok kanan bawah -->
            <div
              class="absolute -bottom-3 -right-3 pointer-events-none select-none transition-opacity"
              :class="k.warnaIkon"
            >
              <svg v-if="k.id === 'cuci'" viewBox="0 0 24 24" class="w-28 h-28 stroke-[1.6] stroke-current fill-none rotate-12">
                <path d="M12 3v4M8 7h8v3a4 4 0 0 1-8 0V7zM4 14h16a1 1 0 0 1 1 1v2a3 3 0 0 1-3 3H6a3 3 0 0 1-3-3v-2a1 1 0 0 1 1-1z" />
                <path d="M7 14v4M12 14v4M17 14v4" />
              </svg>
              <svg v-else-if="k.id === 'freon'" viewBox="0 0 24 24" class="w-28 h-28 stroke-[1.6] stroke-current fill-none rotate-12">
                <path d="M12 2v20M2 12h20M4.93 4.93l14.14 14.14M4.93 19.07l14.14-14.14M12 6l-2.5-2.5M14.5 3.5L12 6M12 18l-2.5 2.5M14.5 20.5L12 18M6 12l-2.5-2.5M3.5 14.5L6 12M18 12l2.5-2.5M20.5 14.5L18 12" />
              </svg>
              <svg v-else-if="k.id === 'perbaikan'" viewBox="0 0 24 24" class="w-28 h-28 stroke-[1.6] stroke-current fill-none rotate-12">
                <path d="M14.7 3.3a1 1 0 0 0-1.4 0l-4.7 4.7a6 6 0 0 0-7.1 8.6 1 1 0 0 0 1.4 0l3.3-3.3 2.8 2.8-3.3 3.3a1 1 0 0 0 0 1.4 6 6 0 0 0 8.6-7.1l4.7-4.7a1 1 0 0 0 0-1.4l-4.3-4.3z" />
              </svg>
              <span
                v-else
                class="material-symbols-outlined text-[100px] leading-none block rotate-12"
                :data-icon="k.ikon"
              >
                {{ k.ikon }}
              </span>
            </div>
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
