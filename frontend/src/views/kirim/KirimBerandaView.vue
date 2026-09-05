<script setup lang="ts">
/**
 * BisaKirim — beranda, layar pertama setelah lokasi dipilih.
 *
 * Isinya satu pekerjaan: menentukan paketnya diambil di mana dan diantar ke
 * mana. Sisanya — voucher, keunggulan — ditaruh di bawah, karena orang yang
 * membuka menu ini datang untuk mengirim, bukan untuk membaca.
 *
 * Titik ambil sudah terisi dari halaman lokasi. Titik antarnya kosong dan
 * itulah satu-satunya kolom yang menunggu diisi.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import KirimBerandaSkeleton from '@/components/skeleton/KirimBerandaSkeleton.vue'
import IkonVoucher from '@/components/kirim/IkonVoucher.vue'
import promoInstant15 from '@/assets/Instant15_BisaKirim.png'
import promoSameday20 from '@/assets/Sameday20_BisaKirim.png'
import promoAjakKirim from '@/assets/AjakKirim_BisaKirim.png'
import { useSkeleton } from '@/composables/useSkeleton'
import { useKirimStore } from '@/stores/kirim'
import { useLocationStore } from '@/stores/location'
import { voucherKirim, type VoucherKirim } from '@/api/kirim'
import { KEUNGGULAN, rupiah } from '@/lib/kirim'

const router = useRouter()
const kembali = useKembali()
const kirimStore = useKirimStore()
const locationStore = useLocationStore()

const { tampil: skelTampil, tandaiSiap } = useSkeleton()

const ambil = computed(() => kirimStore.ambil)
const antar = computed(() => kirimStore.antar)

type Lembar = 'ambil' | 'antar' | null
const lembar = ref<Lembar>(null)

const riwayat = ref(locationStore.loadSearchHistory())

/*
 * Voucher diambil dari server, bukan disalin ke klien: katalognya satu, dan
 * salinan di sini akan mulai berbeda pada perubahan pertama tanpa memberi
 * tanda apa pun.
 *
 * Yang ditampilkan di layar ini SYARATNYA saja. Potongan rupiahnya bergantung
 * ongkir, dan ongkirnya belum ada sebelum tujuannya diisi — menyebut angka di
 * sini berarti menjanjikan potongan yang belum tentu berlaku.
 */
/**
 * Banner promo disimpan sebagai daftar, bukan ditulis satu per satu di
 * template. Titik penanda dan perputaran otomatis membaca panjang daftar ini —
 * menambah promo cukup menambah satu baris, tanpa menyentuh tempat lain yang
 * bisa lupa diperbarui.
 *
 * Alt-nya mengikuti yang TERGAMBAR di banner, bukan nama berkasnya.
 */
const bannerHero = [
  { src: promoInstant15, alt: 'INSTANT15, diskon 15% sampai Rp20.000 untuk layanan Instant' },
  { src: promoSameday20, alt: 'SAMEDAY20, diskon 20% sampai Rp25.000 untuk layanan Same-Day' },
  {
    src: promoAjakKirim,
    alt: 'AJAKKIRIM, teman dapat diskon Rp25.000 dan kamu dapat cashback Rp25.000',
  },
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
 * ketika orang sedang membacanya — itu terasa seperti aplikasi merebut kendali.
 */
let jamHero: ReturnType<typeof setInterval> | null = null

function hentikanHero() {
  if (jamHero) clearInterval(jamHero)
  jamHero = null
}

function mulaiHero() {
  hentikanHero()

  // Gerakan yang tidak diminta adalah hal pertama yang dimatikan setelan ini;
  // menjalankannya tetap berarti mengabaikan permintaan yang jelas.
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) return
  if (bannerHero.length < 2) return

  jamHero = setInterval(() => keHero((heroAktif.value + 1) % bannerHero.length), 4500)
}

const voucher = ref<VoucherKirim[]>([])
const jumlahVoucher = ref(0)
const lembarVoucher = ref(false)

