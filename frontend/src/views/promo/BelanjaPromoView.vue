<script setup lang="ts">
import { computed } from 'vue'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { useBelanjaDraftStore } from '@/stores/belanjaDraft'
import { usePromoStore } from '@/stores/promo'
import { BIAYA_LAYANAN, ongkirUntuk } from '@/lib/belanja/ongkir'
import {
  LABEL_GRUP,
  PROMO,
  hitungPromo,
  totalHemat,
  type GrupPromo,
  type KonteksPromo,
} from '@/lib/promo/promoBelanja'

const belanjaDraft = useBelanjaDraftStore()
const promoStore = usePromoStore()

/** Sama dengan halaman checkout: jarak masih nilai sementara sampai gerai punya koordinat. */
const JARAK_SEMENTARA_KM = 4.2

const totalBarang = computed(() =>
  belanjaDraft.draft.items.reduce((s, i) => s + (i.harga ?? 0) * i.qty, 0),
)

const subtotalKategori = computed(() => {
  const peta: Record<string, number> = {}
  for (const i of belanjaDraft.draft.items) {
    peta[i.kategori] = (peta[i.kategori] ?? 0) + (i.harga ?? 0) * i.qty
  }
  return peta
})

const konteks = computed<KonteksPromo>(() => ({
  totalBarang: totalBarang.value,
  biayaLayanan: BIAYA_LAYANAN,
  ongkir: ongkirUntuk(JARAK_SEMENTARA_KM),
  transaksiKe: promoStore.state.transaksiSelesai + 1,
  waktu: new Date(),
  subtotalKategori: subtotalKategori.value,
}))

const dinilai = computed(() =>
  PROMO.map((promo) => {
    const hasil = hitungPromo(promo, konteks.value)
    return { promo, hasil, hemat: totalHemat(hasil) + hasil.cashback }
  }),
)

/** Dikelompokkan per grup; grup yang punya promo aktif naik ke atas. */
const grup = computed(() => {
  const peta = new Map<GrupPromo, typeof dinilai.value>()
  for (const d of dinilai.value) {
    if (!peta.has(d.promo.grup)) peta.set(d.promo.grup, [])
    peta.get(d.promo.grup)!.push(d)
  }
  return [...peta.entries()]
    .map(([id, daftar]) => ({
      id,
      judul: LABEL_GRUP[id],
      daftar,
      adaYangBerlaku: daftar.some((d) => d.hasil.berlaku),
    }))
    .sort((a, b) => Number(b.adaYangBerlaku) - Number(a.adaYangBerlaku))
})

const jumlahBisaDipakai = computed(() => dinilai.value.filter((d) => d.hasil.berlaku).length)
const dipilih = computed(() => promoStore.state.dipilih)

function pakai(id: string, bisa: boolean) {
  // Pengaman: promo yang belum memenuhi syarat tidak boleh terpasang.
  if (!bisa) return
  promoStore.pilih(dipilih.value === id ? null : id)
}

const kembali = useKembali()

