<script setup lang="ts">
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'

defineProps<{
  step?: number
}>()

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
  <nav class="bg-[#0B192C] text-white px-4 py-3 flex items-center justify-between sticky top-0 z-50 shadow-md border-b border-slate-800">
    <div class="flex items-center gap-3">
      <button
        type="button"
        class="w-9 h-9 rounded-full bg-slate-800/80 text-white flex items-center justify-center hover:bg-slate-700 active:scale-95 transition-all"
        @click="goBackOrHome"
      >
        <Icon name="arrow-left" class="w-5 h-5 text-white" />
      </button>
      <div class="flex items-center gap-2">
        <svg
          xmlns="http://www.w3.org/2000/svg"
          viewBox="0 0 64 64"
          class="w-8 h-8 shrink-0"
        >
          <defs>
            <linearGradient id="blueLogo" x1="0" y1="0" x2="1" y2="1">
              <stop offset="0%" stop-color="#0A63E8"/>
              <stop offset="100%" stop-color="#0644B5"/>
            </linearGradient>
          </defs>

          <!-- SHOPPING BAG -->
          <path
            d="M11 19
               H53
               L49 57
               H15
               Z"
            fill="#0A326B"/>

          <!-- BAG TOP -->
          <path
            d="M11 19H53L52 25H12Z"
            fill="#8BC53F"/>

          <!-- BAG HANDLE -->
          <path
            d="M20 19
               C20 10 25 6 32 6
               C39 6 44 10 44 19"
            fill="none"
            stroke="#8BC53F"
            stroke-width="4"
            stroke-linecap="round"/>

          <!-- SERBAGUNA LOGO CENTER -->
          <g transform="translate(20 28) scale(.82)">
            <!-- BLUE S -->
            <path
              d="M4 7
                 C4 3 8 0 13 0
                 H22
                 L27 5
                 H14
                 C11 5 9 6 9 8
                 C9 10 11 11 14 12
                 L20 14
                 C25 16 28 19 28 23
                 C28 28 24 31 19 32
                 H13
                 L8 27
                 H19
                 C22 27 23 26 23 24
                 C23 22 21 21 18 20
                 L11 18
                 C6 16 4 12 4 7Z"
              fill="url(#blueLogo)"/>

            <!-- SHORT LIME ACCENT -->
            <path
              d="M8 24
                 C9 27 12 28 15 28
                 H19
                 L23 24
                 L27 27
                 L21 32
                 H14
                 C10 31 7 29 6 26
                 Z"
              fill="#8BC53F"/>
          </g>
        </svg>
        <span class="font-extrabold text-lg tracking-tight text-white">Bisa Belanja</span>
      </div>
    </div>
  </nav>
</template>