onMounted(async () => {
  // Titik ambil datang dari halaman lokasi. Kalau seseorang masuk lewat tautan
  // langsung, draf lokasi terakhir dipakai — dan kalau itu pun tidak ada,
  // kolomnya kosong dan menunggu diisi, bukan diisi tebakan.
  if (!kirimStore.ambil && locationStore.draft) {
    kirimStore.setAmbil({ ...locationStore.draft })
  }
  tandaiSiap()
  window.addEventListener('resize', perbaruiHero, { passive: true })

  try {
    const v = await voucherKirim()
    voucher.value = v.voucher
    jumlahVoucher.value = v.jumlah
  } catch {
    // Voucher gagal diambil bukan alasan menahan halaman: bagian ini
    // disembunyikan, dan pemesanannya tetap bisa jalan.
    voucher.value = []
    jumlahVoucher.value = 0
  }
})

/*
 * Carousel baru dipasang setelah skeleton pergi: selama skeleton yang
 * tergambar, track-nya belum ada di DOM dan clientWidth-nya nol — perputaran
 * yang dimulai saat itu menggulir ke posisi 0 terus.
 */
watch(skelTampil, (tampil) => {
  if (tampil) return
  nextTick(() => {
    perbaruiHero()
    trackHero.value?.addEventListener('scroll', perbaruiHero, { passive: true })
    trackHero.value?.addEventListener('pointerdown', hentikanHero, { passive: true })
    mulaiHero()
  })
})

onBeforeUnmount(() => {
  hentikanHero()
  trackHero.value?.removeEventListener('scroll', perbaruiHero)
  trackHero.value?.removeEventListener('pointerdown', hentikanHero)
  window.removeEventListener('resize', perbaruiHero)
})

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  if (lembar.value === 'ambil') kirimStore.setAmbil({ ...(kirimStore.ambil ?? {}), ...l })
  else kirimStore.setAntar({ ...(kirimStore.antar ?? {}), ...l })

  locationStore.addSearchHistory(l)
  riwayat.value = locationStore.loadSearchHistory()
  lembar.value = null
}

function pakaiRiwayat(r: { label: string; address: string; lat: number; lng: number }) {
  const titik = { alamat: r.address, lat: r.lat, lng: r.lng }
  if (!antar.value) kirimStore.setAntar(titik)
  else kirimStore.setAmbil(titik)
}

const siap = computed(() => !!ambil.value && !!antar.value)

function lanjut() {
  if (!siap.value) return
  router.push({ name: 'task-kirim-detail' })
}
</script>

