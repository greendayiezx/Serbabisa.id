<script setup lang="ts">
/**
 * BisaKirim — detail pengiriman.
 *
 * Di sinilah paketnya dijelaskan dan ongkirnya muncul. Tiga hal ditanyakan
 * SEBELUM harga, bukan sesudah, karena ketiganya bisa membatalkan kiriman:
 * ukuran paket (menentukan kendaraan yang sanggup), isi kiriman (ada yang tidak
 * bisa diantar sama sekali), dan nilai barang (menentukan plafon proteksi).
 *
 * Semua ongkir datang dari server. Jarak, tarif, dan potongan voucher dihitung
 * di sana, dan server menghitungnya sekali lagi sebelum menagih.
 */
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PemuatBerputar from '@/components/ui/PemuatBerputar.vue'
import KirimDetailSkeleton from '@/components/skeleton/KirimDetailSkeleton.vue'
import SpandukHemat from '@/components/kirim/SpandukHemat.vue'
import PetaRuteKirim from '@/components/kirim/PetaRuteKirim.vue'
import { useSkeleton } from '@/composables/useSkeleton'
import { useKirimStore } from '@/stores/kirim'
import { estimasiKirim, type HasilEstimasiKirim } from '@/api/kirim'
import { pesanError } from '@/api/belanja'
import { UKURAN, rupiah, type PilihanKirim } from '@/lib/kirim'
import bisaKirimInstantImg from '@/assets/BisaKirim_Instant.svg'
import bisaKirimInstantMobilImg from '@/assets/BisaKirim_InstantMobil.svg'

const router = useRouter()
const kembali = useKembali()
const kirimStore = useKirimStore()

const { tampil: skelTampil, tandaiSiap } = useSkeleton()

const ambil = computed(() => kirimStore.ambil)
const antar = computed(() => kirimStore.antar)

const memuat = ref(false)
const galat = ref<string | null>(null)
const hasil = ref<HasilEstimasiKirim | null>(null)
const dipilih = ref<PilihanKirim | null>(null)
const lembarPaket = ref(false)

/* ────────── Peta rute ────────── */
const petaRef = ref<InstanceType<typeof PetaRuteKirim> | null>(null)

