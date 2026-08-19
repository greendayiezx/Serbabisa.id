import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { Role } from '@/types'

const router = createRouter({
  history: createWebHistory(),
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
      component: () => import('@/views/customer/AngkutDetailView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/angkut/confirm',
      name: 'task-angkut-confirm',
      component: () => import('@/views/customer/AngkutConfirmView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/angkut/delivery',
      name: 'task-angkut-delivery',
      component: () => import('@/views/customer/AngkutDeliveryDetailView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/detail',
      name: 'task-belanja-detail',
      component: () => import('@/views/customer/BelanjaDetailView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/kategori/:kategori',
      name: 'task-belanja-kategori',
      component: () => import('@/views/customer/BelanjaKategoriView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/cari',
      name: 'task-belanja-cari',
      component: () => import('@/views/customer/BelanjaSearchView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/keranjang',
      name: 'task-belanja-keranjang',
      component: () => import('@/views/customer/BelanjaKeranjangView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/checkout',
      name: 'task-belanja-checkout',
      component: () => import('@/views/customer/BelanjaCheckoutView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/promo',
      name: 'task-belanja-promo',
      component: () => import('@/views/customer/BelanjaPromoView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/langganan',
      name: 'task-belanja-langganan',
      component: () => import('@/views/customer/BelanjaLanggananView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/status-bayar/:nomor',
      name: 'task-belanja-status-bayar',
      component: () => import('@/views/customer/BelanjaStatusBayarView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/belanja/status-pesanan/:nomor',
      name: 'task-belanja-status-pesanan',
      component: () => import('@/views/customer/BelanjaStatusPesananView.vue'),
      meta: { roles: ['customer'] as Role[] },
    },
    {
      path: '/tasks/new/location',
      name: 'task-location',
      component: () => import('@/views/customer/LocationView.vue'),
      meta: { roles: ['customer', 'mitra', 'admin'] as Role[] },
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
      meta: { roles: ['customer', 'mitra', 'admin'] as Role[] },
    },
    {
      path: '/tasks/:id/chat',
      name: 'task-chat',
      component: () => import('@/views/ChatView.vue'),
      meta: { roles: ['customer', 'mitra'] as Role[] },
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