<template>
  <KirimBerandaSkeleton v-if="skelTampil" />

  <div v-else class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
    <!--
      Banner promo bergeser sendiri, pola yang sama dengan BisaBersih. Pita di
      bawah gambar memakai warna baris terakhir bannernya (#01226A, diambil dari
      pikselnya) supaya tidak ada garis sambungan — di situlah titik penanda
      duduk; ditaruh di atas gambar, ia menimpa tulisan promonya.
    -->
    <section class="relative w-full pb-12" style="background: #01226a">
      <div
        ref="trackHero"
        class="flex overflow-x-auto no-scrollbar snap-x snap-mandatory scroll-smooth"
      >
        <div v-for="b in bannerHero" :key="b.src" class="shrink-0 w-full snap-center">
          <img :src="b.src" :alt="b.alt" class="block w-full h-auto" />
        </div>
      </div>

      <button
        type="button"
        aria-label="Kembali"
        class="absolute top-4 left-4 z-10 w-11 h-11 rounded-full bg-(--color-surface-0)/95 shadow-lg flex items-center justify-center active:scale-95 transition-transform"
        @click="kembali"
      >
        <Icon name="arrow-left" class="w-5 h-5" />
      </button>

      <!--
        Hanya titik, tanpa panah: bannernya sudah bisa digeser dengan jari, dan
        panah melingkar di atas ilustrasi menutupi bagian yang ingin dilihat.
        Yang aktif dibuat memanjang, bukan sekadar lebih terang — bedanya tetap
        terbaca oleh mata yang sulit membedakan warna.
      -->
      <div
        v-if="bannerHero.length > 1"
        class="absolute inset-x-0 bottom-5 h-6 flex items-center justify-center gap-1.5"
      >
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
    </section>

    <main class="max-w-[430px] mx-auto px-4 -mt-5 relative z-10 flex flex-col gap-3.5">
      <!-- Titik ambil & antar -->
      <section class="bg-white rounded-2xl shadow-lg p-4 border-2 border-white">
        <div class="flex items-start gap-3">
          <div class="flex-1 min-w-0">
            <button
              type="button"
              class="w-full flex items-start gap-3 text-left"
              @click="lembar = 'ambil'"
            >
              <Icon name="pin" class="w-[22px] h-[22px] mt-0.5 text-(--color-azure) shrink-0" />
              <span class="flex-1 min-w-0">
                <span class="block text-[11px] text-(--color-on-surface-variant)">Ambil paket di</span>
                <span class="block truncate text-[13.5px] font-bold">
                  {{ ambil?.alamat ?? 'Pilih titik ambil' }}
                </span>
              </span>
            </button>

            <div class="my-2.5 ml-[9px] w-[22px] flex flex-col items-center gap-[3px]" aria-hidden="true">
              <span class="w-[3px] h-[3px] rounded-full bg-(--color-azure)/70"></span>
              <span class="w-[3px] h-[3px] rounded-full bg-orange-400/70"></span>
              <span class="w-[3px] h-[3px] rounded-full bg-orange-500"></span>
            </div>

            <button
              type="button"
              class="w-full flex items-start gap-3 text-left"
              @click="lembar = 'antar'"
            >
              <Icon name="pin" class="w-[22px] h-[22px] mt-0.5 text-orange-500 shrink-0" />
              <span class="flex-1 min-w-0">
                <span class="block text-[11px] text-(--color-on-surface-variant)">Kirim paket ke</span>
                <span
                  class="block truncate text-[13.5px]"
                  :class="antar ? 'font-bold' : 'text-(--color-on-surface-variant)'"
                >
                  {{ antar?.alamat ?? 'Kirim paket ke mana?' }}
                </span>
              </span>
            </button>
          </div>

          <!--
            Tukar arah. Ikut menukar nama dan nomor teleponnya juga — titik yang
            tertukar tanpa kontaknya berarti kurir menelepon orang yang salah.
          -->
          <button
            type="button"
            aria-label="Tukar titik ambil dan tujuan"
            class="w-10 h-10 rounded-full border-2 border-white flex items-center justify-center shrink-0 self-center active:scale-90 transition-transform disabled:opacity-40 shadow-xs"
            :disabled="!ambil || !antar"
            @click="kirimStore.tukar()"
          >
            <Icon name="arrow-right" class="w-4 h-4 -rotate-90" />
          </button>
        </div>

        <!--
          Alamat terakhir menyatu dengan kolom tujuan, bukan berdiri sebagai
          kartu sendiri: daftar ini ADALAH cara mengisi kolom di atasnya, dan
          memisahkannya membuat orang mengira keduanya dua hal berbeda.
        -->
        <div v-if="riwayat.length" class="mt-3 pt-3 border-t border-white">
          <button
            v-for="r in riwayat.slice(0, 4)"
            :key="r.id"
            type="button"
            class="w-full py-2.5 flex items-start gap-3 text-left active:opacity-70 transition-opacity"
            @click="pakaiRiwayat(r)"
          >
            <Icon name="clock" class="w-4 h-4 mt-0.5 shrink-0 text-(--color-on-surface-variant)" />
            <span class="flex-1 min-w-0">
              <span class="block text-[13px] font-bold leading-snug truncate">{{ r.label }}</span>
              <span class="block truncate text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
                {{ r.address }}
              </span>
            </span>
          </button>
        </div>
      </section>

      <!-- Voucher: syaratnya saja, angkanya menyusul di layar detail -->
      <button
        v-if="jumlahVoucher > 0"
        type="button"
        class="bg-white rounded-2xl p-4 flex items-center gap-3 text-left active:scale-[0.99] transition-transform border-2 border-white shadow-xs"
        @click="lembarVoucher = true"
      >
        <IkonVoucher :ukuran="40" />
        <div class="flex-1 min-w-0">
          <p class="text-[13.5px] font-extrabold">
            {{ jumlahVoucher }} voucher buat kamu
          </p>
          <p class="text-[11.5px] text-(--color-on-surface-variant) truncate">
            Potongannya muncul setelah tujuannya diisi
          </p>
        </div>
        <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface-variant)" />
      </button>

      <!-- Yang benar-benar bisa dilakukan layanan ini -->
      <section class="bg-white rounded-2xl p-5 border-2 border-white shadow-xs">
        <h2 class="text-[14px] font-display font-extrabold mb-1">BisaKirim siap bantu</h2>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-3.5">
          Kirim paket, dokumen, atau kunci — dijemput dan diantar hari itu juga.
        </p>

        <div class="flex flex-col gap-2.5">
          <div
            v-for="k in KEUNGGULAN"
            :key="k.judul"
            class="flex items-start gap-3 rounded-xl bg-white border-2 border-white shadow-xs p-3.5"
          >
            <template v-if="k.judul === 'Proteksi paket'">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="40"
                height="40"
                viewBox="0 0 40 40"
                fill="none"
                class="shrink-0"
              >
                <defs>
                  <linearGradient
                    id="protectionBg"
                    x1="4"
                    y1="4"
                    x2="36"
                    y2="36"
                    gradientUnits="userSpaceOnUse"
                  >
                    <stop offset="0" stop-color="#167FE8"/>
                    <stop offset="1" stop-color="#3BBEB8"/>
                  </linearGradient>

                  <linearGradient
                    id="shield"
                    x1="12"
                    y1="9"
                    x2="29"
                    y2="31"
                    gradientUnits="userSpaceOnUse"
                  >
                    <stop offset="0" stop-color="#FFFFFF"/>
                    <stop offset="1" stop-color="#EAF8FF"/>
                  </linearGradient>
                </defs>

                <!-- Background -->
                <rect
                  width="40"
                  height="40"
                  rx="12"
                  fill="url(#protectionBg)"
                />

                <!-- Shield -->
                <path
                  d="M20 8.5
                     L29 12.2
                     V19
                     C29 25.1 25.3 29.4 20 31.5
                     C14.7 29.4 11 25.1 11 19
                     V12.2
                     L20 8.5Z"
                  fill="url(#shield)"
                />

                <!-- Package inside shield -->
                <path
                  d="M15.2 17.2
                     L20 14.8
                     L24.8 17.2
                     V23.1
                     L20 25.6
                     L15.2 23.1
                     V17.2Z"
                  fill="#167FE8"
                />

                <!-- Package top -->
                <path
                  d="M15.2 17.2L20 19.7L24.8 17.2"
                  stroke="#C8FF00"
                  stroke-width="1.4"
                  stroke-linejoin="round"
                />

                <!-- Package center -->
                <path
                  d="M20 19.7V25.5"
                  stroke="#C8FF00"
                  stroke-width="1.4"
                  stroke-linecap="round"
                />

                <!-- Protection check -->
                <path
                  d="M17.5 21.8L19.1 23.3L22.5 20"
                  stroke="#FFFFFF"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />

                <!-- Small lime accent -->
                <circle
                  cx="29.5"
                  cy="10"
                  r="2"
                  fill="#C8FF00"
                />
              </svg>
            </template>
            <template v-else-if="k.judul === 'Kode terima paket'">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="40"
                height="40"
                viewBox="0 0 40 40"
                fill="none"
                class="shrink-0"
              >
                <defs>
                  <linearGradient
                    id="receiveCodeBg"
                    x1="4"
                    y1="4"
                    x2="36"
                    y2="36"
                    gradientUnits="userSpaceOnUse"
                  >
                    <stop offset="0" stop-color="#167FE8"/>
                    <stop offset="1" stop-color="#3BBEB8"/>
                  </linearGradient>

                  <linearGradient
                    id="packageWhite"
                    x1="11"
                    y1="10"
                    x2="29"
                    y2="30"
                    gradientUnits="userSpaceOnUse"
                  >
                    <stop offset="0" stop-color="#FFFFFF"/>
                    <stop offset="1" stop-color="#EAF8FF"/>
                  </linearGradient>
                </defs>

                <!-- Background -->
                <rect
                  width="40"
                  height="40"
                  rx="12"
                  fill="url(#receiveCodeBg)"
                />

                <!-- Package -->
                <path
                  d="M9.5 15.2L20 10L30.5 15.2V25.8L20 31L9.5 25.8V15.2Z"
                  fill="url(#packageWhite)"
                />

                <!-- Package top -->
                <path
                  d="M9.5 15.2L20 20.5L30.5 15.2"
                  stroke="#167FE8"
                  stroke-width="1.5"
                  stroke-linejoin="round"
                />

                <!-- Package center -->
                <path
                  d="M20 20.5V31"
                  stroke="#167FE8"
                  stroke-width="1.5"
                  stroke-linecap="round"
                />

                <!-- Tape -->
                <path
                  d="M17.3 11.35L22.7 14.05V18.85L17.3 16.15V11.35Z"
                  fill="#C8FF00"
                />

                <!-- Code keypad -->
                <rect
                  x="21.5"
                  y="18"
                  width="10"
                  height="9"
                  rx="2.5"
                  fill="#167FE8"
                />

                <!-- PIN dots -->
                <circle cx="24.2" cy="21" r="0.9" fill="#FFFFFF"/>
                <circle cx="27" cy="21" r="0.9" fill="#FFFFFF"/>
                <circle cx="29.8" cy="21" r="0.9" fill="#FFFFFF"/>

                <circle cx="24.2" cy="24.2" r="0.9" fill="#C8FF00"/>
                <circle cx="27" cy="24.2" r="0.9" fill="#C8FF00"/>
                <circle cx="29.8" cy="24.2" r="0.9" fill="#C8FF00"/>

                <!-- Verification check -->
                <path
                  d="M24.2 26L26.2 27.8L30.5 23.5"
                  stroke="#C8FF00"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />

                <!-- Small accent -->
                <circle
                  cx="31.5"
                  cy="10"
                  r="2"
                  fill="#C8FF00"
                />
              </svg>
            </template>
            <template v-else-if="k.judul === 'Ambil di tempat'">
              <svg
                xmlns="http://www.w3.org/2000/svg"
                width="40"
                height="40"
                viewBox="0 0 40 40"
                fill="none"
                class="shrink-0"
              >
                <defs>
                  <linearGradient
                    id="pickupBg"
                    x1="4"
                    y1="4"
                    x2="36"
                    y2="36"
                    gradientUnits="userSpaceOnUse"
                  >
                    <stop offset="0" stop-color="#167FE8"/>
                    <stop offset="1" stop-color="#3BBEB8"/>
                  </linearGradient>

                  <linearGradient
                    id="boxWhite"
                    x1="10"
                    y1="13"
                    x2="25"
                    y2="29"
                    gradientUnits="userSpaceOnUse"
                  >
                    <stop offset="0" stop-color="#FFFFFF"/>
                    <stop offset="1" stop-color="#EAF8FF"/>
                  </linearGradient>
                </defs>

                <!-- Background -->
                <rect
                  width="40"
                  height="40"
                  rx="12"
                  fill="url(#pickupBg)"
                />

                <!-- Package -->
                <path
                  d="M7.5 17L16 12.7L24.5 17V26L16 30.3L7.5 26V17Z"
                  fill="url(#boxWhite)"
                />

                <!-- Box top -->
                <path
                  d="M7.5 17L16 21.3L24.5 17"
                  stroke="#167FE8"
                  stroke-width="1.4"
                  stroke-linejoin="round"
                />

                <!-- Box center -->
                <path
                  d="M16 21.3V30.3"
                  stroke="#167FE8"
                  stroke-width="1.4"
                  stroke-linecap="round"
                />

                <!-- Box tape -->
                <path
                  d="M13.9 13.75L18.1 15.85V20.2L13.9 18.1V13.75Z"
                  fill="#C8FF00"
                />

                <!-- Courier -->
                <!-- Head -->
                <circle
                  cx="29"
                  cy="11.5"
                  r="3"
                  fill="#FFFFFF"
                />

                <!-- Helmet -->
                <path
                  d="M26.2 11.5C26.2 9.8 27.45 8.5 29 8.5C30.55 8.5 31.8 9.8 31.8 11.5H26.2Z"
                  fill="#C8FF00"
                />

                <!-- Body -->
                <path
                  d="M26 15.5C26 14.4 26.9 13.5 28 13.5H30C31.1 13.5 32 14.4 32 15.5V22H26V15.5Z"
                  fill="#FFFFFF"
                />

                <!-- Arm reaching package -->
                <path
                  d="M27 16.5L23 19.5L20.8 19"
                  stroke="#FFFFFF"
                  stroke-width="1.8"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />

                <!-- Hand -->
                <circle
                  cx="20.7"
                  cy="19"
                  r="1.2"
                  fill="#C8FF00"
                />

                <!-- Lower body / leg -->
                <path
                  d="M28 22L26 27"
                  stroke="#FFFFFF"
                  stroke-width="1.8"
                  stroke-linecap="round"
                />

                <path
                  d="M30.5 22L33 27"
                  stroke="#FFFFFF"
                  stroke-width="1.8"
                  stroke-linecap="round"
                />

                <!-- Motion / pickup arrow -->
                <path
                  d="M11 10H6.5"
                  stroke="#C8FF00"
                  stroke-width="1.5"
                  stroke-linecap="round"
                />

                <path
                  d="M8.5 8L6.5 10L8.5 12"
                  stroke="#C8FF00"
                  stroke-width="1.5"
                  stroke-linecap="round"
                  stroke-linejoin="round"
                />

                <!-- Small accent -->
                <circle
                  cx="34"
                  cy="8"
                  r="1.7"
                  fill="#C8FF00"
                />
              </svg>
            </template>
            <span
              v-else
              class="w-9 h-9 rounded-xl bg-(--color-primary-container) flex items-center justify-center shrink-0"
            >
              <Icon :name="k.ikon" class="w-4.5 h-4.5 text-(--color-on-primary-container)" />
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-[13px] font-bold leading-tight">{{ k.judul }}</p>
              <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant) mt-0.5">
                {{ k.isi }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- Daftar voucher -->
      <div
        v-if="lembarVoucher"
        class="fixed inset-0 z-50 flex items-end"
        @click.self="lembarVoucher = false"
      >
        <div class="absolute inset-0 bg-black/40"></div>
        <div
          class="relative w-full max-w-[430px] mx-auto bg-white rounded-t-3xl p-5 max-h-[80vh] overflow-y-auto"
        >
          <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>
          <h2 class="text-[16px] font-display font-extrabold mb-1">Voucher BisaKirim</h2>
          <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant) mb-4">
            Berapa potongannya bergantung ongkir, jadi angkanya baru muncul setelah tujuan kiriman
            diisi.
          </p>

          <div class="flex flex-col gap-2.5">
            <div
              v-for="v in voucher"
              :key="v.kode"
              class="rounded-2xl border-2 border-white bg-white p-4 shadow-xs"
              :class="v.terpakai ? 'opacity-55' : ''"
            >
              <div class="flex items-center justify-between gap-3">
                <p class="text-[13.5px] font-extrabold">{{ v.nama }}</p>
                <span
                  class="px-2.5 py-0.5 rounded-md bg-(--color-surface-container) text-[10.5px] font-extrabold"
                >
                  {{ v.kode }}
                </span>
              </div>
              <p class="mt-1 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
                {{ v.deskripsi }}
              </p>
              <p class="mt-1.5 text-[11px] text-(--color-on-surface-variant)">
                Mulai ongkir {{ rupiah(v.minimum) }}
              </p>
              <!-- Yang sudah terpakai tetap ditampilkan, tapi ditandai. -->
              <p v-if="v.terpakai" class="mt-1.5 text-[11px] font-semibold text-(--color-error)">
                Sudah dipakai — voucher ini hanya untuk kiriman pertama.
              </p>
            </div>
          </div>
        </div>
      </div>

      <SheetPilihLokasi
        :tampil="lembar !== null"
        :alamat="(lembar === 'ambil' ? ambil?.alamat : antar?.alamat) ?? ''"
        :lat="(lembar === 'ambil' ? ambil?.lat : antar?.lat) ?? ambil?.lat ?? -6.2088"
        :lng="(lembar === 'ambil' ? ambil?.lng : antar?.lng) ?? ambil?.lng ?? 106.8456"
        :judul-peta="lembar === 'ambil' ? 'Set titik ambil' : 'Set tujuan kiriman'"
        @tutup="lembar = null"
        @pilih="terimaLokasi"
      />
    </main>

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.10)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="!siap"
          @click="lanjut"
        >
          Lanjut
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
        <p v-if="!siap" class="mt-2 text-[11.5px] text-center text-(--color-on-surface-variant)">
          Isi titik ambil dan tujuannya dulu supaya ongkirnya bisa dihitung.
        </p>
      </div>
    </footer>
  </div>
</template>
