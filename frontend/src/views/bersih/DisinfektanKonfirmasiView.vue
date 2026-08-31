<script setup lang="ts">
/**
 * Disinfektan — langkah 2: ke mana petugas datang dan menemui siapa.
 *
 * Susunannya sama persis dengan konfirmasi Servis AC: lokasi berpeta di paling
 * atas, ringkasan pekerjaan, lalu data pemesan. Dua alur yang menanyakan hal
 * yang sama tidak boleh menanyakannya dengan dua cara berbeda.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import KartuLokasiPeta from '@/components/KartuLokasiPeta.vue'
import KontakPenerima from '@/components/KontakPenerima.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import { useLocationStore } from '@/stores/location'
import { useDisinfektanStore } from '@/stores/disinfektan'
import { pesanDisinfektan } from '@/api/disinfektan'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import {
  AREA,
  KONDISI,
  PERHATIAN,
  PROPERTI,
  golongan,
  hitungDisinfektan,
} from '@/lib/bersih/disinfektan'

const router = useRouter()
const kembali = useKembali()
const locationStore = useLocationStore()
const disinfektanStore = useDisinfektanStore()

const draft = computed(() => disinfektanStore.draft)

const namaProperti = computed(
  () => PROPERTI.find((p) => p.id === draft.value?.properti)?.nama ?? draft.value?.properti ?? '',
)
const namaKondisi = computed(
  () => KONDISI.find((k) => k.id === draft.value?.kondisi)?.nama ?? draft.value?.kondisi ?? '',
)
const namaPerhatian = computed(() =>
  (draft.value?.perhatian ?? [])
    .map((id) => PERHATIAN.find((p) => p.id === id)?.nama ?? id)
    .join(', '),
)
const areaTampil = computed(() => AREA[golongan(draft.value?.properti ?? 'rumah')])

const rincian = computed(() => {
  const d = draft.value
  if (!d) return { baris: [], total: 0 }
  return hitungDisinfektan(d.properti, d.luas, d.ruangan, d.toilet, d.kondisi)
})

const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

const jadwalTeks = computed(() => {
  const d = draft.value
  if (!d?.tanggal) return ''
  const t = new Date(`${d.tanggal}T00:00:00`)
  return `${t.getDate()} ${BULAN[t.getMonth()]} ${t.getFullYear()} · ${d.waktu}`
})

/* ────────── Lokasi ────────── */
const alamat = computed(() => locationStore.draft?.alamat ?? '')
const lat = computed(() => locationStore.draft?.lat ?? -6.2088)
const lng = computed(() => locationStore.draft?.lng ?? 106.8456)
const lembarLokasi = ref(false)

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  locationStore.setDraft(l)
  lembarLokasi.value = false
}

/* ────────── Kontak ────────── */
const namaPenerima = ref('')
const telepon = ref('')
const ditandai = ref(false)

onMounted(() => {
  if (!disinfektanStore.draft) {
    router.replace({ name: 'task-bersih-disinfektan-pesan' })
  }
})

/* ────────── Kirim ────────── */
const memproses = ref(false)
const galat = ref<string | null>(null)

async function kirim() {
  const d = draft.value
  if (!d || memproses.value) return

  if (!alamat.value) {
    galat.value = 'Lokasi pengerjaan belum diisi.'
    return
  }
  if (!namaPenerima.value.trim() || !telepon.value.trim()) {
    ditandai.value = true
    galat.value = 'Nama dan nomor telepon belum diisi.'
    return
  }

  memproses.value = true
  galat.value = null

  try {
    const hasil = await pesanDisinfektan({
      properti: d.properti,
      luas: d.luas,
      ruangan: d.ruangan,
      toilet: d.toilet,
      kondisi: d.kondisi,
      perhatian: [...d.perhatian],
      catatan: d.catatan || undefined,
      tanggal: d.tanggal,
      waktu: d.waktu,
      nama_penerima: namaPenerima.value.trim(),
      telepon_penerima: telepon.value.trim(),
      lokasi_alamat: alamat.value,
      lokasi_lat: lat.value,
      lokasi_lng: lng.value,
    })

    disinfektanStore.hapus()
    router.replace({ name: 'task-detail', params: { id: hasil.id } })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-32">
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Lokasi &amp; Data Pemesan</h1>
      </div>
    </header>

    <main v-if="draft" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <KartuLokasiPeta
        :alamat="alamat"
        :lat="lat"
        :lng="lng"
        :tersembunyi="lembarLokasi"
        label="Lokasi pengerjaan"
        @ubah="lembarLokasi = true"
      />

      <!-- Ringkasan dari langkah sebelumnya -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
          <h2 class="text-[14px] font-display font-extrabold">Disinfektan</h2>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
            @click="kembali"
          >
            Ubah
          </button>
        </div>

        <div class="flex flex-col gap-2 text-[13px]">
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Properti</span>
            <span class="font-bold text-right">{{ namaProperti }} · {{ draft.luas }} m²</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Ruangan</span>
            <span class="font-bold">{{ draft.ruangan }} ruangan · {{ draft.toilet }} toilet</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant) shrink-0">Kondisi</span>
            <span class="font-bold text-right leading-snug">{{ namaKondisi }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant) shrink-0">Jadwal</span>
            <span class="font-bold text-right leading-snug">{{ jadwalTeks }}</span>
          </div>
          <div v-if="namaPerhatian" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant) shrink-0">Perhatian</span>
            <span class="font-bold text-right leading-snug">{{ namaPerhatian }}</span>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-(--color-outline)/15">
          <p class="text-[11.5px] font-bold mb-1.5">Area yang ditangani</p>
          <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant)">
            {{ areaTampil.join(' · ') }}
          </p>
        </div>
      </section>

      <KontakPenerima
        v-model:nama="namaPenerima"
        v-model:telepon="telepon"
        judul="Data Pemesan"
        :ditandai="ditandai"
      />

      <!--
        Diulang di layar terakhir sebelum membayar, bukan hanya di halaman
        penjelasan: ini titik terakhir orang bisa membatalkan tanpa rugi.
      -->
      <p class="px-1 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)">
        Disinfeksi permukaan bukan sterilisasi dan tidak menjamin ruangan bebas virus. Produk dan
        waktu kontaknya mengikuti label masing-masing produk; petugas mencatatnya di laporan
        setelah pekerjaan selesai.
      </p>

      <SheetPilihLokasi
        :tampil="lembarLokasi"
        :alamat="alamat"
        :lat="lat"
        :lng="lng"
        judul-peta="Set lokasi pengerjaan"
        @tutup="lembarLokasi = false"
        @pilih="terimaLokasi"
      />
    </main>

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between gap-3 mb-3">
          <span class="text-[12.5px] text-(--color-on-surface-variant)">Total estimasi</span>
          <span class="text-[17px] font-extrabold">{{ rupiah(rincian.total) }}</span>
        </div>

        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="memproses"
          @click="kirim"
        >
          {{ memproses ? 'Memproses…' : 'Pesan Sekarang' }}
          <Icon v-if="!memproses" name="arrow-right" class="w-4 h-4" />
        </button>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>
  </div>
</template>
