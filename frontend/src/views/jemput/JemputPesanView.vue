<script setup lang="ts">
/**
 * BisaJemput — tujuan dan pilihan kendaraan.
 *
 * Titik jemput sudah dikonfirmasi di layar sebelumnya dan ditampilkan di sini
 * apa adanya; kalau belum, halaman ini memulangkan pengguna ke sana alih-alih
 * menebak titiknya sendiri.
 *
 * SEMUA HARGA DI LAYAR INI DATANG DARI SERVER. Tidak ada satu pun tarif yang
 * dihitung di sini, dan itu bukan soal kerapian: tarifnya bergantung jarak
 * jalan, lama tempuh, dan pengali jam sibuk — tiga hal yang kalau disalin ke
 * klien akan menampilkan angka yang berbeda dari yang ditagih.
 */
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PemuatBerputar from '@/components/ui/PemuatBerputar.vue'
import { TILE_URL, TILE_OPTIONS, pinIcon } from '@/lib/mapTiles'
import { useJemputStore } from '@/stores/jemput'
import { estimasiJemput, pesanJemput, type HasilEstimasi } from '@/api/jemput'
import { pesanError } from '@/api/belanja'
import { rentangMenit, rupiah, type PilihanJemput } from '@/lib/jemput'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import bisaJemputMotorImg from '@/assets/BisaJemput_MotorBiasa.svg'
import logoBni from '@/assets/logo_bni.svg'
import logoBri from '@/assets/logo_bri.svg'
import logoDana from '@/assets/logo_dana.svg'
import logoMandiri from '@/assets/logo_mandiri.svg'
import logoOvo from '@/assets/logo_ovo.svg'
import logoQris from '@/assets/logo_qris.svg'
import logoBca from '@/assets/LOGO-BCA.png'
import logoLinkaja from '@/assets/LOGO_LINKAJA.png'
import logoSpay from '@/assets/LOGO-SPAY.png'

const router = useRouter()
const kembali = useKembali()
const jemputStore = useJemputStore()

const kelas = ref<'motor' | 'mobil'>('motor')
const memuat = ref(false)
const galat = ref<string | null>(null)
const hasil = ref<HasilEstimasi | null>(null)
const dipilih = ref<PilihanJemput | null>(null)

const jemput = computed(() => jemputStore.jemput)
const tujuan = computed(() => jemputStore.tujuan)

const pilihanKelas = computed(() =>
  (hasil.value?.pilihan ?? []).filter((p) => p.kelas === kelas.value),
)

/** Satu kartu per kendaraan; varian jadi baris di dalamnya, seperti di Gojek. */
const kartu = computed(() => {
  const per = new Map<string, PilihanJemput[]>()
  for (const p of pilihanKelas.value) {
    per.set(p.tipe, [...(per.get(p.tipe) ?? []), p])
  }
  return [...per.entries()].map(([tipe, varian]) => ({ tipe, varian }))
})

/* ────────── Peta rute ────────── */
const petaEl = ref<HTMLDivElement | null>(null)
let peta: L.Map | null = null
let garis: L.Polyline | null = null
let bayangGaris: L.Polyline | null = null
let penandaJemput: L.Marker | null = null
let penandaTujuan: L.Marker | null = null

function gambarPeta() {
  if (!petaEl.value || !jemput.value) return

  if (!peta) {
    // Bisa digeser dan di-zoom: rute yang panjang tidak muat dalam satu layar,
    // dan orang perlu menyusurinya untuk memastikan jalannya masuk akal.
    peta = L.map(petaEl.value, { zoomControl: false, attributionControl: false })
    L.tileLayer(TILE_URL, TILE_OPTIONS).addTo(peta)
  }

  const a: L.LatLngTuple = [jemput.value.lat, jemput.value.lng]

  // Penanda lama dilepas dulu; menambah terus tanpa melepas meninggalkan
  // tumpukan pin di titik yang sama tiap kali peta digambar ulang.
  penandaJemput?.remove()
  penandaJemput = L.marker(a, { icon: pinIcon('#1e9bf0') }).addTo(peta)

  if (!tujuan.value) {
    penandaTujuan?.remove()
    penandaTujuan = null
    garis?.remove()
    bayangGaris?.remove()
    garis = bayangGaris = null
    peta.setView(a, 16)
    setTimeout(() => peta?.invalidateSize(), 120)
    return
  }

  const b: L.LatLngTuple = [tujuan.value.lat, tujuan.value.lng]
  penandaTujuan?.remove()
  penandaTujuan = L.marker(b, { icon: pinIcon('#f97316') }).addTo(peta)

  /*
   * Garisnya mengikuti jalan, dan titik-titiknya datang dari server — dari
   * perhitungan yang sama yang dipakai menagih. Kalau layanan rute sedang
   * tidak terjangkau, server mengirim geometri kosong dan yang tergambar
   * adalah garis lurus putus-putus: bentuk yang berbeda, supaya tidak terbaca
   * sebagai rute sungguhan.
   */
  const titik: L.LatLngExpression[] =
    (hasil.value?.geometri as L.LatLngExpression[] | null | undefined) ?? [a, b]
  const perkiraan = !hasil.value?.lewat_jalan

  garis?.remove()
  bayangGaris?.remove()

  // Dua lapis: garis gelap tipis di bawah supaya rute tetap terbaca di atas
  // peta yang terang maupun gelap.
  bayangGaris = L.polyline(titik, {
    color: '#1B2C5E',
    weight: 8,
    opacity: 0.25,
    lineJoin: 'round',
  }).addTo(peta)
  garis = L.polyline(titik, {
    color: '#8BC53F',
    weight: 5,
    lineJoin: 'round',
    dashArray: perkiraan ? '8 8' : undefined,
  }).addTo(peta)

  peta.fitBounds(L.latLngBounds(titik as L.LatLngTuple[]).pad(0.2))
  setTimeout(() => peta?.invalidateSize(), 120)
}

