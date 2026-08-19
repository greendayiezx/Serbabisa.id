<script setup lang="ts">
/**
 * Blok abu berkilau untuk placeholder saat konten belum siap.
 *
 * Kilaunya pakai background-position, bukan translate, supaya tidak memicu
 * layout ulang. Setelan "kurangi gerakan" dihormati: kilaunya berhenti, bloknya
 * tetap terlihat sebagai abu polos.
 */
withDefaults(
  defineProps<{
    /** Kelas tinggi/lebar Tailwind, mis. "h-4 w-24". */
    class?: string
    /** Bentuk bulat penuh, untuk placeholder avatar/ikon. */
    bulat?: boolean
  }>(),
  { class: '', bulat: false },
)
</script>

<template>
  <div
    class="skeleton bg-(--color-surface-container)"
    :class="[bulat ? 'rounded-full' : 'rounded-lg', $props.class]"
    aria-hidden="true"
  ></div>
</template>

<style scoped>
.skeleton {
  background-image: linear-gradient(
    90deg,
    transparent 0%,
    rgb(255 255 255 / 0.55) 50%,
    transparent 100%
  );
  background-size: 200% 100%;
  background-repeat: no-repeat;
  animation: skeleton-kilau 1.3s ease-in-out infinite;
}

@keyframes skeleton-kilau {
  from {
    background-position: -100% 0;
  }
  to {
    background-position: 100% 0;
  }
}

@media (prefers-reduced-motion: reduce) {
  .skeleton {
    animation: none;
    background-image: none;
  }
}
</style>
