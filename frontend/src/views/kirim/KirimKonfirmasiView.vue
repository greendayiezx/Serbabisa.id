<script setup lang="ts">
/**
 * BisaKirim — data pengirim, penerima, dan pembayaran.
 *
 * Nomor telepon KEDUA sisi wajib, dan itu bukan formalitas: kurir menelepon
 * pengirim saat menjemput dan penerima saat mengantar. Satu nomor yang kosong
 * berarti satu perjalanan yang berhenti di depan pintu tanpa ada yang bisa
 * dihubungi.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import Spinner from '@/components/ui/Spinner.vue'
import KirimKonfirmasiSkeleton from '@/components/skeleton/KirimKonfirmasiSkeleton.vue'
import KontakPenerima from '@/components/KontakPenerima.vue'
import PemuatBerputar from '@/components/ui/PemuatBerputar.vue'
import KartuLokasiPeta from '@/components/KartuLokasiPeta.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import SheetMetodeBayar from '@/components/SheetMetodeBayar.vue'
import { useSkeleton } from '@/composables/useSkeleton'
import { useKirimStore } from '@/stores/kirim'
import { useLocationStore } from '@/stores/location'
import { useAuthStore } from '@/stores/auth'
import { estimasiKirim, pesanKirim } from '@/api/kirim'
import { pesanError } from '@/api/belanja'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import { rupiah } from '@/lib/kirim'

const router = useRouter()
const kembali = useKembali()
const kirimStore = useKirimStore()
const locationStore = useLocationStore()
const authStore = useAuthStore()

const { tampil: skelTampil, tandaiSiap } = useSkeleton()

const pilihan = computed(() => kirimStore.pilihan)
const promo = computed(() => kirimStore.promo ?? pilihan.value?.promo_terbaik ?? null)

const namaPengirim = ref('')
const teleponPengirim = ref('')
const catatanAmbil = ref('')
const namaPenerima = ref('')
const teleponPenerima = ref('')
const catatanAntar = ref('')

const lembarMetode = ref(false)
const lembarLokasi = ref(false)
const alamatTersimpan = ref(false)
const menghitungUlang = ref(false)
let penandaSimpan: ReturnType<typeof setTimeout> | null = null
const ditandai = ref(false)
const memproses = ref(false)
const galat = ref<string | null>(null)

const namaMetode = computed(() => LABEL_METODE[kirimStore.metode as MetodeId] ?? kirimStore.metode)

const namaPengirimTampil = computed(() =>
  namaPengirim.value.trim()
    ? `${namaPengirim.value} · ${teleponPengirim.value}`
    : 'Isi nama dan nomor pengirim',
)

const total = computed(() =>
  Math.max(0, (pilihan.value?.total ?? 0) - (promo.value?.potongan ?? 0)),
)

onMounted(() => {
  if (!kirimStore.ambil || !kirimStore.antar || !kirimStore.pilihan) {
    router.replace({ name: 'task-kirim' })
    return
  }

  /*
   * Pengirim diambil dari halaman pengambilan kalau sudah diisi; kalau belum,
   * jatuh ke pemilik akun. Dialah yang memesan, jadi itu tebakan paling masuk
   * akal — dan layar menyebutkannya, bukan mengisinya diam-diam.
   */
  namaPengirim.value = kirimStore.ambil.nama ?? authStore.user?.name ?? ''
  teleponPengirim.value = kirimStore.ambil.telepon ?? authStore.user?.phone ?? ''
  catatanAmbil.value = kirimStore.ambil.catatan ?? ''

  namaPenerima.value = kirimStore.antar.nama ?? ''
  teleponPenerima.value = kirimStore.antar.telepon ?? ''
  catatanAntar.value = kirimStore.antar.catatan ?? ''

  tandaiSiap()
})

