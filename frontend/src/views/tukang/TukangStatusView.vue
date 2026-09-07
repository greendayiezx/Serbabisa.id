<script setup lang="ts">
/**
 * BisaTukang — status pekerjaan.
 *
 * Seluruh isi layar ini digerakkan `tahap` DARI SERVER. Versi sebelumnya
 * memajukan keadaannya sendiri dengan pewaktu di browser: tukang "berangkat"
 * lalu "tiba" tanpa ada siapa-siapa yang benar-benar berangkat, dan RAB-nya
 * dikarang di sisi klien. Yang tampil sekarang hanya yang memang tercatat.
 *
 * Bagian yang paling berhati-hati adalah PENAWARAN. Menyetujuinya mengikat
 * harga dan lingkup kerja, jadi tombolnya menuntut nama penyetuju dan totalnya
 * tidak pernah dikirim dari sini — server memakai angka yang tersimpan.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import AvatarTukang from '@/components/tukang/AvatarTukang.vue'
import { useKembali } from '@/composables/useKembali'
import { useAuthStore } from '@/stores/auth'
import {
  ambilPekerjaan,
  batalTukang,
  nilaiTukang,
  revisiPenawaran,
  setujuiPenawaran,
  tipTukang,
  type Pekerjaan,
  type TahapTukang,
} from '@/api/tukang'
import { pesanError } from '@/api/belanja'

const route = useRoute()
const kembali = useKembali()
const auth = useAuthStore()
const nomor = String(route.params.nomor ?? '')

const data = ref<Pekerjaan | null>(null)
const memuat = ref(true)
const galat = ref<string | null>(null)
let pewaktu: ReturnType<typeof setInterval> | null = null

const tahap = computed<TahapTukang>(() => data.value?.tahap ?? 'mencari')
const beres = computed(() => tahap.value === 'selesai' || tahap.value === 'batal')

/** Satu kalimat keadaan; yang dicari orang saat membuka layar ini. */
const TAHAP: Record<TahapTukang, { judul: string; keterangan: string }> = {
  mencari: {
    judul: 'Mencari tukang terdekat',
    keterangan: 'Belum ada yang ditugaskan, dan kamu belum ditagih apa pun.',
  },
  menuju: {
    judul: 'Tukang sedang menuju lokasi',
    keterangan: 'Siapkan akses ke area yang rusak.',
  },
  tiba: {
    judul: 'Tukang sudah sampai',
    keterangan: 'Sedang memeriksa kerusakannya sebelum menyusun penawaran.',
  },
  penawaran: {
    judul: 'Penawaran sudah terbit',
    keterangan: 'Periksa rinciannya. Pekerjaan baru dimulai setelah kamu setujui.',
  },
  dikerjakan: {
    judul: 'Sedang dikerjakan',
    keterangan: 'Harganya sudah disepakati dan tidak berubah di tengah jalan.',
  },
  selesai: {
    judul: 'Pekerjaan selesai',
    keterangan: 'Terima kasih sudah pakai BisaTukang.',
  },
  batal: {
    judul: 'Pesanan dibatalkan',
    keterangan: 'Tidak ada yang ditagih untuk pesanan ini.',
  },
}

const pita = computed(() => TAHAP[tahap.value] ?? TAHAP.mencari)

function rupiah(n: number) {
  return 'Rp' + Math.round(n).toLocaleString('id-ID')
}

function tanggal(iso: string | null | undefined) {
  if (!iso) return null
  const d = new Date(iso)
  return Number.isNaN(d.getTime())
    ? null
    : d.toLocaleDateString('id-ID', { day: 'numeric', month: 'long', year: 'numeric' })
}

