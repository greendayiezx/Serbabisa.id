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
  <div class="min-h-screen flex items-center justify-center p-5 bg-(--color-surface)">
    <form class="w-full max-w-sm space-y-4" @submit.prevent="handleSubmit">
      <div class="flex items-center justify-center gap-2 font-display font-extrabold text-lg mb-2">
        <span class="w-2.5 h-2.5 rounded-full bg-(--color-lime)"></span>Tugasin
      </div>
      <h1 class="text-2xl font-extrabold text-center font-display">Buat akun baru</h1>

      <p v-if="error" class="text-sm font-semibold text-(--color-error) text-center">{{ error }}</p>

      <div class="flex gap-2">
        <button
          type="button"
          class="flex-1 rounded-2xl border py-2.5 min-h-11 font-bold text-sm"
          :class="form.role === 'customer' ? 'border-(--color-azure) text-(--color-on-primary-container) bg-(--color-primary-container)' : 'border-(--color-outline) text-(--color-on-surface-variant)'"
          @click="form.role = 'customer'"
        >
          Customer
        </button>
        <button
          type="button"
          class="flex-1 rounded-2xl border py-2.5 min-h-11 font-bold text-sm"
          :class="form.role === 'mitra' ? 'border-(--color-azure) text-(--color-on-primary-container) bg-(--color-primary-container)' : 'border-(--color-outline) text-(--color-on-surface-variant)'"
          @click="form.role = 'mitra'"
        >
          Mitra
        </button>
      </div>

      <div>
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5" for="name">Nama</label>
        <input id="name" v-model="form.name" required class="w-full rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3 text-sm outline-none min-h-11" />
      </div>

      <div>
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5" for="email">Email</label>
        <input id="email" v-model="form.email" type="email" required class="w-full rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3 text-sm outline-none min-h-11" />
      </div>

      <div>
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5" for="phone">No. HP</label>
        <input id="phone" v-model="form.phone" required class="w-full rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3 text-sm outline-none min-h-11" />
      </div>

      <div>
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5" for="password">Password</label>
        <input id="password" v-model="form.password" type="password" required class="w-full rounded-(--radius-input) bg-(--color-surface-container) px-3.5 py-3 text-sm outline-none min-h-11" />
      </div>

      <div>
        <label class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5" for="password_confirmation">Konfirmasi Password</label>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
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
        {{ loading ? 'Memproses...' : 'Daftar' }}
      </button>

      <p class="text-center text-sm text-(--color-on-surface-variant)">
        Sudah punya akun?
        <RouterLink :to="{ name: 'login' }" class="text-(--color-on-primary-container) font-bold">Masuk</RouterLink>
      </p>
    </form>
  </div>
</template>
