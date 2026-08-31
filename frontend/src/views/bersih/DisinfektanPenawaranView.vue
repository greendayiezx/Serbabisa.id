<script setup lang="ts">
/**
 * Disinfektan — permintaan penawaran.
 *
 * Untuk area di atas 300 m² dan kantor besar. TIDAK menagih apa pun: selisih
 * antara satu gedung dan gedung lain terlalu besar untuk diwakili satu angka,
 * jadi harganya menyusul setelah ditinjau.
 */
import { computed, onMounted, ref } from 'vue'
import { useKembali } from '@/composables/useKembali'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import KartuLokasiPeta from '@/components/KartuLokasiPeta.vue'
import KontakPenerima from '@/components/KontakPenerima.vue'
import PilihanField from '@/components/PilihanField.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import { useLocationStore } from '@/stores/location'
import { ajukanPenawaranDisinfektan, type PermintaanDisinfektan } from '@/api/disinfektan'
import { pesanError } from '@/api/belanja'
import { KONDISI, LUAS, PROPERTI } from '@/lib/bersih/disinfektan'

const router = useRouter()
const kembali = useKembali()
const locationStore = useLocationStore()

const FREKUENSI = [
  { id: 'sekali', nama: 'Sekali saja' },
  { id: 'mingguan', nama: 'Rutin mingguan' },
  { id: 'dua-mingguan', nama: 'Rutin dua mingguan' },
  { id: 'bulanan', nama: 'Rutin bulanan' },
]

const properti = ref('kantor')
const luas = ref('>300')
const ruangan = ref(10)
const toilet = ref(4)
const kondisi = ref('banyak-orang')
const frekuensi = ref('sekali')
const catatan = ref('')

const alamat = computed(() => locationStore.draft?.alamat ?? '')
const lat = computed(() => locationStore.draft?.lat ?? -6.2088)
const lng = computed(() => locationStore.draft?.lng ?? 106.8456)
const lembarLokasi = ref(false)

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  locationStore.setDraft(l)
  lembarLokasi.value = false
}

const namaPenerima = ref('')
const telepon = ref('')
const ditandai = ref(false)

const memproses = ref(false)
const galat = ref<string | null>(null)
const hasil = ref<PermintaanDisinfektan | null>(null)

onMounted(() => {
  // Tidak ada yang perlu dipulihkan: permintaan penawaran selalu diisi dari
  // awal, dan isinya tidak dibawa antar-halaman.
})

