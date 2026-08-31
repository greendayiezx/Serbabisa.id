<script setup lang="ts">
/**
 * Pemesanan Servis AC — langkah 2: konfirmasi.
 *
 * Yang dikumpulkan di sini semuanya TIDAK mengubah harga layanan: data
 * pemesan, jadwal, langganan rutin, catatan, dan kode promo (yang potongannya
 * pun dihitung ulang server). Itu sebabnya ia dipisah dari langkah 1 — di sana
 * setiap ketukan menggeser angka di footer, di sini tidak.
 *
 * Seperti checkout lain di aplikasi ini, yang dikirim hanya PILIHAN. Total di
 * layar adalah estimasi; yang menagih App\Services\ACTarif di server.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import MetodeBayarIcon from '@/components/MetodeBayarIcon.vue'
import DatePickerField from '@/components/DatePickerField.vue'
import TimePickerField from '@/components/TimePickerField.vue'
import { useServisACStore } from '@/stores/servisAC'
import { useAuthStore } from '@/stores/auth'
import { pesanServisAC } from '@/api/servisAC'
import { pesanError } from '@/api/belanja'
import { rupiah } from '@/lib/rupiah'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import { DISKON_RUTIN_PERSEN, PAKET_AC, RUTIN_AC, hitungHargaAC } from '@/lib/servis-ac/hargaAC'
import { PROMO_AC, cariPromoAC, hitungPromoAC } from '@/lib/promo/promoAC'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const acStore = useServisACStore()
const authStore = useAuthStore()

const draft = computed(() => acStore.draft)
const namaPaket = computed(
  () => PAKET_AC.find((p) => p.id === draft.value?.paket)?.nama ?? draft.value?.paket ?? '',
)

/* ────────── Data pemesan ────────── */
const namaPemesan = ref('')
const telepon = ref('')
const ditandaiData = ref(false)

/* ────────── Jadwal ────────── */
const tanggal = ref('')
const waktu = ref('')
const ditandaiJadwal = ref(false)

/* Jadwal rutin: potongannya untuk kunjungan BERIKUTNYA, bukan yang ini. */
const rutinAktif = ref(false)
const rutin = ref('3-bulan')

const catatan = ref('')

onMounted(() => {
  /*
   * Tanpa draf tidak ada yang bisa dikonfirmasi — dan menampilkan halaman
   * kosong hanya membuat orang menekan tombol yang pasti gagal. Terjadi kalau
   * URL ini dibuka langsung atau halaman disegarkan.
   */
  if (!acStore.draft) {
    router.replace({ name: 'servis-ac-pesan' })
    return
  }

  // Diisi dari akun sebagai titik awal, bukan dikunci: yang ditemui teknisi di
  // lokasi belum tentu pemilik akun.
  namaPemesan.value = authStore.user?.name ?? ''
  telepon.value = authStore.user?.phone ?? ''

  const dariKatalog = String(route.query.promo ?? '')
  if (dariKatalog) pilihPromo(dariKatalog.toUpperCase())
})

/* ────────── Harga ────────── */
const rincian = computed(() => hitungHargaAC(draft.value?.paket ?? 'standard', draft.value?.unit ?? 1))

/* ────────── Promo ────────── */
const promoKode = ref<string | null>(null)
const kodeInput = ref('')
const promoPesan = ref<{ ok: boolean; teks: string } | null>(null)

const promoTerpakai = computed(() => cariPromoAC(promoKode.value))
const hasilPromo = computed(() =>
  hitungPromoAC(promoTerpakai.value, rincian.value.total, draft.value?.unit ?? 1),
)

const semuaPromo = computed(() =>
  PROMO_AC.map((p) => {
    const hasil = hitungPromoAC(p, rincian.value.total, draft.value?.unit ?? 1)
    return {
      promo: p,
      hasil,
      alasan: hasil.alasan ?? (hasil.kurang > 0 ? `Kurang ${rupiah(hasil.kurang)} lagi` : null),
    }
  }),
)

const total = computed(() => rincian.value.total - hasilPromo.value.potongan)

