import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { PilihanJemput, PromoJemput, TitikJemput } from '@/lib/jemput'

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

  /**
   * Promo dan metode bayar ikut disimpan di sini, bukan di dalam halaman
   * pemesanan: keduanya dipilih di layar lain (voucher dan lembar pembayaran)
   * lalu dibawa kembali, dan state yang tinggal di halaman akan hilang saat
   * halamannya ditinggalkan.
   */
  const promo = ref<PromoJemput | null>(null)
  const metode = ref('gopay')

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
    // berlaku. Menyimpannya hanya akan menampilkan harga yang salah — begitu
    // pula promonya, yang potongannya dihitung dari tarif itu.
    pilihan.value = null
    promo.value = null
  }

  function setPilihan(p: PilihanJemput | null) {
    pilihan.value = p

    /*
     * Ganti kendaraan berarti ganti tarif, dan promo yang tadi bisa dipakai
     * belum tentu masih memenuhi minimumnya. Yang dipakai adalah salinan promo
     * MILIK pilihan baru — bukan potongan lama yang dibawa-bawa.
     */
    if (!p) {
      promo.value = null
      return
    }
    const kode = promo.value?.kode
    promo.value = kode
      ? (p.promo.find((x) => x.kode === kode && x.bisa_dipakai) ?? null)
      : null
  }

  function setPromo(p: PromoJemput | null) {
    promo.value = p?.bisa_dipakai ? p : null
  }

  function setMetode(m: string) {
    metode.value = m
  }

  function hapus() {
    jemput.value = null
    jemputDikonfirmasi.value = false
    tujuan.value = null
    pilihan.value = null
    promo.value = null
  }

  return {
    jemput,
    jemputDikonfirmasi,
    tujuan,
    pilihan,
    promo,
    metode,
    setJemput,
    konfirmasiJemput,
    setTujuan,
    setPilihan,
    setPromo,
    setMetode,
    hapus,
  }
})
