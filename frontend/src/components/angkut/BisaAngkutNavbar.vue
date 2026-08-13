<script setup lang="ts">
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'

const router = useRouter()

function goBackOrHome() {
  if (window.history.state?.back) {
    router.back()
  } else {
    router.push({ name: 'home' })
  }
}
</script>

<template>
  <nav class="bg-[#0B192C] text-white px-4 py-3 flex items-center justify-between sticky top-0 z-30 shadow-md border-b border-slate-800">
    <div class="flex items-center gap-3">
      <button
        type="button"
        class="w-9 h-9 rounded-full bg-slate-800/80 text-white flex items-center justify-center hover:bg-slate-700 active:scale-95 transition-all"
        @click="goBackOrHome"
      >
        <Icon name="arrow-left" class="w-5 h-5 text-white" />
      </button>
      <div class="flex items-center gap-2">
        <svg class="w-7 h-7 text-[#38BDF8]" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
          <path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <path d="M15 8h4.5a1.5 1.5 0 0 1 1.2.6l2.3 3.1a1.5 1.5 0 0 1 .3.9v3.4a1 1 0 0 1-1 1h-2" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          <circle cx="7" cy="18" r="2.5" stroke="currentColor" stroke-width="2" fill="#0B192C"/>
          <circle cx="17" cy="18" r="2.5" stroke="currentColor" stroke-width="2" fill="#0B192C"/>
        </svg>
        <span class="font-extrabold text-lg tracking-tight text-white">BisaAngkut</span>
      </div>
    </div>
  </nav>
</template>
