<script setup lang="ts">
/**
 * Ikon/logo satu metode pembayaran.
 *
 * Dipakai bersama oleh baris ringkasan (di atas total) dan sheet pemilih, supaya
 * logo di depan SELALU ikut berubah mengikuti metode yang dipilih — bukan ikon
 * tetap. Logo bank/e-wallet diimpor sekali di sini, tidak diulang di tiap halaman.
 */
import type { MetodeId } from '@/lib/metodeBayar'
import logoBni from '@/assets/logo_bni.svg'
import logoBri from '@/assets/logo_bri.svg'
import logoDana from '@/assets/logo_dana.svg'
import logoMandiri from '@/assets/logo_mandiri.svg'
import logoOvo from '@/assets/logo_ovo.svg'
import logoQris from '@/assets/logo_qris.svg'
import logoBca from '@/assets/LOGO-BCA.png'
import logoSpay from '@/assets/LOGO-SPAY.png'

defineProps<{ id: MetodeId }>()
</script>

<template>
  <span class="w-9 h-9 flex items-center justify-center shrink-0">
    <svg v-if="id === 'balance'" class="w-8 h-8" viewBox="0 0 32 32" fill="none">
      <rect width="32" height="32" rx="8" fill="#1E9BF0" />
      <rect x="6" y="10" width="20" height="13" rx="3" fill="#fff" />
      <rect x="6" y="10" width="20" height="4" rx="2" fill="#0B67B0" />
      <circle cx="21.5" cy="18.5" r="2.5" fill="#8BC53F" />
    </svg>
    <svg v-else-if="id === 'gopay'" class="w-8 h-8" viewBox="0 0 24 24" fill="none">
      <rect width="24" height="24" rx="12" fill="#00AED6" />
      <path fill-rule="evenodd" clip-rule="evenodd" d="M7 8C5.89543 8 5 8.89543 5 10V15C5 16.6569 6.34315 18 8 18H16C17.6569 18 19 16.6569 19 15V11C19 9.89543 18.1046 9 17 9H9.5C9.22386 9 9 8.77614 9 8.5C9 8.22386 9.22386 8 9.5 8H17C17.5523 8 18 7.55228 18 7C18 6.44772 17.5523 6 17 6H9C7.89543 6 7 6.89543 7 8ZM16 13C16.5523 13 17 12.5523 17 12C17 11.4477 16.5523 11 16 11C15.4477 11 15 11.4477 15 12C15 12.5523 15.4477 13 16 13Z" fill="#fff" />
    </svg>
    <img v-else-if="id === 'qris'" :src="logoQris" alt="QRIS" class="h-5 w-auto object-contain" />
    <img v-else-if="id === 'ovo'" :src="logoOvo" alt="OVO" class="h-6 w-auto object-contain" />
    <img v-else-if="id === 'shopeepay'" :src="logoSpay" alt="ShopeePay" class="h-9 w-auto object-contain scale-110" />
    <img v-else-if="id === 'dana'" :src="logoDana" alt="DANA" class="h-6 w-auto object-contain" />
    <img v-else-if="id === 'bca'" :src="logoBca" alt="BCA" class="h-6 w-auto object-contain" />
    <img v-else-if="id === 'bni'" :src="logoBni" alt="BNI" class="h-5 w-auto object-contain" />
    <img v-else-if="id === 'bri'" :src="logoBri" alt="BRI" class="h-6 w-auto object-contain" />
    <img v-else-if="id === 'mandiri'" :src="logoMandiri" alt="Mandiri" class="h-6 w-auto object-contain" />
    <svg v-else class="w-6 h-6 text-[#65b318]" viewBox="0 0 24 24" fill="none">
      <rect x="2" y="6" width="20" height="12" rx="2.5" stroke="currentColor" stroke-width="2" />
      <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
      <path d="M6 10v4M18 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
    </svg>
  </span>
</template>
