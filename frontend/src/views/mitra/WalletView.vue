<script setup lang="ts">
import { onMounted } from 'vue'
import { useWalletStore } from '@/stores/wallet'
import AppLayout from '@/layouts/AppLayout.vue'
import Icon from '@/components/icons/Icon.vue'

const walletStore = useWalletStore()

onMounted(() => {
  walletStore.fetchWallet()
})
</script>

<template>
  <AppLayout>
    <div class="px-5 pt-5 pb-6">
      <h2 class="text-lg font-extrabold mb-4">Dompet Saya</h2>
      <div class="rounded-(--radius-card) bg-linear-to-br from-(--color-azure) to-[#0f7fce] text-white p-5">
        <p class="text-sm opacity-80 flex items-center gap-1.5"><Icon name="wallet" class="w-4 h-4" />Saldo</p>
        <p class="text-2xl font-extrabold mt-1.5">
          Rp{{ (walletStore.wallet?.saldo ?? 0).toLocaleString('id-ID') }}
        </p>
      </div>
    </div>
  </AppLayout>
</template>
