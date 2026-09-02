<script setup lang="ts">
/**
 * BisaJemput — tujuan dan pilihan kendaraan.
 *
 * Titik jemput sudah dikonfirmasi di layar sebelumnya dan ditampilkan di sini
 * apa adanya; kalau belum, halaman ini memulangkan pengguna ke sana alih-alih
 * menebak titiknya sendiri.
 *
 * SEMUA HARGA DI LAYAR INI DATANG DARI SERVER. Tidak ada satu pun tarif yang
 * dihitung di sini, dan itu bukan soal kerapian: tarifnya bergantung jarak
 * jalan, lama tempuh, dan pengali jam sibuk — tiga hal yang kalau disalin ke
 * klien akan menampilkan angka yang berbeda dari yang ditagih.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PemuatBerputar from '@/components/ui/PemuatBerputar.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import { TILE_URL, TILE_OPTIONS, pinIcon } from '@/lib/mapTiles'
import { useJemputStore } from '@/stores/jemput'
import { estimasiJemput, type HasilEstimasi } from '@/api/jemput'
import { pesanError } from '@/api/belanja'
import { rentangMenit, rupiah, type PilihanJemput } from '@/lib/jemput'

const router = useRouter()
const kembali = useKembali()
const jemputStore = useJemputStore()

const kelas = ref<'motor' | 'mobil'>('motor')
const lembarTujuan = ref(false)
const memuat = ref(false)
const galat = ref<string | null>(null)
const hasil = ref<HasilEstimasi | null>(null)
const dipilih = ref<PilihanJemput | null>(null)

const jemput = computed(() => jemputStore.jemput)
const tujuan = computed(() => jemputStore.tujuan)

const pilihanKelas = computed(() =>
  (hasil.value?.pilihan ?? []).filter((p) => p.kelas === kelas.value),
)

/** Satu kartu per kendaraan; varian jadi baris di dalamnya, seperti di Gojek. */
const kartu = computed(() => {
  const per = new Map<string, PilihanJemput[]>()
  for (const p of pilihanKelas.value) {
    per.set(p.tipe, [...(per.get(p.tipe) ?? []), p])
  }
  return [...per.entries()].map(([tipe, varian]) => ({ tipe, varian }))
})

/* ────────── Peta rute ────────── */
const petaEl = ref<HTMLDivElement | null>(null)
let peta: L.Map | null = null
let garis: L.Polyline | null = null

function gambarPeta() {
  if (!petaEl.value || !jemput.value) return

  if (!peta) {
    peta = L.map(petaEl.value, { zoomControl: false, attributionControl: false, dragging: false })
    L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(peta)
  }

  const a: L.LatLngTuple = [jemput.value.lat, jemput.value.lng]
  L.marker(a, { icon: pinIcon() }).addTo(peta)

  if (tujuan.value) {
    const b: L.LatLngTuple = [tujuan.value.lat, tujuan.value.lng]
    L.marker(b, { icon: pinIcon() }).addTo(peta)
    garis?.remove()
    garis = L.polyline([a, b], { color: '#8BC53F', weight: 4 }).addTo(peta)
    peta.fitBounds(L.latLngBounds([a, b]).pad(0.35))
  } else {
    peta.setView(a, 16)
  }

  setTimeout(() => peta?.invalidateSize(), 120)
}

