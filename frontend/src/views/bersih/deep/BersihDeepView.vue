<script setup lang="ts">
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import DatePickerField from '@/components/DatePickerField.vue'
import { useBersihDeepStore } from '@/stores/bersihDeep'
import TimePickerField from '@/components/TimePickerField.vue'

const router = useRouter()
const kembali = useKembali()
const deepStore = useBersihDeepStore()

type PackageType = 'move_in' | 'pasca_renovasi' | 'sanitasi_total'

interface PackageOption {
  id: PackageType
  title: string
  desc: string
  basePrice: number
  icon: string
  /** Dua-tiga kata isi pekerjaannya, untuk dibandingkan sekilas. */
  tag: string[]
  /**
   * Pekerjaan yang dulu dijual terpisah sebagai "Layanan Tambahan" dan kini
   * sudah termasuk paket.
   *
   * Harganya TIDAK digratiskan, melainkan dilebur ke basePrice — kalau tidak,
   * setiap paket kehilangan margin sebesar add-on yang diserapnya.
   */
  termasuk: string[]
  /** Sorotan "paling sering dipilih" — hanya boleh satu paket. */
  sorot?: string
}

const packages: PackageOption[] = [
  {
    id: 'move_in',
    title: 'Paket Move-In',
    desc: 'Untuk rumah baru/pindahan. Fokus pada debu halus dan sanitasi.',
    // 550.000 + sedot tungau kasur (75.000)
    basePrice: 625000,
    icon: 'home_work',
    tag: ['Sanitasi Lemari', 'Sisa Cat/Semen'],
    termasuk: ['Sedot tungau kasur'],
    sorot: 'Paling Populer',
  },
  {
    id: 'pasca_renovasi',
    title: 'Paket Pasca Renovasi',
    desc: 'Pembersihan sisa semen, cat, dan debu konstruksi.',
    // 750.000 + scrubbing lantai mesin untuk 3 ruangan (3 × 50.000)
    basePrice: 900000,
    tag: ['Debu Konstruksi', 'Poles Lantai'],
    termasuk: ['Scrubbing lantai mesin'],
    // Nama ikon harus benar-benar ada di Material Symbols: yang tidak ada
    // dirender sebagai TEKS MENTAH — 'drive_file_stream' sebelumnya tampil
    // sebagai tulisan "VE_FILE_" melebar keluar kartu.
    icon: 'construction',
  },
  {
    id: 'sanitasi_total',
    title: 'Paket Sanitasi Total',
    desc: 'Fokus pada pembasmian bakteri dan tungau.',
    // 600.000 + fogging (100.000) + sedot tungau kasur (75.000)
    basePrice: 775000,
    tag: ['Fogging', 'Anti Tungau'],
    termasuk: ['Fogging disinfektan', 'Sedot tungau kasur'],
    icon: 'sanitizer',
  },
]

/**
 * Isi halaman ditulis sebagai data, bukan diketik berulang di template.
 *
 * Empat kartu fitur yang sebelumnya disalin-tempel berbeda hanya pada tiga
 * nilainya; menyalin markupnya berarti empat tempat yang harus diperbaiki tiap
 * kali gayanya berubah.
 */
const fitur = [
  {
    ikon: 'construction',
    nama: 'Alat Profesional',
    catatan: 'Steam cleaner & vakum industri',
    warnaText: 'text-(--color-azure)',
  },
  {
    ikon: 'coronavirus',
    nama: 'Disinfeksi Total',
    catatan: 'Basmi kuman & bakteri di area sentuh',
    warnaText: 'text-[#10b981]',
  },
  {
    ikon: 'soap',
    nama: 'Kerak Tuntas',
    catatan: 'Chemical khusus kerak menahun',
    warnaText: 'text-[#e11d48]',
  },
  {
    ikon: 'eco',
    nama: 'Ramah Lingkungan',
    catatan: 'Aman untuk anak & hewan peliharaan',
    warnaText: 'text-[#84cc16]',
  },
]

/** Apa yang benar-benar dikerjakan — supaya "deep" tidak sekadar kata. */
const cakupan = [
  {
    judul: 'Detailing Furniture & Perabotan',
    catatan: 'Pembersihan luar dalam lemari, laci, dan polishing.',
  },
  {
    judul: 'Wall Dusting & Plafon',
    catatan: 'Debu di dinding, sudut plafon, dan lampu gantung.',
  },
  {
    judul: 'Sanitasi Handle & Saklar',
    catatan: 'Disinfeksi area yang paling sering disentuh.',
  },
  {
    judul: 'Scrubbing & Polishing Lantai',
    catatan: 'Pembersihan mendalam sela keramik dan pemolesan lantai.',
  },
]


const selectedPackage = ref<PackageType>('move_in')
const luasRuangan = ref(50)
const jumlahRuangan = ref(3)

/* ---------------- Jadwal ---------------- */

/**
 * Memakai DatePickerField & TimePickerField — komponen yang sama dengan layar
 * BisaBersih lain (Pesan Kantor, Konfirmasi) dan BisaAngkut.
 *
 * Sebelumnya halaman ini punya pemilih tanggalnya sendiri berupa lima kotak
 * hari: cara memilih jadwal jadi berbeda hanya karena halamannya berbeda,
 * padahal layanannya sama — dan lima kotak itu juga menutup pilihan tanggal
 * yang lebih jauh.
 */
const tanggal = ref('')
const waktu = ref('')

/** Tepi merah baru muncul setelah tombol pesan ditekan, bukan sejak awal. */
const ditandaiJadwal = ref(false)

const rincianHarga = computed(() => {
  const pkg = packages.find((p) => p.id === selectedPackage.value)
  const baris: { label: string; nilai: number }[] = [
    { label: pkg?.title ?? 'Deep Cleaning', nilai: pkg?.basePrice ?? 550000 },
  ]

  return baris
})

const estimasiHarga = computed(() => rincianHarga.value.reduce((jml, b) => jml + b.nilai, 0))

/** Rincian dibuka atas permintaan; tertutup lebih dulu supaya bilah bawah ringkas. */
const rincianTampil = ref(false)

function rp(n: number) {
  return 'Rp ' + n.toLocaleString('id-ID')
}