/* ────────── Muat berkala ────────── */
async function muat() {
  try {
    data.value = await ambilPekerjaan(nomor)
    if (beres.value && pewaktu) {
      clearInterval(pewaktu)
      pewaktu = null
    }
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
}

/* ────────── Penawaran ────────── */
const namaPenyetuju = ref('')
const mengirimSetuju = ref(false)
const galatPenawaran = ref<string | null>(null)

const penawaran = computed(() => data.value?.penawaran ?? null)
const bisaJawab = computed(
  () => !!penawaran.value && penawaran.value.keputusan !== 'disetujui' && !penawaran.value.kedaluwarsa,
)

async function setujui() {
  if (mengirimSetuju.value || namaPenyetuju.value.trim().length < 2) return

  mengirimSetuju.value = true
  galatPenawaran.value = null
  try {
    await setujuiPenawaran(nomor, namaPenyetuju.value.trim())
    await muat()
  } catch (e) {
    galatPenawaran.value = pesanError(e)
  } finally {
    mengirimSetuju.value = false
  }
}

const revisiTerbuka = ref(false)
const catatanRevisi = ref('')
const mengirimRevisi = ref(false)

async function ajukanRevisi() {
  if (mengirimRevisi.value || catatanRevisi.value.trim().length < 5) return

  mengirimRevisi.value = true
  galatPenawaran.value = null
  try {
    await revisiPenawaran(nomor, catatanRevisi.value.trim())
    catatanRevisi.value = ''
    revisiTerbuka.value = false
    await muat()
  } catch (e) {
    galatPenawaran.value = pesanError(e)
  } finally {
    mengirimRevisi.value = false
  }
}

/* ────────── Tip ────────── */
const PILIHAN_TIP = [5000, 10000, 20000, 50000]
const tipDipilih = ref<number | null>(null)
const tipTerbuka = ref(false)
const mengirimTip = ref(false)
const galatTip = ref<string | null>(null)

const tipTerkirim = computed(() => data.value?.tip ?? 0)
const bisaTip = computed(
  () => !!data.value?.tukang && ['tiba', 'penawaran', 'dikerjakan', 'selesai'].includes(tahap.value),
)

async function kirimTip() {
  if (!tipDipilih.value || mengirimTip.value) return

  mengirimTip.value = true
  galatTip.value = null
  try {
    await tipTukang(nomor, tipDipilih.value)
    tipDipilih.value = null
    await muat()
  } catch (e) {
    galatTip.value = pesanError(e)
  } finally {
    mengirimTip.value = false
  }
}

/* ────────── Penilaian ────────── */
const TAG_NILAI = ['Profesional', 'Cepat', 'Ramah', 'Harga transparan', 'Hasil rapi']
const bintang = ref(5)
const tagTerpilih = ref<string[]>([])
const ulasan = ref('')
const mengirimNilai = ref(false)
const galatNilai = ref<string | null>(null)

function toggleTag(t: string) {
  const i = tagTerpilih.value.indexOf(t)
  if (i >= 0) tagTerpilih.value.splice(i, 1)
  else tagTerpilih.value.push(t)
}

async function kirimNilai() {
  if (mengirimNilai.value) return

  mengirimNilai.value = true
  galatNilai.value = null
  try {
    await nilaiTukang(nomor, {
      bintang: bintang.value,
      tag: tagTerpilih.value,
      ulasan: ulasan.value.trim() || undefined,
    })
    await muat()
  } catch (e) {
    galatNilai.value = pesanError(e)
  } finally {
    mengirimNilai.value = false
  }
}

/* ────────── Batal ────────── */
const membatalkan = ref(false)
const galatBatal = ref<string | null>(null)

async function batal() {
  if (membatalkan.value) return

  membatalkan.value = true
  galatBatal.value = null
  try {
    await batalTukang(nomor)
    await muat()
  } catch (e) {
    galatBatal.value = pesanError(e)
  } finally {
    membatalkan.value = false
  }
}

/* ────────── Rincian biaya ────────── */
/**
 * Baris nota disusun dari apa yang BENAR-BENAR sudah masuk tagihan.
 *
 * Penawaran yang belum disetujui sengaja tidak ikut: menuliskannya di nota
 * membuat angka yang masih usulan terbaca sebagai tagihan.
 */
const baris = computed(() => {
  const d = data.value
  if (!d) return []

  const keluar: { label: string; nilai: number }[] = []
  if (d.biaya_kunjungan > 0) {
    keluar.push({ label: 'Kunjungan & pemeriksaan', nilai: d.biaya_kunjungan })
  }
  if (d.penawaran?.keputusan === 'disetujui') {
    for (const b of d.penawaran.baris) keluar.push({ label: b.nama, nilai: b.nilai })
  }
  if (d.tip > 0) keluar.push({ label: 'Tip tukang', nilai: d.tip })

  return keluar
})

const totalTagihan = computed(() => (data.value?.total ?? 0) + (data.value?.tip ?? 0))

onMounted(async () => {
  namaPenyetuju.value = auth.user?.name ?? ''
  await muat()
  if (!beres.value) pewaktu = setInterval(muat, 8000)
})

onBeforeUnmount(() => {
  if (pewaktu) clearInterval(pewaktu)
})
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-16">
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold">Pekerjaan</h1>
      </div>
    </header>

    <div v-if="memuat" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5 animate-pulse">
      <div class="h-24 rounded-2xl bg-(--color-surface-0)"></div>
      <div class="h-32 rounded-2xl bg-(--color-surface-0)"></div>
      <div class="h-40 rounded-2xl bg-(--color-surface-0)"></div>
    </div>

    <p
      v-else-if="galat && !data"
      role="alert"
      class="max-w-[430px] mx-auto px-4 pt-8 text-[13px] font-semibold text-(--color-error)"
    >
      {{ galat }}
    </p>

    <main v-else-if="data" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- ── Keadaan ── -->
      <section
        class="rounded-2xl p-5"
        :class="
          tahap === 'batal'
            ? 'bg-(--color-error-container) text-(--color-on-error-container)'
            : 'bg-(--color-azure) text-white'
        "
      >
        <h2 class="text-[17px] font-display font-extrabold leading-tight">{{ pita.judul }}</h2>
        <p class="mt-1 text-[12.5px] leading-snug opacity-90">{{ pita.keterangan }}</p>
        <p class="mt-3 text-[11px] opacity-80">
          {{ data.model_kerja === 'borongan' ? 'Permintaan survei' : 'Nomor pekerjaan' }}
          {{ data.nomor }}
        </p>
      </section>

      <!-- ── Tukang ── -->
      <section
        v-if="data.tukang"
        class="bg-(--color-surface-0) rounded-2xl p-4 flex items-center gap-3.5"
      >
        <AvatarTukang :nama="data.tukang.nama" class="w-14 h-14 shrink-0" />
        <div class="flex-1 min-w-0">
          <p class="text-[14px] font-extrabold leading-tight">{{ data.tukang.nama }}</p>
          <p class="mt-0.5 text-[11.5px] text-(--color-on-surface-variant)">
            {{ data.tukang.keahlian }} · {{ data.tukang.tahun }} tahun
          </p>
          <p class="mt-1.5 flex items-center gap-1.5 text-[11.5px]">
            <Icon name="star" class="w-3.5 h-3.5 text-(--color-gold)" />
            <span class="font-extrabold">{{ data.tukang.bintang }}</span>
            <span class="text-(--color-on-surface-variant)">
              {{ data.tukang.pekerjaan.toLocaleString('id-ID') }} pekerjaan
            </span>
          </p>
        </div>
        <span
          v-if="tahap === 'menuju' && data.tukang.tiba_menit > 0"
          class="shrink-0 rounded-full bg-(--color-secondary-container) text-(--color-on-secondary-container) px-2.5 py-1 text-[11px] font-bold"
        >
          {{ data.tukang.tiba_menit }} mnt
        </span>
      </section>

      <!-- ── Penawaran ── -->
      <section v-if="penawaran" class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-start justify-between gap-3">
          <div class="min-w-0">
            <h3 class="text-[15px] font-display font-extrabold leading-tight">
              {{ data.model_kerja === 'borongan' ? 'RAB pekerjaan' : 'Penawaran perbaikan' }}
            </h3>
            <p class="mt-0.5 text-[11.5px] text-(--color-on-surface-variant)">
              <template v-if="penawaran.keputusan === 'disetujui'">
                Disetujui {{ penawaran.nama_penyetuju }}
              </template>
              <template v-else-if="penawaran.kedaluwarsa">Sudah lewat masa berlaku</template>
              <template v-else-if="penawaran.berlaku_sampai">
                Berlaku sampai {{ tanggal(penawaran.berlaku_sampai) }}
              </template>
            </p>
          </div>
          <span
            v-if="penawaran.keputusan === 'disetujui'"
            class="shrink-0 rounded-full bg-(--color-secondary-container) text-(--color-on-secondary-container) px-2.5 py-1 text-[10.5px] font-bold"
          >
            Disetujui
          </span>
          <span
            v-else-if="penawaran.keputusan === 'revisi'"
            class="shrink-0 rounded-full bg-(--color-tertiary-container) text-(--color-on-tertiary-container) px-2.5 py-1 text-[10.5px] font-bold"
          >
            Menunggu revisi
          </span>
        </div>

        <div class="mt-4 flex flex-col gap-2 text-[13px]">
          <div v-for="b in penawaran.baris" :key="b.nama" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">{{ b.nama }}</span>
            <span class="font-semibold shrink-0">{{ rupiah(b.nilai) }}</span>
          </div>
        </div>
        <div
          class="mt-3 pt-3 border-t border-(--color-outline)/15 flex justify-between gap-3 items-baseline"
        >
          <span class="text-[14px] font-extrabold">Total penawaran</span>
          <span class="text-[16px] font-extrabold">{{ rupiah(penawaran.total) }}</span>
        </div>

        <p class="mt-3 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
          <template v-if="penawaran.material_ditanggung === 'pelanggan'">
            Material kamu sendiri yang menyiapkan, jadi tidak ikut ditagih di sini.
          </template>
          <template v-else>Harga material sudah termasuk; struk belanjanya ditunjukkan.</template>
          Bergaransi {{ penawaran.garansi_hari }} hari sejak pekerjaan selesai.
        </p>

        <!-- Menyetujui mengikat harga dan lingkup kerja, jadi namanya dituntut. -->
        <div v-if="bisaJawab" class="mt-4 pt-4 border-t border-(--color-outline)/15">
          <label class="block">
            <span class="text-[11.5px] font-semibold text-(--color-on-surface-variant)">
              Nama yang menyetujui
            </span>
            <input
              v-model="namaPenyetuju"
              type="text"
              autocomplete="name"
              class="mt-1 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-2.5 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none"
              placeholder="Nama lengkap"
            />
          </label>

          <button
            type="button"
            class="mt-3 w-full h-12 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold active:scale-[0.98] transition-transform disabled:opacity-40"
            :disabled="namaPenyetuju.trim().length < 2 || mengirimSetuju"
            @click="setujui"
          >
            {{ mengirimSetuju ? 'Mengirim…' : `Setujui & kerjakan · ${rupiah(penawaran.total)}` }}
          </button>

          <button
            type="button"
            class="mt-2 w-full h-11 rounded-full bg-(--color-surface-container) text-[13px] font-extrabold active:scale-[0.98] transition-transform"
            @click="revisiTerbuka = !revisiTerbuka"
          >
            Minta revisi
          </button>

          <div v-if="revisiTerbuka" class="mt-3">
            <textarea
              v-model="catatanRevisi"
              rows="3"
              class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
              placeholder="Bagian mana yang ingin diubah? Makin jelas, makin cepat penawaran barunya."
            ></textarea>
            <button
              type="button"
              class="mt-2 w-full h-11 rounded-full bg-(--color-on-surface) text-(--color-surface-0) text-[13px] font-extrabold active:scale-[0.98] transition-transform disabled:opacity-40"
              :disabled="catatanRevisi.trim().length < 5 || mengirimRevisi"
              @click="ajukanRevisi"
            >
              {{ mengirimRevisi ? 'Mengirim…' : 'Kirim permintaan revisi' }}
            </button>
            <!-- Ditulis apa adanya: yang dikirim permintaan, bukan harga baru. -->
            <p class="mt-2 text-[11px] leading-snug text-(--color-on-surface-variant)">
              Permintaanmu diteruskan ke tukang. Angkanya tidak berubah sampai dia menerbitkan
              penawaran baru.
            </p>
          </div>

          <p v-if="galatPenawaran" role="alert" class="mt-2 text-[11.5px] font-semibold text-(--color-error)">
            {{ galatPenawaran }}
          </p>
        </div>
      </section>

      <!-- ── Isi pekerjaan ── -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Yang dikerjakan</h3>
        <div class="flex flex-col gap-2 text-[13px]">
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Keahlian</span>
            <span class="font-semibold text-right">{{ data.nama_kategori }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Jenis masalah</span>
            <span class="font-semibold text-right">{{ data.sub_kategori }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Properti</span>
            <span class="font-semibold text-right capitalize">
              {{ data.tipe_properti }} · {{ data.lokasi_masalah }}
            </span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Material</span>
            <span class="font-semibold text-right">
              {{ data.penyediaan_material === 'tukang' ? 'Dibelikan tukang' : 'Disiapkan sendiri' }}
            </span>
          </div>
        </div>

        <p
          v-if="data.deskripsi_masalah"
          class="mt-3 pt-3 border-t border-(--color-outline)/15 text-[12.5px] leading-relaxed text-(--color-on-surface-variant)"
        >
          {{ data.deskripsi_masalah }}
        </p>

        <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex items-start gap-2.5">
          <Icon name="pin" class="w-4 h-4 mt-0.5 shrink-0 text-(--color-azure)" />
          <span class="text-[12.5px] leading-snug">{{ data.lokasi_alamat }}</span>
        </div>
      </section>

      <!-- ── Tip ── -->
      <section v-if="bisaTip" class="bg-(--color-surface-0) rounded-2xl p-4">
        <div class="flex items-start gap-3">
          <span class="w-9 h-9 rounded-full bg-amber-100 flex items-center justify-center shrink-0">
            <Icon name="sparkle" class="w-5 h-5 text-amber-500" />
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-[15px] font-display font-extrabold leading-tight">
              {{ tipTerkirim > 0 ? 'Terima kasih!' : 'Kasih tip buat tukang' }}
            </p>
            <p class="mt-0.5 text-[12px] leading-snug text-(--color-on-surface-variant)">
              <template v-if="tipTerkirim > 0">
                Tip {{ rupiah(tipTerkirim) }} sudah diteruskan ke tukang.
              </template>
              <template v-else>Diterima tukang seluruhnya, tanpa potongan.</template>
            </p>
          </div>
        </div>

        <button
          v-if="!tipTerbuka"
          type="button"
          class="mt-3.5 inline-flex items-center gap-2 h-11 pl-5 pr-4 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold active:scale-[0.97] transition-transform"
          @click="tipTerbuka = true"
        >
          {{ tipTerkirim > 0 ? 'Tambah tip' : 'Kasih tip' }}
          <Icon name="arrow-right" class="w-4.5 h-4.5 text-white" />
        </button>

        <div v-else class="mt-3.5">
          <div class="grid grid-cols-4 gap-2">
            <button
              v-for="n in PILIHAN_TIP"
              :key="n"
              type="button"
              class="px-1 py-2 rounded-full border text-center text-[12.5px] font-bold transition-colors disabled:opacity-40"
              :class="
                tipDipilih === n
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)/40 text-(--color-on-surface)'
              "
              :disabled="mengirimTip"
              @click="tipDipilih = n"
            >
              {{ rupiah(n) }}
            </button>
          </div>
          <button
            type="button"
            class="mt-3 w-full h-11 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold active:scale-[0.98] transition-transform disabled:opacity-40"
            :disabled="!tipDipilih || mengirimTip"
            @click="kirimTip"
          >
            {{
              mengirimTip ? 'Mengirim…' : tipDipilih ? `Kasih tip ${rupiah(tipDipilih)}` : 'Pilih nominal dulu'
            }}
          </button>
          <p v-if="galatTip" role="alert" class="mt-2 text-[11.5px] font-semibold text-(--color-error)">
            {{ galatTip }}
          </p>
          <p class="mt-2 text-[11px] leading-snug text-(--color-on-surface-variant)">
            Tip ditambahkan ke tagihan dan diteruskan utuh ke tukang.
          </p>
        </div>
      </section>

      <!-- ── Rincian biaya ── -->
      <section v-if="baris.length" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Rincian biaya</h3>
        <div class="flex flex-col gap-2 text-[13px]">
          <div v-for="b in baris" :key="b.label" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">{{ b.label }}</span>
            <span class="font-semibold shrink-0">{{ rupiah(b.nilai) }}</span>
          </div>
        </div>
        <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex justify-between gap-3">
          <span class="text-[14px] font-extrabold">Total</span>
          <span class="text-[16px] font-extrabold">{{ rupiah(totalTagihan) }}</span>
        </div>

        <!--
          Penawaran yang belum disetujui sengaja TIDAK ikut di nota ini: angka
          yang masih usulan tidak boleh terbaca sebagai tagihan.
        -->
        <p
          v-if="penawaran && penawaran.keputusan !== 'disetujui'"
          class="mt-3 text-[11.5px] leading-snug text-(--color-on-surface-variant)"
        >
          Penawaran {{ rupiah(penawaran.total) }} belum masuk tagihan — baru dihitung setelah kamu
          setujui.
        </p>
      </section>

      <!-- ── Penilaian ── -->
      <section v-if="tahap === 'selesai'" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[15px] font-display font-extrabold leading-tight">
          {{ data.penilaian ? 'Terima kasih atas penilaianmu' : 'Bagaimana hasil kerjanya?' }}
        </h3>

        <template v-if="data.penilaian">
          <p class="mt-2 flex items-center gap-1.5 text-[13px]">
            <Icon name="star" class="w-4 h-4 text-(--color-gold)" />
            <span class="font-extrabold">{{ data.penilaian.bintang }}/5</span>
          </p>
          <p
            v-if="data.penilaian.ulasan"
            class="mt-1.5 text-[12.5px] leading-relaxed text-(--color-on-surface-variant)"
          >
            {{ data.penilaian.ulasan }}
          </p>
        </template>

        <template v-else>
          <div class="mt-3 flex items-center gap-2">
            <button
              v-for="n in 5"
              :key="n"
              type="button"
              :aria-label="`${n} bintang`"
              class="active:scale-90 transition-transform"
              @click="bintang = n"
            >
              <Icon
                name="star"
                class="w-8 h-8"
                :class="n <= bintang ? 'text-(--color-gold)' : 'text-(--color-outline)/35'"
              />
            </button>
          </div>

          <div class="mt-3 flex flex-wrap gap-2">
            <button
              v-for="t in TAG_NILAI"
              :key="t"
              type="button"
              class="px-3 py-1.5 rounded-full border text-[12px] font-semibold transition-colors"
              :class="
                tagTerpilih.includes(t)
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)/30 text-(--color-on-surface-variant)'
              "
              @click="toggleTag(t)"
            >
              {{ t }}
            </button>
          </div>

          <textarea
            v-model="ulasan"
            rows="2"
            class="mt-3 w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
            placeholder="Ceritakan pengalamanmu (opsional)"
          ></textarea>

          <button
            type="button"
            class="mt-3 w-full h-12 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold active:scale-[0.98] transition-transform disabled:opacity-40"
            :disabled="mengirimNilai"
            @click="kirimNilai"
          >
            {{ mengirimNilai ? 'Mengirim…' : 'Kirim penilaian' }}
          </button>
          <p v-if="galatNilai" role="alert" class="mt-2 text-[11.5px] font-semibold text-(--color-error)">
            {{ galatNilai }}
          </p>
        </template>
      </section>

      <!-- ── Batal ── -->
      <section v-if="tahap === 'mencari'" class="pb-4">
        <button
          type="button"
          class="w-full h-12 rounded-full border-[1.5px] border-(--color-error)/60 text-(--color-error) text-[13.5px] font-extrabold active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="membatalkan"
          @click="batal"
        >
          {{ membatalkan ? 'Membatalkan…' : 'Batalkan pesanan' }}
        </button>
        <p v-if="galatBatal" role="alert" class="mt-2 text-[11.5px] font-semibold text-(--color-error)">
          {{ galatBatal }}
        </p>
        <p class="mt-2 text-[11.5px] leading-snug text-center text-(--color-on-surface-variant)">
          Belum ada tukang yang ditugaskan, jadi tidak ada biaya pembatalan.
        </p>
      </section>
    </main>
  </div>
</template>
