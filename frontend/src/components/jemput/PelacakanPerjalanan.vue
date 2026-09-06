<script setup lang="ts">
/**
 * BisaJemput — layar pelacakan setelah pengemudi menerima order.
 *
 * Peta memenuhi layar, keterangan menumpuk di atasnya, dan detailnya ada di
 * lembar yang bisa ditarik. Susunan ini dipilih karena sesudah pengemudi
 * berangkat yang dilihat orang berulang-ulang cuma dua hal: kendaraannya ada
 * di mana, dan berapa lama lagi. Sisanya — rincian tarif, nomor transaksi,
 * tombol batal — dibuka kalau dicari.
 *
 * POSISI KENDARAAN DAN SISA JARAK DATANG DARI SERVER, dan boleh tidak ada.
 * Kalau server belum tahu di mana pengemudinya, petanya tidak menggambar
 * kendaraan dan layarnya tidak menyebut jarak. Angka jarak yang dikarang di
 * sisi penumpang tampak sama meyakinkannya dengan yang benar — dan orang
 * memakai angka itu untuk memutuskan kapan turun ke lobi.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import SheetGeser from '@/components/SheetGeser.vue'
import { TILE_URL, TILE_OPTIONS, pinIcon } from '@/lib/mapTiles'
import { ikonMotorHtml } from '@/lib/ikonMotor'
import MetodeBayarIcon from '@/components/MetodeBayarIcon.vue'
import LencanaVarian from '@/components/jemput/LencanaVarian.vue'
import { labelMetode, type MetodeId } from '@/lib/metodeBayar'
import { rupiah } from '@/lib/jemput'
import type { Perjalanan } from '@/api/jemput'
import promoMinimalImg from '@/assets/BisaJemput_MinimalTransaksi.png'
import promoJemputImg from '@/assets/PromoBisaJemput.png'

const props = defineProps<{
  data: Perjalanan
  membatalkan: boolean
}>()

const emit = defineEmits<{
  kembali: []
  bagikan: []
  batal: []
}>()

const terbuka = ref(false)
const nomorTersalin = ref(false)
let penandaSalin: ReturnType<typeof setTimeout> | null = null

const pengemudi = computed(() => props.data.pengemudi)
const tahap = computed(() => props.data.tahap)

/** Posisi pengemudi hanya dipakai kalau server benar-benar mengirimkannya. */
const posisiPengemudi = computed<L.LatLngTuple | null>(() => {
  const p = pengemudi.value
  return typeof p?.lat === 'number' && typeof p?.lng === 'number' ? [p.lat, p.lng] : null
})

/**
 * Sisa jarak, atau null kalau tidak ada yang berarti untuk disebut.
 *
 * Nol bukan angka yang layak ditampilkan: "0,00 km lagi" di layar orang yang
 * pengemudinya sudah berdiri di depannya terbaca seperti angka yang macet,
 * bukan seperti kabar bahwa ia sudah sampai.
 */
const sisaKm = computed(() => {
  const km = pengemudi.value?.jarak_km
  return typeof km === 'number' && km >= 0.05 ? km : null
})

const sisaJarak = computed(() =>
  sisaKm.value === null ? null : `${sisaKm.value.toFixed(2).replace('.', ',')} km`,
)

/** Satu kalimat keadaan, dipakai di pita hijau di atas lembar. */
const pita = computed(() => {
  const p = pengemudi.value
  if (tahap.value === 'tiba') {
    return {
      judul: 'Pengemudi sudah menunggu',
      keterangan: 'Temui di titik jemput yang kamu konfirmasi.',
    }
  }
  if (tahap.value === 'jalan') {
    return {
      judul: 'Dalam perjalanan ke tujuan',
      keterangan: sisaJarak.value ? `Sisa ${sisaJarak.value} lagi.` : 'Selamat jalan, ya.',
    }
  }
  return {
    judul: `Pengemudi sampai dalam ${p?.tiba_menit ?? '-'} mnt`,
    keterangan: 'Tunggu di titik jemput.',
  }
})

/* ────────── Peta ────────── */
const petaEl = ref<HTMLDivElement | null>(null)
let peta: L.Map | null = null
let garis: L.Polyline | null = null
let bayang: L.Polyline | null = null
let penandaJemput: L.Marker | null = null
let penandaTujuan: L.Marker | null = null
let penandaPengemudi: L.Marker | null = null

/**
 * Peta masih mengikuti kendaraan, atau pengguna sudah mengambil alih.
 *
 * Sekali jarinya menggeser peta, pembaruan berikutnya tidak boleh menyeret
 * pandangannya kembali — yang dilihat orang saat itu adalah sesuatu yang ia
 * cari sendiri.
 */
const ikuti = ref(true)
const pengemudiTerlihat = ref(true)

/** Menyala selama PETA yang menggerakkan dirinya, bukan jari pengguna. */
let sedangAtur = false

/** Banner promo di atas metode pembayaran. */
const BANNER_PROMO = [
  { src: promoMinimalImg, alt: 'Promo BisaJemput: minimal transaksi' },
  { src: promoJemputImg, alt: 'Promo BisaJemput' },
]

