import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import type { BelanjaItem } from './belanjaDraft'
import { ambilPesanan, ambilRiwayat, pesanError } from '@/api/belanja'

const STORAGE_KEY = 'tugasin_pesanan'

export type StatusPesanan = 'diproses' | 'dibelanjakan' | 'diantar' | 'selesai'

export interface RincianBiaya {
  totalBarang: number
  potongan: number
  biayaLayanan: number
  ongkir: number
  ongkirGratis: boolean
  /** Ongkir sebelum digratiskan, untuk ditampilkan dicoret. */
  ongkirNormal: number
  cashback: number
  totalTagihan: number
}

export interface Pesanan {
  invoice: string
  nomor: string
  /** ISO. Waktu pembayaran. */
  dibuatPada: string
  estimasiMulai: string
  estimasiSelesai: string
  metodePembayaran: string
  status: StatusPesanan
  toko: string
  alamat: string
  penerima: string
  telepon: string
  catatan: string
  items: BelanjaItem[]
  biaya: RincianBiaya
}

function bacaCache(): Pesanan[] {
  try {
    const mentah = localStorage.getItem(STORAGE_KEY)
    if (!mentah) return []
    const data = JSON.parse(mentah)
    if (!Array.isArray(data)) return []
    // Divalidasi seadanya: cukup pastikan field kunci ada, sisanya dipakai apa
    // adanya karena hanya ditulis oleh aplikasi ini sendiri.
    return data.filter(
      (p): p is Pesanan =>
        !!p && typeof p.invoice === 'string' && typeof p.nomor === 'string' && !!p.biaya,
    )
  } catch {
    return []
  }
}

/**
 * Riwayat pesanan BisaBelanja.
 *
 * Sumber kebenarannya adalah server; localStorage sekarang berperan sebagai
 * CACHE, bukan penyimpan utama seperti dulu. Bedanya penting: nota yang sudah
 * dibayar tetap ada walau pengguna ganti perangkat atau membersihkan browser,
 * dan halaman status masih bisa menampilkan sesuatu ketika jaringan sedang mati.
 */
export const usePesananStore = defineStore('pesanan', () => {
  const daftar = ref<Pesanan[]>(bacaCache())
  const memuat = ref(false)
  const galat = ref<string | null>(null)

  watch(
    daftar,
    (nilai) => {
      try {
        // Simpan 20 pesanan terakhir saja supaya localStorage tidak membengkak.
        localStorage.setItem(STORAGE_KEY, JSON.stringify(nilai.slice(0, 20)))
      } catch {
        // Kuota penuh / storage diblokir — riwayat tetap jalan di memori.
      }
    },
    { deep: true },
  )

  /** Masukkan/perbarui satu pesanan di cache, terbaru di depan. */
  function simpan(p: Pesanan) {
    const i = daftar.value.findIndex((x) => x.nomor === p.nomor)
    if (i >= 0) daftar.value.splice(i, 1, p)
    else daftar.value.unshift(p)
  }

  function cari(nomor: string): Pesanan | null {
    return daftar.value.find((p) => p.nomor === nomor) ?? null
  }

  /**
   * Ambil satu pesanan dari server dan segarkan cache-nya.
   *
   * Kalau server tidak terjangkau tapi pesanannya ada di cache, cache yang
   * dipakai dan galat sengaja dibiarkan null: pengguna yang baru saja membayar
   * lebih butuh melihat notanya daripada melihat pesan error jaringan.
   */
  async function muat(nomor: string): Promise<Pesanan | null> {
    memuat.value = true
    galat.value = null
    try {
      const p = await ambilPesanan(nomor)
      simpan(p)
      return p
    } catch (e) {
      const cadangan = cari(nomor)
      if (!cadangan) galat.value = pesanError(e)
      return cadangan
    } finally {
      memuat.value = false
    }
  }

  /** Segarkan seluruh riwayat dari server. */
  async function muatRiwayat(): Promise<void> {
    memuat.value = true
    galat.value = null
    try {
      daftar.value = await ambilRiwayat()
    } catch (e) {
      galat.value = pesanError(e)
    } finally {
      memuat.value = false
    }
  }

  const terbaru = () => daftar.value[0] ?? null

  return { daftar, memuat, galat, simpan, cari, muat, muatRiwayat, terbaru }
})