function pesanSekarang() {
  const pkg = packages.find((p) => p.id === selectedPackage.value)

  if (!tanggal.value || !waktu.value) {
    // Ditahan di sini, bukan dibiarkan lolos lalu gagal di layar berikutnya.
    ditandaiJadwal.value = true
    document.getElementById('jadwal')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
    return
  }

  /*
   * Pilihan dititipkan ke store, bukan ke query URL: halaman konfirmasi butuh
   * daftar layanan yang sudah termasuk paket, dan menyusun ulang daftar itu
   * dari potongan teks di URL hanya menambah satu tempat lagi yang bisa salah.
   */
  deepStore.set({
    paketId: selectedPackage.value,
    paketNama: pkg?.title ?? 'Deep Cleaning',
    paketDeskripsi: pkg?.desc ?? '',
    hargaPaket: pkg?.basePrice ?? 0,
    termasuk: [...(pkg?.termasuk ?? [])],
    luasM2: luasRuangan.value,
    jumlahRuangan: jumlahRuangan.value,
    tanggal: tanggal.value,
    waktu: waktu.value,
  })

  router.push({ name: 'task-bersih-deep-konfirmasi' })
}
</script>

<template>
  <div class="relative min-h-dvh bg-(--color-surface) text-(--color-on-surface) font-body antialiased pb-32">
    <!--
      Menempel pada HALAMAN, bukan pada layar: dengan posisi tetap (fixed) ia
      ikut turun saat digulung dan berakhir menutupi isi di bawahnya.
    -->
    <div class="absolute top-4 left-4 z-50 flex items-center gap-2.5 pointer-events-none">
      <button
        type="button"
        aria-label="Kembali"
        class="w-10 h-10 rounded-full bg-white/90 dark:bg-[#1b2126]/90 text-slate-800 dark:text-white flex items-center justify-center shadow-md active:scale-95 transition-transform pointer-events-auto"
        @click="kembali"
      >
        <span class="material-symbols-outlined text-[20px]" data-icon="arrow_back">arrow_back</span>
      </button>

      <div class="inline-flex items-center px-3.5 py-1.5 rounded-full bg-white/90 dark:bg-[#1b2126]/90 text-(--color-azure) text-[11px] font-semibold shadow-md pointer-events-auto backdrop-blur-xs">
        <span class="material-symbols-outlined text-[16px] mr-1" data-icon="verified">verified</span>
        Premium Service
      </div>
    </div>

    <!-- Main Content Canvas -->
    <main class="max-w-[1200px] mx-auto md:px-6">
      <!-- Hero Section -->
      <section class="relative w-full min-h-[310px] sm:min-h-[330px] md:min-h-[353px] overflow-hidden">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 1200 345"
          width="1200"
          height="345"
          class="w-full h-[310px] sm:h-[330px] md:h-[353px] block"
          preserveAspectRatio="xMidYMid slice"
        >
          <defs>

            <!-- Background wall with solid rich color -->
            <linearGradient id="wall" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#E2DDD2"/>
              <stop offset="100%" stop-color="#CEC6B5"/>
            </linearGradient>

            <!-- Floor -->
            <linearGradient id="floor" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#D8D5CC"/>
              <stop offset="100%" stop-color="#AFAEA7"/>
            </linearGradient>

            <!-- Window light -->
            <linearGradient id="window" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#E9FAFF"/>
              <stop offset="50%" stop-color="#CDEFF5"/>
              <stop offset="100%" stop-color="#B5DCE5"/>
            </linearGradient>

            <!-- Sofa -->
            <linearGradient id="sofa" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#D8D3C7"/>
              <stop offset="100%" stop-color="#AAA59B"/>
            </linearGradient>

            <!-- Green plant -->
            <linearGradient id="leaf" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#A8D957"/>
              <stop offset="100%" stop-color="#4E8C4A"/>
            </linearGradient>

            <!-- Steam cleaner -->
            <linearGradient id="machine" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#3BBEB8"/>
              <stop offset="100%" stop-color="#167FE8"/>
            </linearGradient>

            <linearGradient id="lime" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#DFFF67"/>
              <stop offset="100%" stop-color="#8CC63F"/>
            </linearGradient>

            <linearGradient id="heroOverlay" x1="0" y1="0" x2="0" y2="1">
              <stop offset="0%" stop-color="#1A1A1A" stop-opacity="0"/>
              <stop offset="35%" stop-color="#1A1A1A" stop-opacity="0.2"/>
              <stop offset="100%" stop-color="#1A1A1A" stop-opacity="0.85"/>
            </linearGradient>

            <!-- Shadows without blur -->
            <filter id="softShadow"
              x="-30%"
              y="-30%"
              width="160%"
              height="180%">
              <feGaussianBlur stdDeviation="0"/>
            </filter>

            <filter id="objectShadow"
              x="-30%"
              y="-30%"
              width="160%"
              height="180%">
              <feDropShadow
                dx="0"
                dy="4"
                stdDeviation="0"
                flood-color="#354044"
                flood-opacity=".15"/>
            </filter>

            <!-- Curved hill image mask -->
            <clipPath id="roundedImage">
              <path
                d="M0 0
                   H1200
                   V321
                   C1180 333 1160 340 1130 340
                   C1092 340 1070 321 1041 298
                   C1026 286 1012 286 996 298
                   C966 321 944 340 903 340
                   C865 340 844 321 813 295
                   C797 282 784 282 769 295
                   C737 322 716 339 676 339
                   C639 339 618 321 587 300
                   C571 289 558 289 542 300
                   C512 321 490 337 451 337
                   C414 337 395 318 365 292
                   C350 279 337 279 322 292
                   C291 318 270 336 232 336
                   C198 336 184 325 153 307
                   C139 299 126 299 112 307
                   C75 328 55 342 0 342
                   Z"/>
            </clipPath>

          </defs>


          <!-- ================================================= -->
          <!-- IMAGE AREA -->
          <!-- ================================================= -->

          <g clip-path="url(#roundedImage)">

            <!-- WALL -->
            <rect
              x="0"
              y="0"
              width="1200"
              height="230"
              fill="url(#wall)"/>


            <!-- ================================================= -->
            <!-- LARGE WINDOW -->
            <!-- ================================================= -->

            <rect
              x="730"
              y="20"
              width="310"
              height="190"
              rx="4"
              fill="#FFFFFF"
              opacity=".95"/>

            <rect
              x="742"
              y="32"
              width="286"
              height="166"
              fill="url(#window)"/>

            <!-- Window vertical frame -->
            <rect
              x="875"
              y="32"
              width="7"
              height="166"
              fill="#FFFFFF"
              opacity=".8"/>

            <!-- Window horizontal frame -->
            <rect
              x="742"
              y="111"
              width="286"
              height="7"
              fill="#FFFFFF"
              opacity=".8"/>


            <!-- ================================================= -->
            <!-- MORNING SUN -->
            <!-- ================================================= -->

            <circle
              cx="950"
              cy="76"
              r="30"
              fill="#FFF3B2"
              opacity=".95"/>

            <g
              stroke="#F6D979"
              stroke-width="4"
              stroke-linecap="round"
              opacity=".7">

              <path d="M950 33V21"/>
              <path d="M950 131V143"/>
              <path d="M907 76H895"/>
              <path d="M993 76H1005"/>
              <path d="M919 45L910 36"/>
              <path d="M981 107L990 116"/>
              <path d="M981 45L990 36"/>
              <path d="M919 107L910 116"/>

            </g>


            <!-- ================================================= -->
            <!-- MORNING LIGHT ON FLOOR -->
            <!-- ================================================= -->

            <path
              d="M760 198
                 L1010 198
                 L1130 350
                 L590 350Z"
              fill="#FFF7C9"
              opacity=".20"/>


            <!-- ================================================= -->
            <!-- WALL DECOR -->
            <!-- ================================================= -->

            <rect
              x="90"
              y="46"
              width="185"
              height="116"
              rx="5"
              fill="#FFFFFF"
              filter="url(#objectShadow)"/>

            <rect
              x="101"
              y="57"
              width="163"
              height="94"
              rx="3"
              fill="#DCEBED"/>

            <!-- Minimal art -->
            <path
              d="M115 130
                 C140 95 160 102 180 122
                 C201 143 222 91 250 111"
              fill="none"
              stroke="#86B7B5"
              stroke-width="7"
              stroke-linecap="round"/>

            <circle
              cx="217"
              cy="83"
              r="12"
              fill="#C8FF00"
              opacity=".75"/>


            <!-- ================================================= -->
            <!-- FLOOR -->
            <!-- ================================================= -->

            <path
              d="M0 205
                 L1200 205
                 L1200 353
                 L0 353Z"
              fill="url(#floor)"/>


            <!-- Floor planks -->
            <g
              stroke="#9D9C96"
              stroke-width="2"
              opacity=".32">

              <path d="M0 255H1200"/>
              <path d="M0 302H1200"/>

              <path d="M140 205L95 353"/>
              <path d="M390 205L370 353"/>
              <path d="M650 205L665 353"/>
              <path d="M910 205L950 353"/>
              <path d="M1090 205L1160 353"/>

            </g>


            <!-- ================================================= -->
            <!-- FLOOR REFLECTION -->
            <!-- ================================================= -->

            <ellipse
              cx="600"
              cy="315"
              rx="420"
              ry="25"
              fill="#FFFFFF"
              opacity=".18"
              filter="url(#softShadow)"/>

            <ellipse
              cx="805"
              cy="272"
              rx="175"
              ry="13"
              fill="#FFFFFF"
              opacity=".18"/>


            <!-- ================================================= -->
            <!-- SOFA -->
            <!-- ================================================= -->

            <g filter="url(#objectShadow)">

              <!-- Sofa back -->
              <rect
                x="230"
                y="125"
                width="405"
                height="100"
                rx="28"
                fill="url(#sofa)"/>

              <!-- Sofa arms -->
              <rect
                x="205"
                y="155"
                width="58"
                height="82"
                rx="22"
                fill="#A7A298"/>

              <rect
                x="603"
                y="155"
                width="58"
                height="82"
                rx="22"
                fill="#A7A298"/>

              <!-- Seat -->
              <rect
                x="240"
                y="194"
                width="385"
                height="57"
                rx="20"
                fill="#C5C0B5"/>

              <!-- Cushions -->
              <rect
                x="265"
                y="142"
                width="115"
                height="70"
                rx="18"
                fill="#E0DCD2"/>

              <rect
                x="393"
                y="142"
                width="115"
                height="70"
                rx="18"
                fill="#D5D1C7"/>

              <rect
                x="521"
                y="142"
                width="88"
                height="70"
                rx="18"
                fill="#E1DED5"/>

              <!-- Sofa legs -->
              <rect
                x="250"
                y="237"
                width="12"
                height="30"
                rx="4"
                fill="#6D706D"/>

              <rect
                x="600"
                y="237"
                width="12"
                height="30"
                rx="4"
                fill="#6D706D"/>

            </g>


            <!-- ================================================= -->
            <!-- PILLOWS -->
            <!-- ================================================= -->

            <rect
              x="285"
              y="161"
              width="58"
              height="48"
              rx="12"
              transform="rotate(-8 285 161)"
              fill="#3BBEB8"/>

            <rect
              x="535"
              y="160"
              width="56"
              height="47"
              rx="12"
              transform="rotate(8 535 160)"
              fill="#E4D27C"/>


            <!-- ================================================= -->
            <!-- COFFEE TABLE -->
            <!-- ================================================= -->

            <g filter="url(#objectShadow)">

              <ellipse
                cx="745"
                cy="259"
                rx="130"
                ry="34"
                fill="#7E817C"/>

              <ellipse
                cx="745"
                cy="252"
                rx="130"
                ry="31"
                fill="#D9D5CA"/>

              <path
                d="M675 270L690 345"
                stroke="#777973"
                stroke-width="10"
                stroke-linecap="round"/>

              <path
                d="M815 270L800 345"
                stroke="#777973"
                stroke-width="10"
                stroke-linecap="round"/>

              <!-- Cup -->
              <ellipse
                cx="750"
                cy="241"
                rx="22"
                ry="7"
                fill="#A87957"/>

              <rect
                x="730"
                y="222"
                width="40"
                height="22"
                rx="7"
                fill="#F4F1E8"/>

              <path
                d="M770 228C790 224 790 243 770 240"
                fill="none"
                stroke="#F4F1E8"
                stroke-width="6"/>

            </g>


            <!-- ================================================= -->
            <!-- PLANT -->
            <!-- ================================================= -->

            <g filter="url(#objectShadow)">

              <!-- Pot -->
              <path
                d="M1000 190
                   L1070 190
                   L1058 270
                   L1012 270Z"
                fill="#D5B48D"/>

              <ellipse
                cx="1035"
                cy="190"
                rx="35"
                ry="10"
                fill="#B7926B"/>

              <!-- Stem -->
              <path
                d="M1035 194C1034 160 1030 127 1021 104"
                fill="none"
                stroke="#527849"
                stroke-width="7"
                stroke-linecap="round"/>

              <path
                d="M1035 194C1041 158 1055 139 1071 119"
                fill="none"
                stroke="#527849"
                stroke-width="6"
                stroke-linecap="round"/>

              <!-- Leaves -->
              <ellipse
                cx="1017"
                cy="105"
                rx="13"
                ry="30"
                transform="rotate(-30 1017 105)"
                fill="url(#leaf)"/>

              <ellipse
                cx="1071"
                cy="117"
                rx="13"
                ry="30"
                transform="rotate(38 1071 117)"
                fill="url(#leaf)"/>

              <ellipse
                cx="1047"
                cy="91"
                rx="12"
                ry="28"
                transform="rotate(15 1047 91)"
                fill="#74A94C"/>

              <ellipse
                cx="1009"
                cy="136"
                rx="11"
                ry="25"
                transform="rotate(-48 1009 136)"
                fill="#659B49"/>

            </g>


            <!-- ================================================= -->
            <!-- STEAM CLEANER - FOREGROUND -->
            <!-- ================================================= -->

            <g filter="url(#objectShadow)">

              <!-- Machine body -->
              <path
                d="M82 235
                   C82 221 93 211 107 211
                   H145
                   C159 211 170 222 170 236
                   V293
                   H82Z"
                fill="url(#machine)"/>

              <!-- Lime panel -->
              <rect
                x="96"
                y="229"
                width="60"
                height="38"
                rx="9"
                fill="#FFFFFF"
                opacity=".95"/>

              <circle
                cx="112"
                cy="248"
                r="8"
                fill="#C8FF00"/>

              <rect
                x="127"
                y="241"
                width="20"
                height="5"
                rx="2"
                fill="#167FE8"/>

              <rect
                x="127"
                y="251"
                width="14"
                height="5"
                rx="2"
                fill="#3BBEB8"/>


              <!-- Handle -->
              <path
                d="M105 215
                   L105 177
                   C105 163 116 154 130 154
                   H148"
                fill="none"
                stroke="#0A326B"
                stroke-width="9"
                stroke-linecap="round"/>

              <!-- Handle grip -->
              <path
                d="M145 154H169"
                stroke="#C8FF00"
                stroke-width="10"
                stroke-linecap="round"/>


              <!-- Hose -->
              <path
                d="M165 264
                   C205 263 220 278 242 292
                   C260 304 278 302 292 290"
                fill="none"
                stroke="#243D55"
                stroke-width="9"
                stroke-linecap="round"/>


              <!-- Cleaning head -->
              <path
                d="M278 285
                   H338
                   C346 285 351 292 348 299
                   L342 308
                   H275
                   C267 308 263 301 268 294Z"
                fill="#167FE8"/>

              <rect
                x="280"
                y="303"
                width="61"
                height="9"
                rx="4"
                fill="#C8FF00"/>


              <!-- Clean shine -->
              <g
                stroke="#FFFFFF"
                stroke-linecap="round"
                opacity=".85">

                <path
                  d="M355 278V294"
                  stroke-width="3"/>

                <path
                  d="M347 286H363"
                  stroke-width="3"/>

                <path
                  d="M370 267V277"
                  stroke-width="2"/>

                <path
                  d="M365 272H375"
                  stroke-width="2"/>

              </g>

            </g>


            <!-- ================================================= -->
            <!-- CLEAN FLOOR SHINE -->
            <!-- ================================================= -->

            <g
              stroke="#FFFFFF"
              stroke-linecap="round"
              opacity=".6">

              <path
                d="M390 318H485"
                stroke-width="4"/>

              <path
                d="M420 330H550"
                stroke-width="3"/>

              <path
                d="M875 315H960"
                stroke-width="4"/>

            </g>


            <!-- ================================================= -->
            <!-- LIME BRAND ACCENT -->
            <!-- ================================================= -->

            <circle
              cx="1150"
              cy="285"
              r="42"
              fill="#C8FF00"
              opacity=".16"/>

            <circle
              cx="1150"
              cy="285"
              r="25"
              fill="#3BBEB8"
              opacity=".16"/>

            <!-- ========================================= -->
            <!-- CURVED MOUNTAIN / HILL BORDER -->
            <!-- ========================================= -->

            <path
              d="M0 342

                 C55 342 75 328 112 307
                 C126 299 139 299 153 307
                 C184 325 198 336 232 336

                 C270 336 291 318 322 292
                 C337 279 350 279 365 292
                 C395 318 414 337 451 337

                 C490 337 512 321 542 300
                 C558 289 571 289 587 300
                 C618 321 639 339 676 339

                 C716 339 737 322 769 295
                 C784 282 797 282 813 295
                 C844 321 865 340 903 340

                 C944 340 966 321 996 298
                 C1012 286 1026 286 1041 298
                 C1070 321 1092 340 1130 340

                 C1160 340 1180 333 1200 321"

              fill="none"
              stroke="#C8FF00"
              stroke-width="7"
              stroke-linecap="round"
              stroke-linejoin="round"/>

          </g>

        </svg>
      </section>

      <div class="p-4 md:p-6 space-y-8">
        <!-- Judul & deskripsi -->
        <section>
          <h1 class="font-display text-[24px] font-extrabold leading-tight">Deep Cleaning</h1>
          <p class="mt-2 text-(--color-on-surface-variant) text-[15px] leading-relaxed">
            Cocok untuk pindahan rumah, pasca renovasi, atau pembersihan rutin tahunan yang
            membutuhkan perhatian ekstra pada detail.
          </p>
        </section>

        <!-- Fitur utama -->
        <section>
          <div class="flex items-center gap-2 mb-4">
            <h3 class="font-display text-[14px] font-semibold text-(--color-on-surface)">Fitur Utama</h3>
            <span class="h-px bg-(--color-outline)/60 flex-1"></span>
          </div>
          <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            <div
              v-for="f in fitur"
              :key="f.nama"
              class="bg-(--color-surface-0) p-4 rounded-xl border border-(--color-outline)/40 flex flex-col items-center text-center gap-2 shadow-xs"
            >
              <span class="material-symbols-outlined text-[28px] my-1" :class="f.warnaText" :data-icon="f.ikon">{{ f.ikon }}</span>
              <span class="block text-[13.5px] font-bold text-(--color-on-surface)">{{ f.nama }}</span>
              <span class="block text-[11.5px] leading-snug text-(--color-on-surface-variant)">
                {{ f.catatan }}
              </span>
            </div>
          </div>
        </section>

        <!--
          Cakupan pekerjaan. "Deep cleaning" adalah janji yang mudah diucapkan
          dan sulit diperiksa; daftar ini yang membuatnya bisa ditagih.
        -->
        <section class="bg-white p-5 rounded-2xl border border-white shadow-xs">
          <h3 class="font-display text-[14px] font-semibold text-(--color-on-surface) mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-[20px] text-(--color-on-surface)" data-icon="checklist">checklist</span>
            Apa yang dibersihkan?
          </h3>
          <ul class="space-y-3">
            <li v-for="c in cakupan" :key="c.judul" class="flex items-start gap-3">
              <span class="mt-0.5 shrink-0 text-emerald-600 flex items-center justify-center">
                <span class="material-symbols-outlined text-[18px]" data-icon="check">check</span>
              </span>
              <span>
                <span class="block text-[13.5px] font-bold text-(--color-on-surface)">{{ c.judul }}</span>
                <span class="block text-[12px] leading-snug text-(--color-on-surface-variant)">{{ c.catatan }}</span>
              </span>
            </li>
          </ul>
        </section>

        <!-- Package Options -->
        <section>
          <h3 class="font-display text-[14px] font-semibold text-(--color-on-surface) mb-4">Pilih Paket</h3>
          <!--
            Menurun di ponsel, bukan digeser ke samping. Sebagai carousel, kartu
            ketiga selalu terpotong di tepi layar dan pengguna tidak pernah tahu
            ada berapa paket sebenarnya. Harga tiap paket ikut ditampilkan —
            tanpa itu paket hanya bisa dibandingkan lewat deskripsi.
          -->
          <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            <label
              v-for="pkg in packages"
              :key="pkg.id"
              class="relative cursor-pointer group"
            >
              <input
                type="radio"
                name="package"
                :value="pkg.id"
                :checked="selectedPackage === pkg.id"
                class="peer sr-only"
                @change="selectedPackage = pkg.id"
              />
              <div
                class="h-full bg-(--color-surface-0) rounded-xl p-5 transition-all shadow-xs group-hover:shadow-md"
                :class="
                  selectedPackage === pkg.id
                    ? 'bg-(--color-primary-container)/40 shadow-[0_10px_30px_rgba(0,0,0,0.06)]'
                    : ''
                "
              >
                <div class="flex justify-between items-start mb-3">
                  <template v-if="pkg.id === 'move_in'">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 120 120"
                      class="w-12 h-12 block shrink-0"
                      fill="none"
                    >
                      <defs>
                        <linearGradient id="houseMoveIn" x1="25" y1="20" x2="95" y2="100" gradientUnits="userSpaceOnUse">
                          <stop offset="0%" stop-color="#167FE8"/>
                          <stop offset="100%" stop-color="#3BBEB8"/>
                        </linearGradient>
                        <linearGradient id="limeMoveIn" x1="0" y1="0" x2="1" y2="1">
                          <stop offset="0%" stop-color="#E5FF78"/>
                          <stop offset="100%" stop-color="#A6D94A"/>
                        </linearGradient>
                        <filter id="shadowMoveIn" x="-30%" y="-30%" width="160%" height="170%">
                          <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0A326B" flood-opacity=".16"/>
                        </filter>
                      </defs>
                      <circle cx="60" cy="60" r="49" fill="#F3FBFF"/>
                      <g filter="url(#shadowMoveIn)">
                        <path d="M18 67L42 54L65 67L41 81L18 67Z" fill="#FFD84D"/>
                        <path d="M18 67V87L41 101V81L18 67Z" fill="#E9B82F"/>
                        <path d="M41 81V101L65 87V67L41 81Z" fill="#F4C83E"/>
                        <path d="M38 59L47 64V78L41 81" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round" opacity=".9"/>
                      </g>
                      <g filter="url(#shadowMoveIn)">
                        <path d="M39 51 L60 30 L81 51" fill="none" stroke="url(#houseMoveIn)" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M45 49 L60 34 L75 49" fill="none" stroke="#3BBEB8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity=".55"/>
                        <path d="M42 50 V82 C42 86 45 88 49 88 H71 C75 88 78 86 78 82 V50" fill="#FFFFFF" stroke="url(#houseMoveIn)" stroke-width="6" stroke-linejoin="round"/>
                        <rect x="55" y="65" width="11" height="23" rx="3" fill="#0A326B"/>
                        <circle cx="64" cy="77" r="1.6" fill="#C8FF00"/>
                        <rect x="69" y="57" width="9" height="10" rx="2" fill="#DDF7FA"/>
                        <path d="M73.5 57V67 M69 62H78" stroke="#3BBEB8" stroke-width="1.8"/>
                      </g>
                      <g fill="#B9C5CA">
                        <circle cx="25" cy="39" r="2"/>
                        <circle cx="31" cy="31" r="1.5"/>
                        <circle cx="20" cy="48" r="1.2"/>
                        <circle cx="94" cy="43" r="1.8"/>
                        <circle cx="100" cy="52" r="1.2"/>
                        <circle cx="89" cy="33" r="1.3"/>
                        <circle cx="27" cy="94" r="1.5"/>
                        <circle cx="94" cy="87" r="1.5"/>
                      </g>
                      <path d="M91 20 L94 28 L102 31 L94 34 L91 42 L88 34 L80 31 L88 28Z" fill="#C8FF00"/>
                      <path d="M23 57 L25 62 L30 64 L25 66 L23 71 L21 66 L16 64 L21 62Z" fill="#FFD43B"/>
                      <g filter="url(#shadowMoveIn)">
                        <circle cx="91" cy="82" r="15" fill="url(#limeMoveIn)"/>
                        <path d="M84 82 L89 87 L99 76" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                      </g>
                      <path d="M25 105 C39 99 51 99 64 105 C76 110 88 110 101 104" stroke="#3BBEB8" stroke-width="4" stroke-linecap="round"/>
                    </svg>
                  </template>
                  <template v-else-if="pkg.id === 'pasca_renovasi'">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 120 120"
                      class="w-12 h-12 block shrink-0"
                      fill="none"
                    >
                      <defs>
                        <linearGradient id="brandRenov" x1="24" y1="25" x2="96" y2="96" gradientUnits="userSpaceOnUse">
                          <stop offset="0%" stop-color="#167FE8"/>
                          <stop offset="100%" stop-color="#3BBEB8"/>
                        </linearGradient>
                        <linearGradient id="limeRenov" x1="0" y1="0" x2="1" y2="1">
                          <stop offset="0%" stop-color="#E5FF78"/>
                          <stop offset="100%" stop-color="#A6D94A"/>
                        </linearGradient>
                        <linearGradient id="cementRenov" x1="0" y1="0" x2="1" y2="1">
                          <stop offset="0%" stop-color="#D9D9D4"/>
                          <stop offset="100%" stop-color="#A9AAA5"/>
                        </linearGradient>
                        <filter id="shadowRenov" x="-30%" y="-30%" width="160%" height="170%">
                          <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0A326B" flood-opacity=".16"/>
                        </filter>
                      </defs>
                      <circle cx="60" cy="60" r="49" fill="#F3FBFF"/>
                      <g filter="url(#shadowRenov)">
                        <path d="M30 50 L60 25 L90 50" stroke="url(#brandRenov)" stroke-width="7" stroke-linecap="round" stroke-linejoin="round"/>
                        <path d="M38 48 L60 30 L82 48" stroke="#3BBEB8" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" opacity=".5"/>
                        <path d="M35 48 V79 C35 84 39 88 44 88 H76 C81 88 85 84 85 79 V48" fill="#FFFFFF" stroke="url(#brandRenov)" stroke-width="6" stroke-linejoin="round"/>
                        <rect x="54" y="65" width="13" height="23" rx="3" fill="#0A326B"/>
                        <circle cx="64" cy="77" r="1.7" fill="#C8FF00"/>
                        <rect x="70" y="57" width="10" height="10" rx="2" fill="#DDF7FA"/>
                        <path d="M75 57V67 M70 62H80" stroke="#3BBEB8" stroke-width="1.8"/>
                      </g>
                      <g filter="url(#shadowRenov)">
                        <path d="M19 76 H43 V94 C43 97 40 99 37 99 H25 C22 99 19 97 19 94Z" fill="#FFFFFF" stroke="#167FE8" stroke-width="3"/>
                        <ellipse cx="31" cy="76" rx="12" ry="5" fill="#E9B93F" stroke="#167FE8" stroke-width="3"/>
                        <path d="M20 76 C23 80 39 80 42 76" fill="#FFD84D"/>
                        <path d="M23 75 C23 64 39 64 39 75" fill="none" stroke="#0A326B" stroke-width="3" stroke-linecap="round"/>
                        <rect x="24" y="83" width="14" height="7" rx="2" fill="#3BBEB8"/>
                      </g>
                      <g>
                        <path d="M70 93 C72 87 78 84 84 86 C87 81 94 84 96 89 C101 89 103 94 100 98 H68 C66 96 67 94 70 93Z" fill="url(#cementRenov)"/>
                        <path d="M78 86L83 82L88 86L84 91L78 90Z" fill="#B8B9B4"/>
                        <path d="M91 88L96 85L100 90L96 94L91 92Z" fill="#CACBC6"/>
                        <circle cx="67" cy="98" r="2.5" fill="#92938F"/>
                        <circle cx="103" cy="99" r="2" fill="#A5A6A1"/>
                        <rect x="57" y="96" width="5" height="3" rx="1" transform="rotate(-20 57 96)" fill="#A7A8A3"/>
                      </g>
                      <g fill="#AEB8BC">
                        <circle cx="18" cy="48" r="2"/>
                        <circle cx="23" cy="40" r="1.5"/>
                        <circle cx="15" cy="56" r="1.2"/>
                        <circle cx="98" cy="48" r="1.8"/>
                        <circle cx="104" cy="55" r="1.2"/>
                        <circle cx="101" cy="39" r="1.4"/>
                        <circle cx="49" cy="21" r="1.5"/>
                        <circle cx="77" cy="25" r="1.4"/>
                      </g>
                      <g fill="#FFD84D">
                        <circle cx="17" cy="67" r="2"/>
                        <circle cx="12" cy="72" r="1.3"/>
                        <circle cx="22" cy="63" r="1.2"/>
                      </g>
                      <path d="M99 19 L102 27 L110 30 L102 33 L99 41 L96 33 L88 30 L96 27Z" fill="#C8FF00"/>
                      <g filter="url(#shadowRenov)">
                        <circle cx="91" cy="67" r="14" fill="url(#limeRenov)"/>
                        <path d="M84 67 L89 72 L99 61" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round" stroke-linejoin="round"/>
                      </g>
                      <path d="M48 100 L50 105 L55 107 L50 109 L48 114 L46 109 L41 107 L46 105Z" fill="#3BBEB8"/>
                    </svg>
                  </template>
                  <template v-else-if="pkg.id === 'sanitasi_total'">
                    <svg
                      xmlns="http://www.w3.org/2000/svg"
                      viewBox="0 0 120 120"
                      class="w-12 h-12 block shrink-0"
                      fill="none"
                    >
                      <defs>
                        <linearGradient id="brandSanitasi" x1="25" y1="20" x2="95" y2="100" gradientUnits="userSpaceOnUse">
                          <stop offset="0%" stop-color="#167FE8"/>
                          <stop offset="100%" stop-color="#3BBEB8"/>
                        </linearGradient>
                        <linearGradient id="limeSanitasi" x1="0" y1="0" x2="1" y2="1">
                          <stop offset="0%" stop-color="#E5FF78"/>
                          <stop offset="100%" stop-color="#A6D94A"/>
                        </linearGradient>
                        <linearGradient id="shieldSanitasi" x1="42" y1="25" x2="78" y2="95" gradientUnits="userSpaceOnUse">
                          <stop offset="0%" stop-color="#167FE8"/>
                          <stop offset="100%" stop-color="#3BBEB8"/>
                        </linearGradient>
                        <filter id="shadowSanitasi" x="-30%" y="-30%" width="160%" height="170%">
                          <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0A326B" flood-opacity=".16"/>
                        </filter>
                      </defs>
                      <circle cx="60" cy="60" r="49" fill="#F3FBFF"/>
                      <g filter="url(#shadowSanitasi)">
                        <path d="M60 21 L88 32 V57 C88 76 76 90 60 98 C44 90 32 76 32 57 V32Z" fill="url(#shieldSanitasi)"/>
                        <path d="M60 29 L80 37 V57 C80 70 71 81 60 88 C49 81 40 70 40 57 V37Z" fill="#FFFFFF" opacity=".95"/>
                        <path d="M60 35 L75 41 V57 C75 67 68 76 60 82 C52 76 45 67 45 57 V41Z" fill="#EAFBFF"/>
                      </g>
                      <g>
                        <path d="M51 47 C48 43 51 39 55 40 C58 36 64 38 65 42 C70 41 73 45 71 49 C75 52 73 58 69 59 C68 64 63 66 59 63 C55 66 50 63 51 59 C46 58 46 52 51 47Z" fill="#3BBEB8"/>
                        <circle cx="56" cy="49" r="2" fill="#FFFFFF" opacity=".9"/>
                        <circle cx="65" cy="47" r="2" fill="#FFFFFF" opacity=".9"/>
                        <circle cx="63" cy="57" r="2" fill="#FFFFFF" opacity=".9"/>
                        <path d="M73 62 C71 59 74 56 77 58 C80 56 83 59 82 62 C85 65 82 69 79 68 C77 71 73 69 74 66 C71 66 71 64 73 62Z" fill="#167FE8" opacity=".9"/>
                        <circle cx="77" cy="62" r="1.4" fill="#FFFFFF"/>
                        <circle cx="47" cy="64" r="4" fill="#FFD43B"/>
                        <circle cx="46" cy="63" r="1" fill="#FFFFFF"/>
                      </g>
                      <g>
                        <circle cx="84" cy="29" r="13" fill="#C8FF00" stroke="#FFFFFF" stroke-width="3" filter="url(#shadowSanitasi)"/>
                        <path d="M78 23L90 35" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round"/>
                        <path d="M90 23L78 35" stroke="#FFFFFF" stroke-width="4" stroke-linecap="round"/>
                      </g>
                      <g fill="#AEBBC0">
                        <circle cx="22" cy="43" r="2"/>
                        <circle cx="28" cy="35" r="1.3"/>
                        <circle cx="19" cy="51" r="1.2"/>
                        <circle cx="96" cy="48" r="1.8"/>
                        <circle cx="102" cy="57" r="1.2"/>
                        <circle cx="98" cy="40" r="1.4"/>
                        <circle cx="28" cy="83" r="1.5"/>
                        <circle cx="96" cy="82" r="1.5"/>
                      </g>
                      <g stroke="#7D8C91" stroke-width="1.8" stroke-linecap="round">
                        <ellipse cx="24" cy="75" rx="7" ry="5" fill="#D5DADB"/>
                        <path d="M20 72L16 68"/>
                        <path d="M20 75L15 75"/>
                        <path d="M20 78L16 82"/>
                        <path d="M28 72L32 68"/>
                        <path d="M28 75L33 75"/>
                        <path d="M28 78L32 82"/>
                      </g>
                      <path d="M98 78 L101 85 L108 88 L101 91 L98 98 L95 91 L88 88 L95 85Z" fill="#C8FF00"/>
                      <path d="M34 21 L36 27 L42 29 L36 31 L34 37 L32 31 L26 29 L32 27Z" fill="#FFD43B"/>
                      <g filter="url(#shadowSanitasi)">
                        <circle cx="96" cy="101" r="11" fill="url(#limeSanitasi)"/>
                        <path d="M91 101 L95 105 L102 97" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
                      </g>
                      <path d="M39 106 C50 101 61 101 72 106" stroke="#3BBEB8" stroke-width="3.5" stroke-linecap="round"/>
                    </svg>
                  </template>
                  <div
                    v-else
                    class="w-12 h-12 rounded-full flex items-center justify-center transition-colors"
                    :class="
                      selectedPackage === pkg.id
                        ? 'bg-(--color-azure)/10 text-(--color-azure)'
                        : 'bg-(--color-surface-container) text-(--color-on-surface-variant)'
                    "
                  >
                    <span class="material-symbols-outlined" :data-icon="pkg.icon">{{ pkg.icon }}</span>
                  </div>
                  <div
                    class="w-6 h-6 rounded-full flex items-center justify-center transition-colors"
                    :class="
                      selectedPackage === pkg.id
                        ? 'bg-(--color-azure) text-white'
                        : 'bg-(--color-surface-container)'
                    "
                  >
                    <span
                      v-if="selectedPackage === pkg.id"
                      class="material-symbols-outlined text-[16px]"
                      data-icon="check"
                    >
                      check
                    </span>
                  </div>
                </div>
                <h4 class="font-display text-[17px] font-bold text-(--color-on-surface)">{{ pkg.title }}</h4>

                <span
                  v-if="pkg.sorot"
                  class="inline-flex items-center gap-1 mt-1 text-[11.5px] font-bold text-(--color-azure)"
                >
                  <span class="material-symbols-outlined text-[14px]" data-icon="thumb_up">thumb_up</span>
                  {{ pkg.sorot }}
                </span>

                <p class="mt-1.5 text-[13px] leading-snug text-(--color-on-surface-variant)">{{ pkg.desc }}</p>

                <div class="mt-3 flex flex-wrap gap-2">
                  <span
                    v-for="t in pkg.tag"
                    :key="t"
                    class="bg-(--color-surface-container) px-2 py-1 rounded-md text-[11px] font-medium text-(--color-on-surface-variant)"
                  >
                    {{ t }}
                  </span>
                </div>

                <!--
                  Bekas "Layanan Tambahan". Ditulis sebagai yang SUDAH termasuk,
                  bukan sebagai pilihan berbayar terpisah.
                -->
                <ul v-if="pkg.termasuk.length" class="mt-3 space-y-1.5">
                  <li
                    v-for="t in pkg.termasuk"
                    :key="t"
                    class="flex items-center gap-2 text-[12px] text-(--color-on-surface)"
                  >
                    <span class="material-symbols-outlined text-[16px] text-emerald-600" data-icon="check">
                      check
                    </span>
                    Termasuk {{ t.toLowerCase() }}
                  </li>
                </ul>

                <p class="mt-3 text-[15px] font-extrabold text-(--color-azure)">
                  {{ rp(pkg.basePrice) }}
                  <span class="text-[11px] font-medium text-(--color-on-surface-variant)">
                    / mulai dari
                  </span>
                </p>
              </div>
            </label>
          </div>
        </section>

        <!--
          Jadwal ditanyakan di sini, bukan di layar berikutnya: harga sudah
          terbentuk dari pilihan di atas, dan waktu adalah hal terakhir yang
          menentukan pesanan ini bisa dijalankan atau tidak.
        -->
        <section id="jadwal" class="bg-(--color-surface-0) rounded-2xl p-5 border border-(--color-outline)/40 shadow-xs">
          <h3 class="text-[15px] font-display font-extrabold mb-4 flex items-center gap-2">
            <Icon name="clock" class="w-5 h-5 text-(--color-azure)" />
            Jadwal Kunjungan
          </h3>

          <div class="grid grid-cols-2 gap-3">
            <DatePickerField v-model="tanggal" wajib :ditandai="ditandaiJadwal" />
            <TimePickerField v-model="waktu" wajib :ditandai="ditandaiJadwal" />
          </div>

          <p
            v-if="ditandaiJadwal && (!tanggal || !waktu)"
            class="text-[11.5px] font-semibold text-(--color-error) mt-2.5"
          >
            Pilih tanggal dan waktu kunjungan dulu ya.
          </p>
        </section>

        <!-- Jaminan -->
        <section class="bg-(--color-primary-container)/30 rounded-2xl p-4 flex flex-col gap-3">
          <div class="flex items-center gap-3">
            <span class="w-9 h-9 shrink-0 rounded-full bg-(--color-primary-container) text-(--color-on-primary-container) flex items-center justify-center">
              <span class="material-symbols-outlined text-[18px]" data-icon="shield_with_heart">shield_with_heart</span>
            </span>
            <span>
              <span class="block text-[13px] font-bold text-(--color-on-surface)">Garansi Kinclong 100%</span>
              <span class="block text-[11.5px] text-(--color-on-surface-variant)">
                Pembersihan ulang gratis kalau hasilnya tidak sesuai.
              </span>
            </span>
          </div>

          <span class="h-px bg-(--color-outline)/50 w-full"></span>

          <div class="flex items-center gap-3">
            <span class="w-9 h-9 shrink-0 rounded-full bg-(--color-primary-container) text-(--color-on-primary-container) flex items-center justify-center">
              <span class="material-symbols-outlined text-[18px]" data-icon="workspace_premium">workspace_premium</span>
            </span>
            <span>
              <span class="block text-[13px] font-bold text-(--color-on-surface)">Mitra Tersertifikasi</span>
              <span class="block text-[11.5px] text-(--color-on-surface-variant)">
                Cleaner sudah melewati verifikasi dan pelatihan deep cleaning.
              </span>
            </span>
          </div>
        </section>
      </div>
    </main>

    <!-- Bottom Action Bar -->
    <div
      class="fixed bottom-0 w-full z-50 glass-panel rounded-t-xl px-4 md:px-6 py-4 shadow-[0_-10px_30px_rgba(0,0,0,0.05)] md:max-w-[1200px] md:left-1/2 md:-translate-x-1/2 md:rounded-xl md:bottom-4 md:border border-(--color-outline)/30"
    >
      <!-- Rincian: tertutup lebih dulu, dibuka kalau pengguna ingin tahu asal angkanya. -->
      <div v-if="rincianTampil" class="max-w-[1200px] mx-auto mb-3 pb-3 border-b border-(--color-outline)/40">
        <div
          v-for="(b, i) in rincianHarga"
          :key="i"
          class="flex items-start justify-between gap-3 text-[12.5px] py-1"
        >
          <span class="text-(--color-on-surface-variant)">{{ b.label }}</span>
          <span class="font-bold whitespace-nowrap">{{ rp(b.nilai) }}</span>
        </div>
      </div>

      <div class="flex justify-between items-center max-w-[1200px] mx-auto">
        <div>
          <button
            type="button"
            class="flex items-center gap-1 text-[11.5px] font-medium text-(--color-on-surface-variant) mb-0.5"
            :aria-expanded="rincianTampil"
            @click="rincianTampil = !rincianTampil"
          >
            Total Estimasi
            <span
              class="material-symbols-outlined text-[16px] transition-transform"
              :class="rincianTampil ? 'rotate-180' : ''"
              data-icon="expand_more"
            >
              expand_more
            </span>
          </button>
          <p class="font-display text-[22px] font-extrabold text-(--color-azure)">{{ rp(estimasiHarga) }}</p>
        </div>
        <button
          type="button"
          class="bg-(--color-azure) text-white rounded-full px-8 py-3.5 font-bold text-[15px] hover:bg-(--color-on-primary-container) active:scale-95 transition-all min-h-[52px] shadow-[0_10px_28px_rgba(30,155,240,0.3)]"
          @click="pesanSekarang"
        >
          Lanjut Pesan
        </button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.glass-panel {
  background: var(--color-surface-0);
  border-top: 1px solid var(--color-outline);
}

.no-scrollbar {
  scrollbar-width: none;
}
.no-scrollbar::-webkit-scrollbar {
  display: none;
}
</style>
