<script setup lang="ts">
/**
 * Navbar BisaBersih — tumbuh saat halaman digulung.
 *
 * Di puncak halaman hanya tombol kembali yang tampil, melayang di atas hero.
 * Batangnya sendiri transparan: hero halaman ini menyentuh tepi atas layar, dan
 * batang gelap yang menutupinya membuang bagian gambar yang paling menarik.
 *
 * Begitu digulung melewati ambang, latarnya memadat dan nama layanannya muncul
 * — di titik itu hero sudah tergulung pergi, dan pengguna butuh penanda sedang
 * berada di halaman apa.
 */
import { onBeforeUnmount, onMounted, ref } from 'vue'
import Icon from '@/components/icons/Icon.vue'
import { useKembali } from '@/composables/useKembali'

const props = withDefaults(
  defineProps<{
    /**
     * Jarak gulir sebelum batangnya memadat. Bawaannya sedikit di bawah tinggi
     * hero, jadi peralihannya terjadi ketika gambar hampir habis tergulung.
     */
    ambang?: number
  }>(),
  { ambang: 120 },
)

const goBackOrHome = useKembali()
const padat = ref(false)

function pantau() {
  padat.value = window.scrollY > props.ambang
}

onMounted(() => {
  // passive: pendengar ini tidak pernah membatalkan gulirannya, dan menyatakan
  // itu membuat peramban tidak perlu menunggu sebelum menggulung.
  window.addEventListener('scroll', pantau, { passive: true })
  pantau()
})

onBeforeUnmount(() => window.removeEventListener('scroll', pantau))
</script>

<template>
  <nav
    class="fixed top-0 inset-x-0 z-50 px-4 py-3 flex items-center justify-between transition-colors duration-300"
    :class="
      padat
        ? 'bg-[#0B192C] text-white shadow-md border-b border-slate-800'
        : 'bg-transparent border-b border-transparent'
    "
  >
    <div class="flex items-center gap-3">
      <button
        type="button"
        aria-label="Kembali"
        class="w-9 h-9 rounded-full flex items-center justify-center active:scale-95 transition-all"
        :class="padat ? 'bg-slate-800/80 hover:bg-slate-700 text-white' : 'bg-black/35 text-white backdrop-blur-xs'"
        @click="goBackOrHome"
      >
        <Icon name="arrow-left" class="w-5 h-5 text-white" />
      </button>

      <!--
        Nama layanan ikut hilang saat batangnya bening. Dibiarkan tampil di atas
        hero, ia bertumpuk dengan judul yang sudah ada di dalam gambarnya.

        Memakai opacity + pointer-events, bukan v-if: elemennya tetap ada
        sehingga lebar navbar tidak melompat saat peralihan.
      -->
      <div
        class="flex items-center gap-2 transition-opacity duration-300"
        :class="padat ? 'opacity-100' : 'opacity-0 pointer-events-none'"
        :aria-hidden="!padat"
      >
        <svg
          class="w-7 h-7 text-[#38BDF8]"
          viewBox="0 0 24 24"
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <path d="M12 2L14.5 9.5L22 12L14.5 14.5L12 22L9.5 14.5L2 12L9.5 9.5L12 2Z" fill="currentColor" />
          <path
            d="M19 1.5L20.2 5.2L24 6.5L20.2 7.8L19 11.5L17.8 7.8L14 6.5L17.8 5.2L19 1.5Z"
            fill="#8BC53F"
            opacity="0.9"
          />
        </svg>
        <span class="font-extrabold text-lg tracking-tight text-white">BisaBersih</span>
      </div>
    </div>
  </nav>
</template>
