<script setup lang="ts">
/**
 * Daftar lengkap tukang bersih (mode "Pilih Sendiri").
 *
 * Dibuka dari BersihRumahView lewat "Lihat Semua". Pilihan disimpan di store
 * (bertahan lewat localStorage) supaya tetap terbawa saat kembali ke halaman
 * pemesanan — komponen pemesanan di-unmount saat navigasi.
 *
 * Semua yang tampil di sini adalah data nyata dari server: nama dari akun
 * mitra, level DIHITUNG dari ulasan yang benar-benar diterima, jumlah order
 * dari tugas yang selesai. Tidak ada nama contoh — kalau belum ada mitra
 * terdaftar, halaman ini mengatakannya apa adanya.
 */
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import CleanerAvatar from '@/components/bersih/CleanerAvatar.vue'
import { useCleanerBersihStore } from '@/stores/cleanerBersih'
import type { CleanerServer } from '@/api/cleaner'

const router = useRouter()
const cleanerStore = useCleanerBersihStore()

onMounted(() => {
  void cleanerStore.muat()
})

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}

/** Angka order yang enak dibaca: 1200 -> "1.200". */
function angka(n: number) {
  return n.toLocaleString('id-ID')
}

/**
 * Tampilan angka performa cleaner.
 *
 * Selama `pembanding` masih nol (belum ada ulasan / belum ada order), yang
 * tampil adalah "-" alih-alih angka nol — nol di sini berarti "belum dinilai",
 * bukan "nilainya jelek".
 */
function nilai(n: number, pembanding = n) {
  return pembanding > 0 ? angka(n) : '-'
}

/**
 * Warna badge jenjang. Level tertinggi paling menonjol; yang terendah kalem —
 * cleaner baru bukan cleaner buruk, hanya belum punya rekam jejak.
 */
function warnaLevel(c: CleanerServer) {
  if (c.jumlah_ulasan === 0) return 'text-(--color-on-surface-variant)'
  if (c.level >= 4) return 'text-(--color-gold)'
  if (c.level === 3) return 'text-(--color-azure)'
  return 'text-(--color-on-surface-variant)'
}

function kembali() {
  router.replace({ name: 'task-bersih-rumah' })
}

function pilih(c: CleanerServer) {
  cleanerStore.set(c.id)
  router.replace({ name: 'task-bersih-rumah' })
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-10">
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
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Pilih Tukang Bersih</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-4">
      <!-- Sedang menarik data -->
      <p
        v-if="cleanerStore.memuat"
        class="rounded-2xl p-4 bg-(--color-surface-0) text-[12.5px] text-(--color-on-surface-variant)"
      >
        Memuat daftar cleaner&hellip;
      </p>

      <!-- Belum ada mitra sama sekali -->
      <div
        v-else-if="!cleanerStore.daftar.length"
        class="rounded-2xl p-5 bg-(--color-surface-0) flex flex-col items-center text-center gap-2"
      >
        <Icon name="info" class="w-8 h-8 text-(--color-on-surface-variant)" />
        <h2 class="text-[14px] font-extrabold">Belum ada cleaner terdaftar</h2>
        <p class="text-[12.5px] text-(--color-on-surface-variant) leading-snug">
          Mitra di area kamu belum ada yang bisa dipilih sendiri. Kamu tetap bisa memesan lewat
          <strong>Cleaner Tercepat</strong> — kami carikan begitu ada yang tersedia.
        </p>
        <button
          type="button"
          class="mt-1 rounded-full px-6 py-2.5 text-[13px] font-bold bg-(--color-azure) text-white active:scale-95 transition-transform"
          @click="kembali"
        >
          Kembali ke pemesanan
        </button>
      </div>

      <!-- Daftar cleaner -->
      <article
        v-for="c in cleanerStore.daftar"
        :key="c.id"
        class="rounded-2xl p-4 bg-(--color-surface-0) border-2 transition-colors"
        :class="cleanerStore.dipilih === c.id ? 'border-(--color-azure)' : 'border-(--color-outline)/20'"
      >
        <div class="flex items-start gap-3.5">
          <CleanerAvatar :gender="c.gender ?? undefined" :nama="c.nama" class="w-14 h-14 shrink-0" />

          <div class="flex-1 min-w-0">
            <h2 class="text-[15px] font-extrabold truncate">{{ c.nama }}</h2>

<!-- Bintang, order, dan level ditandai "-" selama belum ada ulasan. -->
            <div class="flex items-center gap-2 mt-0.5 text-[12px] text-(--color-on-surface-variant)">
              <span class="flex items-center gap-0.5">
                <Icon name="star" class="w-3.5 h-3.5 text-(--color-gold)" />
                <span class="font-bold text-(--color-on-surface)">
                  {{ nilai(c.rating, c.jumlah_ulasan) }}
                </span>
                <span v-if="c.jumlah_ulasan > 0">({{ angka(c.jumlah_ulasan) }})</span>
              </span>
              <span class="w-1 h-1 rounded-full bg-(--color-outline)/50"></span>
              <span>{{ nilai(c.order_selesai) }} order</span>
            </div>

            <div class="mt-1 flex items-center gap-1 text-[11.5px] font-bold" :class="warnaLevel(c)">
              <Icon name="sparkle" class="w-3.5 h-3.5" />
              <span v-if="c.jumlah_ulasan > 0">Level {{ c.level }} &middot; {{ c.nama_level }}</span>
              <span v-else>Level -</span>
            </div>
          </div>
        </div>

        <!-- Harga + aksi -->
        <div class="flex items-center justify-between mt-3.5 pt-3.5 border-t border-(--color-outline)/20">
          <div class="flex flex-col">
            <span class="text-[11px] text-(--color-on-surface-variant)">Tarif</span>
            <span class="text-[15px] font-extrabold">
              {{ rp(c.harga_per_jam) }}<span class="text-[12px] font-normal text-(--color-on-surface-variant)">/jam</span>
            </span>
          </div>
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-full px-6 py-2.5 text-[13px] font-bold active:scale-95 transition-all"
            :class="
              cleanerStore.dipilih === c.id
                ? 'bg-(--color-lime) text-[#33430b]'
                : 'bg-(--color-azure) text-white'
            "
            @click="pilih(c)"
          >
            <Icon v-if="cleanerStore.dipilih === c.id" name="check" class="w-4 h-4" />
            {{ cleanerStore.dipilih === c.id ? 'Dipilih' : 'Pilih' }}
          </button>
        </div>
      </article>

    </main>
  </div>
</template>
