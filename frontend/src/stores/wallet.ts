import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'
import type { Wallet } from '@/types'

export const useWalletStore = defineStore('wallet', () => {
  const wallet = ref<Wallet | null>(null)

  async function fetchWallet() {
    const { data } = await apiClient.get<Wallet>('/wallet')
    wallet.value = data
    return data
  }

  async function requestPayout(jumlah: number, bankTujuan: string, noRekening: string) {
    return apiClient.post('/payout-requests', {
      jumlah,
      bank_tujuan: bankTujuan,
      no_rekening: noRekening,
    })
  }

  return { wallet, fetchWallet, requestPayout }
})
