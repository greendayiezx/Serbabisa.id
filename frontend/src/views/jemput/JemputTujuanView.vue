<script setup lang="ts">
/**
 * BisaJemput — cari tujuan, satu halaman penuh.
 *
 * Bukan lembar bawah, dan itu bukan soal selera: mengetik tujuan adalah
 * pekerjaan yang butuh papan ketik, daftar hasil, riwayat, dan kadang pindah ke
 * peta. Lembar bawah menyisakan sepertiga layar untuk semua itu, lalu tertutup
 * papan ketik. Halaman penuh memberi hasil pencarian ruang yang sebenarnya
 * dibutuhkan.
 *
 * Titik jemput ikut ditampilkan di atas kolom tujuan — sudah dikonfirmasi di
 * layar sebelumnya, dan ditaruh di sini supaya orang bisa memastikan ia mencari
 * rute dari tempat yang benar, tanpa harus kembali dulu.
 */
import { nextTick, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import PemuatBerputar from '@/components/ui/PemuatBerputar.vue'
import PemilihLokasi from '@/components/PemilihLokasi.vue'
import { cariLokasi, type HasilLokasi } from '@/lib/geocode'
import { useJemputStore } from '@/stores/jemput'
import { useLocationStore } from '@/stores/location'

const router = useRouter()
const jemputStore = useJemputStore()
const locationStore = useLocationStore()

const kueri = ref(jemputStore.tujuan?.alamat ?? '')
const hasil = ref<HasilLokasi[]>([])
const mencari = ref(false)
const galat = ref<string | null>(null)
const peta = ref(false)

const kolom = ref<HTMLInputElement | null>(null)
const riwayat = ref(locationStore.loadSearchHistory())

let jeda: ReturnType<typeof setTimeout> | null = null

onMounted(async () => {
  if (!jemputStore.jemput || !jemputStore.jemputDikonfirmasi) {
    router.replace({ name: 'task-jemput-titik' })
    return
  }

  await nextTick()
  kolom.value?.focus()
  // Teks lama disorot, bukan dihapus: kalau tujuannya cuma perlu diubah
  // sedikit, mengetik ulang seluruhnya jadi kerja sia-sia.
  kolom.value?.select()
})

watch(kueri, (nilai) => {
  if (jeda) clearTimeout(jeda)

  const teks = nilai.trim()
  if (teks.length < 3) {
    hasil.value = []
    mencari.value = false
    return
  }

  // Memanggil penyedia geocoding tiap ketukan akan diblokir dan menghabiskan
  // kuota; jeda 400 ms sudah terasa langsung tapi hanya mengirim sekali.
  mencari.value = true
  jeda = setTimeout(async () => {
    galat.value = null
    try {
      hasil.value = await cariLokasi(teks, jemputStore.jemput ?? undefined)
    } catch {
      hasil.value = []
      galat.value = 'Pencarian sedang tidak bisa dipakai. Coba pilih lewat peta.'
    } finally {
      mencari.value = false
    }
  }, 400)
})

function pakai(l: { alamat: string; lat: number; lng: number }) {
  jemputStore.setTujuan(l)
  locationStore.addSearchHistory(l)
  router.replace({ name: 'task-jemput-pesan' })
}

function dariPeta(l: { alamat: string; lat: number; lng: number }) {
  peta.value = false
  pakai(l)
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-0) text-(--color-on-surface)">
    <header class="sticky top-0 z-30 bg-(--color-surface-0)">
      <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center gap-3">
        <button
          type="button"
          aria-label="Tutup"
          class="w-10 h-10 -ml-2 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="router.back()"
        >
          <Icon name="x" class="w-5 h-5" />
        </button>
        <h1 class="text-[17px] font-display font-extrabold">Mau ke mana hari ini?</h1>
      </div>
    </header>

    <div class="max-w-[430px] mx-auto px-4">
      <!-- Titik jemput & tujuan, satu kartu seperti di layar pemesanan -->
      <div class="rounded-2xl bg-(--color-surface-container) p-3.5">
        <button
          type="button"
          class="w-full flex items-center gap-3 text-left"
          @click="router.replace({ name: 'task-jemput-titik' })"
        >
          <span
            class="w-6 h-6 rounded-full bg-(--color-secondary-container) flex items-center justify-center shrink-0"
          >
            <Icon
              name="arrow-right"
              class="w-3.5 h-3.5 -rotate-90 text-(--color-on-secondary-container)"
            />
          </span>
          <span class="flex-1 truncate text-[13px] text-(--color-on-surface-variant)">
            {{ jemputStore.jemput?.alamat }}
          </span>
        </button>

        <div class="my-2 ml-3 border-l-2 border-dotted border-(--color-outline)/40 h-3"></div>

        <div class="flex items-center gap-3">
          <span
            class="w-6 h-6 rounded-full bg-(--color-tertiary-container) flex items-center justify-center shrink-0"
          >
            <span class="w-2.5 h-2.5 rounded-full bg-(--color-on-tertiary-container)"></span>
          </span>
          <input
            ref="kolom"
            v-model="kueri"
            type="text"
            enterkeyhint="search"
            placeholder="Mau pergi ke mana?"
            class="flex-1 min-w-0 bg-transparent text-[14px] font-semibold outline-none"
          />
          <button
            v-if="kueri"
            type="button"
            aria-label="Kosongkan"
            class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 active:scale-90 transition-transform"
            @click="((kueri = ''), kolom?.focus())"
          >
            <Icon name="x" class="w-3.5 h-3.5 text-(--color-on-surface-variant)" />
          </button>
        </div>
      </div>

      <div class="mt-3">
        <button
          type="button"
          class="px-4 h-10 rounded-full border-[1.5px] border-(--color-outline)/50 text-[12.5px] font-extrabold flex items-center gap-2 active:scale-95 transition-transform"
          @click="peta = true"
        >
          <Icon name="crosshair" class="w-4 h-4 text-(--color-azure)" />
          Pilih lewat peta
        </button>
      </div>
    </div>

    <div class="max-w-[430px] mx-auto mt-4">
      <div v-if="mencari" class="py-10 flex justify-center"><PemuatBerputar /></div>

      <p v-else-if="galat" role="alert" class="px-4 py-6 text-[13px] font-semibold text-(--color-error)">
        {{ galat }}
      </p>

      <!-- Hasil pencarian -->
      <ul v-else-if="hasil.length" class="pb-8">
        <li v-for="(h, i) in hasil" :key="`${h.lat}-${h.lng}-${i}`">
          <button
            type="button"
            class="w-full px-4 py-3.5 flex items-start gap-3 text-left border-b border-(--color-outline)/12 active:bg-(--color-surface-container) transition-colors"
            @click="pakai({ alamat: h.label, lat: h.lat, lng: h.lng })"
          >
            <Icon name="pin" class="w-4.5 h-4.5 mt-0.5 shrink-0 text-(--color-on-surface-variant)" />
            <span class="flex-1 min-w-0">
              <span class="block text-[13.5px] font-bold leading-snug">{{ h.nama }}</span>
              <span class="block text-[11.5px] leading-snug text-(--color-on-surface-variant) mt-0.5">
                {{ h.alamat }}
              </span>
            </span>
          </button>
        </li>
      </ul>

      <p
        v-else-if="kueri.trim().length >= 3"
        class="px-4 py-10 text-center text-[13px] text-(--color-on-surface-variant)"
      >
        Tidak ada tempat yang cocok. Coba kata lain, atau pilih lewat peta.
      </p>

      <!-- Belum mengetik: riwayat lokasi -->
      <template v-else-if="riwayat.length">
        <p class="px-4 pb-2 text-[11.5px] font-bold uppercase tracking-wider text-(--color-on-surface-variant)">
          Terakhir dikunjungi
        </p>
        <ul class="pb-8">
          <li v-for="r in riwayat" :key="r.id">
            <button
              type="button"
              class="w-full px-4 py-3.5 flex items-start gap-3 text-left border-b border-(--color-outline)/12 active:bg-(--color-surface-container) transition-colors"
              @click="pakai({ alamat: r.address, lat: r.lat, lng: r.lng })"
            >
              <Icon name="clock" class="w-4.5 h-4.5 mt-0.5 shrink-0 text-(--color-on-surface-variant)" />
              <span class="flex-1 min-w-0">
                <span class="block text-[13.5px] font-bold leading-snug">{{ r.label }}</span>
                <span class="block text-[11.5px] leading-snug text-(--color-on-surface-variant) mt-0.5">
                  {{ r.address }}
                </span>
              </span>
            </button>
          </li>
        </ul>
      </template>

      <p v-else class="px-4 py-10 text-center text-[13px] text-(--color-on-surface-variant)">
        Ketik nama tempat atau jalan tujuanmu.
      </p>
    </div>

    <PemilihLokasi
      :tampil="peta"
      :alamat="jemputStore.tujuan?.alamat ?? ''"
      :lat="jemputStore.tujuan?.lat ?? jemputStore.jemput?.lat ?? -6.2088"
      :lng="jemputStore.tujuan?.lng ?? jemputStore.jemput?.lng ?? 106.8456"
      judul="Set tujuan"
      label-cari="Cari lokasi tujuan"
      @tutup="peta = false"
      @pilih="dariPeta"
    />
  </div>
</template>
