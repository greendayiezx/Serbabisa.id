<script setup lang="ts">
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'
import Icon from '@/components/icons/Icon.vue'

const auth = useAuthStore()
const router = useRouter()

const roleLabel: Record<string, string> = { customer: 'Customer', mitra: 'Mitra', admin: 'Admin' }

function initial(name?: string) {
  return (name ?? '?').trim().charAt(0).toUpperCase()
}

async function handleLogout() {
  await auth.logout()
  router.push({ name: 'login' })
}
</script>

<template>
  <AppLayout>
    <div class="relative overflow-hidden bg-linear-to-br from-(--color-azure) to-[#0f7fce] px-5 pt-4.5 pb-11.5">
      <div class="blob w-30 h-30 -top-7.5 -right-7.5 bg-(--color-lime) opacity-30"></div>
      <h3 class="relative z-10 text-white font-extrabold text-base">Profil Saya</h3>
    </div>

    <div class="relative z-10 -mt-8.5 mx-4 rounded-(--radius-card) bg-(--color-surface-0) shadow-(--shadow-float) px-4.5 py-4.5 text-center">
      <div class="w-16.5 h-16.5 rounded-full mx-auto mb-2.5 flex items-center justify-center font-display font-extrabold text-white text-[22px] bg-linear-to-br from-(--color-azure) to-[#0f7fce]">
        {{ initial(auth.user?.name) }}
      </div>
      <h3 class="text-[17px] font-extrabold">{{ auth.user?.name }}</h3>
      <div class="flex justify-center gap-1.5 mt-1.5">
        <span class="rounded-full text-xs font-bold px-3 py-1.5 bg-(--color-primary-container) text-(--color-on-primary-container)">
          {{ roleLabel[auth.user?.role ?? 'customer'] }}
        </span>
        <span
          v-if="auth.user?.status_verifikasi === 'verified'"
          class="inline-flex items-center gap-1 rounded-full text-xs font-bold px-3 py-1.5 bg-(--color-lime) text-[#33430b]"
        >
          <Icon name="shield" class="w-3 h-3" />Terverifikasi
        </span>
      </div>

      <div class="flex mt-4 border-t border-(--color-outline) pt-3.5">
        <div class="flex-1">
          <b class="block font-display text-[17px] font-extrabold">—</b>
          <span class="block text-[10.5px] text-(--color-on-surface-variant) font-semibold">Tugas Selesai</span>
        </div>
        <div class="flex-1">
          <b class="block font-display text-[17px] font-extrabold">{{ auth.user?.email ? new Date().getFullYear() : '—' }}</b>
          <span class="block text-[10.5px] text-(--color-on-surface-variant) font-semibold">Member Sejak</span>
        </div>
        <div class="flex-1">
          <b class="block font-display text-[17px] font-extrabold">—</b>
          <span class="block text-[10.5px] text-(--color-on-surface-variant) font-semibold">Rating Saya</span>
        </div>
      </div>
    </div>

    <div class="px-5 pt-6 pb-2">
      <h4 class="font-extrabold text-[15.5px] mb-3">Ulasan</h4>
      <p class="text-sm text-(--color-on-surface-variant)">Belum ada ulasan yang masuk.</p>
    </div>

    <ul class="mt-4">
      <li class="flex items-center gap-3 px-5 py-3.5 border-b border-(--color-outline) text-[13.5px] font-bold">
        <Icon name="user" class="w-[18px] h-[18px] text-(--color-on-primary-container)" />Edit Profil
        <Icon name="chevron-right" class="w-4 h-4 ml-auto text-(--color-on-surface-variant)" />
      </li>
      <li class="flex items-center gap-3 px-5 py-3.5 border-b border-(--color-outline) text-[13.5px] font-bold">
        <Icon name="pin" class="w-[18px] h-[18px] text-(--color-on-primary-container)" />Alamat Tersimpan
        <Icon name="chevron-right" class="w-4 h-4 ml-auto text-(--color-on-surface-variant)" />
      </li>
      <li class="flex items-center gap-3 px-5 py-3.5 border-b border-(--color-outline) text-[13.5px] font-bold">
        <Icon name="card" class="w-[18px] h-[18px] text-(--color-on-primary-container)" />Metode Pembayaran
        <Icon name="chevron-right" class="w-4 h-4 ml-auto text-(--color-on-surface-variant)" />
      </li>
      <li class="flex items-center gap-3 px-5 py-3.5 border-b border-(--color-outline) text-[13.5px] font-bold">
        <Icon name="help" class="w-[18px] h-[18px] text-(--color-on-primary-container)" />Pusat Bantuan
        <Icon name="chevron-right" class="w-4 h-4 ml-auto text-(--color-on-surface-variant)" />
      </li>
      <li class="flex items-center gap-3 px-5 py-3.5 text-[13.5px] font-bold text-(--color-error) cursor-pointer" @click="handleLogout">
        <Icon name="logout" class="w-[18px] h-[18px]" />Keluar
      </li>
    </ul>
  </AppLayout>
</template>
