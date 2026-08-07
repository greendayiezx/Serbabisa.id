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
  <div class="min-h-screen flex items-center justify-center p-4">
    <form class="w-full max-w-sm space-y-4" @submit.prevent="handleSubmit">
      <h1 class="text-2xl font-semibold text-center">Masuk ke Tugasin</h1>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div>
        <label class="block text-sm font-medium mb-1" for="email">Email</label>
        <input
          id="email"
          v-model="email"
          type="email"
          required
          class="w-full rounded-lg border border-gray-300 px-3 py-2 min-h-11"
        />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1" for="password">Password</label>
        <input
          id="password"
          v-model="password"
          type="password"
          required
          class="w-full rounded-lg border border-gray-300 px-3 py-2 min-h-11"
        />
      </div>

      <button
        type="submit"
        :disabled="loading"
        class="w-full bg-brand text-white rounded-lg py-2.5 font-medium min-h-11 disabled:opacity-50"
      >
        {{ loading ? 'Memproses...' : 'Masuk' }}
      </button>

      <p class="text-center text-sm text-gray-600">
        Belum punya akun?
        <RouterLink :to="{ name: 'register' }" class="text-brand font-medium">Daftar</RouterLink>
      </p>
    </form>
  </div>
</template>
