<script setup lang="ts">
/**
 * Petak unggah foto/video untuk permintaan servis.
 *
 * Berkas dikirim sebagai data URL, pola yang sama dengan tanda tangan
 * permintaan kantor — tidak ada endpoint unggah terpisah, dan menambahkannya
 * hanya untuk ini berarti satu jalur baru yang harus dijaga sendiri.
 *
 * Gambar dikecilkan di peramban sebelum dikirim. Foto ponsel sekarang 3-5 MB,
 * dan base64 menambah sepertiga lagi; tanpa dikecilkan, satu permintaan dengan
 * enam foto akan ditolak server sebelum sampai.
 */
import { ref } from 'vue'
import Icon from '@/components/icons/Icon.vue'

export interface SlotFoto {
  id: string
  label: string
  ikon?: string
}

const props = defineProps<{
  slot: SlotFoto[]
  /** Sisi terpanjang setelah dikecilkan. */
  maksSisi?: number
}>()

const model = defineModel<Record<string, string>>({ default: () => ({}) })

const galat = ref<string | null>(null)

/**
 * Kecilkan lewat canvas, lalu keluarkan sebagai JPEG mutu 0,72.
 *
 * JPEG, bukan PNG: isi foto adalah pemandangan, bukan grafik bergaris — PNG di
 * situ menghasilkan berkas berlipat besarnya tanpa terlihat lebih baik.
 */
function kecilkan(berkas: File, maksSisi: number): Promise<string> {
  return new Promise((selesai, gagal) => {
    const pembaca = new FileReader()
    pembaca.onerror = () => gagal(new Error('Berkas tidak terbaca.'))
    pembaca.onload = () => {
      const gambar = new Image()
      gambar.onerror = () => gagal(new Error('Berkas itu bukan gambar.'))
      gambar.onload = () => {
        const skala = Math.min(1, maksSisi / Math.max(gambar.width, gambar.height))
        const kanvas = document.createElement('canvas')
        kanvas.width = Math.round(gambar.width * skala)
        kanvas.height = Math.round(gambar.height * skala)

        const ctx = kanvas.getContext('2d')
        if (!ctx) return gagal(new Error('Peramban tidak bisa memproses gambar.'))

        ctx.drawImage(gambar, 0, 0, kanvas.width, kanvas.height)
        selesai(kanvas.toDataURL('image/jpeg', 0.72))
      }
      gambar.src = String(pembaca.result)
    }
    pembaca.readAsDataURL(berkas)
  })
}

async function pilihBerkas(e: Event, id: string) {
  const input = e.target as HTMLInputElement
  const berkas = input.files?.[0]
  if (!berkas) return

  galat.value = null

  try {
    model.value = { ...model.value, [id]: await kecilkan(berkas, props.maksSisi ?? 1280) }
  } catch (err) {
    galat.value = err instanceof Error ? err.message : 'Gambar gagal diproses.'
  } finally {
    // Dikosongkan supaya memilih berkas yang sama dua kali tetap memicu change.
    input.value = ''
  }
}

function hapus(id: string) {
  const salinan = { ...model.value }
  delete salinan[id]
  model.value = salinan
}
</script>

<template>
  <div>
    <div class="grid grid-cols-3 gap-2.5">
      <div v-for="s in slot" :key="s.id" class="relative">
        <label
          class="aspect-square w-full rounded-xl border-2 border-dashed border-(--color-outline)/50 flex flex-col items-center justify-center gap-1.5 cursor-pointer overflow-hidden active:scale-95 transition-transform"
          :class="model[s.id] ? 'border-solid border-(--color-azure)' : ''"
        >
          <img
            v-if="model[s.id]"
            :src="model[s.id]"
            :alt="s.label"
            class="absolute inset-0 w-full h-full object-cover"
          />
          <template v-else>
            <Icon :name="s.ikon ?? 'camera'" class="w-6 h-6 text-(--color-on-surface-variant)" />
            <span class="text-[10.5px] font-semibold text-center px-1 leading-tight">
              {{ s.label }}
            </span>
          </template>

          <input type="file" accept="image/*" class="sr-only" @change="pilihBerkas($event, s.id)" />
        </label>

        <button
          v-if="model[s.id]"
          type="button"
          :aria-label="`Hapus foto ${s.label}`"
          class="absolute -top-1.5 -right-1.5 w-6 h-6 rounded-full bg-(--color-error) text-white flex items-center justify-center shadow-md active:scale-90 transition-transform"
          @click="hapus(s.id)"
        >
          <Icon name="x" class="w-3 h-3" />
        </button>
      </div>
    </div>

    <p v-if="galat" role="alert" class="mt-2 text-[11.5px] font-semibold text-(--color-error)">
      {{ galat }}
    </p>
  </div>
</template>