async function muatEstimasi() {
  if (!jemput.value || !tujuan.value) return

  memuat.value = true
  galat.value = null
  try {
    hasil.value = await estimasiJemput({
      jemput_lat: jemput.value.lat,
      jemput_lng: jemput.value.lng,
      tujuan_lat: tujuan.value.lat,
      tujuan_lng: tujuan.value.lng,
    })
    // Pilihan termurah di kelas yang sedang dibuka jadi pilihan awal.
    dipilih.value =
      [...hasil.value.pilihan]
        .filter((p) => p.kelas === kelas.value)
        .sort((a, b) => a.tarif_setelah_promo - b.tarif_setelah_promo)[0] ?? null
  } catch (e) {
    hasil.value = null
    dipilih.value = null
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
}

onMounted(async () => {
  // Aturan yang sama seperti di server: tanpa titik jemput terkonfirmasi,
  // tidak ada yang boleh dilanjutkan dari sini.
  if (!jemputStore.jemput || !jemputStore.jemputDikonfirmasi) {
    router.replace({ name: 'task-jemput-titik' })
    return
  }

  await nextTick()
  gambarPeta()
  if (tujuan.value) await muatEstimasi()
})

onBeforeUnmount(() => {
  peta?.remove()
  peta = null
})

watch(lembarTujuan, async (buka) => {
  if (buka) {
    peta?.remove()
    peta = null
    return
  }
  await nextTick()
  gambarPeta()
})

async function terimaTujuan(l: { alamat: string; lat: number; lng: number }) {
  jemputStore.setTujuan(l)
  lembarTujuan.value = false
  await nextTick()
  gambarPeta()
  await muatEstimasi()
}

watch(kelas, () => {
  if (!hasil.value) return
  dipilih.value =
    [...hasil.value.pilihan]
      .filter((p) => p.kelas === kelas.value)
      .sort((a, b) => a.tarif_setelah_promo - b.tarif_setelah_promo)[0] ?? null
})

function pilih(p: PilihanJemput) {
  dipilih.value = p
}

function lanjut() {
  if (!dipilih.value) return
  jemputStore.setPilihan(dipilih.value)
  router.push({ name: 'task-jemput-ringkasan' })
}

function sama(a: PilihanJemput | null, b: PilihanJemput) {
  return !!a && a.tipe === b.tipe && a.varian === b.varian
}
</script>

<template>
  <div class="relative min-h-dvh w-full bg-(--color-surface-container) isolate pb-4">
    <div ref="petaEl" class="absolute inset-x-0 top-0 h-[52vh] z-0" aria-label="Peta rute"></div>

    <button
      type="button"
      aria-label="Kembali"
      class="absolute top-4 left-4 z-20 w-11 h-11 rounded-full bg-(--color-surface-0) shadow-lg flex items-center justify-center active:scale-95 transition-transform"
      @click="kembali"
    >
      <Icon name="arrow-left" class="w-5 h-5" />
    </button>

    <!-- Titik jemput & tujuan, seperti kartu alamat di aplikasi transportasi -->
    <div class="relative z-20 max-w-[430px] mx-auto px-4 pt-4">
      <div class="rounded-2xl bg-(--color-surface-0) shadow-lg p-3.5 flex items-center gap-3">
        <div class="flex-1 min-w-0">
          <button
            type="button"
            class="w-full flex items-center gap-2.5 text-left"
            @click="router.push({ name: 'task-jemput-titik' })"
          >
            <span
              class="w-5 h-5 rounded-full bg-(--color-secondary-container) flex items-center justify-center shrink-0"
            >
              <Icon name="arrow-right" class="w-3 h-3 -rotate-90 text-(--color-on-secondary-container)" />
            </span>
            <span class="flex-1 truncate text-[13px] font-semibold">
              {{ jemput?.alamat ?? 'Titik jemput' }}
            </span>
          </button>

          <div class="my-2 border-t border-(--color-outline)/15"></div>

          <button
            type="button"
            class="w-full flex items-center gap-2.5 text-left"
            @click="lembarTujuan = true"
          >
            <span class="w-5 h-5 rounded-full bg-(--color-tertiary-container) flex items-center justify-center shrink-0">
              <span class="w-2 h-2 rounded-full bg-(--color-on-tertiary-container)"></span>
            </span>
            <span
              class="flex-1 truncate text-[13px]"
              :class="tujuan ? 'font-semibold' : 'text-(--color-on-surface-variant)'"
            >
              {{ tujuan?.alamat ?? 'Mau pergi ke mana?' }}
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- Lembar pilihan kendaraan -->
    <section
      class="relative z-20 mt-[34vh] bg-(--color-surface-0) rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.16)] min-h-[46vh]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-4 pb-40">
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>

        <!-- Tab kelas -->
        <div class="flex items-center gap-6 border-b border-(--color-outline)/15 mb-4">
          <button
            v-for="k in (['motor', 'mobil'] as const)"
            :key="k"
            type="button"
            class="pb-2.5 text-[14px] font-extrabold border-b-2 transition-colors"
            :class="
              kelas === k
                ? 'border-(--color-azure) text-(--color-azure)'
                : 'border-transparent text-(--color-on-surface-variant)'
            "
            :aria-pressed="kelas === k"
            @click="kelas = k"
          >
            {{ k === 'motor' ? 'Motor' : 'Mobil' }}
          </button>
        </div>

        <!-- Belum ada tujuan -->
        <div v-if="!tujuan" class="py-10 text-center">
          <Icon name="pin" class="w-9 h-9 mx-auto mb-3 text-(--color-on-surface-variant)" />
          <p class="text-[13.5px] font-bold mb-1">Tentukan tujuanmu dulu</p>
          <p class="text-[12px] leading-snug text-(--color-on-surface-variant) px-6">
            Tarifnya ikut jarak dan lama perjalanan, jadi harganya baru bisa dihitung setelah
            tujuannya jelas.
          </p>
          <button
            type="button"
            class="mt-4 px-5 h-11 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold active:scale-95 transition-transform"
            @click="lembarTujuan = true"
          >
            Pilih tujuan
          </button>
        </div>

        <div v-else-if="memuat" class="py-12 flex justify-center">
          <PemuatBerputar />
        </div>

        <p v-else-if="galat" role="alert" class="py-8 text-[13px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>

        <template v-else-if="hasil">
          <!--
            Jam sibuk disebut sebelum daftar harganya, bukan disembunyikan di
            rincian. Tarif yang naik diam-diam terbaca sebagai tagihan yang
            salah, dan itu merusak kepercayaan lebih mahal daripada nilai
            kenaikannya sendiri.
          -->
          <div
            v-if="hasil.sibuk"
            class="mb-3 flex gap-2 rounded-xl bg-(--color-tertiary-container)/50 p-3.5"
          >
            <Icon name="alert" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-on-tertiary-container)" />
            <p class="text-[11.5px] leading-snug">
              Tarif sedang naik ×{{ hasil.sibuk_pengali.toFixed(2).replace('.', ',') }}.
              {{ hasil.sibuk_alasan }}
            </p>
          </div>

          <p class="mb-3 text-[11.5px] text-(--color-on-surface-variant)">
            Perkiraan jarak {{ hasil.km.toFixed(1).replace('.', ',') }} km
          </p>

          <div class="flex flex-col gap-2.5">
            <div
              v-for="k in kartu"
              :key="k.tipe"
              class="rounded-2xl border-2 overflow-hidden transition-colors"
              :class="
                k.varian.some((v) => sama(dipilih, v))
                  ? 'border-(--color-azure) bg-(--color-secondary-container)/30'
                  : 'border-(--color-outline)/20'
              "
            >
              <div class="px-4 pt-3.5 pb-2 flex items-start gap-3">
                <span
                  class="w-10 h-10 rounded-xl bg-(--color-primary-container) flex items-center justify-center shrink-0"
                >
                  <Icon
                    :name="k.varian[0].kelas === 'motor' ? 'motorcycle' : 'car'"
                    class="w-5 h-5 text-(--color-on-primary-container)"
                  />
                </span>
                <div class="flex-1 min-w-0">
                  <p class="text-[14px] font-display font-extrabold leading-tight">
                    {{ k.varian[0].label }}
                  </p>
                  <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
                    {{ k.varian[0].keterangan }} · {{ k.varian[0].menit }} menit perjalanan
                  </p>
                </div>
              </div>

              <button
                v-for="v in k.varian"
                :key="v.varian"
                type="button"
                class="w-full px-4 py-3 flex items-center gap-3 text-left border-t border-(--color-outline)/12 transition-colors"
                :class="sama(dipilih, v) ? 'bg-(--color-secondary-container)/60' : ''"
                :aria-pressed="sama(dipilih, v)"
                @click="pilih(v)"
              >
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <span
                      class="px-2 py-0.5 rounded-md bg-(--color-azure) text-white text-[9.5px] font-extrabold tracking-wide"
                    >
                      {{ v.label_varian }}
                    </span>
                    <span
                      class="px-2 py-0.5 rounded-full bg-(--color-surface-container) text-[10.5px] font-bold"
                    >
                      {{ rentangMenit(v.jemput_menit) }}
                    </span>
                  </div>
                  <p class="mt-1 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
                    {{ v.catatan }}
                  </p>
                </div>

                <div class="text-right shrink-0">
                  <p class="text-[15px] font-extrabold">{{ rupiah(v.tarif_setelah_promo) }}</p>
                  <p
                    v-if="v.tarif_setelah_promo < v.tarif"
                    class="text-[11.5px] line-through text-(--color-on-surface-variant)"
                  >
                    {{ rupiah(v.tarif) }}
                  </p>
                </div>

                <span
                  class="w-5 h-5 rounded-full border-2 shrink-0 flex items-center justify-center"
                  :class="
                    sama(dipilih, v) ? 'border-(--color-azure)' : 'border-(--color-outline)/50'
                  "
                >
                  <span
                    v-if="sama(dipilih, v)"
                    class="w-2.5 h-2.5 rounded-full bg-(--color-azure)"
                  ></span>
                </span>
              </button>
            </div>
          </div>

          <p class="mt-4 text-[11px] leading-snug text-(--color-on-surface-variant)">
            Tarif ini perkiraan berdasarkan jarak dan waktu tempuh, belum termasuk tol dan parkir
            kalau ada.
          </p>
        </template>
      </div>
    </section>

    <footer
      v-if="dipilih"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.10)]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between gap-3 mb-2.5">
          <div class="min-w-0">
            <p class="text-[12px] text-(--color-on-surface-variant) truncate">
              {{ dipilih.label }} · {{ dipilih.label_varian }}
            </p>
            <p
              v-if="dipilih.promo_terbaik"
              class="text-[11.5px] font-bold text-(--color-on-secondary-container)"
            >
              Hemat {{ rupiah(dipilih.promo_terbaik.potongan) }} · {{ dipilih.promo_terbaik.nama }}
            </p>
          </div>
          <span class="text-[17px] font-extrabold shrink-0">
            {{ rupiah(dipilih.tarif_setelah_promo) }}
          </span>
        </div>

        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
          @click="lanjut"
        >
          Lanjut
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </div>
    </footer>

    <SheetPilihLokasi
      :tampil="lembarTujuan"
      :alamat="tujuan?.alamat ?? ''"
      :lat="tujuan?.lat ?? jemput?.lat ?? -6.2088"
      :lng="tujuan?.lng ?? jemput?.lng ?? 106.8456"
      judul-peta="Set tujuan"
      @tutup="lembarTujuan = false"
      @pilih="terimaTujuan"
    />
  </div>
</template>
