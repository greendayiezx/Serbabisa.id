<script setup lang="ts">
/**
 * BisaJemput — konfirmasi pemesanan.
 *
 * Rincian tarifnya dibuka apa adanya: tarif dasar, jarak, waktu, penyesuaian
 * minimum, dan kenaikan permintaan kalau ada. Angka yang tidak bisa dijelaskan
 * asalnya akan selalu terbaca sebagai angka yang mengada-ada, dan penumpang
 * yang merasa begitu tidak memesan lagi.
 *
 * Yang dikirim ke server tetap hanya pilihan dan koordinat. Tarif di layar ini
 * hasil hitungan server pada langkah sebelumnya, dan server menghitungnya
 * sekali lagi sebelum menagih — kalau keduanya berbeda, yang berlaku adalah
 * hitungan server.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import { useJemputStore } from '@/stores/jemput'
import { pesanJemput } from '@/api/jemput'
import { pesanError } from '@/api/belanja'
import { METODE_BAYAR, rupiah, type PromoJemput } from '@/lib/jemput'

const router = useRouter()
const kembali = useKembali()
const jemputStore = useJemputStore()

const pilihan = computed(() => jemputStore.pilihan)
const jemput = computed(() => jemputStore.jemput)
const tujuan = computed(() => jemputStore.tujuan)

const metode = ref('gopay')
const lembarMetode = ref(false)
const lembarPromo = ref(false)

const promoDipakai = ref<PromoJemput | null>(null)
const penumpang = ref(1)
const untukOrangLain = ref(false)
const namaPenumpang = ref('')
const teleponPenumpang = ref('')
const dijadwalkan = ref(false)
const jadwalPada = ref('')
const catatan = ref('')

const memproses = ref(false)
const galat = ref<string | null>(null)

const promoTersedia = computed(() => pilihan.value?.promo ?? [])
const potongan = computed(() => promoDipakai.value?.potongan ?? 0)
const total = computed(() => Math.max(0, (pilihan.value?.tarif ?? 0) - potongan.value))

const namaMetode = computed(
  () => METODE_BAYAR.find((m) => m.id === metode.value)?.nama ?? metode.value,
)

/** Batas waktu penjadwalan: satu jam dari sekarang, dibulatkan ke menit. */
const minimalJadwal = computed(() => {
  const t = new Date(Date.now() + 60 * 60 * 1000)
  const p = (n: number) => String(n).padStart(2, '0')
  return `${t.getFullYear()}-${p(t.getMonth() + 1)}-${p(t.getDate())}T${p(t.getHours())}:${p(t.getMinutes())}`
})

onMounted(() => {
  if (!jemputStore.pilihan || !jemputStore.jemputDikonfirmasi) {
    router.replace({ name: 'task-jemput-titik' })
    return
  }
  promoDipakai.value = jemputStore.pilihan.promo_terbaik ?? null
})

function pakaiPromo(p: PromoJemput) {
  if (!p.bisa_dipakai) return
  promoDipakai.value = promoDipakai.value?.kode === p.kode ? null : p
  lembarPromo.value = false
}