/**
 * Titik tujuan digeser dari layar ini.
 *
 * Lewat setAntar, bukan setKontak: rutenya berubah, jadi ongkir dan voucher
 * yang sudah dihitung memang harus dibuang — harga lama sudah tidak berlaku.
 * Karena itu ongkirnya langsung diminta ulang ke server di sini, bukan
 * dibiarkan kosong: tanpa itu layar ini kehilangan `pilihan` dan tinggal
 * kerangka tanpa isi, tanpa satu pun pesan yang menjelaskan kenapa.
 */
async function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  const kendaraanLama = kirimStore.pilihan?.kendaraan

  kirimStore.setAntar({
    ...(kirimStore.antar ?? {}),
    ...l,
    nama: namaPenerima.value,
    telepon: teleponPenerima.value,
    catatan: catatanAntar.value || null,
  })
  locationStore.addSearchHistory(l)
  lembarLokasi.value = false
  alamatTersimpan.value = false

  await hitungUlang(kendaraanLama)
}

/**
 * Minta ongkir baru untuk titik yang baru.
 *
 * Kendaraan yang tadi dipilih dipertahankan kalau masih sanggup. Kalau tidak,
 * yang termurah di antara yang sanggup diambil — dan itu DIKATAKAN, karena
 * kendaraan yang berganti sendiri berarti harga yang berganti sendiri.
 */
async function hitungUlang(kendaraanLama?: string) {
  if (!kirimStore.ambil || !kirimStore.antar) return

  menghitungUlang.value = true
  galat.value = null
  try {
    const h = await estimasiKirim({
      ambil_lat: kirimStore.ambil.lat,
      ambil_lng: kirimStore.ambil.lng,
      antar_lat: kirimStore.antar.lat,
      antar_lng: kirimStore.antar.lng,
      ukuran: kirimStore.ukuran,
      nilai_barang: kirimStore.nilaiBarang || undefined,
    })

    const sanggup = h.pilihan.filter((p) => p.sanggup)
    const sama = sanggup.find((p) => p.kendaraan === kendaraanLama)
    const dipakai =
      sama ?? sanggup.sort((a, b) => a.total_setelah_promo - b.total_setelah_promo)[0] ?? null

    kirimStore.setPilihan(dipakai)

    if (!dipakai) {
      galat.value = 'Belum ada kurir yang sanggup ke titik itu. Coba geser titiknya sedikit.'
    } else if (kendaraanLama && !sama) {
      galat.value = `Kendaraan sebelumnya tidak sanggup ke titik baru, jadi diganti ${dipakai.label}.`
    }
  } catch (e) {
    kirimStore.setPilihan(null)
    galat.value = pesanError(e)
  } finally {
    menghitungUlang.value = false
  }
}

/**
 * Simpan alamat tujuan ke daftar alamat.
 *
 * Masuk ke riwayat alamat, BUKAN ke slot "Rumah" seperti di BisaAngkut: yang
 * disimpan di sini alamat PENERIMA, orang lain. Menimpanya ke Rumah berarti
 * alamat rumah sendiri hilang tanpa diminta, dan baru ketahuan saat memesan
 * berikutnya.
 */
function simpanAlamat() {
  const t = kirimStore.antar
  if (!t) return

  if (alamatTersimpan.value) {
    alamatTersimpan.value = false
    if (penandaSimpan) {
      clearTimeout(penandaSimpan)
      penandaSimpan = null
    }
    return
  }

  locationStore.addSearchHistory({ alamat: t.alamat, lat: t.lat, lng: t.lng })
  alamatTersimpan.value = true
  if (penandaSimpan) clearTimeout(penandaSimpan)
  penandaSimpan = setTimeout(() => {
    alamatTersimpan.value = false
    penandaSimpan = null
  }, 2500)
}

onBeforeUnmount(() => {
  if (penandaSimpan) clearTimeout(penandaSimpan)
})

