<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useSkeleton } from '@/composables/useSkeleton'
import Skeleton from '@/components/ui/Skeleton.vue'
import Icon from '@/components/icons/Icon.vue'
import BisaBersihNavbar from '@/components/bersih/BisaBersihNavbar.vue'
import { LANGGANAN_BERSIH, hematPersen } from '@/lib/promo/promoBersih'
import promoBisaBersih from '@/assets/PromoBisaBersih.svg'
import promoGratisKunjungan from '@/assets/Promo Gratis biaya kunjungan.svg'
import promoOffice100 from '@/assets/PromoOffice100.svg'
import promoACHemat20 from '@/assets/PromoACHEMAT20.svg'
import promoDeep100 from '@/assets/PromoDeep100.svg'
import promoBersihBaru from '@/assets/PromoBersihBaru.svg'
import imgBisaKinclong from '@/assets/BisaKinclong.png'
import imgFreshHome from '@/assets/FreshHome.jpg'

const router = useRouter()

/**
 * Angka pada chip "Hemat sampai …%" dihitung, bukan ditulis tangan.
 *
 * Chip ini sekarang menautkan ke halaman paket, jadi angkanya wajib sama dengan
 * yang tampil di sana. Versi sebelumnya menulis "Save up to 20%" sementara kartu
 * di bawahnya menulis 40%, dan keduanya meleset dari tabel harganya sendiri.
 */
const hematMaks = computed(() => Math.max(...LANGGANAN_BERSIH.map((p) => hematPersen(p))))

function keLangganan() {
  router.push({ name: 'task-bersih-langganan' })
}

type ServiceId = 'house' | 'office' | 'deep' | 'ac' | 'disinfect'

interface Service {
  id: ServiceId
  label: string
  desc: string
}

const services: Service[] = [
  { id: 'house', label: 'Bersih Rumah', desc: 'Rumah & peralatan bersih' },
  { id: 'office', label: 'Bersih Kantor', desc: 'Ruang kerja rapi & bersih' },
  { id: 'deep', label: 'Deep Cleaning', desc: 'Pembersihan menyeluruh' },
  { id: 'ac', label: 'Servis AC', desc: 'Cuci & perawatan AC' },
  { id: 'disinfect', label: 'Disinfektan', desc: 'Area sering disentuh' },
]

const selectedService = ref<string | null>(null)

/**
 * Isi bundling & paket langganan di bawah masih statis.
 *
 * BisaBelanja sudah punya sumbernya (lib/promo.ts + tabel promos /
 * subscription_plans), tapi BisaBersih belum — layanannya belum punya katalog
 * harga di backend. Ditulis deklaratif supaya nanti tinggal ditukar sumbernya
 * tanpa menyentuh template.
 *
 * Promo Hari Ini sendiri tidak lagi memakai data: dua banner-nya adalah
 * ilustrasi utuh, jadi tulisannya hidup di dalam SVG masing-masing.
 */
interface Bundling {
  id: string
  ikon: string
  nama: string
  isi: string
  harga: number
  hargaCoret: number
  latar: string
  warnaIkon: string
  foto?: string
  posisiFoto?: string
}

const bundling: Bundling[] = [
  {
    id: 'kinclong-total',
    ikon: 'sparkle',
    nama: 'Kinclong Total',
    isi: 'General Cleaning + Cuci Karpet',
    harga: 420000,
    hargaCoret: 500000,
    latar: 'bg-(--color-azure)/10',
    warnaIkon: 'text-(--color-azure)',
    foto: imgBisaKinclong,
    posisiFoto: 'object-top',
  },
  {
    id: 'fresh-home',
    ikon: 'layers',
    nama: 'Fresh Home',
    isi: 'Deep Clean + Poles Lantai',
    harga: 550000,
    hargaCoret: 650000,
    latar: 'bg-(--color-lime)/15',
    warnaIkon: 'text-(--color-on-secondary-container)',
    foto: imgFreshHome,
    posisiFoto: 'object-top',
  },
]


function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

function handleServiceSelect(id: string) {
  selectedService.value = id
}

function handleLanjut() {
  if (!selectedService.value) return

  if (selectedService.value === 'house') {
    router.push({ name: 'task-bersih-rumah' })
    return
  }
  if (selectedService.value === 'office') {
    router.push({ name: 'task-bersih-kantor' })
    return
  }
  if (selectedService.value === 'deep') {
    router.push({ name: 'task-bersih-deep' })
    return
  }
  if (selectedService.value === 'ac') {
    router.push({ name: 'servis-ac' })
    return
  }
  if (selectedService.value === 'disinfect') {
    router.push({ name: 'task-bersih-disinfektan' })
    return
  }

  /*
   * Tidak ada lagi jalur cadangan ke tugas custom: kelima layanan di daftar
   * punya halamannya sendiri. Jalur itu dulu hanya menampung Setrika, dan
   * membiarkannya berarti layanan yang lupa disambungkan akan diam-diam
   * berakhir sebagai formulir tugas kosong, bukan sebagai galat yang terlihat.
   */
}

/**
 * Skeleton halaman: digambar di frame pertama, lalu konten asli menyusul di
 * frame berikutnya. Dua rAF dipakai supaya skeleton benar-benar sempat
 * dilukis browser sebelum kerja render konten dimulai.
 */
const { tampil: skelTampil, tandaiSiap } = useSkeleton()
function keSemuaPromo() {
  router.push({ name: 'task-bersih-promo' })
}

/* ---------------- Carousel hero ---------------- */

/**
 * Banner hero disimpan sebagai daftar, bukan ditulis satu per satu di template.
 * Titik penanda, panah, dan perputaran otomatis semuanya membaca panjang daftar
 * ini — menambah promo cukup menambah satu baris di sini, tanpa menyentuh tiga
 * tempat lain yang bisa lupa diperbarui.
 */
const bannerHero = [
  { src: promoBisaBersih, alt: 'Potongan Rp50.000 untuk layanan kebersihan pertama' },
  // Alt mengikuti yang TERGAMBAR, bukan nama berkasnya: isinya promo voucher,
  // sementara berkasnya bernama "Gratis biaya kunjungan".
  { src: promoGratisKunjungan, alt: 'Voucher BisaBersih, potongan hingga Rp200.000' },
  {
    src: promoBersihBaru,
    alt: 'BERSIHBARU50, potongan Rp50.000, khusus pelanggan baru',
  },
  { src: promoDeep100, alt: 'DEEP100, potongan Rp100.000 untuk Deep Cleaning' },
  { src: promoOffice100, alt: 'OFFICE100, potongan Rp100.000 untuk cleaning kantor' },
  { src: promoACHemat20, alt: 'ACHEMAT20, potongan Rp20.000 untuk minimal 2 unit AC' },
]

const trackHero = ref<HTMLElement | null>(null)
const heroAktif = ref(0)

function perbaruiHero() {
  const el = trackHero.value
  if (!el || el.clientWidth === 0) return
  heroAktif.value = Math.round(el.scrollLeft / el.clientWidth)
}

function keHero(i: number) {
  const el = trackHero.value
  if (!el) return
  el.scrollTo({ left: i * el.clientWidth, behavior: 'smooth' })
}

/**
 * Perputaran otomatis berhenti PERMANEN begitu pengguna menggeser sendiri.
 *
 * Carousel yang terus berjalan setelah disentuh akan menarik banner pergi tepat
 * ketika orang sedang membacanya — itu terasa seperti aplikasi merebut kendali,
 * bukan membantu.
 */
let jamHero: ReturnType<typeof setInterval> | null = null

function hentikanHero() {
  if (jamHero) clearInterval(jamHero)
  jamHero = null
}

function mulaiHero() {
  hentikanHero()

  // Gerakan yang tidak diminta adalah hal pertama yang dimatikan oleh setelan
  // ini; menjalankannya tetap berarti mengabaikan permintaan yang jelas.
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
  if (bannerHero.length < 2) return

  jamHero = setInterval(() => keHero((heroAktif.value + 1) % bannerHero.length), 4500)
}

/* ---------------- Geser carousel promo ---------------- */

const trackPromo = ref<HTMLElement | null>(null)
const bisaKiri = ref(false)
const bisaKanan = ref(false)

const trackBundling = ref<HTMLElement | null>(null)
const bisaKiriBundling = ref(false)
const bisaKananBundling = ref(false)

/**
 * Panah hanya muncul kalau memang masih ada yang bisa digeser ke arah itu.
 * Toleransi 8 px dipakai karena scrollLeft bisa berupa pecahan saat perangkat
 * memakai penskalaan non-integer, sehingga perbandingan persis tidak pernah
 * bernilai benar di ujung track.
 */
function perbaruiPanah() {
  const el = trackPromo.value
  if (el) {
    bisaKiri.value = el.scrollLeft > 8
    bisaKanan.value = el.scrollLeft + el.clientWidth < el.scrollWidth - 8
  }

  const elBundling = trackBundling.value
  if (elBundling) {
    bisaKiriBundling.value = elBundling.scrollLeft > 8
    bisaKananBundling.value = elBundling.scrollLeft + elBundling.clientWidth < elBundling.scrollWidth - 8
  }
}

function geserPromo(arah: 'kiri' | 'kanan') {
  const el = trackPromo.value
  if (!el) return
  // Langkahnya diukur dari slide pertama, bukan angka tetap: banner promo
  // selebar layar, jadi 268 px hasil ukur kartu kecil dulu akan berhenti di
  // tengah banner. Dengan diukur, ukuran slide boleh berubah kapan saja.
  const slide = el.firstElementChild as HTMLElement | null
  const langkah = slide ? Math.round(slide.getBoundingClientRect().width) + 12 : el.clientWidth
  el.scrollBy({ left: arah === 'kanan' ? langkah : -langkah, behavior: 'smooth' })
  window.setTimeout(perbaruiPanah, 350)
}

