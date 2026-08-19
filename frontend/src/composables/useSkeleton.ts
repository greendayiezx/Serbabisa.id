import { onBeforeUnmount, ref } from 'vue'

interface Opsi {
  /**
   * Berapa lama minimal skeleton ditahan sejak frame pertama. Skeleton yang
   * muncul lalu hilang dalam belasan milidetik terbaca sebagai gangguan render,
   * bukan sebagai indikator memuat.
   */
  minimal?: number
}

/**
 * Pengatur skeleton halaman.
 *
 * `tampil` sengaja dimulai dari `true`, bukan `false`. Versi sebelumnya memakai
 * ambang tunda ("tampilkan kalau belum siap dalam 90 ms") dan hasil pengukuran
 * menunjukkan skeleton tidak pernah tergambar sama sekali — bahkan dengan CPU
 * dicekik 20x. Penyebabnya: komponen dirender sinkron dalam task yang sama
 * dengan setup, jadi timer tidak pernah kebagian giliran sebelum konten asli
 * selesai. Satu-satunya cara skeleton benar-benar terlihat adalah menjadikannya
 * isi frame pertama, lalu konten asli menyusul di frame berikutnya.
 *
 * Konsekuensinya konten asli tertunda ~`minimal` ms. Itu harga yang dibayar
 * supaya perpindahan halaman punya umpan balik, bukan layar diam.
 */
export function useSkeleton(opsi: Opsi = {}) {
  const minimal = opsi.minimal ?? 180

  const tampil = ref(true)
  let timer: ReturnType<typeof setTimeout> | null = null

  /**
   * Jam batas minimal dihitung sejak skeleton benar-benar TERGAMBAR, bukan
   * sejak setup. Di perangkat sangat lambat, jarak setup ke frame pertama bisa
   * lebih panjang dari batas minimal itu sendiri — kalau dihitung dari setup,
   * skeleton langsung hilang di frame berikutnya dan justru berkedip di
   * perangkat yang paling membutuhkannya.
   */
  let mulai = Date.now()
  requestAnimationFrame(() => (mulai = Date.now()))

  /** Panggil begitu halaman siap digambar. */
  function tandaiSiap() {
    if (!tampil.value) return
    const sisa = Math.max(0, minimal - (Date.now() - mulai))
    timer = setTimeout(() => (tampil.value = false), sisa)
  }

  onBeforeUnmount(() => {
    if (timer) clearTimeout(timer)
  })

  return { tampil, tandaiSiap }
}
