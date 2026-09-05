<script setup lang="ts">
/**
 * BisaKirim — beranda, layar pertama setelah lokasi dipilih.
 *
 * Isinya satu pekerjaan: menentukan paketnya diambil di mana dan diantar ke
 * mana. Sisanya — voucher, keunggulan — ditaruh di bawah, karena orang yang
 * membuka menu ini datang untuk mengirim, bukan untuk membaca.
 *
 * Titik ambil sudah terisi dari halaman lokasi. Titik antarnya kosong dan
 * itulah satu-satunya kolom yang menunggu diisi.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import SheetPilihLokasi from '@/components/SheetPilihLokasi.vue'
import KirimBerandaSkeleton from '@/components/skeleton/KirimBerandaSkeleton.vue'
import KirimHeroArt from '@/components/kirim/KirimHeroArt.vue'
import { useSkeleton } from '@/composables/useSkeleton'
import { useKirimStore } from '@/stores/kirim'
import { useLocationStore } from '@/stores/location'
import { voucherKirim, type VoucherKirim } from '@/api/kirim'
import { KEUNGGULAN, rupiah } from '@/lib/kirim'

const router = useRouter()
const kembali = useKembali()
const kirimStore = useKirimStore()
const locationStore = useLocationStore()

const { tampil: skelTampil, tandaiSiap } = useSkeleton()

const ambil = computed(() => kirimStore.ambil)
const antar = computed(() => kirimStore.antar)

type Lembar = 'ambil' | 'antar' | null
const lembar = ref<Lembar>(null)

const riwayat = ref(locationStore.loadSearchHistory())

/*
 * Voucher diambil dari server, bukan disalin ke klien: katalognya satu, dan
 * salinan di sini akan mulai berbeda pada perubahan pertama tanpa memberi
 * tanda apa pun.
 *
 * Yang ditampilkan di layar ini SYARATNYA saja. Potongan rupiahnya bergantung
 * ongkir, dan ongkirnya belum ada sebelum tujuannya diisi — menyebut angka di
 * sini berarti menjanjikan potongan yang belum tentu berlaku.
 */
const voucher = ref<VoucherKirim[]>([])
const jumlahVoucher = ref(0)
const lembarVoucher = ref(false)

onMounted(async () => {
  // Titik ambil datang dari halaman lokasi. Kalau seseorang masuk lewat tautan
  // langsung, draf lokasi terakhir dipakai — dan kalau itu pun tidak ada,
  // kolomnya kosong dan menunggu diisi, bukan diisi tebakan.
  if (!kirimStore.ambil && locationStore.draft) {
    kirimStore.setAmbil({ ...locationStore.draft })
  }
  tandaiSiap()

  try {
    const v = await voucherKirim()
    voucher.value = v.voucher
    jumlahVoucher.value = v.jumlah
  } catch {
    // Voucher gagal diambil bukan alasan menahan halaman: bagian ini
    // disembunyikan, dan pemesanannya tetap bisa jalan.
    voucher.value = []
    jumlahVoucher.value = 0
  }
})

function terimaLokasi(l: { alamat: string; lat: number; lng: number }) {
  if (lembar.value === 'ambil') kirimStore.setAmbil({ ...(kirimStore.ambil ?? {}), ...l })
  else kirimStore.setAntar({ ...(kirimStore.antar ?? {}), ...l })

  locationStore.addSearchHistory(l)
  riwayat.value = locationStore.loadSearchHistory()
  lembar.value = null
}

function pakaiRiwayat(r: { label: string; address: string; lat: number; lng: number }) {
  const titik = { alamat: r.address, lat: r.lat, lng: r.lng }
  if (!antar.value) kirimStore.setAntar(titik)
  else kirimStore.setAmbil(titik)
}

const siap = computed(() => !!ambil.value && !!antar.value)

function lanjut() {
  if (!siap.value) return
  router.push({ name: 'task-kirim-detail' })
}
</script>