/** Tinggi label jarak di atas kendaraan, termasuk jaraknya ke ikon. */
const TINGGI_LABEL = 30

/**
 * Ikon kendaraan, dengan sisa jarak menempel di atasnya.
 *
 * Labelnya jadi satu dengan penandanya, bukan elemen terpisah yang diposisikan
 * sendiri: Leaflet yang memindahkan penanda saat peta digeser atau dizum, dan
 * label yang dipasang terpisah harus dihitung ulang di setiap gerakan itu —
 * satu perhitungan yang terlewat membuat angkanya melayang jauh dari
 * kendaraan yang diterangkannya.
 *
 * Jaraknya hanya ikut kalau server mengirimkannya. Yang tidak diketahui tidak
 * ditulis.
 */
function ikonKendaraan(): L.DivIcon {
  const motor = (props.data.kelas ?? '').startsWith('motor')
  const ukuran = motor ? 56 : 42

  const kendaraan = motor
    ? ikonMotorHtml(ukuran)
    : '<svg viewBox="0 0 24 24" width="42" height="42" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.28))">' +
      '<circle cx="12" cy="12" r="11.5" fill="#FFFFFF"/>' +
      '<rect x="3" y="10" width="18" height="7" rx="2.6" fill="#8BC53F"/>' +
      '<path d="M6 10 l2.4-4h7.2L18 10z" fill="#6FAE33"/>' +
      '<circle cx="7.5" cy="17.5" r="2.2" fill="#0A326B"/>' +
      '<circle cx="16.5" cy="17.5" r="2.2" fill="#0A326B"/>' +
      '</svg>'

  const label = sisaJarak.value
    ? `<span style="
        display:inline-block; white-space:nowrap;
        background:#FFFFFF; color:#0A326B;
        font-size:12px; font-weight:800; line-height:1;
        padding:6px 10px; border-radius:999px;
        box-shadow:0 3px 10px rgba(0,0,0,0.22);
      ">${sisaJarak.value} lagi</span>`
    : ''

  const tinggi = ukuran + (label ? TINGGI_LABEL : 0)

  return L.divIcon({
    className: '',
    html: `<div style="display:flex; flex-direction:column; align-items:center; gap:4px; width:${ukuran}px">${label}${kendaraan}</div>`,
    iconSize: [ukuran, tinggi],
    // Jangkarnya di TENGAH KENDARAAN, bukan di tengah kotak: labelnya menambah
    // tinggi di atas, dan jangkar yang ikut bergeser membuat motornya berdiri
    // setengah blok dari titik yang sebenarnya.
    iconAnchor: [ukuran / 2, tinggi - ukuran / 2],
  })
}