function rp(n: number) {
  return 'Rp' + n.toLocaleString('id-ID')
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
    <header class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/10">
      <div class="max-w-[430px] mx-auto h-16 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Promo</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-5">
      <p class="text-[12px] text-(--color-on-surface-variant)">
        <span class="font-extrabold text-(--color-on-surface)">{{ jumlahBisaDipakai }} promo</span>
        bisa dipakai untuk belanja {{ rp(totalBarang) }} ini. Hanya satu promo yang bisa aktif sekaligus.
      </p>

      <!-- Voucher per grup -->
      <section v-for="g in grup" :key="g.id">
        <h2 class="text-[13px] font-extrabold mb-2.5">{{ g.judul }}</h2>
        <div class="flex flex-col gap-2.5">
          <div
            v-for="d in g.daftar"
            :key="d.promo.id"
            class="bg-(--color-surface-0) rounded-2xl border-2 p-3.5 transition-colors"
            :class="[
              dipilih === d.promo.id ? 'border-(--color-azure)' : 'border-transparent',
              d.hasil.berlaku ? '' : 'opacity-70',
            ]"
          >
            <div class="flex items-start gap-3">
              <svg
                v-if="g.id === 'tier'"
                viewBox="0 0 500 500"
                xmlns="http://www.w3.org/2000/svg"
                class="w-10 h-10 shrink-0"
              >
                <defs>
                  <linearGradient id="tier-blueTag" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#5CC8FF"/>
                    <stop offset="100%" stop-color="#0C86DE"/>
                  </linearGradient>
                  <linearGradient id="tier-yellowTag" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFDC5C"/>
                    <stop offset="100%" stop-color="#F5A200"/>
                  </linearGradient>
                  <linearGradient id="tier-ringGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFFFFF"/>
                    <stop offset="100%" stop-color="#B9CBE4"/>
                  </linearGradient>
                  <linearGradient id="tier-navy" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#3A7BE8"/>
                    <stop offset="100%" stop-color="#12327F"/>
                  </linearGradient>
                  <linearGradient id="tier-orange" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFC061"/>
                    <stop offset="100%" stop-color="#FF8A00"/>
                  </linearGradient>
                  <linearGradient id="tier-green" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#A2EE4F"/>
                    <stop offset="100%" stop-color="#57BC18"/>
                  </linearGradient>
                  <linearGradient id="tier-yellowSpark" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE472"/>
                    <stop offset="100%" stop-color="#FFB000"/>
                  </linearGradient>

                  <filter id="tier-shadow" x="-40%" y="-40%" width="180%" height="180%">
                    <feDropShadow dx="0" dy="9" stdDeviation="9" flood-color="#0B2E5C" flood-opacity="0.28"/>
                  </filter>
                  <filter id="tier-shadowSm" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0B2E5C" flood-opacity="0.25"/>
                  </filter>

                  <!-- Reusable tag shape: rounded square, centered at origin -->
                  <path id="tier-tagShape" d="
                    M -130,-92
                    Q -130,-130 -92,-130
                    L 92,-130
                    Q 130,-130 130,-92
                    L 130,92
                    Q 130,130 92,130
                    L -92,130
                    Q -130,130 -130,92
                    Z"/>
                </defs>

                <!-- ============ Sparkle accents ============ -->
                <g filter="url(#tier-shadowSm)">
                  <!-- yellow, right side -->
                  <g fill="url(#tier-yellowSpark)">
                    <path d="M400,120 q14,-6 20,7 l14,30 q6,13 -8,15 l-30,5 q-14,2 -14,-12 l0,-32 q0,-14 18,-13 Z"/>
                    <path d="M448,186 q14,-4 18,9 l10,32 q4,13 -10,14 l-32,2 q-14,1 -12,-13 l4,-31 q2,-14 22,-13 Z"/>
                    <path d="M436,258 q14,-4 18,9 l9,28 q4,13 -10,14 l-30,2 q-14,1 -12,-13 l3,-27 q2,-14 22,-13 Z"/>
                  </g>
                  <!-- green, bottom-left -->
                  <g fill="url(#tier-green)">
                    <path d="M62,352 q-6,-14 8,-15 l44,-4 q14,-1 12,13 l-6,42 q-2,14 -15,7 l-30,-16 q-13,-7 -13,-27 Z"/>
                    <path d="M96,414 q-4,-14 10,-14 l32,-1 q14,0 11,13 l-8,31 q-3,13 -16,6 l-19,-11 q-11,-6 -10,-24 Z"/>
                    <path d="M168,432 q-2,-14 12,-13 l24,3 q14,2 9,15 l-11,28 q-5,13 -17,4 l-12,-10 q-9,-8 -5,-27 Z"/>
                  </g>
                </g>

                <!-- ============ Metal ring ============ -->
                <g transform="translate(148,126) rotate(-38)" filter="url(#tier-shadowSm)">
                  <ellipse cx="0" cy="0" rx="34" ry="52" fill="none" stroke="url(#tier-ringGrad)" stroke-width="15"/>
                  <ellipse cx="0" cy="0" rx="34" ry="52" fill="none" stroke="#FFFFFF" stroke-width="4" opacity="0.6"/>
                </g>

                <!-- ============ Blue tag (behind) ============ -->
                <g filter="url(#tier-shadow)">
                  <g transform="translate(200,238) rotate(45) scale(0.92)">
                    <use href="#tier-tagShape" fill="url(#tier-blueTag)"/>
                  </g>
                  <!-- eyelet hole near top corner -->
                  <circle cx="200" cy="118" r="21" fill="#0A3C86"/>
                  <circle cx="200" cy="116" r="15" fill="#062A63"/>
                </g>

                <!-- ============ Yellow tag (front) ============ -->
                <g filter="url(#tier-shadow)">
                  <g transform="translate(272,282) rotate(45)">
                    <use href="#tier-tagShape" fill="url(#tier-yellowTag)"/>
                    <!-- crisp inner outline, same shape scaled down -->
                    <g transform="scale(0.87)">
                      <use href="#tier-tagShape" fill="none" stroke="#FFF0B8" stroke-width="6" opacity="0.85"/>
                    </g>
                    <!-- subtle top highlight -->
                    <path d="M -104,-108 Q -60,-124 20,-120" fill="none" stroke="#FFFFFF" stroke-width="7"
                          stroke-linecap="round" opacity="0.45"/>
                  </g>
                </g>

                <!-- ============ Percent symbol (clean & centered) ============ -->
                <g transform="translate(272,282)">
                  <!-- diagonal bar -->
                  <g transform="rotate(38)">
                    <rect x="-19" y="-86" width="38" height="172" rx="19"
                          fill="url(#tier-navy)" stroke="#FFFFFF" stroke-width="7"/>
                  </g>
                  <!-- upper-left circle -->
                  <circle cx="-52" cy="-52" r="36" fill="url(#tier-navy)" stroke="#FFFFFF" stroke-width="7"/>
                  <circle cx="-52" cy="-52" r="13" fill="url(#tier-orange)"/>
                  <!-- lower-right circle -->
                  <circle cx="52" cy="52" r="36" fill="url(#tier-navy)" stroke="#FFFFFF" stroke-width="7"/>
                  <circle cx="52" cy="52" r="13" fill="url(#tier-orange)"/>
                </g>
              </svg>
              <svg
                v-else-if="g.id === 'first-order'"
                viewBox="0 0 500 500"
                xmlns="http://www.w3.org/2000/svg"
                class="w-10 h-10 shrink-0"
              >
                <defs>
                  <linearGradient id="fo-blueDisc" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#5CC8FF"/>
                    <stop offset="100%" stop-color="#0C86DE"/>
                  </linearGradient>
                  <linearGradient id="fo-blueDiscDeep" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#2E9DF7"/>
                    <stop offset="100%" stop-color="#0A5FB4"/>
                  </linearGradient>
                  <linearGradient id="fo-navy" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#3A7BE8"/>
                    <stop offset="100%" stop-color="#12327F"/>
                  </linearGradient>
                  <linearGradient id="fo-lime" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#B6F14C"/>
                    <stop offset="100%" stop-color="#63C61C"/>
                  </linearGradient>
                  <linearGradient id="fo-yellowSpark" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE472"/>
                    <stop offset="100%" stop-color="#FFB000"/>
                  </linearGradient>
                  <linearGradient id="fo-orange" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFC061"/>
                    <stop offset="100%" stop-color="#FF8A00"/>
                  </linearGradient>

                  <filter id="fo-shadow" x="-40%" y="-40%" width="180%" height="180%">
                    <feDropShadow dx="0" dy="9" stdDeviation="9" flood-color="#0B2E5C" flood-opacity="0.28"/>
                  </filter>
                  <filter id="fo-shadowSm" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0B2E5C" flood-opacity="0.25"/>
                  </filter>

                  <clipPath id="fo-discClip">
                    <circle cx="240" cy="250" r="130"/>
                  </clipPath>
                </defs>

                <!-- ============ Sparkle accents ============ -->
                <g filter="url(#fo-shadowSm)">
                  <!-- yellow sparkles, upper right -->
                  <g fill="url(#fo-yellowSpark)">
                    <path transform="translate(418,118) rotate(15)"
                          d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                    <path transform="translate(452,208) rotate(-10) scale(0.62)"
                          d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                    <circle cx="392" cy="196" r="11"/>
                  </g>
                  <!-- lime sparkles, lower left -->
                  <g fill="url(#fo-lime)">
                    <path transform="translate(72,382) rotate(-12) scale(0.9)"
                          d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                    <path transform="translate(126,442) rotate(20) scale(0.55)"
                          d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                    <circle cx="60" cy="312" r="10"/>
                  </g>
                </g>

                <!-- ============ Outer ring (behind disc) ============ -->
                <circle cx="240" cy="250" r="152" fill="none" stroke="url(#fo-blueDiscDeep)"
                        stroke-width="14" opacity="0.35"/>
                <circle cx="240" cy="250" r="152" fill="none" stroke="url(#fo-lime)"
                        stroke-width="14" stroke-linecap="round"
                        stroke-dasharray="120 835" stroke-dashoffset="-460"/>

                <!-- ============ Avatar disc ============ -->
                <g filter="url(#fo-shadow)">
                  <circle cx="240" cy="250" r="130" fill="url(#fo-blueDisc)"/>
                  <circle cx="240" cy="250" r="130" fill="none" stroke="#FFFFFF" stroke-width="8" opacity="0.9"/>

                  <!-- person silhouette, clipped to disc -->
                  <g clip-path="url(#fo-discClip)">
                    <!-- shoulders -->
                    <path d="M240,258 c62,0 106,44 106,104 v70 H134 v-70 c0,-60 44,-104 106,-104 Z"
                          fill="url(#fo-navy)"/>
                    <path d="M240,258 c62,0 106,44 106,104 v70 H134 v-70 c0,-60 44,-104 106,-104 Z"
                          fill="none" stroke="#FFFFFF" stroke-width="8" opacity="0.85"/>
                    <!-- head -->
                    <circle cx="240" cy="196" r="56" fill="url(#fo-navy)" stroke="#FFFFFF" stroke-width="8"/>
                  </g>

                  <!-- glossy highlight -->
                  <path d="M148,168 q40,-52 116,-58" fill="none" stroke="#FFFFFF"
                        stroke-width="12" stroke-linecap="round" opacity="0.35"/>
                </g>

                <!-- ============ "New" plus badge ============ -->
                <g filter="url(#fo-shadow)" transform="translate(374,366)">
                  <circle cx="0" cy="0" r="66" fill="url(#fo-lime)" stroke="#FFFFFF" stroke-width="9"/>
                  <g stroke="#FFFFFF" stroke-width="16" stroke-linecap="round">
                    <line x1="-28" y1="0" x2="28" y2="0"/>
                    <line x1="0" y1="-28" x2="0" y2="28"/>
                  </g>
                </g>

                <!-- small orange dot accent -->
                <circle cx="330" cy="118" r="14" fill="url(#fo-orange)" filter="url(#fo-shadowSm)"/>
              </svg>
              <svg
                v-else-if="g.id === 'gratis-ongkir'"
                viewBox="0 0 500 500"
                xmlns="http://www.w3.org/2000/svg"
                class="w-10 h-10 shrink-0"
              >
                <defs>
                  <linearGradient id="go-blueBody" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#5CC8FF"/>
                    <stop offset="100%" stop-color="#0C86DE"/>
                  </linearGradient>
                  <linearGradient id="go-blueCab" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#2E9DF7"/>
                    <stop offset="100%" stop-color="#0A5FB4"/>
                  </linearGradient>
                  <linearGradient id="go-navy" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#3A7BE8"/>
                    <stop offset="100%" stop-color="#12327F"/>
                  </linearGradient>
                  <linearGradient id="go-lime" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#B6F14C"/>
                    <stop offset="100%" stop-color="#63C61C"/>
                  </linearGradient>
                  <linearGradient id="go-yellowSpark" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE472"/>
                    <stop offset="100%" stop-color="#FFB000"/>
                  </linearGradient>
                  <linearGradient id="go-boxGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFDC5C"/>
                    <stop offset="100%" stop-color="#F5A200"/>
                  </linearGradient>
                  <linearGradient id="go-orange" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFC061"/>
                    <stop offset="100%" stop-color="#FF8A00"/>
                  </linearGradient>

                  <filter id="go-shadow" x="-40%" y="-40%" width="180%" height="180%">
                    <feDropShadow dx="0" dy="9" stdDeviation="9" flood-color="#0B2E5C" flood-opacity="0.28"/>
                  </filter>
                  <filter id="go-shadowSm" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0B2E5C" flood-opacity="0.25"/>
                  </filter>
                </defs>

                <!-- ============ Speed lines (motion) ============ -->
                <g stroke-linecap="round" opacity="0.9">
                  <line x1="30" y1="196" x2="112" y2="196" stroke="url(#go-lime)" stroke-width="16"/>
                  <line x1="14" y1="252" x2="82" y2="252" stroke="url(#go-yellowSpark)" stroke-width="16"/>
                  <line x1="42" y1="308" x2="100" y2="308" stroke="url(#go-lime)" stroke-width="16"/>
                </g>

                <!-- ============ Sparkle accents ============ -->
                <g filter="url(#go-shadowSm)">
                  <path fill="url(#go-yellowSpark)" transform="translate(430,116) rotate(12)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <path fill="url(#go-lime)" transform="translate(84,412) rotate(-14) scale(0.8)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <circle cx="388" cy="90" r="11" fill="url(#go-lime)"/>
                  <circle cx="60" cy="360" r="10" fill="url(#go-orange)"/>
                </g>

                <!-- ============ Truck ============ -->
                <g filter="url(#go-shadow)">
                  <!-- cargo box (rear) -->
                  <rect x="132" y="168" width="196" height="164" rx="26" fill="url(#go-blueBody)"
                        stroke="#FFFFFF" stroke-width="8"/>

                  <!-- cabin (front) -->
                  <path d="M336,222
                           L392,222
                           C404,222 415,228 422,238
                           L456,286
                           C461,293 464,302 464,311
                           L464,306
                           C464,320 453,332 438,332
                           L336,332
                           Z"
                        fill="url(#go-blueCab)" stroke="#FFFFFF" stroke-width="8" stroke-linejoin="round"/>

                  <!-- window -->
                  <path d="M356,244 L392,244 C399,244 405,247 409,253 L430,283 L356,283 Z"
                        fill="#DCF1FF" stroke="#FFFFFF" stroke-width="6" stroke-linejoin="round"/>

                  <!-- headlight -->
                  <rect x="446" y="300" width="20" height="18" rx="8" fill="url(#go-yellowSpark)"/>
                </g>

                <!-- ============ Free-shipping package on cargo ============ -->
                <g filter="url(#go-shadowSm)" transform="translate(230,250)">
                  <rect x="-46" y="-46" width="92" height="92" rx="16" fill="url(#go-boxGrad)"
                        stroke="#FFFFFF" stroke-width="7"/>
                  <line x1="0" y1="-46" x2="0" y2="46" stroke="#FFFFFF" stroke-width="7"/>
                  <line x1="-46" y1="0" x2="46" y2="0" stroke="#FFFFFF" stroke-width="7"/>
                </g>

                <!-- ============ Wheels ============ -->
                <g filter="url(#go-shadow)">
                  <g>
                    <circle cx="200" cy="356" r="52" fill="url(#go-navy)" stroke="#FFFFFF" stroke-width="8"/>
                    <circle cx="200" cy="356" r="18" fill="url(#go-lime)"/>
                  </g>
                  <g>
                    <circle cx="404" cy="356" r="52" fill="url(#go-navy)" stroke="#FFFFFF" stroke-width="8"/>
                    <circle cx="404" cy="356" r="18" fill="url(#go-lime)"/>
                  </g>
                </g>

                <!-- ============ "Gratis" badge (Rp dicoret) ============ -->
                <g filter="url(#go-shadow)" transform="translate(148,140)">
                  <circle cx="0" cy="0" r="62" fill="url(#go-lime)" stroke="#FFFFFF" stroke-width="9"/>
                  <text x="0" y="16" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                        font-size="46" font-weight="800" fill="#FFFFFF">Rp</text>
                  <line x1="-42" y1="34" x2="42" y2="-34" stroke="#FFFFFF" stroke-width="11" stroke-linecap="round"/>
                </g>
              </svg>
              <svg
                v-else-if="g.id === 'jam-emas'"
                viewBox="0 0 500 500"
                xmlns="http://www.w3.org/2000/svg"
                class="w-10 h-10 shrink-0"
              >
                <defs>
                  <linearGradient id="je-goldFace" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE894"/>
                    <stop offset="55%" stop-color="#FFC633"/>
                    <stop offset="100%" stop-color="#F09A00"/>
                  </linearGradient>
                  <linearGradient id="je-goldRim" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFD75C"/>
                    <stop offset="100%" stop-color="#E08900"/>
                  </linearGradient>
                  <linearGradient id="je-blueRing" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#5CC8FF"/>
                    <stop offset="100%" stop-color="#0C86DE"/>
                  </linearGradient>
                  <linearGradient id="je-navy" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#3A7BE8"/>
                    <stop offset="100%" stop-color="#12327F"/>
                  </linearGradient>
                  <linearGradient id="je-lime" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#B6F14C"/>
                    <stop offset="100%" stop-color="#63C61C"/>
                  </linearGradient>
                  <linearGradient id="je-boltGrad" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE472"/>
                    <stop offset="100%" stop-color="#FF9A00"/>
                  </linearGradient>
                  <linearGradient id="je-orange" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFC061"/>
                    <stop offset="100%" stop-color="#FF8A00"/>
                  </linearGradient>

                  <filter id="je-shadow" x="-40%" y="-40%" width="180%" height="180%">
                    <feDropShadow dx="0" dy="9" stdDeviation="9" flood-color="#0B2E5C" flood-opacity="0.28"/>
                  </filter>
                  <filter id="je-shadowSm" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0B2E5C" flood-opacity="0.25"/>
                  </filter>
                </defs>

                <!-- ============ Sparkles & accents ============ -->
                <g filter="url(#je-shadowSm)">
                  <path fill="url(#je-lime)" transform="translate(74,140) rotate(-12) scale(0.85)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <path fill="url(#je-boltGrad)" transform="translate(430,168) rotate(14) scale(0.75)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <path fill="url(#je-lime)" transform="translate(408,398) rotate(20) scale(0.6)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <circle cx="108" cy="392" r="12" fill="url(#je-orange)"/>
                  <circle cx="62" cy="278" r="9" fill="url(#je-boltGrad)"/>
                </g>

                <!-- ============ Motion arcs behind clock ============ -->
                <circle cx="250" cy="272" r="176" fill="none" stroke="url(#je-blueRing)"
                        stroke-width="14" opacity="0.25"/>
                <circle cx="250" cy="272" r="176" fill="none" stroke="url(#je-lime)"
                        stroke-width="14" stroke-linecap="round"
                        stroke-dasharray="130 976" stroke-dashoffset="-560"/>

                <!-- ============ Clock top button & side lugs ============ -->
                <g filter="url(#je-shadowSm)">
                  <rect x="228" y="66" width="44" height="34" rx="12"
                        fill="url(#je-navy)" stroke="#FFFFFF" stroke-width="7"/>
                </g>

                <!-- ============ Clock body ============ -->
                <g filter="url(#je-shadow)">
                  <!-- outer blue rim -->
                  <circle cx="250" cy="272" r="146" fill="url(#je-blueRing)" stroke="#FFFFFF" stroke-width="8"/>
                  <!-- gold face -->
                  <circle cx="250" cy="272" r="120" fill="url(#je-goldFace)" stroke="url(#je-goldRim)" stroke-width="8"/>
                  <!-- inner ring line -->
                  <circle cx="250" cy="272" r="102" fill="none" stroke="#FFFFFF" stroke-width="5" opacity="0.7"/>

                  <!-- hour ticks -->
                  <g stroke="#FFFFFF" stroke-width="9" stroke-linecap="round" opacity="0.95">
                    <line x1="250" y1="168" x2="250" y2="188"/>
                    <line x1="354" y1="272" x2="334" y2="272"/>
                    <line x1="250" y1="376" x2="250" y2="356"/>
                    <line x1="146" y1="272" x2="166" y2="272"/>
                  </g>
                  <g stroke="#FFFFFF" stroke-width="6" stroke-linecap="round" opacity="0.6">
                    <line x1="302" y1="182" x2="294" y2="199"/>
                    <line x1="340" y1="220" x2="323" y2="228"/>
                    <line x1="340" y1="324" x2="323" y2="316"/>
                    <line x1="302" y1="362" x2="294" y2="345"/>
                    <line x1="198" y1="362" x2="206" y2="345"/>
                    <line x1="160" y1="324" x2="177" y2="316"/>
                    <line x1="160" y1="220" x2="177" y2="228"/>
                    <line x1="198" y1="182" x2="206" y2="199"/>
                  </g>

                  <!-- glossy highlight -->
                  <path d="M 168,206 q40,-46 106,-52" fill="none" stroke="#FFFFFF"
                        stroke-width="12" stroke-linecap="round" opacity="0.4"/>
                </g>

                <!-- ============ Lightning bolt (flash) ============ -->
                <g filter="url(#je-shadow)">
                  <path d="M 274,168
                           L 196,282
                           L 244,282
                           L 222,388
                           L 312,258
                           L 262,258
                           Z"
                        fill="url(#je-boltGrad)" stroke="#FFFFFF" stroke-width="10" stroke-linejoin="round"/>
                </g>

                <!-- ============ Clock hands (navy, small over bolt) ============ -->
                <g>
                  <circle cx="250" cy="272" r="17" fill="url(#je-navy)" stroke="#FFFFFF" stroke-width="6"/>
                </g>

                <!-- ============ "%" flash badge ============ -->
                <g filter="url(#je-shadow)" transform="translate(392,332)">
                  <circle cx="0" cy="0" r="60" fill="url(#je-lime)" stroke="#FFFFFF" stroke-width="9"/>
                  <g stroke="#FFFFFF" stroke-width="10" stroke-linecap="round" fill="none">
                    <circle cx="-17" cy="-17" r="10"/>
                    <circle cx="17" cy="17" r="10"/>
                    <line x1="-22" y1="24" x2="22" y2="-24"/>
                  </g>
                </g>
              </svg>
              <svg
                v-else-if="g.id === 'payday'"
                viewBox="0 0 500 500"
                xmlns="http://www.w3.org/2000/svg"
                class="w-10 h-10 shrink-0"
              >
                <defs>
                  <linearGradient id="pd-walletFront" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#5CC8FF"/>
                    <stop offset="100%" stop-color="#0C86DE"/>
                  </linearGradient>
                  <linearGradient id="pd-walletBack" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#2E9DF7"/>
                    <stop offset="100%" stop-color="#0A5FB4"/>
                  </linearGradient>
                  <linearGradient id="pd-navy" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#3A7BE8"/>
                    <stop offset="100%" stop-color="#12327F"/>
                  </linearGradient>
                  <linearGradient id="pd-lime" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#B6F14C"/>
                    <stop offset="100%" stop-color="#63C61C"/>
                  </linearGradient>
                  <linearGradient id="pd-limeDeep" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#9CE348"/>
                    <stop offset="100%" stop-color="#4FA915"/>
                  </linearGradient>
                  <linearGradient id="pd-goldCoin" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE894"/>
                    <stop offset="55%" stop-color="#FFC633"/>
                    <stop offset="100%" stop-color="#F09A00"/>
                  </linearGradient>
                  <linearGradient id="pd-goldRim" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFD75C"/>
                    <stop offset="100%" stop-color="#E08900"/>
                  </linearGradient>
                  <linearGradient id="pd-orange" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFC061"/>
                    <stop offset="100%" stop-color="#FF8A00"/>
                  </linearGradient>

                  <filter id="pd-shadow" x="-40%" y="-40%" width="180%" height="180%">
                    <feDropShadow dx="0" dy="9" stdDeviation="9" flood-color="#0B2E5C" flood-opacity="0.28"/>
                  </filter>
                  <filter id="pd-shadowSm" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0B2E5C" flood-opacity="0.25"/>
                  </filter>
                </defs>

                <!-- ============ Sparkles ============ -->
                <g filter="url(#pd-shadowSm)">
                  <path fill="url(#pd-lime)" transform="translate(60,190) rotate(-12) scale(0.8)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <path fill="url(#pd-goldCoin)" transform="translate(452,246) rotate(14) scale(0.7)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <circle cx="70" cy="400" r="11" fill="url(#pd-orange)"/>
                  <circle cx="440" cy="392" r="10" fill="url(#pd-lime)"/>
                </g>

                <!-- ============ Flying money notes (behind wallet) ============ -->
                <g filter="url(#pd-shadowSm)">
                  <g transform="translate(140,132) rotate(-18)">
                    <rect x="-64" y="-38" width="128" height="76" rx="14"
                          fill="url(#pd-limeDeep)" stroke="#FFFFFF" stroke-width="7"/>
                    <circle cx="0" cy="0" r="21" fill="none" stroke="#FFFFFF" stroke-width="6"/>
                    <text x="0" y="9" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                          font-size="22" font-weight="800" fill="#FFFFFF">Rp</text>
                  </g>
                  <g transform="translate(320,110) rotate(14)">
                    <rect x="-58" y="-34" width="116" height="68" rx="13"
                          fill="url(#pd-lime)" stroke="#FFFFFF" stroke-width="7"/>
                    <circle cx="0" cy="0" r="18" fill="none" stroke="#FFFFFF" stroke-width="6"/>
                    <text x="0" y="8" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                          font-size="20" font-weight="800" fill="#FFFFFF">Rp</text>
                  </g>
                </g>

                <!-- ============ Coins peeking from wallet ============ -->
                <g filter="url(#pd-shadowSm)">
                  <g transform="translate(178,232)">
                    <circle cx="0" cy="0" r="44" fill="url(#pd-goldCoin)" stroke="url(#pd-goldRim)" stroke-width="7"/>
                    <circle cx="0" cy="0" r="30" fill="none" stroke="#FFFFFF" stroke-width="5" opacity="0.8"/>
                    <text x="0" y="12" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                          font-size="30" font-weight="800" fill="#FFFFFF">Rp</text>
                  </g>
                  <g transform="translate(300,222)">
                    <circle cx="0" cy="0" r="52" fill="url(#pd-goldCoin)" stroke="url(#pd-goldRim)" stroke-width="7"/>
                    <circle cx="0" cy="0" r="36" fill="none" stroke="#FFFFFF" stroke-width="5" opacity="0.8"/>
                    <text x="0" y="14" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                          font-size="36" font-weight="800" fill="#FFFFFF">Rp</text>
                  </g>
                </g>

                <!-- ============ Wallet ============ -->
                <g filter="url(#pd-shadow)">
                  <!-- back panel -->
                  <rect x="96" y="228" width="308" height="176" rx="42"
                        fill="url(#pd-walletBack)" stroke="#FFFFFF" stroke-width="8"/>
                  <!-- front panel -->
                  <path d="M 96,290
                           h 308
                           v 72
                           a 42,42 0 0 1 -42,42
                           h -224
                           a 42,42 0 0 1 -42,-42
                           z"
                        fill="url(#pd-walletFront)" stroke="#FFFFFF" stroke-width="8" stroke-linejoin="round"/>
                  <!-- clasp -->
                  <rect x="316" y="308" width="98" height="56" rx="28"
                        fill="url(#pd-navy)" stroke="#FFFFFF" stroke-width="8"/>
                  <circle cx="365" cy="336" r="15" fill="url(#pd-lime)"/>
                  <!-- highlight -->
                  <path d="M 128,318 q46,-10 108,-8" fill="none" stroke="#FFFFFF"
                        stroke-width="10" stroke-linecap="round" opacity="0.35"/>
                </g>

                <!-- ============ Calendar "payday" badge ============ -->
                <g filter="url(#pd-shadow)" transform="translate(392,404)">
                  <rect x="-60" y="-56" width="120" height="112" rx="24"
                        fill="#FFFFFF" stroke="#FFFFFF" stroke-width="6"/>
                  <path d="M -60,-32 h 120 v -0 a 24,24 0 0 0 -24,-24 h -72 a 24,24 0 0 0 -24,24 z"
                        fill="url(#pd-navy)"/>
                  <rect x="-60" y="-56" width="120" height="112" rx="24" fill="none"
                        stroke="url(#pd-navy)" stroke-width="7"/>
                  <!-- rings -->
                  <rect x="-36" y="-70" width="14" height="28" rx="7" fill="url(#pd-navy)"/>
                  <rect x="22" y="-70" width="14" height="28" rx="7" fill="url(#pd-navy)"/>
                  <!-- check mark -->
                  <path d="M -28,6 L -8,28 L 30,-16" fill="none" stroke="url(#pd-lime)"
                        stroke-width="14" stroke-linecap="round" stroke-linejoin="round"/>
                </g>
              </svg>
              <svg
                v-else-if="g.id === 'weekend'"
                viewBox="0 0 500 500"
                xmlns="http://www.w3.org/2000/svg"
                class="w-10 h-10 shrink-0"
              >
                <defs>
                  <linearGradient id="wk-blueCal" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#5CC8FF"/>
                    <stop offset="100%" stop-color="#0C86DE"/>
                  </linearGradient>
                  <linearGradient id="wk-navy" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#3A7BE8"/>
                    <stop offset="100%" stop-color="#12327F"/>
                  </linearGradient>
                  <linearGradient id="wk-lime" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#B6F14C"/>
                    <stop offset="100%" stop-color="#63C61C"/>
                  </linearGradient>
                  <linearGradient id="wk-gold" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE894"/>
                    <stop offset="55%" stop-color="#FFC633"/>
                    <stop offset="100%" stop-color="#F09A00"/>
                  </linearGradient>
                  <linearGradient id="wk-goldRim" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFD75C"/>
                    <stop offset="100%" stop-color="#E08900"/>
                  </linearGradient>
                  <linearGradient id="wk-orange" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFC061"/>
                    <stop offset="100%" stop-color="#FF8A00"/>
                  </linearGradient>

                  <filter id="wk-shadow" x="-40%" y="-40%" width="180%" height="180%">
                    <feDropShadow dx="0" dy="9" stdDeviation="9" flood-color="#0B2E5C" flood-opacity="0.28"/>
                  </filter>
                  <filter id="wk-shadowSm" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0B2E5C" flood-opacity="0.25"/>
                  </filter>
                </defs>

                <!-- ============ Confetti / burst rays ============ -->
                <g stroke-linecap="round">
                  <line x1="86"  y1="96"  x2="126" y2="140" stroke="url(#wk-lime)"   stroke-width="15"/>
                  <line x1="414" y1="96"  x2="374" y2="140" stroke="url(#wk-gold)"   stroke-width="15"/>
                  <line x1="42"  y1="240" x2="90"  y2="240" stroke="url(#wk-gold)"   stroke-width="15"/>
                  <line x1="458" y1="240" x2="410" y2="240" stroke="url(#wk-lime)"   stroke-width="15"/>
                  <line x1="250" y1="34"  x2="250" y2="76"  stroke="url(#wk-orange)" stroke-width="15"/>
                </g>

                <!-- ============ Confetti bits ============ -->
                <g filter="url(#wk-shadowSm)">
                  <rect x="98"  y="180" width="26" height="14" rx="7" fill="url(#wk-gold)"   transform="rotate(-24 111 187)"/>
                  <rect x="376" y="180" width="26" height="14" rx="7" fill="url(#wk-lime)"   transform="rotate(22 389 187)"/>
                  <rect x="132" y="420" width="26" height="14" rx="7" fill="url(#wk-orange)" transform="rotate(16 145 427)"/>
                  <rect x="348" y="424" width="26" height="14" rx="7" fill="url(#wk-lime)"   transform="rotate(-18 361 431)"/>
                  <circle cx="74"  cy="330" r="11" fill="url(#wk-lime)"/>
                  <circle cx="428" cy="330" r="11" fill="url(#wk-gold)"/>
                  <circle cx="250" cy="466" r="10" fill="url(#wk-orange)"/>
                </g>

                <!-- ============ Calendar ============ -->
                <g filter="url(#wk-shadow)">
                  <!-- rings -->
                  <rect x="164" y="90"  width="24" height="58" rx="12" fill="url(#wk-navy)"/>
                  <rect x="312" y="90"  width="24" height="58" rx="12" fill="url(#wk-navy)"/>

                  <!-- body -->
                  <rect x="118" y="118" width="264" height="256" rx="46"
                        fill="#FFFFFF" stroke="#FFFFFF" stroke-width="8"/>
                  <!-- header band -->
                  <path d="M 118,186
                           v -22
                           a 46,46 0 0 1 46,-46
                           h 172
                           a 46,46 0 0 1 46,46
                           v 22 z"
                        fill="url(#wk-blueCal)"/>
                  <!-- outline -->
                  <rect x="118" y="118" width="264" height="256" rx="46" fill="none"
                        stroke="url(#wk-blueCal)" stroke-width="9"/>

                  <!-- weekday dots row -->
                  <g fill="#C9DDF2">
                    <circle cx="168" cy="228" r="12"/>
                    <circle cx="212" cy="228" r="12"/>
                    <circle cx="256" cy="228" r="12"/>
                    <circle cx="300" cy="228" r="12"/>
                  </g>
                  <!-- weekend highlighted cells -->
                  <rect x="150" y="266" width="88" height="66" rx="22" fill="url(#wk-lime)"/>
                  <rect x="262" y="266" width="88" height="66" rx="22" fill="url(#wk-lime)"/>
                  <text x="194" y="313" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                        font-size="30" font-weight="800" fill="#FFFFFF">SA</text>
                  <text x="306" y="313" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                        font-size="30" font-weight="800" fill="#FFFFFF">SU</text>
                </g>

                <!-- ============ Super star badge ============ -->
                <g filter="url(#wk-shadow)" transform="translate(374,352)">
                  <circle cx="0" cy="0" r="66" fill="url(#wk-gold)" stroke="#FFFFFF" stroke-width="9"/>
                  <path fill="#FFFFFF"
                        d="M0,-38 L11,-12 L39,-9 L18,9 L24,37 L0,22 L-24,37 L-18,9 L-39,-9 L-11,-12 Z"/>
                </g>

                <!-- ============ Small lightning accent ============ -->
                <g filter="url(#wk-shadowSm)" transform="translate(126,352)">
                  <path d="M 8,-46 L -22,4 L 0,4 L -10,44 L 24,-8 L 2,-8 Z"
                        fill="url(#wk-gold)" stroke="#FFFFFF" stroke-width="7" stroke-linejoin="round"/>
                </g>
              </svg>
              <svg
                v-else-if="g.id === 'bundle'"
                viewBox="0 0 500 500"
                xmlns="http://www.w3.org/2000/svg"
                class="w-10 h-10 shrink-0"
              >
                <defs>
                  <linearGradient id="bd-boxBlue" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#5CC8FF"/>
                    <stop offset="100%" stop-color="#0C86DE"/>
                  </linearGradient>
                  <linearGradient id="bd-boxBlueDeep" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#2E9DF7"/>
                    <stop offset="100%" stop-color="#0A5FB4"/>
                  </linearGradient>
                  <linearGradient id="bd-boxGold" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFDC5C"/>
                    <stop offset="100%" stop-color="#F5A200"/>
                  </linearGradient>
                  <linearGradient id="bd-boxLime" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#B6F14C"/>
                    <stop offset="100%" stop-color="#63C61C"/>
                  </linearGradient>
                  <linearGradient id="bd-navy" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#3A7BE8"/>
                    <stop offset="100%" stop-color="#12327F"/>
                  </linearGradient>
                  <linearGradient id="bd-orange" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFC061"/>
                    <stop offset="100%" stop-color="#FF8A00"/>
                  </linearGradient>
                  <linearGradient id="bd-gold" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE894"/>
                    <stop offset="100%" stop-color="#F09A00"/>
                  </linearGradient>

                  <filter id="bd-shadow" x="-40%" y="-40%" width="180%" height="180%">
                    <feDropShadow dx="0" dy="9" stdDeviation="9" flood-color="#0B2E5C" flood-opacity="0.28"/>
                  </filter>
                  <filter id="bd-shadowSm" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0B2E5C" flood-opacity="0.25"/>
                  </filter>
                </defs>

                <!-- ============ Sparkles ============ -->
                <g filter="url(#bd-shadowSm)">
                  <path fill="url(#bd-boxLime)" transform="translate(66,146) rotate(-12) scale(0.8)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <path fill="url(#bd-gold)" transform="translate(444,180) rotate(14) scale(0.72)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <circle cx="62" cy="330" r="11" fill="url(#bd-orange)"/>
                  <circle cx="452" cy="336" r="10" fill="url(#bd-boxLime)"/>
                </g>

                <!-- ============ Small side box (left, back) ============ -->
                <g filter="url(#bd-shadow)" transform="translate(126,300) rotate(-8)">
                  <rect x="-62" y="-58" width="124" height="116" rx="22"
                        fill="url(#bd-boxLime)" stroke="#FFFFFF" stroke-width="8"/>
                  <line x1="0" y1="-58" x2="0" y2="58" stroke="#FFFFFF" stroke-width="8"/>
                  <line x1="-62" y1="-14" x2="62" y2="-14" stroke="#FFFFFF" stroke-width="8"/>
                </g>

                <!-- ============ Small side box (right, back) ============ -->
                <g filter="url(#bd-shadow)" transform="translate(384,306) rotate(9)">
                  <rect x="-58" y="-54" width="116" height="108" rx="22"
                        fill="url(#bd-boxGold)" stroke="#FFFFFF" stroke-width="8"/>
                  <line x1="0" y1="-54" x2="0" y2="54" stroke="#FFFFFF" stroke-width="8"/>
                  <line x1="-58" y1="-12" x2="58" y2="-12" stroke="#FFFFFF" stroke-width="8"/>
                </g>

                <!-- ============ Main box (center, front) ============ -->
                <g filter="url(#bd-shadow)">
                  <!-- lid -->
                  <rect x="128" y="196" width="244" height="66" rx="24"
                        fill="url(#bd-boxBlueDeep)" stroke="#FFFFFF" stroke-width="8"/>
                  <!-- body -->
                  <rect x="146" y="256" width="208" height="164" rx="28"
                        fill="url(#bd-boxBlue)" stroke="#FFFFFF" stroke-width="8"/>
                  <!-- vertical ribbon -->
                  <rect x="222" y="196" width="56" height="224" rx="10"
                        fill="url(#bd-gold)" stroke="#FFFFFF" stroke-width="7"/>
                  <!-- highlight -->
                  <path d="M 172,290 q34,-10 76,-8" fill="none" stroke="#FFFFFF"
                        stroke-width="10" stroke-linecap="round" opacity="0.35"/>
                </g>

                <!-- ============ Ribbon bow on top ============ -->
                <g filter="url(#bd-shadow)" transform="translate(250,180)">
                  <path d="M -6,4 C -60,-8 -96,-34 -86,-58 C -76,-80 -34,-72 -6,-6 Z"
                        fill="url(#bd-gold)" stroke="#FFFFFF" stroke-width="8" stroke-linejoin="round"/>
                  <path d="M 6,4 C 60,-8 96,-34 86,-58 C 76,-80 34,-72 6,-6 Z"
                        fill="url(#bd-gold)" stroke="#FFFFFF" stroke-width="8" stroke-linejoin="round"/>
                  <circle cx="0" cy="2" r="22" fill="url(#bd-orange)" stroke="#FFFFFF" stroke-width="8"/>
                </g>

                <!-- ============ "Hemat" badge (Rp turun) ============ -->
                <g filter="url(#bd-shadow)" transform="translate(372,414)">
                  <circle cx="0" cy="0" r="62" fill="url(#bd-navy)" stroke="#FFFFFF" stroke-width="9"/>
                  <text x="0" y="-2" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                        font-size="34" font-weight="800" fill="#FFFFFF">Rp</text>
                  <path d="M 0,10 L 0,34 M -16,20 L 0,36 L 16,20"
                        fill="none" stroke="url(#bd-boxLime)" stroke-width="10"
                        stroke-linecap="round" stroke-linejoin="round"/>
                </g>
              </svg>
              <svg
                v-else-if="g.id === 'cashback'"
                viewBox="0 0 500 500"
                xmlns="http://www.w3.org/2000/svg"
                class="w-10 h-10 shrink-0"
              >
                <defs>
                  <linearGradient id="cb-gold" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE894"/>
                    <stop offset="55%" stop-color="#FFC633"/>
                    <stop offset="100%" stop-color="#F09A00"/>
                  </linearGradient>
                  <linearGradient id="cb-goldRim" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFD75C"/>
                    <stop offset="100%" stop-color="#E08900"/>
                  </linearGradient>
                  <linearGradient id="cb-blue" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#5CC8FF"/>
                    <stop offset="100%" stop-color="#0C86DE"/>
                  </linearGradient>
                  <linearGradient id="cb-navy" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#3A7BE8"/>
                    <stop offset="100%" stop-color="#12327F"/>
                  </linearGradient>
                  <linearGradient id="cb-lime" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#B6F14C"/>
                    <stop offset="100%" stop-color="#63C61C"/>
                  </linearGradient>
                  <linearGradient id="cb-limeDeep" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#9CE348"/>
                    <stop offset="100%" stop-color="#4FA915"/>
                  </linearGradient>
                  <linearGradient id="cb-orange" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFC061"/>
                    <stop offset="100%" stop-color="#FF8A00"/>
                  </linearGradient>

                  <filter id="cb-shadow" x="-40%" y="-40%" width="180%" height="180%">
                    <feDropShadow dx="0" dy="9" stdDeviation="9" flood-color="#0B2E5C" flood-opacity="0.28"/>
                  </filter>
                  <filter id="cb-shadowSm" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0B2E5C" flood-opacity="0.25"/>
                  </filter>
                </defs>

                <!-- ============ Sparkles ============ -->
                <g filter="url(#cb-shadowSm)">
                  <path fill="url(#cb-lime)" transform="translate(64,148) rotate(-12) scale(0.8)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <path fill="url(#cb-gold)" transform="translate(448,186) rotate(14) scale(0.7)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <circle cx="60" cy="352" r="11" fill="url(#cb-orange)"/>
                  <circle cx="452" cy="342" r="10" fill="url(#cb-lime)"/>
                </g>

                <!-- ============ Money notes behind ============ -->
                <g filter="url(#cb-shadowSm)">
                  <g transform="translate(136,368) rotate(-14)">
                    <rect x="-62" y="-36" width="124" height="72" rx="14"
                          fill="url(#cb-limeDeep)" stroke="#FFFFFF" stroke-width="7"/>
                    <circle cx="0" cy="0" r="19" fill="none" stroke="#FFFFFF" stroke-width="6"/>
                    <text x="0" y="8" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                          font-size="20" font-weight="800" fill="#FFFFFF">Rp</text>
                  </g>
                  <g transform="translate(364,378) rotate(12)">
                    <rect x="-58" y="-34" width="116" height="68" rx="14"
                          fill="url(#cb-lime)" stroke="#FFFFFF" stroke-width="7"/>
                    <circle cx="0" cy="0" r="18" fill="none" stroke="#FFFFFF" stroke-width="6"/>
                    <text x="0" y="8" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                          font-size="20" font-weight="800" fill="#FFFFFF">Rp</text>
                  </g>
                </g>

                <!-- ============ Circular "back" arrow (blue) ============ -->
                <g filter="url(#cb-shadow)">
                  <!-- arc -->
                  <path d="M 250,84
                           A 166,166 0 1 0 416,250"
                        fill="none" stroke="url(#cb-blue)" stroke-width="34" stroke-linecap="round"/>
                  <path d="M 250,84
                           A 166,166 0 1 0 416,250"
                        fill="none" stroke="#FFFFFF" stroke-width="10" stroke-linecap="round" opacity="0.35"/>
                  <!-- arrow head at the arc start (pointing back/left) -->
                  <path d="M 250,84 L 250,26 L 190,84 L 250,142 Z"
                        fill="url(#cb-navy)" stroke="#FFFFFF" stroke-width="8" stroke-linejoin="round"/>
                </g>

                <!-- ============ Center gold coin ============ -->
                <g filter="url(#cb-shadow)">
                  <circle cx="250" cy="250" r="110" fill="url(#cb-gold)" stroke="url(#cb-goldRim)" stroke-width="9"/>
                  <circle cx="250" cy="250" r="90" fill="none" stroke="#FFFFFF" stroke-width="6" opacity="0.8"/>
                  <text x="250" y="286" text-anchor="middle" font-family="Arial, Helvetica, sans-serif"
                        font-size="86" font-weight="800" fill="#FFFFFF">Rp</text>
                  <!-- glossy highlight -->
                  <path d="M 186,196 q34,-38 90,-42" fill="none" stroke="#FFFFFF"
                        stroke-width="12" stroke-linecap="round" opacity="0.4"/>
                </g>

                <!-- ============ Percent badge ============ -->
                <g filter="url(#cb-shadow)" transform="translate(396,404)">
                  <circle cx="0" cy="0" r="58" fill="url(#cb-navy)" stroke="#FFFFFF" stroke-width="9"/>
                  <g stroke="url(#cb-lime)" stroke-width="10" stroke-linecap="round" fill="none">
                    <circle cx="-16" cy="-16" r="10"/>
                    <circle cx="16" cy="16" r="10"/>
                    <line x1="-21" y1="23" x2="21" y2="-23"/>
                  </g>
                </g>
              </svg>
              <svg
                v-else-if="g.id === 'referral'"
                viewBox="0 0 500 500"
                xmlns="http://www.w3.org/2000/svg"
                class="w-10 h-10 shrink-0"
              >
                <defs>
                  <linearGradient id="rf-blue" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#5CC8FF"/>
                    <stop offset="100%" stop-color="#0C86DE"/>
                  </linearGradient>
                  <linearGradient id="rf-lime" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#B6F14C"/>
                    <stop offset="100%" stop-color="#63C61C"/>
                  </linearGradient>
                  <linearGradient id="rf-navy" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#3A7BE8"/>
                    <stop offset="100%" stop-color="#12327F"/>
                  </linearGradient>
                  <linearGradient id="rf-gold" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFE894"/>
                    <stop offset="55%" stop-color="#FFC633"/>
                    <stop offset="100%" stop-color="#F09A00"/>
                  </linearGradient>
                  <linearGradient id="rf-orange" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#FFC061"/>
                    <stop offset="100%" stop-color="#FF8A00"/>
                  </linearGradient>

                  <filter id="rf-shadow" x="-40%" y="-40%" width="180%" height="180%">
                    <feDropShadow dx="0" dy="9" stdDeviation="9" flood-color="#0B2E5C" flood-opacity="0.28"/>
                  </filter>
                  <filter id="rf-shadowSm" x="-50%" y="-50%" width="200%" height="200%">
                    <feDropShadow dx="0" dy="4" stdDeviation="4" flood-color="#0B2E5C" flood-opacity="0.25"/>
                  </filter>

                  <clipPath id="rf-clipLeft"><circle cx="176" cy="308" r="98"/></clipPath>
                  <clipPath id="rf-clipRight"><circle cx="328" cy="308" r="98"/></clipPath>
                </defs>

                <!-- ============ Sparkles ============ -->
                <g filter="url(#rf-shadowSm)">
                  <path fill="url(#rf-lime)" transform="translate(66,168) rotate(-12) scale(0.78)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <path fill="url(#rf-gold)" transform="translate(446,210) rotate(14) scale(0.7)"
                        d="M0,-30 L8,-9 L30,0 L8,9 L0,30 L-8,9 L-30,0 L-8,-9 Z"/>
                  <circle cx="72" cy="404" r="11" fill="url(#rf-orange)"/>
                  <circle cx="440" cy="392" r="10" fill="url(#rf-lime)"/>
                </g>

                <!-- ============ Treat drink (shared, on top) ============ -->
                <g filter="url(#rf-shadow)" transform="translate(250,146)">
                  <!-- straw -->
                  <line x1="10" y1="-72" x2="34" y2="-16" stroke="url(#rf-lime)" stroke-width="16"
                        stroke-linecap="round"/>
                  <!-- lid -->
                  <rect x="-56" y="-58" width="112" height="26" rx="13"
                        fill="#FFFFFF" stroke="url(#rf-navy)" stroke-width="7"/>
                  <!-- cup body -->
                  <path d="M -48,-32 L 48,-32 L 34,72 C 33,82 25,88 16,88 L -16,88 C -25,88 -33,82 -34,72 Z"
                        fill="url(#rf-gold)" stroke="#FFFFFF" stroke-width="8" stroke-linejoin="round"/>
                  <!-- band -->
                  <rect x="-42" y="6" width="84" height="22" rx="11" fill="#FFFFFF" opacity="0.75"/>
                </g>

                <!-- ============ Left friend ============ -->
                <g filter="url(#rf-shadow)">
                  <circle cx="176" cy="308" r="98" fill="url(#rf-blue)" stroke="#FFFFFF" stroke-width="8"/>
                  <g clip-path="url(#rf-clipLeft)">
                    <path d="M176,314 c46,0 80,33 80,78 v54 H96 v-54 c0,-45 34,-78 80,-78 Z" fill="url(#rf-navy)"/>
                    <path d="M176,314 c46,0 80,33 80,78 v54 H96 v-54 c0,-45 34,-78 80,-78 Z"
                          fill="none" stroke="#FFFFFF" stroke-width="7" opacity="0.85"/>
                    <circle cx="176" cy="268" r="42" fill="url(#rf-navy)" stroke="#FFFFFF" stroke-width="7"/>
                  </g>
                  <path d="M 116,254 q28,-36 78,-40" fill="none" stroke="#FFFFFF"
                        stroke-width="10" stroke-linecap="round" opacity="0.35"/>
                </g>

                <!-- ============ Right friend ============ -->
                <g filter="url(#rf-shadow)">
                  <circle cx="328" cy="308" r="98" fill="url(#rf-lime)" stroke="#FFFFFF" stroke-width="8"/>
                  <g clip-path="url(#rf-clipRight)">
                    <path d="M328,314 c46,0 80,33 80,78 v54 H248 v-54 c0,-45 34,-78 80,-78 Z" fill="url(#rf-navy)"/>
                    <path d="M328,314 c46,0 80,33 80,78 v54 H248 v-54 c0,-45 34,-78 80,-78 Z"
                          fill="none" stroke="#FFFFFF" stroke-width="7" opacity="0.85"/>
                    <circle cx="328" cy="268" r="42" fill="url(#rf-navy)" stroke="#FFFFFF" stroke-width="7"/>
                  </g>
                  <path d="M 268,254 q28,-36 78,-40" fill="none" stroke="#FFFFFF"
                        stroke-width="10" stroke-linecap="round" opacity="0.35"/>
                </g>

                <!-- ============ Heart badge (traktir) ============ -->
                <g filter="url(#rf-shadow)" transform="translate(252,404)">
                  <circle cx="0" cy="0" r="58" fill="#FFFFFF" stroke="#FFFFFF" stroke-width="6"/>
                  <circle cx="0" cy="0" r="58" fill="url(#rf-orange)" stroke="#FFFFFF" stroke-width="9"/>
                  <path d="M 0,28
                           C -34,6 -30,-22 -14,-26
                           C -5,-28 0,-22 0,-16
                           C 0,-22 5,-28 14,-26
                           C 30,-22 34,6 0,28 Z"
                        fill="#FFFFFF"/>
                </g>
              </svg>
              <span
                v-else
                class="w-10 h-10 rounded-xl bg-(--color-surface-container) flex items-center justify-center text-lg shrink-0"
              >
                {{ d.promo.emoji }}
              </span>
              <div class="flex-1 min-w-0">
                <div class="flex items-center gap-2">
                  <h3 class="flex-1 text-[13px] font-extrabold leading-snug">{{ d.promo.judul }}</h3>
                  <span
                    v-if="d.promo.kode"
                    class="shrink-0 text-[9.5px] font-extrabold tracking-wide px-2 py-0.5 rounded border border-dashed border-(--color-azure) text-(--color-azure)"
                  >
                    {{ d.promo.kode }}
                  </span>
                </div>
                <p class="text-[11px] text-(--color-on-surface-variant) mt-0.5">{{ d.promo.ringkas }}</p>
                <ul class="mt-1.5 flex flex-col gap-0.5">
                  <li
                    v-for="t in d.promo.detail"
                    :key="t"
                    class="text-[10.5px] text-(--color-on-surface-variant) leading-snug flex gap-1.5"
                  >
                    <span class="text-(--color-azure) shrink-0">&bull;</span>{{ t }}
                  </li>
                </ul>
                <p v-if="d.promo.kuota" class="text-[10px] text-(--color-error) font-semibold mt-1">
                  Kuota terbatas {{ d.promo.kuota.toLocaleString('id-ID') }} pengguna
                </p>
              </div>
            </div>

            <div class="flex items-center gap-3 mt-3 pt-2.5 border-t border-(--color-outline)/10">
              <div class="flex-1 min-w-0">
                <p v-if="d.hasil.berlaku" class="text-[12px] font-extrabold text-(--color-azure)">
                  {{ d.hasil.cashback ? `Cashback ${rp(d.hasil.cashback)}` : `Hemat ${rp(d.hemat)}` }}
                </p>
                <p v-else class="text-[11.5px] text-(--color-on-surface-variant)">{{ d.hasil.alasan }}</p>
              </div>
              <button
                type="button"
                :disabled="!d.hasil.berlaku"
                class="h-9 px-5 rounded-full text-[12.5px] font-bold shrink-0 transition-all active:scale-95 disabled:opacity-40 disabled:active:scale-100"
                :class="dipilih === d.promo.id ? 'bg-(--color-on-surface) text-white' : 'bg-(--color-azure) text-white'"
                @click="pakai(d.promo.id, d.hasil.berlaku)"
              >
                {{ dipilih === d.promo.id ? 'Dipakai' : 'Pakai' }}
              </button>
            </div>
          </div>
        </div>
      </section>

    </main>

    <footer class="fixed bottom-0 w-full z-40 bg-(--color-surface-0) border-t border-(--color-outline)/15">
      <div class="max-w-[430px] mx-auto px-4 py-3.5">
        <button
          type="button"
          class="w-full bg-(--color-azure) text-white rounded-xl py-3.5 text-[15px] font-extrabold active:scale-95 transition-transform"
          @click="kembali"
        >
          Selesai
        </button>
      </div>
    </footer>

  </div>
</template>
