<script setup lang="ts">
/**
 * Kotak tanda tangan — digambar langsung di tempat.
 *
 * Kanvasnya dibuat seukuran piksel perangkat (devicePixelRatio) supaya garisnya
 * tidak buram di layar beresolusi tinggi; koordinat sentuhan dikonversi dari
 * ukuran CSS ke ukuran kanvas, bukan dipakai mentah.
 *
 * Memakai Pointer Events, bukan mouse + touch terpisah: satu jalur peristiwa
 * yang sama melayani jari, stylus, dan tetikus tanpa penanganan ganda.
 */
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'

const props = withDefaults(
  defineProps<{
    /** Tinggi area tanda tangan dalam piksel CSS. */
    tinggi?: number
    /** Tandai merah kalau wajib dan masih kosong. */
    ditandai?: boolean
  }>(),
  { tinggi: 180, ditandai: false },
)

/** PNG data URL, atau string kosong kalau belum ada coretan. */
const model = defineModel<string>({ default: '' })

const wadah = ref<HTMLDivElement | null>(null)
const kanvas = ref<HTMLCanvasElement | null>(null)
const adaCoretan = ref(false)

let ctx: CanvasRenderingContext2D | null = null
let menggambar = false
let pointerAktif: number | null = null

/**
 * Ukuran kanvas mengikuti lebar wadahnya.
 *
 * Mengubah width/height kanvas MENGHAPUS isinya, jadi coretan yang sudah ada
 * digambar ulang dari data URL-nya — kalau tidak, memutar layar akan
 * menghilangkan tanda tangan yang sudah dibuat.
 */
function siapkanKanvas() {
  const el = kanvas.value
  const box = wadah.value
  if (!el || !box) return

  const rasio = window.devicePixelRatio || 1
  const lebarCss = box.clientWidth
  const tinggiCss = props.tinggi

  const sebelumnya = adaCoretan.value ? model.value : ''

  el.width = Math.round(lebarCss * rasio)
  el.height = Math.round(tinggiCss * rasio)
  el.style.width = `${lebarCss}px`
  el.style.height = `${tinggiCss}px`

  ctx = el.getContext('2d')
  if (!ctx) return

  ctx.scale(rasio, rasio)
  ctx.lineWidth = 2.5
  ctx.lineCap = 'round'
  ctx.lineJoin = 'round'
  ctx.strokeStyle = '#1b1c1b'

  if (sebelumnya) {
    const img = new Image()
    img.onload = () => ctx?.drawImage(img, 0, 0, lebarCss, tinggiCss)
    img.src = sebelumnya
  }
}

let pengamat: ResizeObserver | null = null

onMounted(() => {
  siapkanKanvas()
  if (typeof ResizeObserver !== 'undefined' && wadah.value) {
    pengamat = new ResizeObserver(() => siapkanKanvas())
    pengamat.observe(wadah.value)
  }
})

onBeforeUnmount(() => pengamat?.disconnect())

// Dikosongkan dari luar (mis. induk mereset form) → kanvas ikut bersih.
watch(model, (v) => {
  if (!v && adaCoretan.value) bersihkanKanvas()
})

function titik(e: PointerEvent): [number, number] {
  const r = kanvas.value!.getBoundingClientRect()
  return [e.clientX - r.left, e.clientY - r.top]
}

function mulai(e: PointerEvent) {
  if (!ctx || !kanvas.value) return
  // Tangkap pointer supaya coretan tidak putus saat jari keluar kotak.
  kanvas.value.setPointerCapture(e.pointerId)
  pointerAktif = e.pointerId
  menggambar = true

  const [x, y] = titik(e)
  ctx.beginPath()
  ctx.moveTo(x, y)
  // Ketukan tunggal tanpa gerakan tetap meninggalkan titik.
  ctx.lineTo(x, y)
  ctx.stroke()
}

function gerak(e: PointerEvent) {
  if (!menggambar || !ctx || e.pointerId !== pointerAktif) return
  const [x, y] = titik(e)
  ctx.lineTo(x, y)
  ctx.stroke()
}

function selesai(e: PointerEvent) {
  if (!menggambar || e.pointerId !== pointerAktif) return
  menggambar = false
  pointerAktif = null
  adaCoretan.value = true
  simpan()
}

function simpan() {
  if (!kanvas.value) return
  model.value = kanvas.value.toDataURL('image/png')
}

function bersihkanKanvas() {
  const el = kanvas.value
  if (!ctx || !el) return
  ctx.clearRect(0, 0, el.width, el.height)
  adaCoretan.value = false
}

function hapus() {
  bersihkanKanvas()
  model.value = ''
}

defineExpose({ hapus })
</script>

<template>
  <div>
    <div
      ref="wadah"
      class="relative rounded-2xl border-2 bg-white overflow-hidden"
      :class="
        ditandai && !adaCoretan
          ? 'border-(--color-error)'
          : adaCoretan
            ? 'border-(--color-azure)'
            : 'border-(--color-outline)/30'
      "
      :style="{ height: `${tinggi}px` }"
    >
      <canvas
        ref="kanvas"
        class="relative block cursor-crosshair touch-none"
        aria-label="Area tanda tangan"
        @pointerdown.prevent="mulai"
        @pointermove.prevent="gerak"
        @pointerup="selesai"
        @pointercancel="selesai"
      ></canvas>
    </div>

    <div class="flex items-center justify-between gap-3 mt-1.5">
      <p class="text-[11px] text-(--color-on-surface-variant) leading-snug">
        Tanda tangan ini menyertai berkas permintaanmu.
      </p>
      <button
        v-if="adaCoretan"
        type="button"
        class="shrink-0 text-[12px] font-bold text-(--color-azure) underline underline-offset-4 active:scale-95 transition-transform"
        @click="hapus"
      >
        Ulangi
      </button>
    </div>
  </div>
</template>
