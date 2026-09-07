<script setup lang="ts">
/**
 * BisaTukang — beranda layanan.
 *
 * Layar ini menjawab satu pertanyaan sebelum yang lain: pekerjaanmu ini
 * dihitung per jam atau diborong? Bedanya bukan besar-kecilnya, melainkan CARA
 * HARGANYA DITENTUKAN — dan itu menentukan seluruh alur berikutnya. Ganti kran
 * tidak perlu menunggu survei; renovasi total tidak bisa ditarifkan per jam.
 * Menanyakannya di sini menghemat satu percakapan yang biasanya baru terjadi
 * setelah tukang berdiri di depan pintu.
 *
 * Kategori, keunggulan, dan profil tukang di bawahnya melayani pertanyaan
 * kedua: apa yang bisa dikerjakan, dan kenapa harus lewat sini.
 */
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import { useKembali } from '@/composables/useKembali'
import { useLocationStore } from '@/stores/location'
import {
  useTukangStore,
  KEUNGGULAN_TUKANG,
  MODEL_KERJA,
  TUKANG_PILIHAN,
  type ModelKerja,
} from '@/stores/tukang'
import { katalogTukang, type KategoriTukang } from '@/api/tukang'
import { pesanError } from '@/api/belanja'
import BisaTukangHeroArt from '@/components/tukang/BisaTukangHeroArt.vue'
import IkonKategoriTukang from '@/components/tukang/IkonKategoriTukang.vue'
import AvatarTukang from '@/components/tukang/AvatarTukang.vue'
import { heroTimeOfDayFromHour } from '@/lib/heroSky'

const router = useRouter()
const kembali = useKembali()
const locationStore = useLocationStore()
const tukangStore = useTukangStore()

const alamat = computed(() => locationStore.draft?.alamat ?? 'Alamat belum dipilih')

/* Langit hero mengikuti jam nyata, dibaca sekali saat halaman dibuka. */
const heroTimeOfDay = heroTimeOfDayFromHour(new Date().getHours())

/*
 * Kategori dipotong lima dulu.
 *
 * Sembilan kartu sekaligus membuat halamannya terbaca sebagai daftar, bukan
 * pilihan; lima cukup untuk menunjukkan cakupannya, sisanya dibuka yang memang
 * mencari.
 */
const CUPLIK = 5
const semuaKategori = ref(false)

const kategori = ref<KategoriTukang[]>([])
const galatKatalog = ref<string | null>(null)

const kategoriTampil = computed(() =>
  semuaKategori.value ? kategori.value : kategori.value.slice(0, CUPLIK),
)

onMounted(async () => {
  try {
    kategori.value = (await katalogTukang()).kategori
  } catch (e) {
    galatKatalog.value = pesanError(e)
  }
})

/** Model kerja dipilih DI SINI, lalu alur pemesanan tinggal meneruskannya. */
function pesan(model: ModelKerja['id']) {
  tukangStore.modelKerja = model
  router.push({ name: 'task-tukang-pesan' })
}

