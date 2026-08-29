<script setup lang="ts">
/**
 * Ajukan revisi penawaran.
 *
 * Halaman ini TIDAK mengubah satu angka pun. Pelanggan mengajukan; harga dan
 * jadwal baru dikirim setelah tim meninjau. Membiarkan angkanya diubah dari
 * sini berarti tidak ada lagi yang namanya penawaran.
 *
 * Permintaan dibuat PER ITEM, bukan satu kolom bebas untuk semuanya: "pipa jadi
 * 2 meter dan jadwal digeser" dalam satu paragraf harus diurai lagi oleh orang
 * di sisi lain, dan yang terurai salah akan jadi penawaran yang salah.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PilihanField from '@/components/PilihanField.vue'
import {
  ajukanRevisiPenawaran,
  ambilPenawaran,
  type PenawaranLengkap,
} from '@/api/penawaranAC'
import { pesanError } from '@/api/belanja'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()

const nomor = String(route.params.nomor ?? '')

const data = ref<PenawaranLengkap | null>(null)
const memuat = ref(true)
const penawaran = computed(() => data.value?.penawaran ?? null)

const KATEGORI = [
  { id: 'jenis-layanan', nama: 'Jenis layanan' },
  { id: 'jumlah-unit', nama: 'Jumlah unit AC' },
  { id: 'material', nama: 'Material' },
  { id: 'panjang-pipa', nama: 'Panjang pipa' },
  { id: 'jadwal', nama: 'Jadwal pengerjaan' },
  { id: 'paket-harga', nama: 'Paket harga' },
  { id: 'scope', nama: 'Lingkup pekerjaan' },
  { id: 'pembayaran', nama: 'Ketentuan pembayaran' },
  { id: 'lainnya', nama: 'Lainnya' },
]

const ALASAN = [
  { id: 'anggaran', nama: 'Menyesuaikan anggaran' },
  { id: 'jadwal', nama: 'Mengubah jadwal' },
  { id: 'kurangi-scope', nama: 'Mengurangi lingkup' },
  { id: 'tambah-pekerjaan', nama: 'Menambah pekerjaan' },
  { id: 'bandingkan', nama: 'Membandingkan paket' },
  { id: 'lokasi-berubah', nama: 'Data lokasi berubah' },
  { id: 'lainnya', nama: 'Lainnya' },
]

const PAKET = [
  { id: '', nama: 'Tidak perlu paket lain' },
  { id: 'essential', nama: 'Essential — hanya jasa pemasangan' },
  { id: 'standard', nama: 'Standard — jasa + material standar' },
  { id: 'premium', nama: 'Premium — material premium + garansi tambahan' },
  { id: 'khusus', nama: 'Saya ingin penawaran khusus' },
]

const kategori = ref<string[]>([])
const alasan = ref('anggaran')
const paket = ref('')
const catatan = ref('')
const perItem = ref<Record<string, string>>({})

const ditandai = ref(false)
const memproses = ref(false)
const galat = ref<string | null>(null)
const terkirim = ref(false)

function toggleKategori(id: string) {
  const i = kategori.value.indexOf(id)
  if (i >= 0) kategori.value.splice(i, 1)
  else kategori.value.push(id)
}

onMounted(async () => {
  try {
    data.value = await ambilPenawaran(nomor)
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
})

async function kirim() {
  if (memproses.value) return

  if (!kategori.value.length || !catatan.value.trim()) {
    ditandai.value = true
    galat.value = !kategori.value.length
      ? 'Pilih dulu bagian mana yang ingin diubah.'
      : 'Tuliskan perubahan yang Anda inginkan.'
    return
  }

  memproses.value = true
  galat.value = null

  try {
    await ajukanRevisiPenawaran(nomor, {
      kategori: [...kategori.value],
      alasan: alasan.value,
      paket_alternatif: paket.value || undefined,
      catatan: catatan.value.trim(),
      per_item: Object.entries(perItem.value)
        .filter(([, v]) => v.trim())
        .map(([item, permintaan]) => ({ item, permintaan: permintaan.trim() })),
    })
    terkirim.value = true
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Ajukan Revisi</h1>
      </div>
    </header>

    <!-- ============ Terkirim ============ -->
    <main v-if="terkirim" class="max-w-[430px] mx-auto px-4 pt-6 flex flex-col gap-4">
      <section class="bg-(--color-surface-0) rounded-2xl p-6 text-center">
        <span
          class="w-16 h-16 rounded-full bg-(--color-secondary-container) flex items-center justify-center mx-auto mb-4"
        >
          <Icon name="check-circle" class="w-8 h-8 text-(--color-on-secondary-container)" />
        </span>
        <h2 class="text-[18px] font-display font-extrabold mb-1.5">Permintaan revisi terkirim</h2>
        <p class="text-[12.5px] leading-snug text-(--color-on-surface-variant)">
          Tim BisaBersih meninjau permintaan Anda, lalu mengirim penawaran baru dengan harga dan
          jadwal yang sudah disesuaikan. Penawaran lama tidak berlaku sampai penggantinya terbit.
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

    <!-- ============ Formulir ============ -->
    <main v-else class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <p v-if="memuat" class="py-10 text-center text-[13px] text-(--color-on-surface-variant)">
        Memuat penawaran&hellip;
      </p>

      <template v-else>
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <p class="text-[10.5px] uppercase tracking-wider text-(--color-on-surface-variant)">
            No. Penawaran
          </p>
          <p class="text-[16px] font-display font-extrabold mb-2">
            {{ penawaran?.nomor ?? nomor }}
          </p>
          <p class="text-[12.5px] leading-snug text-(--color-on-surface-variant)">
            Pilih bagian yang ingin diubah. Harga dan jadwal baru dikirim setelah tim kami meninjau
            permintaan Anda — tidak ada angka yang berubah dari halaman ini.
          </p>
        </section>

        <!-- Kategori -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <h2 class="text-[14px] font-display font-extrabold mb-3">Bagian yang ingin diubah</h2>

          <div class="flex flex-wrap gap-2">
            <button
              v-for="k in KATEGORI"
              :key="k.id"
              type="button"
              class="px-4 py-2 rounded-full border text-[12.5px] font-semibold transition-colors"
              :class="
                kategori.includes(k.id)
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)/50 text-(--color-on-surface-variant)'
              "
              :aria-pressed="kategori.includes(k.id)"
              @click="toggleKategori(k.id)"
            >
              {{ k.nama }}
            </button>
          </div>

          <p
            v-if="ditandai && !kategori.length"
            class="mt-2.5 text-[11.5px] font-semibold text-(--color-error)"
          >
            Pilih minimal satu bagian.
          </p>
        </section>

        <!--
          Item penawaran ditampilkan satu per satu dengan kolomnya sendiri.
          Meminta semua perubahan ditulis di satu kotak memaksa orang di sisi
          lain menguraikannya lagi — dan yang terurai salah jadi penawaran salah.
        -->
        <section v-if="penawaran?.baris?.length" class="bg-(--color-surface-0) rounded-2xl p-5">
          <h2 class="text-[14px] font-display font-extrabold mb-1">Permintaan per item</h2>
          <p class="text-[11.5px] text-(--color-on-surface-variant) mb-4 leading-snug">
            Isi hanya yang ingin diubah. Yang dikosongkan dianggap tidak berubah.
          </p>

          <div class="flex flex-col gap-4">
            <div v-for="b in penawaran.baris" :key="b.nama">
              <div class="flex items-center gap-2 mb-1.5">
                <span
                  v-if="b.kategori"
                  class="px-2 py-0.5 rounded bg-(--color-surface-container) text-[10px] font-bold uppercase tracking-wide text-(--color-on-surface-variant)"
                >
                  {{ b.kategori }}
                </span>
                <span class="text-[13px] font-bold">{{ b.nama }}</span>
              </div>
              <input
                v-model="perItem[b.nama]"
                type="text"
                placeholder="Misal: ubah jadi 5 meter"
                class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
              />
            </div>
          </div>
        </section>

        <!-- Alasan & paket -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5 flex flex-col gap-4">
          <PilihanField
            v-model="alasan"
            label="Alasan revisi"
            judul-panel="Alasan revisi"
            ikon="help"
            :opsi="ALASAN"
          />

          <PilihanField
            v-model="paket"
            label="Alternatif paket"
            judul-panel="Pilih alternatif paket"
            ikon="layers"
            :opsi="PAKET"
          />
        </section>

        <!-- Catatan -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <h2 class="text-[14px] font-display font-extrabold mb-2">
            Jelaskan perubahan yang Anda inginkan
          </h2>
          <textarea
            v-model="catatan"
            rows="4"
            placeholder="Misal: saya ingin mengurangi pipa menjadi 2 meter dan mengganti jadwal ke hari Selasa sore."
            class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 outline-none focus:border-(--color-azure) resize-none"
            :class="ditandai && !catatan.trim() ? 'border-(--color-error)' : 'border-transparent'"
          />
        </section>
      </template>
    </main>

    <footer
      v-if="!terkirim && !memuat"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="memproses"
          @click="kirim"
        >
          {{ memproses ? 'Mengirim…' : 'Kirim Permintaan Revisi' }}
          <Icon v-if="!memproses" name="send" class="w-4 h-4" />
        </button>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>
  </div>
</template>