function gambarPeta() {
  if (!petaEl.value) return

  const j = props.data.jemput
  const t = props.data.tujuan
  if (!j || !t) return

  if (!peta) {
    peta = L.map(petaEl.value, { zoomControl: false, attributionControl: false })
    L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(peta)

    /*
     * `dragstart` dan `zoomstart` juga menyala saat PETA SENDIRI yang bergerak
     * (fitBounds di bawah), jadi keduanya dijaga penanda `sedangAtur`. Tanpa
     * itu, pemusatan otomatis akan langsung mematikan dirinya sendiri di
     * gambar pertama, dan peta tidak pernah mengikuti kendaraannya sama sekali.
     */
    peta.on('dragstart zoomstart', () => {
      if (!sedangAtur) ikuti.value = false
    })
    peta.on('moveend zoomend', perbaruiTerlihat)
  }

  const a: L.LatLngTuple = [j.lat, j.lng]
  const b: L.LatLngTuple = [t.lat, t.lng]

  // Dibuang dulu, bukan dipindahkan: penanda lama yang tertinggal membuat dua
  // kendaraan untuk satu pengemudi, dan yang basi tampak sama sahihnya.
  penandaJemput?.remove()
  penandaTujuan?.remove()
  penandaPengemudi?.remove()
  penandaPengemudi = null

  penandaJemput = L.marker(a, { icon: pinIcon('#1e9bf0') }).addTo(peta)

  /*
   * GARIS YANG DIGAMBAR MENGIKUTI APA YANG SEDANG TERJADI.
   *
   * Selama menjemput, yang ditunggu orang adalah kendaraan yang menuju DIA —
   * jadi yang digambar jalur pengemudi ke titik jemput, dan tujuan akhir belum
   * ikut ditampilkan supaya petanya bisa dizum ke bagian yang sedang berjalan.
   * Sesudah naik, barulah rute perjalanan yang digambar.
   *
   * Garis mengikuti jalan bila server tahu rutenya, lurus PUTUS-PUTUS bila
   * tidak — bentuk yang berbeda supaya tidak terbaca sebagai jalan yang sungguh
   * dilewati.
   */
  const menjemput = props.data.tahap === 'dijemput' && !!posisiPengemudi.value
  let titik: L.LatLngExpression[]
  let lewatJalan: boolean

  if (menjemput) {
    const p = pengemudi.value
    titik = (p?.rute as L.LatLngExpression[] | null | undefined) ?? [
      posisiPengemudi.value as L.LatLngTuple,
      a,
    ]
    lewatJalan = !!p?.rute?.length
  } else {
    penandaTujuan = L.marker(b, { icon: pinIcon('#f97316') }).addTo(peta)
    titik = (props.data.geometri as L.LatLngExpression[] | null | undefined) ?? [a, b]
    lewatJalan = props.data.lewat_jalan
  }

  garis?.remove()
  bayang?.remove()
  bayang = L.polyline(titik, { color: '#1B2C5E', weight: 9, opacity: 0.22, lineJoin: 'round' }).addTo(peta)
  garis = L.polyline(titik, {
    color: '#8BC53F',
    weight: 5.5,
    lineJoin: 'round',
    dashArray: lewatJalan ? undefined : '8 8',
  }).addTo(peta)

  const semua: L.LatLngTuple[] = [...(titik as L.LatLngTuple[])]
  if (posisiPengemudi.value) {
    penandaPengemudi = L.marker(posisiPengemudi.value, { icon: ikonKendaraan() }).addTo(peta)
    semua.push(posisiPengemudi.value)
  }

  /*
   * PETA HANYA DIPUSATKAN ULANG SELAMA PENGGUNA BELUM MENGAMBIL ALIH.
   *
   * Layar ini bertanya ke server tiap enam detik, dan setiap jawaban menggambar
   * ulang petanya. Dulu fitBounds ikut dipanggil setiap kali — jadi peta yang
   * baru saja digeser jari kembali ke tempat semula dalam hitungan detik.
   * Petanya sebenarnya bisa digeser; yang terlihat orang adalah peta yang
   * menolak digeser, dan itu jauh lebih buruk daripada peta yang diam.
   *
   * Padding bawahnya besar karena lembar detail menutupi bagian bawah layar:
   * tanpa itu kendaraan dan titik jemput jatuh persis di baliknya.
   */
  if (ikuti.value) {
    sedangAtur = true
    peta.fitBounds(L.latLngBounds(semua), {
      paddingTopLeft: [40, 120],
      paddingBottomRight: [40, 300],
    })
    // Dilepas di frame berikutnya: fitBounds memicu movestart/zoomstart sendiri,
    // dan tanpa jeda ini peta akan mengira dirinya digeser pengguna.
    requestAnimationFrame(() => (sedangAtur = false))
  }

  perbaruiTerlihat()
  setTimeout(() => peta?.invalidateSize(), 120)
}

/**
 * Apakah kendaraannya masih di dalam layar peta.
 *
 * Dipakai memutuskan tombol "pusatkan" muncul atau tidak: selama kendaraannya
 * masih kelihatan, orang tidak sedang tersesat dan tombolnya cuma menutupi
 * peta.
 */
function perbaruiTerlihat() {
  const p = posisiPengemudi.value
  pengemudiTerlihat.value = !p || !peta ? true : peta.getBounds().contains(L.latLng(p))
}

/** Kembalikan peta ke kendaraan, dan ikuti lagi setiap pembaruan berikutnya. */
function pusatkanKePengemudi() {
  ikuti.value = true
  gambarPeta()
}

onMounted(async () => {
  await nextTick()
  gambarPeta()
})

onBeforeUnmount(() => {
  if (penandaSalin) clearTimeout(penandaSalin)
  peta?.remove()
  peta = null
})

watch(
  // sisaKm ikut dipantau: angkanya ditulis DI DALAM ikon penanda, jadi
  // penandanya harus digambar ulang saat jaraknya berubah.
  () =>
    [
      props.data.tahap,
      props.data.geometri,
      posisiPengemudi.value,
      pengemudi.value?.rute,
      sisaKm.value,
    ] as const,
  gambarPeta,
  { deep: true },
)

async function salinNomor() {
  try {
    await navigator.clipboard.writeText(props.data.nomor)
    nomorTersalin.value = true
    if (penandaSalin) clearTimeout(penandaSalin)
    penandaSalin = setTimeout(() => (nomorTersalin.value = false), 2000)
  } catch {
    // Papan klip ditolak peramban (izin, atau bukan konteks aman). Nomornya
    // tetap terbaca di layar, jadi tidak ada yang hilang selain kemudahannya.
  }
}
</script>