async function muatEstimasi() {
  if (!ambil.value || !antar.value) return

  memuat.value = true
  galat.value = null
  try {
    hasil.value = await estimasiKirim({
      ambil_lat: ambil.value.lat,
      ambil_lng: ambil.value.lng,
      antar_lat: antar.value.lat,
      antar_lng: antar.value.lng,
      ukuran: kirimStore.ukuran,
      nilai_barang: kirimStore.nilaiBarang || undefined,
    })

    // Kendaraan termurah yang SANGGUP membawa paketnya. Yang tidak sanggup
    // tidak pernah jadi pilihan awal, meski lebih murah.
    kirimStore.setRute(hasil.value.geometri, hasil.value.lewat_jalan)

    const sanggup = hasil.value.pilihan.filter((p) => p.sanggup)
    pilih(sanggup.sort((a, b) => a.total_setelah_promo - b.total_setelah_promo)[0] ?? null)

    await nextTick()
    petaRef.value?.gambar()
  } catch (e) {
    hasil.value = null
    pilih(null)
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
}

function pilih(p: PilihanKirim | null) {
  dipilih.value = p
  kirimStore.setPilihan(p)
}

onMounted(async () => {
  if (!kirimStore.ambil || !kirimStore.antar) {
    router.replace({ name: 'task-kirim' })
    return
  }
  tandaiSiap()
  await muatEstimasi()
})

// Peta dibangun setelah skeleton pergi: selama skeleton tampil, div petanya
// belum ada di DOM dan gambarPeta() berhenti di penjagaan null-nya.
watch(skelTampil, async (masihSkeleton) => {
  if (masihSkeleton) return
  await nextTick()
  petaRef.value?.gambar()
})

async function gantiUkuran(id: string) {
  kirimStore.setUkuran(id)
  lembarPaket.value = false
  await muatEstimasi()
}

const ukuranTerpilih = computed(() => UKURAN.find((u) => u.id === kirimStore.ukuran))

const bisaLanjut = computed(
  () => !!dipilih.value && dipilih.value.sanggup && !memuat.value,
)

function lanjut() {
  if (!bisaLanjut.value) return
  router.push({ name: 'task-kirim-konfirmasi' })
}
</script>

<template>
  <KirimDetailSkeleton v-if="skelTampil" />

  <div v-else class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-[21rem]">
    <header class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/10">
      <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Detail pengiriman</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- Titik ambil & antar -->
      <section class="bg-(--color-surface-0) rounded-2xl p-4">
        <div class="flex items-start gap-3">
          <!--
            Tiap baris membuka halamannya sendiri. Kontak pengambilan dan
            kontak pengantaran adalah dua pekerjaan yang berbeda orang dan
            berbeda waktu; menumpuknya di satu formulir panjang membuat orang
            mengisi kolom milik sisi yang salah.
          -->
          <div class="flex-1 min-w-0 flex flex-col gap-3">
            <button
              type="button"
              class="w-full flex items-start gap-3 text-left"
              @click="router.push({ name: 'task-kirim-ambil' })"
            >
              <Icon name="pin" class="w-[22px] h-[22px] mt-0.5 text-(--color-azure) shrink-0" />
              <span class="flex-1 min-w-0">
                <span class="block text-[13px] font-bold truncate">
                  {{ ambil?.nama || 'Titik ambil' }}
                </span>
                <span class="block text-[11.5px] text-(--color-on-surface-variant) truncate">
                  {{ ambil?.alamat }}
                </span>
              </span>
              <Icon name="chevron-right" class="w-4 h-4 mt-1 shrink-0 text-(--color-on-surface-variant)" />
            </button>

            <button
              type="button"
              class="w-full flex items-start gap-3 text-left"
              @click="router.push({ name: 'task-kirim-antar' })"
            >
              <Icon name="pin" class="w-[22px] h-[22px] mt-0.5 text-orange-500 shrink-0" />
              <span class="flex-1 min-w-0">
                <span class="block text-[13px] font-bold truncate">
                  {{ antar?.nama || 'Tujuan' }}
                </span>
                <span class="block text-[11.5px] text-(--color-on-surface-variant) truncate">
                  {{ antar?.alamat }}
                </span>
              </span>
              <Icon name="chevron-right" class="w-4 h-4 mt-1 shrink-0 text-(--color-on-surface-variant)" />
            </button>
          </div>

          <button
            type="button"
            aria-label="Tukar titik ambil dan tujuan"
            class="w-9 h-9 rounded-full border border-(--color-outline)/30 flex items-center justify-center shrink-0 self-center active:scale-90 transition-transform"
            @click="(kirimStore.tukar(), muatEstimasi())"
          >
            <Icon name="arrow-right" class="w-4 h-4 -rotate-90" />
          </button>
        </div>
      </section>

      <!-- Ukuran & isi paket -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5 flex flex-col gap-4">
        <button
          type="button"
          class="w-full flex items-center gap-3 text-left"
          @click="lembarPaket = true"
        >
          <Icon name="package" class="w-5 h-5 text-(--color-azure) shrink-0" />
          <div class="flex-1 min-w-0">
            <p class="text-[13.5px] font-bold">Ukuran & berat paket</p>
            <p class="text-[11.5px] text-(--color-on-surface-variant)">
              {{ ukuranTerpilih?.label }} · sampai {{ ukuranTerpilih?.berat }} kg
            </p>
          </div>
          <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface-variant)" />
        </button>

        <div class="pt-4 border-t border-(--color-outline)/15">
          <p class="text-[12.5px] font-bold mb-2">Isi paket</p>
          <input
            v-model="kirimStore.isi"
            type="text"
            maxlength="120"
            placeholder="Mis. dokumen kontrak, kunci rumah"
            class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
          />
        </div>

        <!-- Kode terima paket -->
        <div class="pt-4 border-t border-(--color-outline)/15">
          <button
            type="button"
            class="w-full flex items-center gap-3 text-left"
            :aria-pressed="kirimStore.pakaiKodeTerima"
            @click="kirimStore.pakaiKodeTerima = !kirimStore.pakaiKodeTerima"
          >
            <div class="flex-1">
              <p class="text-[13.5px] font-bold">Kode terima paket</p>
              <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant)">
                Kurir hanya menyerahkan ke orang yang tahu kodenya. Gratis.
              </p>
            </div>
            <span
              class="w-11 h-6 rounded-full p-0.5 shrink-0 transition-colors"
              :class="kirimStore.pakaiKodeTerima ? 'bg-(--color-azure)' : 'bg-(--color-outline)/30'"
            >
              <span
                class="block w-5 h-5 rounded-full bg-white transition-transform"
                :class="kirimStore.pakaiKodeTerima ? 'translate-x-5' : ''"
              ></span>
            </span>
          </button>
        </div>
      </section>

      <!-- Jarak & rute -->
      <section class="bg-(--color-surface-0) rounded-2xl p-4 isolate">
        <p class="text-[12px] text-center text-(--color-on-surface-variant) mb-3">
          <template v-if="hasil">
            {{ hasil.lewat_jalan ? 'Jarak rute' : 'Perkiraan jarak' }}
            {{ hasil.km.toFixed(1).replace('.', ',') }} km
          </template>
          <template v-else>Menghitung jarak…</template>
        </p>
        <PetaRuteKirim
          ref="petaRef"
          :ambil="ambil"
          :antar="antar"
          :geometri="hasil?.geometri ?? null"
          :lewat-jalan="hasil?.lewat_jalan ?? false"
        />
      </section>

      <p v-if="galat" role="alert" class="text-[12.5px] font-semibold text-(--color-error) px-1">
        {{ galat }}
      </p>
    </main>

    <!-- Lembar ukuran paket -->
    <div v-if="lembarPaket" class="fixed inset-0 z-50 flex items-end" @click.self="lembarPaket = false">
      <div class="absolute inset-0 bg-black/40"></div>
      <div class="relative w-full max-w-[430px] mx-auto bg-(--color-surface-0) rounded-t-3xl p-5">
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>
        <h2 class="text-[16px] font-display font-extrabold mb-3">Ukuran & berat paket</h2>

        <button
          v-for="u in UKURAN"
          :key="u.id"
          type="button"
          class="w-full flex items-center gap-3 rounded-xl px-3.5 py-3.5 text-left transition-colors"
          :class="kirimStore.ukuran === u.id ? 'bg-(--color-secondary-container)/50' : ''"
          @click="gantiUkuran(u.id)"
        >
          <div class="flex-1">
            <p class="text-[13.5px] font-bold">{{ u.label }} · sampai {{ u.berat }} kg</p>
            <p class="text-[11.5px] text-(--color-on-surface-variant)">{{ u.contoh }}</p>
          </div>
          <Icon v-if="kirimStore.ukuran === u.id" name="check" class="w-4 h-4 text-(--color-azure)" />
        </button>
      </div>
    </div>

    <!-- Pilihan kendaraan + tombol lanjut -->
    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.16)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-3"></div>

        <div v-if="memuat" class="py-6 flex justify-center"><PemuatBerputar /></div>

        <template v-else-if="hasil">
          <div class="flex flex-col gap-2 mb-3">
            <button
              v-for="p in hasil.pilihan"
              :key="p.kendaraan"
              type="button"
              class="w-full flex items-center gap-3 rounded-2xl border-2 px-3.5 py-3 text-left transition-colors"
              :class="[
                dipilih?.kendaraan === p.kendaraan
                  ? 'border-(--color-azure) bg-(--color-secondary-container)/30'
                  : 'border-(--color-outline)/20',
                p.sanggup ? '' : 'opacity-55',
              ]"
              :disabled="!p.sanggup"
              :aria-pressed="dipilih?.kendaraan === p.kendaraan"
              @click="pilih(p)"
            >
              <div class="w-13 h-13 shrink-0 flex items-center justify-center">
                <img
                  v-if="p.kendaraan === 'motor'"
                  :src="bisaKirimInstantImg"
                  alt="BisaKirim Instant Motor"
                  class="w-13 h-13 object-contain"
                />
                <img
                  v-else-if="p.kendaraan === 'mobil'"
                  :src="bisaKirimInstantMobilImg"
                  alt="BisaKirim Instant Mobil"
                  class="w-13 h-13 object-contain"
                />
                <span
                  v-else
                  class="w-13 h-13 rounded-2xl bg-(--color-primary-container) flex items-center justify-center shrink-0"
                >
                  <Icon
                    name="car"
                    class="w-6 h-6 text-(--color-on-primary-container)"
                  />
                </span>
              </div>

              <div class="flex-1 min-w-0">
                <p class="text-[13.5px] font-extrabold leading-tight">{{ p.label }}</p>
                <p class="text-[11px] text-(--color-on-surface-variant) mt-0.5">
                  Maks. {{ p.maks_berat }} kg · {{ p.estimasi }}
                </p>
                <!-- Alasan tidak sanggup ditulis, bukan cuma diredupkan. -->
                <p v-if="!p.sanggup" class="text-[11px] font-semibold text-(--color-error) mt-0.5">
                  {{ p.alasan }}
                </p>
              </div>

              <div class="text-right shrink-0">
                <p class="text-[14.5px] font-extrabold">{{ rupiah(p.total_setelah_promo) }}</p>
                <p
                  v-if="p.total_setelah_promo < p.total"
                  class="text-[11px] line-through text-(--color-on-surface-variant)"
                >
                  {{ rupiah(p.total) }}
                </p>
              </div>
            </button>
          </div>

          <SpandukHemat
            v-if="dipilih?.promo_terbaik"
            class="mb-3"
            :jumlah="rupiah(dipilih.promo_terbaik.potongan)"
            :nama="dipilih.promo_terbaik.nama"
          />
        </template>

        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="!bisaLanjut"
          @click="lanjut"
        >
          Tambah detail pengiriman
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
      </div>
    </footer>
  </div>
</template>