async function pesan() {
  const p = pilihan.value
  if (!p || !jemput.value || !tujuan.value || memproses.value) return

  if (untukOrangLain.value && (!namaPenumpang.value.trim() || !teleponPenumpang.value.trim())) {
    galat.value = 'Nama dan nomor penumpang belum diisi.'
    return
  }
  if (dijadwalkan.value && !jadwalPada.value) {
    galat.value = 'Jadwal penjemputan belum dipilih.'
    return
  }

  memproses.value = true
  galat.value = null

  try {
    const hasil = await pesanJemput({
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
      metode: metode.value,
      kode_promo: promoDipakai.value?.kode,
      untuk_orang_lain: untukOrangLain.value,
      nama_penumpang: untukOrangLain.value ? namaPenumpang.value.trim() : undefined,
      telepon_penumpang: untukOrangLain.value ? teleponPenumpang.value.trim() : undefined,
      dijadwalkan: dijadwalkan.value,
      jadwal_pada: dijadwalkan.value ? jadwalPada.value : undefined,
      catatan: catatan.value || undefined,
    })

    jemputStore.hapus()
    router.replace({ name: 'task-jemput-perjalanan', params: { nomor: hasil.nomor_invoice } })
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memproses.value = false
  }
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-36">
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold pr-10">Konfirmasi Pesanan</h1>
      </div>
    </header>

    <main v-if="pilihan" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- Rute -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex gap-3">
          <div class="flex flex-col items-center pt-1 shrink-0">
            <span class="w-3 h-3 rounded-full bg-(--color-on-secondary-container)"></span>
            <span class="w-0.5 flex-1 my-1 bg-(--color-outline)/30"></span>
            <span class="w-3 h-3 rounded-full bg-(--color-on-tertiary-container)"></span>
          </div>
          <div class="flex-1 min-w-0 flex flex-col gap-4">
            <div>
              <p class="text-[11px] font-bold uppercase tracking-wider text-(--color-azure)">
                Titik jemput
              </p>
              <p class="text-[13px] font-semibold leading-snug">{{ jemput?.alamat }}</p>
              <p
                v-if="jemput?.catatan"
                class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5"
              >
                {{ jemput.catatan }}
              </p>
            </div>
            <div>
              <p class="text-[11px] font-bold uppercase tracking-wider text-(--color-on-surface-variant)">
                Tujuan
              </p>
              <p class="text-[13px] font-semibold leading-snug">{{ tujuan?.alamat }}</p>
            </div>
          </div>
        </div>

        <div
          class="mt-4 pt-4 border-t border-(--color-outline)/15 flex items-center justify-between gap-3"
        >
          <span class="text-[13px] font-bold">{{ pilihan.label }}</span>
          <span class="text-[12px] text-(--color-on-surface-variant)">
            {{ pilihan.km.toFixed(1).replace('.', ',') }} km · {{ pilihan.menit }} menit
          </span>
        </div>
      </section>

      <!-- Rincian harga -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h2 class="text-[14px] font-display font-extrabold mb-3">Rincian tarif</h2>

        <div class="flex flex-col gap-2 text-[13px]">
          <div v-for="b in pilihan.baris" :key="b.label" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">{{ b.label }}</span>
            <span class="font-semibold">{{ rupiah(b.nilai) }}</span>
          </div>

          <div
            v-if="potongan > 0"
            class="flex justify-between gap-3 text-(--color-on-secondary-container)"
          >
            <span>Promo {{ promoDipakai?.kode }}</span>
            <span class="font-semibold">-{{ rupiah(potongan) }}</span>
          </div>
        </div>

        <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex justify-between gap-3">
          <span class="text-[14px] font-extrabold">Total</span>
          <span class="text-[17px] font-extrabold">{{ rupiah(total) }}</span>
        </div>

        <p
          v-if="pilihan.sibuk"
          class="mt-2 text-[11px] leading-snug text-(--color-on-surface-variant)"
        >
          {{ pilihan.sibuk_alasan }}.
        </p>
      </section>

      <!-- Promo -->
      <button
        type="button"
        class="bg-(--color-surface-0) rounded-2xl p-5 flex items-center gap-3 text-left active:scale-[0.99] transition-transform"
        @click="lembarPromo = true"
      >
        <Icon name="sparkle" class="w-5 h-5 text-(--color-azure) shrink-0" />
        <div class="flex-1 min-w-0">
          <p class="text-[13.5px] font-bold">
            {{ promoDipakai ? promoDipakai.nama : 'Pakai promo' }}
          </p>
          <p class="text-[11.5px] text-(--color-on-surface-variant)">
            {{ promoDipakai ? `Hemat ${rupiah(potongan)}` : 'Lihat promo yang berlaku' }}
          </p>
        </div>
        <Icon name="chevron-right" class="w-4 h-4 text-(--color-on-surface-variant)" />
      </button>

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

      <!-- Penumpang & opsi -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5 flex flex-col gap-4">
        <div class="flex items-center justify-between gap-3">
          <div>
            <p class="text-[13.5px] font-bold">Jumlah penumpang</p>
            <p class="text-[11.5px] text-(--color-on-surface-variant)">
              Maksimal {{ pilihan.kapasitas }} orang
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
              :disabled="penumpang >= pilihan.kapasitas"
              @click="penumpang++"
            >
              <Icon name="plus" class="w-4 h-4" />
            </button>
          </div>
        </div>

        <!--
          Dipesan untuk orang lain: pengemudi menjemput seseorang yang bukan
          pemilik akun, jadi nama dan nomor yang bisa dihubungi di lokasi wajib
          ada. Tanpa itu pengemudi hanya punya titik peta dan nama pemesan yang
          tidak akan muncul.
        -->
        <div class="pt-4 border-t border-(--color-outline)/15">
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

        <div class="pt-4 border-t border-(--color-outline)/15">
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
            <!--
              Tarif perjalanan terjadwal dihitung ulang saat penjemputan, bukan
              dikunci dari sekarang: ramai tidaknya nanti tidak bisa diketahui
              hari ini. Dikatakan supaya angka yang berubah tidak terbaca
              sebagai tagihan yang keliru.
            -->
            <p class="mt-2 text-[11px] leading-snug text-(--color-on-surface-variant)">
              Tarif perjalanan terjadwal dihitung ulang saat penjemputan, mengikuti ramainya
              permintaan saat itu.
            </p>
          </div>
        </div>
      </section>

      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-2">Catatan untuk pengemudi</h3>
        <textarea
          v-model="catatan"
          rows="2"
          placeholder="Mis. bawa satu koper kabin"
          class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
        />
      </section>
    </main>

    <!-- Lembar promo -->
    <div v-if="lembarPromo" class="fixed inset-0 z-50 flex items-end" @click.self="lembarPromo = false">
      <div class="absolute inset-0 bg-black/40"></div>
      <div class="relative w-full max-w-[430px] mx-auto bg-(--color-surface-0) rounded-t-3xl p-5 max-h-[80vh] overflow-y-auto">
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>
        <h2 class="text-[16px] font-display font-extrabold mb-3">Promo</h2>

        <div class="flex flex-col gap-2.5">
          <button
            v-for="p in promoTersedia"
            :key="p.kode"
            type="button"
            class="w-full rounded-2xl border-2 p-4 text-left transition-colors"
            :class="[
              promoDipakai?.kode === p.kode
                ? 'border-(--color-azure) bg-(--color-secondary-container)/40'
                : 'border-(--color-outline)/20',
              p.bisa_dipakai ? '' : 'opacity-55',
            ]"
            :disabled="!p.bisa_dipakai"
            @click="pakaiPromo(p)"
          >
            <div class="flex items-center justify-between gap-3">
              <span class="text-[13.5px] font-extrabold">{{ p.nama }}</span>
              <span v-if="p.bisa_dipakai" class="text-[13px] font-extrabold text-(--color-on-secondary-container)">
                -{{ rupiah(p.potongan) }}
              </span>
            </div>
            <p class="mt-1 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
              {{ p.deskripsi }}
            </p>
            <!-- Alasan promo tidak bisa dipakai ditulis, bukan cuma diredupkan. -->
            <p v-if="p.alasan" class="mt-1.5 text-[11px] font-semibold text-(--color-error)">
              {{ p.alasan }}
            </p>
          </button>
        </div>
      </div>
    </div>

    <!-- Lembar metode bayar -->
    <div
      v-if="lembarMetode"
      class="fixed inset-0 z-50 flex items-end"
      @click.self="lembarMetode = false"
    >
      <div class="absolute inset-0 bg-black/40"></div>
      <div class="relative w-full max-w-[430px] mx-auto bg-(--color-surface-0) rounded-t-3xl p-5">
        <div class="w-10 h-1 rounded-full bg-(--color-outline)/30 mx-auto mb-4"></div>
        <h2 class="text-[16px] font-display font-extrabold mb-3">Metode pembayaran</h2>

        <button
          v-for="m in METODE_BAYAR"
          :key="m.id"
          type="button"
          class="w-full flex items-center gap-3 rounded-xl px-3 py-3.5 text-left transition-colors"
          :class="metode === m.id ? 'bg-(--color-secondary-container)/50' : ''"
          @click="((metode = m.id), (lembarMetode = false))"
        >
          <Icon :name="m.ikon" class="w-5 h-5 text-(--color-azure)" />
          <span class="flex-1 text-[13.5px] font-semibold">{{ m.nama }}</span>
          <Icon v-if="metode === m.id" name="check" class="w-4 h-4 text-(--color-azure)" />
        </button>
      </div>
    </div>

    <footer class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0) shadow-[0_-10px_40px_rgba(0,0,0,0.08)]">
      <div class="max-w-[430px] mx-auto px-4 pt-3 pb-[calc(0.875rem+env(safe-area-inset-bottom))]">
        <div class="flex items-center justify-between gap-3 mb-3">
          <span class="text-[12.5px] text-(--color-on-surface-variant)">{{ namaMetode }}</span>
          <span class="text-[17px] font-extrabold">{{ rupiah(total) }}</span>
        </div>

        <button
          type="button"
          class="w-full h-12 rounded-full bg-(--color-azure) text-white text-[14.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="memproses"
          @click="pesan"
        >
          {{ memproses ? 'Memproses…' : 'Pesan BisaJemput' }}
          <Icon v-if="!memproses" name="arrow-right" class="w-4 h-4" />
        </button>

        <p v-if="galat" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galat }}
        </p>
      </div>
    </footer>
  </div>
</template>
