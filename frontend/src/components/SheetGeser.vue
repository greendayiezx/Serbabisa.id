<script setup lang="ts">
/**
 * Lembar bawah yang bisa digeser: mengintip di bawah, penuh saat ditarik.
 *
 * Dibuat untuk layar perjalanan — peta harus tetap terlihat sementara isian di
 * bawahnya panjang. Dua titik henti saja, mengintip dan penuh; titik henti
 * ketiga di tengah membuat orang berhenti di tempat yang tidak dirancang untuk
 * apa pun.
 *
 * GESERAN HANYA DARI KEPALANYA, bukan dari seluruh badan lembar. Isinya bisa
 * digulung sendiri, dan satu gerakan jari yang berarti dua hal sekaligus —
 * menggulung isi atau menarik lembar — akan salah tafsir setiap kali isinya
 * kebetulan sedang di posisi paling atas.
 *
 * Ketukan pada kepalanya juga membuka dan menutup: menarik lembar butuh
 * gerakan yang tidak bisa dilakukan lewat papan tik atau pembaca layar, jadi
 * tanpa ketukan ini isi yang tersembunyi tidak punya jalan masuk sama sekali.
 */
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    /** Tinggi bagian yang terlihat saat mengintip, dalam piksel. */
    puncak?: number
    /** Tinggi lembar saat penuh, sebagai pecahan tinggi layar. */
    penuh?: number
    /** Label tombol kepala untuk pembaca layar. */
    label?: string
  }>(),
  {
    puncak: 232,
    penuh: 0.88,
    label: 'Detail perjalanan',
  },
)

/** Terbuka penuh atau tidak. */
const terbuka = defineModel<boolean>({ default: false })

const lembarEl = ref<HTMLElement | null>(null)
const tinggiLembar = ref(0)

/** Seberapa jauh lembar diturunkan dari posisi penuh, dalam piksel. */
const turun = ref(0)
const menggeser = ref(false)

const turunMaks = computed(() => Math.max(0, tinggiLembar.value - props.puncak))

function ukur() {
  tinggiLembar.value = lembarEl.value?.offsetHeight ?? 0
  if (!menggeser.value) turun.value = terbuka.value ? 0 : turunMaks.value
}

let pengamat: ResizeObserver | null = null

onMounted(() => {
  ukur()
  if (lembarEl.value) {
    pengamat = new ResizeObserver(ukur)
    pengamat.observe(lembarEl.value)
  }
  window.addEventListener('resize', ukur)
})

onBeforeUnmount(() => {
  pengamat?.disconnect()
  window.removeEventListener('resize', ukur)
  window.removeEventListener('pointermove', geser)
  window.removeEventListener('pointerup', lepas)
  window.removeEventListener('pointercancel', lepas)
})

watch(terbuka, (buka) => {
  if (!menggeser.value) turun.value = buka ? 0 : turunMaks.value
})

let mulaiY = 0
let mulaiTurun = 0
let waktuMulai = 0
/*
 * Geseran sungguhan menekan ketukan yang menyusul.
 *
 * Peramban tetap mengirim `click` sesudah pointerup meski jarinya baru saja
 * menarik lembar. Tanpa penanda ini, satu tarikan akan mengubah keadaan dua
 * kali — dibuka oleh geserannya, lalu ditutup lagi oleh ketukannya.
 */
let sempatGeser = false

function tangkap(e: PointerEvent) {
  // Hanya tombol kiri / sentuhan; klik kanan tidak menarik apa pun.
  if (e.button !== 0) return
  menggeser.value = true
  mulaiY = e.clientY
  mulaiTurun = turun.value
  waktuMulai = performance.now()
  sempatGeser = false

  /*
   * Pendengarnya dipasang di window, bukan di elemennya.
   *
   * Jari yang bergerak lebih cepat daripada lembarnya akan keluar dari kepala
   * lembar di tengah geseran; kalau pendengarnya menempel di situ, geserannya
   * berhenti di tengah jalan dan lembarnya menggantung.
   */
  window.addEventListener('pointermove', geser, { passive: false })
  window.addEventListener('pointerup', lepas)
  window.addEventListener('pointercancel', lepas)
}

function geser(e: PointerEvent) {
  if (!menggeser.value) return
  e.preventDefault()
  // Lebih dari beberapa piksel berarti ini geseran, bukan ketukan yang meleset.
  if (Math.abs(e.clientY - mulaiY) > 4) sempatGeser = true
  const y = mulaiTurun + (e.clientY - mulaiY)
  turun.value = Math.min(turunMaks.value, Math.max(0, y))
}

function lepas(e: PointerEvent) {
  if (!menggeser.value) return
  menggeser.value = false
  window.removeEventListener('pointermove', geser)
  window.removeEventListener('pointerup', lepas)
  window.removeEventListener('pointercancel', lepas)

  const jarak = e.clientY - mulaiY
  const lama = Math.max(1, performance.now() - waktuMulai)
  const laju = jarak / lama // piksel per milidetik

  /*
   * Lemparan cepat menang atas jarak.
   *
   * Tanpa ini, tarikan pendek tapi tegas akan memantul balik karena belum
   * melewati setengah jalan — dan lembar yang memantul terbaca seperti
   * aplikasi yang tidak mendengar.
   */
  if (Math.abs(laju) > 0.5) {
    terbuka.value = laju < 0
  } else {
    terbuka.value = turun.value < turunMaks.value / 2
  }
  turun.value = terbuka.value ? 0 : turunMaks.value
}

function ketuk() {
  if (sempatGeser) {
    sempatGeser = false
    return
  }
  terbuka.value = !terbuka.value
}
</script>

<template>
  <section
    ref="lembarEl"
    class="fixed inset-x-0 bottom-0 z-30 mx-auto max-w-[430px] rounded-t-3xl bg-(--color-surface-0) shadow-[0_-12px_40px_rgba(0,0,0,0.18)] flex flex-col"
    :style="{
      height: `${penuh * 100}dvh`,
      transform: `translateY(${turun}px)`,
      transition: menggeser ? 'none' : 'transform 260ms cubic-bezier(0.22, 1, 0.36, 1)',
    }"
  >
    <!-- Kepala: pegangan geser, sekaligus tombol buka-tutup -->
    <button
      type="button"
      class="shrink-0 w-full pt-2.5 pb-1 touch-none cursor-grab active:cursor-grabbing"
      :aria-expanded="terbuka"
      :aria-label="terbuka ? `Tutup ${label}` : `Buka ${label}`"
      @pointerdown="tangkap"
      @click="ketuk"
    >
      <span class="block w-10 h-1.5 rounded-full bg-(--color-outline)/40 mx-auto"></span>
    </button>

    <div class="flex-1 min-h-0 overflow-y-auto overscroll-contain">
      <slot />
    </div>
  </section>
</template>

<style scoped>
@media (prefers-reduced-motion: reduce) {
  section {
    transition: none !important;
  }
}
</style>
