import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'
import type { User } from '@/types'

export const useAuthStore = defineStore('auth', () => {
  const user = ref<User | null>(null)
  const token = ref<string | null>(localStorage.getItem('tugasin_token'))

  function setSession(newUser: User, newToken: string) {
    user.value = newUser
    token.value = newToken
    localStorage.setItem('tugasin_token', newToken)
  }

  function clearSession() {
    user.value = null
    token.value = null
    localStorage.removeItem('tugasin_token')
  }

  async function login(email: string, password: string) {
    const { data } = await apiClient.post('/login', { email, password })
    setSession(data.user, data.token)
  }

  async function register(payload: {
    name: string
    email: string
    phone: string
    password: string
    password_confirmation: string
    role: 'customer' | 'mitra'
  }) {
    const { data } = await apiClient.post('/register', payload)
    setSession(data.user, data.token)
  }

  async function logout() {
    try {
      await apiClient.post('/logout')
    } finally {
      clearSession()
    }
  }

  async function fetchMe() {
    const { data } = await apiClient.get('/me')
    user.value = data
    return data
  }

  return { user, token, setSession, clearSession, login, register, logout, fetchMe }
})
