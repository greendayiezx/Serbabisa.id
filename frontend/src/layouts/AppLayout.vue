<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const route = useRoute()
const auth = useAuthStore()

const navItems = computed(() => {
  if (auth.user?.role === 'mitra') {
    return [
      { to: { name: 'mitra-task-list' }, label: 'Tugas' },
      { to: { name: 'wallet' }, label: 'Dompet' },
      { to: { name: 'home' }, label: 'Akun' },
    ]
  }
  if (auth.user?.role === 'admin') {
    return [{ to: { name: 'admin-dashboard' }, label: 'Dashboard' }]
  }
  return [
    { to: { name: 'home' }, label: 'Beranda' },
    { to: { name: 'task-create' }, label: 'Buat Tugas' },
  ]
})
</script>

<template>
  <div class="min-h-screen flex flex-col pb-16 md:pb-0">
    <header class="bg-brand text-white px-4 py-3 sticky top-0 z-10">
      <h1 class="text-lg font-semibold">Tugasin</h1>
    </header>

    <main class="flex-1 max-w-screen-md mx-auto w-full p-4">
      <slot />
    </main>

    <nav
      v-if="auth.user"
      class="fixed bottom-0 inset-x-0 bg-white border-t border-gray-200 flex md:hidden"
    >
      <RouterLink
        v-for="item in navItems"
        :key="item.label"
        :to="item.to"
        class="flex-1 text-center py-3 text-sm"
        :class="route.name === item.to.name ? 'text-brand font-medium' : 'text-gray-500'"
      >
        {{ item.label }}
      </RouterLink>
    </nav>
  </div>
</template>