/** Ketuk kategori: sekalian menetapkan layanan yang dicari. */
function pesanKategori(id: string) {
  tukangStore.setCategory(id)
  pesan('harian')
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-32">
    <!-- ── Kepala ── -->
    <header
      class="sticky top-0 z-40 bg-(--color-surface-0)/90 backdrop-blur-md border-b border-(--color-outline)/12"
    >
      <div class="max-w-[430px] mx-auto px-4 h-14 flex items-center gap-3">
        <button
          type="button"
          aria-label="Kembali"
          class="w-9 h-9 rounded-full bg-(--color-surface-container) flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <div class="flex-1 min-w-0">
          <h1 class="text-[15px] font-display font-extrabold leading-tight text-(--color-azure)">
            BisaTukang
          </h1>
          <p class="text-[11px] text-(--color-on-surface-variant) truncate">{{ alamat }}</p>
        </div>
      </div>
    </header>

    <main class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-6">
      <!-- ── Hero ── -->
      <section
        class="relative h-[236px] rounded-3xl overflow-hidden shadow-[0_12px_32px_rgba(10,26,58,0.2)] bg-[#0a1a3a]"
      >
        <BisaTukangHeroArt :time-of-day="heroTimeOfDay" class="absolute inset-0 w-full h-full" />
        <div
          class="absolute inset-x-0 bottom-0 h-4/5 bg-gradient-to-t from-[#050e22] via-[#050e22]/88 to-transparent pointer-events-none"
        ></div>

        <div class="absolute inset-x-5 bottom-5 z-10 text-white">
          <span
            class="inline-flex items-center gap-1.5 rounded-full bg-white/15 backdrop-blur-sm px-2.5 py-1 text-[10.5px] font-bold"
          >
            <Icon name="shield" class="w-3.5 h-3.5" />
            Tukang terverifikasi &amp; bergaransi
          </span>
          <h2 class="mt-2 text-[22px] leading-[1.15] font-extrabold font-display">
            Perbaikan rumah tanpa ribet
          </h2>
          <p class="mt-1.5 text-[12.5px] leading-snug text-white/80">
            Tukang datang, masalahnya diperiksa dulu, harganya kamu setujui sebelum dikerjakan.
          </p>
          <!--
            Di tempat ini rancangannya memasang angka "10.000 rumah selesai".
            Belum ada yang menghitungnya, jadi yang ditulis di sini cara
            kerjanya — yang memang benar dan sama meyakinkannya.
          -->
          <div
            class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] font-semibold text-white/75"
          >
            <span class="flex items-center gap-1">
              <Icon name="search" class="w-3.5 h-3.5" />
              Diperiksa dulu
            </span>
            <span class="flex items-center gap-1">
              <Icon name="wallet" class="w-3.5 h-3.5" />
              Harga disetujui
            </span>
            <span class="flex items-center gap-1">
              <Icon name="shield" class="w-3.5 h-3.5" />
              Bergaransi
            </span>
          </div>
        </div>
      </section>

      <!-- ── Dua model kerja ── -->
      <section>
        <h3 class="text-[16px] font-display font-extrabold leading-tight">Pilih model kerja</h3>
        <p class="mt-0.5 text-[12px] text-(--color-on-surface-variant)">
          Sesuaikan dengan skala pekerjaannya.
        </p>

        <div class="mt-3 flex flex-col gap-3">
          <div
            v-for="m in MODEL_KERJA"
            :key="m.id"
            class="rounded-3xl bg-(--color-surface-0) border border-(--color-outline)/15 shadow-sm p-4"
          >
            <div class="flex items-start justify-between gap-3">
              <span
                class="w-12 h-12 rounded-2xl flex items-center justify-center shrink-0"
                :class="
                  m.id === 'harian'
                    ? 'bg-(--color-azure)/10 text-(--color-azure)'
                    : 'bg-(--color-secondary-container) text-(--color-on-secondary-container)'
                "
              >
                <Icon :name="m.id === 'harian' ? 'clock' : 'wrench'" class="w-6 h-6" />
              </span>
              <span
                class="rounded-full px-2.5 py-1 text-[10.5px] font-extrabold uppercase tracking-wide shrink-0"
                :class="
                  m.id === 'harian'
                    ? 'bg-(--color-lime) text-(--color-on-surface)'
                    : 'bg-(--color-secondary-container) text-(--color-on-secondary-container)'
                "
              >
                {{ m.lencana }}
              </span>
            </div>

            <h4 class="mt-3 text-[17px] font-display font-extrabold leading-tight">{{ m.nama }}</h4>
            <p class="mt-1 text-[12.5px] leading-snug text-(--color-on-surface-variant)">
              {{ m.ringkas }}
            </p>

            <ul class="mt-3 flex flex-col gap-1.5">
              <li
                v-for="c in m.cakupan"
                :key="c"
                class="flex items-start gap-2 text-[12.5px] leading-snug"
              >
                <Icon
                  name="check-circle"
                  class="w-4 h-4 mt-px shrink-0"
                  :class="
                    m.id === 'harian'
                      ? 'text-(--color-azure)'
                      : 'text-(--color-on-secondary-container)'
                  "
                />
                <span>{{ c }}</span>
              </li>
            </ul>

            <button
              type="button"
              class="mt-4 w-full h-11 rounded-full text-[13.5px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
              :class="
                m.id === 'harian'
                  ? 'bg-(--color-azure) text-white'
                  : 'bg-(--color-on-surface) text-(--color-surface-0)'
              "
              @click="pesan(m.id)"
            >
              {{ m.ajakan }}
              <Icon :name="m.id === 'harian' ? 'arrow-right' : 'calendar'" class="w-4.5 h-4.5" />
            </button>
          </div>
        </div>
      </section>

      <!-- ── Kategori ── -->
      <section>
        <div class="flex items-end justify-between gap-3">
          <div class="min-w-0">
            <h3 class="text-[16px] font-display font-extrabold leading-tight">Kategori tukang</h3>
            <p class="mt-0.5 text-[12px] text-(--color-on-surface-variant)">
              Spesialis untuk tiap jenis kerusakan.
            </p>
          </div>
          <button
            v-if="kategori.length > CUPLIK"
            type="button"
            class="shrink-0 text-[12.5px] font-extrabold text-(--color-azure) flex items-center gap-0.5"
            @click="semuaKategori = !semuaKategori"
          >
            {{ semuaKategori ? 'Ringkas' : 'Lihat semua' }}
            <Icon
              name="chevron-right"
              class="w-4 h-4 transition-transform"
              :class="semuaKategori ? 'rotate-90' : ''"
            />
          </button>
        </div>

        <p
          v-if="galatKatalog"
          role="alert"
          class="mt-3 rounded-2xl bg-(--color-error-container) text-(--color-on-error-container) px-4 py-3 text-[12.5px] font-semibold"
        >
          {{ galatKatalog }}
        </p>

        <!-- Rangka selama katalog belum sampai; daftar kosong terbaca
             seolah tidak ada layanannya sama sekali. -->
        <div v-else-if="!kategori.length" class="mt-3 grid grid-cols-2 gap-2.5 animate-pulse">
          <div v-for="n in 4" :key="n" class="h-28 rounded-2xl bg-(--color-surface-0)"></div>
        </div>

        <div v-else class="mt-3 grid grid-cols-2 gap-2.5">
          <button
            v-for="(k, i) in kategoriTampil"
            :key="k.id"
            type="button"
            class="text-left p-3.5 rounded-2xl bg-(--color-surface-0) border border-(--color-outline)/15 active:scale-[0.98] transition-transform"
            :class="
              i === kategoriTampil.length - 1 && kategoriTampil.length % 2 === 1 ? 'col-span-2' : ''
            "
            @click="pesanKategori(k.id)"
          >
            <span
              class="w-10 h-10 rounded-xl bg-(--color-surface-container) flex items-center justify-center p-1.5"
            >
              <IkonKategoriTukang :id="k.id" />
            </span>
            <span class="mt-2.5 block text-[13px] font-extrabold leading-tight">{{ k.nama }}</span>
            <span
              class="mt-1 block text-[11.5px] leading-snug text-(--color-on-surface-variant) line-clamp-2"
            >
              {{ k.deskripsi }}
            </span>
          </button>
        </div>
      </section>

      <!-- ── Kenapa BisaTukang ── -->
      <section class="rounded-3xl bg-(--color-surface-0) border border-(--color-outline)/15 p-5">
        <h3 class="text-[16px] font-display font-extrabold leading-tight text-center">
          Kenapa lewat BisaTukang?
        </h3>
        <p class="mt-1 text-[12px] text-center text-(--color-on-surface-variant)">
          Yang bikin tenang bukan janjinya, tapi urutannya.
        </p>

        <div class="mt-4 grid grid-cols-2 gap-3">
          <div
            v-for="u in KEUNGGULAN_TUKANG"
            :key="u.judul"
            class="rounded-2xl bg-(--color-surface-container) p-3.5 text-center"
          >
            <span
              class="w-11 h-11 mx-auto rounded-full bg-(--color-surface-0) flex items-center justify-center text-(--color-azure)"
            >
              <Icon :name="u.ikon" class="w-5.5 h-5.5" />
            </span>
            <p class="mt-2.5 text-[12.5px] font-extrabold leading-tight">{{ u.judul }}</p>
            <p class="mt-1 text-[11px] leading-snug text-(--color-on-surface-variant)">
              {{ u.ringkas }}
            </p>
          </div>
        </div>
      </section>

      <!-- ── Tukang pilihan ── -->
      <section>
        <h3 class="text-[16px] font-display font-extrabold leading-tight">Tukang pilihan</h3>
        <p class="mt-0.5 text-[12px] text-(--color-on-surface-variant)">
          Mitra dengan rating tertinggi di sekitarmu.
        </p>

        <div class="mt-3 flex flex-col gap-2.5">
          <div
            v-for="t in TUKANG_PILIHAN"
            :key="t.nama"
            class="rounded-2xl bg-(--color-surface-0) border border-(--color-outline)/15 p-4 flex items-center gap-3.5"
          >
            <AvatarTukang :nama="t.nama" class="w-14 h-14 shrink-0" />
            <div class="flex-1 min-w-0">
              <p class="flex items-center gap-1.5 text-[13.5px] font-extrabold leading-tight">
                <span class="truncate">{{ t.nama }}</span>
                <Icon name="check-circle" class="w-4 h-4 shrink-0 text-(--color-azure)" />
              </p>
              <p class="mt-0.5 text-[11.5px] leading-snug text-(--color-on-surface-variant)">
                {{ t.keahlian }} · {{ t.tahun }} tahun
              </p>
              <p class="mt-1.5 flex items-center gap-1.5 text-[11.5px]">
                <Icon name="star" class="w-3.5 h-3.5 text-(--color-gold)" />
                <span class="font-extrabold">{{ t.bintang }}</span>
                <span class="text-(--color-on-surface-variant)">
                  ({{ t.ulasan.toLocaleString('id-ID') }} ulasan)
                </span>
              </p>
            </div>
            <span
              class="shrink-0 rounded-full bg-(--color-secondary-container) text-(--color-on-secondary-container) px-2.5 py-1 text-[10.5px] font-bold"
            >
              Siap
            </span>
          </div>
        </div>

        <!--
          Ditulis apa adanya: profil di atas belum berasal dari data mitra
          sungguhan. Rating dan jumlah ulasan karangan terbaca persis seperti
          yang asli, dan itu justru yang membuatnya menyesatkan.
        -->
        <p class="mt-2.5 text-[11px] leading-snug text-(--color-on-surface-variant)">
          Profil di atas masih contoh; daftar mitra sungguhan menyusul.
        </p>
      </section>
    </main>

    <!-- ── Bilah aksi ── -->
    <div
      class="fixed bottom-0 inset-x-0 z-40 bg-(--color-surface-0)/95 backdrop-blur-md border-t border-(--color-outline)/12"
    >
      <div
        class="max-w-[430px] mx-auto px-4 py-3 pb-[calc(0.75rem+env(safe-area-inset-bottom))] flex items-center gap-2.5"
      >
        <button
          type="button"
          class="flex-1 h-12 rounded-full bg-(--color-azure) text-white text-[13.5px] font-extrabold active:scale-[0.98] transition-transform"
          @click="pesan('harian')"
        >
          Pesan tukang sekarang
        </button>
        <button
          type="button"
          class="shrink-0 h-12 px-4 rounded-full bg-(--color-surface-container) text-[13px] font-extrabold active:scale-[0.98] transition-transform"
          @click="pesan('borongan')"
        >
          Survei
        </button>
      </div>
    </div>
  </div>
</template>
