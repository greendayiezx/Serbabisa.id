<script setup lang="ts">
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '@/stores/auth'
import type { Role } from '@/types'

const auth = useAuthStore()
const router = useRouter()

const form = ref({
  name: '',
  email: '',
  phone: '',
  password: '',
  password_confirmation: '',
  role: 'customer' as Role,
})
const error = ref('')
const loading = ref(false)

async function handleSubmit() {
  error.value = ''
  loading.value = true
  try {
    await auth.register({ ...form.value, role: form.value.role as 'customer' | 'mitra' })
    router.push({ name: 'home' })
  } catch (err: any) {
    error.value = err.response?.data?.message ?? 'Gagal mendaftar. Coba lagi.'
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center p-4">
    <form class="w-full max-w-sm space-y-4" @submit.prevent="handleSubmit">
      <h1 class="text-2xl font-semibold text-center">Daftar Tugasin</h1>

      <p v-if="error" class="text-sm text-red-600">{{ error }}</p>

      <div class="flex gap-2">
        <button
          type="button"
          class="flex-1 rounded-lg border py-2 min-h-11"
          :class="form.role === 'customer' ? 'border-brand text-brand bg-brand/5' : 'border-gray-300'"
          @click="form.role = 'customer'"
        >
          Customer
        </button>
        <button
          type="button"
          class="flex-1 rounded-lg border py-2 min-h-11"
          :class="form.role === 'mitra' ? 'border-brand text-brand bg-brand/5' : 'border-gray-300'"
          @click="form.role = 'mitra'"
        >
          Mitra
        </button>
      </div>

      <div>
        <label class="block text-sm font-medium mb-1" for="name">Nama</label>
        <input id="name" v-model="form.name" required class="w-full rounded-lg border border-gray-300 px-3 py-2 min-h-11" />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1" for="email">Email</label>
        <input id="email" v-model="form.email" type="email" required class="w-full rounded-lg border border-gray-300 px-3 py-2 min-h-11" />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1" for="phone">No. HP</label>
        <input id="phone" v-model="form.phone" required class="w-full rounded-lg border border-gray-300 px-3 py-2 min-h-11" />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1" for="password">Password</label>
        <input id="password" v-model="form.password" type="password" required class="w-full rounded-lg border border-gray-300 px-3 py-2 min-h-11" />
      </div>

      <div>
        <label class="block text-sm font-medium mb-1" for="password_confirmation">Konfirmasi Password</label>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
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
        {{ loading ? 'Memproses...' : 'Daftar' }}
      </button>

      <p class="text-center text-sm text-gray-600">
        Sudah punya akun?
        <RouterLink :to="{ name: 'login' }" class="text-brand font-medium">Masuk</RouterLink>
      </p>
    </form>
  </div>
</template>
