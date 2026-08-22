import { createPinia, setActivePinia } from 'pinia'
import { beforeEach, describe, expect, it, vi } from 'vitest'

// Mock lapisan HTTP supaya test tidak benar-benar memanggil jaringan.
// Ini inti "unit test": kita uji logika store secara terisolasi.
vi.mock('@/api/client', () => ({
  default: { post: vi.fn(), get: vi.fn() },
}))

import apiClient from '@/api/client'
import { useAuthStore } from '@/stores/auth'

describe('auth store', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
    localStorage.clear()
    vi.clearAllMocks()
  })

  it('setSession menyimpan user, token, dan menulis token ke localStorage', () => {
    const store = useAuthStore()
    store.setSession({ id: 1, name: 'Budi' } as never, 'tok_123')

    expect(store.user).toMatchObject({ name: 'Budi' })
    expect(store.token).toBe('tok_123')
    expect(localStorage.getItem('tugasin_token')).toBe('tok_123')
  })

  it('clearSession menghapus sesi & token dari localStorage', () => {
    const store = useAuthStore()
    store.setSession({ id: 1 } as never, 'tok_123')

    store.clearSession()

    expect(store.user).toBeNull()
    expect(store.token).toBeNull()
    expect(localStorage.getItem('tugasin_token')).toBeNull()
  })

  it('login memanggil POST /login dan menyimpan sesi dari response', async () => {
    vi.mocked(apiClient.post).mockResolvedValue({
      data: { user: { id: 7, name: 'Siti' }, token: 'tok_login' },
    })

    const store = useAuthStore()
    await store.login('siti@example.com', 'password123')

    expect(apiClient.post).toHaveBeenCalledWith('/login', {
      email: 'siti@example.com',
      password: 'password123',
    })
    expect(store.token).toBe('tok_login')
  })

  it('logout tetap membersihkan sesi walau request ke server gagal', async () => {
    vi.mocked(apiClient.post).mockRejectedValue(new Error('network down'))

    const store = useAuthStore()
    store.setSession({ id: 1 } as never, 'tok')

    // logout() tidak menelan error (tidak ada catch), tapi clearSession ada di
    // blok `finally` — jadi sesi tetap bersih meski promise-nya reject.
    await expect(store.logout()).rejects.toThrow('network down')

    expect(store.token).toBeNull()
    expect(localStorage.getItem('tugasin_token')).toBeNull()
  })
})
