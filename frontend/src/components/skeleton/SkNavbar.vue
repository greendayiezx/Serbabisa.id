<script setup lang="ts">
/** Bar atas: tombol kembali bulat + judul. Tinggi 64px, sama dengan header asli. */
import Skeleton from '@/components/ui/Skeleton.vue'
withDefaults(defineProps<{ gelap?: boolean }>(), { gelap: false })
</script>

<template>
  <div
    class="h-16 px-4 flex items-center gap-3"
    :class="gelap ? 'bg-[#0B192C]' : 'bg-(--color-surface-0)'"
  >
    <Skeleton class="w-9 h-9" bulat />
    <Skeleton class="h-4 w-36" />
  </div>
</template>