async function kirim() {
  const p = pilihan.value
  if (!p || !kirimStore.ambil || !kirimStore.antar || memproses.value) return

  if (
    !namaPengirim.value.trim() ||
    !teleponPengirim.value.trim() ||
    !namaPenerima.value.trim() ||
    !teleponPenerima.value.trim()
  ) {
    ditandai.value = true
    galat.value = 'Nama dan nomor telepon pengirim serta penerima harus diisi.'
    return
  }
  if (!kirimStore.isi.trim()) {
    galat.value = 'Isi paketnya belum ditulis.'
    return
  }

  memproses.value = true
  galat.value = null

  try {
    const hasil = await pesanKirim({
      kendaraan: p.kendaraan,
      ukuran: kirimStore.ukuran,
      isi: kirimStore.isi.trim(),
      nilai_barang: kirimStore.nilaiBarang || undefined,
      pakai_kode_terima: kirimStore.pakaiKodeTerima,

      ambil_alamat: kirimStore.ambil.alamat,
      ambil_lat: kirimStore.ambil.lat,
      ambil_lng: kirimStore.ambil.lng,
      ambil_nama: namaPengirim.value.trim(),
      ambil_telepon: teleponPengirim.value.trim(),
      ambil_catatan: catatanAmbil.value || undefined,

      antar_alamat: kirimStore.antar.alamat,
      antar_lat: kirimStore.antar.lat,
      antar_lng: kirimStore.antar.lng,
      antar_nama: namaPenerima.value.trim(),
      antar_telepon: teleponPenerima.value.trim(),
      antar_catatan: catatanAntar.value || undefined,

      metode: kirimStore.metode,
      kode_promo: promo.value?.kode,
    })

    kirimStore.hapus()
    // Ke layar tunggu, bukan langsung ke status: sebelum ada kurir, layar
    // status hanya berisi hal-hal yang belum berlaku.
    router.replace({ name: 'task-kirim-mencari', params: { nomor: hasil.nomor_invoice } })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}
</script>

<template>
  <KirimKonfirmasiSkeleton v-if="skelTampil" />

  <div v-else class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-36">
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Detail pengirim & penerima</h1>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!--
        Peta tujuan, dan bisa diperbaiki dari sini — pola yang sama dengan
        layar konfirmasi BisaAngkut: ketuk petanya, geser pinnya, simpan.

        Titik yang ditampilkan adalah TUJUAN, bukan rute. Di layar terakhir
        sebelum memesan, yang masih bisa keliru dan masih sempat diperbaiki
        adalah tempat paketnya diantar; titik ambil punya kartunya sendiri di
        bawah. Patokannya ikut di kartu ini, bukan di bawah nama penerima,
        karena patokan menerangkan TEMPAT, bukan orangnya.
      -->
      <KartuLokasiPeta
        :alamat="kirimStore.antar?.alamat ?? ''"
        :lat="kirimStore.antar?.lat ?? -6.2088"
        :lng="kirimStore.antar?.lng ?? 106.8456"
        label="Paket diantar ke"
        warna-pin="#f97316"
        tombol="Edit"
        :tersembunyi="lembarLokasi"
        @ubah="lembarLokasi = true"
      >
        <div class="flex items-center gap-2 rounded-xl bg-(--color-surface-container) px-3.5 py-3">
          <Icon name="pin" class="w-4 h-4 text-(--color-on-surface-variant) shrink-0" />
          <input
            v-model="catatanAntar"
            type="text"
            maxlength="255"
            placeholder="Ada patokan terdekat? (opsional)"
            class="w-full bg-transparent text-[13px] outline-none placeholder:text-(--color-on-surface-variant)"
          />
        </div>
      </KartuLokasiPeta>

      <SheetPilihLokasi
        :tampil="lembarLokasi"
        :alamat="kirimStore.antar?.alamat ?? ''"
        :lat="kirimStore.antar?.lat ?? -6.2088"
        :lng="kirimStore.antar?.lng ?? 106.8456"
        judul-peta="Set tujuan kiriman"
        @tutup="lembarLokasi = false"
        @pilih="terimaLokasi"
      />

      <!--
        HANYA PENERIMA di sini.
        
        Detail pengambilan punya halamannya sendiri, dibuka dari baris "Titik
        ambil" di layar sebelumnya. Menumpuk keduanya di satu formulir membuat
        orang mengisi kolom milik sisi yang salah — dan dua kartu yang mirip
        berjajar adalah cara paling mudah tertukar.

        Kalau halaman pengambilan tidak dibuka, pengirimnya jatuh ke pemilik
        akun. Itu tebakan yang paling masuk akal — dialah yang memesan — dan
        disebutkan apa adanya di bawah, bukan diisi diam-diam.
      -->
      <KontakPenerima
        v-model:nama="namaPenerima"
        v-model:telepon="teleponPenerima"
        judul="Detail Penerima"
        placeholder-nama="Nama penerima paket…"
        pesan-kosong="Nama dan nomor penerima dibutuhkan supaya kurir bisa menghubungi saat mengantar."
        :ditandai="ditandai"
      >
        <!-- Simpan alamat? — seperti di konfirmasi BisaAngkut -->
        <div class="flex items-center justify-between gap-3 flex-wrap pt-4 mt-1">
          <div class="flex items-center gap-3 min-w-0">
            <Icon name="bookmark" class="w-[22px] h-[22px] shrink-0" />
            <span class="font-extrabold text-[14px] truncate">Simpan alamat?</span>
          </div>
          <button
            type="button"
            class="shrink-0 min-h-11 rounded-full px-6 text-[13px] font-extrabold transition-all active:scale-95 shadow-xs"
            :class="
              alamatTersimpan
                ? 'bg-(--color-primary-container) text-(--color-on-primary-container)'
                : 'bg-(--color-azure) text-white'
            "
            @click="simpanAlamat"
          >
            {{ alamatTersimpan ? 'Tersimpan ✓' : 'Simpan' }}
          </button>
        </div>
      </KontakPenerima>

      <!-- Siapa yang menyerahkan paket, dan cara menggantinya -->
      <button
        type="button"
        class="bg-(--color-surface-0) rounded-2xl p-4 flex items-start gap-3 text-left active:scale-[0.99] transition-transform"
        @click="router.push({ name: 'task-kirim-ambil' })"
      >
        <Icon name="pin" class="w-[22px] h-[22px] mt-0.5 text-(--color-azure) shrink-0" />
        <span class="flex-1 min-w-0">
          <span class="block text-[11px] text-(--color-on-surface-variant)">Diambil dari</span>
          <span class="block text-[13px] font-bold leading-snug truncate">
            {{ kirimStore.ambil?.alamat }}
          </span>
          <span class="block text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
            {{ namaPengirimTampil }}
          </span>
        </span>
        <Icon name="chevron-right" class="w-4 h-4 mt-1 shrink-0 text-(--color-on-surface-variant)" />
      </button>

      <!--
        Rincian. Hanya bagian INI yang menunggu `pilihan`, bukan seluruh
        halaman: saat titik tujuan digeser, ongkirnya sesaat belum ada, dan
        halaman yang lenyap seluruhnya tidak memberi tahu apa pun.
      -->
      <section v-if="pilihan" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[14px] font-display font-extrabold mb-3">Rincian biaya</h2>

        <div class="flex flex-col gap-2 text-[13px]">
          <div v-for="b in pilihan.baris" :key="b.label" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">{{ b.label }}</span>
            <span class="font-semibold">{{ rupiah(b.nilai) }}</span>
          </div>
          <div
            v-if="promo"
            class="flex justify-between gap-3 text-(--color-on-secondary-container)"
          >
            <span>Voucher {{ promo.kode }}</span>
            <span class="font-semibold">-{{ rupiah(promo.potongan) }}</span>
          </div>
        </div>

        <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex justify-between gap-3">
          <span class="text-[14px] font-extrabold">Total</span>
          <span class="text-[17px] font-extrabold">{{ rupiah(total) }}</span>
        </div>

        <!--
          Plafon proteksi disebut ANGKANYA. Ganti rugi tanpa angka baru
          ketahuan batasnya justru saat barang benar-benar hilang.
        -->
        <p
          v-if="kirimStore.nilaiBarang > 0"
          class="mt-3 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)"
        >
          Proteksi mengganti sampai {{ rupiah(kirimStore.nilaiBarang) }} — sesuai nilai yang kamu
          daftarkan. Voucher tidak memotong premi proteksi.
        </p>
      </section>

      <!-- Ongkir sedang dihitung ulang untuk titik yang baru digeser -->
      <section
        v-else-if="menghitungUlang"
        class="bg-(--color-surface-0) rounded-2xl p-5 flex items-center gap-3"
      >
        <PemuatBerputar class="w-5 h-5 text-(--color-azure) shrink-0" />
        <p class="text-[13px] text-(--color-on-surface-variant)">
          Menghitung ongkir untuk titik yang baru…
        </p>
      </section>

      <!--
        Tidak ada kartu metode pembayaran di sini: metodenya diganti dari bar
        bayar di bawah, satu kendali untuk satu hal.
      -->

      <p
        v-if="kirimStore.pakaiKodeTerima"
        class="px-1 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)"
      >
        Kode terima paket dibuat setelah pesanan jadi, dan hanya muncul di layar pesananmu. Berikan
        ke penerima lewat cara yang kamu percaya.
      </p>
    </main>

    <!--
      Lembar metode pembayaran yang sama dengan checkout BisaBelanja: daftar,
      saldo, aktivasi, dan aturannya satu, bukan dua yang bisa berbeda.
    -->
    <SheetMetodeBayar
      v-model="kirimStore.metode"
      v-model:buka="lembarMetode"
      :total="total"
    />

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <!--
        Bar bayar sebentuk dengan checkout BisaBelanja: metodenya bisa diganti
        dari sini, tanpa menggulung balik ke tengah formulir.
      -->
      <div
        class="max-w-[430px] mx-auto px-4 py-3.5 pb-[calc(0.875rem+env(safe-area-inset-bottom))] flex items-center justify-between gap-4"
      >
        <div class="flex flex-col min-w-0">
          <button
            type="button"
            class="flex items-center gap-1 text-[12.5px] font-semibold text-(--color-on-surface-variant) active:scale-95 transition-transform"
            @click="lembarMetode = true"
          >
            {{ namaMetode }}
            <Icon name="chevron-down" class="w-3.5 h-3.5" />
          </button>
          <span class="flex items-center gap-2 min-w-0">
            <span class="text-[20px] font-extrabold leading-tight truncate">{{ rupiah(total) }}</span>
            <span
              v-if="promo"
              class="shrink-0 text-[10.5px] font-bold text-(--color-secondary) bg-(--color-secondary-container) rounded-full px-2 py-0.5"
            >
              Hemat {{ rupiah(promo.potongan) }}
            </span>
          </span>
        </div>

        <button
          type="button"
          class="flex-1 bg-(--color-azure) text-white rounded-xl py-3.5 text-[15px] font-extrabold active:scale-95 transition-all disabled:opacity-40 disabled:active:scale-100 flex items-center justify-center gap-2"
          :disabled="memproses || menghitungUlang || !pilihan"
          @click="kirim"
        >
          <Spinner v-if="memproses" />
          {{ memproses ? 'Memproses…' : 'Pesan' }}
        </button>
      </div>

      <p
        v-if="galat"
        role="alert"
        class="max-w-[430px] mx-auto px-4 pb-3 -mt-1 text-[12px] font-semibold text-(--color-error)"
      >
        {{ galat }}
      </p>
    </footer>
  </div>
</template>
