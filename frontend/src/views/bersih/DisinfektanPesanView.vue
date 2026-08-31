<script setup lang="ts">
/**
 * Disinfektan — langkah 1: apa yang didisinfeksi dan kapan.
 *
 * Lokasi dan data pemesan pindah ke halaman konfirmasi, mengikuti pola yang
 * sama dengan alur Servis AC.
 *
 * Satu pertanyaan di sini bukan soal harga: bila ada darah, cairan tubuh, atau
 * limbah berisiko, pesanannya TIDAK dilanjutkan. Layar mengatakannya lebih dulu
 * supaya orang tidak mengisi formulir panjang lalu ditolak di ujung — dan
 * server menolaknya juga, karena aturan seperti ini tidak boleh hanya hidup di
 * layar.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PilihanField from '@/components/PilihanField.vue'
import DatePickerField from '@/components/DatePickerField.vue'
import TimePickerField from '@/components/TimePickerField.vue'
import { useDisinfektanStore } from '@/stores/disinfektan'
import { rupiah } from '@/lib/rupiah'
import {
  KONDISI,
  LUAS,
  LUAS_PENAWARAN,
  PERHATIAN,
  PERHATIAN_DITOLAK,
  PROPERTI,
  RUANGAN_TERMASUK,
  TOILET_TERMASUK,
  hitungDisinfektan,
} from '@/lib/bersih/disinfektan'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const disinfektanStore = useDisinfektanStore()

const properti = ref('rumah')
const luas = ref('50-100')
const ruangan = ref(RUANGAN_TERMASUK)
const toilet = ref(TOILET_TERMASUK)
const kondisi = ref('normal')
const perhatian = ref<string[]>([])
const adaRisikoBiologis = ref(false)
const catatan = ref('')
const tanggal = ref('')
const waktu = ref('')

const galat = ref<string | null>(null)

function togglePerhatian(id: string) {
  const i = perhatian.value.indexOf(id)
  if (i >= 0) perhatian.value.splice(i, 1)
  else perhatian.value.push(id)
}

const butuhPenawaran = computed(() => luas.value === LUAS_PENAWARAN)
const rincian = computed(() =>
  hitungDisinfektan(properti.value, luas.value, ruangan.value, toilet.value, kondisi.value),
)

onMounted(() => {
  const d = disinfektanStore.draft
  if (d) {
    properti.value = d.properti
    luas.value = d.luas
    ruangan.value = d.ruangan
    toilet.value = d.toilet
    kondisi.value = d.kondisi
    perhatian.value = [...d.perhatian]
    catatan.value = d.catatan
    tanggal.value = d.tanggal
    waktu.value = d.waktu
    return
  }

  // Jenis lokasi sudah dipilih di halaman sebelumnya; tidak perlu ditanya lagi.
  const dari = String(route.query.properti ?? '')
  if (PROPERTI.some((p) => p.id === dari)) properti.value = dari
})

function lanjut() {
  if (adaRisikoBiologis.value) {
    galat.value =
      'Untuk area dengan darah, cairan tubuh, atau limbah berisiko, layanan ini belum bisa menanganinya.'
    return
  }
  if (butuhPenawaran.value) {
    galat.value = 'Area di atas 300 m² dihitung lewat penawaran. Gunakan tombol Minta Penawaran.'
    return
  }
  if (!tanggal.value || !waktu.value) {
    galat.value = 'Jadwal pengerjaan belum dipilih.'
    return
  }

  galat.value = null
  disinfektanStore.set({
    properti: properti.value,
    luas: luas.value,
    ruangan: ruangan.value,
    toilet: toilet.value,
    kondisi: kondisi.value,
    perhatian: [...perhatian.value],
    catatan: catatan.value,
    tanggal: tanggal.value,
    waktu: waktu.value,
  })

  router.push({ name: 'task-bersih-disinfektan-konfirmasi' })
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-40">
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Booking Disinfektan</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- 1. Detail lokasi -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          1. Detail Area
        </h2>

        <PilihanField
          v-model="properti"
          label="Jenis properti"
          judul-panel="Jenis properti"
          ikon="home"
          :opsi="PROPERTI"
        />

        <p class="mt-4 mb-2 text-[12.5px] font-bold">Estimasi luas area</p>
        <div class="grid grid-cols-2 gap-2">
          <button
            v-for="l in LUAS"
            :key="l.id"
            type="button"
            class="py-3 rounded-xl border text-center transition-colors"
            :class="
              luas === l.id
                ? 'bg-(--color-azure) border-(--color-azure) text-white'
                : 'border-(--color-outline)/50'
            "
            :aria-pressed="luas === l.id"
            @click="luas = l.id"
          >
            <span class="block text-[13px] font-bold">{{ l.nama }}</span>
            <span class="block text-[10.5px] opacity-80">{{ l.catatan }}</span>
          </button>
        </div>

        <!--
          Dikatakan saat luasnya dipilih, bukan setelah tombol ditekan: kalau
          areanya memang sebesar itu, orang tidak perlu mengisi sisa formulir
          untuk tahu jalurnya berbeda.
        -->
        <div
          v-if="butuhPenawaran"
          class="mt-3 flex gap-2 rounded-xl bg-(--color-primary-container)/40 p-3.5"
        >
          <Icon name="alert" class="w-4 h-4 shrink-0 text-(--color-azure) mt-0.5" />
          <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant)">
            Area di atas 300 m² dihitung lewat penawaran, bukan harga pasang — selisih antar
            gedung terlalu besar untuk satu angka.
            <button
              type="button"
              class="font-bold text-(--color-azure) underline"
              @click="router.push({ name: 'task-bersih-disinfektan-penawaran' })"
            >
              Ajukan penawaran
            </button>
          </p>
        </div>

        <div class="mt-5 flex flex-col gap-3">
          <div
            v-for="c in [
              { label: 'Jumlah ruangan', model: 'ruangan' as const, min: 1, maks: 60 },
              { label: 'Jumlah toilet', model: 'toilet' as const, min: 0, maks: 30 },
            ]"
            :key="c.model"
            class="flex items-center justify-between gap-3 rounded-xl bg-(--color-surface-container) px-4 py-3"
          >
            <span class="text-[13.5px] font-bold">{{ c.label }}</span>
            <div class="flex items-center gap-3">
              <button
                type="button"
                :aria-label="`Kurangi ${c.label}`"
                class="w-9 h-9 rounded-full bg-(--color-surface-0) flex items-center justify-center active:scale-90 transition-transform disabled:opacity-40"
                :disabled="(c.model === 'ruangan' ? ruangan : toilet) <= c.min"
                @click="c.model === 'ruangan' ? ruangan-- : toilet--"
              >
                <Icon name="minus" class="w-4 h-4" />
              </button>
              <span class="w-6 text-center text-[15px] font-extrabold">
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
        </div>

        <p class="mt-2 text-[11px] text-(--color-on-surface-variant)">
          Sudah termasuk {{ RUANGAN_TERMASUK }} ruangan dan {{ TOILET_TERMASUK }} toilet.
        </p>
      </section>

      <!-- 2. Kondisi area -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          2. Kondisi Area
        </h2>
        <PilihanField
          v-model="kondisi"
          judul-panel="Kondisi area saat ini"
          ikon="info"
          :opsi="KONDISI"
        />
      </section>

      <!-- 3. Perhatian keamanan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-1">
          3. Perhatian Khusus
        </h2>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-4 leading-snug">
          Centang yang berlaku. Ini menentukan produk yang boleh dipakai dan cara
          mengaplikasikannya, bukan menaikkan harga.
        </p>

        <div class="flex flex-col gap-2.5">
          <button
            v-for="p in PERHATIAN"
            :key="p.id"
            type="button"
            class="w-full flex items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition-colors"
            :class="
              perhatian.includes(p.id)
                ? 'border-(--color-azure) bg-(--color-azure)/8'
                : 'border-(--color-outline)/30'
            "
            :aria-pressed="perhatian.includes(p.id)"
            @click="togglePerhatian(p.id)"
          >
            <span class="flex-1 text-[13px] font-semibold">{{ p.nama }}</span>
            <span
              v-if="perhatian.includes(p.id)"
              class="w-5 h-5 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0"
            >
              <Icon name="check" class="w-3 h-3 text-white" />
            </span>
            <span v-else class="w-5 h-5 rounded border-2 border-(--color-outline) shrink-0"></span>
          </button>
        </div>

        <!--
          Dipisah dari daftar di atas dan diberi warna berbeda: jawabannya bukan
          harga yang lebih tinggi, melainkan penyedia yang berbeda. Menaruhnya
          sebagai pilihan setara membuatnya terbaca sebagai sekadar surcharge.
        -->
        <div class="mt-4 pt-4 border-t border-(--color-outline)/15">
          <button
            type="button"
            class="w-full flex items-center gap-3 rounded-xl border-2 px-4 py-3 text-left transition-colors"
            :class="
              adaRisikoBiologis
                ? 'border-(--color-error) bg-(--color-error)/8'
                : 'border-(--color-outline)/30'
            "
            :aria-pressed="adaRisikoBiologis"
            @click="adaRisikoBiologis = !adaRisikoBiologis"
          >
            <span class="flex-1 text-[13px] font-semibold">{{ PERHATIAN_DITOLAK.nama }}</span>
            <span
              v-if="adaRisikoBiologis"
              class="w-5 h-5 rounded-full bg-(--color-error) flex items-center justify-center shrink-0"
            >
              <Icon name="check" class="w-3 h-3 text-white" />
            </span>
            <span v-else class="w-5 h-5 rounded border-2 border-(--color-outline) shrink-0"></span>
          </button>

          <div
            v-if="adaRisikoBiologis"
            class="mt-3 rounded-xl bg-(--color-error)/8 border border-(--color-error)/30 p-3.5 flex gap-2"
          >
            <Icon name="alert" class="w-4 h-4 shrink-0 text-(--color-error) mt-0.5" />
            <p class="text-[11.5px] leading-relaxed text-(--color-on-surface-variant)">
              Layanan ini <strong class="text-(--color-on-surface)">belum bisa menangani</strong>
              area dengan darah, cairan tubuh, atau limbah berisiko. Penanganannya butuh prosedur
              dan perlengkapan khusus — hubungi penyedia jasa dekontaminasi agar aman untuk Anda
              maupun petugas.
            </p>
          </div>
        </div>
      </section>

      <!-- 4. Jadwal -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[12px] font-extrabold uppercase tracking-wider text-(--color-azure) mb-4">
          4. Jadwal Pengerjaan
        </h2>
        <div class="grid grid-cols-2 gap-3">
          <DatePickerField v-model="tanggal" wajib />
          <TimePickerField v-model="waktu" wajib />
        </div>
      </section>

      <!-- Catatan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-2">Catatan untuk petugas</h3>
        <textarea
          v-model="catatan"
          rows="3"
          placeholder="Misal: fokus di ruang tamu dan dapur"
          class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
        />
      </section>
    </main>

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between gap-3 mb-3">
          <span class="text-[12.5px] text-(--color-on-surface-variant)">Estimasi</span>
          <span class="text-[17px] font-extrabold">
            {{ butuhPenawaran ? 'Lewat penawaran' : rupiah(rincian.total) }}
          </span>
        </div>

        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="adaRisikoBiologis || butuhPenawaran"
          @click="lanjut"
        >
          Lanjut
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>
  </div>
</template>
