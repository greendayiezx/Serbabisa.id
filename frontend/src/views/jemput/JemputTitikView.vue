<script setup lang="ts">
/**
 * BisaJemput — konfirmasi titik jemput.
 *
 * Layar ini SELALU dilewati, dan tidak bisa dilompati dengan menekan lanjut
 * lebih cepat: penanda konfirmasinya hanya menyala dari sini, dan server
 * menolak pesanan yang tidak membawanya.
 *
 * Alasannya bukan tata cara. Hasil pencarian alamat menunjuk ke tengah jalan
 * atau ke titik tengah sebuah gedung, dan GPS ponsel meleset belasan sampai
 * puluhan meter di antara gedung tinggi. Di gang, di komplek, di basement mal,
 * selisih itu berarti pengemudi berhenti di tempat yang tidak ada orangnya —
 * lalu menunggu, lalu membatalkan, dan yang kena biaya pembatalan justru
 * penumpang yang sebenarnya sudah berdiri di tempatnya.
 *
 * Karena itu petanya bisa digeser sampai pas, dan ada kolom catatan untuk hal
 * yang tidak bisa diwakili koordinat: "di depan pos satpam", "lobi belakang".
 */
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import JemputTitikSkeleton from '@/components/skeleton/JemputTitikSkeleton.vue'
import { useSkeleton } from '@/composables/useSkeleton'
import { TILE_URL, TILE_OPTIONS, pinIcon } from '@/lib/mapTiles'
import { labelKoordinat, reverseGeocode } from '@/lib/geocode'
import { useJemputStore } from '@/stores/jemput'
import { useLocationStore } from '@/stores/location'

const router = useRouter()
const kembali = useKembali()
const jemputStore = useJemputStore()
const locationStore = useLocationStore()

const awal = jemputStore.jemput ??
  locationStore.draft ?? { alamat: '', lat: -6.2088, lng: 106.8456 }

const titik = ref({ lat: awal.lat, lng: awal.lng })
const alamat = ref(awal.alamat)
const catatan = ref(jemputStore.jemput?.catatan ?? '')
const membaca = ref(false)
const lembar = ref(false)

/** Alamat ini pernah dipakai sebelumnya — ditandai, bukan diklaim akurat. */
const pernahKeSini = ref(false)

const { tampil: skelTampil, tandaiSiap } = useSkeleton()

const petaEl = ref<HTMLDivElement | null>(null)
let peta: L.Map | null = null
let penanda: L.Marker | null = null
let jedaBaca: ReturnType<typeof setTimeout> | null = null

function bacaAlamat() {
  if (jedaBaca) clearTimeout(jedaBaca)
  jedaBaca = setTimeout(async () => {
    membaca.value = true
    try {
      alamat.value =
        (await reverseGeocode(titik.value.lat, titik.value.lng)) ??
        labelKoordinat(titik.value.lat, titik.value.lng)
    } finally {
      membaca.value = false
    }
  }, 550)
}

function pindah(ke: L.LatLng) {
  titik.value = { lat: ke.lat, lng: ke.lng }
  penanda?.setLatLng(ke)
  // Titik bergeser berarti alamatnya bukan lagi alamat yang pernah dipakai.
  pernahKeSini.value = false
  bacaAlamat()
}

function pasangPeta() {
  if (!petaEl.value || peta) return

  peta = L.map(petaEl.value, {
    center: [titik.value.lat, titik.value.lng],
    zoom: 17,
    zoomControl: false,
    attributionControl: false,
  })
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(peta)

  penanda = L.marker([titik.value.lat, titik.value.lng], {
    icon: pinIcon(),
    draggable: true,
  }).addTo(peta)

  penanda.on('dragend', () => {
    const ke = penanda!.getLatLng()
    pindah(ke)
  })
  peta.on('click', (e: L.LeafletMouseEvent) => pindah(e.latlng))

  // Leaflet salah mengukur tinggi kalau wadahnya baru saja dipasang.
  setTimeout(() => peta?.invalidateSize(), 120)
}

function lepasPeta() {
  peta?.remove()
  peta = null
  penanda = null
}

onMounted(async () => {
  pernahKeSini.value = locationStore
    .loadSearchHistory()
    .some((h) => h.address === alamat.value)

  tandaiSiap()

  /*
   * Datang ke sini tanpa alamat sama sekali — misalnya dari tautan langsung —
   * akan menampilkan peta di titik bawaan dengan tombol mati dan tidak ada
   * petunjuk kenapa. Alamat titik awalnya dibaca sekali supaya layar ini
   * selalu punya sesuatu untuk dikonfirmasi atau digeser.
   */
  if (!alamat.value.trim()) bacaAlamat()
})

