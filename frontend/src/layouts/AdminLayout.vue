<script setup lang="ts">
import { useAuthStore } from '@/stores/auth'
import Icon from '@/components/icons/Icon.vue'

const auth = useAuthStore()

const navItems = [
  { to: { name: 'admin-dashboard' }, label: 'Dashboard', icon: 'gauge' },
  { to: { name: 'admin-dashboard' }, label: 'Pengguna', icon: 'users' },
  { to: { name: 'admin-dashboard' }, label: 'Transaksi', icon: 'receipt' },
  { to: { name: 'admin-dashboard' }, label: 'Kategori', icon: 'grid' },
  { to: { name: 'admin-dashboard' }, label: 'Dispute', icon: 'alert' },
]

function initial(name?: string) {
  return (name ?? 'A').trim().charAt(0).toUpperCase()
}
</script>

<template>
  <div class="min-h-screen flex bg-(--color-surface) text-(--color-on-surface)">
    <aside class="w-56 shrink-0 bg-(--color-surface-container) border-r border-(--color-outline) p-3.5 hidden md:flex md:flex-col">
      <div class="flex items-center gap-2 font-display font-extrabold text-base px-2.5 pt-1.5 pb-5.5">
        <span class="w-2.5 h-2.5 rounded-full bg-(--color-lime)"></span>Tugasin
      </div>
      <nav class="flex flex-col gap-0.5">
        <RouterLink
          v-for="(item, i) in navItems"
          :key="item.label"
          :to="item.to"
          class="flex items-center gap-2.5 px-3 py-2.5 rounded-xl text-[13.5px] font-bold"
          :class="i === 0 ? 'bg-(--color-primary-container) text-(--color-on-primary-container)' : 'text-(--color-on-surface-variant)'"
        >
          <Icon :name="item.icon" class="w-[18px] h-[18px]" />{{ item.label }}
        </RouterLink>
      </nav>
      <div class="mt-auto flex items-center gap-2.5 pt-2.5 border-t border-(--color-outline) px-1">
        <div class="w-8.5 h-8.5 rounded-full flex items-center justify-center font-display font-extrabold text-white text-xs bg-linear-to-br from-(--color-azure) to-[#0f7fce]">
          {{ initial(auth.user?.name) }}
        </div>
        <div>
          <div class="text-[12.5px] font-bold">{{ auth.user?.name }}</div>
          <div class="text-[10.5px] text-(--color-on-surface-variant)">Administrator</div>
        </div>
      </div>
    </aside>

    <div class="flex-1 min-w-0 flex flex-col">
      <div class="flex items-center gap-3.5 px-5 md:px-6.5 py-3.5 border-b border-(--color-outline)">
        <div class="font-display font-extrabold text-base md:hidden flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-(--color-lime)"></span>Tugasin
        </div>
        <div class="hidden md:flex flex-1 max-w-105 items-center gap-2 bg-(--color-surface-container) rounded-xl px-3.5 py-2.5">
          <Icon name="search" class="w-4 h-4 text-(--color-on-surface-variant)" />
          <input placeholder="Cari user, transaksi, atau ID tugas..." class="w-full bg-transparent border-none outline-none text-[13px]" />
        </div>
        <div class="ml-auto flex items-center gap-2.5">
          <Icon name="bell" class="w-[19px] h-[19px] text-(--color-on-surface-variant)" />
          <div class="w-8.5 h-8.5 rounded-full flex items-center justify-center font-display font-extrabold text-white text-xs bg-linear-to-br from-(--color-azure) to-[#0f7fce]">
            {{ initial(auth.user?.name) }}
          </div>
        </div>
      </div>

      <main class="flex-1 p-5 md:p-6.5 overflow-x-auto">
        <slot />
      </main>
    </div>
  </div>
</template>
