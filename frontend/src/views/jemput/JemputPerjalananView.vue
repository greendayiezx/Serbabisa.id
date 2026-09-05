<script setup lang="ts">
/**
 * BisaJemput — perjalanan berlangsung.
 *
 * Seluruh isi layar ditentukan `tahap` DARI SERVER. Tidak ada tahap yang
 * ditebak dari waktu berjalan, dan tidak ada kartu pengemudi yang ditampilkan
 * sebelum benar-benar ada pengemudi yang ditugaskan: nama yang muncul lalu
 * berganti karena ternyata orang lain yang menjemput lebih buruk daripada
 * menunggu dengan jujur.
 *
 * Tombol daruratnya sungguhan — menelepon 112 dan menyiapkan pesan berisi
 * titik terakhir beserta pelat nomor. Tombol darurat yang cuma membuka dialog
 * "hubungi CS" adalah janji keselamatan yang tidak ditepati, dan orang baru
 * mengetahuinya pada saat paling tidak tepat.
 */
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import JemputPerjalananSkeleton from '@/components/skeleton/JemputPerjalananSkeleton.vue'
import LottieIcon from '@/components/LottieIcon.vue'
import animasiMencari from '@/assets/lottie/jemput-mencari-pengemudi.json'
import {
  ambilPerjalanan,
  batalkanPerjalanan,
  nilaiPerjalanan,
  type Perjalanan,
} from '@/api/jemput'
import { pesanError } from '@/api/belanja'
import { JUDUL_TAHAP, TAG_PENILAIAN, rupiah } from '@/lib/jemput'

const route = useRoute()
const router = useRouter()
const kembali = useKembali()
const nomor = String(route.params.nomor ?? '')

const data = ref<Perjalanan | null>(null)
const memuat = ref(true)
const galat = ref<string | null>(null)
let pewaktu: ReturnType<typeof setInterval> | null = null

const tahap = computed(() => data.value?.tahap ?? 'mencari')
const judul = computed(() => JUDUL_TAHAP[tahap.value] ?? JUDUL_TAHAP.mencari)
const selesai = computed(() => tahap.value === 'selesai' || tahap.value === 'batal')

/* ────────── Penilaian ────────── */
const bintang = ref(0)
const tagDipilih = ref<string[]>([])
const ulasan = ref('')
const tip = ref(0)
const mengirimNilai = ref(false)
const galatNilai = ref<string | null>(null)