onBeforeUnmount(() => {
  if (jedaBaca) clearTimeout(jedaBaca)
  lepasPeta()
})

/*
 * Peta dipasang SETELAH skeleton pergi, bukan di onMounted.
 *
 * Skeleton menempati seluruh template lewat v-if, jadi saat onMounted berjalan
 * div petanya belum ada di DOM dan ref-nya masih null — pasangPeta() berhenti
 * di penjagaannya, lalu tidak ada lagi yang memanggilnya.
 */
watch(skelTampil, async (masihSkeleton) => {
  if (masihSkeleton) return
  await nextTick()
  pasangPeta()
})

/* Lembar pilih lokasi menutupi peta; petanya dilepas supaya panenya tidak
   menembus lembar (pane Leaflet punya z-index sendiri). */
watch(lembar, async (buka) => {
  if (buka) {
    lepasPeta()
    return
  }
  await nextTick()
  pasangPeta()
})

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  titik.value = { lat: l.lat, lng: l.lng }
  alamat.value = l.alamat
  pernahKeSini.value = false
  lembar.value = false
}

function konfirmasi() {
  if (!alamat.value.trim()) return

  jemputStore.setJemput({
    alamat: alamat.value,
    lat: titik.value.lat,
    lng: titik.value.lng,
  })
  jemputStore.konfirmasiJemput(catatan.value.trim() || null)
  locationStore.setDraft({ alamat: alamat.value, lat: titik.value.lat, lng: titik.value.lng })

  router.push({ name: 'task-jemput-pesan' })
}
</script>

<template>
  <JemputTitikSkeleton v-if="skelTampil" />

  <div v-else class="relative min-h-dvh w-full bg-(--color-surface-container) isolate">
    <!-- Peta memenuhi layar; lembar konfirmasi duduk di atasnya. -->
    <div ref="petaEl" class="absolute inset-0 z-0" aria-label="Peta titik jemput"></div>

    <button
      type="button"
      aria-label="Kembali"
      class="absolute top-4 left-4 z-20 w-11 h-11 rounded-full bg-(--color-surface-0) shadow-lg flex items-center justify-center active:scale-95 transition-transform"
      @click="kembali"
    >
      <Icon name="arrow-left" class="w-5 h-5" />
    </button>

    <div
      v-if="pernahKeSini"
      class="absolute top-5 left-1/2 -translate-x-1/2 z-20 flex items-center gap-2 rounded-full bg-(--color-surface-0) shadow-lg px-3.5 py-2"
    >
      <Icon name="clock" class="w-4 h-4 text-(--color-azure)" />
      <span class="text-[12px] font-semibold">Kamu pernah ke sini</span>
    </div>

    <!-- Lembar konfirmasi -->
    <section
      class="absolute inset-x-0 bottom-0 z-20 bg-(--color-surface-0) rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.16)]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>

        <div class="flex items-center justify-between gap-3 mb-3">
          <h1 class="text-[16px] font-display font-extrabold">Set lokasi jemput</h1>
          <button
            type="button"
            class="px-4 h-9 rounded-full border-[1.5px] border-(--color-outline)/50 text-[12.5px] font-extrabold active:scale-95 transition-transform"
            @click="lembar = true"
          >
            Edit
          </button>
        </div>

        <div class="rounded-2xl bg-(--color-azure)/10 border border-(--color-azure)/20 p-4">
          <span
            v-if="pernahKeSini"
            class="inline-block mb-1.5 px-2.5 py-0.5 rounded-full bg-(--color-azure) text-white text-[10.5px] font-extrabold"
          >
            Pernah ke sini
          </span>
          <p class="text-[14px] font-extrabold leading-snug">
            {{ membaca ? 'Membaca alamat…' : alamat || 'Geser peta ke titik jemputmu' }}
          </p>
          <p class="mt-1 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
            {{ titik.lat.toFixed(5) }}, {{ titik.lng.toFixed(5) }}
          </p>
        </div>

        <!--
          Yang tidak bisa diwakili koordinat. Kalimat sependek "di depan pos
          satpam" menghemat lima menit telepon di lokasi.
        -->
        <input
          v-model="catatan"
          type="text"
          maxlength="120"
          placeholder="Patokan untuk pengemudi (mis. depan pos satpam)"
          class="mt-3 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
        />



        <button
          type="button"
          class="mt-3 w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="!alamat.trim() || membaca"
          @click="konfirmasi"
        >
          Konfirmasi titik jemput
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </div>
    </section>

    <SheetPilihLokasi
      :tampil="lembar"
      :alamat="alamat"
      :lat="titik.lat"
      :lng="titik.lng"
      judul-peta="Set titik jemput"
      @tutup="lembar = false"
      @pilih="terimaLokasi"
    />
  </div>
</template>