<template>
  <div class="relative min-h-dvh w-full overflow-hidden bg-(--color-surface-container)">
    <!--
      isolate wajib. Panel Leaflet punya z-index sendiri (ubin 400, penanda
      600) dan div peta ini tidak membentuk stacking context, jadi angka itu
      ikut bersaing di halaman: seluruh lapisan di atasnya — kartu alamat,
      tombol kembali, lembar detail — tertimbun peta. Semuanya tetap ada di
      DOM dan terbaca oleh pengukuran; yang memperlihatkannya cuma tangkapan
      layar.
    -->
    <div ref="petaEl" class="absolute inset-0 isolate" aria-label="Peta perjalanan"></div>

    <!-- ── Lapisan atas peta ── -->
    <div class="relative z-20 max-w-[430px] mx-auto px-4 pt-[calc(0.75rem+env(safe-area-inset-top))]">
      <!--
        Kartu alamat. TIDAK ada tombol "Edit" di sini: mengubah tujuan setelah
        pengemudi berangkat butuh tarif dan rute yang dihitung ulang, dan
        layanan itu belum ada. Tombol yang tidak melakukan apa-apa lebih buruk
        daripada tombol yang tidak ada — yang menekannya baru tahu saat sudah
        di dalam mobil.
      -->
      <button
        type="button"
        class="w-full bg-(--color-surface-0) rounded-2xl shadow-lg px-4 py-3 text-left active:scale-[0.99] transition-transform"
        @click="terbuka = true"
      >
        <div class="flex items-center gap-3">
          <span
            class="w-6 h-6 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0"
          >
            <Icon name="arrow-up" class="w-3.5 h-3.5 text-white" />
          </span>
          <span class="flex-1 min-w-0 text-[13px] font-semibold truncate">
            {{ data.jemput?.alamat }}
          </span>
        </div>
        <span class="block h-[2px] bg-(--color-outline)/15 my-2.5 ml-9"></span>
        <div class="flex items-center gap-3">
          <span class="w-6 h-6 flex items-center justify-center shrink-0">
            <span class="w-3.5 h-3.5 rounded-full bg-orange-500"></span>
          </span>
          <span class="flex-1 min-w-0 text-[13px] font-semibold truncate">
            {{ data.tujuan?.alamat }}
          </span>
          <Icon name="chevron-right" class="w-4 h-4 shrink-0 text-(--color-on-surface-variant)" />
        </div>
      </button>

      <!--
        Tombol pulang ke kendaraan, TEPAT DI ATAS tombol kembali.

        Muncul begitu pengguna menggeser petanya sendiri: sejak itu peta berhenti
        mengikuti kendaraan, dan tanpa tombol ini satu-satunya jalan pulang
        adalah menebak-nebak arah dengan jari. Dulu ia baru muncul setelah
        kendaraannya benar-benar keluar layar — geseran yang menyisakan
        kendaraannya di pinggir tidak memunculkan apa pun, dan yang mencarinya
        menyimpulkan tombolnya memang tidak ada.
      -->
      <Transition
        enter-active-class="transition duration-200"
        enter-from-class="opacity-0 -translate-y-1"
        leave-active-class="transition duration-150"
        leave-to-class="opacity-0"
      >
        <div v-if="!ikuti && posisiPengemudi" class="mt-49 mb-2 flex">
          <button
            type="button"
            class="inline-flex items-center gap-2 rounded-full bg-(--color-surface-0) shadow-lg pl-3 pr-4 py-2.5 text-[12.5px] font-extrabold active:scale-95 transition-transform"
            @click="pusatkanKePengemudi"
          >
            <Icon name="crosshair" class="w-4 h-4 text-(--color-azure)" />
            Kembali ke pengemudi
          </button>
        </div>
      </Transition>

      <!--
        Jarak dari kartu alamat hanya dipasang kalau tombol di atas belum ada;
        kalau ada, tombol itu yang sudah memberi jaraknya. Tanpa ini, keduanya
        menumpuk dan barisnya terdorong dua kali lebih jauh ke bawah.
      -->
      <div class="flex items-center gap-2" :class="!ikuti && posisiPengemudi ? '' : 'mt-49'">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full bg-(--color-surface-0) shadow-lg flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="emit('kembali')"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>

        <!--
          Sisa jaraknya TIDAK lagi di sini, melainkan menempel di atas
          kendaraannya di peta. Angka yang mengambang di pojok layar tidak
          menerangkan apa-apa: yang ingin diketahui orang adalah berapa jauh
          KENDARAAN ITU, dan angka yang menempel padanya ikut bergerak bersama
          pin saat pengemudinya mendekat.
        -->

        <button
          type="button"
          aria-label="Bagikan perjalanan"
          class="ml-auto w-10 h-10 rounded-full bg-(--color-surface-0) shadow-lg flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="emit('bagikan')"
        >
          <Icon name="send" class="w-4.5 h-4.5" />
        </button>
      </div>

    </div>

    <!-- ── Lembar detail, bisa ditarik ── -->
    <SheetGeser v-model="terbuka" :puncak="330" label="detail perjalanan">
      <template #header>
        <!-- Pita keadaan, ditaruh di luar card putih -->
        <div
          class="rounded-t-3xl bg-(--color-azure) px-5 pt-3.5 pb-6 flex items-center gap-3 text-white shadow-md"
        >
          <span
            class="w-10 h-10 rounded-full bg-white flex items-center justify-center shrink-0 shadow-sm overflow-hidden p-1"
          >
            <svg viewBox="0 0 512 512" class="w-7 h-7 shrink-0" xmlns="http://www.w3.org/2000/svg">
              <defs>
                <linearGradient id="blueMotor" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0" stop-color="#1683FF"/>
                  <stop offset="1" stop-color="#0756D9"/>
                </linearGradient>
                <linearGradient id="darkBlueMotor" x1="0" y1="0" x2="1" y2="1">
                  <stop offset="0" stop-color="#084EBD"/>
                  <stop offset="1" stop-color="#06348E"/>
                </linearGradient>
              </defs>

              <!-- REAR WHEEL -->
              <circle cx="150" cy="383" r="57" fill="#172235"/>
              <circle cx="150" cy="383" r="35" fill="#E7EDF5"/>
              <circle cx="150" cy="383" r="20" fill="#8C98AA"/>
              <circle cx="150" cy="383" r="8" fill="#344054"/>

              <!-- FRONT WHEEL -->
              <circle cx="394" cy="383" r="57" fill="#172235"/>
              <circle cx="394" cy="383" r="35" fill="#E7EDF5"/>
              <circle cx="394" cy="383" r="20" fill="#8C98AA"/>
              <circle cx="394" cy="383" r="8" fill="#344054"/>

              <!-- REAR BODY -->
              <path d="M92 327 C105 306 130 293 159 294 L268 307 C288 310 302 325 303 345 L302 365 L205 365 C193 333 171 321 145 321 C123 321 106 331 96 350 Z" fill="url(#blueMotor)"/>

              <!-- BODY LOWER -->
              <path d="M100 349 C113 326 132 316 157 316 C184 316 204 333 214 358 L343 358 C355 358 365 367 365 380 L365 394 L202 394 C193 362 174 344 149 344 C130 344 114 354 104 370 Z" fill="url(#darkBlueMotor)"/>

              <!-- SIDE PANEL -->
              <path d="M99 330 C133 306 193 312 241 328 C260 335 274 349 280 366 L205 366 C193 337 174 321 147 321 C128 321 111 331 99 348 Z" fill="#1683FF"/>

              <!-- SEAT -->
              <path d="M136 292 C151 278 184 277 216 283 L273 294 C282 296 288 304 286 311 C284 318 277 322 268 321 L159 311 C147 310 138 303 136 292 Z" fill="#202B3D"/>

              <!-- FRONT SCOOTER BODY -->
              <path d="M300 307 C309 279 325 253 351 238 C368 228 393 229 408 242 L425 260 C433 270 434 284 428 295 L408 333 C400 347 386 357 370 359 L318 359 C300 355 291 338 300 307 Z" fill="url(#blueMotor)"/>

              <!-- FRONT LOWER FAIRING -->
              <path d="M354 274 C376 267 399 274 410 291 L427 317 C433 327 431 340 423 348 C414 357 399 361 384 358 L348 348 L329 316 Z" fill="#0756D9"/>

              <!-- FRONT FENDER -->
              <path d="M349 349 C361 335 381 327 402 330 C420 333 434 344 442 359 L430 367 C418 351 403 345 389 346 C374 347 362 354 354 365 Z" fill="#1683FF"/>

              <!-- FRONT FORK -->
              <path d="M380 285 L394 288 L409 363 L397 367 Z" fill="#AEB9C8"/>

              <!-- HANDLE STEM -->
              <path d="M354 247 L346 190 L356 188 L370 246 Z" fill="#273449"/>

              <!-- MIRROR -->
              <ellipse cx="349" cy="180" rx="15" ry="20" fill="#263348"/>
              <path d="M349 198 L355 213" stroke="#263348" stroke-width="6" stroke-linecap="round"/>

              <!-- HANDLEBAR -->
              <rect x="350" y="239" width="54" height="12" rx="6" fill="#273449"/>

              <!-- HEADLIGHT -->
              <path d="M382 239 C397 239 410 248 413 261 L414 275 C414 281 409 285 403 284 L384 280 C376 278 372 271 374 263 L376 249 C377 243 379 240 382 239 Z" fill="#F7FAFF"/>

              <!-- TAIL LIGHT -->
              <path d="M92 319 C82 320 75 327 75 337 C75 346 82 353 92 353 L106 353 L111 326 C106 321 100 319 92 319 Z" fill="#FF3B3B"/>

              <!-- RIDER BODY -->
              <path d="M211 146 C234 137 260 143 276 160 L305 195 C314 207 314 224 306 237 L276 280 C265 295 247 300 230 293 L191 275 C177 269 169 255 171 239 L180 181 C183 164 194 152 211 146 Z" fill="url(#blueMotor)"/>

              <!-- RIDER BACK SHADOW -->
              <path d="M187 180 C192 157 211 146 229 148 C214 171 207 202 208 235 C208 258 220 276 239 290 L216 284 C192 278 176 260 174 238 Z" fill="#0B5ED7"/>

              <!-- RIDER ARM -->
              <path d="M258 184 C271 184 284 190 296 199 L347 231 C354 235 356 244 352 251 C348 258 339 260 331 256 L272 227 C256 220 248 203 252 191 C253 187 255 185 258 184 Z" fill="#1683FF"/>

              <!-- ARM CUFF -->
              <path d="M328 225 L352 233 C359 236 361 244 357 250 L351 258 L326 247 Z" fill="#084EBB"/>

              <!-- HAND -->
              <circle cx="356" cy="246" r="13" fill="#F3B27D"/>

              <!-- RIDER LEG -->
              <path d="M239 264 C258 260 278 267 290 282 L319 318 L299 336 L265 307 L225 292 Z" fill="#063E9E"/>

              <!-- LOWER LEG -->
              <path d="M299 314 L324 324 L310 356 L284 348 Z" fill="#073F9E"/>

              <!-- SHOE -->
              <path d="M278 343 C289 339 305 343 316 351 L323 359 C327 364 323 371 316 372 L278 372 C269 372 263 367 266 360 C268 352 271 346 278 343 Z" fill="#202B3D"/>

              <!-- SHOE HIGHLIGHT -->
              <path d="M279 360 L305 364" stroke="#F5F8FC" stroke-width="5" stroke-linecap="round"/>

              <!-- NECK -->
              <path d="M220 143 L222 126 L250 125 L253 149 Z" fill="#F3B27D"/>

              <!-- HEAD -->
              <path d="M207 91 C213 65 238 51 264 59 C287 66 301 89 296 113 L291 132 C287 148 271 157 254 154 L231 149 C213 145 203 128 205 110 Z" fill="#F3B27D"/>

              <!-- FACE -->
              <path d="M272 94 C282 94 291 99 296 107 L291 130 C287 145 273 152 260 151 L270 137 C277 127 280 114 272 94 Z" fill="#F7C18F"/>

              <!-- EYE -->
              <ellipse cx="274" cy="105" rx="4" ry="6" fill="#172235"/>

              <!-- HELMET -->
              <path d="M195 106 C190 76 205 48 233 36 C265 22 300 34 315 61 C321 72 324 84 322 95 L291 94 C282 81 269 75 255 75 C239 75 226 84 220 99 L220 120 C208 118 199 113 195 106 Z" fill="#0878F9"/>

              <!-- HELMET DARK EDGE -->
              <path d="M196 105 C203 116 211 120 222 121 L222 108 C219 96 227 83 239 77 C228 77 216 83 207 92 C202 97 198 101 196 105 Z" fill="#0756D9"/>

              <!-- HELMET VISOR -->
              <path d="M278 72 C296 72 310 78 319 88 C323 92 320 98 314 99 L281 98 C276 91 275 82 278 72 Z" fill="#063E9E"/>

              <!-- HELMET DOT -->
              <circle cx="218" cy="83" r="7" fill="#DCEAFF"/>
            </svg>
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-[14px] font-extrabold leading-tight">{{ pita.judul }}</p>
            <p class="text-[12px] leading-snug text-white/90 mt-0.5">
              {{ pita.keterangan }}
            </p>
          </div>
        </div>
      </template>

      <!--
        Isi lembar disusun ulang menurut keadaannya.

        Saat mengintip, yang dicari orang adalah pelat dan nama pengemudi —
        itu yang harus ada di bagian yang terlihat. Begitu lembarnya ditarik
        penuh, yang dicari berganti jadi berapa yang dibayar dan bagaimana,
        jadi metode pembayaran naik ke atas dan kartu pengemudi turun ke
        bawahnya.

        Diatur lewat , bukan dua salinan markup yang ditukar dengan
        v-if: menukar salinan berarti elemennya dilepas lalu dipasang lagi —
        peta di baliknya ikut kehilangan simpulnya, dan posisi gulungan
        melompat setiap kali lembarnya dibuka.
      -->
      <div class="flex flex-col gap-1.5 pb-4">
      <!-- Kendaraan dan pengemudi: yang dicocokkan sebelum naik -->
      <section :class="terbuka ? 'order-2' : 'order-1'" class="px-4 py-1.5">
        <div v-if="pengemudi" class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
          <div v-if="!terbuka" class="flex items-start gap-3">
            <div class="flex-1 min-w-0">
              <p class="text-[19px] font-display font-extrabold tracking-wide">
                {{ pengemudi.plat }}
              </p>
              <p class="mt-0.5 flex items-center gap-2 flex-wrap">
                <span class="text-[15px] font-bold">{{ pengemudi.kendaraan }}</span>
                <span
                  class="rounded-full bg-(--color-surface-container) px-2.5 py-1 text-[11.5px] font-semibold"
                >
                  {{ pengemudi.warna }}
                </span>
              </p>
            </div>
            <span
              class="w-14 h-14 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0"
            >
              <Icon name="user" class="w-7 h-7 text-(--color-on-surface-variant)" />
            </span>
          </div>

          <div
            class="flex items-center gap-3"
            :class="terbuka ? '' : 'mt-3 pt-3 border-t border-(--color-outline)/15'"
          >
            <div class="flex-1 min-w-0">
              <p class="text-[13.5px] font-semibold">{{ pengemudi.nama }}</p>
              <div class="mt-2 flex items-center gap-2 flex-wrap">
                <span
                  class="inline-flex items-center gap-1 rounded-full bg-(--color-surface-container) px-2.5 py-1 text-[12px] font-bold"
                >
                  <Icon name="star" class="w-3.5 h-3.5 text-orange-500" />
                  {{ pengemudi.bintang }}
                </span>
                <span
                  class="inline-flex items-center gap-1 rounded-full bg-(--color-surface-container) px-2.5 py-1 text-[12px] font-semibold"
                >
                  {{ pengemudi.perjalanan.toLocaleString('id-ID') }} perjalanan
                </span>
              </div>
            </div>

            <!-- Saat terbuka, potretnya pindah ke baris ini: baris pelat yang
                 tadinya memuatnya sudah tidak digambar. -->
            <span
              v-if="terbuka"
              class="w-14 h-14 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0"
            >
              <Icon name="user" class="w-7 h-7 text-(--color-on-surface-variant)" />
            </span>
          </div>

          <div v-if="!terbuka" class="mt-3.5 flex items-center gap-2.5">
            <a
              href="tel:+62000000000"
              aria-label="Telepon pengemudi"
              class="w-12 h-12 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0 active:scale-95 transition-transform"
            >
              <Icon name="phone" class="w-5 h-5 text-white" />
            </a>
            <button
              type="button"
              class="flex-1 h-12 rounded-full bg-(--color-surface-container) px-4 flex items-center gap-2 text-left active:scale-[0.98] transition-transform"
              @click="emit('kembali')"
            >
              <span class="flex-1 text-[13px] text-(--color-on-surface-variant)">
                Kirim chat ke pengemudi
              </span>
              <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface-variant)" />
            </button>
          </div>
        </div>
      </section>

      <!-- Rute -->
      <section :class="terbuka ? 'order-1' : 'order-2'" class="px-4 py-1.5">
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
          <!--
            Kepala kartu: nama menu, ikon kendaraannya, dan lencana varian yang
            DIPILIH penumpang. Ikonnya mengikuti kelas — motor untuk motor,
            mobil untuk mobil — supaya tidak menaruh gambar motor di atas
            perjalanan mobil. Lencananya baru digambar kalau server mengirim
            varian; yang tak diketahui tidak ditebak.
          -->
          <div class="flex items-center gap-2.5 mb-3.5 pb-3.5 border-b-3[px] border-gray-200">
            <span class="w-9 h-9 rounded-full bg-(--color-azure)/12 flex items-center justify-center shrink-0">
              <svg
                v-if="(data.kelas ?? 'motor').startsWith('motor')"
                viewBox="0 0 24 24"
                class="w-5 h-5 text-(--color-azure)"
                fill="none"
                stroke="currentColor"
                stroke-width="1.7"
                stroke-linecap="round"
                stroke-linejoin="round"
                aria-hidden="true"
              >
                <circle cx="5.5" cy="17" r="3" />
                <circle cx="18.5" cy="17" r="3" />
                <path d="M5.5 17 L10.5 10 H14" />
                <path d="M10.5 10 H15.5 L18.5 17" />
                <path d="M13 7 H15 L15.5 10" />
              </svg>
              <Icon v-else name="car" class="w-5 h-5 text-(--color-azure)" />
            </span>
            <span class="text-[15px] font-display font-extrabold leading-none">BisaJemput</span>
            <LencanaVarian :label="data.label_varian" class="ml-0.5" />
          </div>

          <!-- Detail rute -->
          <div class="flex gap-3">
            <div class="flex flex-col items-center pt-1 shrink-0">
              <span class="w-6 h-6 rounded-full bg-emerald-600 flex items-center justify-center text-white text-[11px] font-bold shadow-sm">
                ↑
              </span>
              <span class="w-0.5 flex-1 my-1 border-l-2 border-dashed border-(--color-outline)/80"></span>
              <span class="w-6 h-6 rounded-full bg-orange-500 flex items-center justify-center text-white text-[11px] font-bold shadow-sm">
                ●
              </span>
            </div>
            <div class="flex-1 min-w-0 flex flex-col gap-2.5">
              <div>
                <p class="text-[11px] text-(--color-on-surface-variant)">Lokasi jemput</p>
                <p class="text-[14px] font-extrabold text-(--color-on-surface) leading-tight mt-0.5">
                  {{ data.jemput?.alamat }}
                </p>
                <p
                  v-if="data.jemput?.catatan"
                  class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5"
                >
                  {{ data.jemput.catatan }}
                </p>
              </div>

              <p class="text-[12px] font-semibold text-(--color-on-surface-variant) py-0.5">
                {{ data.menit }} menit · {{ data.km?.toFixed(1).replace('.', ',') }} km
              </p>

              <div>
                <p class="text-[11px] text-(--color-on-surface-variant)">Lokasi tujuan</p>
                <p class="text-[14px] font-extrabold text-(--color-on-surface) leading-tight mt-0.5">
                  {{ data.tujuan?.alamat }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </section>

      <!--
        Pita promo, tepat di atas metode pembayaran.

        Digulung mendatar dengan snap: dua gambar berukuran penuh yang
        ditumpuk ke bawah akan mendorong metode pembayaran keluar layar, dan
        yang dicari orang saat membuka lembar ini justru metode pembayarannya.

        loading="lazy" karena keduanya berkas besar (1,4 MB) dan berada di
        bagian yang cuma terlihat setelah lembarnya ditarik — memuatnya di awal
        berarti menahan hal-hal yang benar-benar dilihat orang lebih dulu.
      -->
      <section class="order-3 py-1.5">
        <!--
          Gambarnya TIDAK bisa diketuk. Halaman voucher BisaJemput butuh
          pilihan kendaraan yang sudah dibuang begitu pesanan jadi, jadi
          ketukan di sini tidak punya tujuan yang benar. Banner yang bisa
          ditekan tapi tidak membawa ke mana-mana baru ketahuan setelah
          ditekan — dan yang menekannya sedang di dalam perjalanan.
        -->
        <div
          class="flex gap-3 overflow-x-auto no-scrollbar snap-x snap-mandatory px-4 scroll-smooth"
        >
          <img
            v-for="b in BANNER_PROMO"
            :key="b.src"
            :src="b.src"
            :alt="b.alt"
            loading="lazy"
            class="shrink-0 w-[86%] snap-center rounded-2xl shadow-sm block h-auto"
          />
        </div>
      </section>

      <!-- Metode pembayaran -->
      <section class="order-4 px-4 py-1.5">
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
          <div class="flex items-center justify-between gap-3">
            <p class="text-[14px] font-display font-extrabold">Metode pembayaran</p>
          </div>
          <div class="mt-2.5 flex items-center gap-3">
            <MetodeBayarIcon :id="(data.metode ?? 'tunai') as MetodeId" />
            <span class="flex-1 text-[13.5px] font-semibold">{{ labelMetode(data.metode) }}</span>
            <span class="text-[14px] font-extrabold">{{ rupiah(data.total) }}</span>
          </div>
        </div>
      </section>

      <!-- Rincian tarif -->
      <section class="order-5 px-4 py-1.5">
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
          <p class="text-[14px] font-display font-extrabold mb-3">Rincian tarif</p>
          <div class="flex flex-col gap-2 text-[13px]">
            <div v-for="b in data.baris" :key="b.label" class="flex justify-between gap-3">
              <span class="text-(--color-on-surface-variant)">{{ b.label }}</span>
              <span class="font-semibold">{{ rupiah(b.nilai) }}</span>
            </div>
            <div
              v-if="data.potongan > 0"
              class="flex justify-between gap-3 text-(--color-on-secondary-container)"
            >
              <span>Promo {{ data.promo?.kode }}</span>
              <span class="font-semibold">-{{ rupiah(data.potongan) }}</span>
            </div>
          </div>
          <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex justify-between gap-3">
            <span class="text-[14px] font-extrabold">Total</span>
            <span class="text-[16px] font-extrabold">{{ rupiah(data.total) }}</span>
          </div>
        </div>
      </section>

      <!-- Keselamatan -->
      <section class="order-6 px-4 py-1.5">
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
          <p class="text-[14px] font-display font-extrabold mb-3">Keselamatan</p>
          <div class="grid grid-cols-2 gap-2.5">
            <a
              href="tel:112"
              class="h-11 rounded-full bg-(--color-error) text-white text-[13px] font-extrabold flex items-center justify-center gap-2 active:scale-95 transition-transform"
            >
              <Icon name="shield" class="w-4 h-4" /> Darurat 112
            </a>
            <button
              type="button"
              class="h-11 rounded-full border-[1.5px] border-(--color-outline)/50 text-[13px] font-extrabold flex items-center justify-center gap-2 active:scale-95 transition-transform"
              @click="emit('bagikan')"
            >
              <Icon name="send" class="w-4 h-4" /> Bagikan
            </button>
          </div>
          <p class="mt-2.5 text-[11px] leading-snug text-(--color-on-surface-variant) text-center">
            Tombol darurat menelepon 112 langsung.
          </p>
        </div>
      </section>

      <!-- Nomor transaksi dan pembatalan -->
      <section class="order-7 px-4 py-1.5">
        <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
          <button
            type="button"
            class="w-full rounded-xl bg-(--color-surface-container) px-4 py-3 flex items-center justify-center gap-2 text-[12px] text-(--color-on-surface-variant) active:scale-[0.99] transition-transform"
            @click="salinNomor"
          >
            No. Transaksi — {{ data.nomor }}
            <Icon :name="nomorTersalin ? 'check' : 'copy'" class="w-3.5 h-3.5" />
          </button>
          <p v-if="nomorTersalin" class="mt-1.5 text-center text-[11px] text-(--color-on-surface-variant)">
            Nomor tersalin.
          </p>

          <button
            v-if="tahap === 'dijemput' || tahap === 'tiba'"
            type="button"
            class="mt-3 w-full h-12 rounded-full bg-(--color-error) text-white text-[13.5px] font-extrabold active:scale-[0.98] transition-transform disabled:opacity-40"
            :disabled="membatalkan"
            @click="emit('batal')"
          >
            {{ membatalkan ? 'Membatalkan…' : 'Batalkan booking' }}
          </button>
        </div>
      </section>
      </div>
    </SheetGeser>
  </div>
</template>
