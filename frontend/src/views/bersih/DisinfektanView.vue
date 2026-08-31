<script setup lang="ts">
/**
 * Disinfektan — halaman masuk layanan.
 *
 * Halaman ini menjelaskan sebelum menjual, dan urutannya disengaja: yang
 * pertama dibaca adalah BATAS layanannya, bukan harganya.
 *
 * Disinfeksi permukaan BUKAN sterilisasi. Ia mengurangi mikroba di permukaan
 * yang sering disentuh; ia tidak membuat ruangan bebas penyakit, dan hasilnya
 * bergantung kondisi permukaan, jenis produk, konsentrasi, serta waktu kontak.
 * Bagian "Tidak termasuk" diberi bobot yang sama dengan "Termasuk" — yang tidak
 * disebut akan dianggap termasuk, dan selisihnya berubah jadi perselisihan di
 * lokasi.
 */
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { rupiah } from '@/lib/rupiah'
import {
  APD_PETUGAS,
  AREA,
  DASAR,
  LANGKAH,
  MULAI_DARI,
  PROPERTI,
  SEBELUM_PETUGAS_DATANG,
  TIDAK_TERMASUK,
  golongan,
} from '@/lib/bersih/disinfektan'

const router = useRouter()
const kembali = useKembali()

const properti = ref('rumah')

