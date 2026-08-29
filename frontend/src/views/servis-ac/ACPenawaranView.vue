<script setup lang="ts">
/**
 * Setujui Penawaran pemasangan AC.
 *
 * Menekan tombol di halaman ini mengikat: lingkup pekerjaan, harga, dan
 * pengecualiannya menjadi kesepakatan. Karena itu persetujuan tidak pernah
 * terjadi hanya karena halamannya terbuka — ada kotak centang yang harus
 * ditandai dan nama yang harus diisi, dan server menolak permintaan yang tidak
 * membawa keduanya.
 *
 * Bagian "Tidak termasuk" ditampilkan sama menonjolnya dengan "Termasuk".
 * Yang tidak disebut akan dianggap termasuk oleh pelanggan, dan selisih itulah
 * yang berubah jadi perselisihan di lokasi.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import TandaTanganPad from '@/components/TandaTanganPad.vue'
import { ambilPenawaran, setujuiPenawaran, type PenawaranLengkap } from '@/api/penawaranAC'
import { pesanError } from '@/api/belanja'
import { useAuthStore } from '@/stores/auth'
import { rupiah } from '@/lib/rupiah'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const authStore = useAuthStore()

const nomor = String(route.params.nomor ?? '')

const data = ref<PenawaranLengkap | null>(null)
const memuat = ref(true)
const galat = ref<string | null>(null)

const penawaran = computed(() => data.value?.penawaran ?? null)
const sudahDijawab = computed(() => penawaran.value?.keputusan != null)

/* ────────── Persetujuan ────────── */
const setuju = ref(false)
const namaPenyetuju = ref('')
const jabatan = ref('')
const tandaTangan = ref('')
const jadwalDipilih = ref('')
const ditandai = ref(false)
const memproses = ref(false)

const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

