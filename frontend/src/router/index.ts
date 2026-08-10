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