function geserBundling(arah: 'kiri' | 'kanan') {
  const el = trackBundling.value
  if (!el) return
  const slide = el.firstElementChild as HTMLElement | null
  const langkah = slide ? Math.round(slide.getBoundingClientRect().width) + 12 : el.clientWidth
  el.scrollBy({ left: arah === 'kanan' ? langkah : -langkah, behavior: 'smooth' })
  window.setTimeout(perbaruiPanah, 350)
}

/**
 * Pengukuran ditunda sampai skeleton benar-benar lepas.
 *
 * Sempat dipasang di nextTick(onMounted) dan hasilnya panah kanan tidak pernah
 * muncul: pada saat itu skeleton masih terpasang, jadi `trackPromo` masih null,
 * perbaruiPanah() keluar lebih awal, dan listener scroll tidak pernah terikat.
 * skelTampil baru bernilai false beberapa ratus milidetik kemudian.
 */
watch(
  skelTampil,
  (tampil) => {
    if (tampil) return
    nextTick(() => {
      perbaruiPanah()
      perbaruiHero()
      trackHero.value?.addEventListener('scroll', perbaruiHero, { passive: true })
      trackHero.value?.addEventListener('pointerdown', hentikanHero, { passive: true })
      mulaiHero()
      trackPromo.value?.addEventListener('scroll', perbaruiPanah, { passive: true })
      trackBundling.value?.addEventListener('scroll', perbaruiPanah, { passive: true })
    })
  },
  { immediate: true },
)

onMounted(() => {
  requestAnimationFrame(() => requestAnimationFrame(() => tandaiSiap()))
  window.addEventListener('resize', perbaruiPanah, { passive: true })
  window.addEventListener('resize', perbaruiHero, { passive: true })
})

onBeforeUnmount(() => {
  hentikanHero()
  trackHero.value?.removeEventListener('scroll', perbaruiHero)
  trackHero.value?.removeEventListener('pointerdown', hentikanHero)
  trackPromo.value?.removeEventListener('scroll', perbaruiPanah)
  trackBundling.value?.removeEventListener('scroll', perbaruiPanah)
  window.removeEventListener('resize', perbaruiPanah)
  window.removeEventListener('resize', perbaruiHero)
})
</script>

