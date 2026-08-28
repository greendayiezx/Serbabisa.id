import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { GRUP_VOUCHER, type VoucherBersih } from '@/lib/promo/promoBersih'

const STORAGE_KEY = 'tugasin_promo_bersih'

/**
 * Promo BisaBersih yang sedang dipilih pengguna.
 *
 * Dipilih di halaman promo, dipakai di halaman pemesanan. Disimpan ke
 * localStorage supaya pilihan tidak hilang saat berpindah halaman atau
 * memuat ulang — pengguna memilih promo dulu, baru menyusun pesanannya.
 *
 * Yang disimpan hanya ID-nya, bukan salinan datanya: nilai promo boleh berubah,
 * dan pilihan lama tidak boleh mengunci angka yang sudah tidak berlaku.
 */
export const usePromoBersihStore = defineStore('promoBersih', () => {
  const dipilih = ref<string | null>(bacaAwal())

  function bacaAwal(): string | null {
    try {
      const v = localStorage.getItem(STORAGE_KEY)
      // Diperiksa terhadap katalog: id dari versi lama bisa saja sudah dihapus.
      return v && semuaVoucher().some((x) => x.id === v) ? v : null
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

  function pilih(id: string | null) {
    dipilih.value = dipilih.value === id ? null : id
  }

  /** Objek voucher yang sedang dipilih, atau null. */
  function voucher(): VoucherBersih | null {
    if (!dipilih.value) return null
    return semuaVoucher().find((v) => v.id === dipilih.value) ?? null
  }

  return { dipilih, pilih, voucher }
})

function semuaVoucher(): VoucherBersih[] {
  return GRUP_VOUCHER.flatMap((g) => g.voucher)
}
