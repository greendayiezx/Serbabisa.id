import { useRoute, useRouter } from 'vue-router'

/**
 * Tombol kembali yang menaiki hierarki halaman, bukan menelusuri riwayat.
 *
 * `router.back()` mengikuti urutan kunjungan browser. Begitu pengguna bolak-balik
 * — misalnya rumah → promo → rumah → promo — riwayatnya menumpuk, dan menekan
 * kembali harus berkali-kali sebelum sampai ke halaman utama. Padahal yang
 * diharapkan dari panah di pojok kiri atas adalah "naik satu tingkat".
 *
 * Urutan penentuan tujuan:
 *
 *   1. `?dari=` — halaman yang punya LEBIH DARI SATU pintu masuk membawa jalur
 *      asalnya. Halaman promo bisa dibuka dari halaman layanan maupun dari
 *      halaman pemesanan; tanpa ini, pengguna yang datang dari pemesanan akan
 *      terlempar ke halaman layanan dan kehilangan isian yang sedang dikerjakan.
 *      Bedanya dengan riwayat browser: hanya SATU tingkat, tidak menumpuk.
 *   2. `meta.induk` di router — hierarki tetap untuk halaman berpintu tunggal.
 *   3. Beranda.
 *
 * Dipakai `replace`, bukan `push`: naik tingkat tidak boleh menambah entri baru,
 * karena itu justru memperpanjang riwayat yang sedang kita hindari.
 */
export function useKembali() {
  const router = useRouter()
  const route = useRoute()

  return function kembali() {
    const dari = jalurAsalYangSah(route.query.dari, router)
    if (dari) {
      router.replace(dari)
      return
    }

    const induk = route.meta.induk as string | undefined
    if (!induk) {
      router.replace({ name: 'home' })
      return
    }

    /*
     * Halaman lokasi butuh ?category untuk memilih tampilan yang benar. Tanpa
     * itu ia jatuh ke tampilan umum: judulnya berubah jadi "Mau anter tugas ke
     * mana hari ini?", ilustrasi layanannya hilang, dan alamat yang dipilih di
     * sana tidak melanjutkan ke mana-mana. Halamannya terlihat benar, hanya
     * saja jalannya buntu — itu sebabnya ini tidak pernah terbaca sebagai galat.
     *
     * Dua sumbernya, karena tidak semua halaman menyimpan kategori di URL-nya
     * sendiri: `meta.indukQuery` untuk yang tidak (BisaKirim, BisaJemput), dan
     * ?category milik rute sekarang untuk yang iya (BisaBersih).
     */
    const indukQuery = route.meta.indukQuery as Record<string, string> | undefined
    const category = route.query.category
    const query = {
      ...(indukQuery ?? {}),
      ...(typeof category === 'string' && category ? { category } : {}),
    }

    router.replace({
      name: induk,
      ...(Object.keys(query).length ? { query } : {}),
    })
  }
}

/**
 * Saring `?dari=` supaya hanya menerima jalur internal yang benar-benar ada.
 *
 * Nilai ini berasal dari URL, jadi bisa diisi siapa saja. Tanpa penyaringan,
 * `?dari=https://situs-lain` akan membuat tombol kembali melempar pengguna ke
 * luar aplikasi — persis pola serangan open redirect.
 */
function jalurAsalYangSah(
  nilai: unknown,
  router: ReturnType<typeof useRouter>,
): string | null {
  if (typeof nilai !== 'string' || !nilai) return null

  // Harus jalur internal: diawali satu garis miring, dan bukan "//" yang di
  // browser berarti alamat protokol-relatif ke domain lain.
  if (!nilai.startsWith('/') || nilai.startsWith('//')) return null

  try {
    return router.resolve(nilai).matched.length > 0 ? nilai : null
  } catch {
    return null
  }
}
