<script setup lang="ts">
/**
 * Perbaikan & Pasang AC — halaman masuk.
 *
 * Sengaja bercabang sejak awal, bukan satu proses panjang. Perbaikan dan
 * pemasangan menanyakan hal yang berbeda, menghitung uang dengan cara yang
 * berbeda, dan dikerjakan teknisi dengan persiapan yang berbeda. Menyatukannya
 * berarti menanyai orang yang mau memasang AC tentang keluhan AC yang belum ia
 * punya.
 *
 * Perbedaan itu juga terbaca di kartunya: perbaikan menyebut biaya pemeriksaan
 * yang pasti, pemasangan menyebut RENTANG, dan pindah AC tidak menyebut angka
 * sama sekali karena harganya memang belum bisa diketahui sebelum survei.
 */
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { rupiah } from '@/lib/rupiah'
import {
  BIAYA_PEMERIKSAAN_PERBAIKAN,
  PASANG_MULAI,
  PASANG_SAMPAI,
} from '@/lib/servis-ac/perbaikanAC'

const router = useRouter()
const kembali = useKembali()

const LAYANAN = [
  {
    id: 'perbaiki',
    ikon: 'wrench',
    judul: 'Perbaiki AC',
    isi: 'AC tidak dingin, bocor, berisik, mati total, atau muncul kode error.',
    labelHarga: 'Biaya pemeriksaan',
    harga: `Mulai ${rupiah(BIAYA_PEMERIKSAAN_PERBAIKAN)}`,
    tombol: 'Periksa Sekarang',
    garis: 'border-(--color-azure)',
    tujuan: 'servis-ac-perbaiki',
  },
  {
    id: 'pasang',
    ikon: 'plus-square',
    judul: 'Pasang AC',
    isi: 'Pemasangan unit baru dengan estimasi material yang transparan.',
    labelHarga: 'Paket lengkap',
    harga: `${rupiah(PASANG_MULAI)} – ${rupiah(PASANG_SAMPAI)}`,
    tombol: 'Ajukan Pemasangan',
    garis: 'border-(--color-lime)',
    tujuan: 'servis-ac-pasang',
  },
  {
    id: 'pindah',
    ikon: 'truck',
    judul: 'Pindah AC',
    isi: 'Bongkar dan pasang kembali AC ke lokasi yang berbeda.',
    labelHarga: 'Harga',
    harga: 'Setelah survei lokasi',
    tombol: 'Pesan Survei',
    garis: 'border-(--color-error)',
    tujuan: 'servis-ac-pasang',
    // Jenis pekerjaannya sudah pasti, jadi formulirnya dibuka dengan pilihan
    // itu terisi — tidak perlu ditanya ulang apa yang sudah diketuk.
    query: { jenis: 'pindah-lokasi' },
  },
] as const

function buka(l: (typeof LAYANAN)[number]) {
  router.push({ name: l.tujuan, query: 'query' in l ? l.query : undefined })
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-16">
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Perbaikan &amp; Pasang AC</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-5 flex flex-col gap-4">
      <section>
        <h2 class="text-[20px] font-display font-extrabold leading-tight mb-2">
          Butuh AC kembali dingin atau ingin memasang unit baru?
        </h2>
        <p class="text-[13px] leading-snug text-(--color-on-surface-variant)">
          Teknisi BisaBersih siap membantu. Harga dikonfirmasi sebelum pekerjaan dimulai — bukan
          setelahnya.
        </p>
      </section>

      <button
        v-for="l in LAYANAN"
        :key="l.id"
        type="button"
        class="text-left bg-(--color-surface-0) rounded-2xl border-l-4 p-5 active:scale-[0.99] transition-transform shadow-(--shadow-lift)"
        :class="l.garis"
        @click="buka(l)"
      >
        <div class="flex items-center gap-3 mb-2.5">
          <span
            class="w-11 h-11 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0"
          >
            <Icon :name="l.ikon" class="w-5 h-5 text-(--color-azure)" />
          </span>
          <h3 class="text-[17px] font-display font-extrabold">{{ l.judul }}</h3>
        </div>

        <p class="text-[13px] leading-snug text-(--color-on-surface-variant) mb-3.5">
          {{ l.isi }}
        </p>

        <div class="rounded-xl bg-(--color-surface-container) px-3.5 py-2.5 mb-3.5">
          <p class="text-[10.5px] uppercase tracking-wider text-(--color-on-surface-variant)">
            {{ l.labelHarga }}
          </p>
          <p class="text-[15px] font-extrabold text-(--color-azure)">{{ l.harga }}</p>
        </div>

        <span
          class="w-full h-11 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold flex items-center justify-center gap-2"
        >
          {{ l.tombol }}
          <Icon name="arrow-right" class="w-4 h-4" />
        </span>
      </button>

      <!--
        Ditulis di halaman masuk, bukan disimpan sampai halaman penawaran:
        orang berhak tahu apa yang menggerakkan harganya sebelum mengisi
        formulir panjang.
      -->
      <p class="px-1 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)">
        Harga pemasangan bergantung panjang pipa, jalur kabel, bracket, kapasitas AC, akses lokasi,
        dan tingkat kesulitannya. Material tambahan seperti pipa dihitung per meter. Angka final
        dikonfirmasi setelah foto diperiksa atau lokasi disurvei.
      </p>
    </main>
  </div>
</template>
