/**
 * Format Rupiah untuk nilai yang datang dari server.
 *
 * Kolom decimal Laravel (harga, budget) dikirim sebagai STRING — `'17000.00'`,
 * bukan `17000`. Ini penting karena `String.prototype.toLocaleString()` ADA dan
 * mengembalikan stringnya utuh tanpa melempar galat apa pun, sehingga
 * `Rp${task.harga.toLocaleString('id-ID')}` diam-diam mencetak "Rp17000.00".
 *
 * Karena itu nilainya selalu dilewatkan `Number()` dulu di sini, dan semua
 * tempat yang menampilkan uang dari server memakai fungsi ini alih-alih
 * memanggil `toLocaleString` sendiri-sendiri.
 */
export function rupiah(nilai: number | string | null | undefined): string {
  const angka = Number(nilai ?? 0)

  // NaN muncul kalau kolomnya berisi teks yang bukan angka. Menampilkan
  // "RpNaN" ke pengguna lebih buruk daripada menampilkan nol.
  if (!Number.isFinite(angka)) return 'Rp0'

  return 'Rp' + Math.round(angka).toLocaleString('id-ID')
}
