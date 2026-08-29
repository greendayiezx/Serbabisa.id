<script setup lang="ts">
/**
 * Cek & Tambah Freon — ringkasan sebelum pesanan dibuat.
 *
 * Satu hal yang halaman ini harus katakan dengan jelas: yang dibayar sekarang
 * HANYA pemeriksaan. Harga freon dan perbaikan belum bisa disebut karena
 * penyebabnya belum diketahui, dan menampilkan angka yang belum pasti hanya
 * membuat pelanggan merasa ditagih diam-diam nanti.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import LottieIcon from '@/components/LottieIcon.vue'
import panahLottie from '@/assets/lottie/panah-konfirmasi.json'
import MetodeBayarIcon from '@/components/MetodeBayarIcon.vue'
import { useFreonStore } from '@/stores/freon'
import { useLocationStore } from '@/stores/location'
import { pesanPemeriksaanFreon } from '@/api/freon'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import { KAPASITAS_AC, TIPE_AC } from '@/lib/servis-ac/hargaAC'
import { JENIS_FREON, MEREK_AC, hitungPemeriksaan } from '@/lib/servis-ac/hargaFreon'
import { cariPromoAC, hitungPromoAC } from '@/lib/promo/promoAC'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const freonStore = useFreonStore()
const locationStore = useLocationStore()

const draft = computed(() => freonStore.draft)

onMounted(() => {
  if (!draft.value) {
    router.replace({ name: 'servis-ac-freon' })
    return
  }

  const dariKatalog = String(route.query.promo ?? '')
  if (dariKatalog) pilihPromo(dariKatalog.toUpperCase())
})

const rincian = computed(() => hitungPemeriksaan(draft.value?.unit ?? 1))
const alamat = computed(() => locationStore.draft?.alamat ?? '')

const namaTipe = computed(() => TIPE_AC.find((t) => t.id === draft.value?.tipe)?.nama ?? '-')
const namaKapasitas = computed(
  () => KAPASITAS_AC.find((k) => k.id === draft.value?.kapasitas)?.nama ?? '-',
)
const namaMerek = computed(() => MEREK_AC.find((m) => m.id === draft.value?.merek)?.nama ?? '-')
const namaFreon = computed(
  () => JENIS_FREON.find((f) => f.id === draft.value?.jenisFreon)?.nama ?? '-',
)

const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]
const HARI = ['Minggu', 'Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu']

const jadwalTeks = computed(() => {
  const d = draft.value
  if (!d?.tanggal) return '-'
  const t = new Date(d.tanggal + 'T00:00:00')
  if (Number.isNaN(t.getTime())) return d.tanggal
  return `${HARI[t.getDay()]}, ${t.getDate()} ${BULAN[t.getMonth()]} · ${d.slot}`
})

/* ────────── Promo ────────── */
const promoKode = ref<string | null>(null)
const kodeInput = ref('')
const promoPesan = ref<{ ok: boolean; teks: string } | null>(null)

const promoTerpakai = computed(() => cariPromoAC(promoKode.value))
const hasilPromo = computed(() =>
  hitungPromoAC(promoTerpakai.value, rincian.value.total, draft.value?.unit ?? 1, 'freon'),
)

const total = computed(() => rincian.value.total - hasilPromo.value.potongan)

function pilihPromo(kode: string) {
  const promo = cariPromoAC(kode)
  if (!promo) {
    promoPesan.value = { ok: false, teks: 'Kode promo tidak ditemukan.' }
    return
  }

  const hasil = hitungPromoAC(promo, rincian.value.total, draft.value?.unit ?? 1, 'freon')
  if (!hasil.berlaku) {
    promoPesan.value = {
      ok: false,
      teks: hasil.alasan ?? `Kurang ${rupiah(hasil.kurang)} lagi untuk memakai promo ini.`,
    }
    return
  }

  promoKode.value = promo.kode
  kodeInput.value = ''
  promoPesan.value = { ok: true, teks: `${promo.kode} dipakai — hemat ${rupiah(hasil.potongan)}.` }
}

function pakaiKode() {
  const kode = kodeInput.value.trim().toUpperCase()
  if (kode) pilihPromo(kode)
}

