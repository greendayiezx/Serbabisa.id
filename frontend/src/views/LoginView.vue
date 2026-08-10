<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'

const auth = useAuthStore()
const router = useRouter()

const email = ref('')
const password = ref('')
const error = ref('')
const loading = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await auth.login(email.value, password.value)
    router.push({ name: 'home' })
  } catch (err: any) {
    error.value = err.response?.data?.message ?? 'Gagal masuk. Coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center p-5 bg-(--color-surface)">
    <form class="w-full max-w-sm space-y-4" @submit.prevent="handleSubmit">
      <div class="flex items-center justify-center gap-2 font-display font-extrabold text-lg mb-2">
        <span class="w-2.5 h-2.5 rounded-full bg-(--color-lime)"></span>Tugasin
      </div>
      <h1 class="text-2xl font-extrabold text-center font-display">Masuk ke akunmu</h1>

      <p v-if="error" class="text-sm font-semibold text-(--color-error) text-center">{{ error }}</p>

      <div>
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5" for="email">Email</label>
        <input
          id="email"
          v-model="email"
          type="email"
          required
          class="w-full rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3 text-sm outline-none min-h-11"
        />
      </div>

      <div>
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5" for="password">Password</label>
        <input
          id="password"
          v-model="password"
          type="password"
          required
          class="w-full rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3 text-sm outline-none min-h-11"
        />
      </div>

      <button
        type="submit"
        :disabled="loading"
        class="w-full rounded-full bg-(--color-azure) text-white font-bold text-[15px] py-3.5 min-h-11 disabled:opacity-50"
      >
        {{ loading ? 'Memproses...' : 'Masuk' }}
      </button>

      <p class="text-center text-sm text-(--color-on-surface-variant)">
        Belum punya akun?
        <RouterLink :to="{ name: 'register' }" class="text-(--color-on-primary-container) font-bold">Daftar</RouterLink>
      </p>
    </form>
  </div>
</template>
