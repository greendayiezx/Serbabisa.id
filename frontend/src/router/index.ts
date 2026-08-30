import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { Role } from '@/types'

const router = createRouter({
  history: createWebHistory(),
  /**
   * `meta.induk` = nama rute satu tingkat di atasnya.
   *
   * Dipakai tombol kembali (composables/useKembali.ts) supaya panah di pojok
   * kiri atas menaiki hierarki halaman, bukan menelusuri riwayat browser.
   * Rute tanpa induk dianggap berinduk ke beranda.
   */
  routes: [
    {
      path: '/login',
      name: 'login',
      component: () => import('@/views/LoginView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/register',
      name: 'register',
      component: () => import('@/views/RegisterView.vue'),
      meta: { guestOnly: true },
    },
    {
      path: '/',
      name: 'home',
      component: () => import('@/views/HomeView.vue'),
      meta: { roles: ['customer', 'mitra', 'admin'] as Role[] },
    },
    {
      path: '/tasks/new/angkut',
      name: 'task-angkut-location',
      component: () => import('@/views/customer/LocationView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/angkut/detail',
      name: 'task-angkut-detail',
      component: () => import('@/views/angkut/AngkutDetailView.vue'),
      meta: { induk: 'task-angkut-location', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/angkut/confirm',
      name: 'task-angkut-confirm',
      component: () => import('@/views/angkut/AngkutConfirmView.vue'),
      meta: { induk: 'task-angkut-detail', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/angkut/delivery',
      name: 'task-angkut-delivery',
      component: () => import('@/views/angkut/AngkutDeliveryDetailView.vue'),
      meta: { induk: 'task-angkut-confirm', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/detail',
      name: 'task-belanja-detail',
      component: () => import('@/views/belanja/BelanjaDetailView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/kategori/:kategori',
      name: 'task-belanja-kategori',
      component: () => import('@/views/belanja/BelanjaKategoriView.vue'),
      meta: { induk: 'task-belanja-detail', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/cari',
      name: 'task-belanja-cari',
      component: () => import('@/views/belanja/BelanjaSearchView.vue'),
      meta: { induk: 'task-belanja-detail', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/keranjang',
      name: 'task-belanja-keranjang',
      component: () => import('@/views/belanja/BelanjaKeranjangView.vue'),
      meta: { induk: 'task-belanja-detail', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/checkout',
      name: 'task-belanja-checkout',
      component: () => import('@/views/belanja/BelanjaCheckoutView.vue'),
      meta: { induk: 'task-belanja-detail', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/promo',
      name: 'task-belanja-promo',
      component: () => import('@/views/promo/BelanjaPromoView.vue'),
      meta: { induk: 'task-belanja-checkout', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/langganan',
      name: 'task-belanja-langganan',
      component: () => import('@/views/belanja/BelanjaLanggananView.vue'),
      meta: { induk: 'task-belanja-checkout', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/status-bayar/:nomor',
      name: 'task-belanja-status-bayar',
      component: () => import('@/views/belanja/BelanjaStatusBayarView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/status-pesanan/:nomor',
      name: 'task-belanja-status-pesanan',
      component: () => import('@/views/belanja/BelanjaStatusPesananView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/location',
      name: 'task-location',
      component: () => import('@/views/customer/LocationView.vue'),
      meta: { roles: ['customer', 'mitra', 'admin'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/rumah',
      name: 'task-bersih-rumah',
      component: () => import('@/views/bersih/rumah/BersihRumahView.vue'),
      meta: { induk: 'task-bersih-detail', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/kantor',
      name: 'task-bersih-kantor',
      component: () => import('@/views/bersih/kantor/BersihKantorView.vue'),
      meta: { induk: 'task-bersih-detail', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/kantor/pesan',
      name: 'task-bersih-kantor-pesan',
      component: () => import('@/views/bersih/kantor/BersihKantorPesanView.vue'),
      meta: { induk: 'task-bersih-kantor', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/kantor/konfirmasi',
      name: 'task-bersih-kantor-konfirmasi',
      component: () => import('@/views/bersih/kantor/BersihKantorKonfirmasiView.vue'),
      meta: { induk: 'task-bersih-kantor-pesan', roles: ['customer'] as Role[] },
    },
    {
      // Layar tunggu setelah bayar: menanyakan status sampai ada cleaner yang
      // menerima, lalu berpindah sendiri ke detail-order.
      path: '/tasks/new/bersih/kantor/mencari/:nomor',
      name: 'task-bersih-kantor-mencari',
      component: () => import('@/views/bersih/kantor/BersihKantorMencariView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/kantor/detail-order',
      name: 'task-bersih-kantor-detail-order',
      component: () => import('@/views/bersih/kantor/BersihKantorDetailOrderView.vue'),
      meta: { induk: 'home', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/kantor/penawaran',
      name: 'task-bersih-kantor-penawaran',
      component: () => import('@/views/bersih/kantor/BersihKantorPenawaranView.vue'),
      meta: { induk: 'task-bersih-kantor', roles: ['customer'] as Role[] },
    },
    {
      // Konfirmasi setelah permintaan penawaran terkirim, mis. REQ-000001.
      path: '/tasks/new/bersih/kantor/terkirim/:nomor',
      name: 'kantor-permintaan-terkirim',
      component: () => import('@/views/bersih/kantor/KantorPermintaanTerkirimView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      // Dokumen penawaran yang sudah disusun tim, mis. /penawaran/OFF-000124.
      path: '/penawaran/:nomor',
      name: 'penawaran',
      component: () => import('@/views/bersih/kantor/PenawaranView.vue'),
      meta: { induk: 'task-list', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/kantor/promo',
      name: 'task-bersih-kantor-promo',
      component: () => import('@/views/promo/BersihKantorPromoView.vue'),
      meta: { induk: 'task-bersih-kantor', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/langganan',
      name: 'task-bersih-langganan',
      component: () => import('@/views/bersih/rumah/BersihLanggananView.vue'),
      meta: { induk: 'task-bersih-detail', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/cleaner',
      name: 'task-bersih-cleaner',
      component: () => import('@/views/bersih/rumah/BersihCleanerView.vue'),
      meta: { induk: 'task-bersih-rumah', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/status-bayar/:nomor',
      name: 'task-bersih-status-bayar',
      component: () => import('@/views/bersih/rumah/BersihStatusBayarView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/promo',
      name: 'task-bersih-promo',
      component: () => import('@/views/promo/BersihPromoView.vue'),
      meta: { induk: 'task-bersih-detail', roles: ['customer'] as Role[] },
    },
    {
      // Pola jalurnya disamakan dengan layanan lain (angkut/detail,
      // belanja/detail) supaya URL layanan konsisten dan mudah ditebak.
      path: '/tasks/new/bersih/detail',
      name: 'task-bersih-detail',
      component: () => import('@/views/bersih/BersihDetailView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/bersih/deep',
      alias: '/tasks/new/bersih/deep-cleaning',
      name: 'task-bersih-deep',
      component: () => import('@/views/bersih/deep/BersihDeepView.vue'),
      meta: { induk: 'task-bersih-detail', roles: ['customer'] as Role[] },
    },
    {
      // Ringkasan sebelum pesanan dibuat — pola yang sama dengan konfirmasi
      // BisaBersih Kantor, ditambah pemilihan layanan tambahan.
      path: '/tasks/new/bersih/deep/konfirmasi',
      name: 'task-bersih-deep-konfirmasi',
      component: () => import('@/views/bersih/deep/BersihDeepKonfirmasiView.vue'),
      meta: { induk: 'task-bersih-deep', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new',
      name: 'task-create',
      component: () => import('@/views/customer/TaskCreateView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      // Servis AC: halaman masuk layanan.
      path: '/tasks/new/servis-ac',
      name: 'servis-ac',
      component: () => import('@/views/servis-ac/ACServisView.vue'),
      meta: { induk: 'home', roles: ['customer'] as Role[] },
    },
    {
      // Detail unit, paket, jadwal, dan pembayaran — satu halaman.
      path: '/tasks/new/servis-ac/pesan',
      name: 'servis-ac-pesan',
      component: () => import('@/views/servis-ac/ACPesanView.vue'),
      meta: { induk: 'servis-ac', roles: ['customer'] as Role[] },
    },
    {
      // Konfirmasi Cuci AC: data pemesan, jadwal, promo, dan pembayaran.
      path: '/tasks/new/servis-ac/konfirmasi',
      name: 'servis-ac-konfirmasi',
      component: () => import('@/views/servis-ac/ACKonfirmasiView.vue'),
      meta: { induk: 'servis-ac-pesan', roles: ['customer'] as Role[] },
    },
    {
      /*
       * Penawaran pemasangan. Nomornya nomor PERMINTAAN (REQ-), bukan nomor
       * penawaran: satu permintaan bisa melahirkan beberapa penawaran setelah
       * revisi, dan yang tetap adalah permintaannya.
       */
      path: '/tasks/servis-ac/penawaran/:nomor',
      name: 'servis-ac-penawaran',
      component: () => import('@/views/servis-ac/ACPenawaranView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/servis-ac/penawaran/:nomor/disetujui',
      name: 'servis-ac-penawaran-disetujui',
      component: () => import('@/views/servis-ac/ACPenawaranDisetujuiView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/servis-ac/penawaran/:nomor/revisi',
      name: 'servis-ac-penawaran-revisi',
      component: () => import('@/views/servis-ac/ACPenawaranRevisiView.vue'),
      meta: { induk: 'servis-ac-penawaran', roles: ['customer'] as Role[] },
    },
    {
      // Perbaikan & Pasang AC: halaman masuk yang bercabang tiga.
      path: '/tasks/new/servis-ac/perbaikan',
      name: 'servis-ac-perbaikan',
      component: () => import('@/views/servis-ac/ACPerbaikanView.vue'),
      meta: { induk: 'servis-ac', roles: ['customer'] as Role[] },
    },
    {
      // Perbaiki AC: yang ditagih hanya kunjungan diagnosisnya.
      path: '/tasks/new/servis-ac/perbaiki',
      name: 'servis-ac-perbaiki',
      component: () => import('@/views/servis-ac/ACPerbaikiPesanView.vue'),
      meta: { induk: 'servis-ac-perbaikan', roles: ['customer'] as Role[] },
    },
    {
      // Pasang/pindah AC: permintaan penawaran, tanpa tagihan.
      path: '/tasks/new/servis-ac/pasang',
      name: 'servis-ac-pasang',
      component: () => import('@/views/servis-ac/ACPasangPesanView.vue'),
      meta: { induk: 'servis-ac-perbaikan', roles: ['customer'] as Role[] },
    },
    {
      // Perbaiki AC langkah 2: lokasi & data pemesan, lalu dipesan.
      path: '/tasks/new/servis-ac/perbaiki/konfirmasi',
      name: 'servis-ac-perbaiki-konfirmasi',
      component: () => import('@/views/servis-ac/ACPerbaikiKonfirmasiView.vue'),
      meta: { induk: 'servis-ac-perbaiki', roles: ['customer'] as Role[] },
    },
    {
      // Pasang/pindah AC langkah 2: lokasi & data pemesan, lalu dikirim.
      path: '/tasks/new/servis-ac/pasang/konfirmasi',
      name: 'servis-ac-pasang-konfirmasi',
      component: () => import('@/views/servis-ac/ACPasangKonfirmasiView.vue'),
      meta: { induk: 'servis-ac-pasang', roles: ['customer'] as Role[] },
    },
    {
      // Cek & Tambah Freon: isian pemeriksaan.
      path: '/tasks/new/servis-ac/freon',
      name: 'servis-ac-freon',
      component: () => import('@/views/servis-ac/FreonPesanView.vue'),
      meta: { induk: 'servis-ac', roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/servis-ac/freon/ringkasan',
      name: 'servis-ac-freon-ringkasan',
      component: () => import('@/views/servis-ac/FreonRingkasanView.vue'),
      meta: { induk: 'servis-ac-freon', roles: ['customer'] as Role[] },
    },
    {
      // Hasil pemeriksaan teknisi — dua keadaan: belum diperiksa & sudah ada
      // temuan yang menunggu jawaban pelanggan.
      path: '/tasks/new/servis-ac/freon/hasil/:nomor',
      name: 'servis-ac-freon-hasil',
      component: () => import('@/views/servis-ac/FreonHasilView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      // Konfirmasi setelah pesanan tercatat.
      path: '/tasks/new/servis-ac/selesai/:nomor',
      name: 'servis-ac-selesai',
      component: () => import('@/views/servis-ac/ACSelesaiView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      // Katalog promo satu layanan. Katalognya berbeda tiap layanan; halaman
      // dan cara pakainya sama, jadi satu view saja yang melayani semuanya.
      path: '/promo/:layanan',
      name: 'promo-layanan',
      component: () => import('@/views/promo/PromoLayananView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/mine',
      name: 'task-list',
      component: () => import('@/views/customer/MyTasksView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/:id',
      name: 'task-detail',
      component: () => import('@/views/TaskDetailView.vue'),
      meta: { induk: 'task-list', roles: ['customer', 'mitra', 'admin'] as Role[] },
    },
    {
      path: '/tasks/:id/chat',
      name: 'task-chat',
      component: () => import('@/views/ChatView.vue'),
      meta: { induk: 'task-detail', roles: ['customer', 'mitra'] as Role[] },
    },
    {
      path: '/profile',
      name: 'profile',
      component: () => import('@/views/ProfileView.vue'),
      meta: { roles: ['customer', 'mitra', 'admin'] as Role[] },
    },
    {
      path: '/mitra/tasks',
      name: 'mitra-task-list',
      component: () => import('@/views/mitra/TaskListView.vue'),
      meta: { roles: ['mitra'] as Role[] },
    },
    {
      path: '/wallet',
      name: 'wallet',
      component: () => import('@/views/mitra/WalletView.vue'),
      meta: { roles: ['mitra'] as Role[] },
    },
    {
      // Permintaan penawaran BisaBersih Kantor — tahapnya digerakkan dari sini.
      path: '/admin/permintaan',
      name: 'admin-permintaan-kantor',
      component: () => import('@/views/admin/PermintaanKantorView.vue'),
      meta: { induk: 'admin-dashboard', roles: ['admin'] as Role[] },
    },
    {
      path: '/admin',
      name: 'admin-dashboard',
      component: () => import('@/views/admin/DashboardView.vue'),
      meta: { roles: ['admin'] as Role[] },
    },
  ],
  // Tanpa ini, Vue Router membiarkan posisi scroll halaman sebelumnya terbawa,
  // sehingga halaman baru terbuka di tengah/bawah. Selalu mulai dari atas,
  // kecuali saat tombol back/forward browser (pakai posisi tersimpan).
  scrollBehavior(_to, _from, savedPosition) {
    return savedPosition ?? { top: 0 }
  },
})

router.beforeEach(async (to) => {
  const auth = useAuthStore()

  if (to.meta.guestOnly && auth.token) {
    return { name: 'home' }
  }

  if (to.meta.roles) {
    if (!auth.token) {
      return { name: 'login' }
    }

    if (!auth.user) {
      try {
        await auth.fetchMe()
      } catch {
        auth.clearSession()
        return { name: 'login' }
      }
    }

    const allowedRoles = to.meta.roles as Role[]
    if (auth.user && !allowedRoles.includes(auth.user.role)) {
      return { name: 'home' }
    }
  }

  return true
})

export default router