<template>
  <!-- Skeleton loading -->
  <div v-if="skelTampil" class="min-h-dvh w-full bg-(--color-surface) pb-24">
    <div class="h-56 w-full">
      <Skeleton class="h-full w-full" />
    </div>
    <div class="mx-4 mt-4 space-y-4">
      <Skeleton class="h-14 w-full" />
      <div class="grid grid-cols-3 gap-3">
        <Skeleton class="h-20 w-full" v-for="i in 6" :key="i" />
      </div>
      <Skeleton class="h-7 w-40" />
      <div class="space-y-3">
        <Skeleton class="h-20 w-full" v-for="i in 3" :key="'c' + i" />
      </div>
      <Skeleton class="h-40 w-full" />
    </div>
  </div>

  <template v-else>
    <div class="min-h-dvh w-full bg-(--color-surface) text-(--color-on-surface) font-body overflow-x-hidden">
      <!-- TopAppBar: fixed sticky navbar matching BisaAngkut & BisaBelanja -->
      <BisaBersihNavbar />

      <!--
        Tanpa jarak atas: navbarnya bening di puncak halaman, jadi hero boleh
        menyentuh tepi atas layar. Jarak di sini justru menyisakan pita kosong
        di atas gambar, dengan tombol kembali melayang di atas pita itu.
      -->
      <main class="pb-28">
        <!--
          Hero menyentuh tepi kiri-kanan, tanpa hamparan teks di atasnya:
          gambarnya sudah memuat judul dan promonya sendiri, dan menumpuk teks
          kedua di situ hanya menutupi karya yang sudah utuh.

          Pita 48px di bawah gambar memakai warna baris terakhir gambarnya
          (#002578, diambil dari pikselnya). Di situlah titik penanda duduk —
          ditaruh di atas gambar, ia akan menimpa tulisan promonya.
        -->
        <section class="relative w-full pb-12" style="background: #002578">
          <div
            ref="trackHero"
            class="flex overflow-x-auto no-scrollbar snap-x snap-mandatory scroll-smooth"
          >
            <div v-for="b in bannerHero" :key="b.src" class="shrink-0 w-full snap-center">
              <img :src="b.src" :alt="b.alt" class="block w-full h-auto" />
            </div>
          </div>

          <!--
            Hanya titik, tanpa panah: bannernya sudah bisa digeser dengan jari,
            dan panah melingkar di atas ilustrasi menutupi bagian gambar yang
            justru ingin dilihat.
          -->
          <template v-if="bannerHero.length > 1">
            <!--
              Titik penanda: berapa banner yang ada, dan sedang di mana. Yang
              aktif dibuat memanjang, bukan sekadar lebih terang — bedanya tetap
              terbaca oleh mata yang sulit membedakan warna.
            -->
            <!--
              Duduk di 20-44px dari dasar pita: kartu isi naik 20px menutupi
              bagian bawah section ini, dan titik yang separuhnya berada di
              bawah garis itu tidak bisa diketuk sama sekali — ketukannya
              diterima kartu, bukan titiknya.
            -->
            <div class="absolute inset-x-0 bottom-5 h-6 flex items-center justify-center gap-1.5">
              <button
                v-for="(b, i) in bannerHero"
                :key="b.src"
                type="button"
                class="h-5 flex items-center px-0.5"
                :aria-label="`Ke promo ke-${i + 1}`"
                :aria-current="i === heroAktif"
                @click="hentikanHero(); keHero(i)"
              >
                <span
                  class="block h-1.5 rounded-full transition-all duration-300"
                  :class="i === heroAktif ? 'w-5 bg-white' : 'w-1.5 bg-white/45'"
                ></span>
              </button>
            </div>
          </template>
        </section>

        <!--
          Kartu isi naik menutupi bagian bawah hero dan space birunya.
        -->
        <div
          class="relative z-[2] -mt-5 rounded-t-[32px] bg-(--color-surface) shadow-[0_-4px_16px_rgba(0,0,0,0.06)] pt-6 flex flex-col gap-5"
        >
          <!-- Service Categories (Grid) -->
        <!-- Promo Hari Ini -->
        <section class="mx-4">
          <div class="flex justify-between items-end mb-3">
            <h3 class="font-display font-extrabold text-[15px] text-(--color-on-surface)">
              Promo Hari Ini
            </h3>
            <button
              type="button"
              class="text-[12px] font-semibold text-(--color-azure) hover:underline active:scale-95 transition-transform"
              @click="keSemuaPromo"
            >
              Lihat semua
            </button>
          </div>

          <!--
            Dua banner promo jadi slide carousel, bukan tumpukan: keduanya
            selebar layar dan sama pentingnya, jadi digeser menyamping lebih
            hemat ruang daripada ditumpuk. snap-x membuatnya berhenti rapi di
            tepi tiap banner.
          -->
          <div class="relative">
            <!-- Panah kiri -->
            <transition name="fade">
              <button
                v-if="bisaKiri"
                type="button"
                class="absolute -left-3 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-(--color-surface-0) shadow-(--shadow-lift) border border-(--color-outline)/40 flex items-center justify-center active:scale-90 transition-transform"
                aria-label="Geser promo ke kiri"
                @click="geserPromo('kiri')"
              >
                <Icon name="chevron-left" class="w-4 h-4 text-(--color-on-surface)" />
              </button>
            </transition>

            <div
              ref="trackPromo"
              class="flex items-start gap-3 overflow-x-auto no-scrollbar pb-1 snap-x snap-mandatory scroll-smooth"
            >
              <!-- Slide 1: Traktiran Teman -->
              <div class="shrink-0 w-full snap-center rounded-2xl overflow-hidden border border-(--color-outline)/10 shadow-(--shadow-lift)">
              <svg
                width="100%"
                viewBox="0 0 700 320"
                xmlns="http://www.w3.org/2000/svg"
                class="w-full h-auto block rounded-2xl"
              >
                <defs>
                  <linearGradient id="tt_cardBg" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#2E86F0"/>
                    <stop offset="55%" stop-color="#1C6BE4"/>
                    <stop offset="100%" stop-color="#1554CE"/>
                  </linearGradient>
                  <linearGradient id="tt_ticketGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#4E9BFF"/>
                    <stop offset="100%" stop-color="#2A6FE0"/>
                  </linearGradient>
                  <linearGradient id="tt_ticketSide" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#3D82E8"/>
                    <stop offset="100%" stop-color="#1F58C4"/>
                  </linearGradient>
                  <linearGradient id="tt_limeGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#A8E22B"/>
                    <stop offset="100%" stop-color="#79B818"/>
                  </linearGradient>
                  <linearGradient id="tt_coinGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFD84D"/>
                    <stop offset="100%" stop-color="#F2A507"/>
                  </linearGradient>
                  <linearGradient id="tt_coinRim" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFC42E"/>
                    <stop offset="100%" stop-color="#E08A00"/>
                  </linearGradient>
                  <linearGradient id="tt_skinLeft" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFDCC0"/>
                    <stop offset="100%" stop-color="#F5B98E"/>
                  </linearGradient>
                  <linearGradient id="tt_skinRight" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFD0AC"/>
                    <stop offset="100%" stop-color="#EDA477"/>
                  </linearGradient>
                  <linearGradient id="tt_sleeveBlue" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#4A7BF0"/>
                    <stop offset="100%" stop-color="#2450C8"/>
                  </linearGradient>
                  <linearGradient id="tt_sleeveLime" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#A8E22B"/>
                    <stop offset="100%" stop-color="#72AF14"/>
                  </linearGradient>
                  <linearGradient id="tt_yellowGrad" x1="0" y1="0" x2="0" y2="1">
                    <stop offset="0%" stop-color="#FFD84D"/>
                    <stop offset="100%" stop-color="#FBB916"/>
                  </linearGradient>

                  <filter id="tt_deep" x="-40%" y="-40%" width="180%" height="180%">
                    <feDropShadow dx="0" dy="10" stdDeviation="10" flood-color="#0A2E7A" flood-opacity="0.35"/>
                  </filter>
                  <filter id="tt_soft" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="5" stdDeviation="6" flood-color="#0A2E7A" flood-opacity="0.3"/>
                  </filter>

                  <clipPath id="tt_cardClip">
                    <rect x="0" y="0" width="700" height="320" rx="36" ry="36"/>
                  </clipPath>
                </defs>

                <g clip-path="url(#tt_cardClip)">
                  <!-- ================= BACKGROUND ================= -->
                  <rect width="700" height="320" fill="url(#tt_cardBg)"/>
                  <!-- lingkaran gelap di belakang tangan -->
                  <ellipse cx="528" cy="160" rx="146" ry="140" fill="#1449B8" opacity="0.55"/>

                  <!-- ================= DEKORASI ================= -->
                  <!-- segitiga biru muda -->
                  <path d="M410,84 l26,6 -14,24 z" fill="#5B9BFF"/>
                  <!-- segitiga lime -->
                  <path d="M676,152 l-24,10 18,18 z" fill="#A8E22B"/>
                  <!-- sparkle kuning -->
                  <path d="M400,134 l7,17 17,7 -17,7 -7,17 -7,-17 -17,-7 17,-7 z" fill="url(#tt_yellowGrad)"/>

                  <!-- burst kuning di atas tangan -->
                  <g fill="url(#tt_yellowGrad)">
                    <path d="M520,24 l12,0 4,44 -20,0 z"/>
                    <path d="M470,42 l10,-6 26,36 -17,10 z"/>
                    <path d="M572,36 l10,6 -19,40 -17,-10 z"/>
                    <path d="M438,70 l6,-10 34,20 -10,17 z"/>
                    <path d="M604,60 l6,10 -30,23 -10,-17 z"/>
                  </g>

                  <!-- ================= TANGAN TOS ================= -->
                  <!-- lengan kiri (biru) -->
                  <g filter="url(#tt_deep)">
                    <path d="M392,290
                             C 400,246 428,206 466,182
                             L 512,234
                             C 486,252 468,278 460,306 Z"
                          fill="url(#tt_sleeveBlue)"/>
                    <!-- telapak kiri -->
                    <g transform="translate(470,106) rotate(-14)">
                      <rect x="-30" y="0" width="62" height="92" rx="26" fill="url(#tt_skinLeft)"/>
                      <rect x="-24" y="-46" width="16" height="56" rx="8" fill="url(#tt_skinLeft)"/>
                      <rect x="-5" y="-58" width="16" height="68" rx="8" fill="url(#tt_skinLeft)"/>
                      <rect x="14" y="-52" width="16" height="62" rx="8" fill="url(#tt_skinLeft)"/>
                      <rect x="-42" y="18" width="18" height="46" rx="9" fill="url(#tt_skinLeft)"
                            transform="rotate(-22 -33 41)"/>
                    </g>
                  </g>

                  <!-- lengan kanan (lime) -->
                  <g filter="url(#tt_deep)">
                    <path d="M690,258
                             C 678,216 650,182 610,162
                             L 566,216
                             C 594,232 612,256 620,284 Z"
                          fill="url(#tt_sleeveLime)"/>
                    <!-- telapak kanan (mirror) -->
                    <g transform="translate(578,106) scale(-1,1) rotate(-14)">
                      <rect x="-30" y="0" width="62" height="92" rx="26" fill="url(#tt_skinRight)"/>
                      <rect x="-24" y="-46" width="16" height="56" rx="8" fill="url(#tt_skinRight)"/>
                      <rect x="-5" y="-58" width="16" height="68" rx="8" fill="url(#tt_skinRight)"/>
                      <rect x="14" y="-52" width="16" height="62" rx="8" fill="url(#tt_skinRight)"/>
                      <rect x="-42" y="18" width="18" height="46" rx="9" fill="url(#tt_skinRight)"
                            transform="rotate(-22 -33 41)"/>
                    </g>
                  </g>

                  <!-- ================= KOIN ATAS ================= -->
                  <g filter="url(#tt_soft)" transform="translate(636,90)">
                    <circle cx="0" cy="0" r="36" fill="url(#tt_coinRim)"/>
                    <circle cx="0" cy="0" r="29" fill="url(#tt_coinGrad)"/>
                    <circle cx="0" cy="0" r="23" fill="none" stroke="#E08A00" stroke-width="3" opacity="0.6"/>
                    <text x="0" y="9" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                          font-size="24" font-weight="800" fill="#FFFFFF">Rp</text>
                  </g>

                  <!-- ================= TIKET CASHBACK ================= -->
                  <g filter="url(#tt_deep)" transform="rotate(-3 520 200)">
                    <!-- sisi kanan tiket (lebih gelap) -->
                    <path d="M628,122 h26 a14,14 0 0 1 14,14 v34 a16,16 0 0 0 0,32 v34
                             a14,14 0 0 1 -14,14 h-26 z" fill="url(#tt_ticketSide)"/>
                    <!-- badan tiket -->
                    <path d="M400,122 h228 v128 h-228
                             a14,14 0 0 1 -14,-14 v-100 a14,14 0 0 1 14,-14 z" fill="url(#tt_ticketGrad)"/>
                    <!-- teks -->
                    <text x="446" y="162" font-family="Arial, Helvetica, sans-serif"
                          font-size="24" font-weight="800" fill="#FFFFFF">Rp</text>
                    <text x="410" y="222" font-family="Arial, Helvetica, sans-serif"
                          font-size="62" font-weight="800" fill="#FFFFFF">30.000</text>
                    <!-- pill CASHBACK -->
                    <rect x="408" y="226" width="212" height="44" rx="22" fill="url(#tt_limeGrad)"/>
                    <text x="514" y="258" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                          font-size="27" font-weight="800" fill="#1B4A0C">CASHBACK</text>
                  </g>

                  <!-- ================= KOIN BAWAH ================= -->
                  <g filter="url(#tt_soft)" transform="translate(348,266)">
                    <circle cx="0" cy="0" r="32" fill="url(#tt_coinRim)"/>
                    <circle cx="0" cy="0" r="26" fill="url(#tt_coinGrad)"/>
                    <circle cx="0" cy="0" r="20" fill="none" stroke="#E08A00" stroke-width="3" opacity="0.6"/>
                    <text x="0" y="8" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                          font-size="21" font-weight="800" fill="#FFFFFF">Rp</text>
                  </g>

                  <!-- ================= TEKS KIRI ================= -->
                  <g font-family="Arial, Helvetica, sans-serif">
                    <text x="44" y="66" font-size="40" font-weight="800" fill="#FFFFFF">Traktiran</text>
                    <text x="44" y="110" font-size="40" font-weight="800" fill="#B4EE2E">Teman!</text>

                    <rect x="46" y="122" width="104" height="4" rx="2" fill="#B4EE2E"/>
                    <circle cx="158" cy="124" r="3.5" fill="#B4EE2E"/>

                    <text x="44" y="160" font-size="22" font-weight="700" fill="#FFFFFF">Ajak teman &amp; dapatkan</text>
                    <text x="44" y="188" font-size="22" font-weight="700" fill="#FFFFFF">cashback</text>

                    <text x="44" y="228" font-size="32" font-weight="800" fill="#B4EE2E">Rp30.000.</text>

                    <text x="44" y="264" font-size="22" font-weight="700" fill="#FFFFFF">Temanmu untung,</text>
                    <text x="44" y="292" font-size="22" font-weight="700" fill="#FFFFFF">kamu untung!</text>
                  </g>
                </g>
              </svg>
              </div>

              <!-- Slide 2: Cashback 10% -->
              <div class="shrink-0 w-full snap-center rounded-2xl overflow-hidden border border-(--color-outline)/10 shadow-(--shadow-lift)">
                <svg
                  viewBox="0 0 700 320"
                  xmlns="http://www.w3.org/2000/svg"
                  class="w-full h-auto block rounded-2xl"
                  role="img"
                  aria-label="Cashback 10 persen, minimal belanja Rp200.000, untung hingga Rp30.000"
                >
                  <defs>
                    <linearGradient id="cb_cardBg" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#2A80EE"/>
                      <stop offset="55%" stop-color="#1A66E2"/>
                      <stop offset="100%" stop-color="#124FCC"/>
                    </linearGradient>
                    <linearGradient id="cb_ticketGrad" x1="0" y1="0" x2="0.3" y2="1">
                      <stop offset="0%" stop-color="#5AA0FF"/>
                      <stop offset="55%" stop-color="#2E77E8"/>
                      <stop offset="100%" stop-color="#1B57CC"/>
                    </linearGradient>
                    <linearGradient id="cb_limeGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#C4F03A"/>
                      <stop offset="100%" stop-color="#8FD117"/>
                    </linearGradient>
                    <linearGradient id="cb_limeFlat" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#BEEE33"/>
                      <stop offset="100%" stop-color="#93D419"/>
                    </linearGradient>
                    <linearGradient id="cb_coinFace" x1="0" y1="0" x2="0.4" y2="1">
                      <stop offset="0%" stop-color="#FFE063"/>
                      <stop offset="55%" stop-color="#FFC61F"/>
                      <stop offset="100%" stop-color="#F0A200"/>
                    </linearGradient>
                    <linearGradient id="cb_coinEdge" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#F5B912"/>
                      <stop offset="100%" stop-color="#D98A00"/>
                    </linearGradient>
                    <linearGradient id="cb_yellowGrad" x1="0" y1="0" x2="0" y2="1">
                      <stop offset="0%" stop-color="#FFE063"/>
                      <stop offset="100%" stop-color="#FBC016"/>
                    </linearGradient>

                    <filter id="cb_deep" x="-40%" y="-40%" width="180%" height="180%">
                      <feDropShadow dx="0" dy="10" stdDeviation="10" flood-color="#0A2E7A" flood-opacity="0.35"/>
                    </filter>

                    <clipPath id="cb_cardClip">
                      <rect x="0" y="0" width="700" height="320" rx="36" ry="36"/>
                    </clipPath>
                  </defs>

                  <g clip-path="url(#cb_cardClip)">
                    <!-- Latar -->
                    <rect width="700" height="320" fill="url(#cb_cardBg)"/>
                    <ellipse cx="500" cy="150" rx="165" ry="135" fill="#0F49C0" opacity="0.5"/>

                    <!-- Dekorasi -->
                    <path d="M596,26 l30,-5 -10,36 z" fill="url(#cb_limeFlat)"/>
                    <path d="M632,54 l28,4 -22,26 z" fill="url(#cb_limeFlat)"/>
                    <path d="M352,44 l8,19 19,8 -19,8 -8,19 -8,-19 -19,-8 19,-8 z" fill="url(#cb_yellowGrad)"/>
                    <path d="M330,272 l21,4 -10,23 z" fill="#5B9BFF"/>
                    <path d="M576,276 l21,-4 -7,25 z" fill="#5B9BFF"/>
                    <path d="M322,224 l18,9 -16,14 z" fill="#4E8FFA" opacity="0.9"/>

                    <!-- Tiket -->
                    <g filter="url(#cb_deep)" transform="rotate(-9 500 140)">
                      <path d="M372,60
                               h230
                               a16,16 0 0 1 16,16
                               v36
                               a21,21 0 0 0 0,42
                               v36
                               a16,16 0 0 1 -16,16
                               h-230
                               a16,16 0 0 1 -16,-16
                               v-36
                               a21,21 0 0 0 0,-42
                               v-36
                               a16,16 0 0 1 16,-16 z"
                            fill="url(#cb_ticketGrad)"/>
                      <line x1="420" y1="68" x2="420" y2="198" stroke="#8FBEFF" stroke-width="4"
                            stroke-dasharray="11 11" stroke-linecap="round" opacity="0.85"/>
                      <text x="522" y="118" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                            font-size="26" font-weight="800" fill="#FFFFFF" letter-spacing="1">CASHBACK</text>
                      <text x="522" y="182" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                            font-size="62" font-weight="800" fill="#FFFFFF">10%</text>
                      <path d="M384,84 q34,-12 78,-10" fill="none" stroke="#FFFFFF"
                            stroke-width="7" stroke-linecap="round" opacity="0.28"/>
                    </g>

                    <!-- Tumpukan koin -->
                    <g filter="url(#cb_deep)">
                      <g>
                        <path d="M368,262 v-46 h84 v46 a42,15 0 0 1 -84,0 z" fill="url(#cb_coinEdge)"/>
                        <g stroke="#D98A00" stroke-width="2" opacity="0.5">
                          <path d="M368,226 a42,15 0 0 0 84,0" fill="none"/>
                          <path d="M368,238 a42,15 0 0 0 84,0" fill="none"/>
                          <path d="M368,250 a42,15 0 0 0 84,0" fill="none"/>
                        </g>
                        <ellipse cx="410" cy="216" rx="42" ry="15" fill="url(#cb_coinFace)"/>
                        <ellipse cx="410" cy="216" rx="32" ry="10" fill="none" stroke="#E8A100" stroke-width="3" opacity="0.55"/>
                      </g>
                      <g transform="translate(496,238)">
                        <circle cx="0" cy="0" r="44" fill="url(#cb_coinEdge)"/>
                        <circle cx="0" cy="-3" r="41" fill="url(#cb_coinFace)"/>
                        <circle cx="0" cy="-3" r="32" fill="none" stroke="#E8A100" stroke-width="4" opacity="0.55"/>
                        <text x="0" y="9" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                              font-size="34" font-weight="800" fill="#F0A200">Rp</text>
                        <path d="M-27,-25 q15,-14 37,-12" fill="none" stroke="#FFFFFF"
                              stroke-width="6" stroke-linecap="round" opacity="0.45"/>
                      </g>
                    </g>

                    <!-- Teks kiri: ritme sama persis dengan kartu Traktiran -->
                    <g font-family="Arial, Helvetica, sans-serif">
                      <text x="44" y="66" font-size="40" font-weight="800" fill="#FFFFFF">Cashback</text>
                      <text x="44" y="110" font-size="40" font-weight="800" fill="#C4F03A">10%!</text>

                      <rect x="46" y="124" width="104" height="6" rx="3" fill="#C4F03A"/>
                      <circle cx="162" cy="127" r="5" fill="#C4F03A"/>

                      <text x="44" y="166" font-size="22" font-weight="700" fill="#FFFFFF">Min. belanja</text>
                      <text x="44" y="204" font-size="32" font-weight="800" fill="#C4F03A">Rp200.000,</text>
                      <text x="44" y="240" font-size="22" font-weight="700" fill="#FFFFFF">untung hingga</text>

                      <rect x="44" y="254" width="200" height="44" rx="22" fill="url(#cb_limeGrad)"/>
                      <text x="144" y="285" text-anchor="middle" font-size="26" font-weight="800" fill="#123A8F">Rp30.000.</text>
                    </g>
                  </g>
                </svg>
              </div>
            </div>

            <!-- Panah kanan -->
            <transition name="fade">
              <button
                v-if="bisaKanan"
                type="button"
                class="absolute -right-3 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-(--color-surface-0) shadow-(--shadow-lift) border border-(--color-outline)/40 flex items-center justify-center active:scale-90 transition-transform"
                aria-label="Geser promo ke kanan"
                @click="geserPromo('kanan')"
              >
                <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface)" />
              </button>
            </transition>
          </div>
        </section>

        <section class="mx-4">
          <div class="flex justify-between items-end mb-3">
            <h3 class="font-display font-extrabold text-[15px] text-(--color-on-surface)">Layanan Kami</h3>
            <a
              class="text-[12px] font-semibold text-(--color-azure) hover:underline"
              href="#"
              @click.prevent
              >Lihat semua</a
            >
          </div>
          <!--
            Enam kolom, tiap kartu selebar dua: dengan lima layanan, barisnya
            jadi 3 di atas dan 2 di bawah. Kartu keempat digeser mulai kolom
            kedua supaya dua kartu terakhir duduk di tengah, bukan menggantung
            di kiri dengan satu lubang di kanan.
          -->
          <div class="grid grid-cols-6 gap-3">
            <button
              v-for="(s, i) in services"
              :key="s.id"
              type="button"
              class="col-span-2 relative flex flex-col items-center justify-center p-4 rounded-2xl transition-all active:scale-95 border-2 group"
              :class="[
                i === 3 ? 'col-start-2' : '',
                selectedService === s.id
                  ? 'border-(--color-azure) bg-(--color-primary-container) shadow-[0_10px_28px_rgba(30,155,240,0.30)] -translate-y-0.5'
                  : 'border-(--color-outline)/15 bg-(--color-surface-0) shadow-[0_4px_20px_rgba(0,0,0,0.05)] hover:border-(--color-azure)/40 hover:shadow-[0_10px_30px_rgba(0,0,0,0.08)]',
              ]"
              :aria-pressed="selectedService === s.id"
              @click="handleServiceSelect(s.id)"
            >
              <!-- Penanda pilihan aktif: badge centang di pojok -->
              <span
                v-if="selectedService === s.id"
                class="absolute top-2 right-2 w-5 h-5 rounded-full bg-(--color-azure) text-white flex items-center justify-center shadow-sm"
              >
                <Icon name="check" class="w-3 h-3" />
              </span>
              <template v-if="s.id === 'house'">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 96 96"
                  class="w-12 h-12 mb-2 shrink-0 object-contain"
                >
                  <defs>
                    <linearGradient id="houseBlue" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#2196F3"/>
                      <stop offset="100%" stop-color="#1478E8"/>
                    </linearGradient>

                    <linearGradient id="lime" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#B8EF63"/>
                      <stop offset="100%" stop-color="#8BC53F"/>
                    </linearGradient>
                  </defs>

                  <!-- House -->
                  <path
                    d="M14 45L48 17L82 45"
                    fill="none"
                    stroke="#0A326B"
                    stroke-width="7"
                    stroke-linecap="round"
                    stroke-linejoin="round"/>

                  <path
                    d="M22 41V78H74V41"
                    fill="#FFFFFF"
                    stroke="#0A326B"
                    stroke-width="6"
                    stroke-linejoin="round"/>

                  <!-- Roof -->
                  <path
                    d="M16 44L48 18L80 44"
                    fill="url(#houseBlue)"
                    opacity=".95"/>

                  <!-- Door -->
                  <rect
                    x="41"
                    y="57"
                    width="14"
                    height="21"
                    rx="2"
                    fill="#0A326B"/>

                  <circle
                    cx="52"
                    cy="67"
                    r="1.8"
                    fill="#8BC53F"/>

                  <!-- Window -->
                  <rect
                    x="27"
                    y="51"
                    width="13"
                    height="13"
                    rx="2"
                    fill="#8BC53F"/>

                  <path
                    d="M33.5 51V64M27 57.5H40"
                    stroke="#FFFFFF"
                    stroke-width="2"/>

                  <!-- Cleaning bubbles -->
                  <circle cx="70" cy="28" r="4" fill="#8BC53F"/>
                  <circle cx="78" cy="20" r="2.5" fill="#2196F3"/>
                  <circle cx="84" cy="30" r="2" fill="#8BC53F"/>

                  <!-- Sparkle -->
                  <path
                    d="M76 43
                       L79 50
                       L86 53
                       L79 56
                       L76 63
                       L73 56
                       L66 53
                       L73 50Z"
                    fill="url(#lime)"/>

                  <!-- Broom -->
                  <path
                    d="M19 72L34 48"
                    stroke="#DDAA22"
                    stroke-width="4"
                    stroke-linecap="round"/>

                  <path
                    d="M13 72
                       L22 72
                       L27 80
                       H9
                       Z"
                    fill="#8BC53F"/>

                  <!-- Cleaning cloth / shine -->
                  <path
                    d="M62 70
                       C67 65 75 65 79 70
                       C75 76 68 78 62 76Z"
                    fill="#2196F3"/>

                  <path
                    d="M66 70H75"
                    stroke="#FFFFFF"
                    stroke-width="2"
                    stroke-linecap="round"/>
                </svg>
              </template>
              <template v-else-if="s.id === 'office'">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 96 96"
                  class="w-12 h-12 mb-2 shrink-0 object-contain"
                >
                  <defs>
                    <linearGradient id="officeBlue" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#2196F3"/>
                      <stop offset="100%" stop-color="#1478E8"/>
                    </linearGradient>

                    <linearGradient id="officeLime" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#B8EF63"/>
                      <stop offset="100%" stop-color="#8BC53F"/>
                    </linearGradient>
                  </defs>

                  <!-- Office Building -->
                  <rect
                    x="19"
                    y="22"
                    width="58"
                    height="57"
                    rx="4"
                    fill="#FFFFFF"
                    stroke="#0A326B"
                    stroke-width="5"/>

                  <!-- Building Top -->
                  <rect
                    x="16"
                    y="17"
                    width="64"
                    height="9"
                    rx="3"
                    fill="url(#officeBlue)"/>

                  <!-- Windows -->
                  <g fill="#8BC53F">
                    <rect x="27" y="32" width="11" height="9" rx="2"/>
                    <rect x="43" y="32" width="11" height="9" rx="2"/>
                    <rect x="59" y="32" width="11" height="9" rx="2"/>

                    <rect x="27" y="47" width="11" height="9" rx="2"/>
                    <rect x="43" y="47" width="11" height="9" rx="2"/>
                    <rect x="59" y="47" width="11" height="9" rx="2"/>
                  </g>

                  <!-- Window reflections -->
                  <g stroke="#FFFFFF" stroke-width="2" opacity=".9">
                    <path d="M29 34L36 39"/>
                    <path d="M45 34L52 39"/>
                    <path d="M61 34L68 39"/>
                  </g>

                  <!-- Office Door -->
                  <rect
                    x="43"
                    y="62"
                    width="15"
                    height="17"
                    rx="2"
                    fill="#0A326B"/>

                  <circle
                    cx="54"
                    cy="70"
                    r="1.7"
                    fill="#F4C542"/>

                  <!-- Cleaning Person -->
                  <g>
                    <!-- Head -->
                    <circle
                      cx="70"
                      cy="56"
                      r="9"
                      fill="#F2B58D"/>

                    <!-- Hair -->
                    <path
                      d="M62 55
                         C62 48 67 45 72 47
                         C76 48 79 52 78 56
                         C74 52 69 51 65 54Z"
                      fill="#0A326B"/>

                    <!-- Face -->
                    <circle cx="73" cy="56" r="1.3" fill="#0A326B"/>

                    <path
                      d="M72 60Q75 62 77 59"
                      fill="none"
                      stroke="#0A326B"
                      stroke-width="1.5"
                      stroke-linecap="round"/>

                    <!-- Uniform -->
                    <path
                      d="M62 65
                         C65 62 75 62 78 66
                         L82 80
                         H59
                         Z"
                      fill="url(#officeBlue)"/>

                    <!-- Lime uniform accent -->
                    <path
                      d="M65 68H77"
                      stroke="#8BC53F"
                      stroke-width="3"
                      stroke-linecap="round"/>

                    <!-- Arm holding cloth -->
                    <path
                      d="M62 67L51 73"
                      fill="none"
                      stroke="#0A326B"
                      stroke-width="5"
                      stroke-linecap="round"/>

                    <circle
                      cx="50"
                      cy="74"
                      r="4"
                      fill="#F2B58D"/>
                  </g>

                  <!-- Cleaning Mop -->
                  <path
                    d="M52 73L37 43"
                    fill="none"
                    stroke="#DDAA22"
                    stroke-width="4"
                    stroke-linecap="round"/>

                  <!-- Mop Head -->
                  <path
                    d="M28 41
                       H43
                       L46 48
                       H25
                       Z"
                    fill="#8BC53F"/>

                  <path
                    d="M29 47L27 55
                       M34 47L33 56
                       M39 47L40 55"
                    stroke="#8BC53F"
                    stroke-width="3"
                    stroke-linecap="round"/>

                  <!-- Cleaning Sparkles -->
                  <path
                    d="M16 39
                       L19 46
                       L26 49
                       L19 52
                       L16 59
                       L13 52
                       L6 49
                       L13 46Z"
                    fill="url(#officeLime)"/>

                  <path
                    d="M84 28
                       L86 33
                       L91 35
                       L86 37
                       L84 42
                       L82 37
                       L77 35
                       L82 33Z"
                    fill="#F4C542"/>

                  <!-- Small bubbles -->
                  <circle cx="84" cy="50" r="3" fill="#8BC53F"/>
                  <circle cx="89" cy="44" r="2" fill="#2196F3"/>
                  <circle cx="91" cy="54" r="1.8" fill="#8BC53F"/>
                </svg>
              </template>
              <template v-else-if="s.id === 'deep'">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 96 96"
                  class="w-12 h-12 mb-2 shrink-0 object-contain"
                >
                  <defs>
                    <linearGradient id="deepBlue" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#2196F3"/>
                      <stop offset="100%" stop-color="#0A326B"/>
                    </linearGradient>

                    <linearGradient id="deepLime" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#C8FF70"/>
                      <stop offset="100%" stop-color="#8BC53F"/>
                    </linearGradient>
                  </defs>

                  <!-- Cleaning bucket -->
                  <path
                    d="M17 58
                       H47
                       L44 80
                       H20
                       Z"
                    fill="#FFFFFF"
                    stroke="#0A326B"
                    stroke-width="5"
                    stroke-linejoin="round"/>

                  <!-- Bucket handle -->
                  <path
                    d="M20 58
                       C20 43 43 43 44 58"
                    fill="none"
                    stroke="#0A326B"
                    stroke-width="5"
                    stroke-linecap="round"/>

                  <!-- Cleaning solution -->
                  <path
                    d="M19 65
                       C26 61 34 68 44 63
                       V79
                       H20
                       Z"
                    fill="url(#deepLime)"/>

                  <!-- Deep cleaning brush -->
                  <g>
                    <!-- Handle -->
                    <path
                      d="M42 66
                         L67 28"
                      fill="none"
                      stroke="#D9A72E"
                      stroke-width="5"
                      stroke-linecap="round"/>

                    <!-- Brush head -->
                    <path
                      d="M60 24
                         L74 31
                         L66 45
                         L52 38
                         Z"
                      fill="url(#deepBlue)"
                      stroke="#0A326B"
                      stroke-width="3"
                      stroke-linejoin="round"/>

                    <!-- Brush bristles -->
                    <path
                      d="M54 38L48 47
                       M58 40L52 50
                       M62 42L57 52
                       M66 43L62 53"
                      stroke="#8BC53F"
                      stroke-width="3"
                      stroke-linecap="round"/>
                  </g>

                  <!-- Cleaning spray bottle -->
                  <g>
                    <path
                      d="M68 58
                         H84
                         L82 79
                         H70
                         Z"
                      fill="#2196F3"
                      stroke="#0A326B"
                      stroke-width="3"
                      stroke-linejoin="round"/>

                    <path
                      d="M72 58V52H79V58"
                      fill="#8BC53F"
                      stroke="#0A326B"
                      stroke-width="3"/>

                    <!-- Spray nozzle -->
                    <path
                      d="M75 52
                         H86
                         L90 55"
                      fill="none"
                      stroke="#0A326B"
                      stroke-width="3"
                      stroke-linecap="round"
                      stroke-linejoin="round"/>

                    <!-- Bottle highlight -->
                    <path
                      d="M73 63V73"
                      stroke="#FFFFFF"
                      stroke-width="2"
                      stroke-linecap="round"
                      opacity=".8"/>
                  </g>

                  <!-- Deep-clean sparkle -->
                  <path
                    d="M77 16
                       L80 24
                       L88 27
                       L80 30
                       L77 38
                       L74 30
                       L66 27
                       L74 24Z"
                    fill="url(#deepLime)"/>

                  <!-- Small sparkles -->
                  <path
                    d="M51 18
                       L53 23
                       L58 25
                       L53 27
                       L51 32
                       L49 27
                       L44 25
                       L49 23Z"
                    fill="#F4C542"/>

                  <circle
                    cx="88"
                    cy="42"
                    r="3"
                    fill="#8BC53F"/>

                  <circle
                    cx="58"
                    cy="14"
                    r="2"
                    fill="#2196F3"/>

                  <!-- Floor shine -->
                  <path
                    d="M12 84
                       C29 80 46 87 63 83
                       C73 81 82 82 88 85"
                    fill="none"
                    stroke="#8BC53F"
                    stroke-width="4"
                    stroke-linecap="round"
                    opacity=".8"/>
                </svg>
              </template>
              <template v-else-if="s.id === 'ac'">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 96 96"
                  class="w-12 h-12 mb-2 shrink-0 object-contain"
                >
                  <defs>
                    <linearGradient id="acBlue" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#2196F3"/>
                      <stop offset="100%" stop-color="#0A326B"/>
                    </linearGradient>

                    <linearGradient id="acLime" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#C8FF70"/>
                      <stop offset="100%" stop-color="#8BC53F"/>
                    </linearGradient>
                  </defs>

                  <!-- AC Indoor Unit -->
                  <rect
                    x="14"
                    y="22"
                    width="52"
                    height="27"
                    rx="7"
                    fill="#FFFFFF"
                    stroke="#0A326B"
                    stroke-width="5"/>

                  <!-- AC Front Panel -->
                  <path
                    d="M18 34
                       H62
                       C62 43 57 47 48 47
                       H30
                       C21 47 18 42 18 34Z"
                    fill="#EAF5FF"/>

                  <!-- AC Display -->
                  <rect
                    x="23"
                    y="27"
                    width="17"
                    height="5"
                    rx="2.5"
                    fill="#8BC53F"/>

                  <circle
                    cx="53"
                    cy="29.5"
                    r="2.5"
                    fill="#2196F3"/>

                  <!-- Air Flow -->
                  <path
                    d="M27 53
                       C24 58 24 63 28 67"
                    fill="none"
                    stroke="#2196F3"
                    stroke-width="4"
                    stroke-linecap="round"/>

                  <path
                    d="M39 53
                       C36 59 36 64 40 69"
                    fill="none"
                    stroke="#8BC53F"
                    stroke-width="4"
                    stroke-linecap="round"/>

                  <path
                    d="M51 53
                       C48 58 48 63 52 67"
                    fill="none"
                    stroke="#2196F3"
                    stroke-width="4"
                    stroke-linecap="round"/>

                  <!-- Technician -->
                  <g>
                    <!-- Head -->
                    <circle
                      cx="73"
                      cy="49"
                      r="10"
                      fill="#F2B58D"/>

                    <!-- Hair -->
                    <path
                      d="M64 48
                         C64 40 69 36 75 37
                         C80 38 83 42 82 47
                         C77 43 71 43 66 47Z"
                      fill="#0A326B"/>

                    <!-- Eye -->
                    <circle
                      cx="77"
                      cy="49"
                      r="1.5"
                      fill="#0A326B"/>

                    <!-- Smile -->
                    <path
                      d="M75 54
                         Q78 56 80 53"
                      fill="none"
                      stroke="#0A326B"
                      stroke-width="1.5"
                      stroke-linecap="round"/>

                    <!-- Uniform -->
                    <path
                      d="M64 60
                         C68 56 79 56 83 61
                         L88 80
                         H60
                         Z"
                      fill="url(#acBlue)"/>

                    <!-- Lime uniform accent -->
                    <path
                      d="M67 64
                         H81"
                      stroke="#8BC53F"
                      stroke-width="4"
                      stroke-linecap="round"/>

                    <!-- Arm -->
                    <path
                      d="M65 62
                         L54 52"
                      fill="none"
                      stroke="#0A326B"
                      stroke-width="6"
                      stroke-linecap="round"/>

                    <!-- Hand -->
                    <circle
                      cx="53"
                      cy="51"
                      r="4"
                      fill="#F2B58D"/>
                  </g>

                  <!-- Screwdriver / Maintenance Tool -->
                  <g transform="rotate(-35 55 49)">
                    <rect
                      x="51"
                      y="39"
                      width="5"
                      height="23"
                      rx="2.5"
                      fill="#DDAA22"/>

                    <rect
                      x="48"
                      y="35"
                      width="11"
                      height="8"
                      rx="3"
                      fill="#2196F3"/>
                  </g>

                  <!-- Maintenance Gear -->
                  <circle
                    cx="19"
                    cy="70"
                    r="11"
                    fill="#FFFFFF"
                    stroke="#0A326B"
                    stroke-width="4"/>

                  <path
                    d="M19 63
                       V77
                       M12 70H26"
                    stroke="#8BC53F"
                    stroke-width="3"
                    stroke-linecap="round"/>

                  <circle
                    cx="19"
                    cy="70"
                    r="3"
                    fill="#2196F3"/>

                  <!-- Snowflake / Cooling Symbol -->
                  <g transform="translate(73 20)">
                    <path
                      d="M0 0V16
                         M-7 4L7 12
                         M7 4L-7 12"
                      stroke="url(#acLime)"
                      stroke-width="2.5"
                      stroke-linecap="round"/>

                    <path
                      d="M0 0L-2 3
                         M0 0L2 3
                         M0 16L-2 13
                         M0 16L2 13"
                      stroke="#8BC53F"
                      stroke-width="2"
                      stroke-linecap="round"/>
                  </g>

                  <!-- Maintenance Sparkle -->
                  <path
                    d="M43 14
                       L46 21
                       L53 24
                       L46 27
                       L43 34
                       L40 27
                       L33 24
                       L40 21Z"
                    fill="url(#acLime)"/>

                  <!-- Small bubbles -->
                  <circle cx="29" cy="15" r="3" fill="#2196F3"/>
                  <circle cx="58" cy="12" r="2" fill="#8BC53F"/>

                  <!-- Floor / Accent -->
                  <path
                    d="M12 84
                       C31 80 53 87 85 83"
                    fill="none"
                    stroke="#8BC53F"
                    stroke-width="4"
                    stroke-linecap="round"/>
                </svg>
              </template>
              <template v-else-if="s.id === 'disinfect'">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 96 96"
                  class="w-12 h-12 mb-2 shrink-0 object-contain"
                >
                  <defs>
                    <linearGradient id="disBlue" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#2196F3"/>
                      <stop offset="100%" stop-color="#0A326B"/>
                    </linearGradient>

                    <linearGradient id="disLime" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#C8FF70"/>
                      <stop offset="100%" stop-color="#8BC53F"/>
                    </linearGradient>
                  </defs>

                  <!-- Disinfectant Spray Bottle -->
                  <g>
                    <!-- Bottle body -->
                    <path
                      d="M29 37
                         H57
                         L60 78
                         Q60 82 56 82
                         H30
                         Q26 82 26 78
                         Z"
                      fill="#FFFFFF"
                      stroke="#0A326B"
                      stroke-width="5"
                      stroke-linejoin="round"/>

                    <!-- Disinfectant liquid -->
                    <path
                      d="M28 58
                         C35 54 46 62 58 56
                         L60 78
                         Q60 81 56 81
                         H30
                         Q27 81 27 78
                         Z"
                      fill="url(#disLime)"/>

                    <!-- Bottle label -->
                    <rect
                      x="31"
                      y="48"
                      width="24"
                      height="17"
                      rx="3"
                      fill="url(#disBlue)"/>

                    <!-- Shield/check on label -->
                    <path
                      d="M43 51
                         L50 54
                         V59
                         C50 63 47 65 43 66
                         C39 65 36 63 36 59
                         V54
                         Z"
                      fill="#FFFFFF"
                      opacity=".95"/>

                    <path
                      d="M39 58
                         L42 61
                         L47 55"
                      fill="none"
                      stroke="#8BC53F"
                      stroke-width="2.5"
                      stroke-linecap="round"
                      stroke-linejoin="round"/>

                    <!-- Bottle neck -->
                    <rect
                      x="35"
                      y="28"
                      width="17"
                      height="10"
                      rx="2"
                      fill="#8BC53F"
                      stroke="#0A326B"
                      stroke-width="4"/>

                    <!-- Spray head -->
                    <path
                      d="M38 28
                         V22
                         H55
                         L63 26
                         H72
                         Q75 26 75 29
                         V31
                         H51"
                      fill="url(#disBlue)"
                      stroke="#0A326B"
                      stroke-width="4"
                      stroke-linejoin="round"
                      stroke-linecap="round"/>

                    <!-- Spray trigger -->
                    <path
                      d="M55 30
                         C61 31 61 37 57 40"
                      fill="none"
                      stroke="#0A326B"
                      stroke-width="4"
                      stroke-linecap="round"/>
                  </g>

                  <!-- Disinfectant Mist -->
                  <g fill="#8BC53F">
                    <circle cx="79" cy="25" r="3"/>
                    <circle cx="85" cy="20" r="2"/>
                    <circle cx="88" cy="28" r="2.5"/>
                    <circle cx="81" cy="34" r="1.8"/>
                  </g>

                  <g fill="#2196F3">
                    <circle cx="84" cy="39" r="2"/>
                    <circle cx="90" cy="34" r="1.5"/>
                  </g>

                  <!-- Protection Sparkle -->
                  <path
                    d="M18 25
                       L21 32
                       L28 35
                       L21 38
                       L18 45
                       L15 38
                       L8 35
                       L15 32Z"
                    fill="url(#disLime)"/>

                  <!-- Small Sparkle -->
                  <path
                    d="M68 12
                       L70 17
                       L75 19
                       L70 21
                       L68 26
                       L66 21
                       L61 19
                       L66 17Z"
                    fill="#F4C542"/>

                  <!-- Clean Shine -->
                  <path
                    d="M11 72
                       C18 69 22 70 26 73"
                    fill="none"
                    stroke="#2196F3"
                    stroke-width="4"
                    stroke-linecap="round"/>

                  <path
                    d="M65 79
                       C74 76 82 78 88 80"
                    fill="none"
                    stroke="#8BC53F"
                    stroke-width="4"
                    stroke-linecap="round"/>
                </svg>
              </template>
              <span
                v-else
                class="w-12 h-12 rounded-full flex items-center justify-center mb-2 transition-colors"
                :class="
                  selectedService === s.id
                    ? 'bg-(--color-azure) text-white'
                    : 'bg-(--color-primary-container) group-hover:bg-(--color-azure) group-hover:text-white'
                "
              >
                <Icon name="layers" class="w-6 h-6 text-(--color-on-primary-container)" />
              </span>
              <span class="text-[11px] font-medium text-(--color-on-surface) leading-tight text-center">
                {{ s.label }}
              </span>
            </button>
          </div>
        </section>

        <!-- Bundling Kinclong -->
        <section>
          <h3 class="font-display font-extrabold text-[15px] text-(--color-on-surface) mb-3 px-4">
            Bundling Kinclong
          </h3>
          <div class="relative">
            <!-- Panah kiri -->
            <transition name="fade">
              <button
                v-if="bisaKiriBundling"
                type="button"
                class="absolute -left-3 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-(--color-surface-0) shadow-(--shadow-lift) border border-(--color-outline)/40 flex items-center justify-center active:scale-90 transition-transform"
                aria-label="Geser bundling ke kiri"
                @click="geserBundling('kiri')"
              >
                <Icon name="chevron-left" class="w-4 h-4 text-(--color-on-surface)" />
              </button>
            </transition>

            <div
              ref="trackBundling"
              class="flex gap-3 overflow-x-auto no-scrollbar px-4 pb-1"
            >
              <div
                v-for="paket in bundling"
                :key="paket.id"
                class="shrink-0 w-56 bg-(--color-surface-0) border border-(--color-outline)/40 rounded-2xl overflow-hidden shadow-(--shadow-lift)"
              >
                <div class="h-24 flex items-center justify-center overflow-hidden" :class="paket.latar">
                  <img
                    v-if="paket.foto"
                    :src="paket.foto"
                    :alt="paket.nama"
                    class="w-full h-full object-cover"
                    :class="paket.posisiFoto || 'object-top'"
                  />
                  <Icon v-else :name="paket.ikon" class="w-9 h-9" :class="paket.warnaIkon" />
                </div>
                <div class="p-3.5">
                  <h4 class="font-display font-bold text-[13px] text-(--color-on-surface) mb-0.5">
                    {{ paket.nama }}
                  </h4>
                  <p class="text-[11.5px] text-(--color-on-surface-variant) mb-1.5">{{ paket.isi }}</p>
                  <p class="text-[13.5px] font-extrabold text-(--color-azure)">
                    {{ rp(paket.harga) }}
                    <span class="text-[11px] font-medium text-(--color-on-surface-variant) line-through ml-1">
                      {{ rp(paket.hargaCoret) }}
                    </span>
                  </p>
                </div>
              </div>
            </div>

            <!-- Panah kanan -->
            <transition name="fade">
              <button
                v-if="bisaKananBundling"
                type="button"
                class="absolute -right-3 top-1/2 -translate-y-1/2 z-10 w-8 h-8 rounded-full bg-(--color-surface-0) shadow-(--shadow-lift) border border-(--color-outline)/40 flex items-center justify-center active:scale-90 transition-transform"
                aria-label="Geser bundling ke kanan"
                @click="geserBundling('kanan')"
              >
                <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface)" />
              </button>
            </transition>
          </div>
        </section>

        <!-- Special Section: Why BisaBersih? -->
        <section class="mx-4">
          <h3 class="font-display font-extrabold text-[15px] text-(--color-on-surface) mb-3">
            Mengapa BisaBersih?
          </h3>
          <div class="flex flex-col gap-3">
            <!-- Card 1 -->
            <div class="bg-(--color-surface-0) p-4 rounded-2xl border border-(--color-outline)/10 shadow-(--shadow-lift) flex items-start gap-4">
              <div class="w-8 h-8 flex-shrink-0 flex items-center justify-center">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 32 32"
                  class="w-7 h-7 shrink-0"
                >
                  <defs>
                    <linearGradient id="vcBlue" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#2196F3"/>
                      <stop offset="100%" stop-color="#1478E8"/>
                    </linearGradient>
                  </defs>

                  <!-- Shield -->
                  <path
                    d="M16 2.5
                       L28 6.5
                       V15
                       C28 22.5 22.5 28 16 30
                       C9.5 28 4 22.5 4 15
                       V6.5Z"
                    fill="url(#vcBlue)"
                    stroke="#0A326B"
                    stroke-width="2.5"
                    stroke-linejoin="round"/>

                  <!-- Check -->
                  <path
                    d="M10.5 16
                       L14.5 20
                       L21.5 11.5"
                    fill="none"
                    stroke="#B8EF63"
                    stroke-width="3.2"
                    stroke-linecap="round"
                    stroke-linejoin="round"/>
                </svg>
              </div>
              <div>
                <h4 class="font-display font-bold text-[13px] text-(--color-on-surface) mb-1">Pembersih Terverifikasi</h4>
                <p class="text-[12px] text-(--color-on-surface-variant)">
                  Setiap mitra telah melalui pemeriksaan latar belakang dan dilatih secara profesional untuk layanan terbaik.
                </p>
              </div>
            </div>
            <!-- Card 2 -->
            <div class="bg-(--color-surface-0) p-4 rounded-2xl border border-(--color-outline)/10 shadow-(--shadow-lift) flex items-start gap-4">
              <div class="w-8 h-8 flex-shrink-0 flex items-center justify-center">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 32 32"
                  class="w-7 h-7 shrink-0"
                >
                  <defs>
                    <linearGradient id="ecoLime" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#B8EF63"/>
                      <stop offset="100%" stop-color="#8BC53F"/>
                    </linearGradient>

                    <linearGradient id="ecoBlue" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#2196F3"/>
                      <stop offset="100%" stop-color="#1478E8"/>
                    </linearGradient>
                  </defs>

                  <!-- Leaf -->
                  <path
                    d="M27 4
                       C27 17 19 26 6 27
                       C3 15 11 5 27 4Z"
                    fill="url(#ecoLime)"
                    stroke="#0A326B"
                    stroke-width="2.5"
                    stroke-linejoin="round"/>

                  <!-- Water drop -->
                  <path
                    d="M24 16.5
                       C26.6 20 28 21.9 28 23.6
                       A4 4 0 0 1 20 23.6
                       C20 21.9 21.4 20 24 16.5Z"
                    fill="url(#ecoBlue)"
                    stroke="#0A326B"
                    stroke-width="2.2"
                    stroke-linejoin="round"/>
                </svg>
              </div>
              <div>
                <h4 class="font-display font-bold text-[13px] text-(--color-on-surface) mb-1">Peralatan Ramah Lingkungan</h4>
                <p class="text-[12px] text-(--color-on-surface-variant)">
                  Kami menggunakan produk pembersih yang aman dan ramah lingkungan, efektif mengangkat kotoran namun tetap aman bagi rumah Anda.
                </p>
              </div>
            </div>
            <!-- Card 3 -->
            <div class="bg-(--color-surface-0) p-4 rounded-2xl border border-(--color-outline)/10 shadow-(--shadow-lift) flex items-start gap-4">
              <div class="w-8 h-8 flex-shrink-0 flex items-center justify-center">
                <svg
                  xmlns="http://www.w3.org/2000/svg"
                  viewBox="0 0 32 32"
                  class="w-7 h-7 shrink-0"
                >
                  <defs>
                    <linearGradient id="sgBlue" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#2196F3"/>
                      <stop offset="100%" stop-color="#1478E8"/>
                    </linearGradient>

                    <linearGradient id="sgLime" x1="0" y1="0" x2="1" y2="1">
                      <stop offset="0%" stop-color="#B8EF63"/>
                      <stop offset="100%" stop-color="#8BC53F"/>
                    </linearGradient>
                  </defs>

                  <!-- Clock face -->
                  <circle
                    cx="15"
                    cy="15"
                    r="11.5"
                    fill="url(#sgBlue)"
                    stroke="#0A326B"
                    stroke-width="2.5"/>

                  <!-- Hands -->
                  <path
                    d="M15 8.5
                       V15.5
                       L20 18.5"
                    fill="none"
                    stroke="#FFFFFF"
                    stroke-width="2.8"
                    stroke-linecap="round"
                    stroke-linejoin="round"/>

                  <!-- Check badge -->
                  <circle
                    cx="24.5"
                    cy="24.5"
                    r="6"
                    fill="url(#sgLime)"
                    stroke="#0A326B"
                    stroke-width="2.2"/>

                  <path
                    d="M21.8 24.6
                       L23.7 26.5
                       L27.2 22.6"
                    fill="none"
                    stroke="#0A326B"
                    stroke-width="2"
                    stroke-linecap="round"
                    stroke-linejoin="round"/>
                </svg>
              </div>
              <div>
                <h4 class="font-display font-bold text-[13px] text-(--color-on-surface) mb-1">Garansi Kepuasan 24 Jam</h4>
                <p class="text-[12px] text-(--color-on-surface-variant)">
                  Kurang puas dengan hasilnya? Beritahu kami dalam 24 jam dan kami akan membersihkan ulang secara gratis.
                </p>
              </div>
            </div>
          </div>
        </section>

        <!--
          Subscription Plans: banner + daftar paket jadi SATU section.
          Sebelumnya terpisah, sehingga halaman menampilkan dua judul langganan
          berurutan dan pembaca harus menebak apakah keduanya hal yang sama.
          Banner sekarang berperan sebagai kepala section, kartunya isi.
        -->
        <section class="mx-4 mb-6">
          <svg
            width="100%"
            viewBox="0 0 700 360"
            xmlns="http://www.w3.org/2000/svg"
            class="w-full h-auto block rounded-[2rem] shadow-[0_20px_60px_rgba(0,0,0,0.15)] mb-3"
          >
            <defs>
              <linearGradient id="sp_bg" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#1E9BF0"/>
                <stop offset="100%" stop-color="#0A6FD6"/>
              </linearGradient>

              <linearGradient id="lime" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#AAEE00"/>
                <stop offset="100%" stop-color="#7CBD00"/>
              </linearGradient>

              <linearGradient id="bottle" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#1E9BF0"/>
                <stop offset="100%" stop-color="#0B71E1"/>
              </linearGradient>

              <filter id="softShadow" x="-20%" y="-20%" width="140%" height="140%">
                <feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#063B78" flood-opacity=".3"/>
              </filter>
            </defs>

            <!-- CARD BACKGROUND -->
            <rect x="0" y="0" width="700" height="360" rx="36" fill="url(#sp_bg)"/>

            <!-- DECORATIVE BACKGROUND BLOBS -->
            <circle cx="650" cy="50" r="120" fill="#FFFFFF" opacity=".06"/>
            <circle cx="50" cy="340" r="100" fill="#8BC53F" opacity=".08"/>

            <!-- LEFT CONTENT: TITLE & DESCRIPTION & CTA BUTTON -->
            <g transform="translate(44 36)">
              <circle cx="22" cy="22" r="22" fill="#FFFFFF" opacity=".2"/>
              <rect x="11" y="13" width="22" height="20" rx="3.5" fill="none" stroke="#AAEE00" stroke-width="2.8"/>
              <path d="M11 19H33M17 9V13M27 9V13" stroke="#AAEE00" stroke-width="2.8" stroke-linecap="round"/>
            </g>

            <text x="44" y="125" font-family="Outfit, Inter, system-ui, sans-serif" font-size="42" font-weight="900" fill="#FFFFFF">
              Subscription Plans
            </text>

            <text x="44" y="175" font-family="Inter, system-ui, sans-serif" font-size="23" font-weight="700" fill="#EAF4FE">
              Schedule weekly or bi-weekly cleans
            </text>

            <text x="44" y="210" font-family="Inter, system-ui, sans-serif" font-size="23" font-weight="700" fill="#EAF4FE">
              and lock in huge savings.
            </text>

            <!-- CTA BUTTON -->
            <g filter="url(#softShadow)" class="cursor-pointer active:scale-95 transition-transform" @click="keLangganan">
              <rect x="44" y="248" width="270" height="60" rx="30" fill="url(#lime)"/>
              <text x="179" y="286" text-anchor="middle" font-family="Outfit, Inter, system-ui, sans-serif" font-size="21" font-weight="900" fill="#33430b">
                Hemat sampai {{ hematMaks }}% &gt;
              </text>
            </g>

            <!-- RIGHT ILLUSTRATION -->
            <g id="illustration" transform="translate(100, 0)">
              <!-- CALENDAR CARD -->
              <g filter="url(#softShadow)">
                <rect x="382" y="65" width="170" height="142" rx="16" fill="#FFFFFF"/>
                <path d="M382 81 C382 72.163 389.163 65 398 65 H536 C544.837 65 552 72.163 552 81 V100 H382 Z" fill="#1478E8"/>

                <!-- CALENDAR DAYS HEADER -->
                <g fill="#FFFFFF" font-family="Inter, system-ui, sans-serif" font-size="12" font-weight="800" text-anchor="middle">
                  <text x="413" y="88">M</text>
                  <text x="441" y="88">T</text>
                  <text x="469" y="88">W</text>
                  <text x="491" y="88">T</text>
                  <text x="519" y="88">F</text>
                </g>

                <!-- CALENDAR CELLS -->
                <g fill="#E4EFF9">
                  <rect x="402" y="106" width="22" height="18" rx="5"/>
                  <rect x="430" y="106" width="22" height="18" rx="5"/>
                  <rect x="458" y="106" width="22" height="18" rx="5"/>
                  <rect x="486" y="106" width="22" height="18" rx="5"/>
                  <rect x="514" y="106" width="22" height="18" rx="5"/>

                  <rect x="402" y="130" width="22" height="18" rx="5"/>
                  <rect x="430" y="130" width="22" height="18" rx="5"/>
                  <rect x="458" y="130" width="22" height="18" rx="5"/>
                  <rect x="486" y="130" width="22" height="18" rx="5"/>
                  <rect x="514" y="130" width="22" height="18" rx="5"/>

                  <rect x="402" y="154" width="22" height="18" rx="5"/>
                  <rect x="430" y="154" width="22" height="18" rx="5"/>
                  <rect x="458" y="154" width="22" height="18" rx="5"/>
                  <rect x="486" y="154" width="22" height="18" rx="5"/>
                  <rect x="514" y="154" width="22" height="18" rx="5"/>
                </g>

                <!-- SELECTED RECURRING DAY -->
                <rect
                  x="458"
                  y="130"
                  width="22"
                  height="18"
                  rx="5"
                  fill="url(#lime)"
                />

                <path
                  d="M463 139L467 143L475 134"
                  stroke="#3D7000"
                  stroke-width="2.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />
              </g>

              <!-- CLEANING SPRAY BOTTLE -->
              <g class="float2" filter="url(#softShadow)">
                <!-- TRIGGER -->
                <path
                  d="M315 219H336L348 228L339 239H315V219Z"
                  fill="#075CCF"
                />

                <path
                  d="M324 219V210C324 204 329 200 335 200H352V212H340C337 212 335 215 335 219H324Z"
                  fill="#0C73E7"
                />

                <!-- BOTTLE -->
                <path
                  d="M326 236C326 227 333 220 342 220H384C393 220 400 227 400 236V316C400 328 390 338 378 338H348C336 338 326 328 326 316V236Z"
                  fill="url(#bottle)"
                />

                <!-- LABEL -->
                <rect
                  x="337"
                  y="256"
                  width="52"
                  height="48"
                  rx="12"
                  fill="#0B71E1"
                />

                <circle
                  cx="363"
                  cy="278"
                  r="14"
                  fill="url(#lime)"
                />

                <path
                  d="M356 278L361 283L371 272"
                  stroke="#FFFFFF"
                  stroke-width="4"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />

                <rect
                  x="348"
                  y="297"
                  width="30"
                  height="4"
                  rx="2"
                  fill="#BDE1FF"
                />
              </g>

              <!-- MICROFIBER CLOTH -->
              <g
                class="float2"
                style="animation-delay:.45s"
              >
                <path
                  d="M527 238L578 255L557 314L506 296L527 238Z"
                  fill="#D7FF36"
                  opacity=".95"
                />

                <path
                  d="M533 249L565 260M527 263L560 275M521 278L554 290"
                  stroke="#7CB800"
                  stroke-width="3"
                  stroke-linecap="round"
                  opacity=".55"
                />
              </g>

              <!-- SPARKLES -->
              <g class="spark" fill="#FFFFFF">
                <path
                  d="M568 73L573 86L586 91L573 96L568 109L563 96L550 91L563 86L568 73Z"
                />
              </g>

              <g
                class="spark"
                style="animation-delay:.65s"
                fill="#DFFF42"
              >
                <path
                  d="M351 63L355 73L365 77L355 81L351 91L347 81L337 77L347 73L351 63Z"
                />
              </g>

              <g
                class="spark"
                style="animation-delay:1s"
                fill="#FFFFFF"
              >
                <path
                  d="M583 191L586 199L594 202L586 205L583 213L580 205L572 202L580 199L583 191Z"
                />
              </g>

              <!-- SMALL SHINE -->
              <circle
                cx="424"
                cy="57"
                r="5"
                fill="#FFFFFF"
                opacity=".5"
              />
            </g>
          </svg>
        </section>
        </div>
      </main>

      <!-- Sticky footer CTA -->
      <footer
        class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0)/90 backdrop-blur-xl border-t border-(--color-outline)/40 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))]"
      >
        <div class="max-w-[430px] mx-auto px-4 flex gap-3">
          <button
            type="button"
            :disabled="!selectedService"
            class="flex-1 bg-(--color-azure) text-white rounded-full py-3.5 text-[14px] font-bold shadow-(--shadow-lift) hover:opacity-90 active:scale-95 transition-all flex items-center justify-center gap-2 disabled:opacity-40 disabled:cursor-not-allowed min-h-11"
            @click="handleLanjut"
          >
            Lanjut
            <Icon name="arrow-right" class="w-4 h-4" />
          </button>
        </div>
      </footer>
    </div>
  </template>
</template>

<style scoped>
/* Panah muncul/menghilang halus, bukan berkedip saat track mencapai ujung. */
.fade-enter-active,
.fade-leave-active {
  transition: opacity 0.18s ease;
}

.fade-enter-from,
.fade-leave-to {
  opacity: 0;
}

@keyframes floatAnimation {
  0%, 100% { transform: translateY(0px); }
  50% { transform: translateY(-6px); }
}

@keyframes sparkAnimation {
  0%, 100% { opacity: 0.3; transform: scale(0.9); }
  50% { opacity: 1; transform: scale(1.1); }
}

.float2 {
  animation: floatAnimation 3s ease-in-out infinite;
}

.spark {
  animation: sparkAnimation 2s ease-in-out infinite;
  transform-origin: center;
}
</style>
