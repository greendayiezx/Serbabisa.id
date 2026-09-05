import 'vue-router'
import type { Role } from '@/types'

declare module 'vue-router' {
  interface RouteMeta {
    roles?: Role[]
    guestOnly?: boolean
    /** Halaman satu tingkat di atasnya, tujuan panah kembali. */
    induk?: string
    /**
     * Query yang harus ikut saat naik ke `induk`.
     *
     * Untuk halaman yang induknya butuh parameter tapi dirinya sendiri tidak
     * menyimpannya di URL — misalnya /tasks/new/kirim, yang induknya adalah
     * halaman lokasi dengan ?category=bisakirim.
     */
    indukQuery?: Record<string, string>
    /** Sisi kiriman yang sedang diisi: pengambilan atau pengantaran. */
    sisi?: 'ambil' | 'antar'
  }
}
