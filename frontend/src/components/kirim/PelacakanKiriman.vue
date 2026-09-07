<script setup lang="ts">
/**
 * BisaKirim — layar pelacakan setelah kurir menerima kiriman.
 *
 * Susunannya sengaja sama dengan layar perjalanan BisaJemput: peta memenuhi
 * layar, keterangan menumpuk di atasnya, detailnya di lembar yang bisa ditarik.
 * Dua menu yang sama-sama "ada kendaraan menuju kamu" sebaiknya dibaca dengan
 * kebiasaan yang sama — bukan dua tata letak berbeda untuk pekerjaan yang
 * bentuknya sama.
 *
 * Yang BERBEDA isinya: di sini yang dijemput paket, bukan orang. Jadi ada satu
 * bagian yang tidak ada di BisaJemput — apa yang dikirim, ukurannya, plafon
 * proteksinya, dan kode terima. Itulah yang dicari pengirim saat kurir sudah
 * di jalan: memastikan yang dijemput benar barangnya, dan menyiapkan kodenya
 * untuk penerima.
 *
 * POSISI KURIR DAN SISA JARAK DATANG DARI SERVER, dan boleh tidak ada. Kalau
 * server belum tahu di mana kurirnya, petanya tidak menggambar kendaraan dan
 * layarnya tidak menyebut jarak.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import SheetGeser from '@/components/SheetGeser.vue'
import MetodeBayarIcon from '@/components/MetodeBayarIcon.vue'
import AvatarPengemudi from '@/components/jemput/AvatarPengemudi.vue'
import { TILE_URL, TILE_OPTIONS, pinIcon } from '@/lib/mapTiles'
import { ikonMotorHtml } from '@/lib/ikonMotor'
import { labelMetode, type MetodeId } from '@/lib/metodeBayar'
import { UKURAN, rupiah } from '@/lib/kirim'
import { pesanError } from '@/api/belanja'
import { tipKurir, type Kiriman } from '@/api/kirim'

const props = defineProps<{ data: Kiriman }>()

const emit = defineEmits<{ kembali: []; bagikan: [] }>()

/** Tinggi lembar saat mengintip; dipakai lembar DAN batas lapisan kendali. */
const PUNCAK_LEMBAR = 330

/* ────────── Tip untuk kurir ────────── */
const PILIHAN_TIP = [5000, 10000, 20000, 50000]

const tipDipilih = ref<number | null>(null)
const mengirimTip = ref(false)
const galatTip = ref<string | null>(null)
const tipTerkirim = ref(props.data.tip ?? 0)

/**
 * Pemilih nominal disembunyikan sampai orang menekan "Kasih tip".
 *
 * Sama alasannya dengan BisaJemput: deretan nominal yang langsung terpampang
 * membuat tip terbaca sebagai tagihan tambahan. Yang mau berterima kasih
 * menekan dulu, baru memilih berapa.
 */
const tipTerbuka = ref(false)

/**
 * Tip hanya ditawarkan selama kurirnya masih di jalan.
 *
 * Sesudah paket sampai, layar ini berganti jadi nota — dan tombol tip di
 * sana menagih orang untuk pekerjaan yang sudah tutup bukunya.
 */
const bisaTip = computed(
  () => !!kurir.value && ['menjemput', 'diantar'].includes(tahap.value),
)

watch(
  () => props.data.tip,
  (t) => {
    if (typeof t === 'number' && t > tipTerkirim.value) tipTerkirim.value = t
  },
)

async function kirimTip() {
  if (!tipDipilih.value || mengirimTip.value) return

  mengirimTip.value = true
  galatTip.value = null
  try {
    const h = await tipKurir(props.data.nomor, tipDipilih.value)
    tipTerkirim.value = h.tip
    tipDipilih.value = null
  } catch (e) {
    galatTip.value = pesanError(e)
  } finally {
    mengirimTip.value = false
  }
}

const terbuka = ref(false)
const nomorTersalin = ref(false)
let penandaSalin: ReturnType<typeof setTimeout> | null = null

const kurir = computed(() => props.data.kurir)
const tahap = computed(() => props.data.tahap)

