<script setup lang="ts">
/**
 * Layar "mencari pengemudi" BisaJemput — satu SVG yang memenuhi layar.
 *
 * ADEGANNYA SENGAJA BUKAN PETA seperti BisaKirim. Di situ yang dicari kurir
 * untuk paket, dan yang masuk akal digambar adalah peta dengan dua ujung. Di
 * sini yang menunggu ORANGNYA SENDIRI, berdiri di titik jemput — jadi yang
 * digambar sudut jalan: trotoar, tiang lampu, penumpang menunggu, dan
 * kendaraan-kendaraan yang lewat di dekatnya sambil dijajaki.
 *
 * Kendaraannya motor DAN mobil, karena BisaJemput memang punya keduanya.
 * Menggambar satu jenis saja membuat separuh pemesan menunggu sambil melihat
 * kendaraan yang bukan pesanannya.
 *
 * Teks judulnya TIDAK ikut di dalam SVG: teks SVG tidak melipat sendiri dan
 * tidak ikut ukuran huruf pilihan pengguna, jadi kalimat panjang akan menembus
 * tepi layar sempit tanpa satu pun galat. Yang di sini gambarnya saja.
 *
 * Id di <defs> diberi awalan useId(): id SVG berlaku sedokumen, dan nama polos
 * akan diambil dari definisi pertama yang kebetulan dirender lebih dulu.
 */
import { onMounted, ref, useId } from 'vue'

const uid = useId()
const id = (n: string) => `${uid}-${n}`
const url = (n: string) => `url(#${uid}-${n})`

const svg = ref<SVGSVGElement | null>(null)

onMounted(() => {
  /*
   * SMIL tidak bisa dimatikan lewat CSS — `animation: none` tidak menyentuhnya
   * sama sekali. Satu-satunya cara menghormati "kurangi gerak" adalah
   * menghentikannya lewat API-nya sendiri.
   */
  if (window.matchMedia?.('(prefers-reduced-motion: reduce)').matches) {
    svg.value?.pauseAnimations()
  }
})
</script>

