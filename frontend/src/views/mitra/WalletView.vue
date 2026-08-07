<script setup lang="ts">
import { onMounted } from 'vue'
import { useWalletStore } from '@/stores/wallet'
import AppLayout from '@/layouts/AppLayout.vue'

const walletStore = useWalletStore()

onMounted(() => {
  walletStore.fetchWallet()
})
</script>

<template>
  <AppLayout>
    <h2 class="text-lg font-semibold mb-3">Dompet Saya</h2>
    <div class="rounded-xl bg-brand text-white p-5">
      <p class="text-sm opacity-80">Saldo</p>
      <p class="text-2xl font-semibold mt-1">
        Rp {{ (walletStore.wallet?.saldo ?? 0).toLocaleString('id-ID') }}
      </p>
    </div>
  </AppLayout>
</template>