const areaTampil = computed(() => AREA[golongan(properti.value)])
const mulaiDari = computed(() => DASAR[golongan(properti.value)]['50-100'])
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Disinfektan</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <section>
        <h2 class="text-[19px] font-display font-extrabold leading-tight mb-1.5">
          Bantu jaga kebersihan area yang sering disentuh
        </h2>
        <p class="text-[13px] leading-snug text-(--color-on-surface-variant)">
          Pembersihan permukaan lebih dulu, lalu aplikasi disinfektan pada titik yang paling
          sering tersentuh.
        </p>
      </section>

      <!--
        Ditaruh paling atas, sebelum harga. Kalau kalimat ini berada di bawah
        tombol pesan, ia hanya dibaca oleh orang yang sudah terlanjur memesan.
      -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5 flex gap-3">
        <Icon name="info" class="w-5 h-5 shrink-0 mt-0.5 text-(--color-azure)" />
        <div>
          <h3 class="text-[14px] font-display font-extrabold mb-1.5">Yang perlu diketahui</h3>
          <p class="text-[12.5px] leading-relaxed text-(--color-on-surface-variant)">
            Disinfeksi permukaan <strong class="text-(--color-on-surface)">bukan sterilisasi</strong>.
            Layanan ini mengurangi mikroba di permukaan yang sering disentuh, bukan menjamin
            ruangan bebas virus. Hasilnya dipengaruhi kondisi permukaan, jenis produk,
            konsentrasi, dan waktu kontak.
          </p>
        </div>
      </section>

      <!-- Jenis lokasi -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Pilih jenis lokasi</h3>
        <div class="flex flex-wrap gap-2">
          <button
            v-for="p in PROPERTI"
            :key="p.id"
            type="button"
            class="px-4 py-2 rounded-full border text-[12.5px] font-semibold transition-colors"
            :class="
              properti === p.id
                ? 'bg-(--color-azure) border-(--color-azure) text-white'
                : 'border-(--color-outline)/50 text-(--color-on-surface-variant)'
            "
            :aria-pressed="properti === p.id"
            @click="properti = p.id"
          >
            {{ p.nama }}
          </button>
        </div>
      </section>

      <!-- Termasuk -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3
          class="text-[14px] font-display font-extrabold mb-3 flex items-center gap-2 text-(--color-on-secondary-container)"
        >
          <Icon name="check-circle" class="w-5 h-5" />
          Area yang ditangani
        </h3>
        <ul class="flex flex-col gap-2">
          <li
            v-for="a in areaTampil"
            :key="a"
            class="flex items-start gap-2 text-[12.5px] leading-snug"
          >
            <Icon name="check" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-on-secondary-container)" />
            {{ a }}
          </li>
        </ul>
      </section>

      <!-- Tidak termasuk -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5 border border-(--color-error)/25">
        <h3
          class="text-[14px] font-display font-extrabold mb-3 flex items-center gap-2 text-(--color-error)"
        >
          <Icon name="alert" class="w-5 h-5" />
          Tidak termasuk
        </h3>
        <ul class="flex flex-col gap-2">
          <li
            v-for="t in TIDAK_TERMASUK"
            :key="t"
            class="flex items-start gap-2 text-[12.5px] leading-snug"
          >
            <Icon name="x" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-error)" />
            {{ t }}
          </li>
        </ul>

        <p class="mt-3 pt-3 border-t border-(--color-outline)/15 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)">
          Kalau ada darah, cairan tubuh, limbah medis, atau dugaan penyakit menular berisiko
          tinggi, hubungi penyedia jasa dekontaminasi khusus — bukan layanan ini.
        </p>
      </section>

      <!-- Cara kerja -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3.5">Cara kerjanya</h3>
        <ol class="flex flex-col gap-3">
          <li v-for="(l, i) in LANGKAH" :key="l" class="flex items-start gap-3">
            <span
              class="w-6 h-6 rounded-full bg-(--color-surface-container) text-[11px] font-extrabold flex items-center justify-center shrink-0"
            >
              {{ i + 1 }}
            </span>
            <span class="text-[12.5px] leading-snug pt-0.5">{{ l }}</span>
          </li>
        </ol>

        <!--
          Tidak ada satu angka waktu kontak di layar ini, dan itu disengaja:
          tiap produk punya labelnya sendiri. Menyebut satu angka berarti
          menjanjikan prosedur yang belum tentu benar untuk produk yang dipakai.
        -->
        <p class="mt-4 rounded-xl bg-(--color-surface-container) p-3.5 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)">
          Produk, konsentrasi, dan waktu kontaknya mengikuti label masing-masing produk beserta
          lembar keselamatannya. Petugas mencatat produk yang dipakai di laporan setelah
          pekerjaan selesai.
        </p>
      </section>

      <!-- Keselamatan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Sebelum petugas datang</h3>
        <ul class="flex flex-col gap-2">
          <li
            v-for="s in SEBELUM_PETUGAS_DATANG"
            :key="s"
            class="flex items-start gap-2 text-[12.5px] leading-snug text-(--color-on-surface-variant)"
          >
            <Icon name="check" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-azure)" />
            {{ s }}
          </li>
        </ul>

        <div class="mt-4 pt-4 border-t border-(--color-outline)/15">
          <h4 class="text-[12.5px] font-bold mb-2">Perlengkapan petugas</h4>
          <p class="text-[12px] leading-snug text-(--color-on-surface-variant)">
            {{ APD_PETUGAS.join(' · ') }}
          </p>
        </div>
      </section>
    </main>

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between gap-3 mb-3">
          <span class="text-[12.5px] text-(--color-on-surface-variant)">Mulai dari</span>
          <span class="text-[17px] font-extrabold">{{ rupiah(mulaiDari) }}</span>
        </div>

        <div class="flex items-center gap-2.5">
          <button
            type="button"
            class="shrink-0 px-4 h-12 rounded-full border-[1.5px] border-(--color-outline)/50 text-[12.5px] font-extrabold active:scale-95 transition-transform"
            @click="router.push({ name: 'task-bersih-disinfektan-penawaran' })"
          >
            Minta Penawaran
          </button>

          <button
            type="button"
            class="flex-1 h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
            @click="router.push({ name: 'task-bersih-disinfektan-pesan', query: { properti } })"
          >
            Pesan Sekarang
            <Icon name="arrow-right" class="w-4 h-4" />
          </button>
        </div>

        <p class="mt-2 text-[11px] leading-snug text-(--color-on-surface-variant)">
          Mulai {{ rupiah(MULAI_DARI) }} untuk hunian 50–100 m². Area di atas 300 m² lewat
          penawaran.
        </p>
      </div>
    </footer>
  </div>
</template>
