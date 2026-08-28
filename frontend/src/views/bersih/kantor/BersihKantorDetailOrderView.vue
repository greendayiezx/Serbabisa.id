<script setup lang="ts">
/**
 * Detail Order — BisaBersih Kantor.
 *
 * Halaman ini tampil setelah ada cleaner yang MENERIMA pesanan — layar tunggu
 * (BersihKantorMencariView) yang memindahkan ke sini. Isinya alamat, jadwal,
 * cleaner yang bertugas, dan rincian tagihan.
 *
 * Sumber datanya server, bukan draf di memori: draf hilang begitu halaman
 * dimuat ulang, sedangkan nomor invoice di URL selalu bisa dibaca ulang. Draf
 * hanya dipakai untuk baris rincian harga yang belum dikirim server.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import CleanerAvatar from '@/components/bersih/CleanerAvatar.vue'
import LacakCleanerSheet from '@/components/bersih/LacakCleanerSheet.vue'
import DetailOrderSkeleton from '@/components/skeleton/DetailOrderSkeleton.vue'
import { useSkeleton } from '@/composables/useSkeleton'
import bersihKantorImg from '@/assets/BersihKantor.png'
import { rupiah } from '@/lib/rupiah'
import {
  PAKET_KANTOR,
  JENIS_KANTOR,
  FREKUENSI_KANTOR,
  hitungHargaKantor,
  type PaketKantorId,
} from '@/lib/bersih/hargaBersihKantor'
import { usePenawaranKantorStore } from '@/stores/penawaranKantor'
import { usePromoBersihKantorStore } from '@/stores/promoBersihKantor'
import { hitungPromoKantor } from '@/lib/promo/promoBersihKantor'
import { ambilStatusPesananBersih, type StatusPesananBersih } from '@/api/bersih'
import { tautanInvoicePdf } from '@/api/invoice'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const penawaranStore = usePenawaranKantorStore()
const promoStore = usePromoBersihKantorStore()

/* ────────── Data pesanan ────────── */

/**
 * Pesanan sebagaimana tersimpan di server.
 *
 * Draf di memori hanya sisa dari layar sebelumnya: begitu pesanan dibuat, yang
 * berlaku adalah baris di database — termasuk alamat yang baru saja diedit di
 * halaman konfirmasi. Draf juga hilang begitu halaman ini dimuat ulang atau
 * dibuka lewat tautan, sedangkan nomor invoice di URL selalu bisa dibaca ulang.
 */
const dariServer = ref<StatusPesananBersih | null>(null)

/*
 * `kerangka` menahan skeleton di layar; `memuat` menjawab "datanya sudah
 * sampai belum" dan dipakai label status. Keduanya dibedakan karena skeleton
 * bertahan minimal 180 ms, sedikit lebih lama daripada pengambilan datanya.
 */
const { tampil: kerangka, tandaiSiap } = useSkeleton()
const memuat = ref(true)

onMounted(async () => {
  try {
    dariServer.value = await ambilStatusPesananBersih(invoiceNomor.value)
  } catch {
    // Biarkan kosong — tampilan jatuh ke draf sesi, bukan halaman kosong.
  } finally {
    memuat.value = false
    tandaiSiap()
  }
})

/**
 * Nomor invoice — kalau datang dari konfirmasi, ambil dari query param.
 * Kalau langsung dibuka (deep link), pakai nomor di URL.
 */
const invoiceNomor = computed(() =>
  String(route.query.invoice ?? route.params.nomor ?? 'INV-' + Date.now()),
)

/**
 * Data pesanan dari store (masih di memori setelah bayar).
 * Kalau store kosong (refresh / deep link), pakai data dummy yang masuk akal.
 */
const draft = computed(() => penawaranStore.draft)
const pesanan = computed(() => penawaranStore.pesanan)

