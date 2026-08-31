<script setup lang="ts">
/**
 * Hasil Pemeriksaan AC.
 *
 * Halaman ini punya dua keadaan, dan keduanya ditentukan DATA DI SERVER:
 *
 * 1. Teknisi belum selesai memeriksa → yang tampil status pesanan dan jadwal.
 *    Tidak ada rekomendasi yang dikarang sebelum ada yang memeriksa.
 * 2. Hasil sudah ada → temuan, bukti foto (kalau teknisi mengunggahnya),
 *    rekomendasi beserta biayanya, dan tiga jawaban: setujui, minta penjelasan,
 *    atau tolak.
 *
 * Selama pelanggan belum menjawab, tagihan tidak bertambah sepeser pun.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import MenungguPemeriksaanArt from '@/components/servis-ac/MenungguPemeriksaanArt.vue'
import PemuatBerputar from '@/components/ui/PemuatBerputar.vue'
import apiClient from '@/api/client'
import { setujuiDiagnosa, tolakDiagnosa } from '@/api/freon'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import { hitungRekomendasi } from '@/lib/servis-ac/hargaFreon'

interface Diagnosis {
  status_freon: string
  indikasi_kebocoran: string
  jenis_freon: string
  rekomendasi: string
  pekerjaan: string[]
  diperiksa_pada: string
  keputusan: 'disetujui' | 'ditolak' | null
}

interface PesananUmum {
  nomor: string | null
  task_id: number
  judul: string
  status: string
  detail_layanan: Record<string, unknown>
  dijadwalkan_pada: string | null
  lokasi: { alamat: string | null }
  total: number
  potongan: number
}

const route = useRoute()
const router = useRouter()
const nomor = String(route.params.nomor ?? '')

const pesanan = ref<PesananUmum | null>(null)
const memuat = ref(true)
const galat = ref<string | null>(null)
const memproses = ref(false)

async function muat() {
  try {
    const { data } = await apiClient.get<PesananUmum>(`/pesanan/${encodeURIComponent(nomor)}`)
    pesanan.value = data
  } catch {
    galat.value = 'Pesanan tidak ditemukan atau gagal dimuat.'
  } finally {
    memuat.value = false
  }
}

onMounted(muat)

const detail = computed(() => (pesanan.value?.detail_layanan ?? {}) as Record<string, unknown>)
const diagnosis = computed(() => (detail.value.diagnosis ?? null) as Diagnosis | null)

const biayaPemeriksaan = computed(() => Number(detail.value.biaya_pemeriksaan ?? 0))

const rekomendasi = computed(() =>
  hitungRekomendasi(diagnosis.value?.pekerjaan ?? [], biayaPemeriksaan.value),
)

const sudahDijawab = computed(() => diagnosis.value?.keputusan != null)

/*
 * Halaman ini melayani dua pesanan yang keadaannya sama — menunggu teknisi,
 * lalu menjawab rekomendasinya — tapi pekerjaannya berbeda. Kalimat tunggunya
 * menyebut pekerjaan yang benar-benar dipesan.
 */
const teksMenunggu = computed(() =>
  detail.value.layanan === 'perbaikan'
    ? 'Teknisi akan datang sesuai jadwal, memeriksa unit indoor dan outdoor, lalu menuliskan hasil diagnosisnya di halaman ini.'
    : 'Teknisi akan datang sesuai jadwal, memeriksa tekanan dan kemungkinan kebocoran, lalu menuliskan hasilnya di halaman ini.',
)

const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

const jadwalTeks = computed(() => {
  const iso = pesanan.value?.dijadwalkan_pada
  if (!iso) return '-'

  const bagian = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit', hour12: false,
  }).formatToParts(new Date(iso))
  const ambil = (t: string) => bagian.find((b) => b.type === t)?.value ?? ''

  return `${ambil('day')} ${BULAN[Number(ambil('month')) - 1]} ${ambil('year')}, ${ambil('hour')}:${ambil('minute')} WIB`
})

