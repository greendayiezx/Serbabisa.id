<script setup lang="ts">
/**
 * Dashboard tim — permintaan penawaran BisaBersih Kantor.
 *
 * Di sinilah tahap permintaan digerakkan. Yang dilihat pelanggan di layar
 * "Langkah Selanjutnya" adalah tahap yang ditekan DI SINI, bukan hitungan
 * waktu: kalau belum ada yang menghubungi PIC, langkah itu belum tercentang.
 *
 * Tahap hanya bisa maju. Memundurkannya berarti memberi tahu pelanggan bahwa
 * sesuatu yang sudah terjadi ternyata belum.
 */
import { computed, onMounted, ref } from 'vue'
import AppLayout from '@/layouts/AppLayout.vue'
import Icon from '@/components/icons/Icon.vue'
import { rupiah } from '@/lib/rupiah'
import { pesanError } from '@/api/belanja'
import {
  daftarPermintaanAdmin,
  majukanTahap,
  type PermintaanAdmin,
  type TahapPermintaan,
} from '@/api/adminPermintaan'

const daftar = ref<PermintaanAdmin[]>([])
const jumlah = ref<Record<string, number>>({})
const memuat = ref(true)
const galat = ref<string | null>(null)
const sedangProses = ref<string | null>(null)

const SARINGAN: { id: TahapPermintaan | 'semua'; label: string }[] = [
  { id: 'semua', label: 'Semua' },
  { id: 'ditinjau', label: 'Perlu ditinjau' },
  { id: 'dihubungi', label: 'Sudah dihubungi' },
  { id: 'survei', label: 'Survei' },
]

const saringan = ref<TahapPermintaan | 'semua'>('semua')