const paketId = computed(() => (draft.value?.paketId ?? 'professional') as PaketKantorId)
const paketAktif = computed(
  () => PAKET_KANTOR.find((p) => p.id === paketId.value) ?? PAKET_KANTOR[1],
)
const jenisAktif = computed(
  () => JENIS_KANTOR.find((j) => j.id === (draft.value?.jenisId ?? pesanan.value?.jenisId ?? 'sedang')) ?? JENIS_KANTOR[1],
)
const frekuensiAktif = computed(
  () => FREKUENSI_KANTOR.find((f) => f.id === (draft.value?.frekuensiId ?? pesanan.value?.frekuensiId ?? '2x-minggu')) ?? FREKUENSI_KANTOR[2],
)

const luasHitung = computed(() => {
  const l = pesanan.value?.luasM2
  return l && l > 0 ? l : jenisAktif.value.luasAcuan
})

const rincian = computed(() =>
  hitungHargaKantor({
    paketId: paketId.value,
    luasM2: luasHitung.value,
    jumlahLantai: pesanan.value?.jumlahLantai ?? 1,
    workstation: draft.value?.workstation ?? pesanan.value?.workstation ?? 0,
    ruangMeeting: draft.value?.ruangMeeting ?? pesanan.value?.ruangMeeting ?? 0,
    toilet: draft.value?.toilet ?? 2,
    pantry: draft.value?.pantry ?? 1,
    addOnDipilih: draft.value?.addOnId ?? [],
    frekuensiId: frekuensiAktif.value.id,
  }),
)

/* ────────── Promo ────────── */
const promoTerpakai = computed(() => promoStore.voucher())
const hasilPromo = computed(() =>
  hitungPromoKantor(promoTerpakai.value, rincian.value.totalPerKunjungan),
)
/**
 * Total yang ditagihkan.
 *
 * Angka server dipakai lebih dulu karena itulah yang benar-benar dibayar;
 * perhitungan lokal hanya cadangan selagi datanya belum sampai.
 */
const total = computed(
  () => dariServer.value?.total ?? rincian.value.totalPerKunjungan - hasilPromo.value.potongan,
)

/* ────────── Cleaner yang bertugas ────────── */

/**
 * Hanya dari server. Tidak ada nama cadangan yang dikarang: kalau belum ada
 * yang menerima, yang benar adalah mengatakannya — bukan menampilkan seseorang
 * yang tidak pernah ditugaskan ke pekerjaan ini.
 */
const cleaner = computed(() => dariServer.value?.cleaner ?? null)

/**
 * Jumlah orang yang diberangkatkan, dihitung dari luas dan paket saat checkout
 * (KantorTarif::jumlahKru) dan ikut tersimpan pada pesanan.
 */
const jumlahKru = computed(() => dariServer.value?.jumlah_cleaner ?? 0)

/** Anggota selain cleaner utama. */
const kruLain = computed(() => Math.max(0, jumlahKru.value - (cleaner.value ? 1 : 0)))

const kruTerbuka = ref(false)

/**
 * "-" selama belum ada ulasan.
 *
 * Rating nol berarti BELUM DINILAI, bukan dinilai buruk — menampilkannya
 * sebagai 0,0 memberi kesan yang keliru tentang orang yang bekerja.
 */
function nilai(angka: number, jumlahUlasan: number): string {
  return jumlahUlasan > 0 ? angka.toLocaleString('id-ID') : '-'
}

/* ────────── Hubungi cleaner ────────── */

/** Nomor baru dikirim server SETELAH pesanan diterima. */
const nomorTelepon = computed(() => cleaner.value?.telepon ?? null)

function keChat() {
  const id = dariServer.value?.task_id
  if (!id) return
  router.push({ name: 'task-chat', params: { id } })
}

/* ────────── Lacak cleaner ────────── */
const lacakTampil = ref(false)

/** Belum ada yang menerima berarti belum ada yang bisa dilacak. */
const bisaDilacak = computed(
  () => dariServer.value?.diterima === true && dariServer.value?.lokasi.lat != null,
)

/* ────────── Invoice PDF ────────── */