/** Yang benar-benar dibayar: harga kiriman ditambah tip yang sudah diberi. */
const totalTagihan = computed(() => props.data.total + tipTerkirim.value)

const ukuran = computed(() => UKURAN.find((u) => u.id === props.data.ukuran) ?? null)

/** Posisi kurir hanya dipakai kalau server benar-benar mengirimkannya. */
const posisiKurir = computed<L.LatLngTuple | null>(() => {
  const k = kurir.value
  return typeof k?.lat === 'number' && typeof k?.lng === 'number' ? [k.lat, k.lng] : null
})

/**
 * Sisa jarak, atau null kalau tidak ada yang berarti disebut.
 *
 * Nol bukan angka yang layak ditampilkan: "0,00 km lagi" di layar orang yang
 * kurirnya sudah berdiri di depan pintu terbaca seperti angka yang macet.
 */
const sisaKm = computed(() => {
  const km = kurir.value?.jarak_km
  return typeof km === 'number' && km >= 0.05 ? km : null
})

const sisaJarak = computed(() =>
  sisaKm.value === null ? null : `${sisaKm.value.toFixed(2).replace('.', ',')} km`,
)

/** Satu kalimat keadaan, dipakai di pita di atas lembar. */
const pita = computed(() => {
  const k = kurir.value
  if (tahap.value === 'diantar') {
    return {
      judul: 'Paket sedang diantar',
      keterangan: sisaJarak.value
        ? `Sisa ${sisaJarak.value} lagi ke penerima.`
        : 'Kurir dalam perjalanan ke penerima.',
    }
  }
  if (tahap.value === 'selesai') {
    return { judul: 'Paket sudah sampai', keterangan: 'Terima kasih sudah pakai BisaKirim.' }
  }
  return {
    judul: `Kurir sampai dalam ${k?.tiba_menit ?? '-'} mnt`,
    keterangan: 'Siapkan paketnya di titik ambil.',
  }
})

/* ────────── Peta ────────── */
const petaEl = ref<HTMLDivElement | null>(null)
let peta: L.Map | null = null
let garis: L.Polyline | null = null
let bayang: L.Polyline | null = null
let penandaAmbil: L.Marker | null = null
let penandaAntar: L.Marker | null = null
let penandaKurir: L.Marker | null = null

/** Peta masih mengikuti kurir, atau pengguna sudah mengambil alih. */
const ikuti = ref(true)

/** Menyala selama PETA yang menggerakkan dirinya, bukan jari pengguna. */
let sedangAtur = false

const TINGGI_LABEL = 30

/** Ikon kendaraan dengan sisa jarak menempel di atasnya. */
function ikonKendaraan(): L.DivIcon {
  const motor = !/gran max|carry|mobil|box/i.test(kurir.value?.kendaraan ?? '')
  const ukuranIkon = motor ? 56 : 42

  const kendaraan = motor
    ? ikonMotorHtml(ukuranIkon)
    : '<svg viewBox="0 0 24 24" width="42" height="42" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.28))">' +
      '<circle cx="12" cy="12" r="11.5" fill="#FFFFFF"/>' +
      '<rect x="3" y="9" width="13" height="9" rx="2" fill="#1e9bf0"/>' +
      '<path d="M16 12h3l2 3v3h-5z" fill="#0B67B0"/>' +
      '<circle cx="7.5" cy="18.5" r="2.2" fill="#0A326B"/>' +
      '<circle cx="17.5" cy="18.5" r="2.2" fill="#0A326B"/>' +
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

  const tinggi = ukuranIkon + (label ? TINGGI_LABEL : 0)

  return L.divIcon({
    className: '',
    html: `<div style="display:flex; flex-direction:column; align-items:center; gap:4px; width:${ukuranIkon}px">${label}${kendaraan}</div>`,
    iconSize: [ukuranIkon, tinggi],
    // Jangkarnya di TENGAH KENDARAAN, bukan tengah kotak: label menambah tinggi
    // di atas, dan jangkar yang ikut bergeser membuat kendaraannya berdiri
    // setengah blok dari titik yang sebenarnya.
    iconAnchor: [ukuranIkon / 2, tinggi - ukuranIkon / 2],
  })
}

