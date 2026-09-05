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

  /*
   * Permintaan penawaran bernomor REQ-, belum jadi pesanan. Ada dua jenisnya
   * sekarang, dan halaman terkirim milik kantor tidak bisa menampilkan
   * permintaan pemasangan AC — isinya perusahaan, luas, dan frekuensi.
   * Sampai permintaan AC punya halamannya sendiri, ia dibuka di layar tugas
   * generik yang setidaknya menampilkan isinya dengan benar.
   */
  if (invoice.startsWith('REQ-')) {
    if (task.detail_layanan?.layanan === 'pasang-ac') {
      return { name: 'task-detail', params: { id: task.id } }
    }

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
  /*
   * Disinfektan: begitu laporannya ada, ITU yang dicari orang saat membuka
   * pesanannya lagi — produk apa yang dipakai, berapa waktu kontaknya, dan foto
   * sesudahnya. Selama belum ada, layar tugas generik sudah menampilkan
   * rincian, harga, dan jadwalnya dengan benar.
   */
  /*
   * BisaJemput selalu punya halaman perjalanannya sendiri: yang dicari orang
   * saat membuka pesanan ini bukan rincian tagihan, melainkan pengemudi mana
   * yang datang dan sudah sampai mana.
   */
  // BisaKirim punya layar kirimannya sendiri: yang dicari orang saat membuka
  // pesanan ini adalah kode terima paket dan sudah sampai mana kurirnya.
  if (task.detail_layanan?.layanan === 'kirim' && invoice) {
    return { name: 'task-kirim-status', params: { nomor: invoice } }
  }

  if (task.detail_layanan?.layanan === 'jemput' && invoice) {
    return { name: 'task-jemput-perjalanan', params: { nomor: invoice } }
  }

  if (task.detail_layanan?.layanan === 'disinfektan') {
    return task.detail_layanan?.laporan && invoice
      ? { name: 'task-bersih-disinfektan-laporan', params: { nomor: invoice } }
      : { name: 'task-detail', params: { id: task.id } }
  }

  if (task.detail_layanan?.layanan === 'servis-ac') {
    return { name: 'servis-ac-selesai', params: { nomor: invoice } }
  }

  /*
   * Cek & Tambah Freon berujung pada halaman hasil pemeriksaan: di situlah
   * pelanggan menjawab rekomendasi teknisi, dan itu yang ia cari saat membuka
   * pesanannya.
   */
  // Perbaikan AC memakai halaman hasil yang sama: keadaannya identik —
  // menunggu teknisi, lalu menjawab rekomendasinya.
  if (task.detail_layanan?.layanan === 'freon' || task.detail_layanan?.layanan === 'perbaikan') {
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