/**
 * Tautan bertanda tangan disiapkan lebih dulu, lalu dipasang pada <a> sungguhan.
 *
 * Bukan window.open lalu diisi belakangan: penunda seperti itu kehilangan
 * "gerakan pengguna" yang dibutuhkan peramban, dan tabnya diblokir atau berhenti
 * di about:blank.
 */
const tautanInvoice = ref<string | null>(null)

async function siapkanInvoice() {
  try {
    tautanInvoice.value = await tautanInvoicePdf(invoiceNomor.value)
  } catch {
    // Dibiarkan null: tombolnya tampil nonaktif, bukan menjanjikan sesuatu
    // yang tidak bisa dibuka.
    tautanInvoice.value = null
  }
}

/**
 * Tanda tangan URL punya masa berlaku. Halaman yang lama dibiarkan terbuka akan
 * memegang tautan basi, jadi disegarkan tiap kali tabnya dilihat lagi.
 */
function onTampak() {
  if (document.visibilityState === 'visible') void siapkanInvoice()
}

onMounted(() => {
  void siapkanInvoice()
  document.addEventListener('visibilitychange', onTampak)
})

onBeforeUnmount(() => document.removeEventListener('visibilitychange', onTampak))

/* ────────── Jadwal ────────── */
const tanggal = computed(() => pesanan.value?.tanggal ?? (route.query.tanggal as string) ?? '')
const waktu = computed(() => pesanan.value?.waktu ?? (route.query.waktu as string) ?? '')

const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

/**
 * Jadwal pengerjaan.
 *
 * Server menyimpan waktu dalam UTC, jadi penanggalannya dibaca ulang di zona
 * WIB — tanpa itu jadwal pagi bisa tampil sebagai hari sebelumnya.
 */
const jadwalTeks = computed(() => {
  const iso = dariServer.value?.dijadwalkan_pada
  if (iso) {
    const d = new Date(iso)
    const bagian = new Intl.DateTimeFormat('en-CA', {
      timeZone: 'Asia/Jakarta',
      year: 'numeric', month: '2-digit', day: '2-digit',
      hour: '2-digit', minute: '2-digit', hour12: false,
    }).formatToParts(d)
    const ambil = (t: string) => bagian.find((b) => b.type === t)?.value ?? ''
    return `${ambil('day')} ${BULAN[Number(ambil('month')) - 1]} ${ambil('year')}, ${ambil('hour')}:${ambil('minute')}`
  }

  if (!tanggal.value) return '-'
  const d = new Date(tanggal.value + 'T00:00:00')
  if (Number.isNaN(d.getTime())) return tanggal.value
  return `${String(d.getDate()).padStart(2, '0')} ${BULAN[d.getMonth()]} ${d.getFullYear()}, ${waktu.value || '00:00'}`
})

/* ────────── Alamat ────────── */

/**
 * Alamat yang benar-benar tercatat pada pesanan.
 *
 * Urutannya disengaja: server dulu, draf belakangan. Alamat yang diubah di
 * halaman konfirmasi ikut terkirim saat checkout, jadi baris di server-lah yang
 * paling baru — draf sesi bisa saja masih memegang alamat sebelum diedit.
 */
const alamat = computed(
  () => dariServer.value?.lokasi.alamat ?? pesanan.value?.alamat ?? (route.query.alamat as string) ?? '',
)
const alamatTitle = computed(() => alamat.value.split(',')[0] ?? alamat.value)

/* ────────── Status ────────── */
const statusLabel = computed(() => {
  switch (dariServer.value?.status) {
    case 'accepted': return 'Cleaner Dalam Perjalanan'
    case 'in_progress': return 'Sedang Dikerjakan'
    case 'completed': return 'Selesai'
    case 'cancelled': return 'Dibatalkan'
    case 'pending': return 'Menunggu Cleaner'
    default: return memuat.value ? 'Memuat…' : 'Menunggu Cleaner'
  }
})

/* ────────── Batalkan ────────── */
const konfirmasiBatal = ref(false)
const membatalkan = ref(false)

