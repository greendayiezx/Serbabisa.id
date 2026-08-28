<script setup lang="ts">
import { computed } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import Icon from '@/components/icons/Icon.vue'

const route = useRoute()
const auth = useAuthStore()

/** Sembunyikan nav bawah untuk layar yang butuh fokus penuh (mis. chat). */
const props = defineProps<{ hideNav?: boolean }>()

const navItems = computed(() => {
  if (auth.user?.role === 'mitra') {
    return [
      { to: { name: 'mitra-task-list' }, label: 'Tugas', icon: 'clipboard' },
      { to: { name: 'wallet' }, label: 'Dompet', icon: 'wallet' },
      { to: { name: 'profile' }, label: 'Profil', icon: 'user' },
    ]
  }
  return [
    { to: { name: 'home' }, label: 'Beranda', icon: 'home' },
    { to: { name: 'task-list' }, label: 'Tugas Saya', icon: 'clipboard' },
    { to: { name: 'profile' }, label: 'Profil', icon: 'user' },
  ]
})
</script>

<template>
  <div class="min-h-screen flex flex-col bg-(--color-surface) text-(--color-on-surface)">
    <main class="flex-1 max-w-screen-md mx-auto w-full" :class="hideNav ? 'pb-0' : 'pb-24 md:pb-0'">
      <slot />
    </main>

    <nav
      v-if="auth.user && !props.hideNav"
      class="fixed bottom-0 inset-x-0 bg-(--color-surface-0) border-t border-(--color-outline) flex md:hidden pb-[env(safe-area-inset-bottom)]"
    >
      <RouterLink
        v-for="item in navItems"
        :key="item.label"
        :to="item.to"
        class="flex-1 flex flex-col items-center gap-1 py-2.5 text-[10.5px] font-bold"
        :class="route.name === item.to.name ? 'text-(--color-azure)' : 'text-(--color-on-surface-variant)'"
      >
        <Icon :name="item.icon" class="w-[21px] h-[21px]" />
        {{ item.label }}
      </RouterLink>
    </nav>
  </div>
</template>
