import { defineStore } from 'pinia'
import { ref } from 'vue'
import type { PilihanKirim, PromoKirim, TitikKirim } from '@/lib/kirim'
import type { MetodeId } from '@/lib/metodeBayar'

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
  /*
   * Rute yang sudah dihitung server, disimpan supaya layar konfirmasi
   * menggambar garis YANG SAMA dengan layar detail. Tanpa ini konfirmasi hanya
   * punya dua titik, dan garisnya jatuh ke lurus putus-putus — bentuk yang di
   * aplikasi ini berarti 'rutenya tidak diketahui'. Padahal diketahui.
   */
  const geometri = ref<[number, number][] | null>(null)
  const lewatJalan = ref(false)
  const pilihan = ref<PilihanKirim | null>(null)
  const promo = ref<PromoKirim | null>(null)
  /*
   * Bertipe MetodeId, bukan string: id inilah yang masuk kolom payments.metode
   * dan dipakai rekonsiliasi, jadi salah ketik harus ketahuan saat dikompilasi.
   *
   * Bawaannya tunai, sama dengan checkout BisaBelanja. Sebelumnya gopay —
   * padahal saldo contohnya Rp5.823, jadi layar terbuka dengan metode yang
   * lembar pilihannya sendiri tandai 'saldo tidak cukup' dan tidak bisa
   * dipilih ulang.
   */
  const metode = ref<MetodeId>('tunai')

  function setAmbil(t: TitikKirim | null) {
    ambil.value = t
    lupakanRute()
  }

  function setAntar(t: TitikKirim | null) {
    antar.value = t
    // Rute berubah berarti ongkir berubah; pilihan dan voucher lama tidak
    // berlaku lagi, dan menyimpannya hanya menampilkan harga yang salah.
    lupakanRute()
  }

  /** Tukar titik ambil dan antar BESERTA kontaknya. */
  function tukar() {
    const a = ambil.value
    ambil.value = antar.value
    antar.value = a
    lupakanRute()
  }

  /**
   * Ganti kontak SAJA — nama, telepon, patokan — tanpa menyentuh koordinatnya.
   *
   * Dipisah dari setAmbil/setAntar dengan sengaja: keduanya membuang pilihan
   * kendaraan dan voucher karena rutenya berubah. Mengetik nama penerima bukan
   * perubahan rute, dan memaksa hitung ulang di situ akan menghapus voucher
   * yang sudah dipilih orang tanpa alasan yang bisa ia lihat.
   */
  function setKontak(
    sisi: 'ambil' | 'antar',
    kontak: { nama?: string; telepon?: string; catatan?: string | null },
  ) {
    const titik = sisi === 'ambil' ? ambil : antar
    if (!titik.value) return
    titik.value = { ...titik.value, ...kontak }
  }

  /** Buang semua yang dihitung dari rute lama. */
  function lupakanRute() {
    pilihan.value = null
    promo.value = null
    geometri.value = null
    lewatJalan.value = false
  }

  function setRute(g: [number, number][] | null, lewat: boolean) {
    geometri.value = g
    lewatJalan.value = lewat
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
    lupakanRute()
  }

  return {
    ambil,
    antar,
    ukuran,
    isi,
    nilaiBarang,
    pakaiKodeTerima,
    geometri,
    lewatJalan,
    pilihan,
    promo,
    metode,
    setAmbil,
    setAntar,
    setKontak,
    setRute,
    tukar,
    setUkuran,
    setPilihan,
    setPromo,
    hapus,
  }
})
