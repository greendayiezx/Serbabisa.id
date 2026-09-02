import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { PilihanJemput, TitikJemput } from '@/lib/jemput'

/**
 * Draf perjalanan BisaJemput.
 *
 * Titik jemput disimpan terpisah dari tujuan, dan punya penanda
 * `dikonfirmasi` sendiri. Penanda itu tidak pernah dinyalakan otomatis oleh
 * GPS: hanya penumpang yang bisa menyalakannya, dari layar konfirmasi. Titik
 * GPS bisa meleset puluhan meter, dan di gang atau gedung bertingkat selisih
 * itu berarti pengemudi menunggu di tempat yang salah.
 *
 * Mengubah titik jemput MEMATIKAN penandanya lagi — titik baru berarti
 * konfirmasi baru, bukan konfirmasi lama yang ikut pindah.
 */
export const useJemputStore = defineStore('jemput', () => {
  const jemput = ref<TitikJemput | null>(null)
  const jemputDikonfirmasi = ref(false)
  const tujuan = ref<TitikJemput | null>(null)
  const pilihan = ref<PilihanJemput | null>(null)

  function setJemput(t: TitikJemput) {
    jemput.value = t
    jemputDikonfirmasi.value = false
  }

  function konfirmasiJemput(catatan?: string) {
    if (!jemput.value) return
    jemput.value = { ...jemput.value, catatan: catatan ?? jemput.value.catatan ?? null }
    jemputDikonfirmasi.value = true
  }

  function setTujuan(t: TitikJemput | null) {
    tujuan.value = t
    // Tujuan berubah berarti jarak berubah, dan tarif pilihan lama sudah tidak
    // berlaku. Menyimpannya hanya akan menampilkan harga yang salah.
    pilihan.value = null
  }

  function setPilihan(p: PilihanJemput | null) {
    pilihan.value = p
  }

  function hapus() {
    jemput.value = null
    jemputDikonfirmasi.value = false
    tujuan.value = null
    pilihan.value = null
  }

  return {
    jemput,
    jemputDikonfirmasi,
    tujuan,
    pilihan,
    setJemput,
    konfirmasiJemput,
    setTujuan,
    setPilihan,
    hapus,
  }
})