async function muat() {
  memuat.value = true
  galat.value = null
  try {
    const d = await daftarPermintaanAdmin()
    daftar.value = d.permintaan
    jumlah.value = d.jumlah
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
}

onMounted(muat)

const tersaring = computed(() =>
  saringan.value === 'semua'
    ? daftar.value
    : daftar.value.filter((p) => p.tahap === saringan.value),
)

/**
 * Aksi berikutnya untuk satu permintaan.
 *
 * null berarti tim sudah selesai dengan tahap yang bisa ditekan — sisanya
 * menyusun penawaran, dan itu tercapai lewat dokumen penawarannya sendiri.
 */
function aksiBerikut(p: PermintaanAdmin): { tahap: TahapPermintaan; label: string } | null {
  if (p.tahap === 'ditinjau') return { tahap: 'dihubungi', label: 'ACC & Hubungi PIC' }
  if (p.tahap === 'dihubungi') return { tahap: 'survei', label: 'Jadwalkan Survei' }
  return null
}

async function majukan(p: PermintaanAdmin) {
  const aksi = aksiBerikut(p)
  if (!aksi || sedangProses.value) return

  sedangProses.value = p.nomor
  galat.value = null
  try {
    const baru = await majukanTahap(p.nomor, aksi.tahap)
    const i = daftar.value.findIndex((x) => x.nomor === p.nomor)
    if (i >= 0) daftar.value[i] = baru
    // Hitungan lencana ikut berubah, jadi diambil ulang.
    void muat()
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    sedangProses.value = null
  }
}

/** Tautan WhatsApp ke nomor PIC yang didaftarkan pelanggan. */
function tautanWa(p: PermintaanAdmin): string | null {
  const nomor = (p.telepon_pic ?? '').replace(/[^0-9]/g, '')
  if (!nomor) return null

  // 08xx → 628xx: wa.me menuntut format internasional tanpa tanda plus.
  const internasional = nomor.startsWith('0') ? '62' + nomor.slice(1) : nomor
  const pesan = encodeURIComponent(
    `Halo ${p.nama_pic ?? ''}, saya dari tim BisaBersih. Kami sudah menerima permintaan penawaran ${p.nomor} untuk ${p.nama_perusahaan}.`,
  )

  return `https://wa.me/${internasional}?text=${pesan}`
}

const BULAN = [
  'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
  'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
]

function tanggal(iso: string | null): string {
  if (!iso) return '-'
  const d = new Date(iso)
  if (Number.isNaN(d.getTime())) return '-'
  const jj = String(d.getHours()).padStart(2, '0')
  const mm = String(d.getMinutes()).padStart(2, '0')
  return `${d.getDate()} ${BULAN[d.getMonth()]} ${d.getFullYear()}, ${jj}:${mm}`
}

const warnaTahap: Record<TahapPermintaan, string> = {
  ditinjau: 'bg-(--color-tertiary-container) text-(--color-on-tertiary-container)',
  dihubungi: 'bg-(--color-primary-container) text-(--color-on-primary-container)',
  survei: 'bg-(--color-secondary-container) text-(--color-on-secondary-container)',
}
</script>

<template>
  <AppLayout>
    <div class="px-5 pt-5 pb-6">
      <h2 class="text-lg font-extrabold">Permintaan Penawaran Kantor</h2>
      <p class="text-[12.5px] text-(--color-on-surface-variant) mt-1 leading-snug">
        Tahap yang kamu tekan di sini langsung terlihat di layar status pelanggan.
      </p>

      <!-- Saringan tahap -->
      <div class="flex gap-2 overflow-x-auto no-scrollbar -mx-5 px-5 mt-4 pb-1">
        <button
          v-for="s in SARINGAN"
          :key="s.id"
          type="button"
          class="shrink-0 rounded-full px-3.5 py-2 text-[12.5px] font-bold border-2 transition-colors"
          :class="
            saringan === s.id
              ? 'border-(--color-azure) bg-(--color-primary-container) text-(--color-on-primary-container)'
              : 'border-(--color-outline)/25 text-(--color-on-surface-variant)'
          "
          :aria-pressed="saringan === s.id"
          @click="saringan = s.id"
        >
          {{ s.label }}
          <span v-if="s.id !== 'semua' && jumlah[s.id]" class="ml-1 opacity-70">
            ({{ jumlah[s.id] }})
          </span>
        </button>
      </div>

      <p v-if="galat" role="alert" class="mt-4 text-[12.5px] font-semibold text-(--color-error)">
        {{ galat }}
      </p>

      <p v-if="memuat" class="mt-6 text-[13px] text-(--color-on-surface-variant)">
        Memuat permintaan&hellip;
      </p>

      <div v-else-if="!tersaring.length" class="text-center py-12">
        <Icon name="clipboard" class="w-9 h-9 mx-auto text-(--color-on-surface-variant) mb-2" />
        <p class="text-sm text-(--color-on-surface-variant)">
          Tidak ada permintaan pada tahap ini.
        </p>
      </div>

      <div v-else class="flex flex-col gap-3 mt-4">
        <article
          v-for="p in tersaring"
          :key="p.nomor"
          class="rounded-(--radius-card) border border-(--color-outline)/30 bg-(--color-surface-0) p-4"
        >
          <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
              <p class="font-bold text-sm truncate">{{ p.nama_perusahaan }}</p>
              <p class="text-[11.5px] text-(--color-on-surface-variant)">
                {{ p.nomor }} &middot; {{ tanggal(p.dibuat_pada) }}
              </p>
            </div>
            <span
              class="shrink-0 rounded-full text-[10.5px] font-extrabold px-2.5 py-1"
              :class="warnaTahap[p.tahap]"
            >
              {{ p.label_tahap }}
            </span>
          </div>

          <dl class="mt-3 flex flex-col gap-1 text-[12px]">
            <div v-if="p.nama_pic" class="flex gap-2">
              <dt class="w-20 shrink-0 text-(--color-on-surface-variant)">PIC</dt>
              <dd class="flex-1 min-w-0">{{ p.nama_pic }}</dd>
            </div>
            <div v-if="p.telepon_pic" class="flex gap-2">
              <dt class="w-20 shrink-0 text-(--color-on-surface-variant)">WhatsApp</dt>
              <dd class="flex-1 min-w-0">{{ p.telepon_pic }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="w-20 shrink-0 text-(--color-on-surface-variant)">Lokasi</dt>
              <dd class="flex-1 min-w-0 leading-snug">{{ p.alamat }}</dd>
            </div>
            <div class="flex gap-2">
              <dt class="w-20 shrink-0 text-(--color-on-surface-variant)">Kantor</dt>
              <dd class="flex-1 min-w-0">
                {{ p.jenis_kantor ?? '-' }}
                <span v-if="p.frekuensi"> &middot; {{ p.frekuensi }}</span>
              </dd>
            </div>
            <div v-if="p.estimasi" class="flex gap-2">
              <dt class="w-20 shrink-0 text-(--color-on-surface-variant)">Estimasi</dt>
              <dd class="flex-1 min-w-0">{{ rupiah(p.estimasi) }} / kunjungan</dd>
            </div>
          </dl>

          <div class="flex flex-wrap items-center gap-2 mt-2.5">
            <span
              v-if="p.bertanda_tangan"
              class="inline-flex items-center gap-1 text-[11px] font-semibold text-(--color-on-surface-variant)"
            >
              <Icon name="check-circle" class="w-3.5 h-3.5 text-(--color-azure)" />
              Bertanda tangan
            </span>
            <span
              v-if="p.nomor_penawaran"
              class="inline-flex items-center gap-1 text-[11px] font-semibold text-(--color-on-surface-variant)"
            >
              <Icon name="receipt" class="w-3.5 h-3.5 text-(--color-azure)" />
              Penawaran {{ p.nomor_penawaran }}
            </span>
          </div>

          <div class="flex items-center gap-2 mt-3.5">
            <button
              v-if="aksiBerikut(p)"
              type="button"
              :disabled="sedangProses === p.nomor"
              class="flex-1 h-11 rounded-full bg-(--color-azure) text-white text-[13px] font-bold flex items-center justify-center gap-1.5 active:scale-[0.98] transition-transform disabled:opacity-50"
              @click="majukan(p)"
            >
              <Icon name="check" class="w-4 h-4" />
              {{ sedangProses === p.nomor ? 'Memproses…' : aksiBerikut(p)!.label }}
            </button>
            <p
              v-else-if="!p.nomor_penawaran"
              class="flex-1 text-[11.5px] text-(--color-on-surface-variant) leading-snug"
            >
              Berikutnya: susun penawaran untuk permintaan ini.
            </p>
            <p v-else class="flex-1 text-[11.5px] text-(--color-on-surface-variant) leading-snug">
              Penawaran sudah dikirim ke pelanggan.
            </p>

            <a
              v-if="tautanWa(p)"
              :href="tautanWa(p)!"
              target="_blank"
              rel="noopener noreferrer"
              class="shrink-0 h-11 px-4 rounded-full border border-(--color-outline)/40 text-[13px] font-bold flex items-center justify-center gap-1.5 active:scale-[0.98] transition-transform"
            >
              <Icon name="chat" class="w-4 h-4" />
              WhatsApp
            </a>
          </div>
        </article>
      </div>
    </div>
  </AppLayout>
</template>
