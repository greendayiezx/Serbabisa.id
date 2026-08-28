<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import { useChatStore } from '@/stores/chat'
import { useTaskStore } from '@/stores/task'
import { useAuthStore } from '@/stores/auth'
import AppLayout from '@/layouts/AppLayout.vue'
import Icon from '@/components/icons/Icon.vue'

const route = useRoute()
const kembali = useKembali()
const chatStore = useChatStore()
const taskStore = useTaskStore()
const auth = useAuthStore()

const taskId = Number(route.params.id)
const draft = ref('')
const sending = ref(false)

onMounted(async () => {
  await Promise.all([taskStore.fetchTask(taskId), chatStore.fetchMessages(taskId)])
})

const messages = computed(() => chatStore.messagesByTask[taskId] ?? [])

/**
 * Lawan bicara mengikuti PERAN yang membuka chat: customer melihat driver-nya,
 * mitra melihat customer-nya — bukan sekadar "mitra kalau ada". Tanpa ini,
 * customer justru melihat namanya sendiri saat mitra belum ditugaskan.
 */
const akuMitra = computed(() => auth.user?.role === 'mitra')
const lawan = computed(() =>
  akuMitra.value ? taskStore.currentTask?.customer : taskStore.currentTask?.mitra,
)
/**
 * Nama & nomor driver placeholder selama server belum mengirim identitas mitra.
 * Sama seperti di halaman status pesanan: nama/plat/nomor masih contoh. Nomor
 * placeholder dipakai supaya tombol telepon tetap ada; ganti begitu server
 * mengirim nomor mitra sungguhan.
 */
const DRIVER_TELP = '+6281234567890'
const namaLawan = computed(() => lawan.value?.name ?? (akuMitra.value ? 'Customer' : 'Asep Suparman'))
const teleponLawan = computed(() => lawan.value?.phone ?? (akuMitra.value ? null : DRIVER_TELP))

function initial(name?: string) {
  return (name ?? 'M').trim().charAt(0).toUpperCase()
}

async function send() {
  const text = draft.value.trim()
  if (!text || sending.value) return
  sending.value = true
  try {
    await chatStore.sendMessage(taskId, text)
    draft.value = ''
  } finally {
    sending.value = false
  }
}
</script>

<template>
  <AppLayout :hide-nav="true">
    <div class="flex flex-col h-dvh">
      <div class="flex items-center gap-3 px-5 pt-4.5 pb-3 border-b border-(--color-outline)">
        <button type="button" aria-label="Kembali" class="w-8.5 h-8.5 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0" @click="kembali">
          <Icon name="arrow-left" class="w-[19px] h-[19px]" />
        </button>
        <div class="w-8.5 h-8.5 rounded-full flex items-center justify-center font-display font-extrabold text-white text-[13px] bg-linear-to-br from-(--color-azure) to-[#0f7fce] shrink-0">
          {{ initial(namaLawan) }}
        </div>
        <h3 class="text-sm font-extrabold flex-1 truncate">{{ namaLawan }}</h3>
        <!-- Telepon driver langsung dari dalam aplikasi -->
        <a
          v-if="teleponLawan"
          :href="`tel:${teleponLawan}`"
          aria-label="Telepon driver"
          class="w-9 h-9 rounded-full bg-(--color-azure) text-white flex items-center justify-center shrink-0 active:scale-95 transition-transform"
        >
          <Icon name="phone" class="w-[18px] h-[18px]" />
        </a>
      </div>

      <div class="flex-1 overflow-y-auto px-4 py-3 flex flex-col gap-2.5">
        <div v-for="msg in messages" :key="msg.id" class="flex" :class="msg.sender_id === auth.user?.id ? 'justify-end' : 'justify-start'">
          <div
            class="max-w-[78%] px-3.5 py-2.5 rounded-[18px] text-[13.5px]"
            :class="msg.sender_id === auth.user?.id
              ? 'bg-(--color-azure) text-white rounded-br-[5px]'
              : 'bg-(--color-surface-container) text-(--color-on-surface) rounded-bl-[5px]'"
          >
            {{ msg.isi }}
            <span class="block text-[10px] mt-1 opacity-70">{{ new Date(msg.created_at).toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' }) }}</span>
          </div>
        </div>
        <p v-if="!messages.length" class="text-center text-sm text-(--color-on-surface-variant) py-6">Belum ada pesan. Mulai percakapan dengan Mitra.</p>
      </div>

      <form class="flex items-center gap-2.5 px-4 py-3 border-t border-(--color-outline)" @submit.prevent="send">
        <div class="flex-1 rounded-full bg-(--color-surface-container) px-4 py-2.5">
          <input v-model="draft" placeholder="Tulis pesan..." class="w-full bg-transparent border-none outline-none text-[13.5px]" />
        </div>
        <button type="submit" :disabled="sending" class="w-11 h-11 rounded-full bg-(--color-azure) text-white flex items-center justify-center shrink-0 disabled:opacity-50">
          <Icon name="send" class="w-[18px] h-[18px]" />
        </button>
      </form>
    </div>
  </AppLayout>
</template>
