<script setup lang="ts">
import Icon from '@/components/icons/Icon.vue'
import { useKembali } from '@/composables/useKembali'

const goBackOrHome = useKembali()
</script>

<template>
  <nav class="fixed top-0 inset-x-0 z-50 bg-[#0B192C] text-white px-4 py-3 flex items-center justify-between shadow-md border-b border-slate-800">
    <div class="flex items-center gap-3">
      <button
        type="button"
        aria-label="Kembali"
        class="w-9 h-9 rounded-full bg-slate-800/80 text-white flex items-center justify-center hover:bg-slate-700 active:scale-95 transition-all"
        @click="goBackOrHome"
      >
        <Icon name="arrow-left" class="w-5 h-5 text-white" />
      </button>
      <div class="flex items-center gap-2">
        <svg class="w-7 h-7 text-[#38BDF8]" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M12 2L14.5 9.5L22 12L14.5 14.5L12 22L9.5 14.5L2 12L9.5 9.5L12 2Z" fill="currentColor"/>
          <path d="M19 1.5L20.2 5.2L24 6.5L20.2 7.8L19 11.5L17.8 7.8L14 6.5L17.8 5.2L19 1.5Z" fill="#8BC53F" opacity="0.9"/>
        </svg>
        <span class="font-extrabold text-lg tracking-tight text-white">BisaBersih</span>
      </div>
    </div>
  </nav>
</template>
