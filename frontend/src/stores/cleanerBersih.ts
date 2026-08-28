import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { ambilCleaner, type CleanerServer, type Jenjang } from '@/api/cleaner'

const STORAGE_KEY = 'tugasin_cleaner_bersih'

/**
 * Cleaner BisaBersih: daftar yang tersedia dan mana yang sedang dipilih.
 *
 * Daftarnya datang dari server, bukan katalog di dalam kode. Level, rating, dan
 * jumlah order adalah hasil kerja nyata — customer memberi rating, level cleaner
 * naik sendiri. Kalau belum ada mitra terdaftar, `daftar` memang kosong dan
 * halaman menyatakannya apa adanya.
 *
 * Yang disimpan ke localStorage hanya ID pilihan, bukan salinan datanya: level
 * dan tarif mitra berubah seiring waktu, dan pilihan lama tidak boleh mengunci
 * angka yang sudah usang.
 */
export const useCleanerBersihStore = defineStore('cleanerBersih', () => {
  const dipilih = ref<string | null>(bacaAwal())
  const daftar = ref<CleanerServer[]>([])
  const jenjang = ref<Jenjang[]>([])
  const markupPerJam = ref(0)
  const hargaTerendahPerJam = ref(0)
  const memuat = ref(false)
  const sudahDimuat = ref(false)

  function bacaAwal(): string | null {
    try {
      return localStorage.getItem(STORAGE_KEY)
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

  /**
   * Tarik daftar dari server.
   *
   * Pilihan yang tersimpan diperiksa terhadap hasilnya: mitra bisa berhenti,
   * dan id lama tidak boleh membuat halaman menagih tarif yang tidak ada.
   */
  async function muat(paksa = false): Promise<void> {
    if (memuat.value || (sudahDimuat.value && !paksa)) return
    memuat.value = true
    try {
      const d = await ambilCleaner()
      daftar.value = d.cleaner
      jenjang.value = d.jenjang
      markupPerJam.value = d.markupPerJam
      hargaTerendahPerJam.value = d.hargaTerendahPerJam
      sudahDimuat.value = true

      if (dipilih.value && !d.cleaner.some((c) => c.id === dipilih.value)) {
        dipilih.value = null
      }
    } catch {
      // Daftar kosong bukan penghalang memesan: tanpa pilihan, pesanan tetap
      // bisa dibuat dengan tarif level terendah.
    } finally {
      memuat.value = false
    }
  }

  /** Pilih id tertentu, atau lepas kalau id yang sama diketuk lagi. */
  function pilih(id: string | null) {
    dipilih.value = dipilih.value === id ? null : id
  }

  function set(id: string | null) {
    dipilih.value = id
  }

  /** Objek cleaner yang sedang dipilih, atau null. */
  function cleaner(): CleanerServer | null {
    if (!dipilih.value) return null
    return daftar.value.find((c) => c.id === dipilih.value) ?? null
  }

  return {
    dipilih, daftar, jenjang, markupPerJam, hargaTerendahPerJam,
    memuat, sudahDimuat, muat, pilih, set, cleaner,
  }
})
