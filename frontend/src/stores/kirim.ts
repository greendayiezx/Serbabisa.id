import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { PilihanKirim, PromoKirim, TitikKirim } from '@/lib/kirim'

/**
 * Draf kiriman BisaKirim.
 *
 * Dua titik disimpan terpisah — yang diambil dan yang dituju — karena keduanya
 * bisa ditukar, dan menukarnya harus ikut menukar nama serta nomor teleponnya.
 * Titik yang tertukar tanpa kontaknya berarti kurir menelepon orang yang salah.
 */
export const useKirimStore = defineStore('kirim', () => {
  const ambil = ref<TitikKirim | null>(null)
  const antar = ref<TitikKirim | null>(null)
  const ukuran = ref('kecil')
  const isi = ref('')
  const nilaiBarang = ref(0)
  const pakaiKodeTerima = ref(false)
  const pilihan = ref<PilihanKirim | null>(null)
  const promo = ref<PromoKirim | null>(null)
  const metode = ref('gopay')

  function setAmbil(t: TitikKirim | null) {
    ambil.value = t
    pilihan.value = null
    promo.value = null
  }

  function setAntar(t: TitikKirim | null) {
    antar.value = t
    // Rute berubah berarti ongkir berubah; pilihan dan voucher lama tidak
    // berlaku lagi, dan menyimpannya hanya menampilkan harga yang salah.
    pilihan.value = null
    promo.value = null
  }

  /** Tukar titik ambil dan antar BESERTA kontaknya. */
  function tukar() {
    const a = ambil.value
    ambil.value = antar.value
    antar.value = a
    pilihan.value = null
    promo.value = null
  }

  function setUkuran(u: string) {
    ukuran.value = u
    // Ukuran menentukan kendaraan yang sanggup; pilihan lama belum tentu masih
    // boleh dipakai.
    pilihan.value = null
  }

  function setPilihan(p: PilihanKirim | null) {
    pilihan.value = p

    if (!p) {
      promo.value = null
      return
    }
    const kode = promo.value?.kode
    promo.value = kode ? (p.promo.find((x) => x.kode === kode && x.bisa_dipakai) ?? null) : null
  }

  function setPromo(p: PromoKirim | null) {
    promo.value = p?.bisa_dipakai ? p : null
  }

  function hapus() {
    ambil.value = null
    antar.value = null
    ukuran.value = 'kecil'
    isi.value = ''
    nilaiBarang.value = 0
    pakaiKodeTerima.value = false
    pilihan.value = null
    promo.value = null
  }

  return {
    ambil,
    antar,
    ukuran,
    isi,
    nilaiBarang,
    pakaiKodeTerima,
    pilihan,
    promo,
    metode,
    setAmbil,
    setAntar,
    tukar,
    setUkuran,
    setPilihan,
    setPromo,
    hapus,
  }
})
