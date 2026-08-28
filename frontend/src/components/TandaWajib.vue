<script setup lang="ts">
/**
 * Bintang merah penanda isian wajib.
 *
 * Bintangnya sendiri disembunyikan dari pembaca layar dan digantikan teks
 * "wajib diisi" — "asterisk" dibacakan apa adanya dan tidak berarti apa-apa
 * bagi yang tidak melihat warnanya.
 */
</script>

<template>
  <span class="text-(--color-error) font-bold" aria-hidden="true">*</span>
  <span class="sr-only">(wajib diisi)</span>
</template>