function lepasPromo() {
  promoKode.value = null
  promoPesan.value = null
}

function keKatalogPromo() {
  router.push({
    name: 'promo-layanan',
    params: { layanan: 'ac' },
    query: {
      dari: '/tasks/new/servis-ac/freon/ringkasan',
      nilai: String(rincian.value.total),
      unit: String(draft.value?.unit ?? 1),
      konteks: 'freon',
    },
  })
}

/* ────────── Metode & konfirmasi ────────── */
const METODE: MetodeId[] = ['bca', 'mandiri', 'bni', 'gopay', 'ovo', 'qris']
const metodeDipilih = ref<MetodeId>('bca')
const sheetOpen = ref(false)
const metodeLabel = computed(() => LABEL_METODE[metodeDipilih.value] ?? metodeDipilih.value)

const memproses = ref(false)
const galat = ref<string | null>(null)

async function konfirmasi() {
  const d = draft.value
  if (memproses.value || !d) return

  if (!alamat.value) {
    galat.value = 'Lokasi servis belum diisi.'
    return
  }

  memproses.value = true
  galat.value = null

  try {
    const hasil = await pesanPemeriksaanFreon({
      unit: d.unit,
      keluhan: [...d.keluhan],
      menyala: d.menyala,
      tipe: d.tipe,
      kapasitas: d.kapasitas,
      merek: d.merek,
      jenis_freon: d.jenisFreon,
      catatan: d.catatan || undefined,
      tanggal: d.tanggal,
      slot: d.slot,
      lokasi_alamat: alamat.value,
      lokasi_lat: locationStore.draft?.lat ?? 0,
      lokasi_lng: locationStore.draft?.lng ?? 0,
      metode: metodeDipilih.value,
      promo_kode: promoTerpakai.value?.kode,
    })

    const nomor = hasil.nomor_invoice ?? String(hasil.id)
    freonStore.hapus()

    if (hasil.rincian?.promo_ditolak) {
      // Pesanan tetap dibuat dengan harga penuh — itu harus dikatakan.
      galat.value = `Promo tidak terpakai: ${hasil.rincian.promo_ditolak}`
    }

    router.replace({ name: 'servis-ac-freon-hasil', params: { nomor } })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}
</script>

<template>
  <div v-if="draft" class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-32">
    <header class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/10">
      <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Ringkasan Pesanan</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- Detail layanan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[14px] font-display font-extrabold mb-4">Detail Layanan</h2>

        <div class="flex gap-3">
          <span
            class="w-10 h-10 shrink-0 rounded-full bg-(--color-primary-container) text-(--color-on-primary-container) flex items-center justify-center"
          >
            <span class="material-symbols-outlined text-[20px]" data-icon="ac_unit">ac_unit</span>
          </span>
          <span class="min-w-0">
            <span class="block text-[13.5px] font-bold">Cek &amp; Tambah Freon</span>
            <span class="block text-[12px] text-(--color-on-surface-variant)">
              {{ draft.unit }} AC {{ namaTipe }} · {{ namaKapasitas }} · {{ namaMerek }} ·
              freon {{ namaFreon }}
            </span>
          </span>
        </div>

        <div class="h-px bg-(--color-outline)/20 my-4"></div>

        <div class="flex gap-3 mb-4">
          <span class="w-10 h-10 shrink-0 rounded-full bg-(--color-surface-container) flex items-center justify-center">
            <Icon name="pin" class="w-4 h-4 text-[#F97316]" />
          </span>
          <span class="min-w-0">
            <span class="block text-[11px] uppercase tracking-wider text-(--color-on-surface-variant)">
              Lokasi
            </span>
            <span class="block text-[12.5px] leading-snug">{{ alamat || 'Belum diisi' }}</span>
          </span>
        </div>

        <div class="flex gap-3">
          <span class="w-10 h-10 shrink-0 rounded-full bg-(--color-surface-container) flex items-center justify-center">
            <Icon name="clock" class="w-4 h-4 text-(--color-on-surface-variant)" />
          </span>
          <span class="min-w-0">
            <span class="block text-[11px] uppercase tracking-wider text-(--color-on-surface-variant)">
              Jadwal kedatangan
            </span>
            <span class="block text-[12.5px]">{{ jadwalTeks }}</span>
          </span>
        </div>
      </section>

      <!-- Kode promo -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Kode Promo</h3>

        <div
          v-if="promoTerpakai"
          class="flex items-center gap-3 rounded-xl bg-(--color-azure)/8 border border-(--color-azure)/30 px-3.5 py-2.5"
        >
          <Icon name="check-circle" class="w-4.5 h-4.5 text-(--color-azure) shrink-0" />
          <div class="flex-1 min-w-0">
            <p class="text-[12px] font-bold truncate">{{ promoTerpakai.kode }}</p>
            <p class="text-[11px] text-(--color-on-surface-variant) truncate">
              {{ promoTerpakai.judul }}
            </p>
          </div>
          <button
            type="button"
            class="shrink-0 text-[11.5px] font-bold text-(--color-error) active:scale-95 transition-transform"
            @click="lepasPromo"
          >
            Hapus
          </button>
        </div>

        <div v-else class="flex gap-2">
          <div class="relative flex-1">
            <span class="absolute inset-y-0 left-0 pl-3 flex items-center text-(--color-on-surface-variant)">
              <Icon name="receipt" class="w-4 h-4" />
            </span>
            <input
              v-model="kodeInput"
              type="text"
              placeholder="Masukkan kode promo"
              class="w-full rounded-xl bg-(--color-surface-container) pl-9 pr-3 py-3 text-[13px] font-semibold uppercase border-2 border-transparent focus:border-(--color-azure) outline-none placeholder:normal-case placeholder:font-normal"
              @keyup.enter="pakaiKode"
            />
          </div>
          <button
            type="button"
            class="shrink-0 px-5 rounded-xl bg-(--color-azure) text-white text-[13px] font-bold active:scale-95 transition-transform"
            @click="pakaiKode"
          >
            Pakai
          </button>
        </div>

        <p
          v-if="promoPesan"
          class="mt-2 flex items-center gap-1.5 text-[11.5px] font-semibold"
          :class="promoPesan.ok ? 'text-(--color-on-secondary-container)' : 'text-(--color-error)'"
        >
          <Icon :name="promoPesan.ok ? 'check' : 'alert'" class="w-3.5 h-3.5 shrink-0" />
          {{ promoPesan.teks }}
        </p>

        <button
          type="button"
          class="mt-4 text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
          @click="keKatalogPromo"
        >
          Lihat semua promo Servis AC →
        </button>
      </section>

      <!-- Rincian pembayaran -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[14px] font-display font-extrabold mb-3">Rincian Pembayaran</h2>

        <div class="flex flex-col gap-2 text-[12.5px]">
          <div class="flex justify-between gap-3 text-(--color-on-surface-variant)">
            <span>Biaya pemeriksaan awal</span>
            <span class="font-bold text-(--color-on-surface)">
              {{ rupiah(rincian.biayaPemeriksaan) }}
            </span>
          </div>

          <div
            v-if="rincian.biayaUnitTambahan"
            class="flex justify-between gap-3 text-(--color-on-surface-variant)"
          >
            <span>Unit tambahan ({{ rincian.unit - 1 }})</span>
            <span class="font-bold text-(--color-on-surface)">
              {{ rupiah(rincian.biayaUnitTambahan) }}
            </span>
          </div>

          <div v-if="hasilPromo.potongan" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Diskon promo ({{ promoTerpakai?.kode }})</span>
            <span class="font-bold text-(--color-error)">&minus;{{ rupiah(hasilPromo.potongan) }}</span>
          </div>

          <div class="flex justify-between items-center gap-3 pt-2.5 mt-1 border-t border-(--color-outline)/12">
            <span class="text-[13px] font-extrabold">Total dibayar sekarang</span>
            <span class="text-[19px] font-extrabold text-(--color-azure)">{{ rupiah(total) }}</span>
          </div>
        </div>

        <!--
          Ditulis di sini, sebelum tombol konfirmasi. Ini janji harga yang belum
          bisa disebut angkanya — dan pelanggan berhak tahu itu sebelum membayar,
          bukan setelah teknisi datang.
        -->
        <div class="mt-4 rounded-xl bg-(--color-tertiary-container)/40 border-l-4 border-(--color-gold) p-3.5 flex gap-2">
          <Icon name="info" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-on-tertiary-container)" />
          <p class="text-[11.5px] leading-relaxed text-(--color-on-surface-variant)">
            Harga freon dan perbaikan ditentukan setelah diagnosis teknisi di lokasi, dan
            harus Anda setujui sebelum dikerjakan. Biaya pemeriksaan ini dipotong dari
            total servis kalau pekerjaannya dilanjutkan.
          </p>
        </div>
      </section>
    </main>

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <!--
        Hanya muncul kalau potongannya BENAR-BENAR terpakai. Kode yang sudah
        diketik tapi ditolak syaratnya tidak menghemat apa pun, dan spanduk yang
        tetap tampil di situ adalah janji yang tidak ditepati tagihannya.
      -->
      <div
        v-if="hasilPromo.potongan"
        class="bg-(--color-secondary-container) text-(--color-on-secondary-container) py-2 px-4"
      >
        <p class="max-w-[430px] mx-auto text-[12.5px] font-bold text-center">
          🎉 Total hematmu {{ rupiah(hasilPromo.potongan) }} di pesanan ini!
        </p>
      </div>

      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <button
          type="button"
          class="flex items-center gap-1 mb-2.5 text-[12.5px] font-semibold text-(--color-on-surface-variant) active:scale-95 transition-transform"
          @click="sheetOpen = true"
        >
          {{ metodeLabel }}
          <Icon name="chevron-down" class="w-3.5 h-3.5" />
        </button>

        <!--
          Panahnya animasi Lottie di dalam tombol, bukan ikon diam: tombol ini
          yang mengakhiri pemesanan, dan gerakan kecil ke kanan menandai arah
          langkah berikutnya. Total dan tombol dipisah ke dua sisi supaya
          angkanya terbaca tepat sebelum ditekan.
        -->
        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-between gap-2 pl-5 pr-1.5 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="memproses"
          @click="konfirmasi"
        >
          <span>{{ memproses ? 'Memproses…' : 'Konfirmasi Pesanan' }}</span>
          <span class="flex items-center gap-2 shrink-0">
            <span class="text-[14.5px] font-extrabold">{{ rupiah(total) }}</span>
            <LottieIcon v-if="!memproses" :data="panahLottie" :size="36" />
            <span v-else class="w-9"></span>
          </span>
        </button>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>

    <Teleport to="body">
      <div v-if="sheetOpen" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
        <div class="absolute inset-0 bg-black/45" @click="sheetOpen = false"></div>

        <div
          class="relative w-full md:w-96 max-h-[85dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)"
        >
          <div class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mt-3 mb-1 shrink-0 md:hidden"></div>

          <div class="flex items-center justify-between px-5 py-3.5 shrink-0">
            <h3 class="font-extrabold text-[17px]">Mau bayar pakai apa?</h3>
            <button
              type="button"
              aria-label="Tutup"
              class="w-8 h-8 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform"
              @click="sheetOpen = false"
            >
              <Icon name="x" class="w-4 h-4" />
            </button>
          </div>

          <div class="overflow-y-auto flex-1 pb-6 px-5">
            <div class="flex flex-col gap-2">
              <button
                v-for="m in METODE"
                :key="m"
                type="button"
                class="w-full flex items-center gap-3 p-3 rounded-xl text-left transition-colors"
                :class="metodeDipilih === m ? 'bg-(--color-azure)/8' : 'active:bg-(--color-surface-container)'"
                @click="metodeDipilih = m; sheetOpen = false"
              >
                <MetodeBayarIcon :id="m" />
                <span class="flex-1 min-w-0 text-[14px] font-bold truncate">{{ LABEL_METODE[m] }}</span>
                <span
                  v-if="metodeDipilih === m"
                  class="w-5 h-5 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0"
                >
                  <Icon name="check" class="w-3 h-3 text-white" />
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>
