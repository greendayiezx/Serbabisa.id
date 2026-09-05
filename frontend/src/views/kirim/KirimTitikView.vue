<script setup lang="ts">
/**
 * BisaKirim — detail satu sisi kiriman: pengambilan atau pengantaran.
 *
 * Satu komponen untuk dua halaman, dibedakan `meta.sisi` di rutenya. Isinya
 * memang pekerjaan yang sama — titik di peta, siapa yang ditemui kurir, dan
 * patokannya — hanya berbeda ujung. Menyalinnya jadi dua berkas berarti tiap
 * perbaikan harus diingat dua kali, dan yang terlupakan tidak memberi tanda.
 *
 * Kontaknya disimpan lewat setKontak(), bukan setAmbil/setAntar: keduanya
 * membuang pilihan kendaraan dan voucher karena rutenya berubah. Mengetik nama
 * penerima bukan perubahan rute.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import KontakPenerima from '@/components/KontakPenerima.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import KirimKontakSkeleton from '@/components/skeleton/KirimKontakSkeleton.vue'
import { useSkeleton } from '@/composables/useSkeleton'
import { useKirimStore } from '@/stores/kirim'
import { useLocationStore } from '@/stores/location'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const kirimStore = useKirimStore()
const locationStore = useLocationStore()

const { tampil: skelTampil, tandaiSiap } = useSkeleton()

const sisi = computed<'ambil' | 'antar'>(() =>
  route.meta.sisi === 'antar' ? 'antar' : 'ambil',
)

const titik = computed(() => (sisi.value === 'ambil' ? kirimStore.ambil : kirimStore.antar))

const salinan = computed(() =>
  sisi.value === 'ambil'
    ? {
        judul: 'Detail Pengambilan Paket',
        labelTitik: 'Paket diambil di',
        judulKontak: 'Detail Pengirim',
        placeholderNama: 'Nama yang menyerahkan paket…',
        pesanKosong: 'Nama dan nomor pengirim dibutuhkan supaya kurir bisa menghubungi saat menjemput.',
        placeholderPatokan: 'Mis. pagar hitam, titip satpam',
        judulPeta: 'Set titik ambil',
        warnaPin: 'text-(--color-azure)',
      }
    : {
        judul: 'Detail Pengiriman Paket',
        labelTitik: 'Paket diantar ke',
        judulKontak: 'Detail Penerima',
        placeholderNama: 'Nama penerima paket…',
        pesanKosong: 'Nama dan nomor penerima dibutuhkan supaya kurir bisa menghubungi saat mengantar.',
        placeholderPatokan: 'Mis. lantai 3, sebelah minimarket',
        judulPeta: 'Set tujuan kiriman',
        warnaPin: 'text-orange-500',
      },
)

const nama = ref('')
const telepon = ref('')
const catatan = ref('')
const lembarLokasi = ref(false)
const ditandai = ref(false)

onMounted(() => {
  if (!kirimStore.ambil || !kirimStore.antar) {
    router.replace({ name: 'task-kirim' })
    return
  }

  nama.value = titik.value?.nama ?? ''
  telepon.value = titik.value?.telepon ?? ''
  catatan.value = titik.value?.catatan ?? ''

  tandaiSiap()
})

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  // Titiknya pindah, jadi ongkir dan voucher lama memang harus dihitung ulang —
  // itulah kenapa yang ini lewat setAmbil/setAntar, bukan setKontak.
  if (sisi.value === 'ambil') kirimStore.setAmbil({ ...(kirimStore.ambil ?? {}), ...l })
  else kirimStore.setAntar({ ...(kirimStore.antar ?? {}), ...l })

  locationStore.addSearchHistory(l)
  lembarLokasi.value = false
}

function simpan() {
  if (!nama.value.trim() || !telepon.value.trim()) {
    ditandai.value = true
    return
  }

  kirimStore.setKontak(sisi.value, {
    nama: nama.value.trim(),
    telepon: telepon.value.trim(),
    catatan: catatan.value.trim() || null,
  })

  // Naik satu tingkat, bukan mundur di riwayat: halaman ini bisa dibuka lewat
  // tautan langsung, dan di situ mundur berarti keluar dari aplikasi.
  kembali()
}
</script>

<template>
  <KirimKontakSkeleton v-if="skelTampil" />

  <div v-else class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-32">
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">{{ salinan.judul }}</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- Titik di peta, bisa diganti dari sini -->
      <button
        type="button"
        class="bg-(--color-surface-0) rounded-2xl p-4 flex items-start gap-3 text-left active:scale-[0.99] transition-transform"
        @click="lembarLokasi = true"
      >
        <Icon name="pin" class="w-[22px] h-[22px] mt-0.5 shrink-0" :class="salinan.warnaPin" />
        <span class="flex-1 min-w-0">
          <span class="block text-[11px] text-(--color-on-surface-variant)">
            {{ salinan.labelTitik }}
          </span>
          <span class="block text-[13.5px] font-bold leading-snug">{{ titik?.alamat }}</span>
        </span>
        <span class="text-[12.5px] font-bold text-(--color-azure) shrink-0 self-center">Ubah</span>
      </button>

      <KontakPenerima
        v-model:nama="nama"
        v-model:telepon="telepon"
        :judul="salinan.judulKontak"
        :placeholder-nama="salinan.placeholderNama"
        :pesan-kosong="salinan.pesanKosong"
        :ditandai="ditandai"
      >
        <div class="mt-4">
          <label
            class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5"
          >
            Patokan untuk kurir
          </label>
          <input
            v-model="catatan"
            type="text"
            maxlength="255"
            :placeholder="salinan.placeholderPatokan"
            class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none placeholder:text-(--color-on-surface-variant)"
          />
        </div>
      </KontakPenerima>

      <SheetPilihLokasi
        :tampil="lembarLokasi"
        :alamat="titik?.alamat ?? ''"
        :lat="titik?.lat ?? -6.2088"
        :lng="titik?.lng ?? 106.8456"
        :judul-peta="salinan.judulPeta"
        @tutup="lembarLokasi = false"
        @pilih="terimaLokasi"
      />
    </main>

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
          @click="simpan"
        >
          Simpan
          <Icon name="check" class="w-4 h-4" />
        </button>
      </div>
    </footer>
  </div>
</template>