function gambarPeta() {
  if (!petaEl.value) return

  const a = props.data.ambil
  const b = props.data.antar
  if (!a || !b) return

  if (!peta) {
    peta = L.map(petaEl.value, { zoomControl: false, attributionControl: false })
    L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(peta)

    // dragstart/zoomstart juga menyala saat PETA sendiri yang bergerak, jadi
    // dijaga penanda `sedangAtur` — tanpa itu pemusatan otomatis mematikan
    // dirinya sendiri di gambar pertama.
    peta.on('dragstart zoomstart', () => {
      if (!sedangAtur) ikuti.value = false
    })
  }

  const pa: L.LatLngTuple = [a.lat, a.lng]
  const pb: L.LatLngTuple = [b.lat, b.lng]

  penandaAmbil?.remove()
  penandaAntar?.remove()
  penandaKurir?.remove()
  penandaKurir = null

  penandaAmbil = L.marker(pa, { icon: pinIcon('#1e9bf0') }).addTo(peta)

  /*
   * Selama menjemput, yang ditunggu orang adalah kendaraan yang menuju DIA —
   * jadi yang digambar jalur kurir ke titik ambil, dan tujuan akhir belum ikut
   * ditampilkan supaya peta bisa dizum ke bagian yang sedang berjalan.
   *
   * Garis mengikuti jalan bila server tahu rutenya, lurus PUTUS-PUTUS bila
   * tidak — bentuk yang berbeda supaya tidak terbaca sebagai jalan yang sungguh
   * dilewati.
   */
  const menjemput = tahap.value === 'menjemput' && !!posisiKurir.value
  let titik: L.LatLngExpression[]
  let lewatJalan: boolean

  if (menjemput) {
    titik = (kurir.value?.rute as L.LatLngExpression[] | null | undefined) ?? [
      posisiKurir.value as L.LatLngTuple,
      pa,
    ]
    lewatJalan = !!kurir.value?.rute?.length
  } else {
    penandaAntar = L.marker(pb, { icon: pinIcon('#f97316') }).addTo(peta)
    titik = (props.data.geometri as L.LatLngExpression[] | null | undefined) ?? [pa, pb]
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
  if (posisiKurir.value) {
    penandaKurir = L.marker(posisiKurir.value, { icon: ikonKendaraan() }).addTo(peta)
    semua.push(posisiKurir.value)
  }

  /*
   * Peta hanya dipusatkan ulang selama pengguna belum mengambil alih. Layar ini
   * bertanya ke server berulang kali, dan tiap jawaban menggambar ulang peta —
   * memanggil fitBounds setiap kali berarti peta yang baru digeser jari kembali
   * ke tempat semula dalam hitungan detik.
   */
  if (ikuti.value) {
    sedangAtur = true
    peta.fitBounds(L.latLngBounds(semua), {
      paddingTopLeft: [40, 120],
      paddingBottomRight: [40, PUNCAK_LEMBAR - 40],
    })
    requestAnimationFrame(() => (sedangAtur = false))
  }

  setTimeout(() => peta?.invalidateSize(), 120)
}

/** Kembalikan peta ke kendaraan, dan ikuti lagi setiap pembaruan berikutnya. */
function pusatkanKeKurir() {
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
  () => [props.data.tahap, props.data.geometri, posisiKurir.value, kurir.value?.rute, sisaKm.value] as const,
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
    // Papan klip ditolak peramban. Nomornya tetap terbaca di layar.
  }
}
</script>

