<script setup lang="ts">
/**
 * Pemilih lokasi layar penuh — dua layar dalam satu komponen.
 *
 * 1. PETA. Pin biru yang bisa diseret, sama seperti peta di menu lain
 *    (LocationView) - supaya satu gerakan yang dipelajari pengguna berlaku di
 *    seluruh aplikasi. Mengetuk peta juga memindahkan pin ke titik itu.
 * 2. CARI. Satu kolom pencarian, riwayat lokasi, dan jalan kembali ke peta.
 *
 * Alamat di peta ditulis ulang tiap kali geseran berhenti, dengan jeda —
 * memanggil layanan geocoding tiap piksel akan diblokir penyedia dan
 * menghabiskan kuota.
 */
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import Icon from '@/components/icons/Icon.vue'
import { TILE_URL, TILE_OPTIONS, pinIcon } from '@/lib/mapTiles'
import { cariLokasi, labelKoordinat, reverseGeocode, type HasilLokasi } from '@/lib/geocode'
import { useLocationStore } from '@/stores/location'

const props = withDefaults(
  defineProps<{
    tampil: boolean
    /** Titik awal saat pemilih dibuka. */
    alamat?: string
    lat?: number
    lng?: number
    judul?: string
    /** Label kolom pencarian — halaman ini hanya punya satu tujuan. */
    labelCari?: string
    /** Buka langsung di layar pencarian, bukan peta. */
    mulaiDariCari?: boolean
  }>(),
  {
    alamat: '',
    lat: -6.2088,
    lng: 106.8456,
    judul: 'Set lokasi pengerjaan',
    labelCari: 'Cari lokasi tujuan',
    mulaiDariCari: false,
  },
)

const emit = defineEmits<{
  tutup: []
  pilih: [{ alamat: string; lat: number; lng: number }]
}>()

const locationStore = useLocationStore()

type Layar = 'peta' | 'cari'
const layar = ref<Layar>('peta')

const titik = ref({ lat: props.lat, lng: props.lng })
const alamatTitik = ref(props.alamat)
const membaca = ref(false)

/* ---------------- Peta ---------------- */
const petaEl = ref<HTMLDivElement | null>(null)
let peta: L.Map | null = null
let penanda: L.Marker | null = null
let jedaBaca: ReturnType<typeof setTimeout> | null = null

/**
 * Wadah peta yang pernah dipakai Leaflet menyimpan penanda internal. Kalau
 * elemennya dipakai ulang tanpa dibersihkan, Leaflet menolak menginisialisasi
 * dengan "Map container is already initialized".
 */
function bersihkanWadah(el: HTMLElement) {
  delete (el as unknown as { _leaflet_id?: number })._leaflet_id
  el.innerHTML = ''
}

function lepasPeta() {
  if (jedaBaca) clearTimeout(jedaBaca)
  jedaBaca = null
  peta?.remove()
  peta = null
  penanda = null
}

/**
 * Pindahkan titik terpilih, lalu baca alamatnya setelah jeda.
 *
 * Jedanya penting: seretan menghasilkan puluhan posisi beruntun, dan memanggil
 * layanan geocoding pada tiap posisi akan diblokir penyedianya.
 */
function pindahkanTitik(lat: number, lng: number) {
  titik.value = { lat, lng }
  if (jedaBaca) clearTimeout(jedaBaca)
  jedaBaca = setTimeout(() => void bacaAlamat(lat, lng), 400)
}

async function bacaAlamat(lat: number, lng: number) {
  membaca.value = true
  alamatTitik.value = await reverseGeocode(lat, lng)
  membaca.value = false
}

async function siapkanPeta() {
  await nextTick()
  const el = petaEl.value
  if (!el) return

  lepasPeta()
  bersihkanWadah(el)

  peta = L.map(el, {
    center: [titik.value.lat, titik.value.lng],
    zoom: 17,
    zoomControl: false,
    attributionControl: true,
  })
  L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(peta)

  // autoPan: Leaflet menggeser petanya sendiri ketika pin diseret ke tepi layar,
  // jadi pengguna tidak pernah kehilangan pinnya di luar pandangan.
  penanda = L.marker([titik.value.lat, titik.value.lng], {
    icon: pinIcon(),
    draggable: true,
    autoPan: true,
  }).addTo(peta)

  penanda.on('dragend', () => {
    const ll = penanda!.getLatLng()
    pindahkanTitik(ll.lat, ll.lng)
  })

  // Mengetuk peta memindahkan pin - lebih cepat daripada menyeret jauh.
  peta.on('click', (e: L.LeafletMouseEvent) => {
    penanda?.setLatLng(e.latlng)
    peta?.panTo(e.latlng, { animate: true, duration: 0.3 })
    pindahkanTitik(e.latlng.lat, e.latlng.lng)
  })

  requestAnimationFrame(() => peta?.invalidateSize())

  if (!alamatTitik.value) void bacaAlamat(titik.value.lat, titik.value.lng)
}