const berlakuTeks = computed(() => {
  const t = penawaran.value?.berlaku_sampai
  if (!t) return null
  const d = new Date(`${t}T00:00:00`)
  return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`
})

onMounted(async () => {
  namaPenyetuju.value = authStore.user?.name ?? ''

  try {
    data.value = await ambilPenawaran(nomor)
    // Slot pertama tidak dipilihkan otomatis: jadwal adalah komitmen, dan
    // yang terpilih tanpa disentuh bukan pilihan siapa-siapa.
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
})

const bisaSetuju = computed(
  () =>
    setuju.value &&
    namaPenyetuju.value.trim().length > 0 &&
    !sudahDijawab.value &&
    !penawaran.value?.kedaluwarsa,
)

async function setujuiSekarang() {
  if (memproses.value) return

  if (!bisaSetuju.value) {
    ditandai.value = true
    galat.value = !setuju.value
      ? 'Centang dulu pernyataan persetujuannya.'
      : 'Nama penyetuju belum diisi.'
    return
  }

  memproses.value = true
  galat.value = null

  try {
    const hasil = await setujuiPenawaran(nomor, {
      setuju: true,
      nama_penyetuju: namaPenyetuju.value.trim(),
      jabatan: jabatan.value.trim() || undefined,
      jadwal_id: jadwalDipilih.value || undefined,
      tanda_tangan: tandaTangan.value || undefined,
    })

    router.replace({
      name: 'servis-ac-penawaran-disetujui',
      params: { nomor },
      query: {
        pekerjaan: hasil.nomor_pekerjaan,
        total: String(hasil.total),
        deposit: String(hasil.deposit ?? 0),
      },
    })
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Setujui Penawaran</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <p v-if="memuat" class="py-10 text-center text-[13px] text-(--color-on-surface-variant)">
        Memuat penawaran&hellip;
      </p>

      <section
        v-else-if="!penawaran"
        class="bg-(--color-surface-0) rounded-2xl p-6 text-center"
      >
        <Icon name="alert" class="w-8 h-8 mx-auto mb-3 text-(--color-error)" />
        <p class="text-[13px] leading-snug text-(--color-on-surface-variant)">
          {{ galat ?? 'Penawaran untuk permintaan ini belum terbit.' }}
        </p>
      </section>

      <template v-else>
        <!-- Kepala penawaran -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <div class="flex items-start justify-between gap-3 mb-3">
            <div class="min-w-0">
              <p class="text-[10.5px] uppercase tracking-wider text-(--color-on-surface-variant)">
                No. Penawaran
              </p>
              <p class="text-[17px] font-display font-extrabold">{{ penawaran.nomor }}</p>
            </div>

            <span
              class="shrink-0 px-3 py-1 rounded-full text-[11px] font-extrabold"
              :class="
                penawaran.keputusan === 'disetujui'
                  ? 'bg-(--color-secondary-container) text-(--color-on-secondary-container)'
                  : penawaran.kedaluwarsa
                    ? 'bg-(--color-error)/12 text-(--color-error)'
                    : 'bg-(--color-tertiary-container)/40 text-(--color-on-tertiary-container)'
              "
            >
              {{
                penawaran.keputusan === 'disetujui'
                  ? 'Sudah disetujui'
                  : penawaran.keputusan === 'revisi'
                    ? 'Menunggu penawaran baru'
                    : penawaran.kedaluwarsa
                      ? 'Lewat masa berlaku'
                      : 'Menunggu persetujuan'
              }}
            </span>
          </div>

          <p
            v-if="berlakuTeks"
            class="flex items-center gap-1.5 text-[12.5px] text-(--color-on-surface-variant)"
          >
            <Icon name="calendar" class="w-4 h-4 shrink-0" />
            Berlaku sampai
            <strong class="text-(--color-on-surface)">{{ berlakuTeks }}</strong>
          </p>
        </section>

        <!-- Ringkasan layanan -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <h2 class="text-[14px] font-display font-extrabold mb-3.5">Ringkasan Layanan</h2>

          <div class="flex flex-col gap-3">
            <div class="flex items-start gap-3">
              <Icon name="pin" class="w-4.5 h-4.5 shrink-0 mt-0.5 text-(--color-azure)" />
              <div class="min-w-0">
                <p class="text-[11px] text-(--color-on-surface-variant)">Lokasi</p>
                <p class="text-[13px] font-semibold leading-snug">{{ data?.lokasi_alamat }}</p>
              </div>
            </div>
            <div class="flex items-start gap-3">
              <Icon name="wrench" class="w-4.5 h-4.5 shrink-0 mt-0.5 text-(--color-azure)" />
              <div class="min-w-0">
                <p class="text-[11px] text-(--color-on-surface-variant)">Layanan</p>
                <p class="text-[13px] font-semibold">{{ penawaran.layanan }}</p>
              </div>
            </div>
            <div v-if="penawaran.durasi" class="flex items-start gap-3">
              <Icon name="clock" class="w-4.5 h-4.5 shrink-0 mt-0.5 text-(--color-azure)" />
              <div class="min-w-0">
                <p class="text-[11px] text-(--color-on-surface-variant)">Estimasi pengerjaan</p>
                <p class="text-[13px] font-semibold">{{ penawaran.durasi }}</p>
              </div>
            </div>
          </div>
        </section>

        <!-- Termasuk -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <h2
            class="text-[14px] font-display font-extrabold mb-3 flex items-center gap-2 text-(--color-on-secondary-container)"
          >
            <Icon name="check-circle" class="w-5 h-5" />
            Termasuk dalam pekerjaan
          </h2>
          <ul class="flex flex-col gap-2">
            <li
              v-for="t in penawaran.termasuk"
              :key="t"
              class="flex items-start gap-2 text-[12.5px] leading-snug"
            >
              <Icon
                name="check"
                class="w-4 h-4 shrink-0 mt-0.5 text-(--color-on-secondary-container)"
              />
              {{ t }}
            </li>
          </ul>
        </section>

        <!--
          Pengecualian diberi bobot yang sama dengan lingkup kerjanya, bukan
          catatan kaki. Batas pekerjaan yang tidak terbaca adalah sumber
          perselisihan biaya yang paling sering.
        -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5 border border-(--color-error)/25">
          <h2
            class="text-[14px] font-display font-extrabold mb-3 flex items-center gap-2 text-(--color-error)"
          >
            <Icon name="alert" class="w-5 h-5" />
            Tidak termasuk
          </h2>
          <ul class="flex flex-col gap-2">
            <li
              v-for="t in penawaran.tidak_termasuk"
              :key="t"
              class="flex items-start gap-2 text-[12.5px] leading-snug"
            >
              <Icon name="x" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-error)" />
              {{ t }}
            </li>
          </ul>
        </section>

        <!-- Rincian biaya -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <h2 class="text-[14px] font-display font-extrabold mb-3.5">Rincian Biaya</h2>

          <div class="flex flex-col gap-2.5">
            <div
              v-for="b in penawaran.baris"
              :key="b.nama"
              class="flex justify-between gap-3 text-[12.5px]"
            >
              <span class="text-(--color-on-surface-variant)">{{ b.nama }}</span>
              <span class="font-semibold whitespace-nowrap">{{ rupiah(b.nilai) }}</span>
            </div>

            <div
              v-if="penawaran.potongan"
              class="flex justify-between gap-3 text-[12.5px] text-(--color-error)"
            >
              <span>{{ penawaran.nama_potongan ?? 'Potongan' }}</span>
              <span class="font-semibold whitespace-nowrap">
                &minus;{{ rupiah(penawaran.potongan) }}
              </span>
            </div>
          </div>

          <div
            class="mt-4 pt-4 border-t border-(--color-outline)/20 flex justify-between items-center gap-3"
          >
            <span class="text-[13.5px] font-extrabold">Total</span>
            <span class="text-[19px] font-extrabold text-(--color-azure)">
              {{ rupiah(penawaran.total) }}
            </span>
          </div>

          <p
            v-if="penawaran.catatan"
            class="mt-3 rounded-xl bg-(--color-surface-container) p-3.5 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)"
          >
            {{ penawaran.catatan }}
          </p>
        </section>

        <!-- Jadwal -->
        <section v-if="penawaran.jadwal?.length" class="bg-(--color-surface-0) rounded-2xl p-5">
          <h2 class="text-[14px] font-display font-extrabold mb-1">Pilih Jadwal Pengerjaan</h2>
          <p class="text-[11.5px] text-(--color-on-surface-variant) mb-3.5 leading-snug">
            Boleh dikosongkan — tim kami akan menghubungi Anda untuk menentukan jadwal setelah
            penawaran disetujui.
          </p>

          <div class="flex flex-col gap-2.5">
            <button
              v-for="j in penawaran.jadwal"
              :key="j.id"
              type="button"
              class="w-full flex items-center gap-3 rounded-2xl border-2 px-4 py-3 text-left transition-colors"
              :class="
                jadwalDipilih === j.id
                  ? 'border-(--color-azure) bg-(--color-azure)/8'
                  : 'border-(--color-outline)/30'
              "
              :aria-pressed="jadwalDipilih === j.id"
              @click="jadwalDipilih = jadwalDipilih === j.id ? '' : j.id"
            >
              <span class="flex-1 min-w-0">
                <span class="block text-[13.5px] font-bold">{{ j.label }}</span>
                <span class="block text-[11.5px] text-(--color-on-surface-variant)">
                  {{ j.jam }}
                </span>
              </span>
              <span
                v-if="jadwalDipilih === j.id"
                class="w-5 h-5 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0"
              >
                <Icon name="check" class="w-3 h-3 text-white" />
              </span>
              <span v-else class="w-5 h-5 rounded-full border-2 border-(--color-outline) shrink-0"></span>
            </button>
          </div>
        </section>

        <!-- Persetujuan -->
        <section v-if="!sudahDijawab && !penawaran.kedaluwarsa" class="bg-(--color-surface-0) rounded-2xl p-5">
          <h2 class="text-[14px] font-display font-extrabold mb-3.5">Persetujuan</h2>

          <label class="flex items-start gap-3 mb-5 cursor-pointer">
            <input
              v-model="setuju"
              type="checkbox"
              class="mt-0.5 w-5 h-5 shrink-0 rounded accent-(--color-azure)"
            />
            <span class="text-[12.5px] leading-snug">
              Saya telah membaca dan menyetujui lingkup pekerjaan, rincian biaya, dan bagian yang
              tidak termasuk dalam penawaran ini.
            </span>
          </label>

          <p
            v-if="ditandai && !setuju"
            class="-mt-3 mb-4 text-[11.5px] font-semibold text-(--color-error)"
          >
            Centang dulu pernyataan di atas sebelum menyetujui.
          </p>

          <label class="block mb-3">
            <span class="text-[12.5px] font-bold">Nama penyetuju</span>
            <input
              v-model="namaPenyetuju"
              type="text"
              class="mt-1.5 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 outline-none focus:border-(--color-azure)"
              :class="
                ditandai && !namaPenyetuju.trim()
                  ? 'border-(--color-error)'
                  : 'border-transparent'
              "
            />
          </label>

          <label class="block mb-4">
            <span class="text-[12.5px] font-bold">Jabatan (opsional)</span>
            <input
              v-model="jabatan"
              type="text"
              placeholder="Misal: Pemilik rumah, Manajer GA"
              class="mt-1.5 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
            />
          </label>

          <p class="text-[12.5px] font-bold mb-1.5">Tanda tangan (opsional)</p>
          <TandaTanganPad v-model="tandaTangan" :tinggi="150" />
        </section>

        <!-- Sudah dijawab -->
        <section
          v-else
          class="bg-(--color-surface-0) rounded-2xl p-5 flex gap-2.5"
        >
          <Icon name="info" class="w-5 h-5 shrink-0 text-(--color-azure) mt-0.5" />
          <p class="text-[12.5px] leading-snug text-(--color-on-surface-variant)">
            {{
              penawaran.keputusan === 'disetujui'
                ? 'Penawaran ini sudah disetujui. Perubahan setelahnya diajukan sebagai pekerjaan tambahan.'
                : penawaran.keputusan === 'revisi'
                  ? 'Permintaan revisi Anda sudah tercatat. Tim kami akan mengirim penawaran baru.'
                  : 'Penawaran ini sudah lewat masa berlaku. Minta penawaran baru ke tim kami.'
            }}
          </p>
        </section>
      </template>
    </main>

    <footer
      v-if="penawaran && !sudahDijawab && !penawaran.kedaluwarsa"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <p
          v-if="penawaran.deposit"
          class="mb-2.5 text-[11.5px] leading-snug text-(--color-on-surface-variant)"
        >
          Setelah disetujui, Anda diarahkan membayar deposit
          <strong class="text-(--color-on-surface)">{{ rupiah(penawaran.deposit) }}</strong
          >.
        </p>

        <div class="flex items-center gap-2.5">
          <button
            type="button"
            class="shrink-0 px-4 h-12 rounded-full border-[1.5px] border-(--color-outline)/50 text-[13px] font-extrabold active:scale-95 transition-transform"
            @click="router.push({ name: 'servis-ac-penawaran-revisi', params: { nomor } })"
          >
            Ajukan Revisi
          </button>

          <button
            type="button"
            class="flex-1 h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
            :disabled="memproses"
            @click="setujuiSekarang"
          >
            <Icon v-if="!memproses" name="shield" class="w-4 h-4" />
            {{ memproses ? 'Memproses…' : 'Setujui Penawaran' }}
          </button>
        </div>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>
  </div>
</template>