function pilihPromo(kode: string) {
  // Dicari dari SEMUA promo, bukan hanya yang tampil: kode yang diketik sendiri
  // harus tetap dapat alasan penolakan yang benar.
  const d = semuaPromo.value.find((x) => x.promo.kode === kode)
  if (!d) return

  if (!d.hasil.berlaku) {
    promoPesan.value = { ok: false, teks: d.alasan ?? 'Promo ini belum bisa dipakai.' }
    return
  }

  promoKode.value = d.promo.kode
  kodeInput.value = ''
  promoPesan.value = {
    ok: true,
    teks: `${d.promo.kode} dipakai — hemat ${rupiah(d.hasil.potongan)}.`,
  }
}

function pakaiKode() {
  const kode = kodeInput.value.trim().toUpperCase()
  if (!kode) return

  if (!cariPromoAC(kode)) {
    promoPesan.value = { ok: false, teks: 'Kode promo tidak ditemukan.' }
    return
  }

  pilihPromo(kode)
}

function lepasPromo() {
  promoKode.value = null
  promoPesan.value = null
}

/**
 * Katalog promo Servis AC. Jumlah unit ikut dikirim karena sebagian promo
 * mensyaratkannya — tanpa itu katalog akan menandai promo bisa dipakai padahal
 * belum.
 */
function keKatalogPromo() {
  router.push({
    name: 'promo-layanan',
    params: { layanan: 'ac' },
    query: {
      dari: '/tasks/new/servis-ac/konfirmasi',
      nilai: String(rincian.value.total),
      unit: String(draft.value?.unit ?? 1),
    },
  })
}

/* ────────── Metode pembayaran ────────── */
const METODE: MetodeId[] = ['bca', 'mandiri', 'bni', 'gopay', 'ovo', 'qris']
const metodeDipilih = ref<MetodeId>('bca')
const sheetOpen = ref(false)
const metodeLabel = computed(() => LABEL_METODE[metodeDipilih.value] ?? metodeDipilih.value)

/* ────────── Kirim ────────── */
const memproses = ref(false)
const galat = ref<string | null>(null)
const rincianTampil = ref(false)

function keBagian(id: string) {
  document.getElementById(id)?.scrollIntoView({ behavior: 'smooth', block: 'center' })
}