async function ajukan() {
  if (memproses.value) return

  if (!alamat.value) {
    galat.value = 'Lokasi belum diisi.'
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
    hasil.value = await ajukanPenawaranDisinfektan({
      properti: properti.value,
      luas: luas.value,
      ruangan: ruangan.value,
      toilet: toilet.value,
      kondisi: kondisi.value,
      frekuensi: frekuensi.value,
      catatan: catatan.value || undefined,
      nama_penerima: namaPenerima.value.trim(),
      telepon_penerima: telepon.value.trim(),
      lokasi_alamat: alamat.value,
      lokasi_lat: lat.value,
      lokasi_lng: lng.value,
    })
    window.scrollTo({ top: 0, behavior: 'smooth' })
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">
          {{ hasil ? 'Permintaan Terkirim' : 'Minta Penawaran' }}
        </h1>
      </div>
    </header>

    <main v-if="hasil" class="max-w-[430px] mx-auto px-4 pt-6 flex flex-col gap-4">
      <section class="bg-(--color-surface-0) rounded-2xl p-6 text-center">
        <span
          class="w-16 h-16 rounded-full bg-(--color-secondary-container) flex items-center justify-center mx-auto mb-4"
        >
          <Icon name="check-circle" class="w-8 h-8 text-(--color-on-secondary-container)" />
        </span>
        <h2 class="text-[19px] font-display font-extrabold mb-1.5">Permintaan terkirim</h2>
        <p class="text-[13px] leading-snug text-(--color-on-surface-variant)">
          Nomor permintaan Anda
        </p>
        <p class="mt-1 text-[17px] font-extrabold tracking-wide">{{ hasil.nomor }}</p>
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <p class="text-[12.5px] leading-relaxed text-(--color-on-surface-variant)">
          Tim kami meninjau luas, jumlah ruangan, dan kondisi areanya, lalu mengirim penawaran
          dengan rincian pekerjaan dan biayanya. Anda menyetujui dulu sebelum pekerjaan dimulai.
        </p>
      </section>

      <button
        type="button"
        class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
        @click="router.push({ name: 'task-list' })"
      >
        Lihat di Tugas Saya
        <Icon name="arrow-right" class="w-4 h-4" />
      </button>
    </main>

    <main v-else class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <KartuLokasiPeta
        :alamat="alamat"
        :lat="lat"
        :lng="lng"
        :tersembunyi="lembarLokasi"
        label="Lokasi"
        @ubah="lembarLokasi = true"
      />

      <section class="bg-(--color-surface-0) rounded-2xl p-5 flex flex-col gap-4">
        <PilihanField
          v-model="properti"
          label="Jenis properti"
          judul-panel="Jenis properti"
          ikon="business"
          :opsi="PROPERTI"
        />
        <PilihanField
          v-model="luas"
          label="Luas area"
          judul-panel="Luas area"
          ikon="grid"
          :opsi="LUAS"
        />
        <PilihanField
          v-model="kondisi"
          label="Kondisi area"
          judul-panel="Kondisi area"
          ikon="info"
          :opsi="KONDISI"
        />
        <PilihanField
          v-model="frekuensi"
          label="Frekuensi"
          judul-panel="Frekuensi layanan"
          ikon="calendar"
          :opsi="FREKUENSI"
        />
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5 flex flex-col gap-3">
        <div
          v-for="c in [
            { label: 'Jumlah ruangan', model: 'ruangan' as const, min: 1, maks: 500 },
            { label: 'Jumlah toilet', model: 'toilet' as const, min: 0, maks: 200 },
          ]"
          :key="c.model"
          class="flex items-center justify-between gap-3"
        >
          <span class="text-[13.5px] font-bold">{{ c.label }}</span>
          <div class="flex items-center gap-3">
            <button
              type="button"
              :aria-label="`Kurangi ${c.label}`"
              class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform disabled:opacity-40"
              :disabled="(c.model === 'ruangan' ? ruangan : toilet) <= c.min"
              @click="c.model === 'ruangan' ? ruangan-- : toilet--"
            >
              <Icon name="minus" class="w-4 h-4" />
            </button>
            <span class="w-8 text-center text-[15px] font-extrabold">
              {{ c.model === 'ruangan' ? ruangan : toilet }}
            </span>
            <button
              type="button"
              :aria-label="`Tambah ${c.label}`"
              class="w-9 h-9 rounded-full bg-(--color-azure) text-white flex items-center justify-center active:scale-90 transition-transform disabled:opacity-40"
              :disabled="(c.model === 'ruangan' ? ruangan : toilet) >= c.maks"
              @click="c.model === 'ruangan' ? ruangan++ : toilet++"
            >
              <Icon name="plus" class="w-4 h-4" />
            </button>
          </div>
        </div>
      </section>

      <KontakPenerima
        v-model:nama="namaPenerima"
        v-model:telepon="telepon"
        judul="Kontak"
        :ditandai="ditandai"
      />

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-2">Catatan</h3>
        <textarea
          v-model="catatan"
          rows="3"
          placeholder="Misal: 3 lantai, ada ruang server yang tidak boleh disemprot"
          class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
        />
      </section>

      <SheetPilihLokasi
        :tampil="lembarLokasi"
        :alamat="alamat"
        :lat="lat"
        :lng="lng"
        @tutup="lembarLokasi = false"
        @pilih="terimaLokasi"
      />
    </main>

    <footer
      v-if="!hasil"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="memproses"
          @click="ajukan"
        >
          {{ memproses ? 'Mengirim…' : 'Ajukan Permintaan Penawaran' }}
          <Icon v-if="!memproses" name="arrow-right" class="w-4 h-4" />
        </button>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>
  </div>
</template>
