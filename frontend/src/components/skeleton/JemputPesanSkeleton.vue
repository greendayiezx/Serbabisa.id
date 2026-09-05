<script setup lang="ts">
/**
 * Pemesanan BisaJemput: peta rute di atas, kartu alamat melayang, lalu lembar
 * pilihan kendaraan dan bilah pembayaran di bawah.
 */
import Skeleton from '@/components/ui/Skeleton.vue'
</script>

<template>
  <div class="relative min-h-dvh w-full bg-(--color-surface-container) pb-4">
    <!-- Peta rute -->
    <Skeleton class="absolute inset-x-0 top-0 h-[52vh] rounded-none" />

    <!-- Kartu titik jemput & tujuan -->
    <div class="relative z-10 max-w-[430px] mx-auto px-4 pt-4">
      <div class="rounded-2xl bg-(--color-surface-0) shadow-lg p-3.5">
        <div class="flex items-center gap-2.5">
          <Skeleton class="w-[22px] h-[22px]" bulat />
          <Skeleton class="h-3 flex-1" />
        </div>
        <div class="my-2 border-t border-(--color-outline)/15"></div>
        <div class="flex items-center gap-2.5">
          <Skeleton class="w-[22px] h-[22px]" bulat />
          <Skeleton class="h-3 w-3/5" />
        </div>
      </div>
    </div>

    <!-- Lembar pilihan kendaraan -->
    <section
      class="relative z-10 mt-[34vh] bg-(--color-surface-0) rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.16)] min-h-[46vh]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-4 pb-40">
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>

        <!-- Tab kelas -->
        <div class="flex items-center gap-6 border-b border-(--color-outline)/15 pb-2.5 mb-4">
          <Skeleton class="h-3.5 w-14" />
          <Skeleton class="h-3.5 w-14" />
        </div>

        <Skeleton class="h-2.5 w-40 mb-3" />

        <!-- Dua kartu kendaraan, masing-masing dua baris varian -->
        <div class="flex flex-col gap-2.5">
          <div
            v-for="i in 2"
            :key="i"
            class="rounded-2xl border-2 border-(--color-outline)/20 overflow-hidden"
          >
            <div class="px-4 pt-3.5 pb-2 flex items-start gap-3">
              <Skeleton class="w-10 h-10 rounded-xl" />
              <div class="flex-1 flex flex-col gap-1.5 pt-0.5">
                <Skeleton class="h-3.5 w-2/5" />
                <Skeleton class="h-2.5 w-3/5" />
              </div>
            </div>
            <div
              v-for="j in 2"
              :key="j"
              class="px-4 py-3 flex items-center gap-3 border-t border-(--color-outline)/12"
            >
              <div class="flex-1 flex flex-col gap-1.5">
                <div class="flex items-center gap-2">
                  <Skeleton class="h-4 w-16 rounded-md" />
                  <Skeleton class="h-4 w-20 rounded-full" />
                </div>
                <Skeleton class="h-2.5 w-1/2" />
              </div>
              <Skeleton class="h-4 w-16" />
              <Skeleton class="w-5 h-5" bulat />
            </div>
          </div>
        </div>
      </div>
    </section>

    <!-- Bilah bawah: metode bayar, promo, tombol pesan -->
    <footer class="fixed bottom-0 inset-x-0 z-20 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.10)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center gap-2 mb-3">
          <Skeleton class="w-5 h-5" bulat />
          <Skeleton class="h-3.5 w-20" />
          <Skeleton class="ml-auto h-9 w-28 rounded-full" />
          <Skeleton class="w-9 h-9" bulat />
        </div>
        <Skeleton class="h-12 w-full rounded-full" />
      </div>
    </footer>
  </div>
</template>