async function muat() {
  try {
    data.value = await ambilPerjalanan(nomor)
    if (selesai.value && pewaktu) {
      clearInterval(pewaktu)
      pewaktu = null
    }
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
}

onMounted(async () => {
  await muat()
  // Polling sederhana. Belum ada saluran dorong; enam detik cukup terasa hidup
  // tanpa membanjiri server, dan berhenti sendiri begitu perjalanan selesai.
  if (!selesai.value) pewaktu = setInterval(muat, 6000)
})

onBeforeUnmount(() => {
  if (pewaktu) clearInterval(pewaktu)
})

const bolehBatal = computed(() => ['mencari', 'dijemput', 'tiba'].includes(tahap.value))
const membatalkan = ref(false)

async function batal() {
  if (!bolehBatal.value || membatalkan.value) return
  membatalkan.value = true
  try {
    await batalkanPerjalanan(nomor)
    await muat()
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    membatalkan.value = false
  }
}

/** Pesan pelacakan untuk dikirim ke keluarga — isinya fakta, bukan tautan palsu. */
const pesanBagikan = computed(() => {
  const d = data.value
  if (!d) return ''
  const p = d.pengemudi
  return [
    'Saya sedang naik BisaJemput.',
    `Dari: ${d.jemput?.alamat ?? '-'}`,
    `Ke: ${d.tujuan?.alamat ?? '-'}`,
    p ? `Pengemudi: ${p.nama} · ${p.kendaraan} ${p.plat}` : 'Pengemudi belum ditugaskan.',
    `Nomor pesanan: ${d.nomor}`,
  ].join('\n')
})

async function bagikan() {
  const teks = pesanBagikan.value
  if (navigator.share) {
    try {
      await navigator.share({ title: 'Perjalanan BisaJemput', text: teks })
      return
    } catch {
      // Dibatalkan pengguna — jatuh ke WhatsApp di bawah.
    }
  }
  window.open(`https://wa.me/?text=${encodeURIComponent(teks)}`, '_blank', 'noopener')
}

function toggleTag(t: string) {
  const i = tagDipilih.value.indexOf(t)
  if (i >= 0) tagDipilih.value.splice(i, 1)
  else tagDipilih.value.push(t)
}

async function kirimNilai() {
  if (bintang.value < 1 || mengirimNilai.value) return
  mengirimNilai.value = true
  galatNilai.value = null
  try {
    await nilaiPerjalanan(nomor, {
      bintang: bintang.value,
      tag: tagDipilih.value,
      ulasan: ulasan.value || undefined,
      tip: tip.value || undefined,
    })
    await muat()
  } catch (e) {
    galatNilai.value = pesanError(e)
  } finally {
    mengirimNilai.value = false
  }
}
</script>

<template>
  <JemputPerjalananSkeleton v-if="memuat" />

  <div v-else class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-28">
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
        <h1 class="flex-1 text-left text-[16px] font-extrabold">Perjalanan</h1>
        <button
          v-if="data && !selesai"
          type="button"
          aria-label="Bagikan perjalanan"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="bagikan"
        >
          <Icon name="send" class="w-5 h-5" />
        </button>
      </div>
    </header>

    <!--
      Rantai v-if dimulai di sini. Sebelumnya rantainya diawali blok "memuat"
      yang kini digantikan skeleton di luar div ini — dan `v-else-if` yang
      kehilangan `v-if` pasangannya membuat SELURUH isi halaman tidak
      tergambar, tanpa satu pun galat muncul.
    -->
    <p
      v-if="galat && !data"
      role="alert"
      class="max-w-[430px] mx-auto px-4 pt-8 text-[13px] font-semibold text-(--color-error)"
    >
      {{ galat }}
    </p>

    <main v-else-if="data" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!--
        Mencari pengemudi: satu-satunya tahap yang berdiri sendiri sebagai
        layar, karena di sinilah orang menunggu tanpa tahu berapa lama. Radar
        yang berdenyut memberi tanda bahwa sistemnya masih bekerja — dan
        kalimat di bawahnya menyebut apa adanya bahwa belum ada yang ditugaskan
        dan belum ada yang ditagih.
      -->
      <section v-if="tahap === 'mencari'" class="bg-(--color-surface-0) rounded-2xl p-6 text-center">
        <LottieIcon :data="animasiMencari" :size="150" class="mx-auto" />
        <h2 class="mt-2 text-[17px] font-display font-extrabold leading-tight">
          Mencari pengemudi terdekat untuk kamu…
        </h2>
        <p class="mt-1.5 text-[12.5px] leading-relaxed text-(--color-on-surface-variant)">
          Belum ada pengemudi yang ditugaskan, dan kamu belum ditagih apa pun. Kalau tidak ada yang
          menerima, pesanan ini bisa dibatalkan tanpa biaya.
        </p>
        <p class="mt-3 text-[11.5px] text-(--color-on-surface-variant)">
          Nomor pesanan {{ data.nomor }}
        </p>
      </section>

      <!-- Tahap -->
      <section v-else class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-start gap-3">
          <span
            class="w-11 h-11 rounded-full flex items-center justify-center shrink-0"
            :class="
              tahap === 'selesai'
                ? 'bg-(--color-secondary-container)'
                : tahap === 'batal'
                  ? 'bg-(--color-error)/15'
                  : 'bg-(--color-primary-container)'
            "
          >
            <Icon
              :name="tahap === 'selesai' ? 'check-circle' : tahap === 'batal' ? 'x' : 'car'"
              class="w-6 h-6"
              :class="tahap === 'batal' ? 'text-(--color-error)' : 'text-(--color-azure)'"
            />
          </span>
          <div class="flex-1">
            <h2 class="text-[16px] font-display font-extrabold leading-tight">{{ judul.judul }}</h2>
            <p class="text-[12px] leading-snug text-(--color-on-surface-variant) mt-0.5">
              {{ judul.keterangan }}
            </p>
          </div>
        </div>

        <!-- Garis tahap -->
        <div v-if="tahap !== 'batal'" class="mt-4 flex items-center gap-1.5">
          <span
            v-for="(t, i) in ['mencari', 'dijemput', 'tiba', 'jalan', 'selesai']"
            :key="t"
            class="h-1.5 flex-1 rounded-full"
            :class="
              i <= ['mencari', 'dijemput', 'tiba', 'jalan', 'selesai'].indexOf(tahap)
                ? 'bg-(--color-azure)'
                : 'bg-(--color-outline)/20'
            "
          ></span>
        </div>

      </section>

      <!-- Pengemudi -->
      <section v-if="data.pengemudi" class="bg-(--color-surface-0) rounded-2xl p-5">
        <div class="flex items-center gap-3.5">
          <span
            class="w-14 h-14 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0"
          >
            <Icon name="user" class="w-7 h-7 text-(--color-on-surface-variant)" />
          </span>
          <div class="flex-1 min-w-0">
            <p class="text-[15px] font-display font-extrabold leading-tight">
              {{ data.pengemudi.nama }}
            </p>
            <p class="text-[12px] text-(--color-on-surface-variant) mt-0.5">
              ⭐ {{ data.pengemudi.bintang }} · {{ data.pengemudi.perjalanan.toLocaleString('id-ID') }}
              perjalanan
            </p>
          </div>
        </div>

        <div class="mt-3.5 rounded-xl bg-(--color-surface-container) p-3.5">
          <p class="text-[11px] text-(--color-on-surface-variant)">Cocokkan sebelum naik</p>
          <p class="text-[16px] font-extrabold tracking-wide mt-0.5">{{ data.pengemudi.plat }}</p>
          <p class="text-[12px] text-(--color-on-surface-variant)">
            {{ data.pengemudi.warna }} · {{ data.pengemudi.kendaraan }}
          </p>
        </div>

        <div class="mt-3 grid grid-cols-2 gap-2.5">
          <a
            :href="`tel:+62000000000`"
            class="h-11 rounded-full border-[1.5px] border-(--color-outline)/50 text-[13px] font-extrabold flex items-center justify-center gap-2 active:scale-95 transition-transform"
          >
            <Icon name="phone" class="w-4 h-4" /> Telepon
          </a>
          <button
            type="button"
            class="h-11 rounded-full border-[1.5px] border-(--color-outline)/50 text-[13px] font-extrabold flex items-center justify-center gap-2 active:scale-95 transition-transform"
            @click="router.push({ name: 'task-list' })"
          >
            <Icon name="chat" class="w-4 h-4" /> Chat
          </button>
        </div>

        <!-- Nomor pengemudi tidak pernah ditampilkan apa adanya di sini. -->
        <p
          v-if="data.pengemudi.telepon_tersamar"
          class="mt-2 text-[11px] leading-snug text-(--color-on-surface-variant)"
        >
          Panggilan lewat aplikasi. Nomor kamu dan pengemudi sama-sama disamarkan.
        </p>
      </section>

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
              <p class="text-[13px] font-semibold leading-snug">{{ data.jemput?.alamat }}</p>
              <p
                v-if="data.jemput?.catatan"
                class="text-[11.5px] text-(--color-on-surface-variant) mt-0.5"
              >
                {{ data.jemput.catatan }}
              </p>
            </div>
            <div>
              <p
                class="text-[11px] font-bold uppercase tracking-wider text-(--color-on-surface-variant)"
              >
                Tujuan
              </p>
              <p class="text-[13px] font-semibold leading-snug">{{ data.tujuan?.alamat }}</p>
            </div>
          </div>
        </div>

        <div
          class="mt-4 pt-4 border-t border-(--color-outline)/15 flex items-center justify-between gap-3 text-[12.5px]"
        >
          <span class="text-(--color-on-surface-variant)">
            {{ data.km?.toFixed(1).replace('.', ',') }} km · {{ data.menit }} menit
          </span>
          <span class="font-extrabold">{{ rupiah(data.total) }}</span>
        </div>
      </section>

      <!-- Keselamatan -->
      <section v-if="!selesai" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Keselamatan</h3>
        <div class="grid grid-cols-2 gap-2.5">
          <a
            href="tel:112"
            class="h-11 rounded-full bg-(--color-error) text-white text-[13px] font-extrabold flex items-center justify-center gap-2 active:scale-95 transition-transform"
          >
            <Icon name="shield" class="w-4 h-4" /> Darurat 112
          </a>
          <button
            type="button"
            class="h-11 rounded-full border-[1.5px] border-(--color-outline)/50 text-[13px] font-extrabold flex items-center justify-center gap-2 active:scale-95 transition-transform"
            @click="bagikan"
          >
            <Icon name="send" class="w-4 h-4" /> Bagikan
          </button>
        </div>
        <p class="mt-2.5 text-[11px] leading-snug text-(--color-on-surface-variant)">
          Tombol darurat menelepon 112 langsung. "Bagikan" mengirim titik jemput, tujuan, dan pelat
          nomor ke orang yang kamu pilih.
        </p>
      </section>

      <!-- Rincian tarif -->
      <section class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-3">Rincian tarif</h3>
        <div class="flex flex-col gap-2 text-[13px]">
          <div v-for="b in data.baris" :key="b.label" class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">{{ b.label }}</span>
            <span class="font-semibold">{{ rupiah(b.nilai) }}</span>
          </div>
          <div
            v-if="data.potongan > 0"
            class="flex justify-between gap-3 text-(--color-on-secondary-container)"
          >
            <span>Promo {{ data.promo?.kode }}</span>
            <span class="font-semibold">-{{ rupiah(data.potongan) }}</span>
          </div>
        </div>
        <div class="mt-3 pt-3 border-t border-(--color-outline)/15 flex justify-between gap-3">
          <span class="text-[14px] font-extrabold">Total</span>
          <span class="text-[16px] font-extrabold">{{ rupiah(data.total) }}</span>
        </div>
      </section>

      <!-- Penilaian -->
      <section v-if="tahap === 'selesai' && !data.penilaian" class="bg-(--color-surface-0) rounded-2xl p-5">
        <h3 class="text-[14px] font-display font-extrabold mb-1">Beri penilaian</h3>
        <p class="text-[11.5px] text-(--color-on-surface-variant) mb-3">
          Penilaianmu menentukan pengemudi mana yang tetap bisa menerima order.
        </p>

        <div class="flex items-center gap-2 mb-4">
          <button
            v-for="n in 5"
            :key="n"
            type="button"
            :aria-label="`${n} bintang`"
            class="w-11 h-11 rounded-full flex items-center justify-center active:scale-90 transition-transform"
            @click="bintang = n"
          >
            <Icon
              name="star"
              class="w-7 h-7"
              :class="n <= bintang ? 'text-(--color-azure)' : 'text-(--color-outline)/40'"
            />
          </button>
        </div>

        <div class="flex flex-wrap gap-2 mb-3">
          <button
            v-for="t in TAG_PENILAIAN"
            :key="t"
            type="button"
            class="px-3.5 py-2 rounded-full border text-[12px] font-semibold transition-colors"
            :class="
              tagDipilih.includes(t)
                ? 'bg-(--color-azure) border-(--color-azure) text-white'
                : 'border-(--color-outline)/50 text-(--color-on-surface-variant)'
            "
            :aria-pressed="tagDipilih.includes(t)"
            @click="toggleTag(t)"
          >
            {{ t }}
          </button>
        </div>

        <textarea
          v-model="ulasan"
          rows="2"
          placeholder="Ceritakan pengalamanmu (opsional)"
          class="w-full rounded-xl bg-(--color-surface-container) px-3.5 py-3 text-[13px] border-2 border-transparent focus:border-(--color-azure) outline-none resize-none"
        />

        <div class="mt-4">
          <p class="text-[12.5px] font-bold mb-2">Tip untuk pengemudi (opsional)</p>
          <div class="flex flex-wrap gap-2">
            <button
              v-for="n in [0, 5000, 10000, 20000]"
              :key="n"
              type="button"
              class="px-3.5 py-2 rounded-full border text-[12px] font-semibold transition-colors"
              :class="
                tip === n
                  ? 'bg-(--color-azure) border-(--color-azure) text-white'
                  : 'border-(--color-outline)/50 text-(--color-on-surface-variant)'
              "
              @click="tip = n"
            >
              {{ n === 0 ? 'Tanpa tip' : rupiah(n) }}
            </button>
          </div>
          <!-- Tip tidak dipotong komisi; ditulis supaya orang tahu ke mana perginya. -->
          <p class="mt-2 text-[11px] text-(--color-on-surface-variant)">
            Tip diterima pengemudi seluruhnya.
          </p>
        </div>

        <button
          type="button"
          class="mt-4 w-full h-12 rounded-full bg-(--color-azure) text-white text-[14px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform disabled:opacity-40"
          :disabled="bintang < 1 || mengirimNilai"
          @click="kirimNilai"
        >
          {{ mengirimNilai ? 'Mengirim…' : 'Kirim penilaian' }}
        </button>

        <p v-if="galatNilai" role="alert" class="mt-2 text-[12px] font-semibold text-(--color-error)">
          {{ galatNilai }}
        </p>
      </section>

      <section
        v-else-if="data.penilaian"
        class="bg-(--color-surface-0) rounded-2xl p-5 flex items-center gap-3"
      >
        <Icon name="check-circle" class="w-5 h-5 text-(--color-on-secondary-container) shrink-0" />
        <p class="text-[12.5px] leading-snug">
          Terima kasih, penilaian {{ data.penilaian.bintang }} bintang sudah terkirim.
          <span v-if="data.penilaian.tip > 0">
            Tip {{ rupiah(data.penilaian.tip) }} diteruskan ke pengemudi.
          </span>
        </p>
      </section>

      <button
        v-if="bolehBatal"
        type="button"
        class="w-full h-12 rounded-full border-[1.5px] border-(--color-error)/60 text-(--color-error) text-[13.5px] font-extrabold active:scale-[0.98] transition-transform disabled:opacity-40"
        :disabled="membatalkan"
        @click="batal"
      >
        {{ membatalkan ? 'Membatalkan…' : 'Batalkan perjalanan' }}
      </button>

      <p
        v-if="galat"
        role="alert"
        class="text-[12px] font-semibold text-(--color-error) text-center"
      >
        {{ galat }}
      </p>
    </main>
  </div>
</template>