async function muatEstimasi() {
  if (!jemput.value || !tujuan.value) return

  memuat.value = true
  galat.value = null
  try {
    hasil.value = await estimasiJemput({
      jemput_lat: jemput.value.lat,
      jemput_lng: jemput.value.lng,
      tujuan_lat: tujuan.value.lat,
      tujuan_lng: tujuan.value.lng,
    })
    pulihkanPilihan()

    // Rutenya baru ada setelah jawaban server datang; peta digambar ulang di
    // sini supaya garisnya mengikuti jalan, bukan garis lurus sementara tadi.
    gambarPeta()
  } catch (e) {
    hasil.value = null
    dipilih.value = null
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
}

onMounted(async () => {
  // Aturan yang sama seperti di server: tanpa titik jemput terkonfirmasi,
  // tidak ada yang boleh dilanjutkan dari sini.
  if (!jemputStore.jemput || !jemputStore.jemputDikonfirmasi) {
    router.replace({ name: 'task-jemput-titik' })
    return
  }

  await nextTick()
  gambarPeta()
  if (tujuan.value) await muatEstimasi()
})

onBeforeUnmount(() => {
  peta?.remove()
  peta = null
})

/*
 * Mengetik tujuan pindah ke halamannya sendiri, bukan lembar bawah: pekerjaan
 * itu butuh papan ketik, daftar hasil, dan riwayat sekaligus — dan lembar bawah
 * menyisakan sepertiga layar untuk ketiganya, lalu tertutup papan ketiknya.
 */
function bukaTujuan() {
  router.push({ name: 'task-jemput-tujuan' })
}

/*
 * Ganti tab hanya memilih ulang kalau pilihan sekarang memang bukan milik tab
 * itu. Tanpa syarat ini, memulihkan pilihan tersimpan justru menimpanya
 * sendiri: menyetel `kelas` memicu watcher ini, dan watcher-nya melompat ke
 * yang termurah.
 */
watch(kelas, (k) => {
  if (dipilih.value?.kelas === k) return
  pilihTermurah()
})

/**
 * Kembali ke halaman ini setelah membuka voucher tidak boleh mengubah pilihan.
 *
 * Halaman ini dipasang ulang tiap kali dibuka, dan tanpa pemulihan ini
 * pilihannya jatuh kembali ke yang termurah — orang yang tadi memilih Mobil
 * lalu mampir ke voucher akan kembali menemukan Motor terpilih, beserta
 * harganya yang berbeda dari yang baru saja ia lihat.
 */
function pulihkanPilihan() {
  const tersimpan = jemputStore.pilihan
  if (tersimpan && hasil.value) {
    const lagi = hasil.value.pilihan.find(
      (p) => p.tipe === tersimpan.tipe && p.varian === tersimpan.varian,
    )
    if (lagi) {
      kelas.value = lagi.kelas
      // Langsung ke dipilih, bukan lewat pilih(): setPilihan() akan menilai
      // ulang promonya, dan promo yang sudah dipilih pengguna harus bertahan.
      dipilih.value = lagi
      jemputStore.setPilihan(lagi)
      return
    }
  }
  pilihTermurah()
}

/**
 * Pilihan termurah di kelas yang sedang dibuka.
 *
 * Lewat pilih() juga, bukan menulis `dipilih` langsung: halaman voucher membaca
 * pilihannya dari store, dan pilihan awal yang tidak ikut tersimpan membuat
 * halaman itu berkata "pilih kendaraan dulu" padahal sudah ada yang terpilih.
 */
function pilihTermurah() {
  if (!hasil.value) return
  const termurah =
    [...hasil.value.pilihan]
      .filter((p) => p.kelas === kelas.value)
      .sort((a, b) => a.tarif_setelah_promo - b.tarif_setelah_promo)[0] ?? null

  if (termurah) pilih(termurah)
  else {
    dipilih.value = null
    jemputStore.setPilihan(null)
  }
}

function pilih(p: PilihanJemput) {
  dipilih.value = p
  // Disimpan begitu dipilih, bukan menunggu tombol pesan: halaman voucher
  // menghitung potongannya dari pilihan ini, dan halaman itu bisa dibuka
  // sebelum pesanan dibuat.
  jemputStore.setPilihan(p)
}

/* ────────── Pembayaran, promo, opsi ────────── */
const lembarMetode = ref(false)
const lembarOpsi = ref(false)

interface Metode {
  id: MetodeId
  label: string
  desc?: string
  nonaktif?: boolean
  aksi?: string
}

const SALDO_SERBABISA = 0
const SALDO_GOPAY = 5823

const grupMetode = computed<{ judul: string; daftar: Metode[] }[]>(() => [
  {
    judul: 'Pilihan Pembayaran',
    daftar: [
      {
        id: 'balance',
        label: LABEL_METODE.balance,
        desc:
          SALDO_SERBABISA >= totalBayar.value
            ? `Saldo Rp${SALDO_SERBABISA.toLocaleString('id-ID')}`
            : `Saldo tidak cukup (tersisa Rp${SALDO_SERBABISA.toLocaleString('id-ID')})`,
        nonaktif: SALDO_SERBABISA < totalBayar.value,
        aksi: SALDO_SERBABISA < totalBayar.value ? 'Top Up' : undefined,
      },
      {
        id: 'gopay',
        label: LABEL_METODE.gopay,
        desc:
          SALDO_GOPAY >= totalBayar.value
            ? `Saldo Rp${SALDO_GOPAY.toLocaleString('id-ID')}`
            : `Saldo tidak cukup (tersisa Rp${SALDO_GOPAY.toLocaleString('id-ID')})`,
        nonaktif: SALDO_GOPAY < totalBayar.value,
      },
      { id: 'qris', label: LABEL_METODE.qris, desc: 'Scan pakai aplikasi bank atau e-wallet apa pun' },
    ],
  },
  {
    judul: 'E-Wallet',
    daftar: [
      { id: 'ovo', label: LABEL_METODE.ovo, aksi: 'Aktivasi' },
      { id: 'shopeepay', label: LABEL_METODE.shopeepay, aksi: 'Aktivasi' },
      { id: 'dana', label: LABEL_METODE.dana },
      { id: 'linkaja', label: LABEL_METODE.linkaja },
    ],
  },
  {
    judul: 'Virtual Account',
    daftar: [
      { id: 'bca', label: LABEL_METODE.bca, desc: 'Transfer 24 jam' },
      { id: 'bni', label: LABEL_METODE.bni, desc: 'Transfer 24 jam' },
      { id: 'bri', label: LABEL_METODE.bri, desc: 'Transfer 24 jam' },
      { id: 'mandiri', label: LABEL_METODE.mandiri, desc: 'Transfer 24 jam' },
    ],
  },
  {
    judul: 'Bayar di Tempat',
    daftar: [{ id: 'tunai', label: LABEL_METODE.tunai, desc: 'Siapkan uang pas ya, biar gak repot kembalian' }],
  },
])

const semuaMetode = computed(() => grupMetode.value.flatMap((g) => g.daftar))
const metodeDipilih = computed<MetodeId>(() => (jemputStore.metode as MetodeId) || 'tunai')
const metodeAktif = computed(() => semuaMetode.value.find((m) => m.id === metodeDipilih.value))

function pilihMetode(m: Metode) {
  if (m.nonaktif || m.aksi === 'Aktivasi') return
  jemputStore.setMetode(m.id)
  lembarMetode.value = false
}

const promoDipakai = computed(() => jemputStore.promo)
const adaPromo = computed(() => (dipilih.value?.promo ?? []).some((p) => p.bisa_dipakai))

/**
 * Total yang akan ditagih.
 *
 * Dihitung dari tarif pilihan dikurangi promo yang BENAR-BENAR dipilih, bukan
 * dari `tarif_setelah_promo` yang sudah memotong promo terbaik secara otomatis
 * — angka di tombol harus sama dengan yang dikirim ke server, dan server hanya
 * memotong promo yang kodenya dikirim.
 */
const totalBayar = computed(() =>
  Math.max(0, (dipilih.value?.tarif ?? 0) - (promoDipakai.value?.potongan ?? 0)),
)

const penumpang = ref(1)
const untukOrangLain = ref(false)
const namaPenumpang = ref('')
const teleponPenumpang = ref('')
const dijadwalkan = ref(false)
const jadwalPada = ref('')
const catatan = ref('')

const minimalJadwal = computed(() => {
  const t = new Date(Date.now() + 60 * 60 * 1000)
  const p = (n: number) => String(n).padStart(2, '0')
  return `${t.getFullYear()}-${p(t.getMonth() + 1)}-${p(t.getDate())}T${p(t.getHours())}:${p(t.getMinutes())}`
})

// Kendaraan yang lebih kecil tidak boleh meninggalkan jumlah penumpang yang
// sudah tidak muat.
watch(dipilih, (p) => {
  if (p && penumpang.value > p.kapasitas) penumpang.value = p.kapasitas
})

/* ────────── Pesan langsung dari sini ────────── */
const memproses = ref(false)
const galatPesan = ref<string | null>(null)

async function pesan() {
  const p = dipilih.value
  if (!p || !jemput.value || !tujuan.value || memproses.value) return

  if (untukOrangLain.value && (!namaPenumpang.value.trim() || !teleponPenumpang.value.trim())) {
    galatPesan.value = 'Nama dan nomor penumpang belum diisi.'
    lembarOpsi.value = true
    return
  }
  if (dijadwalkan.value && !jadwalPada.value) {
    galatPesan.value = 'Jadwal penjemputan belum dipilih.'
    lembarOpsi.value = true
    return
  }

  memproses.value = true
  galatPesan.value = null

  try {
    const hasilPesan = await pesanJemput({
      tipe: p.tipe,
      varian: p.varian,
      titik_jemput_dikonfirmasi: true,
      jemput_alamat: jemput.value.alamat,
      jemput_lat: jemput.value.lat,
      jemput_lng: jemput.value.lng,
      jemput_catatan: jemput.value.catatan ?? undefined,
      tujuan_alamat: tujuan.value.alamat,
      tujuan_lat: tujuan.value.lat,
      tujuan_lng: tujuan.value.lng,
      penumpang: penumpang.value,
      metode: jemputStore.metode,
      kode_promo: promoDipakai.value?.kode,
      untuk_orang_lain: untukOrangLain.value,
      nama_penumpang: untukOrangLain.value ? namaPenumpang.value.trim() : undefined,
      telepon_penumpang: untukOrangLain.value ? teleponPenumpang.value.trim() : undefined,
      dijadwalkan: dijadwalkan.value,
      jadwal_pada: dijadwalkan.value ? jadwalPada.value : undefined,
      catatan: catatan.value || undefined,
    })

    jemputStore.hapus()
    router.replace({ name: 'task-jemput-perjalanan', params: { nomor: hasilPesan.nomor_invoice } })
  } catch (e) {
    galatPesan.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}

function sama(a: PilihanJemput | null, b: PilihanJemput) {
  return !!a && a.tipe === b.tipe && a.varian === b.varian
}
</script>

<template>
  <div class="relative min-h-dvh w-full bg-(--color-surface-container) isolate pb-4">
    <div ref="petaEl" class="absolute inset-x-0 top-0 h-[52vh] z-0" aria-label="Peta rute"></div>

    <!--
      Panah balik duduk di bagian bawah peta, bukan di pojok atas: di atas ia
      tepat berada di belakang kartu alamat dan praktis tidak bisa ditekan.
    -->
    <button
      type="button"
      aria-label="Kembali"
      class="absolute top-[41vh] left-4 z-30 w-11 h-11 rounded-full bg-(--color-surface-0) shadow-lg flex items-center justify-center active:scale-95 transition-transform"
      @click="kembali"
    >
      <Icon name="arrow-left" class="w-5 h-5" />
    </button>

    <!-- Titik jemput & tujuan, seperti kartu alamat di aplikasi transportasi -->
    <div class="relative z-20 max-w-[430px] mx-auto px-4 pt-4">
      <div class="rounded-2xl bg-(--color-surface-0) shadow-lg p-3.5 flex items-center gap-3">
        <div class="flex-1 min-w-0">
          <button
            type="button"
            class="w-full flex items-center gap-2.5 text-left"
            @click="router.push({ name: 'task-jemput-titik' })"
          >
            <Icon name="pin" class="w-4.5 h-4.5 text-(--color-azure) shrink-0" />
            <span class="flex-1 truncate text-[13px] font-semibold">
              {{ jemput?.alamat ?? 'Titik jemput' }}
            </span>
          </button>

          <div class="my-2 border-t border-(--color-outline)/15"></div>

          <button
            type="button"
            class="w-full flex items-center gap-2.5 text-left"
            @click="bukaTujuan"
          >
            <Icon name="pin" class="w-4.5 h-4.5 text-orange-500 shrink-0" />
            <span
              class="flex-1 truncate text-[13px]"
              :class="tujuan ? 'font-semibold' : 'text-(--color-on-surface-variant)'"
            >
              {{ tujuan?.alamat ?? 'Mau pergi ke mana?' }}
            </span>
          </button>
        </div>
      </div>
    </div>

    <!-- Lembar pilihan kendaraan -->
    <section
      class="relative z-20 mt-[34vh] bg-(--color-surface-0) rounded-t-3xl shadow-[0_-10px_40px_rgba(0,0,0,0.16)] min-h-[46vh]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-4 pb-40">
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>

        <!-- Tab kelas -->
        <div class="flex items-center gap-6 border-b border-(--color-outline)/15 mb-4">
          <button
            v-for="k in (['motor', 'mobil'] as const)"
            :key="k"
            type="button"
            class="pb-2.5 text-[14px] font-extrabold border-b-2 transition-colors"
            :class="
              kelas === k
                ? 'border-(--color-azure) text-(--color-azure)'
                : 'border-transparent text-(--color-on-surface-variant)'
            "
            :aria-pressed="kelas === k"
            @click="kelas = k"
          >
            {{ k === 'motor' ? 'Motor' : 'Mobil' }}
          </button>
        </div>

        <!-- Belum ada tujuan -->
        <div v-if="!tujuan" class="py-10 text-center">
          <Icon name="pin" class="w-9 h-9 mx-auto mb-3 text-(--color-on-surface-variant)" />
          <p class="text-[13.5px] font-bold mb-1">Tentukan tujuanmu dulu</p>
          <p class="text-[12px] leading-snug text-(--color-on-surface-variant) px-6">
            Tarifnya ikut jarak dan lama perjalanan, jadi harganya baru bisa dihitung setelah
            tujuannya jelas.
          </p>
          <button
            type="button"
            class="mt-4 px-5 h-11 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold active:scale-95 transition-transform"
            @click="bukaTujuan"
          >
            Pilih tujuan
          </button>
        </div>

        <div v-else-if="memuat" class="py-12 flex justify-center">
          <PemuatBerputar />
        </div>

        <p v-else-if="galat" role="alert" class="py-8 text-[13px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>

        <template v-else-if="hasil">
          <!--
            Banner ini HANYA muncul kalau tarifnya benar-benar sedang naik —
            yaitu saat banyak orang memesan di sekitar titik jemput yang sama.
            Di hari biasa ia tidak ada sama sekali, dan itu memang gunanya:
            peringatan yang muncul sepanjang hari berhenti dibaca, lalu
            kenaikan yang sungguhan ikut tidak terlihat.

            Tapi saat ia muncul, ia muncul SEBELUM daftar harga, bukan
            disembunyikan di rincian. Tarif yang naik diam-diam terbaca sebagai
            tagihan yang salah.
          -->
          <div
            v-if="hasil.sibuk"
            class="mb-3 flex gap-2 rounded-xl bg-(--color-tertiary-container)/50 p-3.5"
          >
            <Icon name="alert" class="w-4 h-4 shrink-0 mt-0.5 text-(--color-on-tertiary-container)" />
            <p class="text-[11.5px] leading-snug">
              Tarif sedang naik ×{{ hasil.sibuk_pengali.toFixed(2).replace('.', ',') }}.
              {{ hasil.sibuk_alasan }}
            </p>
          </div>

          <p class="mb-3 text-[11.5px] text-(--color-on-surface-variant)">
            {{ hasil.lewat_jalan ? 'Jarak rute' : 'Perkiraan jarak' }}
            {{ hasil.km.toFixed(1).replace('.', ',') }} km
            <span v-if="!hasil.lewat_jalan">· rute jalan sedang tidak bisa dihitung</span>
          </p>

          <div class="flex flex-col gap-2.5">
            <div
              v-for="k in kartu"
              :key="k.tipe"
              class="rounded-2xl border-2 overflow-hidden transition-colors"
              :class="
                k.varian.some((v) => sama(dipilih, v))
                  ? 'border-(--color-azure) bg-(--color-azure)/6'
                  : 'border-(--color-outline)/20'
              "
            >
              <div class="px-4 pt-3.5 pb-2 flex items-start gap-3">
                <div class="shrink-0 flex items-center justify-center">
                  <img
                    v-if="k.varian[0].kelas === 'motor'"
                    :src="bisaJemputMotorImg"
                    alt="BisaJemput Motor"
                    class="w-13 h-13 object-contain"
                  />
                  <span
                    v-else
                    class="w-10 h-10 rounded-xl bg-(--color-primary-container) flex items-center justify-center shrink-0"
                  >
                    <Icon
                      name="car"
                      class="w-5 h-5 text-(--color-on-primary-container)"
                    />
                  </span>
                </div>
                <div class="flex-1 min-w-0">
                  <p class="text-[14px] font-display font-extrabold leading-tight">
                    {{ k.varian[0].label }}
                  </p>
                  <p class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
                    {{ k.varian[0].keterangan }} · {{ k.varian[0].menit }} menit perjalanan
                  </p>
                </div>
              </div>

              <button
                v-for="v in k.varian"
                :key="v.varian"
                type="button"
                class="w-full px-4 py-3 flex items-center gap-3 text-left border-t border-(--color-outline)/12 transition-colors"
                :class="sama(dipilih, v) ? 'bg-(--color-azure)/15' : ''"
                :aria-pressed="sama(dipilih, v)"
                @click="pilih(v)"
              >
                <div class="flex-1 min-w-0">
                  <div class="flex items-center gap-2 flex-wrap">
                    <svg
                      v-if="v.label_varian === 'CEPAT'"
                      xmlns="http://www.w3.org/2000/svg"
                      width="74"
                      height="22"
                      viewBox="0 0 74 22"
                      role="img"
                      aria-label="CEPAT"
                      class="shrink-0"
                    >
                      <defs>
                        <!-- Background Yellow Gradient -->
                        <linearGradient :id="'bg-cepat-' + v.varian" x1="0" y1="0" x2="1" y2="1">
                          <stop offset="0%" stop-color="#FFD43B"/>
                          <stop offset="100%" stop-color="#F5B301"/>
                        </linearGradient>

                        <!-- Glow -->
                        <filter
                          :id="'glow-cepat-' + v.varian"
                          x="-100%"
                          y="-100%"
                          width="300%"
                          height="300%"
                        >
                          <feGaussianBlur
                            stdDeviation="0.8"
                            result="blur"
                          />
                          <feMerge>
                            <feMergeNode in="blur"/>
                            <feMergeNode in="SourceGraphic"/>
                          </feMerge>
                        </filter>

                        <!-- Kilatan -->
                        <linearGradient :id="'shine-cepat-' + v.varian">
                          <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0"/>
                          <stop offset="45%" stop-color="#FFFFFF" stop-opacity="0"/>
                          <stop offset="50%" stop-color="#FFFFFF" stop-opacity="1"/>
                          <stop offset="55%" stop-color="#FFFFFF" stop-opacity="0"/>
                          <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>
                        </linearGradient>

                        <clipPath :id="'rounded-cepat-' + v.varian">
                          <rect
                            width="74"
                            height="22"
                            rx="6"
                          />
                        </clipPath>
                      </defs>

                      <!-- Badge Background -->
                      <rect
                        width="74"
                        height="22"
                        rx="6"
                        :fill="'url(#bg-cepat-' + v.varian + ')'"
                      />

                      <g :clip-path="'url(#rounded-cepat-' + v.varian + ')'">
                        <!-- Lightning -->
                        <g :filter="'url(#glow-cepat-' + v.varian + ')'">
                          <path
                            d="
                              M12 3
                              L7.5 10
                              H11
                              L9 19
                              L16.5 9
                              H13
                              Z
                            "
                            fill="#B7F34A"
                          >
                            <!-- Kilatan petir -->
                            <animate
                              attributeName="opacity"
                              values="
                                1;
                                .35;
                                1;
                                .7;
                                1
                              "
                              dur="0.7s"
                              repeatCount="indefinite"
                            />

                            <!-- Gerakan kecil -->
                            <animateTransform
                              attributeName="transform"
                              type="translate"
                              values="
                                0 0;
                                1 -0.4;
                                0 0
                              "
                              dur="0.7s"
                              repeatCount="indefinite"
                            />
                          </path>
                        </g>

                        <!-- Text -->
                        <text
                          x="24"
                          y="14.5"
                          font-family="Arial, Helvetica, sans-serif"
                          font-size="10px"
                          font-weight="900"
                          letter-spacing="0.5px"
                          fill="#FFFFFF"
                        >
                          CEPAT
                        </text>

                        <!-- Kilatan cahaya menyapu badge -->
                        <rect
                          x="-25"
                          y="0"
                          width="8"
                          height="22"
                          :fill="'url(#shine-cepat-' + v.varian + ')'"
                          transform="skewX(-18)"
                        >
                          <animate
                            attributeName="x"
                            from="-25"
                            to="90"
                            dur="1.4s"
                            repeatCount="indefinite"
                          />
                        </rect>
                      </g>
                    </svg>

                    <svg
                      v-else-if="v.label_varian === 'HEMAT'"
                      xmlns="http://www.w3.org/2000/svg"
                      width="74"
                      height="22"
                      viewBox="0 0 74 22"
                      role="img"
                      aria-label="HEMAT"
                      class="shrink-0"
                    >
                      <defs>
                        <!-- Background -->
                        <linearGradient :id="'hematBg-' + v.varian" x1="0" y1="0" x2="1" y2="1">
                          <stop offset="0%" stop-color="#1593ED"/>
                          <stop offset="55%" stop-color="#087EDC"/>
                          <stop offset="100%" stop-color="#066CC5"/>
                        </linearGradient>

                        <!-- Lime gradient -->
                        <linearGradient :id="'lime-' + v.varian" x1="0" y1="0" x2="1" y2="1">
                          <stop offset="0%" stop-color="#D2FF62"/>
                          <stop offset="100%" stop-color="#91E52F"/>
                        </linearGradient>

                        <!-- Soft glow -->
                        <filter
                          :id="'hematGlow-' + v.varian"
                          x="-100%"
                          y="-100%"
                          width="300%"
                          height="300%"
                        >
                          <feGaussianBlur
                            stdDeviation="0.7"
                            result="blur"
                          />
                          <feMerge>
                            <feMergeNode in="blur"/>
                            <feMergeNode in="SourceGraphic"/>
                          </feMerge>
                        </filter>

                        <!-- Shine -->
                        <linearGradient :id="'shine-hemat-' + v.varian">
                          <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0"/>
                          <stop offset="42%" stop-color="#FFFFFF" stop-opacity="0"/>
                          <stop offset="50%" stop-color="#FFFFFF" stop-opacity=".95"/>
                          <stop offset="58%" stop-color="#FFFFFF" stop-opacity="0"/>
                          <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>
                        </linearGradient>

                        <clipPath :id="'hematClip-' + v.varian">
                          <rect
                            x="0"
                            y="0"
                            width="74"
                            height="22"
                            rx="6"
                          />
                        </clipPath>
                      </defs>

                      <!-- BACKGROUND -->
                      <rect
                        width="74"
                        height="22"
                        rx="6"
                        :fill="'url(#hematBg-' + v.varian + ')'"
                      />

                      <g :clip-path="'url(#hematClip-' + v.varian + ')'">
                        <!-- WALLET + COIN ICON -->
                        <g
                          transform="translate(4 4)"
                          :filter="'url(#hematGlow-' + v.varian + ')'"
                        >
                          <!-- Wallet body -->
                          <path
                            d="
                              M2.2 5
                              V11.2
                              Q2.2 13.5 4.5 13.5
                              H10.8
                              Q13 13.5 13 11.2
                              V6.8
                              Q13 5 11 5
                              Z
                            "
                            :fill="'url(#lime-' + v.varian + ')'"
                          />

                          <!-- Wallet flap -->
                          <path
                            d="
                              M2.2 6.2
                              V4.5
                              Q2.2 2.8 4 2.8
                              H9.6
                              Q11 2.8 11.8 4.2
                              L13 6.2
                              Z
                            "
                            fill="#B8F34A"
                          />

                          <!-- Wallet pocket -->
                          <path
                            d="
                              M9 7
                              H13
                              V10.8
                              H9.5
                              Q8 10.8 8 8.9
                              Q8 7 9.5 7
                              Z
                            "
                            fill="#78D51F"
                          />

                          <!-- Coin -->
                          <circle
                            cx="5.7"
                            cy="5.2"
                            r="2.2"
                            fill="#FFFFFF"
                          />

                          <path
                            d="
                              M5.7 3.9
                              V6.5
                              M4.8 4.7
                              Q5.7 4 6.5 4.7
                              Q5.5 5.3 4.9 5.7
                              Q5.7 6.4 6.6 5.6
                            "
                            fill="none"
                            stroke="#1593ED"
                            stroke-width=".55"
                            stroke-linecap="round"
                            stroke-linejoin="round"
                          />

                          <!-- Wallet button -->
                          <circle
                            cx="10.2"
                            cy="8.9"
                            r=".75"
                            fill="#FFFFFF"
                          />

                          <!-- Icon pulse -->
                          <animate
                            attributeName="opacity"
                            values="1;.7;1"
                            dur=".9s"
                            repeatCount="indefinite"
                          />
                        </g>

                        <!-- TEXT -->
                        <text
                          x="23"
                          y="14.5"
                          font-family="Inter, Arial, Helvetica, sans-serif"
                          font-size="10px"
                          font-weight="900"
                          letter-spacing="0.3px"
                          fill="#FFFFFF"
                        >
                          HEMAT
                        </text>

                        <!-- SPARK -->
                        <g
                          transform="translate(64 5)"
                          fill="#D2FF62"
                        >
                          <path
                            d="
                              M2 0
                              L2.5 1.5
                              L4 2
                              L2.5 2.5
                              L2 4
                              L1.5 2.5
                              L0 2
                              L1.5 1.5
                              Z
                            "
                          >
                            <animate
                              attributeName="opacity"
                              values="1;.2;1"
                              dur=".65s"
                              repeatCount="indefinite"
                            />
                            <animateTransform
                              attributeName="transform"
                              type="scale"
                              values="1;1.35;1"
                              dur=".65s"
                              repeatCount="indefinite"
                              additive="sum"
                            />
                          </path>
                        </g>

                        <!-- LIGHT SWEEP -->
                        <rect
                          x="-25"
                          y="-5"
                          width="8"
                          height="30"
                          :fill="'url(#shine-hemat-' + v.varian + ')'"
                          transform="skewX(-18)"
                        >
                          <animate
                            attributeName="x"
                            from="-25"
                            to="90"
                            dur="1.45s"
                            repeatCount="indefinite"
                          />
                        </rect>
                      </g>
                    </svg>

                    <svg
                      v-else-if="v.label_varian === 'COMFORT'"
                      xmlns="http://www.w3.org/2000/svg"
                      width="88"
                      height="24"
                      viewBox="0 0 88 24"
                      role="img"
                      aria-label="COMFORT"
                      class="shrink-0"
                    >
                      <defs>
                        <!-- Background Maroon Gradient -->
                        <linearGradient :id="'comfortBg-' + v.varian" x1="0" y1="0" x2="1" y2="1">
                          <stop offset="0%" stop-color="#9B1B30"/>
                          <stop offset="100%" stop-color="#650A19"/>
                        </linearGradient>

                        <!-- Shine -->
                        <linearGradient :id="'shine-comfort-' + v.varian" x1="0" y1="0" x2="1" y2="0">
                          <stop offset="0%" stop-color="#fff" stop-opacity="0"/>
                          <stop offset="50%" stop-color="#fff" stop-opacity=".95"/>
                          <stop offset="100%" stop-color="#fff" stop-opacity="0"/>
                        </linearGradient>

                        <clipPath :id="'clip-comfort-' + v.varian">
                          <rect width="88" height="24" rx="6"/>
                        </clipPath>
                      </defs>

                      <!-- BADGE BACKGROUND -->
                      <rect
                        width="88"
                        height="24"
                        rx="6"
                        :fill="'url(#comfortBg-' + v.varian + ')'"
                      />

                      <g :clip-path="'url(#clip-comfort-' + v.varian + ')'">
                        <!-- COMFORT ICON -->
                        <!-- Sofa -->
                        <path
                          d="M5 14.5
                             Q5 12 7.5 12
                             H14.5
                             Q17 12 17 14.5
                             V18
                             H5Z"
                          fill="#7ED321"
                        />

                        <!-- Sofa back -->
                        <path
                          d="M7 12
                             V9.5
                             Q7 8 8.8 8
                             H13.2
                             Q15 8 15 9.5
                             V12"
                          fill="#B7F34A"
                        />

                        <!-- Person head -->
                        <circle
                          cx="11"
                          cy="6"
                          r="2.2"
                          fill="#FFD0A6"
                        />

                        <!-- Hair -->
                        <path
                          d="M8.8 5.8
                             Q8.8 3.5 11 3.5
                             Q13.2 3.5 13.2 5.4
                             Q12.2 4.7 11 4.8
                             Q9.7 4.9 8.8 5.8Z"
                          fill="#650A19"
                        />

                        <!-- Body relaxed -->
                        <path
                          d="M9 8
                             Q11 7 13 8
                             L14.5 12
                             H11.5
                             L10.5 10.5
                             L8.5 12
                             H6.8
                             L8.2 9Z"
                          fill="#FFFFFF"
                        />

                        <!-- Relaxed arm -->
                        <path
                          d="M12.5 8.5
                             Q14.5 9.5 15 11"
                          stroke="#FFD0A6"
                          stroke-width="1.3"
                          stroke-linecap="round"
                        />

                        <!-- COMFORT TEXT -->
                        <text
                          x="21"
                          y="16"
                          fill="#FFFFFF"
                          font-family="Arial, sans-serif"
                          font-size="9.5px"
                          font-weight="900"
                          letter-spacing=".2px">
                          COMFORT
                        </text>

                        <!-- SHINE ANIMATION -->
                        <rect
                          x="-25"
                          y="0"
                          width="9"
                          height="24"
                          :fill="'url(#shine-comfort-' + v.varian + ')'"
                          transform="skewX(-18)">
                          <animate
                            attributeName="x"
                            from="-25"
                            to="105"
                            dur="1.6s"
                            repeatCount="indefinite"
                          />
                        </rect>
                      </g>
                    </svg>

                    <svg
                      v-else-if="v.label_varian === 'PREMIUM'"
                      xmlns="http://www.w3.org/2000/svg"
                      width="82"
                      height="24"
                      viewBox="0 0 82 24"
                      role="img"
                      aria-label="PREMIUM"
                      class="shrink-0"
                    >
                      <defs>
                        <!-- Premium Black Background Gradient -->
                        <linearGradient :id="'premiumBg-' + v.varian" x1="0" y1="0" x2="1" y2="1">
                          <stop offset="0%" stop-color="#2E2E2E"/>
                          <stop offset="100%" stop-color="#0F0F0F"/>
                        </linearGradient>

                        <!-- Gold Crown Gradient -->
                        <linearGradient :id="'crown-' + v.varian" x1="0" y1="0" x2="1" y2="1">
                          <stop offset="0%" stop-color="#FFE066"/>
                          <stop offset="100%" stop-color="#F5B301"/>
                        </linearGradient>

                        <!-- Shine -->
                        <linearGradient :id="'shine-premium-' + v.varian" x1="0" y1="0" x2="1" y2="0">
                          <stop offset="0%" stop-color="#FFFFFF" stop-opacity="0"/>
                          <stop offset="50%" stop-color="#FFFFFF" stop-opacity=".95"/>
                          <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0"/>
                        </linearGradient>

                        <clipPath :id="'premiumClip-' + v.varian">
                          <rect width="82" height="24" rx="6"/>
                        </clipPath>
                      </defs>

                      <!-- BADGE BACKGROUND -->
                      <rect
                        width="82"
                        height="24"
                        rx="6"
                        :fill="'url(#premiumBg-' + v.varian + ')'"
                      />

                      <g :clip-path="'url(#premiumClip-' + v.varian + ')'">
                        <!-- PREMIUM CROWN ICON -->
                        <!-- Crown -->
                        <path
                          d="
                            M5 7
                            L8 10
                            L11 5
                            L14 10
                            L17 7
                            L15.5 14
                            H6.5
                            Z"
                          :fill="'url(#crown-' + v.varian + ')'"
                          stroke="#FFE885"
                          stroke-width=".7"
                          stroke-linejoin="round"
                        />

                        <!-- Crown base -->
                        <rect
                          x="6.5"
                          y="14"
                          width="9"
                          height="2"
                          rx="1"
                          fill="#FFD700"
                        />

                        <!-- Crown jewel -->
                        <circle
                          cx="11"
                          cy="10.5"
                          r="1"
                          fill="#FFFFFF"
                        >
                          <animate
                            attributeName="opacity"
                            values="1;.35;1"
                            dur="1.2s"
                            repeatCount="indefinite"
                          />
                        </circle>

                        <!-- PREMIUM TEXT -->
                        <text
                          x="21"
                          y="16"
                          fill="#FFFFFF"
                          font-family="Arial, sans-serif"
                          font-size="9.5px"
                          font-weight="900"
                          letter-spacing=".15px"
                        >
                          PREMIUM
                        </text>

                        <!-- SHIMMER -->
                        <rect
                          x="-25"
                          y="0"
                          width="9"
                          height="24"
                          :fill="'url(#shine-premium-' + v.varian + ')'"
                          transform="skewX(-18)"
                        >
                          <animate
                            attributeName="x"
                            from="-25"
                            to="100"
                            dur="1.6s"
                            repeatCount="indefinite"
                          />
                        </rect>
                      </g>
                    </svg>

                    <span
                      v-else
                      class="px-2 py-0.5 rounded-md bg-(--color-azure) text-white text-[9.5px] font-extrabold tracking-wide"
                    >
                      {{ v.label_varian }}
                    </span>
                    <span class="text-[11.5px] font-extrabold text-(--color-on-surface)">
                      {{ rentangMenit(v.jemput_menit) }}
                    </span>
                  </div>
                  <p class="mt-1 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
                    {{ v.catatan }}
                  </p>
                </div>

                <div class="text-right shrink-0">
                  <p class="text-[15px] font-extrabold">{{ rupiah(v.tarif_setelah_promo) }}</p>
                  <p
                    v-if="v.tarif_setelah_promo < v.tarif"
                    class="text-[11.5px] line-through text-(--color-on-surface-variant)"
                  >
                    {{ rupiah(v.tarif) }}
                  </p>
                </div>

                <span
                  class="w-5 h-5 rounded-full border-2 shrink-0 flex items-center justify-center"
                  :class="
                    sama(dipilih, v) ? 'border-(--color-azure)' : 'border-(--color-outline)/50'
                  "
                >
                  <span
                    v-if="sama(dipilih, v)"
                    class="w-2.5 h-2.5 rounded-full bg-(--color-azure)"
                  ></span>
                </span>
              </button>
            </div>
          </div>


        </template>
      </div>
    </section>

    <footer
      v-if="dipilih"
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.10)]"
    >
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <!-- Metode bayar · promo · opsi lain -->
        <div class="flex items-center gap-2 mb-3">
          <button
            type="button"
            class="flex items-center gap-2 shrink-0 active:scale-95 transition-transform"
            @click="lembarMetode = true"
          >
            <span class="w-6 h-6 flex items-center justify-center shrink-0">
              <svg v-if="metodeDipilih === 'balance'" class="w-5.5 h-5.5" viewBox="0 0 32 32" fill="none">
                <rect width="32" height="32" rx="8" fill="#1E9BF0" />
                <rect x="6" y="10" width="20" height="13" rx="3" fill="#fff" />
                <rect x="6" y="10" width="20" height="4" rx="2" fill="#0B67B0" />
                <circle cx="21.5" cy="18.5" r="2.5" fill="#8BC53F" />
              </svg>
              <svg v-else-if="metodeDipilih === 'gopay'" class="w-5.5 h-5.5" viewBox="0 0 24 24" fill="none">
                <rect width="24" height="24" rx="12" fill="#00AED6" />
                <path fill-rule="evenodd" clip-rule="evenodd" d="M7 8C5.89543 8 5 8.89543 5 10V15C5 16.6569 6.34315 18 8 18H16C17.6569 18 19 16.6569 19 15V11C19 9.89543 18.1046 9 17 9H9.5C9.22386 9 9 8.77614 9 8.5C9 8.22386 9.22386 8 9.5 8H17C17.5523 8 18 7.55228 18 7C18 6.44772 17.5523 6 17 6H9C7.89543 6 7 6.89543 7 8ZM16 13C16.5523 13 17 12.5523 17 12C17 11.4477 16.5523 11 16 11C15.4477 11 15 11.4477 15 12C15 12.5523 15.4477 13 16 13Z" fill="#fff" />
              </svg>
              <img v-else-if="metodeDipilih === 'qris'" :src="logoQris" alt="QRIS" class="h-4 w-auto object-contain" />
              <img v-else-if="metodeDipilih === 'ovo'" :src="logoOvo" alt="OVO" class="h-4.5 w-auto object-contain" />
              <img v-else-if="metodeDipilih === 'shopeepay'" :src="logoSpay" alt="ShopeePay" class="h-6 w-auto object-contain" />
              <img v-else-if="metodeDipilih === 'dana'" :src="logoDana" alt="DANA" class="h-4.5 w-auto object-contain" />
              <img v-else-if="metodeDipilih === 'linkaja'" :src="logoLinkaja" alt="LinkAja" class="h-4.5 w-auto object-contain" />
              <img v-else-if="metodeDipilih === 'bca'" :src="logoBca" alt="BCA" class="h-4.5 w-auto object-contain" />
              <img v-else-if="metodeDipilih === 'bni'" :src="logoBni" alt="BNI" class="h-4 w-auto object-contain" />
              <img v-else-if="metodeDipilih === 'bri'" :src="logoBri" alt="BRI" class="h-4.5 w-auto object-contain" />
              <img v-else-if="metodeDipilih === 'mandiri'" :src="logoMandiri" alt="Mandiri" class="h-4.5 w-auto object-contain" />
              <svg v-else class="w-5 h-5 text-[#65b318]" viewBox="0 0 24 24" fill="none">
                <rect x="2" y="6" width="20" height="12" rx="2.5" stroke="currentColor" stroke-width="2" />
                <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                <path d="M6 10v4M18 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
              </svg>
            </span>
            <span class="text-[13.5px] font-extrabold">{{ metodeAktif?.label ?? 'Pilih Pembayaran' }}</span>
            <Icon name="chevron-right" class="w-3.5 h-3.5 text-(--color-on-surface-variant)" />
          </button>

          <!--
            Chip promo hanya muncul kalau ada yang benar-benar bisa dipakai
            untuk pilihan ini. Chip "lihat promo" yang selalu ada lalu berujung
            daftar kosong lebih mengecewakan daripada tidak ada chip sama
            sekali.
          -->
          <button
            v-if="promoDipakai || adaPromo"
            type="button"
            class="ml-auto flex items-center gap-1.5 rounded-full border px-3 h-9 shrink-0 active:scale-95 transition-transform"
            :class="
              promoDipakai
                ? 'border-(--color-on-secondary-container)/40 bg-(--color-secondary-container)/40'
                : 'border-(--color-outline)/40'
            "
            @click="router.push({ name: 'task-jemput-voucher' })"
          >
            <Icon
              :name="promoDipakai ? 'check-circle' : 'sparkle'"
              class="w-4 h-4"
              :class="promoDipakai ? 'text-(--color-on-secondary-container)' : 'text-(--color-azure)'"
            />
            <span class="text-[12px] font-bold">
              {{ promoDipakai ? `Hemat ${rupiah(promoDipakai.potongan)}` : 'Pakai promo' }}
            </span>
          </button>

          <button
            type="button"
            aria-label="Opsi perjalanan"
            class="w-9 h-9 rounded-full border border-(--color-outline)/40 flex items-center justify-center shrink-0 active:scale-90 transition-transform"
            :class="{ 'ml-auto': !promoDipakai && !adaPromo }"
            @click="lembarOpsi = true"
          >
            <Icon name="plus-square" class="w-4 h-4 text-(--color-azure)" />
          </button>
        </div>

        <button
          type="button"
          class="w-full h-13 py-3.5 rounded-full bg-(--color-azure) text-white flex items-center justify-between px-5 gap-3 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="memproses"
          @click="pesan"
        >
          <span class="text-[15px] font-extrabold">
            {{ memproses ? 'Memproses…' : 'Pesan' }}
          </span>
          <span class="flex items-center gap-2.5">
            <span class="text-[15px] font-extrabold">{{ rupiah(totalBayar) }}</span>
            <span
              class="w-7 h-7 rounded-full bg-white/25 flex items-center justify-center shrink-0"
            >
              <Icon name="arrow-right" class="w-4 h-4" />
            </span>
          </span>
        </button>

        <p v-if="galatPesan" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galatPesan }}
        </p>
      </div>
    </footer>

    <!-- Sheet metode pembayaran, sama persis seperti BelanjaCheckoutView -->
    <Teleport to="body">
      <div v-if="lembarMetode" class="fixed inset-0 z-[60] flex items-end md:items-center md:justify-center">
        <Transition
          appear
          enter-active-class="transition-opacity duration-300"
          enter-from-class="opacity-0"
          leave-active-class="transition-opacity duration-200"
          leave-to-class="opacity-0"
        >
          <div class="absolute inset-0 bg-black/45" @click="lembarMetode = false"></div>
        </Transition>

        <Transition
          appear
          enter-active-class="transition-transform duration-300 ease-out"
          enter-from-class="translate-y-full"
          leave-active-class="transition-transform duration-200 ease-in"
          leave-to-class="translate-y-full"
        >
          <div class="relative w-full md:w-96 max-h-[85dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)">
            <div class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mt-3 mb-1 shrink-0 md:hidden"></div>

            <div class="flex items-center justify-between px-5 py-3.5 shrink-0 border-b border-(--color-outline)/10">
              <h3 class="font-extrabold text-[17px]">Mau bayar pakai apa?</h3>
              <button
                type="button"
                aria-label="Tutup"
                class="w-8 h-8 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform"
                @click="lembarMetode = false"
              >
                <Icon name="x" class="w-4 h-4" />
              </button>
            </div>

            <div class="overflow-y-auto flex-1 pb-6">
              <div v-for="g in grupMetode" :key="g.judul">
                <p class="px-5 pt-4 pb-1.5 text-[13px] font-extrabold text-(--color-on-surface)">{{ g.judul }}</p>
                <button
                  v-for="m in g.daftar"
                  :key="m.id"
                  type="button"
                  class="w-full flex items-center gap-3 px-5 py-3 text-left transition-colors"
                  :class="[
                    m.nonaktif || m.aksi === 'Aktivasi' ? 'cursor-default' : 'active:bg-(--color-surface-container)',
                    metodeDipilih === m.id ? 'bg-(--color-azure)/8' : '',
                  ]"
                  @click="pilihMetode(m)"
                >
                  <span class="w-9 h-9 flex items-center justify-center shrink-0">
                    <svg v-if="m.id === 'balance'" class="w-8 h-8" viewBox="0 0 32 32" fill="none">
                      <rect width="32" height="32" rx="8" fill="#1E9BF0" />
                      <rect x="6" y="10" width="20" height="13" rx="3" fill="#fff" />
                      <rect x="6" y="10" width="20" height="4" rx="2" fill="#0B67B0" />
                      <circle cx="21.5" cy="18.5" r="2.5" fill="#8BC53F" />
                    </svg>
                    <svg v-else-if="m.id === 'gopay'" class="w-8 h-8" viewBox="0 0 24 24" fill="none">
                      <rect width="24" height="24" rx="12" fill="#00AED6" />
                      <path fill-rule="evenodd" clip-rule="evenodd" d="M7 8C5.89543 8 5 8.89543 5 10V15C5 16.6569 6.34315 18 8 18H16C17.6569 18 19 16.6569 19 15V11C19 9.89543 18.1046 9 17 9H9.5C9.22386 9 9 8.77614 9 8.5C9 8.22386 9.22386 8 9.5 8H17C17.5523 8 18 7.55228 18 7C18 6.44772 17.5523 6 17 6H9C7.89543 6 7 6.89543 7 8ZM16 13C16.5523 13 17 12.5523 17 12C17 11.4477 16.5523 11 16 11C15.4477 11 15 11.4477 15 12C15 12.5523 15.4477 13 16 13Z" fill="#fff" />
                    </svg>
                    <img v-else-if="m.id === 'qris'" :src="logoQris" alt="QRIS" class="h-5 w-auto object-contain" />
                    <img v-else-if="m.id === 'ovo'" :src="logoOvo" alt="OVO" class="h-6 w-auto object-contain" />
                    <img v-else-if="m.id === 'shopeepay'" :src="logoSpay" alt="ShopeePay" class="h-9 w-auto object-contain scale-110" />
                    <img v-else-if="m.id === 'dana'" :src="logoDana" alt="DANA" class="h-6 w-auto object-contain" />
                    <img v-else-if="m.id === 'linkaja'" :src="logoLinkaja" alt="LinkAja" class="h-6 w-auto object-contain" />
                    <img v-else-if="m.id === 'bca'" :src="logoBca" alt="BCA" class="h-6 w-auto object-contain" />
                    <img v-else-if="m.id === 'bni'" :src="logoBni" alt="BNI" class="h-5 w-auto object-contain" />
                    <img v-else-if="m.id === 'bri'" :src="logoBri" alt="BRI" class="h-6 w-auto object-contain" />
                    <img v-else-if="m.id === 'mandiri'" :src="logoMandiri" alt="Mandiri" class="h-6 w-auto object-contain" />
                    <svg v-else class="w-6 h-6 text-[#65b318]" viewBox="0 0 24 24" fill="none">
                      <rect x="2" y="6" width="20" height="12" rx="2.5" stroke="currentColor" stroke-width="2" />
                      <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="2" />
                      <path d="M6 10v4M18 10v4" stroke="currentColor" stroke-width="2" stroke-linecap="round" />
                    </svg>
                  </span>

                  <span class="flex-1 min-w-0">
                    <span
                      class="block text-[14px] font-bold truncate"
                      :class="m.nonaktif || m.aksi === 'Aktivasi' ? 'text-(--color-outline)' : 'text-(--color-on-surface)'"
                    >
                      {{ m.label }}
                    </span>
                    <span v-if="m.desc" class="block text-[11.5px] text-(--color-on-surface-variant) truncate">
                      {{ m.desc }}
                    </span>
                  </span>

                  <span v-if="m.aksi" class="text-[12.5px] font-bold text-(--color-azure) shrink-0">{{ m.aksi }}</span>
                  <span
                    v-else-if="metodeDipilih === m.id"
                    class="w-5 h-5 rounded-full bg-(--color-azure) flex items-center justify-center shrink-0"
                  >
                    <Icon name="check" class="w-3 h-3 text-white" />
                  </span>
                </button>
                <div class="h-2 bg-(--color-surface-container)"></div>
              </div>
            </div>
          </div>
        </Transition>
      </div>
    </Teleport>

    <!-- Lembar opsi perjalanan -->
    <div v-if="lembarOpsi" class="fixed inset-0 z-50 flex items-end" @click.self="lembarOpsi = false">
      <div class="absolute inset-0 bg-black/40"></div>
      <div
        class="relative w-full max-w-[430px] mx-auto bg-(--color-surface-0) rounded-t-3xl p-5 max-h-[85vh] overflow-y-auto"
      >
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>
        <h2 class="text-[16px] font-display font-extrabold mb-4">Opsi perjalanan</h2>

        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-[13.5px] font-bold">Jumlah penumpang</p>
            <p class="text-[11.5px] text-(--color-on-surface-variant)">
              Maksimal {{ dipilih?.kapasitas ?? 1 }} orang
            </p>
          </div>
          <div class="flex items-center gap-3">
            <button
              type="button"
              aria-label="Kurangi penumpang"
              class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center active:scale-90 transition-transform disabled:opacity-40"
              :disabled="penumpang <= 1"
              @click="penumpang--"
            >
              <Icon name="minus" class="w-4 h-4" />
            </button>
            <span class="w-6 text-center text-[15px] font-extrabold">{{ penumpang }}</span>
            <button
              type="button"
              aria-label="Tambah penumpang"
              class="w-9 h-9 rounded-full bg-(--color-azure) text-white flex items-center justify-center active:scale-90 transition-transform disabled:opacity-40"
              :disabled="penumpang >= (dipilih?.kapasitas ?? 1)"
              @click="penumpang++"
            >
              <Icon name="plus" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!--
          Dipesan untuk orang lain: pengemudi menjemput seseorang yang bukan
          pemilik akun, jadi nama dan nomor yang bisa dihubungi di lokasi wajib
          ada. Tanpa itu pengemudi hanya punya titik peta.
        -->
        <div class="mt-4 pt-4 border-t border-(--color-outline)/15">
          <button
            type="button"
            class="w-full flex items-center gap-3 text-left"
            :aria-pressed="untukOrangLain"
            @click="untukOrangLain = !untukOrangLain"
          >
            <div class="flex-1">
              <p class="text-[13.5px] font-bold">Pesan untuk orang lain</p>
              <p class="text-[11.5px] text-(--color-on-surface-variant)">
                Pengemudi menghubungi penumpangnya, bukan kamu
              </p>
            </div>
            <span
              class="w-11 h-6 rounded-full p-0.5 shrink-0 transition-colors"
              :class="untukOrangLain ? 'bg-(--color-azure)' : 'bg-(--color-outline)/30'"
            >
              <span
                class="block w-5 h-5 rounded-full bg-white transition-transform"
                :class="untukOrangLain ? 'translate-x-5' : ''"
              ></span>
            </span>
          </button>

          <div v-if="untukOrangLain" class="mt-3 flex flex-col gap-2.5">
            <input
              v-model="namaPenumpang"
              type="text"
              maxlength="100"
              placeholder="Nama penumpang"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
            />
            <input
              v-model="teleponPenumpang"
              type="tel"
              maxlength="30"
              placeholder="Nomor telepon penumpang"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
            />
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-(--color-outline)/15">
          <button
            type="button"
            class="w-full flex items-center gap-3 text-left"
            :aria-pressed="dijadwalkan"
            @click="dijadwalkan = !dijadwalkan"
          >
            <div class="flex-1">
              <p class="text-[13.5px] font-bold">Jadwalkan penjemputan</p>
              <p class="text-[11.5px] text-(--color-on-surface-variant)">
                {{ dijadwalkan ? 'Dijemput pada waktu yang kamu pilih' : 'Dijemput sekarang' }}
              </p>
            </div>
            <span
              class="w-11 h-6 rounded-full p-0.5 shrink-0 transition-colors"
              :class="dijadwalkan ? 'bg-(--color-azure)' : 'bg-(--color-outline)/30'"
            >
              <span
                class="block w-5 h-5 rounded-full bg-white transition-transform"
                :class="dijadwalkan ? 'translate-x-5' : ''"
              ></span>
            </span>
          </button>

          <div v-if="dijadwalkan" class="mt-3">
            <input
              v-model="jadwalPada"
              type="datetime-local"
              :min="minimalJadwal"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
            />
            <p class="mt-2 text-[11px] leading-snug text-(--color-on-surface-variant)">
              Tarif perjalanan terjadwal dihitung ulang saat penjemputan, mengikuti ramainya
              permintaan saat itu.
            </p>
          </div>
        </div>

        <div class="mt-4 pt-4 border-t border-(--color-outline)/15">
          <p class="text-[13.5px] font-bold mb-2">Catatan untuk pengemudi</p>
          <textarea
            v-model="catatan"
            rows="2"
            placeholder="Mis. bawa satu koper kabin"
            class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
          />
        </div>

        <button
          type="button"
          class="mt-5 w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-extrabold active:scale-[0.98] transition-transform"
          @click="lembarOpsi = false"
        >
          Simpan
        </button>
      </div>
    </div>
  </div>
</template>