<template>
  <KirimBerandaSkeleton v-if="skelTampil" />

  <div v-else class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
    <!-- Hero: banner layanan, dengan tombol kembali melayang di atasnya -->
    <div class="relative w-full overflow-hidden">
      <KirimHeroArt />

      <button
        type="button"
        aria-label="Kembali"
        class="absolute top-4 left-4 z-10 w-11 h-11 rounded-full bg-(--color-surface-0)/95 shadow-lg flex items-center justify-center active:scale-95 transition-transform"
        @click="kembali"
      >
        <Icon name="arrow-left" class="w-5 h-5" />
      </button>
    </div>

    <main class="max-w-[430px] mx-auto px-4 -mt-8 relative z-10 flex flex-col gap-3.5">
      <!-- Titik ambil & antar -->
      <section class="bg-(--color-surface-0) rounded-2xl shadow-lg p-4">
        <div class="flex items-start gap-3">
          <div class="flex-1 min-w-0">
            <button
              type="button"
              class="w-full flex items-start gap-3 text-left"
              @click="lembar = 'ambil'"
            >
              <Icon name="pin" class="w-[22px] h-[22px] mt-0.5 text-(--color-azure) shrink-0" />
              <span class="flex-1 min-w-0">
                <span class="block text-[11px] text-(--color-on-surface-variant)">Ambil paket di</span>
                <span class="block truncate text-[13.5px] font-bold">
                  {{ ambil?.alamat ?? 'Pilih titik ambil' }}
                </span>
              </span>
            </button>

            <div class="my-2.5 ml-[9px] w-[22px] flex flex-col items-center gap-[3px]" aria-hidden="true">
              <span class="w-[3px] h-[3px] rounded-full bg-(--color-azure)/70"></span>
              <span class="w-[3px] h-[3px] rounded-full bg-orange-400/70"></span>
              <span class="w-[3px] h-[3px] rounded-full bg-orange-500"></span>
            </div>

            <button
              type="button"
              class="w-full flex items-start gap-3 text-left"
              @click="lembar = 'antar'"
            >
              <Icon name="pin" class="w-[22px] h-[22px] mt-0.5 text-orange-500 shrink-0" />
              <span class="flex-1 min-w-0">
                <span class="block text-[11px] text-(--color-on-surface-variant)">Kirim paket ke</span>
                <span
                  class="block truncate text-[13.5px]"
                  :class="antar ? 'font-bold' : 'text-(--color-on-surface-variant)'"
                >
                  {{ antar?.alamat ?? 'Kirim paket ke mana?' }}
                </span>
              </span>
            </button>
          </div>

          <!--
            Tukar arah. Ikut menukar nama dan nomor teleponnya juga — titik yang
            tertukar tanpa kontaknya berarti kurir menelepon orang yang salah.
          -->
          <button
            type="button"
            aria-label="Tukar titik ambil dan tujuan"
            class="w-10 h-10 rounded-full border border-(--color-outline)/30 flex items-center justify-center shrink-0 self-center active:scale-90 transition-transform disabled:opacity-40"
            :disabled="!ambil || !antar"
            @click="kirimStore.tukar()"
          >
            <Icon name="arrow-right" class="w-4 h-4 -rotate-90" />
          </button>
        </div>

        <!--
          Alamat terakhir menyatu dengan kolom tujuan, bukan berdiri sebagai
          kartu sendiri: daftar ini ADALAH cara mengisi kolom di atasnya, dan
          memisahkannya membuat orang mengira keduanya dua hal berbeda.
        -->
        <div v-if="riwayat.length" class="mt-3 pt-3 border-t border-(--color-outline)/12">
          <button
            v-for="r in riwayat.slice(0, 4)"
            :key="r.id"
            type="button"
            class="w-full py-2.5 flex items-start gap-3 text-left active:opacity-70 transition-opacity"
            @click="pakaiRiwayat(r)"
          >
            <Icon name="clock" class="w-4 h-4 mt-0.5 shrink-0 text-(--color-on-surface-variant)" />
            <span class="flex-1 min-w-0">
              <span class="block text-[13px] font-bold leading-snug truncate">{{ r.label }}</span>
              <span class="block truncate text-[11.5px] text-(--color-on-surface-variant) mt-0.5">
                {{ r.address }}
              </span>
            </span>
          </button>
        </div>
      </section>

      <!-- Voucher: syaratnya saja, angkanya menyusul di layar detail -->
      <button
        v-if="jumlahVoucher > 0"
        type="button"
        class="bg-(--color-surface-0) rounded-2xl p-4 flex items-center gap-3 text-left active:scale-[0.99] transition-transform"
        @click="lembarVoucher = true"
      >
        <span
          class="w-10 h-10 rounded-xl bg-(--color-secondary-container) flex items-center justify-center shrink-0"
        >
          <Icon name="sparkle" class="w-5 h-5 text-(--color-on-secondary-container)" />
        </span>
        <div class="flex-1 min-w-0">
          <p class="text-[13.5px] font-extrabold">
            {{ jumlahVoucher }} voucher buat kamu
          </p>
          <p class="text-[11.5px] text-(--color-on-surface-variant) truncate">
            Potongannya muncul setelah tujuannya diisi
          </p>
        </div>
        <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface-variant)" />
      </button>

      <!-- Yang benar-benar bisa dilakukan layanan ini -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[14px] font-display font-extrabold mb-1">BisaKirim siap bantu</h2>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-3.5">
          Kirim paket, dokumen, atau kunci — dijemput dan diantar hari itu juga.
        </p>

        <div class="flex flex-col gap-2.5">
          <div
            v-for="k in KEUNGGULAN"
            :key="k.judul"
            class="flex items-start gap-3 rounded-xl bg-(--color-surface-container) p-3.5"
          >
            <span
              class="w-9 h-9 rounded-xl bg-(--color-primary-container) flex items-center justify-center shrink-0"
            >
              <Icon :name="k.ikon" class="w-4.5 h-4.5 text-(--color-on-primary-container)" />
            </span>
            <div class="flex-1 min-w-0">
              <p class="text-[13px] font-bold leading-tight">{{ k.judul }}</p>
              <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant) mt-0.5">
                {{ k.isi }}
              </p>
            </div>
          </div>
        </div>
      </section>

      <!-- Daftar voucher -->
      <div
        v-if="lembarVoucher"
        class="fixed inset-0 z-50 flex items-end"
        @click.self="lembarVoucher = false"
      >
        <div class="absolute inset-0 bg-black/40"></div>
        <div
          class="relative w-full max-w-[430px] mx-auto bg-(--color-surface-0) rounded-t-3xl p-5 max-h-[80vh] overflow-y-auto"
        >
          <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>
          <h2 class="text-[16px] font-display font-extrabold mb-1">Voucher BisaKirim</h2>
          <p class="text-[11.5px] leading-snug text-(--color-on-surface-variant) mb-4">
            Berapa potongannya bergantung ongkir, jadi angkanya baru muncul setelah tujuan kiriman
            diisi.
          </p>

          <div class="flex flex-col gap-2.5">
            <div
              v-for="v in voucher"
              :key="v.kode"
              class="rounded-2xl border-2 border-(--color-outline)/20 p-4"
              :class="v.terpakai ? 'opacity-55' : ''"
            >
              <div class="flex items-center justify-between gap-3">
                <p class="text-[13.5px] font-extrabold">{{ v.nama }}</p>
                <span
                  class="px-2.5 py-0.5 rounded-md bg-(--color-surface-container) text-[10.5px] font-extrabold"
                >
                  {{ v.kode }}
                </span>
              </div>
              <p class="mt-1 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
                {{ v.deskripsi }}
              </p>
              <p class="mt-1.5 text-[11px] text-(--color-on-surface-variant)">
                Mulai ongkir {{ rupiah(v.minimum) }}
              </p>
              <!-- Yang sudah terpakai tetap ditampilkan, tapi ditandai. -->
              <p v-if="v.terpakai" class="mt-1.5 text-[11px] font-semibold text-(--color-error)">
                Sudah dipakai — voucher ini hanya untuk kiriman pertama.
              </p>
            </div>
          </div>
        </div>
      </div>

      <SheetPilihLokasi
        :tampil="lembar !== null"
        :alamat="(lembar === 'ambil' ? ambil?.alamat : antar?.alamat) ?? ''"
        :lat="(lembar === 'ambil' ? ambil?.lat : antar?.lat) ?? ambil?.lat ?? -6.2088"
        :lng="(lembar === 'ambil' ? ambil?.lng : antar?.lng) ?? ambil?.lng ?? 106.8456"
        :judul-peta="lembar === 'ambil' ? 'Set titik ambil' : 'Set tujuan kiriman'"
        @tutup="lembar = null"
        @pilih="terimaLokasi"
      />
    </main>

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.10)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="!siap"
          @click="lanjut"
        >
          Lanjut
          <Icon name="arrow-right" class="w-4 h-4" />
        </button>
        <p v-if="!siap" class="mt-2 text-[11.5px] text-center text-(--color-on-surface-variant)">
          Isi titik ambil dan tujuannya dulu supaya ongkirnya bisa dihitung.
        </p>
      </div>
    </footer>
  </div>
</template>