function batalkanPesanan() {
  if (!konfirmasiBatal.value) {
    konfirmasiBatal.value = true
    return
  }
  membatalkan.value = true
  // Simulasi pembatalan
  setTimeout(() => {
    membatalkan.value = false
    konfirmasiBatal.value = false
    router.replace({ name: 'home' })
  }, 1500)
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-10">
    <!-- Header -->
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
        <h1 class="flex-1 text-center text-[17px] font-extrabold pr-10">Detail Order</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <DetailOrderSkeleton v-if="kerangka" />

      <template v-else>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  1) ALAMAT PENGERJAAN                                   ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-extrabold mb-2">Alamat Pengerjaan</h3>
        <p class="text-[13px] font-bold">{{ alamatTitle || 'Alamat kantor' }}</p>
        <p class="text-[12px] text-(--color-on-surface-variant) leading-snug mt-0.5">
          {{ alamat || 'Alamat belum diisi' }}
        </p>
      </section>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  2) LAYANAN + FOTO                                     ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-start gap-3.5">
          <div class="flex-1 min-w-0">
            <h2 class="text-[16px] font-extrabold">{{ paketAktif.nama }}</h2>
            <p class="text-[12px] text-(--color-on-surface-variant) mt-0.5">
              {{ jadwalTeks }}
            </p>
            <p class="text-[12px] text-(--color-on-surface-variant)">
              {{ frekuensiAktif.label }}
            </p>
          </div>
          <img
            :src="bersihKantorImg"
            alt="Bersih Kantor"
            class="w-16 h-16 rounded-xl object-cover shrink-0 shadow-sm"
          />
        </div>

        <!-- Status -->
        <div class="mt-4 pt-4 border-t border-(--color-outline)/10">
          <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="text-[11px] text-(--color-on-surface-variant)">Status</p>
              <p class="text-[15px] font-extrabold mt-0.5 flex items-center gap-2">
                <span class="relative w-2 h-2 shrink-0">
                  <span class="absolute inset-0 rounded-full bg-(--color-azure) animate-ping"></span>
                  <span class="absolute inset-0 rounded-full bg-(--color-azure)"></span>
                </span>
                {{ statusLabel }}
              </p>
            </div>
            <button
              v-if="bisaDilacak"
              type="button"
              class="text-[12.5px] font-bold text-(--color-azure) shrink-0 active:scale-95 transition-transform"
              @click="lacakTampil = true"
            >
              Lacak
            </button>
          </div>
        </div>

        <!-- Invoice -->
        <div class="mt-3 pt-3 border-t border-(--color-outline)/10">
          <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="text-[11px] text-(--color-on-surface-variant)">Invoice</p>
              <p class="text-[15px] font-extrabold mt-0.5">{{ invoiceNomor }}</p>
            </div>
            <!--
              <a> sungguhan, bukan tombol ber-JavaScript: peramban ponsel
              memblokir tab yang dibuka di luar gerakan pengguna, dan tautan
              nyata tidak pernah terkena itu.
            -->
            <a
              v-if="tautanInvoice"
              :href="tautanInvoice"
              target="_blank"
              rel="noopener"
              class="text-[12.5px] font-bold text-(--color-azure) shrink-0 active:scale-95 transition-transform"
            >
              Lihat PDF
            </a>
            <span v-else class="text-[12.5px] font-bold text-(--color-on-surface-variant)/50 shrink-0">
              Lihat PDF
            </span>
          </div>
        </div>

        <!--
          Jadwal tanpa tombol "Ubah": pesanan sudah terkirim dan cleaner sudah
          dijadwalkan, jadi perubahan waktu tidak lagi bisa dilakukan sendiri di
          layar ini. Menampilkan tombol yang tidak berfungsi lebih buruk
          daripada tidak menampilkannya sama sekali.
        -->
        <div class="mt-3 pt-3 border-t border-(--color-outline)/10">
          <p class="text-[11px] text-(--color-on-surface-variant) italic">Jadwal Pengerjaan</p>
          <p class="text-[15px] font-extrabold mt-0.5">{{ jadwalTeks }}</p>
        </div>
      </section>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  3) HELPER DETAIL (cleaner dummy)                      ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center justify-between gap-3 mb-4">
          <h3 class="text-[14px] font-extrabold">Cleaner Detail</h3>
          <div class="flex items-center gap-2">
            <!--
              Nomor telepon baru dikirim server setelah pesanan diterima, jadi
              tombolnya memang belum bisa dipakai sebelum itu — ditampilkan
              redup, bukan disembunyikan, supaya jelas bahwa itu akan ada.
            -->
            <a
              v-if="nomorTelepon"
              :href="`tel:${nomorTelepon}`"
              :aria-label="`Telepon ${cleaner?.nama}`"
              class="w-8 h-8 rounded-full border border-(--color-azure)/40 flex items-center justify-center active:scale-90 transition-transform"
            >
              <Icon name="phone" class="w-4 h-4 text-(--color-azure)" />
            </a>
            <span
              v-else
              aria-hidden="true"
              class="w-8 h-8 rounded-full border border-(--color-outline)/20 flex items-center justify-center opacity-40"
            >
              <Icon name="phone" class="w-4 h-4 text-(--color-on-surface-variant)" />
            </span>

            <button
              type="button"
              :disabled="!dariServer?.task_id"
              :aria-label="cleaner ? `Chat dengan ${cleaner.nama}` : 'Chat pesanan'"
              class="w-8 h-8 rounded-full border flex items-center justify-center active:scale-90 transition-transform disabled:opacity-40"
              :class="dariServer?.task_id ? 'border-(--color-azure)/40' : 'border-(--color-outline)/20'"
              @click="keChat"
            >
              <Icon
                name="chat"
                class="w-4 h-4"
                :class="dariServer?.task_id ? 'text-(--color-azure)' : 'text-(--color-on-surface-variant)'"
              />
            </button>
          </div>
        </div>

        <div v-if="cleaner" class="flex items-center gap-3.5">
          <CleanerAvatar :gender="cleaner.gender ?? undefined" :nama="cleaner.nama" class="w-12 h-12 shrink-0" />
          <div class="flex-1 min-w-0">
            <p class="text-[14px] font-extrabold truncate">{{ cleaner.nama }}</p>
            <div class="flex items-center gap-1.5 mt-0.5 text-[12px] text-(--color-on-surface-variant)">
              <Icon name="star" class="w-3.5 h-3.5 text-(--color-gold)" />
              <span class="font-bold text-(--color-on-surface)">
                {{ nilai(cleaner.rating, cleaner.jumlah_ulasan) }}
              </span>
              <span>({{ cleaner.jumlah_ulasan || 0 }})</span>
              <span v-if="cleaner.nama_level">&middot; {{ cleaner.nama_level }}</span>
            </div>
          </div>
        </div>

        <p v-else class="text-[13px] text-(--color-on-surface-variant)">
          Belum ada cleaner yang ditugaskan. Kami masih mencarikan tim yang
          jadwalnya cocok.
        </p>

        <!-- Anggota tim lainnya -->
        <template v-if="kruLain > 0">
          <button
            type="button"
            class="mt-3 flex items-center gap-1.5 text-[12px] text-(--color-azure) font-bold active:scale-95 transition-transform"
            :aria-expanded="kruTerbuka"
            @click="kruTerbuka = !kruTerbuka"
          >
            <Icon name="users" class="w-3.5 h-3.5" />
            Helper lainnya +{{ kruLain }}
            <Icon
              name="chevron-down"
              class="w-3.5 h-3.5 transition-transform"
              :class="kruTerbuka ? 'rotate-180' : ''"
            />
          </button>

          <!--
            Nama anggota lain memang belum ada di server: yang tercatat pada
            pesanan baru cleaner yang menerima. Slotnya ditampilkan apa adanya
            sebagai "menunggu penugasan" — lebih jujur daripada mengarang nama.
          -->
          <ul v-if="kruTerbuka" class="mt-3 flex flex-col gap-2.5">
            <li v-for="i in kruLain" :key="i" class="flex items-center gap-3">
              <span
                class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0"
              >
                <Icon name="user" class="w-4 h-4 text-(--color-on-surface-variant)" />
              </span>
              <div class="min-w-0">
                <p class="text-[13px] font-bold">Cleaner {{ i + 1 }}</p>
                <p class="text-[11.5px] text-(--color-on-surface-variant)">Menunggu penugasan</p>
              </div>
            </li>
          </ul>

          <p class="mt-3 text-[11.5px] text-(--color-on-surface-variant) leading-snug">
            Total {{ jumlahKru }} orang diberangkatkan untuk kunjungan ini.
          </p>
        </template>
      </section>

      <!-- ╔══════════════════════════════════════════════════════════╗
           ║  4) RINCIAN PEMESANAN                                  ║
           ╚══════════════════════════════════════════════════════════╝ -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-extrabold mb-3">Rincian Pemesanan</h3>

        <div class="flex flex-col gap-2.5 text-[13px]">
          <!-- Nama paket -->
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">{{ paketAktif.nama }}</span>
          </div>

          <!-- Durasi / frekuensi -->
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">
              {{ jenisAktif.nama }} · {{ luasHitung }} m²
            </span>
          </div>

          <!-- Frekuensi -->
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">{{ frekuensiAktif.label }}</span>
            <span class="font-bold">{{ rupiah(rincian.layanan) }}</span>
          </div>

          <!-- Add-on -->
          <div v-if="rincian.addOn" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Layanan Tambahan</span>
            <span class="font-bold">{{ rupiah(rincian.addOn) }}</span>
          </div>

          <!-- Diskon frekuensi -->
          <div v-if="rincian.diskonFrekuensi" class="flex justify-between gap-3 text-(--color-on-secondary-container)">
            <span>Diskon langganan</span>
            <span class="font-bold">&minus;{{ rupiah(rincian.diskonFrekuensi) }}</span>
          </div>

          <!-- Diskon promo -->
          <div v-if="hasilPromo.potongan" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Diskon Promo</span>
            <span class="font-bold text-(--color-error)">&minus;{{ rupiah(hasilPromo.potongan) }}</span>
          </div>

          <!-- Total -->
          <div class="flex justify-between gap-3 pt-3 mt-1 border-t border-(--color-outline)/12">
            <span class="font-extrabold">Total</span>
            <span class="text-[16px] font-extrabold">{{ rupiah(total) }}</span>
          </div>
        </div>
      </section>

      <!-- Batalkan -->
      <section class="mt-1 mb-4">
        <button
          type="button"
          :disabled="membatalkan"
          class="w-full py-3.5 rounded-2xl border-2 text-[14px] font-bold flex items-center justify-center gap-2 active:scale-[0.98] transition-all disabled:opacity-50"
          :class="
            konfirmasiBatal
              ? 'border-(--color-error) bg-(--color-error) text-white'
              : 'border-(--color-outline)/30 text-(--color-on-surface-variant)'
          "
          @click="batalkanPesanan"
        >
          <template v-if="membatalkan">
            Membatalkan…
          </template>
          <template v-else-if="konfirmasiBatal">
            <Icon name="alert" class="w-4 h-4" />
            Yakin Batalkan Pesanan?
          </template>
          <template v-else>
            Batalkan Pemesanan
          </template>
        </button>

        <p
          v-if="konfirmasiBatal && !membatalkan"
          class="text-center text-[11.5px] text-(--color-on-surface-variant) mt-2"
        >
          Ketuk sekali lagi untuk mengonfirmasi pembatalan.
        </p>
      </section>

      </template>

      <LacakCleanerSheet
        :tampil="lacakTampil"
        :lat="dariServer?.lokasi.lat ?? null"
        :lng="dariServer?.lokasi.lng ?? null"
        :nama-cleaner="cleaner?.nama"
        :alamat="alamat"
        @tutup="lacakTampil = false"
      />
    </main>
  </div>
</template>
