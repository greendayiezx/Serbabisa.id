<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref, watch } from 'vue'
import lottie, { type AnimationItem } from 'lottie-web'

const props = defineProps<{
  data: object
  size?: number
  width?: number
  height?: number
}>()

const el = ref<HTMLDivElement | null>(null)
let anim: AnimationItem | null = null

function play() {
  if (!el.value) return
  anim = lottie.loadAnimation({
    container: el.value,
    renderer: 'svg',
    loop: true,
    autoplay: true,
    animationData: props.data,
  })
}

onMounted(play)

watch(
  () => props.data,
  () => {
    anim?.destroy()
    play()
  },
)

onBeforeUnmount(() => {
  anim?.destroy()
  anim = null
})
</script>

<template>
  <div
    ref="el"
    :style="{
      width: (width ?? size ?? 40) + 'px',
      height: (height ?? size ?? 40) + 'px'
    }"
  ></div>
</template>
