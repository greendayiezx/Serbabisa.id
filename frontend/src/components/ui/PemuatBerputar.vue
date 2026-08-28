<script setup lang="ts">
/**
 * Indikator berputar — dipakai saat BENTUK isi belum bisa ditebak.
 *
 * Aturan pemakaian di aplikasi ini:
 *
 * - Bentuk akhirnya diketahui dan berulang (daftar kartu, halaman detail yang
 *   susunannya tetap) → pakai SKELETON. Ia menahan ruang, jadi isinya tidak
 *   melompat saat datang.
 * - Bentuk akhirnya berbeda-beda menurut data (halaman tugas yang isinya
 *   berubah menurut status), atau yang ditunggu satu aksi singkat → pakai
 *   PEMUAT INI. Skeleton yang bentuknya salah lebih membingungkan daripada
 *   tidak ada skeleton sama sekali.
 *
 * `role="status"` membuat pembaca layar mengumumkan keadaannya sekali,
 * tanpa merebut fokus dari yang sedang dibaca pengguna.
 */
withDefaults(
  defineProps<{
    /** Kalimat yang dibacakan pembaca layar. */
    label?: string
    /** Sisi lingkaran dalam piksel. */
    ukuran?: number
  }>(),
  {
    label: 'Memuat…',
    ukuran: 34,
  },
)
</script>

<template>
  <div class="w-full flex flex-col items-center justify-center gap-3 py-14" role="status" aria-live="polite">
    <span
      class="putar rounded-full border-[3px] border-(--color-outline) border-t-(--color-azure)"
      :style="{ width: `${ukuran}px`, height: `${ukuran}px` }"
      aria-hidden="true"
    />
    <span class="text-[12.5px] text-(--color-on-surface-variant)">{{ label }}</span>
  </div>
</template>

<style scoped>
@keyframes berputar {
  100% {
    transform: rotate(360deg);
  }
}

.putar {
  animation: berputar 0.8s linear infinite;
}

/*
 * Tanpa animasi, cincin dengan satu sisi berwarna tampak seperti kesalahan
 * gambar. Yang tersisa dibuat menjadi lingkaran utuh yang diam.
 */
@media (prefers-reduced-motion: reduce) {
  .putar {
    animation: none;
    border-color: var(--color-azure);
  }
}
</style>