async function jawab(setuju: boolean) {
  if (memproses.value) return
  memproses.value = true
  galat.value = null

  try {
    if (setuju) await setujuiDiagnosa(nomor)
    else await tolakDiagnosa(nomor)
    await muat()
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}

function keChat() {
  const id = pesanan.value?.task_id
  if (id) router.push({ name: 'task-chat', params: { id } })
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
          @click="router.replace({ name: 'task-list' })"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Hasil Pemeriksaan</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <PemuatBerputar v-if="memuat" label="Memuat pemeriksaan…" />

      <template v-else-if="pesanan">
        <!-- Belum diperiksa -->
        <section v-if="!diagnosis" class="bg-(--color-surface-0) rounded-2xl p-6 text-center">
          <MenungguPemeriksaanArt class="max-w-[260px] mx-auto" />

          <h2 class="mt-2 text-[18px] font-display font-extrabold">Menunggu pemeriksaan</h2>
          <!--
            Halaman ini dipakai dua layanan: pemeriksaan freon dan perbaikan.
            Kalimatnya menyesuaikan — menjanjikan "memeriksa tekanan dan
            kebocoran" pada pesanan perbaikan berarti menyebut pekerjaan yang
            bukan pesanannya.
          -->
          <p class="mt-1.5 text-[12.5px] leading-relaxed text-(--color-on-surface-variant)">
            {{ teksMenunggu }}
          </p>

          <div class="mt-5 pt-4 border-t border-(--color-outline)/15 flex flex-col gap-2 text-left">
            <div class="flex justify-between gap-3 text-[12.5px]">
              <span class="text-(--color-on-surface-variant)">Nomor pesanan</span>
              <span class="font-bold">{{ pesanan.nomor }}</span>
            </div>
            <div class="flex justify-between gap-3 text-[12.5px]">
              <span class="text-(--color-on-surface-variant)">Jadwal</span>
              <span class="font-bold text-right">{{ jadwalTeks }}</span>
            </div>
            <div class="flex justify-between gap-3 text-[12.5px]">
              <span class="text-(--color-on-surface-variant)">Dibayar sekarang</span>
              <span class="font-bold">{{ rupiah(pesanan.total) }}</span>
            </div>
          </div>
        </section>

        <template v-else>
          <!-- Temuan -->
          <section class="bg-(--color-surface-0) rounded-2xl p-5">
            <div class="flex items-center gap-3 mb-4">
              <span
                class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                :class="
                  diagnosis.indikasi_kebocoran.toLowerCase().startsWith('tidak')
                    ? 'bg-(--color-secondary-container) text-(--color-on-secondary-container)'
                    : 'bg-(--color-error-container) text-(--color-on-error-container)'
                "
              >
                <Icon name="alert" class="w-5 h-5" />
              </span>
              <h2 class="text-[16px] font-display font-extrabold">
                {{
                  diagnosis.indikasi_kebocoran.toLowerCase().startsWith('tidak')
                    ? 'Pemeriksaan selesai'
                    : 'Masalah ditemukan'
                }}
              </h2>
            </div>

            <dl class="flex flex-col gap-2.5">
              <div class="rounded-xl bg-(--color-surface-container) p-3.5">
                <dt class="text-[11px] uppercase tracking-wider text-(--color-on-surface-variant)">
                  Status freon
                </dt>
                <dd class="text-[13px] font-bold mt-0.5">{{ diagnosis.status_freon }}</dd>
              </div>
              <div class="rounded-xl bg-(--color-surface-container) p-3.5">
                <dt class="text-[11px] uppercase tracking-wider text-(--color-on-surface-variant)">
                  Indikasi kebocoran
                </dt>
                <dd class="text-[13px] font-bold mt-0.5">{{ diagnosis.indikasi_kebocoran }}</dd>
              </div>
              <!--
                Hanya untuk pesanan pemeriksaan freon. Pada pesanan perbaikan,
                teknisi tidak membaca jenis freon — dan label bernilai kosong
                terbaca sebagai data yang hilang, bukan data yang memang tidak
                ada.
              -->
              <div
                v-if=diagnosis.jenis_freon
                class="rounded-xl bg-(--color-surface-container) p-3.5"
              >
                <dt class="text-[11px] uppercase tracking-wider text-(--color-on-surface-variant)">
                  Jenis freon
                </dt>
                <dd class="text-[13px] font-bold mt-0.5">{{ diagnosis.jenis_freon }}</dd>
              </div>
            </dl>

            <div class="mt-4 rounded-xl bg-(--color-primary-container)/40 border-l-4 border-(--color-azure) p-3.5">
              <p class="text-[12.5px] font-bold text-(--color-on-primary-container) mb-1 flex items-center gap-1.5">
                <span class="material-symbols-outlined text-[18px]" data-icon="build">build</span>
                Rekomendasi teknisi
              </p>
              <p class="text-[12px] leading-relaxed text-(--color-on-surface-variant)">
                {{ diagnosis.rekomendasi }}
              </p>
            </div>
          </section>

          <!-- Rekomendasi & biaya -->
          <section class="bg-(--color-surface-0) rounded-2xl p-5">
            <h2 class="text-[14px] font-display font-extrabold mb-3">Rekomendasi Servis &amp; Biaya</h2>

            <div class="flex flex-col gap-2 text-[12.5px]">
              <div class="flex justify-between gap-3 text-(--color-on-surface-variant)">
                <span>Pemeriksaan (sudah dibayar)</span>
                <span class="font-bold text-(--color-on-surface)">{{ rupiah(biayaPemeriksaan) }}</span>
              </div>

              <div
                v-for="b in rekomendasi.baris"
                :key="b.id"
                class="flex justify-between gap-3 text-(--color-on-surface-variant)"
              >
                <span>{{ b.nama }}</span>
                <span class="font-bold text-(--color-on-surface) whitespace-nowrap">
                  {{ rupiah(b.harga) }}
                </span>
              </div>

              <div class="flex justify-between gap-3 pt-2.5 mt-1 border-t border-(--color-outline)/12">
                <span class="font-bold">Subtotal pekerjaan</span>
                <span class="font-bold">{{ rupiah(rekomendasi.subtotal) }}</span>
              </div>

              <div class="flex justify-between gap-3 text-(--color-error)">
                <span>Potongan biaya pemeriksaan*</span>
                <span class="font-bold">&minus;{{ rupiah(rekomendasi.kreditPemeriksaan) }}</span>
              </div>

              <div class="flex justify-between items-center gap-3 pt-2.5 mt-1 border-t border-(--color-outline)/12">
                <span class="text-[13px] font-extrabold">Total estimasi</span>
                <span class="text-[18px] font-extrabold text-(--color-azure)">
                  {{ rupiah(rekomendasi.total) }}
                </span>
              </div>
            </div>

            <p class="mt-3 text-[11px] leading-snug text-(--color-on-surface-variant)">
              *Biaya pemeriksaan yang sudah Anda bayar dipotong dari total kalau rekomendasi
              ini disetujui.
            </p>
          </section>

          <!-- Keputusan -->
          <section
            v-if="sudahDijawab"
            class="rounded-2xl p-5"
            :class="
              diagnosis.keputusan === 'disetujui'
                ? 'bg-(--color-secondary-container)/50'
                : 'bg-(--color-surface-0)'
            "
          >
            <p class="text-[13px] font-extrabold flex items-center gap-2">
              <Icon
                :name="diagnosis.keputusan === 'disetujui' ? 'check-circle' : 'x'"
                class="w-4.5 h-4.5"
              />
              {{
                diagnosis.keputusan === 'disetujui'
                  ? 'Rekomendasi disetujui'
                  : 'Pekerjaan tambahan ditolak'
              }}
            </p>
            <p class="mt-1.5 text-[12px] leading-snug text-(--color-on-surface-variant)">
              {{
                diagnosis.keputusan === 'disetujui'
                  ? 'Teknisi akan mengerjakannya sekarang. Tagihan sudah diperbarui.'
                  : 'Yang ditagih tetap biaya pemeriksaan saja.'
              }}
            </p>
          </section>
        </template>

        <p v-if="galat" role="alert" class="text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </template>
    </main>

    <!--
      Tiga jawaban, bukan satu. "Minta penjelasan" ada di antaranya karena
      pelanggan yang belum paham temuannya seharusnya bisa bertanya — bukan
      dipaksa memilih setuju atau tolak.
    -->
    <footer
      v-if="diagnosis && !sudahDijawab"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))] flex flex-col gap-2.5">
        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="memproses"
          @click="jawab(true)"
        >
          {{ memproses ? 'Memproses…' : 'Setujui & Lanjutkan' }}
        </button>

        <div class="flex gap-2.5">
          <button
            type="button"
            class="flex-1 h-11 rounded-full bg-(--color-surface-container) text-[13px] font-bold flex items-center justify-center gap-1.5 active:scale-95 transition-transform"
            @click="keChat"
          >
            <Icon name="chat" class="w-4 h-4" />
            Minta Penjelasan
          </button>
          <button
            type="button"
            class="flex-1 h-11 rounded-full border border-(--color-outline)/50 text-[13px] font-bold active:scale-95 transition-transform disabled:opacity-40"
            :disabled="memproses"
            @click="jawab(false)"
          >
            Tolak
          </button>
        </div>
      </div>
    </footer>
  </div>
</template>