watch(
  () => props.tampil,
  async (v) => {
    if (!v) {
      lepasPeta()
      return
    }

    titik.value = { lat: props.lat, lng: props.lng }
    alamatTitik.value = props.alamat
    layar.value = props.mulaiDariCari ? 'cari' : 'peta'
    kata.value = ''
    hasil.value = []

    void locationStore.fetchRecent().catch(() => {})
    if (layar.value === 'peta') await siapkanPeta()
  },
)

watch(layar, async (v) => {
  if (!props.tampil) return
  if (v === 'peta') await siapkanPeta()
  else lepasPeta()
})

onBeforeUnmount(lepasPeta)

/* ---------------- Pencarian ---------------- */
const kata = ref('')
const hasil = ref<HasilLokasi[]>([])
const mencari = ref(false)
let jedaCari: ReturnType<typeof setTimeout> | null = null

watch(kata, (q) => {
  if (jedaCari) clearTimeout(jedaCari)
  if (q.trim().length < 3) {
    hasil.value = []
    mencari.value = false
    return
  }

  mencari.value = true
  jedaCari = setTimeout(async () => {
    // Titik peta yang sedang dilihat jadi bias pencarian.
    hasil.value = await cariLokasi(q, { lat: titik.value.lat, lng: titik.value.lng })
    mencari.value = false
  }, 400)
})

onBeforeUnmount(() => {
  if (jedaCari) clearTimeout(jedaCari)
})

/** Riwayat lokasi milik pengguna, ditampilkan selagi kolom cari kosong. */
const riwayat = computed(() =>
  locationStore.recent.slice(0, 6).map((r) => ({
    label: r.label || r.address,
    alamat: r.address,
    lat: r.lat,
    lng: r.lng,
  })),
)

/** Dari hasil pencarian: pindahkan peta ke sana lalu kembali ke layar peta. */
async function pakaiHasil(h: { label: string; lat: number; lng: number }) {
  titik.value = { lat: h.lat, lng: h.lng }
  alamatTitik.value = h.label
  layar.value = 'peta'
}

function konfirmasi() {
  emit('pilih', {
    alamat: alamatTitik.value || labelKoordinat(titik.value.lat, titik.value.lng),
    lat: titik.value.lat,
    lng: titik.value.lng,
  })
}
</script>

