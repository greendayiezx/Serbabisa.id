<script setup lang="ts">
/**
 * BisaJemput — voucher.
 *
 * Daftar promo untuk perjalanan yang sedang disusun, bukan daftar promo umum:
 * potongan tiap promo dihitung server dari tarif pilihan yang SEDANG dipilih,
 * jadi angka di kartu ini benar-benar angka yang akan dipotong.
 *
 * Yang tidak bisa dipakai tetap ditampilkan beserta ALASANNYA. Menyembunyikan
 * promo yang belum memenuhi syarat membuat orang merasa promonya tidak ada;
 * menampilkannya tanpa alasan membuat orang menekannya berkali-kali. Yang
 * berguna adalah kalimat yang menyebut apa yang kurang.
 */
import { computed, ref } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import { useJemputStore } from '@/stores/jemput'
import { rupiah, type PromoJemput } from '@/lib/jemput'

const router = useRouter()
const jemputStore = useJemputStore()

const pilihan = computed(() => jemputStore.jemput && jemputStore.pilihan)
const kode = ref('')
const galat = ref<string | null>(null)

const semua = computed<PromoJemput[]>(() => jemputStore.pilihan?.promo ?? [])
const bisa = computed(() => semua.value.filter((p) => p.bisa_dipakai))
const belum = computed(() => semua.value.filter((p) => !p.bisa_dipakai))

/** Potongan terbesar ditandai; sisanya tidak, supaya tandanya berarti. */
const palingHemat = computed(() =>
  bisa.value.length ? [...bisa.value].sort((a, b) => b.potongan - a.potongan)[0].kode : null,
)

const dipakai = computed(() => jemputStore.promo?.kode ?? null)

function tarifSetelah(p: PromoJemput): number {
  return Math.max(0, (jemputStore.pilihan?.tarif ?? 0) - p.potongan)
}

function pakai(p: PromoJemput) {
  jemputStore.setPromo(p)
  router.back()
}

function batalkan() {
  jemputStore.setPromo(null)
}

/**
 * Kode diketik manual dicocokkan ke daftar yang sama.
 *
 * Tidak ada jalur rahasia di sini: kode yang tidak ada di daftar memang tidak
 * ada, dan yang ada tapi belum memenuhi syarat dijawab dengan syaratnya —
 * bukan dengan "kode tidak berlaku" yang tidak menjelaskan apa pun.
 */
