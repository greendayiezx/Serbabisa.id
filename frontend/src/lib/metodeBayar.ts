/**
 * Metode pembayaran BisaBelanja.
 *
 * Yang dikirim ke server adalah `id`-nya, bukan labelnya: kolom `payments.metode`
 * dipakai untuk rekonsiliasi, jadi harus stabil walaupun teks yang tampil di
 * layar diubah. Konsekuensinya halaman status perlu menerjemahkan id itu kembali
 * jadi teks — LABEL_METODE di bawah adalah satu-satunya tempat pemetaan itu
 * hidup, dipakai bersama oleh halaman checkout dan halaman status.
 */

export type MetodeId =
  | 'balance' | 'gopay' | 'qris'
  | 'ovo' | 'shopeepay' | 'dana' | 'linkaja'
  | 'bca' | 'bni' | 'bri' | 'mandiri'
  | 'tunai'

export const LABEL_METODE: Record<MetodeId, string> = {
  balance: 'Serbabisa Balance',
  gopay: 'GoPay',
  qris: 'QRIS',
  ovo: 'OVO',
  shopeepay: 'ShopeePay / SPayLater',
  dana: 'DANA',
  linkaja: 'LinkAja',
  bca: 'BCA Virtual Account',
  bni: 'BNI Virtual Account',
  bri: 'BRI Virtual Account',
  mandiri: 'Mandiri Virtual Account',
  tunai: 'Tunai',
}

/**
 * Terjemahkan id metode jadi teks tampilan. Pesanan lama bisa menyimpan id yang
 * sudah dihapus dari daftar, jadi nilai tak dikenal dikembalikan apa adanya
 * daripada memaksakan "Tunai" dan membuat nota menampilkan hal yang salah.
 */
export function labelMetode(id: string | null | undefined): string {
  if (!id) return '—'
  return LABEL_METODE[id as MetodeId] ?? id
}
