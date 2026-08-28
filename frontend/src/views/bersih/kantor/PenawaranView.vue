<script setup lang="ts">
/**
 * Dokumen penawaran BisaBersih Kantor.
 *
 * Tiga paket ditampilkan berdampingan supaya pelanggan membandingkan CAKUPAN,
 * bukan sekadar mencari angka terkecil. Empat aksi tersedia: setujui, ajukan
 * perubahan, chat, dan unduh PDF.
 *
 * Semua angka datang dari server. Halaman ini tidak menghitung apa pun sendiri
 * — penawaran adalah dokumen komersial, dan angka di layar harus sama persis
 * dengan yang tercetak di PDF.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { rupiah } from '@/lib/rupiah'
import { pesanError } from '@/api/belanja'
import {
  ajukanRevisi,
  ambilPenawaran,
  setujuiPenawaran,
  unduhPdfPenawaran,
  type Penawaran,
} from '@/api/penawaran'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const nomor = String(route.params.nomor ?? '')

const penawaran = ref<Penawaran | null>(null)
const memuat = ref(true)
const galat = ref<string | null>(null)

async function muat() {
  try {
    penawaran.value = await ambilPenawaran(nomor)
    galat.value = null
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
}

onMounted(muat)

/* ---------------- Tampilan ---------------- */

const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

