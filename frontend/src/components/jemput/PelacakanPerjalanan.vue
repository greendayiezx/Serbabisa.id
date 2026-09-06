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
import { labelMetode } from '@/lib/metodeBayar'
import { rupiah } from '@/lib/jemput'
import type { Perjalanan } from '@/api/jemput'

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
    keterangan: 'Tunggu di titik jemput supaya tidak perlu berputar.',
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

/** Ikon kendaraan: mengikuti kelas yang dipesan, bukan selalu mobil. */
function ikonKendaraan(): L.DivIcon {
  const motor = (props.data.kelas ?? '').startsWith('motor')
  const isi = motor
    ? '<circle cx="7" cy="17" r="3.2" fill="none" stroke="#0A326B" stroke-width="2"/>' +
      '<circle cx="17" cy="17" r="3.2" fill="none" stroke="#0A326B" stroke-width="2"/>' +
      '<path d="M7 17 L11 10 H15 l2 7" fill="none" stroke="#8BC53F" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>'
    : '<rect x="3" y="10" width="18" height="7" rx="2.6" fill="#8BC53F"/>' +
      '<path d="M6 10 l2.4-4h7.2L18 10z" fill="#6FAE33"/>' +
      '<circle cx="7.5" cy="17.5" r="2.2" fill="#0A326B"/>' +
      '<circle cx="16.5" cy="17.5" r="2.2" fill="#0A326B"/>'

  return L.divIcon({
    className: '',
    html:
      '<svg viewBox="0 0 24 24" width="42" height="42" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.28))">' +
      '<circle cx="12" cy="12" r="11.5" fill="#FFFFFF"/>' +
      isi +
      '</svg>',
    iconSize: [42, 42],
    iconAnchor: [21, 21],
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
  penandaTujuan = L.marker(b, { icon: pinIcon('#f97316') }).addTo(peta)

  /*
   * Garis mengikuti jalan bila server tahu rutenya, lurus PUTUS-PUTUS bila
   * tidak — bentuk yang berbeda supaya tidak terbaca sebagai rute sungguhan.
   */
  const titik: L.LatLngExpression[] =
    (props.data.geometri as L.LatLngExpression[] | null | undefined) ?? [a, b]

  garis?.remove()
  bayang?.remove()
  bayang = L.polyline(titik, { color: '#1B2C5E', weight: 9, opacity: 0.22, lineJoin: 'round' }).addTo(peta)
  garis = L.polyline(titik, {
    color: '#8BC53F',
    weight: 5.5,
    lineJoin: 'round',
    dashArray: props.data.lewat_jalan ? undefined : '8 8',
  }).addTo(peta)

  const semua: L.LatLngTuple[] = [...(titik as L.LatLngTuple[])]
  if (posisiPengemudi.value) {
    penandaPengemudi = L.marker(posisiPengemudi.value, { icon: ikonKendaraan() }).addTo(peta)
    semua.push(posisiPengemudi.value)
  }

  /*
   * Petanya dipangkas di bawah oleh lembar yang menutupi layar. Tanpa padding
   * bawah yang besar, kendaraan dan titik jemput jatuh persis di balik lembar
   * itu — peta yang benar tapi tidak ada yang terlihat.
   */
  peta.fitBounds(L.latLngBounds(semua), { paddingTopLeft: [40, 120], paddingBottomRight: [40, 300] })
  setTimeout(() => peta?.invalidateSize(), 120)
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
  () => [props.data.tahap, props.data.geometri, posisiPengemudi.value] as const,
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
        <span class="block h-px bg-(--color-outline)/15 my-2.5 ml-9"></span>
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

      <div class="mt-3 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full bg-(--color-surface-0) shadow-lg flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="emit('kembali')"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>

        <!-- Sisa jarak hanya muncul kalau server mengirimkannya. -->
        <span
          v-if="sisaJarak"
          class="rounded-full bg-(--color-surface-0) shadow-lg px-3.5 py-2 text-[12.5px] font-extrabold"
        >
          {{ sisaJarak }} lagi
        </span>

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
      <!-- Pita keadaan, menempel di kepala lembar -->
      <div class="mx-4 mb-1 rounded-2xl bg-(--color-secondary-container) px-4 py-3 flex items-center gap-3">
        <span
          class="w-9 h-9 rounded-full bg-(--color-surface-0) flex items-center justify-center shrink-0"
        >
          <Icon name="car" class="w-5 h-5 text-(--color-on-secondary-container)" />
        </span>
        <div class="min-w-0">
          <p class="text-[13.5px] font-extrabold leading-tight">{{ pita.judul }}</p>
          <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant) mt-0.5">
            {{ pita.keterangan }}
          </p>
        </div>
      </div>

      <!-- Kendaraan dan pengemudi: yang dicocokkan sebelum naik -->
      <div v-if="pengemudi" class="px-5 pt-3">
        <div class="flex items-start gap-3">
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

        <div class="mt-3 pt-3 border-t border-(--color-outline)/15">
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

        <div class="mt-3.5 flex items-center gap-2.5">
          <a
            href="tel:+62000000000"
            aria-label="Telepon pengemudi"
            class="w-12 h-12 rounded-full bg-(--color-secondary-container) flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          >
            <Icon name="phone" class="w-5 h-5 text-(--color-on-secondary-container)" />
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

        <p
          v-if="pengemudi.telepon_tersamar"
          class="mt-2 text-[11px] leading-snug text-(--color-on-surface-variant)"
        >
          Panggilan lewat aplikasi. Nomor kamu dan pengemudi sama-sama disamarkan.
        </p>
      </div>

      <div class="h-2.5 bg-(--color-surface-container) mt-4"></div>

      <!-- ── Yang muncul setelah lembarnya ditarik ── -->
      <div class="px-5 py-4">
        <div class="flex items-center justify-between gap-3">
          <p class="text-[14px] font-display font-extrabold">Metode pembayaran</p>
        </div>
        <div class="mt-2.5 flex items-center gap-3">
          <Icon name="wallet" class="w-5 h-5 text-(--color-azure) shrink-0" />
          <span class="flex-1 text-[13.5px] font-semibold">{{ labelMetode(data.metode) }}</span>
          <span class="text-[14px] font-extrabold">{{ rupiah(data.total) }}</span>
        </div>
      </div>

      <div class="h-2.5 bg-(--color-surface-container)"></div>

      <!-- Rute -->
      <div class="px-5 py-4">
        <p class="text-[14px] font-display font-extrabold mb-3">Rute perjalanan</p>
        <div class="flex gap-3">
          <div class="flex flex-col items-center pt-1 shrink-0">
            <span class="w-3 h-3 rounded-full bg-(--color-azure)"></span>
            <span class="w-0.5 flex-1 my-1 bg-(--color-outline)/30"></span>
            <span class="w-3 h-3 rounded-full bg-orange-500"></span>
          </div>
          <div class="flex-1 min-w-0 flex flex-col gap-4">
            <div>
              <p class="text-[11px] font-bold uppercase tracking-wider text-(--color-azure)">
                Titik jemput
              </p>
              <p class="text-[13px] font-semibold leading-snug">{{ data.jemput?.alamat }}</p>
              <p
                v-if="data.jemput?.catatan"
                class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5"
              >
                {{ data.jemput.catatan }}
              </p>
            </div>
            <div>
              <p class="text-[11px] font-bold uppercase tracking-wider text-orange-500">Tujuan</p>
              <p class="text-[13px] font-semibold leading-snug">{{ data.tujuan?.alamat }}</p>
            </div>
          </div>
        </div>
        <p class="mt-3 text-[12px] text-(--color-on-surface-variant)">
          {{ data.km?.toFixed(1).replace('.', ',') }} km · {{ data.menit }} menit
        </p>
      </div>

      <div class="h-2.5 bg-(--color-surface-container)"></div>

      <!-- Rincian tarif -->
      <div class="px-5 py-4">
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

      <div class="h-2.5 bg-(--color-surface-container)"></div>

      <!-- Keselamatan -->
      <div class="px-5 py-4">
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
        <p class="mt-2.5 text-[11px] leading-snug text-(--color-on-surface-variant)">
          Tombol darurat menelepon 112 langsung. "Bagikan" mengirim titik jemput, tujuan, dan pelat
          nomor ke orang yang kamu pilih.
        </p>
      </div>

      <!-- Nomor transaksi dan pembatalan -->
      <div class="px-5 pb-[calc(1.5rem+env(safe-area-inset-bottom))]">
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
          class="mt-3 w-full h-12 rounded-full bg-(--color-error)/10 text-(--color-error) text-[13.5px] font-extrabold active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="membatalkan"
          @click="emit('batal')"
        >
          {{ membatalkan ? 'Membatalkan…' : 'Batalkan booking' }}
        </button>
        <!--
          Disebut apa adanya: pembatalan sesudah pengemudi berangkat bukan hal
          yang gratis bagi orang yang sudah menempuh jalan ke sini.
        -->
        <p
          v-if="tahap === 'dijemput' || tahap === 'tiba'"
          class="mt-2 text-[11px] leading-snug text-center text-(--color-on-surface-variant)"
        >
          Pengemudi sudah berangkat menjemput.
        </p>
      </div>
    </SheetGeser>
  </div>
</template>
