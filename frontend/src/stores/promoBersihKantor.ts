import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { cariVoucherKantor, semuaVoucherKantor, type VoucherKantor } from '@/lib/promo/promoBersihKantor'

const STORAGE_KEY = 'tugasin_promo_bersih_kantor'

/**
 * Promo BisaBersih Kantor yang sedang dipilih.
 *
 * Dipilih di halaman promo, dipakai di halaman pemesanan kantor. Disimpan ke
 * localStorage supaya pilihan tidak hilang saat berpindah halaman — halaman
 * pemesanan di-unmount saat navigasi, jadi ref lokal tidak cukup.
 *
 * Hanya SATU voucher yang bisa aktif: syarat promo kantor menyebut maksimal
 * satu voucher per invoice, jadi memilih yang baru menggantikan yang lama.
 */
export const usePromoBersihKantorStore = defineStore('promoBersihKantor', () => {
  const dipilih = ref<string | null>(bacaAwal())

  function bacaAwal(): string | null {
    try {
      const v = localStorage.getItem(STORAGE_KEY)
      // Diperiksa terhadap katalog: id dari versi lama bisa saja sudah dihapus.
      return v && semuaVoucherKantor().some((x) => x.id === v) ? v : null
    } catch {
      return null
    }
  }

  watch(dipilih, (v) => {
    try {
      if (v) localStorage.setItem(STORAGE_KEY, v)
      else localStorage.removeItem(STORAGE_KEY)
    } catch {
      // Storage diblokir — pilihan tetap berlaku selama sesi ini.
    }
  })

  /** Pilih voucher, atau lepas kalau id yang sama diketuk lagi. */
  function pilih(id: string | null) {
    dipilih.value = dipilih.value === id ? null : id
  }

  function lepas() {
    dipilih.value = null
  }

  /** Objek voucher yang sedang dipilih, atau null. */
  function voucher(): VoucherKantor | null {
    return cariVoucherKantor(dipilih.value)
  }

  return { dipilih, pilih, lepas, voucher }
})
