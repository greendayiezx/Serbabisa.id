<script setup lang="ts">
/**
 * Isian kontak yang ditemui di lokasi — pola yang sama dengan Detail Penerima
 * di BisaAngkut: nama, pemilih kode negara berbendera, dan tombol "Pakai detail
 * saya" untuk mengisi dari akun.
 *
 * Nomornya disimpan sebagai SATU string berkode negara (+6281…), bukan dua
 * kolom terpisah. Yang membaca nomor ini nanti adalah teknisi yang menekan
 * dial — dan nomor tanpa kode negara tidak bisa ditekan begitu saja.
 *
 * Saat diisi dari akun, kode negara yang cocok dikenali dari awalan nomornya,
 * bukan dipaksa +62: pengguna dengan nomor luar negeri akan kehilangan
 * kodenya kalau dipaksa.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import Icon from '@/components/icons/Icon.vue'
import { useAuthStore } from '@/stores/auth'

interface Negara {
  code: string
  iso: string
  name: string
}

const props = withDefaults(
  defineProps<{
    judul?: string
    /** Tandai merah kalau wajib dan masih kosong. */
    ditandai?: boolean
  }>(),
  { judul: 'Data Pemesan', ditandai: false },
)

const nama = defineModel<string>('nama', { default: '' })
/** Nomor lengkap berkode negara, mis. "+6281200001111". */
const telepon = defineModel<string>('telepon', { default: '' })

const authStore = useAuthStore()

const NEGARA: Negara[] = [
  { code: '+62', iso: 'id', name: 'Indonesia' },
  { code: '+60', iso: 'my', name: 'Malaysia' },
  { code: '+65', iso: 'sg', name: 'Singapura' },
  { code: '+61', iso: 'au', name: 'Australia' },
  { code: '+81', iso: 'jp', name: 'Jepang' },
  { code: '+1', iso: 'us', name: 'Amerika Serikat' },
  { code: '+44', iso: 'gb', name: 'Inggris' },
  { code: '+66', iso: 'th', name: 'Thailand' },
  { code: '+84', iso: 'vn', name: 'Vietnam' },
  { code: '+63', iso: 'ph', name: 'Filipina' },
  { code: '+82', iso: 'kr', name: 'Korea Selatan' },
  { code: '+86', iso: 'cn', name: 'Tiongkok' },
  { code: '+91', iso: 'in', name: 'India' },
  { code: '+966', iso: 'sa', name: 'Arab Saudi' },
]

function benderaUrl(iso: string) {
  return `https://flagcdn.com/w40/${iso}.png`
}

const negara = ref<Negara>(NEGARA[0])
const nomorLokal = ref('')
const dropdownBuka = ref(false)
const dropdownEl = ref<HTMLElement | null>(null)

/*
 * Kode negara terpanjang diperiksa lebih dulu. Diurutkan dari yang pendek,
 * "+62" akan cocok lebih dulu untuk nomor +6281… tapi "+1" juga cocok untuk
 * +1966…, dan yang salah cocok memenggal nomornya.
 */
function pisahkan(penuh: string) {
  const bersih = penuh.trim()
  const cocok = [...NEGARA]
    .sort((a, b) => b.code.length - a.code.length)
    .find((n) => bersih.startsWith(n.code))

  if (cocok) {
    negara.value = cocok
    nomorLokal.value = bersih.slice(cocok.code.length)
    return
  }

  // Nomor lokal tanpa kode: 08xx dianggap Indonesia, angka 0 di depan dibuang
  // karena kode negara sudah menggantikannya.
  negara.value = NEGARA[0]
  nomorLokal.value = bersih.replace(/^0+/, '')
}

function gabung() {
  const angka = nomorLokal.value.replace(/[^\d]/g, '').replace(/^0+/, '')
  telepon.value = angka ? `${negara.value.code}${angka}` : ''
}

function pilihNegara(n: Negara) {
  negara.value = n
  dropdownBuka.value = false
  gabung()
}

function pakaiDetailSaya() {
  nama.value = authStore.user?.name ?? ''
  pisahkan(authStore.user?.phone ?? '')
  gabung()
}