function tanggal(iso: string | null): string {
  if (!iso) return '-'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '-'
  return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}`
}

const disetujui = computed(() => penawaran.value?.status === 'disetujui')
const kedaluwarsa = computed(() => penawaran.value?.status === 'kedaluwarsa')
const bisaDitindak = computed(() => !disetujui.value && !kedaluwarsa.value)

const paketDipilih = computed(
  () => penawaran.value?.paket.find((p) => p.id === penawaran.value?.paket_dipilih_id) ?? null,
)

/** Paket yang sedang disorot pengguna sebelum menekan setuju. */
const paketSorot = ref<number | null>(null)
const paketAktif = computed(() => {
  if (paketDipilih.value) return paketDipilih.value.id
  if (paketSorot.value) return paketSorot.value
  return penawaran.value?.paket.find((p) => p.disarankan)?.id ?? penawaran.value?.paket[0]?.id ?? null
})

/**
 * Langkah status. Ditandai dari status server, bukan dari centang yang
 * ditulis tetap — pipeline yang selalu penuh centang tidak berarti apa-apa.
 */
const LANGKAH = [
  { kunci: 'diterima', label: 'Data diterima' },
  { kunci: 'ditinjau', label: 'Ditinjau tim BisaBersih' },
  { kunci: 'dikirim', label: 'Penawaran dikirim' },
  { kunci: 'persetujuan', label: 'Menunggu persetujuan' },
  { kunci: 'disetujui', label: 'Disetujui' },
]

const langkahSelesai = computed(() => {
  const s = penawaran.value?.status
  if (s === 'disetujui') return 5
  if (s === 'dikirim' || s === 'revisi' || s === 'kedaluwarsa') return 3
  if (s === 'survei') return 2
  return 1
})

/* ---------------- Aksi ---------------- */
const memproses = ref(false)
const pesanAksi = ref<string | null>(null)

async function setujui() {
  const id = paketAktif.value
  if (!id || memproses.value) return

  memproses.value = true
  galat.value = null
  try {
    penawaran.value = await setujuiPenawaran(nomor, id)
    pesanAksi.value = 'Penawaran disetujui. Tim akan menghubungi untuk menjadwalkan layanan pertama.'
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}

/* ---------------- Ajukan perubahan ---------------- */
const sheetRevisi = ref(false)
const revisiDipilih = ref<string[]>([])
const catatanRevisi = ref('')

function toggleRevisi(kunci: string) {
  const i = revisiDipilih.value.indexOf(kunci)
  if (i >= 0) revisiDipilih.value.splice(i, 1)
  else revisiDipilih.value.push(kunci)
}

async function kirimRevisi() {
  if (!revisiDipilih.value.length || memproses.value) return

  memproses.value = true
  galat.value = null
  try {
    penawaran.value = await ajukanRevisi(nomor, [...revisiDipilih.value], catatanRevisi.value.trim() || undefined)
    sheetRevisi.value = false
    revisiDipilih.value = []
    catatanRevisi.value = ''
    pesanAksi.value = 'Permintaan perubahan terkirim. Tim akan menyusun penawaran baru.'
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}

/* ---------------- Chat & PDF ---------------- */
function keChat() {
  const id = penawaran.value?.task_id
  if (!id) {
    galat.value = 'Percakapan belum tersedia untuk penawaran ini.'
    return
  }
  router.push({ name: 'task-chat', params: { id } })
}

const mengunduh = ref(false)

async function unduh() {
  if (mengunduh.value) return
  mengunduh.value = true
  galat.value = null
  try {
    await unduhPdfPenawaran(nomor)
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    mengunduh.value = false
  }
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-48">
    <header class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/10">
      <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Penawaran</h1>
      </div>
    </header>

    <p v-if="memuat" class="max-w-[430px] mx-auto px-4 pt-6 text-[13px] text-(--color-on-surface-variant)">
      Memuat penawaran&hellip;
    </p>

    <p
      v-else-if="!penawaran"
      class="max-w-[430px] mx-auto px-4 pt-6 text-[13px] text-(--color-error)"
    >
      {{ galat ?? 'Penawaran tidak ditemukan.' }}
    </p>

    <main v-else class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-4">
      <!-- Kepala dokumen -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[11px] text-(--color-on-surface-variant)">Nomor penawaran</p>
            <h2 class="text-[18px] font-display font-extrabold">{{ penawaran.nomor }}</h2>
          </div>
          <span
            class="shrink-0 text-[10.5px] font-extrabold px-2.5 py-1 rounded-full"
            :class="
              disetujui
                ? 'bg-(--color-lime) text-[#33430b]'
                : kedaluwarsa
                  ? 'bg-(--color-error-container) text-(--color-on-error-container)'
                  : penawaran.status === 'revisi'
                    ? 'bg-(--color-tertiary-container) text-(--color-on-tertiary-container)'
                    : 'bg-(--color-primary-container) text-(--color-on-primary-container)'
            "
          >
            {{
              disetujui ? 'DISETUJUI'
              : kedaluwarsa ? 'KEDALUWARSA'
              : penawaran.status === 'revisi' ? 'REVISI DIMINTA'
              : 'MENUNGGU PERSETUJUAN'
            }}
          </span>
        </div>

        <dl class="mt-3.5 flex flex-col gap-1.5 text-[12.5px]">
          <div class="flex gap-3">
            <dt class="w-28 shrink-0 text-(--color-on-surface-variant)">Perusahaan</dt>
            <dd class="font-semibold">{{ penawaran.nama_perusahaan }}</dd>
          </div>
          <div v-if="penawaran.nama_pic" class="flex gap-3">
            <dt class="w-28 shrink-0 text-(--color-on-surface-variant)">PIC</dt>
            <dd>{{ penawaran.nama_pic }}<span v-if="penawaran.telepon_pic"> · {{ penawaran.telepon_pic }}</span></dd>
          </div>
          <div class="flex gap-3">
            <dt class="w-28 shrink-0 text-(--color-on-surface-variant)">Lokasi</dt>
            <dd class="leading-snug">{{ penawaran.alamat }}</dd>
          </div>
          <div class="flex gap-3">
            <dt class="w-28 shrink-0 text-(--color-on-surface-variant)">Tanggal</dt>
            <dd>{{ tanggal(penawaran.tanggal) }}</dd>
          </div>
          <div class="flex gap-3">
            <dt class="w-28 shrink-0 text-(--color-on-surface-variant)">Berlaku sampai</dt>
            <dd :class="kedaluwarsa ? 'font-bold text-(--color-error)' : ''">
              {{ tanggal(penawaran.berlaku_sampai) }}
            </dd>
          </div>
        </dl>
      </section>

      <!-- Status -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-3.5">Status Penawaran</h2>
        <ul class="flex flex-col gap-2.5">
          <li v-for="(l, i) in LANGKAH" :key="l.kunci" class="flex items-center gap-2.5 text-[12.5px]">
            <span
              class="w-5 h-5 rounded-full flex items-center justify-center shrink-0"
              :class="
                i < langkahSelesai
                  ? 'bg-(--color-azure) text-white'
                  : 'border-2 border-(--color-outline)/40'
              "
            >
              <Icon v-if="i < langkahSelesai" name="check" class="w-3 h-3" />
            </span>
            <span :class="i < langkahSelesai ? 'font-semibold' : 'text-(--color-on-surface-variant)'">
              {{ l.label }}
            </span>
          </li>
        </ul>

        <div
          v-if="penawaran.revisi.length"
          class="mt-4 pt-3.5 border-t border-(--color-outline)/15"
        >
          <h3 class="text-[12.5px] font-bold mb-1.5">Perubahan yang kamu ajukan</h3>
          <ul class="flex flex-col gap-1">
            <li
              v-for="(r, i) in penawaran.revisi"
              :key="i"
              class="text-[12px] text-(--color-on-surface-variant) leading-snug"
            >
              {{ r.permintaan.join(', ') }}<template v-if="r.catatan"> — “{{ r.catatan }}”</template>
            </li>
          </ul>
        </div>
      </section>

      <!-- Ringkasan kebutuhan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-2">Ringkasan Kebutuhan</h2>
        <p class="text-[13px] leading-relaxed">{{ penawaran.ringkasan }}</p>
      </section>

      <!-- Scope of work -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-3">Scope of Work</h2>
        <div class="overflow-x-auto -mx-1 px-1">
          <table class="w-full text-[12px] border-collapse">
            <thead>
              <tr class="text-[10.5px] uppercase tracking-wide text-(--color-on-surface-variant)">
                <th class="text-left font-bold pb-2 pr-2">Area</th>
                <th class="text-left font-bold pb-2 pr-2">Pekerjaan</th>
                <th class="text-left font-bold pb-2 whitespace-nowrap">Frekuensi</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="(s, i) in penawaran.scope"
                :key="i"
                class="border-t border-(--color-outline)/15 align-top"
              >
                <td class="py-2 pr-2 font-semibold">{{ s.area }}</td>
                <td class="py-2 pr-2 text-(--color-on-surface-variant) leading-snug">{{ s.pekerjaan }}</td>
                <td class="py-2 whitespace-nowrap text-(--color-on-surface-variant)">{{ s.frekuensi }}</td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>

      <!-- Tiga pilihan paket -->
      <section>
        <h2 class="text-[15px] font-display font-extrabold mb-1">Pilihan Paket</h2>
        <p class="text-[12px] text-(--color-on-surface-variant) mb-3 leading-snug">
          Tiga tingkat cakupan untuk kebutuhan yang sama. Pilih satu sebelum menyetujui.
        </p>

        <div class="flex flex-col gap-2.5">
          <button
            v-for="p in penawaran.paket"
            :key="p.id"
            type="button"
            :disabled="!bisaDitindak"
            class="relative text-left rounded-2xl border-2 p-4 transition-all active:scale-[0.99] disabled:active:scale-100"
            :class="
              paketAktif === p.id
                ? 'border-(--color-azure) bg-(--color-primary-container)/40 shadow-[0_10px_28px_rgba(30,155,240,0.18)]'
                : 'border-(--color-outline)/20 bg-(--color-surface-0)'
            "
            :aria-pressed="paketAktif === p.id"
            @click="paketSorot = p.id"
          >
            <span
              v-if="p.disarankan"
              class="absolute -top-2 right-3 rounded-full bg-(--color-gold) text-[9.5px] font-extrabold text-[#3f3000] px-2 py-0.5"
            >
              DISARANKAN
            </span>

            <span class="flex items-baseline justify-between gap-2">
              <span class="text-[15px] font-extrabold">{{ p.nama }}</span>
              <span
                v-if="paketDipilih?.id === p.id"
                class="text-[10px] font-extrabold text-(--color-azure) shrink-0"
              >
                DIPILIH
              </span>
            </span>

            <span class="block text-[17px] font-display font-extrabold text-(--color-azure) mt-1">
              {{ rupiah(p.harga_bulanan) }}
              <span class="text-[11.5px] font-normal text-(--color-on-surface-variant)">/bulan</span>
            </span>
            <span class="block text-[11px] text-(--color-on-surface-variant)">
              {{ rupiah(p.harga_per_kunjungan) }} × {{ p.kunjungan_per_bulan }} kunjungan
            </span>

            <ul class="mt-2.5 flex flex-col gap-1">
              <li
                v-for="i in p.isi"
                :key="i"
                class="flex items-start gap-1.5 text-[12px] text-(--color-on-surface-variant)"
              >
                <Icon name="check" class="w-3.5 h-3.5 shrink-0 mt-0.5 text-(--color-azure)" />
                <span class="leading-snug">{{ i }}</span>
              </li>
            </ul>
          </button>
        </div>
      </section>

      <!-- Biaya tambahan & pengecualian -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-2.5">Biaya Tambahan</h2>
        <ul class="flex flex-col gap-1.5 mb-4">
          <li
            v-for="(b, i) in penawaran.biaya_tambahan"
            :key="i"
            class="flex items-start gap-2 text-[12px] text-(--color-on-surface-variant)"
          >
            <Icon name="info" class="w-3.5 h-3.5 shrink-0 mt-0.5" />
            <span class="leading-snug">{{ b }}</span>
          </li>
        </ul>

        <h2 class="text-[15px] font-display font-extrabold mb-2.5">Pengecualian Layanan</h2>
        <ul class="flex flex-col gap-1.5">
          <li
            v-for="(x, i) in penawaran.pengecualian"
            :key="i"
            class="flex items-start gap-2 text-[12px] text-(--color-on-surface-variant)"
          >
            <Icon name="x" class="w-3.5 h-3.5 shrink-0 mt-0.5" />
            <span class="leading-snug">{{ x }}</span>
          </li>
        </ul>
      </section>

      <p
        v-if="pesanAksi"
        class="rounded-2xl bg-(--color-secondary-container) text-(--color-on-secondary-container) p-4 text-[12.5px] leading-snug"
      >
        {{ pesanAksi }}
      </p>
      <p v-if="galat" role="alert" class="text-[12.5px] font-semibold text-(--color-error)">
        {{ galat }}
      </p>
    </main>

    <!-- Aksi -->
    <div
      v-if="penawaran"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0)/95 backdrop-blur-sm border-t border-(--color-outline)/15"
    >
      <div class="max-w-[430px] mx-auto px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] flex flex-col gap-2.5">
        <button
          v-if="bisaDitindak"
          type="button"
          :disabled="memproses || !paketAktif"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-bold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-50"
          @click="setujui"
        >
          <Icon name="check-circle" class="w-4.5 h-4.5" />
          {{ memproses ? 'Memproses…' : 'Setujui Penawaran' }}
        </button>

        <p
          v-else-if="disetujui && paketDipilih"
          class="text-[12.5px] text-center font-semibold text-(--color-on-surface-variant)"
        >
          Disetujui pada paket {{ paketDipilih.nama }} — {{ rupiah(paketDipilih.harga_bulanan) }}/bulan.
        </p>
        <p v-else class="text-[12.5px] text-center font-semibold text-(--color-error)">
          Masa berlaku penawaran ini sudah lewat.
        </p>

        <div class="grid grid-cols-3 gap-2">
          <button
            type="button"
            :disabled="!bisaDitindak"
            class="h-11 rounded-full border border-(--color-outline)/40 text-[12px] font-bold flex items-center justify-center gap-1.5 active:scale-95 transition-transform disabled:opacity-40"
            @click="sheetRevisi = true"
          >
            <Icon name="edit" class="w-4 h-4" />
            Revisi
          </button>
          <button
            type="button"
            class="h-11 rounded-full border border-(--color-outline)/40 text-[12px] font-bold flex items-center justify-center gap-1.5 active:scale-95 transition-transform"
            @click="keChat"
          >
            <Icon name="chat" class="w-4 h-4" />
            Chat
          </button>
          <button
            type="button"
            :disabled="mengunduh"
            class="h-11 rounded-full border border-(--color-outline)/40 text-[12px] font-bold flex items-center justify-center gap-1.5 active:scale-95 transition-transform disabled:opacity-40"
            @click="unduh"
          >
            <Icon name="receipt" class="w-4 h-4" />
            {{ mengunduh ? '…' : 'PDF' }}
          </button>
        </div>
      </div>
    </div>

    <!-- Ajukan perubahan -->
    <div v-if="sheetRevisi" class="fixed inset-0 z-50 flex items-end justify-center">
      <div class="absolute inset-0 bg-black/45" @click="sheetRevisi = false"></div>

      <div
        class="relative w-full max-w-[430px] bg-(--color-surface-0) rounded-t-[28px] p-5 pb-[calc(1.25rem+env(safe-area-inset-bottom))] max-h-[85dvh] overflow-y-auto"
      >
        <div class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mb-4"></div>

        <h3 class="text-[15px] font-display font-extrabold mb-1">Ajukan Perubahan</h3>
        <p class="text-[12px] text-(--color-on-surface-variant) mb-4 leading-snug">
          Pilih apa yang ingin diubah. Tim akan menyusun penawaran baru — penawaran ini tidak batal.
        </p>

        <div class="flex flex-col gap-2">
          <label
            v-for="(label, kunci) in penawaran?.pilihan_revisi ?? {}"
            :key="kunci"
            class="flex items-center gap-3 rounded-xl border-2 px-3.5 py-3 cursor-pointer transition-colors"
            :class="
              revisiDipilih.includes(kunci)
                ? 'border-(--color-azure) bg-(--color-primary-container)/40'
                : 'border-(--color-outline)/25'
            "
          >
            <input
              type="checkbox"
              class="sr-only"
              :checked="revisiDipilih.includes(kunci)"
              @change="toggleRevisi(kunci)"
            />
            <span
              class="w-5 h-5 rounded-md border-2 flex items-center justify-center shrink-0"
              :class="
                revisiDipilih.includes(kunci)
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)'
              "
            >
              <Icon v-if="revisiDipilih.includes(kunci)" name="check" class="w-3.5 h-3.5" />
            </span>
            <span class="text-[13px]">{{ label }}</span>
          </label>
        </div>

        <label class="block mt-3.5">
          <span class="block text-[12px] font-semibold text-(--color-on-surface-variant) mb-1">
            Catatan (opsional)
          </span>
          <textarea
            v-model="catatanRevisi"
            rows="3"
            maxlength="500"
            placeholder="Mis. jadi 3x seminggu, mulai bulan depan."
            class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13.5px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
          />
        </label>

        <button
          type="button"
          :disabled="!revisiDipilih.length || memproses"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-bold mt-4 active:scale-[0.98] transition-transform disabled:opacity-40"
          @click="kirimRevisi"
        >
          {{ memproses ? 'Mengirim…' : 'Kirim Permintaan Perubahan' }}
        </button>
      </div>
    </div>
  </div>
</template>
