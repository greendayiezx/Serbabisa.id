<script setup lang="ts">
import { computed, ref } from 'vue'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import LottieIcon from '@/components/LottieIcon.vue'
import { usePromoStore, type LanggananId } from '@/stores/promo'
import { ongkirUntuk } from '@/lib/belanja/ongkir'
import { LANGGANAN } from '@/lib/promo/promoBelanja'
import { pesanError } from '@/api/belanja'

const promoStore = usePromoStore()

const kinclongLottie = {
  v: '5.5.2',
  fr: 30,
  ip: 0,
  op: 60,
  w: 100,
  h: 100,
  nm: 'Kinclong Sparkle',
  ddd: 0,
  assets: [],
  layers: [
    {
      ddd: 0,
      ind: 1,
      ty: 4,
      nm: 'Sparkle 1',
      sr: 1,
      ks: {
        o: { a: 1, k: [{ t: 0, s: [0] }, { t: 20, s: [100] }, { t: 40, s: [0] }, { t: 60, s: [0] }] },
        r: { a: 1, k: [{ t: 0, s: [0] }, { t: 60, s: [90] }] },
        p: { a: 0, k: [50, 50, 0] },
        a: { a: 0, k: [0, 0, 0] },
        s: { a: 1, k: [{ t: 0, s: [20, 20, 100] }, { t: 20, s: [120, 120, 100] }, { t: 40, s: [20, 20, 100] }, { t: 60, s: [20, 20, 100] }] }
      },
      shapes: [
        {
          ty: 'gr',
          it: [
            {
              ty: 'sr',
              sy: 2,
              pt: { a: 0, k: 4 },
              p: { a: 0, k: [0, 0] },
              r: { a: 0, k: 0 },
              ir: { a: 0, k: 8 },
              is: { a: 0, k: 0 },
              or: { a: 0, k: 32 },
              os: { a: 0, k: 0 }
            },
            {
              ty: 'fl',
              c: { a: 0, k: [1, 0.95, 0.7, 1] },
              o: { a: 0, k: 100 }
            },
            { ty: 'tr', p: { a: 0, k: [0, 0] }, a: { a: 0, k: [0, 0] }, s: { a: 0, k: [100, 100] }, r: { a: 0, k: 0 }, o: { a: 0, k: 100 } }
          ]
        }
      ]
    },
    {
      ddd: 0,
      ind: 2,
      ty: 4,
      nm: 'Sparkle 2',
      sr: 1,
      ks: {
        o: { a: 1, k: [{ t: 0, s: [0] }, { t: 15, s: [0] }, { t: 35, s: [100] }, { t: 55, s: [0] }] },
        r: { a: 1, k: [{ t: 0, s: [-45] }, { t: 60, s: [45] }] },
        p: { a: 0, k: [25, 30, 0] },
        a: { a: 0, k: [0, 0, 0] },
        s: { a: 1, k: [{ t: 15, s: [20, 20, 100] }, { t: 35, s: [100, 100, 100] }, { t: 55, s: [20, 20, 100] }] }
      },
      shapes: [
        {
          ty: 'gr',
          it: [
            {
              ty: 'sr',
              sy: 2,
              pt: { a: 0, k: 4 },
              p: { a: 0, k: [0, 0] },
              r: { a: 0, k: 0 },
              ir: { a: 0, k: 5 },
              is: { a: 0, k: 0 },
              or: { a: 0, k: 20 },
              os: { a: 0, k: 0 }
            },
            {
              ty: 'fl',
              c: { a: 0, k: [1, 1, 1, 1] },
              o: { a: 0, k: 100 }
            },
            { ty: 'tr', p: { a: 0, k: [0, 0] }, a: { a: 0, k: [0, 0] }, s: { a: 0, k: [100, 100] }, r: { a: 0, k: 0 }, o: { a: 0, k: 100 } }
          ]
        }
      ]
    },
    {
      ddd: 0,
      ind: 3,
      ty: 4,
      nm: 'Sparkle 3',
      sr: 1,
      ks: {
        o: { a: 1, k: [{ t: 0, s: [80] }, { t: 25, s: [0] }, { t: 45, s: [100] }, { t: 60, s: [20] }] },
        r: { a: 1, k: [{ t: 0, s: [15] }, { t: 60, s: [105] }] },
        p: { a: 0, k: [78, 70, 0] },
        a: { a: 0, k: [0, 0, 0] },
        s: { a: 1, k: [{ t: 0, s: [80, 80, 100] }, { t: 25, s: [20, 20, 100] }, { t: 45, s: [110, 110, 100] }, { t: 60, s: [40, 40, 100] }] }
      },
      shapes: [
        {
          ty: 'gr',
          it: [
            {
              ty: 'sr',
              sy: 2,
              pt: { a: 0, k: 4 },
              p: { a: 0, k: [0, 0] },
              r: { a: 0, k: 0 },
              ir: { a: 0, k: 6 },
              is: { a: 0, k: 0 },
              or: { a: 0, k: 24 },
              os: { a: 0, k: 0 }
            },
            {
              ty: 'fl',
              c: { a: 0, k: [1, 0.9, 0.5, 1] },
              o: { a: 0, k: 100 }
            },
            { ty: 'tr', p: { a: 0, k: [0, 0] }, a: { a: 0, k: [0, 0] }, s: { a: 0, k: [100, 100] }, r: { a: 0, k: 0 }, o: { a: 0, k: 100 } }
          ]
        }
      ]
    }
  ]
}