const kosongDanDitandai = computed(() => props.ditandai && (!nama.value.trim() || !telepon.value))

function klikDiLuar(e: MouseEvent) {
  if (dropdownEl.value && !dropdownEl.value.contains(e.target as Node)) {
    dropdownBuka.value = false
  }
}

onMounted(() => {
  document.addEventListener('click', klikDiLuar)
  if (telepon.value) pisahkan(telepon.value)
})

onBeforeUnmount(() => document.removeEventListener('click', klikDiLuar))
</script>

<template>
  <section class="bg-(--color-surface-0) rounded-2xl p-5">
    <div class="flex items-center justify-between gap-3 mb-4">
      <h2 class="text-[14px] font-display font-extrabold">{{ judul }}</h2>
      <button
        type="button"
        class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
        @click="pakaiDetailSaya"
      >
        Pakai detail saya
      </button>
    </div>

    <div class="mb-4">
      <label
        class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5"
      >
        Nama lengkap<span class="text-(--color-error)">*</span>
      </label>
      <input
        v-model="nama"
        type="text"
        placeholder="Nama yang ditemui teknisi…"
        class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 outline-none focus:border-(--color-azure) placeholder:text-(--color-on-surface-variant)"
        :class="ditandai && !nama.trim() ? 'border-(--color-error)' : 'border-transparent'"
      />
    </div>

    <div>
      <label
        class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5"
      >
        Nomor telepon<span class="text-(--color-error)">*</span>
      </label>

      <div class="flex items-center gap-3">
        <div ref="dropdownEl" class="relative shrink-0">
          <button
            type="button"
            class="flex items-center gap-1.5 rounded-full border border-(--color-outline)/40 bg-(--color-surface-0) px-3.5 py-1.5 text-[13px] font-bold shadow-xs active:scale-95 transition-transform"
            :aria-expanded="dropdownBuka"
            aria-label="Pilih kode negara"
            @click.stop="dropdownBuka = !dropdownBuka"
          >
            <img
              :src="benderaUrl(negara.iso)"
              :alt="negara.name"
              class="w-5 h-3.5 rounded-[3px] object-cover shrink-0"
            />
            <span>{{ negara.code }}</span>
            <Icon
              name="chevron-down"
              class="w-3.5 h-3.5 text-(--color-on-surface-variant) transition-transform"
              :class="{ 'rotate-180': dropdownBuka }"
            />
          </button>

          <div
            v-if="dropdownBuka"
            class="absolute left-0 top-full mt-1.5 z-40 w-56 rounded-2xl bg-(--color-surface-0) shadow-xl border border-(--color-outline)/40 py-1.5 max-h-60 overflow-y-auto"
          >
            <button
              v-for="n in NEGARA"
              :key="n.code + n.iso"
              type="button"
              class="w-full flex items-center gap-2.5 px-3.5 py-2 text-[12px] font-semibold text-left active:bg-(--color-surface-container) transition-colors"
              :class="{ 'bg-(--color-primary-container)/40 font-bold': n.code === negara.code }"
              @click="pilihNegara(n)"
            >
              <img
                :src="benderaUrl(n.iso)"
                :alt="n.name"
                class="w-5 h-3.5 rounded-[3px] object-cover shrink-0"
              />
              <span class="font-bold min-w-10">{{ n.code }}</span>
              <span class="truncate text-(--color-on-surface-variant)">{{ n.name }}</span>
            </button>
          </div>
        </div>

        <div
          class="flex-1 border-b-2 transition-colors"
          :class="ditandai && !telepon ? 'border-(--color-error)' : 'border-(--color-outline)/40'"
        >
          <input
            v-model="nomorLokal"
            type="tel"
            inputmode="tel"
            placeholder="Masukkan nomor telepon"
            class="w-full bg-transparent text-[13px] font-medium outline-none placeholder:text-(--color-on-surface-variant)/70 py-1.5 px-1"
            @input="gabung"
          />
        </div>
      </div>
    </div>

    <p v-if="kosongDanDitandai" class="mt-3 text-[11.5px] font-semibold text-(--color-error)">
      Nama dan nomor telepon dibutuhkan supaya teknisi bisa menghubungi saat tiba.
    </p>
  </section>
</template>
