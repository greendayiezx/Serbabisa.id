<script setup lang="ts">
/**
 * Perbaikan & Pasang AC — halaman masuk.
 *
 * Dua cabang, bukan tiga. Perbaikan dan pemasangan memang layak dipisah:
 * keduanya menanyakan hal yang berbeda dan menghitung uang dengan cara yang
 * berbeda — perbaikan menagih kunjungan diagnosisnya, pemasangan tidak menagih
 * apa pun sampai penawarannya keluar.
 *
 * Pindah AC TIDAK dipisah, meski sempat begitu. Ia mengisi formulir yang sama,
 * mengirim permintaan yang sama, dan berakhir di penawaran yang sama — bedanya
 * cuma satu pilihan di dalamnya. Menaruhnya sebagai menu tersendiri membuat
 * orang mengira ada dua alur berbeda, lalu bertanya-tanya mana yang benar.
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
    harga: `Mulai ${rupiah(BIAYA_PEMERIKSAAN_PERBAIKAN)}`,
    tombol: 'Periksa Sekarang',
    garis: 'border-(--color-azure)',
    tujuan: 'servis-ac-perbaiki',
  },
  {
    id: 'pasang',
    ikon: 'plus-square',
    judul: 'Pasang & Pindah AC',
    isi: 'Pemasangan unit baru, bongkar-pasang, atau memindahkan AC ke lokasi lain.',
    harga: `${rupiah(PASANG_MULAI)} – ${rupiah(PASANG_SAMPAI)}`,
    tombol: 'Ajukan Pemasangan',
    garis: 'border-(--color-lime)',
    tujuan: 'servis-ac-pasang',
  },
] as const

function buka(l: (typeof LAYANAN)[number]) {
  router.push({ name: l.tujuan })
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
        <div class="rounded-xl border border-(--color-azure)/30 bg-(--color-azure)/8 p-3.5 flex items-start gap-2.5">
          <Icon name="alert" class="w-4 h-4 text-(--color-azure) shrink-0 mt-0.5" />
          <div class="flex flex-col gap-1.5 min-w-0">
            <p class="text-[12.5px] font-bold leading-snug text-(--color-on-surface)">
              Teknisi BisaBersih siap membantu. Harga dikonfirmasi sebelum pekerjaan dimulai.
            </p>
            <p class="text-[11.5px] leading-relaxed text-(--color-on-surface-variant)">
              Harga pemasangan bergantung panjang pipa, jalur kabel, bracket, kapasitas AC, akses lokasi,
              dan tingkat kesulitannya. Material tambahan seperti pipa dihitung per meter. Angka final
              dikonfirmasi setelah foto diperiksa atau lokasi disurvei.
            </p>
          </div>
        </div>
      </section>

      <button
        v-for="l in LAYANAN"
        :key="l.id"
        type="button"
        class="text-left bg-(--color-surface-0) rounded-2xl border-l-4 p-5 active:scale-[0.99] transition-transform shadow-(--shadow-lift)"
        :class="l.garis"
        @click="buka(l)"
      >
        <div class="flex items-center gap-2.5 mb-2.5">
          <Icon :name="l.ikon" class="w-5 h-5 text-(--color-azure) shrink-0" />
          <h3 class="text-[17px] font-display font-extrabold">{{ l.judul }}</h3>
        </div>

        <p class="text-[13px] leading-snug text-(--color-on-surface-variant) mb-3.5">
          {{ l.isi }}
        </p>

        <p class="text-[15px] font-extrabold text-(--color-azure)">{{ l.harga }}</p>
        <p
          v-if="l.id === 'pasang'"
          class="text-[11.5px] text-(--color-on-surface-variant) mb-3.5 mt-0.5"
        >
          Pindah AC selalu lewat survei lokasi — harganya menyusul.
        </p>
        <span v-else class="block mb-3.5"></span>

        <span
          class="w-full h-11 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold flex items-center justify-center gap-2"
        >
          {{ l.tombol }}
          <Icon name="arrow-right" class="w-4 h-4" />
        </span>
      </button>
    </main>
  </div>
</template>
