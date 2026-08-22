import { fileURLToPath, URL } from 'node:url'
import { defineConfig } from 'vitest/config'
import vue from '@vitejs/plugin-vue'

// Konfigurasi terpisah dari vite.config.ts agar build produksi tidak
// membawa setting test. Test diletakkan di folder `tests/` (di luar `src/`)
// supaya tidak ikut ter-typecheck oleh `vue-tsc -b` saat build.
export default defineConfig({
  plugins: [vue()],
  resolve: {
    alias: {
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
  test: {
    globals: true,
    environment: 'jsdom',
    include: ['tests/**/*.spec.ts'],
    coverage: {
      provider: 'v8',
      include: ['src/stores/**', 'src/lib/**'],
    },
  },
})
