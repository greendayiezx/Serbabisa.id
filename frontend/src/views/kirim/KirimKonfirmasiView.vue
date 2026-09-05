<script setup lang="ts">
/**
 * BisaKirim — data pengirim, penerima, dan pembayaran.
 *
 * Nomor telepon KEDUA sisi wajib, dan itu bukan formalitas: kurir menelepon
 * pengirim saat menjemput dan penerima saat mengantar. Satu nomor yang kosong
 * berarti satu perjalanan yang berhenti di depan pintu tanpa ada yang bisa
 * dihubungi.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import KirimKonfirmasiSkeleton from '@/components/skeleton/KirimKonfirmasiSkeleton.vue'
import KontakPenerima from '@/components/KontakPenerima.vue'
import PetaRuteKirim from '@/components/kirim/PetaRuteKirim.vue'
import SheetMetodeBayar from '@/components/SheetMetodeBayar.vue'
import { useSkeleton } from '@/composables/useSkeleton'
import { useKirimStore } from '@/stores/kirim'
import { useAuthStore } from '@/stores/auth'
import { pesanKirim } from '@/api/kirim'
import { pesanError } from '@/api/belanja'
import { LABEL_METODE, type MetodeId } from '@/lib/metodeBayar'
import { rupiah } from '@/lib/kirim'

const router = useRouter()
const kembali = useKembali()
const kirimStore = useKirimStore()
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
    router.replace({ name: 'task-kirim-status', params: { nomor: hasil.nomor_invoice } })
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

    <main v-if="pilihan" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!--
        Peta rute, terakhir kali sebelum pesanan dibuat. Rutenya diambil dari
        store — hasil hitungan server yang sama dengan layar detail — supaya
        garisnya tidak berubah jadi lurus putus-putus di sini, bentuk yang di
        aplikasi ini berarti "rutenya tidak diketahui".
      -->
      <section class="bg-(--color-surface-0) rounded-2xl p-3">
        <PetaRuteKirim
          :ambil="kirimStore.ambil"
          :antar="kirimStore.antar"
          :geometri="kirimStore.geometri"
          :lewat-jalan="kirimStore.lewatJalan"
        />
        <div class="px-1 pt-3 flex flex-col gap-2">
          <p class="flex items-start gap-2 text-[12.5px] leading-snug">
            <span class="mt-1.5 w-2 h-2 rounded-full bg-(--color-azure) shrink-0"></span>
            <span class="min-w-0 truncate">{{ kirimStore.ambil?.alamat }}</span>
          </p>
          <p class="flex items-start gap-2 text-[12.5px] leading-snug">
            <span class="mt-1.5 w-2 h-2 rounded-full bg-orange-500 shrink-0"></span>
            <span class="min-w-0 truncate">{{ kirimStore.antar?.alamat }}</span>
          </p>
        </div>
      </section>

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
        :subjudul="kirimStore.antar?.alamat"
        placeholder-nama="Nama penerima paket…"
        pesan-kosong="Nama dan nomor penerima dibutuhkan supaya kurir bisa menghubungi saat mengantar."
        :ditandai="ditandai"
      >
        <div class="mt-4">
          <label
            class="block text-[11.5px] font-bold text-(--color-on-surface-variant) uppercase tracking-wide mb-1.5"
          >
            Patokan untuk kurir
          </label>
          <input
            v-model="catatanAntar"
            type="text"
            maxlength="255"
            placeholder="Mis. lantai 3, sebelah minimarket"
            class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none placeholder:text-(--color-on-surface-variant)"
          />
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

      <!-- Rincian -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
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

      <!-- Pembayaran -->
      <button
        type="button"
        class="bg-(--color-surface-0) rounded-2xl p-5 flex items-center gap-3 text-left active:scale-[0.99] transition-transform"
        @click="lembarMetode = true"
      >
        <Icon name="wallet" class="w-5 h-5 text-(--color-azure) shrink-0" />
        <div class="flex-1">
          <p class="text-[13.5px] font-bold">{{ namaMetode }}</p>
          <p class="text-[11.5px] text-(--color-on-surface-variant)">Metode pembayaran</p>
        </div>
        <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface-variant)" />
      </button>

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
          class="flex-1 bg-(--color-azure) text-white rounded-xl py-3.5 text-[15px] font-extrabold active:scale-95 transition-all disabled:opacity-40 disabled:active:scale-100"
          :disabled="memproses"
          @click="kirim"
        >
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