function tukar() {
  const cari = kode.value.trim().toUpperCase()
  if (!cari) return

  const ketemu = semua.value.find((p) => p.kode === cari)
  if (!ketemu) {
    galat.value = 'Kode itu tidak dikenal.'
    return
  }
  if (!ketemu.bisa_dipakai) {
    galat.value = ketemu.alasan ?? 'Kode itu belum bisa dipakai untuk perjalanan ini.'
    return
  }

  galat.value = null
  pakai(ketemu)
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-10">
    <header class="sticky top-0 z-30 bg-(--color-surface-0)">
      <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center gap-3">
        <button
          type="button"
          aria-label="Tutup"
          class="w-10 h-10 -ml-2 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="router.back()"
        >
          <Icon name="x" class="w-5 h-5" />
        </button>
        <h1 class="text-[17px] font-display font-extrabold">Voucher</h1>
      </div>

      <div class="max-w-[430px] mx-auto px-4 pb-4">
        <div
          class="flex items-center gap-2.5 rounded-full bg-(--color-surface-container) px-4 py-3"
        >
          <Icon name="sparkle" class="w-4 h-4 shrink-0 text-(--color-on-surface-variant)" />
          <input
            v-model="kode"
            type="text"
            maxlength="30"
            placeholder="Punya kode promo? Masukkan di sini"
            class="flex-1 min-w-0 bg-transparent text-[13px] outline-none uppercase placeholder:normal-case"
            @keyup.enter="tukar"
          />
          <button
            type="button"
            aria-label="Pakai kode"
            class="w-7 h-7 rounded-full flex items-center justify-center shrink-0 active:scale-90 transition-transform"
            @click="tukar"
          >
            <Icon name="chevron-right" class="w-4 h-4" />
          </button>
        </div>
        <p v-if="galat" role="alert" class="mt-2 text-[11.5px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </header>

    <main v-if="pilihan" class="max-w-[430px] mx-auto px-4 pt-2">
      <!-- Layanan yang sedang dipilih -->
      <div class="flex items-center gap-2 mb-4">
        <span
          class="px-2.5 py-1 rounded-md bg-(--color-azure) text-white text-[10.5px] font-extrabold"
        >
          {{ jemputStore.pilihan?.label_varian }}
        </span>
        <span class="text-[12px] font-semibold text-(--color-on-surface-variant)">
          {{ jemputStore.pilihan?.label }}
        </span>
      </div>

      <template v-if="bisa.length">
        <h2 class="text-[13.5px] font-display font-extrabold mb-2.5">
          Tersedia buat {{ jemputStore.pilihan?.label }}
        </h2>

        <div class="flex flex-col gap-2.5 mb-6">
          <div
            v-for="p in bisa"
            :key="p.kode"
            class="relative rounded-2xl bg-(--color-surface-0) border-2 p-4 overflow-hidden"
            :class="dipakai === p.kode ? 'border-(--color-azure)' : 'border-transparent'"
          >
            <span
              v-if="palingHemat === p.kode"
              class="absolute top-0 right-0 px-3 py-1 rounded-bl-xl bg-(--color-on-secondary-container) text-white text-[10.5px] font-extrabold"
            >
              Paling hemat
            </span>

            <p class="text-[19px] font-display font-extrabold leading-tight">
              Potongan {{ rupiah(p.potongan) }}
            </p>
            <p class="mt-1 text-[12.5px] font-bold text-(--color-on-secondary-container)">
              Yang dibayar jadi {{ rupiah(tarifSetelah(p)) }}
            </p>
            <p class="mt-1.5 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
              {{ p.deskripsi }}
            </p>

            <div
              class="mt-3 pt-3 border-t border-dashed border-(--color-outline)/30 flex items-center justify-between gap-3"
            >
              <!--
                Bukan tanggal hangus: promo di katalog ini tidak punya masa
                berlaku, dan menuliskan tanggal yang tidak ada sama saja
                mengarang urgensi. Yang ditulis syarat yang memang berlaku.
              -->
              <span class="text-[11px] leading-snug text-(--color-on-surface-variant)">
                {{
                  p.jenis === 'akuisisi'
                    ? 'Sekali pakai untuk perjalanan pertama'
                    : `Mulai tarif ${rupiah(p.minimum)}`
                }}
              </span>

              <button
                v-if="dipakai === p.kode"
                type="button"
                class="px-4 h-9 rounded-full border-[1.5px] border-(--color-error)/60 text-(--color-error) text-[12.5px] font-extrabold shrink-0 active:scale-95 transition-transform"
                @click="batalkan"
              >
                Batalin
              </button>
              <button
                v-else
                type="button"
                class="px-5 h-9 rounded-full bg-(--color-azure) text-white text-[12.5px] font-extrabold shrink-0 active:scale-95 transition-transform"
                @click="pakai(p)"
              >
                Pakai
              </button>
            </div>
          </div>
        </div>
      </template>

      <p
        v-else
        class="rounded-2xl bg-(--color-surface-0) p-5 text-[12.5px] leading-relaxed text-(--color-on-surface-variant) mb-6"
      >
        Belum ada voucher yang bisa dipakai untuk perjalanan ini. Syarat tiap voucher ada di bawah.
      </p>

      <!-- Yang belum memenuhi syarat -->
      <template v-if="belum.length">
        <h2 class="text-[13.5px] font-display font-extrabold mb-2.5">Belum bisa dipakai</h2>
        <div class="flex flex-col gap-2.5">
          <div
            v-for="p in belum"
            :key="p.kode"
            class="rounded-2xl bg-(--color-surface-0) p-4 opacity-70"
          >
            <p class="text-[15px] font-display font-extrabold leading-tight">{{ p.nama }}</p>
            <p class="mt-1 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
              {{ p.deskripsi }}
            </p>
            <!-- Alasannya disebut, bukan cuma diredupkan. -->
            <p class="mt-2 text-[11.5px] font-semibold text-(--color-error)">{{ p.alasan }}</p>
          </div>
        </div>
      </template>
    </main>

    <p v-else class="max-w-[430px] mx-auto px-4 pt-10 text-center text-[13px] text-(--color-on-surface-variant)">
      Pilih kendaraan dulu, baru voucher yang berlaku bisa ditampilkan.
    </p>
  </div>
</template>