<template>
  <div class="relative min-h-dvh w-full overflow-hidden bg-(--color-surface-container)">
    <!--
      isolate wajib. Panel Leaflet punya z-index sendiri (ubin 400, penanda 600)
      dan div peta ini tidak membentuk stacking context, jadi angka itu ikut
      bersaing di halaman: seluruh lapisan di atasnya tertimbun peta.
    -->
    <div ref="petaEl" class="absolute inset-0 isolate" aria-label="Peta kiriman"></div>

    <!--
      Lapisan kendali TIDAK menangkap sentuhan: sebagai div blok ia selebar
      layar dan setinggi isinya, dan selama ia menerima sentuhan seluruh pita
      itu menelan jari yang mencoba menggeser peta. Batas bawahnya diikat ke
      tinggi lembar supaya tombolnya tidak jatuh di baliknya saat layar pendek.
    -->
    <div
      class="absolute inset-x-0 top-0 z-20 max-w-[430px] mx-auto px-4 pt-[calc(0.75rem+env(safe-area-inset-top))] pb-3 flex flex-col pointer-events-none"
      :style="{ bottom: PUNCAK_LEMBAR + 'px' }"
    >
      <button
        type="button"
        class="pointer-events-auto w-full bg-(--color-surface-0) rounded-2xl shadow-lg px-4 py-3 text-left active:scale-[0.99] transition-transform"
        @click="terbuka = true"
      >
        <div class="flex items-center gap-3">
          <span class="w-6 h-6 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0">
            <Icon name="arrow-up" class="w-3.5 h-3.5 text-white" />
          </span>
          <span class="flex-1 min-w-0 text-[13px] font-semibold truncate">
            {{ data.ambil?.alamat }}
          </span>
        </div>
        <span class="block h-px bg-(--color-outline)/15 my-2.5 ml-9"></span>
        <div class="flex items-center gap-3">
          <span class="w-6 h-6 flex items-center justify-center shrink-0">
            <span class="w-3.5 h-3.5 rounded-full bg-orange-500"></span>
          </span>
          <span class="flex-1 min-w-0 text-[13px] font-semibold truncate">
            {{ data.antar?.alamat }}
          </span>
          <Icon name="chevron-right" class="w-4 h-4 shrink-0 text-(--color-on-surface-variant)" />
        </div>
      </button>

      <div class="flex-1"></div>

      <!-- Tombol pusatkan, tepat di atas tombol kembali dan selalu ada -->
      <div v-if="posisiKurir" class="mb-2 flex">
        <button
          type="button"
          class="pointer-events-auto inline-flex items-center gap-2 rounded-full shadow-lg pl-3 pr-4 py-2.5 text-[12.5px] font-extrabold active:scale-95 transition-[transform,background-color,color]"
          :class="ikuti ? 'bg-(--color-surface-0) text-(--color-on-surface-variant)' : 'bg-(--color-azure) text-white'"
          @click="pusatkanKeKurir"
        >
          <Icon name="crosshair" class="w-4 h-4" :class="ikuti ? 'text-(--color-azure)' : ''" />
          {{ ikuti ? 'Mengikuti kurir' : 'Kembali ke kurir' }}
        </button>
      </div>

      <div class="flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="pointer-events-auto w-10 h-10 rounded-full bg-(--color-surface-0) shadow-lg flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="emit('kembali')"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>

        <button
          type="button"
          aria-label="Bagikan kiriman"
          class="pointer-events-auto ml-auto w-10 h-10 rounded-full bg-(--color-surface-0) shadow-lg flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="emit('bagikan')"
        >
          <Icon name="send" class="w-4.5 h-4.5" />
        </button>
      </div>
    </div>

    <SheetGeser v-model="terbuka" :puncak="PUNCAK_LEMBAR" label="detail kiriman">
      <template #header>
        <div class="rounded-t-3xl bg-(--color-azure) px-5 pt-3.5 pb-6 flex items-center gap-3 text-white shadow-md">
          <span class="w-10 h-10 rounded-full bg-white flex items-center justify-center shrink-0 shadow-sm">
            <Icon name="package" class="w-5 h-5 text-(--color-azure)" />
          </span>
          <div class="min-w-0">
            <p class="text-[13.5px] font-extrabold leading-tight">{{ pita.judul }}</p>
            <p class="text-[11.5px] leading-snug text-white/85 mt-0.5">{{ pita.keterangan }}</p>
          </div>
        </div>
      </template>

      <div class="flex flex-col">
        <!-- Kurir: yang dicocokkan sebelum paket diserahkan -->
        <section v-if="kurir" :class="terbuka ? 'order-2' : 'order-1'" class="px-4 py-1.5">
          <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
            <div v-if="!terbuka" class="flex items-start gap-3">
              <div class="flex-1 min-w-0">
                <p class="text-[19px] font-display font-extrabold tracking-wide">{{ kurir.plat }}</p>
                <p class="mt-0.5 flex items-center gap-2 flex-wrap">
                  <span class="text-[15px] font-bold">{{ kurir.kendaraan }}</span>
                  <span class="rounded-full bg-(--color-surface-container) px-2.5 py-1 text-[11.5px] font-semibold">
                    {{ kurir.warna }}
                  </span>
                </p>
              </div>
              <AvatarPengemudi :nama="kurir.nama" class="w-14 h-14 shrink-0" />
            </div>

            <div
              class="flex items-center gap-3"
              :class="terbuka ? '' : 'mt-3 pt-3 border-t border-(--color-outline)/15'"
            >
              <div class="flex-1 min-w-0">
                <p class="text-[13.5px] font-semibold">{{ kurir.nama }}</p>
                <div class="mt-2 flex items-center gap-2 flex-wrap">
                  <span class="inline-flex items-center gap-1 rounded-full bg-(--color-surface-container) px-2.5 py-1 text-[12px] font-bold">
                    <Icon name="star" class="w-3.5 h-3.5 text-orange-500" />
                    {{ kurir.bintang }}
                  </span>
                  <span class="inline-flex items-center rounded-full bg-(--color-surface-container) px-2.5 py-1 text-[12px] font-semibold">
                    {{ kurir.kiriman.toLocaleString('id-ID') }} kiriman
                  </span>
                </div>
              </div>
              <AvatarPengemudi v-if="terbuka" :nama="kurir.nama" class="w-14 h-14 shrink-0" />
            </div>

            <div v-if="!terbuka" class="mt-3.5 flex items-center gap-2.5">
              <a
                href="tel:+62000000000"
                aria-label="Telepon kurir"
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
                  Kirim chat ke kurir
                </span>
                <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface-variant)" />
              </button>
            </div>
          </div>
        </section>

        <!-- Rute kiriman -->
        <section :class="terbuka ? 'order-1' : 'order-2'" class="px-4 py-1.5">
          <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
            <div class="flex gap-3">
              <div class="flex flex-col items-center pt-1 shrink-0">
                <span class="w-3 h-3 rounded-full bg-(--color-azure)"></span>
                <span class="w-0.5 flex-1 my-1 border-l border-dashed border-(--color-outline)/50"></span>
                <span class="w-3 h-3 rounded-full bg-orange-500"></span>
              </div>
              <div class="flex-1 min-w-0 flex flex-col gap-2.5">
                <div>
                  <p class="text-[11px] text-(--color-on-surface-variant)">Diambil dari</p>
                  <p class="text-[14px] font-extrabold leading-tight mt-0.5">{{ data.ambil?.alamat }}</p>
                  <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
                    {{ data.ambil?.nama }} · {{ data.ambil?.telepon }}
                  </p>
                </div>
                <p class="text-[12px] font-semibold text-(--color-on-surface-variant)">
                  {{ data.km?.toFixed(1).replace('.', ',') }} km
                </p>
                <div>
                  <p class="text-[11px] text-(--color-on-surface-variant)">Diantar ke</p>
                  <p class="text-[14px] font-extrabold leading-tight mt-0.5">{{ data.antar?.alamat }}</p>
                  <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
                    {{ data.antar?.nama }} · {{ data.antar?.telepon }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </section>

        <!--
          ISI PAKET — bagian yang tidak ada di BisaJemput.

          Di sinilah beda kedua menu itu: yang dijemput barang, dan pengirim
          perlu memastikan yang dijemput memang barangnya sebelum menyerahkannya
          ke orang yang baru ia temui. Kode terimanya juga di sini, karena
          itulah yang harus ia sampaikan ke penerima sebelum kurir tiba.
        -->
        <section class="order-3 px-4 py-1.5">
          <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
            <p class="text-[14px] font-display font-extrabold mb-3">Isi kiriman</p>

            <div class="flex flex-col gap-2.5 text-[13px]">
              <div class="flex items-start justify-between gap-3">
                <span class="text-(--color-on-surface-variant) shrink-0">Barang</span>
                <span class="font-semibold text-right">{{ data.isi || '—' }}</span>
              </div>
              <div class="flex items-start justify-between gap-3">
                <span class="text-(--color-on-surface-variant) shrink-0">Ukuran</span>
                <span class="font-semibold text-right">
                  {{ ukuran?.label ?? data.ukuran }}
                  <template v-if="ukuran"> · sampai {{ ukuran.berat }} kg</template>
                </span>
              </div>
              <div class="flex items-start justify-between gap-3">
                <span class="text-(--color-on-surface-variant) shrink-0">Kendaraan</span>
                <span class="font-semibold text-right">{{ data.label ?? '—' }}</span>
              </div>
            </div>

            <!--
              Plafon proteksi disebut ANGKANYA. Ganti rugi tanpa angka baru
              ketahuan batasnya justru saat barang benar-benar hilang.
            -->
            <p
              v-if="data.proteksi_plafon > 0"
              class="mt-3 pt-3 border-t border-(--color-outline)/15 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)"
            >
              Diproteksi sampai {{ rupiah(data.proteksi_plafon) }} sesuai nilai yang kamu daftarkan.
            </p>

            <!--
              Kode terima hanya muncul DI SINI, di layar pemilik kiriman. Kode
              itu gunanya memastikan paket diserahkan ke orang yang benar;
              menampilkannya di tempat lain menghapus gunanya.
            -->
            <div
              v-if="data.kode_terima"
              class="mt-3 rounded-xl bg-(--color-surface-container) p-3.5 flex items-center gap-3"
            >
              <div class="flex-1 min-w-0">
                <p class="text-[12.5px] font-bold">Kode terima paket</p>
                <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant) mt-0.5">
                  Berikan ke penerima. Kurir hanya menyerahkan kalau kodenya cocok.
                </p>
              </div>
              <span class="text-[22px] font-display font-extrabold tracking-[0.18em] shrink-0">
                {{ data.kode_terima }}
              </span>
            </div>
          </div>
        </section>

        <!-- Metode pembayaran -->
        <section class="order-4 px-4 py-1.5">
          <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
            <p class="text-[14px] font-display font-extrabold">Metode pembayaran</p>
            <div class="mt-2.5 flex items-center gap-3">
              <MetodeBayarIcon :id="(data.metode ?? 'tunai') as MetodeId" />
              <span class="flex-1 text-[13.5px] font-semibold">{{ labelMetode(data.metode) }}</span>
              <span class="text-[14px] font-extrabold">{{ rupiah(totalTagihan) }}</span>
            </div>
          </div>
        </section>

        <!--
          Kasih tip, tepat di atas rincian biaya — sepola dengan BisaJemput.

          BisaKirim bahkan tidak punya layar penilaian tempat tip bisa
          menumpang, jadi tanpa bagian ini tidak ada jalan sama sekali untuk
          berterima kasih. Momennya pun memang di sini: saat kurirnya masih
          terlihat, bukan setelah paketnya sampai di kota lain.
        -->
        <section v-if="bisaTip" class="order-5 px-4 py-1.5">
          <div class="rounded-2xl bg-(--color-surface-0) border border-(--color-outline)/30 shadow-sm overflow-hidden">
            <div class="p-4">
              <div class="flex items-start gap-3">
                <span class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
                  <Icon name="sparkle" class="w-5 h-5 text-amber-500" />
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-[15px] font-display font-extrabold leading-tight">
                    {{ tipTerkirim > 0 ? 'Terima kasih!' : 'Kasih tip buat kurir' }}
                  </p>
                  <p class="mt-0.5 text-[12px] leading-snug text-(--color-on-surface-variant)">
                    <template v-if="tipTerkirim > 0">
                      Tip {{ rupiah(tipTerkirim) }} sudah diteruskan ke kurir.
                    </template>
                    <template v-else>Diterima kurir seluruhnya, tanpa potongan.</template>
                  </p>
                </div>
              </div>

              <button
                v-if="tipTerkirim === 0 && !tipTerbuka"
                type="button"
                class="mt-3.5 inline-flex items-center gap-2 h-11 pl-5 pr-4 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold active:scale-[0.97] transition-transform"
                @click="tipTerbuka = true"
              >
                Kasih tip
                <Icon name="arrow-right" class="w-4.5 h-4.5 text-white" />
              </button>
            </div>

            <!-- Pemilih nominal, muncul begitu ajakan ditekan -->
            <div v-if="tipTerbuka && tipTerkirim === 0" class="px-4 pb-4 -mt-1">
              <div class="grid grid-cols-4 gap-2">
                <button
                  v-for="n in PILIHAN_TIP"
                  :key="n"
                  type="button"
                  class="px-1 py-2 rounded-full border text-center text-[12.5px] font-bold transition-colors disabled:opacity-40"
                  :class="
                    tipDipilih === n
                      ? 'bg-(--color-azure) border-(--color-azure) text-white'
                      : 'border-(--color-outline)/40 text-(--color-on-surface)'
                  "
                  :disabled="mengirimTip"
                  @click="tipDipilih = n"
                >
                  {{ rupiah(n) }}
                </button>
              </div>

              <button
                type="button"
                class="mt-3 w-full h-11 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold active:scale-[0.98] transition-transform disabled:opacity-40"
                :disabled="!tipDipilih || mengirimTip"
                @click="kirimTip"
              >
                {{
                  mengirimTip
                    ? 'Mengirim…'
                    : tipDipilih
                      ? `Kasih tip ${rupiah(tipDipilih)}`
                      : 'Pilih nominal dulu'
                }}
              </button>

              <p v-if="galatTip" role="alert" class="mt-2 text-[11.5px] font-semibold text-(--color-error)">
                {{ galatTip }}
              </p>
              <!-- Tip tidak dipotong komisi; ditulis supaya orang tahu ke mana perginya. -->
              <p class="mt-2 text-[11px] leading-snug text-(--color-on-surface-variant)">
                Tip ditambahkan ke tagihan dan diteruskan utuh ke kurir.
              </p>
            </div>
          </div>
        </section>

        <!-- Rincian biaya -->
        <section class="order-6 px-4 py-1.5">
          <div class="bg-(--color-surface-0) rounded-2xl p-4 border border-(--color-outline)/30 shadow-sm">
            <p class="text-[14px] font-display font-extrabold mb-3">Rincian biaya</p>
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
              <!--
                Tip berdiri sebagai barisnya sendiri. Melebur ke ongkir membuat
                nota yang tidak bisa dijelaskan: angkanya naik tanpa ada tarif
                yang berubah.
              -->
              <div v-if="tipTerkirim > 0" class="flex justify-between gap-3">
                <span class="text-(--color-on-surface-variant)">Tip kurir</span>
                <span class="font-semibold">{{ rupiah(tipTerkirim) }}</span>
              </div>
            </div>
            <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex justify-between gap-3">
              <span class="text-[14px] font-extrabold">Total</span>
              <span class="text-[16px] font-extrabold">{{ rupiah(totalTagihan) }}</span>
            </div>
          </div>
        </section>

        <!-- Nomor kiriman -->
        <section class="order-7 px-4 py-1.5 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
          <button
            type="button"
            class="w-full rounded-xl bg-(--color-surface-0) border border-(--color-outline)/30 px-4 py-3 flex items-center justify-center gap-2 text-[12px] text-(--color-on-surface-variant) active:scale-[0.99] transition-transform"
            @click="salinNomor"
          >
            No. Kiriman — {{ data.nomor }}
            <Icon :name="nomorTersalin ? 'check' : 'copy'" class="w-3.5 h-3.5" />
          </button>
          <p v-if="nomorTersalin" class="mt-1.5 text-center text-[11px] text-(--color-on-surface-variant)">
            Nomor tersalin.
          </p>
        </section>
      </div>
    </SheetGeser>
  </div>
</template>