async function konfirmasi() {
  const d = draft.value
  if (!d || memproses.value) return

  if (!namaPemesan.value.trim() || !telepon.value.trim()) {
    galat.value = 'Nama dan nomor telepon belum diisi.'
    ditandaiData.value = true
    keBagian('data-pemesan')
    return
  }
  if (!tanggal.value || !waktu.value) {
    galat.value = 'Jadwal kunjungan belum dipilih.'
    ditandaiJadwal.value = true
    keBagian('jadwal')
    return
  }

  memproses.value = true
  galat.value = null

  try {
    const catatanFinal = [
      d.kondisiLainnya ? `Kondisi lain: ${d.kondisiLainnya}` : '',
      catatan.value.trim(),
    ]
      .filter(Boolean)
      .join(' · ')

    const hasil = await pesanServisAC({
      paket: d.paket,
      unit: d.unit,
      tipe: d.tipe,
      kapasitas: d.kapasitas,
      terakhir_cuci: d.terakhirCuci,
      kondisi: [...d.kondisi],
      rutin: rutinAktif.value ? rutin.value : null,
      catatan: catatanFinal || undefined,
      tanggal: tanggal.value,
      waktu: waktu.value,
      nama_penerima: namaPemesan.value.trim(),
      telepon_penerima: telepon.value.trim(),
      lokasi_alamat: d.alamat,
      lokasi_lat: d.lat,
      lokasi_lng: d.lng,
      metode: metodeDipilih.value,
      promo_kode: promoTerpakai.value?.kode,
    })

    const nomor = hasil.nomor_invoice ?? String(hasil.id)
    acStore.nomorTerakhir = nomor
    acStore.hapus()

    if (hasil.rincian?.promo_ditolak) {
      // Pesanan tetap dibuat, tapi tanpa potongan — pengguna harus tahu.
      galat.value = `Promo tidak terpakai: ${hasil.rincian.promo_ditolak}`
    }

    router.replace({ name: 'servis-ac-selesai', params: { nomor } })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-40">
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
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Konfirmasi Pesanan</h1>
      </div>
    </header>

    <main v-if="draft" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- Ringkasan pilihan dari langkah sebelumnya -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center justify-between gap-3 mb-3">
          <h2 class="text-[14px] font-display font-extrabold">Layanan</h2>
          <button
            type="button"
            class="text-[12.5px] font-bold text-(--color-azure) active:scale-95 transition-transform"
            @click="kembali"
          >
            Ubah
          </button>
        </div>

        <div class="flex flex-col gap-2 text-[13px]">
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Paket</span>
            <span class="font-bold text-right">{{ namaPaket }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Jumlah unit</span>
            <span class="font-bold">{{ draft.unit }} unit</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant) shrink-0">Lokasi</span>
            <span class="font-bold text-right leading-snug">{{ draft.alamat }}</span>
          </div>
        </div>
      </section>

      <!-- Data pemesan -->
      <section id="data-pemesan" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-1 flex items-center gap-2">
          <Icon name="user" class="w-5 h-5 text-(--color-azure)" />
          Data Pemesan
        </h2>
        <!--
          Diisi dari akun, tapi tetap bisa diubah: yang ditemui teknisi di lokasi
          belum tentu pemilik akun — bisa penghuni, penjaga, atau pengurus rumah.
        -->
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-4 leading-snug">
          Yang akan ditemui teknisi di lokasi. Boleh berbeda dari pemilik akun.
        </p>

        <label class="block mb-3">
          <span class="text-[12.5px] font-bold">Nama lengkap</span>
          <input
            v-model="namaPemesan"
            type="text"
            placeholder="Nama yang menemui teknisi"
            class="mt-1.5 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 outline-none focus:border-(--color-azure)"
            :class="
              ditandaiData && !namaPemesan.trim()
                ? 'border-(--color-error)'
                : 'border-transparent'
            "
          />
        </label>

        <label class="block">
          <span class="text-[12.5px] font-bold">Nomor telepon</span>
          <input
            v-model="telepon"
            type="tel"
            inputmode="tel"
            placeholder="08xxxxxxxxxx"
            class="mt-1.5 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 outline-none focus:border-(--color-azure)"
            :class="
              ditandaiData && !telepon.trim() ? 'border-(--color-error)' : 'border-transparent'
            "
          />
        </label>

        <p
          v-if="ditandaiData && (!namaPemesan.trim() || !telepon.trim())"
          class="text-[11.5px] font-semibold text-(--color-error) mt-2.5"
        >
          Nama dan nomor telepon dibutuhkan supaya teknisi bisa menghubungi saat tiba.
        </p>
      </section>

      <!-- Jadwal -->
      <section id="jadwal" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[15px] font-display font-extrabold mb-4 flex items-center gap-2">
          <Icon name="clock" class="w-5 h-5 text-(--color-azure)" />
          Jadwal Kunjungan
        </h2>

        <div class="grid grid-cols-2 gap-3">
          <DatePickerField v-model="tanggal" wajib :ditandai="ditandaiJadwal" />
          <TimePickerField v-model="waktu" wajib :ditandai="ditandaiJadwal" />
        </div>

        <p
          v-if="ditandaiJadwal && (!tanggal || !waktu)"
          class="text-[11.5px] font-semibold text-(--color-error) mt-2.5"
        >
          Pilih tanggal dan waktu kunjungan dulu ya.
        </p>
      </section>

      <!-- Jadwal rutin -->
      <section
        class="bg-(--color-primary-container)/40 rounded-2xl p-5 border border-(--color-azure)/25"
      >
        <div class="flex items-start justify-between gap-4">
          <div class="flex-1 min-w-0">
            <h3 class="text-[13.5px] font-extrabold flex items-center gap-2">
              <span class="material-symbols-outlined text-[20px]" data-icon="event_repeat">
                event_repeat
              </span>
              Jadwalkan cuci rutin
            </h3>
            <!--
              Potongannya untuk kunjungan BERIKUTNYA, bukan yang ini. Ditulis
              terang-terangan supaya tidak dikira memotong tagihan sekarang.
            -->
            <p class="mt-1 text-[11.5px] text-(--color-on-surface-variant) leading-snug">
              Diskon {{ DISKON_RUTIN_PERSEN }}% untuk kunjungan berikutnya — tagihan hari ini tidak
              berubah.
            </p>

            <div v-if="rutinAktif" class="mt-3 flex gap-2">
              <button
                v-for="r in RUTIN_AC"
                :key="r.id"
                type="button"
                class="flex-1 py-2 rounded-full border text-[12.5px] font-semibold transition-colors"
                :class="
                  rutin === r.id
                    ? 'bg-(--color-azure) border-(--color-azure) text-white'
                    : 'border-(--color-azure)/40 text-(--color-on-primary-container)'
                "
                @click="rutin = r.id"
              >
                {{ r.nama }}
              </button>
            </div>
          </div>

          <button
            type="button"
            role="switch"
            :aria-checked="rutinAktif"
            aria-label="Aktifkan jadwal rutin"
            class="relative w-11 h-6 rounded-full shrink-0 transition-colors"
            :class="rutinAktif ? 'bg-(--color-azure)' : 'bg-(--color-outline)'"
            @click="rutinAktif = !rutinAktif"
          >
            <span
              class="absolute top-0.5 left-0.5 w-5 h-5 rounded-full bg-white transition-transform"
              :class="rutinAktif ? 'translate-x-5' : ''"
            />
          </button>
        </div>
      </section>

      <!-- Promo -->
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
            Lepas
          </button>
        </div>

        <div v-else class="flex gap-2">
          <div class="relative flex-1">
            <span
              class="absolute inset-y-0 left-0 pl-3 flex items-center text-(--color-on-surface-variant)"
            >
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

      <!-- Catatan -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-2">Catatan untuk teknisi</h3>
        <textarea
          v-model="catatan"
          rows="3"
          placeholder="Misal: AC di lantai 2, rumah pagar hijau"
          class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
        />
      </section>
    </main>

    <!-- Ringkasan & aksi -->
    <footer
      v-if="draft"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) rounded-t-2xl shadow-[0_-10px_40px_rgba(0,0,0,0.10)]"
    >
      <div
        class="max-w-[430px] mx-auto px-4 pt-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))]"
      >
        <div
          v-if="rincianTampil"
          class="mb-3 pb-3 border-b border-(--color-outline)/20 flex flex-col gap-1.5"
        >
          <div
            v-for="(b, i) in rincian.baris"
            :key="i"
            class="flex justify-between gap-3 text-[12.5px]"
            :class="
              b.potongan ? 'text-(--color-on-secondary-container)' : 'text-(--color-on-surface-variant)'
            "
          >
            <span>{{ b.label }}</span>
            <span class="font-bold whitespace-nowrap">
              <template v-if="b.potongan">&minus;</template>{{ rupiah(b.nilai) }}
            </span>
          </div>
          <div
            v-if="hasilPromo.potongan"
            class="flex justify-between gap-3 text-[12.5px] text-(--color-error)"
          >
            <span>Promo {{ promoTerpakai?.kode }}</span>
            <span class="font-bold whitespace-nowrap">&minus;{{ rupiah(hasilPromo.potongan) }}</span>
          </div>
        </div>

        <button
          type="button"
          class="w-full flex items-center justify-between gap-3 mb-3"
          :aria-expanded="rincianTampil"
          @click="rincianTampil = !rincianTampil"
        >
          <span class="text-[13px] font-bold">Total Estimasi</span>
          <span class="flex items-center gap-1.5 text-(--color-azure)">
            <span class="text-[20px] font-extrabold">{{ rupiah(total) }}</span>
            <Icon
              name="chevron-down"
              class="w-4 h-4 transition-transform"
              :class="rincianTampil ? 'rotate-180' : ''"
            />
          </span>
        </button>

        <div class="flex items-center gap-3">
          <button
            type="button"
            class="flex items-center gap-1 text-[12.5px] font-semibold text-(--color-on-surface-variant) shrink-0 active:scale-95 transition-transform"
            @click="sheetOpen = true"
          >
            {{ metodeLabel }}
            <Icon name="chevron-down" class="w-3.5 h-3.5" />
          </button>

          <button
            type="button"
            class="flex-1 h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
            :disabled="memproses"
            @click="konfirmasi"
          >
            {{ memproses ? 'Memproses…' : 'Konfirmasi Pesanan' }}
            <Icon v-if="!memproses" name="arrow-right" class="w-4 h-4" />
          </button>
        </div>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>

    <!-- Sheet metode pembayaran -->
    <Teleport to="body">
      <div v-if="sheetOpen" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
        <div class="absolute inset-0 bg-black/45" @click="sheetOpen = false"></div>

        <div
          class="relative w-full md:w-96 max-h-[85dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)"
        >
          <div
            class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mt-3 mb-1 shrink-0 md:hidden"
          ></div>

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
                <span class="flex-1 min-w-0 text-[14px] font-bold truncate">
                  {{ LABEL_METODE[m] }}
                </span>
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