/** Ongkir 0–5 km, dipakai untuk menaksir nilai benefit gratis ongkir. */
const ONGKIR_ACUAN = ongkirUntuk(4)

const langgananAktif = computed(() => promoStore.state.langganan)

const paketList = computed(() =>
  LANGGANAN.map((l) => ({
    ...l,
    nilaiBenefit: l.kuotaOngkir * ONGKIR_ACUAN + l.kuotaDiskon * l.diskonPerTransaksi,
    aktif: langgananAktif.value === l.id,
  })),
)

const paketDipilih = ref<LanggananId | null>(null)
const paketInfo = computed(() => LANGGANAN.find((l) => l.id === paketDipilih.value) ?? null)

/** Sedang menunggu server; tombol dikunci supaya tidak dobel-langganan. */
const memproses = ref(false)
const galat = ref<string | null>(null)

async function bukaKonfirmasi(id: LanggananId) {
  if (langgananAktif.value === id) {
    memproses.value = true
    galat.value = null
    try {
      await promoStore.setLangganan(null)
    } catch (e) {
      galat.value = pesanError(e)
    } finally {
      memproses.value = false
    }
    return
  }
  paketDipilih.value = id
}

async function aktifkan() {
  const paket = paketDipilih.value
  paketDipilih.value = null
  if (!paket) return

  memproses.value = true
  galat.value = null
  try {
    await promoStore.setLangganan(paket)
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
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
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Langganan</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-4">
      <p class="text-[12px] text-(--color-on-surface-variant)">
        Semua paket bisa dihentikan kapan saja. Jatah gratis ongkir dan potongan dihitung ulang tiap
        bulan.
      </p>

      <div
        v-for="l in paketList"
        :key="l.id"
        class="rounded-2xl overflow-hidden border-2"
        :class="l.aktif ? 'border-(--color-azure)' : 'border-transparent'"
      >
        <div
          class="p-4 relative overflow-hidden"
          :class="{
            'bg-gradient-to-br from-slate-200 via-zinc-300 to-slate-400': l.id === 'basic',
            'bg-gradient-to-br from-amber-400 via-yellow-500 to-amber-600': l.id === 'premium',
            'bg-gradient-to-br from-[#2E9DF7] via-[#1E7FE0] to-[#0F5FD1]': l.id === 'family',
          }"
        >
          <!-- Kinclong flare sweep overlay -->
          <div class="kinclong-flare"></div>

          <!-- Kinclong Lottie Sparkle Icon -->
          <div class="absolute -top-1 -right-1 pointer-events-none opacity-90 z-10">
            <LottieIcon :data="kinclongLottie" :size="54" />
          </div>

          <div class="relative z-10 flex items-center gap-2">
            <h2
              class="flex-1 text-[15px] font-extrabold"
              :class="l.id === 'family' ? 'text-white' : 'text-slate-900'"
            >
              BisaBelanja {{ l.nama }}
            </h2>
            <span
              class="text-[14px] font-extrabold"
              :class="l.id === 'family' ? 'text-white' : 'text-slate-900'"
            >
              {{ rp(l.harga) }}<span class="text-[10px] font-semibold">/bln</span>
            </span>
          </div>
          <ul class="mt-3 flex flex-col gap-1.5">
            <li
              v-for="b in l.benefit"
              :key="b"
              class="text-[12px] leading-snug flex gap-2"
              :class="l.id === 'family' ? 'text-white/95' : 'text-slate-800'"
            >
              <Icon name="check" class="w-3.5 h-3.5 shrink-0 mt-0.5" />
              {{ b }}
            </li>
          </ul>
        </div>

        <div class="bg-(--color-surface-0) px-4 py-3.5 flex items-center gap-3">
          <div class="flex-1 min-w-0">
            <p v-if="l.aktif" class="text-[12px] font-extrabold text-(--color-azure)">Aktif sekarang</p>
            <p v-else class="text-[12px] text-(--color-on-surface-variant)">
              Nilai manfaat sampai
              <span class="font-extrabold text-(--color-on-surface)">{{ rp(l.nilaiBenefit) }}</span>
              /bulan
            </p>
          </div>
          <button
            type="button"
            class="h-9 px-5 rounded-full text-[12.5px] font-bold shrink-0 active:scale-95 transition-transform"
            :class="
              l.aktif
                ? 'border border-(--color-outline)/40 text-(--color-on-surface-variant)'
                : 'bg-(--color-on-surface) text-white'
            "
            @click="bukaKonfirmasi(l.id)"
          >
            {{ l.aktif ? 'Berhenti' : 'Berlangganan' }}
          </button>
        </div>
      </div>

      <div class="flex items-start gap-2 bg-white border-2 border-white p-3.5 rounded-xl shadow-xs">
        <Icon name="info" class="w-4 h-4 text-slate-700 shrink-0 mt-0.5" />
        <p class="text-[11px] text-slate-700 leading-relaxed font-medium">
          Langganan berjalan terpisah dari voucher promo. Ongkir gratis dari langganan tetap berlaku
          walau kamu sedang memakai voucher lain.
        </p>
      </div>
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

    <Teleport to="body">
      <div v-if="paketInfo" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
        <div class="absolute inset-0 bg-black/45" @click="paketDipilih = null"></div>
        <div class="relative w-full md:w-96 bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] p-5 pb-8">
          <h3 class="text-[16px] font-extrabold mb-1.5">Berlangganan {{ paketInfo.nama }}?</h3>
          <p class="text-[12.5px] text-(--color-on-surface-variant) leading-relaxed mb-4">
            {{ rp(paketInfo.harga) }} per bulan. Bisa dihentikan kapan saja.
          </p>
          <ul class="flex flex-col gap-1 mb-5">
            <li
              v-for="b in paketInfo.benefit"
              :key="b"
              class="text-[12px] text-(--color-on-surface-variant) flex gap-1.5"
            >
              <span class="text-(--color-azure) shrink-0">&bull;</span>{{ b }}
            </li>
          </ul>
          <div class="flex gap-2.5">
            <button
              type="button"
              class="flex-1 h-11 rounded-full border border-(--color-outline)/30 text-[13px] font-bold active:scale-95 transition-transform"
              @click="paketDipilih = null"
            >
              Nanti saja
            </button>
            <button
              type="button"
              class="flex-1 h-11 rounded-full bg-(--color-on-surface) text-white text-[13px] font-bold active:scale-95 transition-transform"
              @click="aktifkan"
            >
              Berlangganan
            </button>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style scoped>
@keyframes kinclong {
  0% {
    transform: translateX(-150%) skewX(-20deg);
  }
  30%, 100% {
    transform: translateX(250%) skewX(-20deg);
  }
}

.kinclong-flare {
  position: absolute;
  top: 0;
  left: 0;
  width: 65%;
  height: 100%;
  background: linear-gradient(
    90deg,
    transparent 0%,
    rgba(255, 255, 255, 0.08) 25%,
    rgba(255, 255, 255, 0.45) 50%,
    rgba(255, 255, 255, 0.08) 75%,
    transparent 100%
  );
  pointer-events: none;
  animation: kinclong 3.5s infinite ease-in-out;
  z-index: 5;
}
</style>
