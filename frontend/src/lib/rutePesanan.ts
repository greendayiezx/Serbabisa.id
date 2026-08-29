import type { RouteLocationRaw } from 'vue-router'
import type { Task } from '@/types'

/**
 * Ke mana sebuah pesanan harus dibuka dari daftar "Tugas Saya".
 *
 * Halaman tugas generik (`/tasks/:id`) hanya menampilkan judul, status, dan
 * harga — itu memadai untuk tugas custom, tapi salah untuk pesanan berlayanan:
 * pesanan BisaBersih yang masih menunggu punya layar tunggunya sendiri, dan
 * pesanan yang sudah diterima punya halaman detail beserta pelacakannya.
 * Membuka layar generik di dua keadaan itu berarti menyembunyikan hal yang
 * justru sedang dicari pengguna.
 *
 * Diputuskan dari DATA PESANAN, bukan dari halaman mana pengguna datang —
 * daftar tugas bisa dibuka kapan saja, termasuk berhari-hari kemudian.
 */
export function ruteTugas(task: Task): RouteLocationRaw {
  const invoice = task.nomor_invoice ?? ''

  // Permintaan penawaran kantor: bernomor REQ-, belum jadi pesanan.
  if (invoice.startsWith('REQ-')) {
    return { name: 'kantor-permintaan-terkirim', params: { nomor: invoice } }
  }

  /*
   * Tanpa nomor invoice tidak ada yang bisa dibuka: setiap halaman status
   * layanan mencari pesanannya lewat nomor itu. Pesanan lama dari sebelum
   * penomoran invoice ada memang seperti ini — dan halaman generik masih
   * menampilkan isinya dengan benar.
   */
  if (!invoice) {
    return { name: 'task-detail', params: { id: task.id } }
  }

  /*
   * BisaBelanja punya layar statusnya sendiri — daftar barang, tahap belanja
   * mitra, sampai pengantaran. Halaman tugas generik hanya menampilkan judul
   * "Pesanan BisaBelanja" tanpa satu pun barang di dalamnya, jadi membukanya
   * dari sini berarti menyembunyikan seluruh isi pesanan.
   */
  if (belanja(task)) {
    return { name: 'task-belanja-status-pesanan', params: { nomor: invoice } }
  }

  /*
   * Servis AC juga punya halaman sendiri. Dikenali dari detail_layanan, bukan
   * dari kategori: BisaTukang menampung banyak pekerjaan lain yang memang
   * cocok dibuka di halaman tugas generik.
   */
  if (task.detail_layanan?.layanan === 'servis-ac') {
    return { name: 'servis-ac-selesai', params: { nomor: invoice } }
  }

  /*
   * Cek & Tambah Freon berujung pada halaman hasil pemeriksaan: di situlah
   * pelanggan menjawab rekomendasi teknisi, dan itu yang ia cari saat membuka
   * pesanannya.
   */
  if (task.detail_layanan?.layanan === 'freon') {
    return { name: 'servis-ac-freon-hasil', params: { nomor: invoice } }
  }

  if (!bersih(task)) {
    return { name: 'task-detail', params: { id: task.id } }
  }

  if (kantor(task)) {
    // Belum ada yang menerima → layar tunggu; halaman detail order berbicara
    // seolah pekerjaan sudah punya penanggung jawab.
    return task.status === 'pending'
      ? { name: 'task-bersih-kantor-mencari', params: { nomor: invoice } }
      : { name: 'task-bersih-kantor-detail-order', query: { invoice } }
  }

  // BisaBersih Rumah: satu halaman yang menampung dua keadaan (menunggu dan
  // sudah diterima), jadi tidak perlu dipisah berdasarkan status.
  return { name: 'task-bersih-status-bayar', params: { nomor: invoice } }
}

function belanja(task: Task): boolean {
  return task.category?.slug === 'bisabelanja' || task.judul.includes('BisaBelanja')
}

function bersih(task: Task): boolean {
  return (
    task.category?.slug === 'bisabersih' ||
    task.judul.startsWith('BisaBersih')
  )
}

/**
 * Kantor dibedakan dari `detail_layanan.layanan` yang ditulis saat checkout.
 * Judul dipakai sebagai cadangan untuk pesanan lama yang belum punya penanda
 * itu — bukan sebagai sumber utama, karena judul bisa berubah kapan saja.
 */
function kantor(task: Task): boolean {
  return task.detail_layanan?.layanan === 'kantor' || task.judul.includes('Kantor')
}