<template>
  <Teleport to="body">
    <div v-if="tampil" class="fixed inset-0 z-[90] bg-(--color-surface) flex flex-col">
      <!-- ============ LAYAR PETA ============ -->
      <template v-if="layar === 'peta'">
        <div class="relative flex-1 min-h-0">
          <div ref="petaEl" class="absolute inset-0"></div>

          <button
            type="button"
            aria-label="Tutup"
            class="absolute top-4 left-4 z-[800] w-10 h-10 rounded-full bg-white text-(--color-on-surface) flex items-center justify-center shadow-md active:scale-95 transition-transform"
            @click="emit('tutup')"
          >
            <Icon name="arrow-left" class="w-5 h-5" />
          </button>
        </div>

        <!-- Lembar bawah -->
        <div class="shrink-0 bg-(--color-surface-0) rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.10)]">
          <div class="max-w-[430px] mx-auto px-5 pt-4 pb-[calc(1rem+env(safe-area-inset-bottom))]">
            <div class="flex items-center justify-between gap-3 mb-3">
              <h2 class="text-[15px] font-display font-extrabold">{{ judul }}</h2>
              <button
                type="button"
                class="shrink-0 rounded-full border border-(--color-azure) text-(--color-azure) text-[12.5px] font-bold px-4 py-1.5 active:scale-95 transition-transform"
                @click="layar = 'cari'"
              >
                Edit
              </button>
            </div>

            <div class="flex items-start gap-3 rounded-2xl bg-(--color-secondary-container)/40 p-3.5">
              <span
                class="w-8 h-8 rounded-full bg-(--color-azure) text-white flex items-center justify-center shrink-0"
              >
                <Icon name="pin" class="w-4 h-4" />
              </span>
              <p class="flex-1 min-w-0 text-[12.5px] leading-snug">
                <span v-if="membaca" class="text-(--color-on-surface-variant)">
                  Membaca alamat&hellip;
                </span>
                <span v-else>{{ alamatTitik }}</span>
              </p>
            </div>

            <button
              type="button"
              :disabled="membaca"
              class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-bold mt-4 active:scale-[0.98] transition-transform disabled:opacity-50"
              @click="konfirmasi"
            >
              Konfirmasi titik
            </button>
          </div>
        </div>
      </template>

      <!-- ============ LAYAR CARI ============ -->
      <template v-else>
        <header class="shrink-0 bg-(--color-surface-0) border-b border-(--color-outline)/10">
          <div class="max-w-[430px] mx-auto px-4 h-16 flex items-center gap-3">
            <button
              type="button"
              aria-label="Tutup"
              class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
              @click="emit('tutup')"
            >
              <Icon name="x" class="w-5 h-5" />
            </button>
            <h2 class="text-[15px] font-extrabold">{{ judul }}</h2>
          </div>
        </header>

        <div class="flex-1 min-h-0 overflow-y-auto">
          <div class="max-w-[430px] mx-auto px-4 pt-3 pb-8">
            <!-- Satu kolom saja: yang diubah hanya lokasi pengerjaannya. -->
            <div class="flex items-center gap-3 rounded-2xl bg-(--color-surface-container) px-3.5 py-3">
              <span
                class="w-6 h-6 rounded-full bg-(--color-on-surface-variant)/70 text-white flex items-center justify-center shrink-0"
              >
                <Icon name="pin" class="w-3.5 h-3.5" />
              </span>
              <input
                v-model="kata"
                type="text"
                :placeholder="labelCari"
                class="flex-1 min-w-0 bg-transparent text-[13.5px] outline-none placeholder:text-(--color-on-surface-variant)"
              />
              <button
                v-if="kata"
                type="button"
                aria-label="Kosongkan"
                class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center active:scale-90 transition-transform"
                @click="kata = ''"
              >
                <Icon name="x" class="w-3.5 h-3.5 text-(--color-on-surface-variant)" />
              </button>
            </div>

            <button
              type="button"
              class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-(--color-outline)/40 px-3.5 py-2 text-[12.5px] font-bold active:scale-95 transition-transform"
              @click="layar = 'peta'"
            >
              <Icon name="map" class="w-4 h-4 text-(--color-azure)" />
              Pilih lewat peta
            </button>

            <!-- Hasil pencarian -->
            <p v-if="mencari" class="mt-5 text-[12.5px] text-(--color-on-surface-variant)">
              Mencari&hellip;
            </p>

            <ul v-else-if="hasil.length" class="mt-4 flex flex-col divide-y divide-(--color-outline)/12">
              <li v-for="(h, i) in hasil" :key="i">
                <button
                  type="button"
                  class="w-full flex items-start gap-3 py-3 text-left active:opacity-70"
                  @click="pakaiHasil(h)"
                >
                  <Icon name="pin" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-on-surface-variant)" />
                  <span class="flex-1 min-w-0">
                    <span class="block text-[12.5px] font-bold leading-snug truncate">{{ h.nama }}</span>
                    <span
                      v-if="h.alamat"
                      class="block text-[11.5px] text-(--color-on-surface-variant) leading-snug"
                    >
                      {{ h.alamat }}
                    </span>
                  </span>
                </button>
              </li>
            </ul>

            <p
              v-else-if="kata.trim().length >= 3"
              class="mt-5 text-[12.5px] text-(--color-on-surface-variant)"
            >
              Tidak ada hasil untuk “{{ kata }}”.
            </p>

            <!-- Riwayat, selagi belum mengetik -->
            <template v-else-if="riwayat.length">
              <h3 class="mt-5 mb-1 text-[11.5px] font-bold text-(--color-on-surface-variant)">
                Terakhir dipakai
              </h3>
              <ul class="flex flex-col divide-y divide-(--color-outline)/12">
                <li v-for="(r, i) in riwayat" :key="i">
                  <button
                    type="button"
                    class="w-full flex items-start gap-3 py-3 text-left active:opacity-70"
                    @click="pakaiHasil({ label: r.alamat || r.label, lat: r.lat, lng: r.lng })"
                  >
                    <Icon name="clock" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-on-surface-variant)" />
                    <span class="flex-1 min-w-0">
                      <span class="block text-[12.5px] font-bold truncate">{{ r.label }}</span>
                      <span class="block text-[11.5px] text-(--color-on-surface-variant) leading-snug">
                        {{ r.alamat }}
                      </span>
                    </span>
                  </button>
                </li>
              </ul>
            </template>
          </div>
        </div>
      </template>
    </div>
  </Teleport>
</template>