<template>
  <svg
    ref="svg"
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 390 780"
    preserveAspectRatio="xMidYMid slice"
    class="absolute inset-0 w-full h-full"
    role="img"
    aria-label="Penumpang menunggu di titik jemput sementara pengemudi terdekat dicari"
  >
    <defs>
      <linearGradient :id="id('langit')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#EAF6FF" />
        <stop offset="55%" stop-color="#DCEEFF" />
        <stop offset="100%" stop-color="#C6E3FB" />
      </linearGradient>

      <radialGradient :id="id('sorot')" cx="50%" cy="50%">
        <stop offset="0%" stop-color="#1e9bf0" stop-opacity=".18" />
        <stop offset="100%" stop-color="#1e9bf0" stop-opacity="0" />
      </radialGradient>

      <linearGradient :id="id('aspal')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#C9DCEB" />
        <stop offset="100%" stop-color="#AFC8DC" />
      </linearGradient>

      <filter :id="id('bayang')" x="-40%" y="-40%" width="180%" height="190%">
        <feDropShadow dx="0" dy="5" stdDeviation="6" flood-color="#0A326B" flood-opacity=".18" />
      </filter>

      <!-- Kendaraan hanya boleh terlihat selama di atas aspal. -->
      <clipPath :id="id('jalan')">
        <rect x="0" y="404" width="390" height="132" />
      </clipPath>
    </defs>

    <rect width="390" height="780" :fill="url('langit')" />

    <!--
      Seluruh adegan digeser ke atas.

      Judul dan tombol menempati sepertiga bawah layar; tanpa geseran ini kaki
      penumpang dan gelombang di tanah persis menembus tulisannya — terlihat di
      peramban, bukan diperkirakan.
    -->
    <g transform="translate(0,-104)">
    <!-- ── Latar kota, jauh dan pucat supaya tidak melawan tokohnya ── -->
    <g fill="#CBE2F6" opacity=".85">
      <rect x="8" y="238" width="58" height="170" rx="5" />
      <rect x="76" y="200" width="46" height="208" rx="5" />
      <rect x="132" y="262" width="52" height="146" rx="5" />
      <rect x="266" y="218" width="50" height="190" rx="5" />
      <rect x="326" y="256" width="56" height="152" rx="5" />
    </g>
    <g fill="#EAF4FE" opacity=".9">
      <rect x="18" y="256" width="12" height="14" rx="2" />
      <rect x="40" y="256" width="12" height="14" rx="2" />
      <rect x="18" y="288" width="12" height="14" rx="2" />
      <rect x="40" y="288" width="12" height="14" rx="2" />
      <rect x="86" y="220" width="12" height="14" rx="2" />
      <rect x="102" y="220" width="12" height="14" rx="2" />
      <rect x="86" y="252" width="12" height="14" rx="2" />
      <rect x="278" y="238" width="12" height="14" rx="2" />
      <rect x="296" y="238" width="12" height="14" rx="2" />
      <rect x="278" y="270" width="12" height="14" rx="2" />
      <rect x="338" y="276" width="12" height="14" rx="2" />
      <rect x="356" y="276" width="12" height="14" rx="2" />
    </g>

    <!-- ── Aspal dan marka ── -->
    <rect x="0" y="404" width="390" height="132" :fill="url('aspal')" />
    <rect x="0" y="404" width="390" height="5" fill="#E7F1FA" opacity=".8" />
    <g stroke="#F3F9FF" stroke-width="5" stroke-linecap="round" opacity=".9">
      <path d="M-16 470 h34" />
      <path d="M42 470 h34" />
      <path d="M100 470 h34" />
      <path d="M158 470 h34" />
      <path d="M216 470 h34" />
      <path d="M274 470 h34" />
      <path d="M332 470 h34" />
      <animateTransform
        attributeName="transform"
        type="translate"
        values="0 0;-58 0"
        dur="1.6s"
        repeatCount="indefinite"
      />
    </g>

    <!--
      Trotoar tempat penumpang berdiri, dipanjangkan sampai dasar viewBox:
      sesudah adegannya digeser naik, trotoar setinggi 16 akan menyisakan
      langit di bawah jalan — jalan yang melayang.
    -->
    <rect x="0" y="536" width="390" height="360" fill="#DCEAF6" />
    <rect x="0" y="536" width="390" height="4" fill="#C3D8E9" />

    <!-- ── Kendaraan yang lewat, dipotong supaya tidak melayang di luar jalan ── -->
    <g :clip-path="url('jalan')">
      <!-- Mobil, lewat di lajur jauh -->
      <g>
        <g transform="translate(0,0)">
          <g transform="translate(60,436)">
            <rect x="-38" y="-14" width="76" height="22" rx="8" fill="#1e9bf0" />
            <path d="M-26 -14 l8 -14 h34 l10 14 z" fill="#4FB4F5" />
            <path d="M-20 -16 l6 -9 h13 v9 z M2 -16 h12 l7 9 h-19 z" fill="#EAF6FF" />
            <circle cx="-20" cy="10" r="7" fill="#12385C" />
            <circle cx="-20" cy="10" r="3" fill="#DCEAF6" />
            <circle cx="22" cy="10" r="7" fill="#12385C" />
            <circle cx="22" cy="10" r="3" fill="#DCEAF6" />
          </g>
          <animateTransform
            attributeName="transform"
            type="translate"
            values="-140 0;460 0"
            dur="6s"
            repeatCount="indefinite"
          />
        </g>
      </g>

      <!-- Motor, lewat di lajur dekat dan lebih cepat -->
      <g>
        <g transform="translate(0,0)">
          <!--
            Motor OJEK, bukan sepeda dan bukan motor kurir: rodanya berban
            tebal, dan yang duduk di atasnya pengemudi berhelm — bukan kotak
            barang. BisaJemput mengantar ORANG, dan kendaraan yang salah di
            layar tunggu adalah janji yang salah tentang apa yang datang.
          -->
          <g transform="translate(300,500)">
            <circle cx="-24" cy="10" r="12" fill="#12385C" />
            <circle cx="-24" cy="10" r="5" fill="#DCEAF6" />
            <circle cx="24" cy="10" r="12" fill="#12385C" />
            <circle cx="24" cy="10" r="5" fill="#DCEAF6" />
            <path
              d="M-24 10 L-8 -6 H8 l16 16"
              fill="none"
              stroke="#f97316"
              stroke-width="7"
              stroke-linejoin="round"
              stroke-linecap="round"
            />
            <path
              d="M-12 -8 h20 l6 -8"
              fill="none"
              stroke="#12385C"
              stroke-width="4"
              stroke-linecap="round"
            />
            <!-- Pengemudi: badan condong ke depan, kepala berhelm -->
            <path
              d="M-6 -12 l6 -14 h8 l4 10"
              fill="none"
              stroke="#1e9bf0"
              stroke-width="9"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
            <circle cx="1" cy="-32" r="8" fill="#12385C" />
            <path d="M-7 -32 a8 8 0 0 1 16 0 z" fill="#1e9bf0" />
          </g>
          <animateTransform
            attributeName="transform"
            type="translate"
            values="440 0;-160 0"
            dur="4.2s"
            begin="1.1s"
            repeatCount="indefinite"
          />
        </g>
      </g>
    </g>

    <!-- ── Titik jemput: pin, gelombang pencarian, dan penumpangnya ── -->
    <circle cx="195" cy="470" r="150" :fill="url('sorot')" />

    <g fill="none" stroke="#1e9bf0" stroke-width="2.5">
      <ellipse cx="195" cy="548" rx="44" ry="14" opacity="0">
        <animate attributeName="rx" values="44;132" dur="3s" repeatCount="indefinite" />
        <animate attributeName="ry" values="14;42" dur="3s" repeatCount="indefinite" />
        <animate attributeName="opacity" values=".6;0" dur="3s" repeatCount="indefinite" />
      </ellipse>
      <ellipse cx="195" cy="548" rx="44" ry="14" opacity="0">
        <animate attributeName="rx" values="44;132" dur="3s" begin="1s" repeatCount="indefinite" />
        <animate attributeName="ry" values="14;42" dur="3s" begin="1s" repeatCount="indefinite" />
        <animate attributeName="opacity" values=".6;0" dur="3s" begin="1s" repeatCount="indefinite" />
      </ellipse>
      <ellipse cx="195" cy="548" rx="44" ry="14" opacity="0">
        <animate attributeName="rx" values="44;132" dur="3s" begin="2s" repeatCount="indefinite" />
        <animate attributeName="ry" values="14;42" dur="3s" begin="2s" repeatCount="indefinite" />
        <animate attributeName="opacity" values=".6;0" dur="3s" begin="2s" repeatCount="indefinite" />
      </ellipse>
    </g>

    <!-- Bayangan penumpang di trotoar -->
    <ellipse cx="195" cy="550" rx="30" ry="8" fill="#0A326B" opacity=".12" />

    <!-- Penumpang: berdiri menunggu, sedikit bergoyang -->
    <g>
      <g transform="translate(195,470)">
        <!-- kaki -->
        <path
          d="M-9 46 v28 M9 46 v28"
          stroke="#2C4A63"
          stroke-width="10"
          stroke-linecap="round"
        />
        <!-- badan -->
        <path d="M-16 6 h32 a6 6 0 0 1 6 6 v30 a6 6 0 0 1 -6 6 h-32 a6 6 0 0 1 -6 -6 v-30 a6 6 0 0 1 6 -6 z" fill="#1e9bf0" />
        <!-- lengan yang memegang ponsel -->
        <path
          d="M-18 16 l-8 16"
          stroke="#1e9bf0"
          stroke-width="9"
          stroke-linecap="round"
        />
        <path d="M18 16 l9 12" stroke="#1e9bf0" stroke-width="9" stroke-linecap="round" />
        <rect x="24" y="24" width="12" height="18" rx="3" fill="#12385C" />
        <rect x="26" y="27" width="8" height="12" rx="1.5" fill="#8BC53F">
          <animate attributeName="opacity" values=".45;1;.45" dur="1.6s" repeatCount="indefinite" />
        </rect>
        <!-- kepala -->
        <circle cy="-10" r="15" fill="#F2C185" />
        <path d="M-15 -12 a15 15 0 0 1 30 0 a22 22 0 0 0 -30 0 z" fill="#3A2A22" />
        <animateTransform
          attributeName="transform"
          type="translate"
          values="195 470;195 466;195 470"
          dur="3.2s"
          repeatCount="indefinite"
          additive="replace"
        />
      </g>
    </g>

    <!-- Pin titik jemput, melayang di atas kepalanya -->
    <g :filter="url('bayang')">
      <g transform="translate(195,372)">
        <path d="M0 22 c-13 -16 -19 -25 -19 -35 a19 19 0 0 1 38 0 c0 10 -6 19 -19 35 z" fill="#1e9bf0" />
        <circle cy="-13" r="7" fill="#FFFFFF" />
        <animateTransform
          attributeName="transform"
          type="translate"
          values="195 372;195 362;195 372"
          dur="2.4s"
          repeatCount="indefinite"
          additive="replace"
        />
      </g>
    </g>

    <!-- ── Kartu pengemudi yang sedang dijajaki, berkedip bergantian ── -->
    <g>
      <g transform="translate(78,318)">
        <circle r="24" fill="#FFFFFF" :filter="url('bayang')" />
        <circle cy="-5" r="7" fill="#F2C185" />
        <path d="M-11 11 c1 -8 6 -11 11 -11 c5 0 10 3 11 11 z" fill="#1e9bf0" />
        <animate attributeName="opacity" values=".35;1;.35" dur="2.4s" repeatCount="indefinite" />
      </g>

      <g transform="translate(312,286)">
        <circle r="22" fill="#FFFFFF" :filter="url('bayang')" />
        <circle cy="-5" r="6.5" fill="#D8906D" />
        <path d="M-10 10 c1 -7 5 -10 10 -10 c5 0 9 3 10 10 z" fill="#f97316" />
        <animate
          attributeName="opacity"
          values=".35;1;.35"
          dur="2.4s"
          begin=".8s"
          repeatCount="indefinite"
        />
      </g>

      <g transform="translate(300,392)">
        <circle r="20" fill="#FFFFFF" :filter="url('bayang')" />
        <circle cy="-4" r="6" fill="#F7D7B0" />
        <path d="M-9 9 c1 -6 4 -9 9 -9 c5 0 8 3 9 9 z" fill="#8BC53F" />
        <animate
          attributeName="opacity"
          values=".35;1;.35"
          dur="2.4s"
          begin="1.6s"
          repeatCount="indefinite"
        />
      </g>
    </g>

    <!-- ── Percik kecil ── -->
    <g fill="#FFD43B">
      <path d="M116 246 l3 7 7 3 -7 3 -3 7 -3 -7 -7 -3 7 -3 z">
        <animate attributeName="opacity" values=".2;1;.2" dur="2s" repeatCount="indefinite" />
      </path>
      <path d="M268 340 l2.5 6 6 2.5 -6 2.5 -2.5 6 -2.5 -6 -6 -2.5 6 -2.5 z">
        <animate
          attributeName="opacity"
          values=".2;1;.2"
          dur="2.2s"
          begin=".6s"
          repeatCount="indefinite"
        />
      </path>
    </g>
    <circle cx="146" cy="352" r="5" fill="#8BC53F">
      <animate attributeName="cy" values="352;344;352" dur="2.6s" repeatCount="indefinite" />
    </circle>
    <circle cx="246" cy="228" r="4" fill="#1e9bf0" opacity=".7">
      <animate attributeName="cy" values="228;220;228" dur="2.2s" repeatCount="indefinite" />
    </circle>
    </g>
  </svg>
</template>
