<script setup lang="ts">
/**
 * Laporan pekerjaan Disinfektan.
 *
 * Dua keadaan, dan keduanya ditentukan DATA DI SERVER:
 *
 * 1. Petugas belum menutup pekerjaannya → yang tampil status dan jadwalnya
 *    saja. Tidak ada produk, waktu kontak, atau foto "sesudah" yang dikarang
 *    sebelum ada yang mengerjakannya.
 * 2. Laporan sudah ada → produk yang dipakai beserta WAKTU KONTAK DARI LABEL
 *    PRODUK ITU, area yang dikerjakan, lama ventilasi, dan foto sebelum
 *    sesudah.
 *
 * Waktu kontaknya baru muncul di sini, bukan di katalog, dan itu inti dari
 * seluruh halaman ini: angkanya milik produk yang benar-benar dipakai di
 * lokasi. Satu angka di katalog akan salah untuk sebagian besar produk lain.
 *
 * Halaman ini juga mengulang batas layanannya di bagian bawah. Laporan yang
 * hanya berisi centang hijau mudah dibaca sebagai "ruangan sekarang steril" —
 * dan itu justru yang tidak boleh dipercaya orang.
 */
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useKembali } from '@/composables/useKembali'
import Icon from '@/components/icons/Icon.vue'
import PemuatBerputar from '@/components/ui/PemuatBerputar.vue'
import { ambilLaporanDisinfektan, type LaporanDisinfektan } from '@/api/disinfektan'
import { pesanError } from '@/api/belanja'
import { PROPERTI } from '@/lib/bersih/disinfektan'

const route = useRoute()
const kembali = useKembali()
const nomor = String(route.params.nomor ?? '')

const data = ref<LaporanDisinfektan | null>(null)
const memuat = ref(true)
const galat = ref<string | null>(null)

const laporan = computed(() => data.value?.laporan ?? null)
const namaProperti = computed(
  () => PROPERTI.find((p) => p.id === data.value?.properti)?.nama ?? data.value?.properti ?? '',
)

const BULAN = [
  'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
  'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
]

function tanggalJam(iso: string | null): string {
  if (!iso) return '—'
  const t = new Date(iso)
  const jam = String(t.getHours()).padStart(2, '0')
  const menit = String(t.getMinutes()).padStart(2, '0')
  return `${t.getDate()} ${BULAN[t.getMonth()]} ${t.getFullYear()}, ${jam}.${menit}`
}

function jam(iso: string | null): string {
  if (!iso) return '—'
  const t = new Date(iso)
  return `${String(t.getHours()).padStart(2, '0')}.${String(t.getMinutes()).padStart(2, '0')}`
}

onMounted(async () => {
  try {
    data.value = await ambilLaporanDisinfektan(nomor)
  } catch (e) {
    galat.value = pesanError(e)
  } finally {
    memuat.value = false
  }
})

/*
 * Menyimpan lewat dialog cetak peramban, bukan endpoint PDF sendiri.
 *
 * Halamannya sudah punya seluruh isinya; menambah pembuat PDF di server berarti
 * satu tata letak lagi yang harus ikut diperbarui tiap kali laporan berubah,
 * dan yang paling sering terlupakan diperbarui justru bagian batas layanannya.
 */
function simpan() {
  window.print()
}
</script>

<template>
  <div class="min-h-dvh w-full bg-(--color-surface-container) text-(--color-on-surface) pb-24">
    <header
      class="sticky top-0 z-30 bg-(--color-surface-0) border-b border-(--color-outline)/10 cetak-sembunyi"
    >
      <div class="max-w-[430px] mx-auto h-14 px-4 flex items-center gap-2">
        <button
          type="button"
          aria-label="Kembali"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="kembali"
        >
          <Icon name="arrow-left" class="w-5 h-5" />
        </button>
        <h1 class="flex-1 text-left text-[16px] font-extrabold">Laporan Pekerjaan</h1>
        <button
          v-if="laporan"
          type="button"
          aria-label="Simpan laporan"
          class="w-10 h-10 rounded-full flex items-center justify-center shrink-0 active:scale-95 transition-transform"
          @click="simpan"
        >
          <Icon name="receipt" class="w-5 h-5" />
        </button>
      </div>
    </header>

    <div v-if="memuat" class="pt-24 flex justify-center">
      <PemuatBerputar />
    </div>

    <p
      v-else-if="galat"
      role="alert"
      class="max-w-[430px] mx-auto px-4 pt-8 text-[13px] font-semibold text-(--color-error)"
    >
      {{ galat }}
    </p>

    <main v-else-if="data" class="max-w-[430px] mx-auto px-4 pt-4 flex flex-col gap-3.5">
      <!-- ══════ Belum ada laporan ══════ -->
      <template v-if="!laporan">
        <section class="bg-(--color-surface-0) rounded-2xl p-6 text-center">
          <span
            class="w-14 h-14 rounded-full bg-(--color-surface-container) flex items-center justify-center mx-auto mb-4"
          >
            <Icon name="clock" class="w-7 h-7 text-(--color-on-surface-variant)" />
          </span>
          <h2 class="text-[17px] font-display font-extrabold mb-1.5">Laporan belum tersedia</h2>
          <p class="text-[12.5px] leading-relaxed text-(--color-on-surface-variant)">
            Laporannya ditulis petugas setelah pekerjaan selesai. Di situlah tercatat produk yang
            dipakai beserta waktu kontaknya — angka itu ikut produknya, jadi baru bisa disebut
            setelah petugas memakainya di lokasi.
          </p>
        </section>

        <section class="bg-(--color-surface-0) rounded-2xl p-5 flex flex-col gap-2 text-[13px]">
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Nomor pesanan</span>
            <span class="font-bold">{{ data.nomor }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant)">Jadwal</span>
            <span class="font-bold text-right">{{ tanggalJam(data.dijadwalkan_pada) }}</span>
          </div>
          <div class="flex justify-between gap-3">
            <span class="text-(--color-on-surface-variant) shrink-0">Lokasi</span>
            <span class="font-bold text-right leading-snug">{{ data.alamat }}</span>
          </div>
        </section>
      </template>

      <!-- ══════ Laporan sudah ada ══════ -->
      <template v-else>
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <div class="flex items-start gap-3">
            <span
              class="w-11 h-11 rounded-full bg-(--color-secondary-container) flex items-center justify-center shrink-0"
            >
              <Icon name="check-circle" class="w-6 h-6 text-(--color-on-secondary-container)" />
            </span>
            <div class="flex-1">
              <h2 class="text-[17px] font-display font-extrabold leading-tight">
                Pekerjaan selesai
              </h2>
              <p class="text-[12px] text-(--color-on-surface-variant) mt-0.5">
                {{ tanggalJam(laporan.selesai_pada) }}
              </p>
            </div>
          </div>

          <div class="mt-4 pt-4 border-t border-(--color-outline)/15 flex flex-col gap-2 text-[13px]">
            <div class="flex justify-between gap-3">
              <span class="text-(--color-on-surface-variant)">Nomor laporan</span>
              <span class="font-bold">{{ laporan.nomor }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-(--color-on-surface-variant)">Pesanan</span>
              <span class="font-bold">{{ data.nomor }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-(--color-on-surface-variant)">Petugas</span>
              <span class="font-bold">{{ laporan.petugas }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-(--color-on-surface-variant) shrink-0">Area</span>
              <span class="font-bold text-right leading-snug">
                {{ namaProperti }} · {{ data.luas }} m² · {{ data.ruangan }} ruangan
              </span>
            </div>
          </div>
        </section>

        <!--
          Produk ditaruh sebelum daftar area, bukan sesudahnya. Inilah bagian
          yang menjelaskan kenapa waktu kontaknya sekian, dan tanpa itu daftar
          area hanya jadi centang-centang.
        -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <h3 class="text-[14px] font-display font-extrabold mb-3">Produk yang dipakai</h3>

          <p class="text-[13.5px] font-bold leading-snug">{{ laporan.produk.nama }}</p>

          <div class="mt-3 flex flex-col gap-2 text-[13px]">
            <div class="flex justify-between gap-3">
              <span class="text-(--color-on-surface-variant)">Bahan aktif</span>
              <span class="font-bold text-right">{{ laporan.produk.bahan_aktif }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-(--color-on-surface-variant)">Konsentrasi</span>
              <span class="font-bold">{{ laporan.produk.konsentrasi }}</span>
            </div>
            <div class="flex justify-between gap-3">
              <span class="text-(--color-on-surface-variant)">Nomor izin edar</span>
              <span class="font-bold">{{ laporan.produk.registrasi ?? '—' }}</span>
            </div>
          </div>

          <div class="mt-4 rounded-xl bg-(--color-primary-container)/40 p-4">
            <p class="text-[11.5px] font-bold uppercase tracking-wider text-(--color-azure)">
              Waktu kontak
            </p>
            <p class="text-[19px] font-display font-extrabold mt-0.5">
              {{ laporan.produk.waktu_kontak }}
            </p>
            <p class="text-[11.5px] leading-relaxed text-(--color-on-surface-variant) mt-1.5">
              Angka ini berasal dari label produk di atas, bukan dari ketentuan umum. Produk lain
              punya waktu kontaknya sendiri.
            </p>
          </div>

          <p
            v-if="laporan.produk.catatan"
            class="mt-3 text-[11.5px] leading-relaxed text-(--color-on-surface-variant)"
          >
            {{ laporan.produk.catatan }}
          </p>
        </section>

        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <h3 class="text-[14px] font-display font-extrabold mb-2">Cara pengerjaan</h3>
          <p class="text-[12.5px] leading-relaxed text-(--color-on-surface-variant)">
            {{ laporan.metode }}
          </p>

          <div class="mt-4 pt-4 border-t border-(--color-outline)/15 grid grid-cols-2 gap-3">
            <div class="rounded-xl bg-(--color-surface-container) p-3.5">
              <p class="text-[11px] text-(--color-on-surface-variant)">Ventilasi</p>
              <p class="text-[15px] font-extrabold mt-0.5">{{ laporan.ventilasi_menit }} menit</p>
            </div>
            <div class="rounded-xl bg-(--color-surface-container) p-3.5">
              <p class="text-[11px] text-(--color-on-surface-variant)">Aman dimasuki</p>
              <p class="text-[15px] font-extrabold mt-0.5">
                {{ jam(laporan.aman_dimasuki_pada) }}
              </p>
            </div>
          </div>

          <p
            v-if="laporan.catatan"
            class="mt-3 text-[12px] leading-relaxed text-(--color-on-surface-variant)"
          >
            {{ laporan.catatan }}
          </p>
        </section>

        <section class="bg-(--color-surface-0) rounded-2xl p-5">
          <h3 class="text-[14px] font-display font-extrabold mb-3">Area yang dikerjakan</h3>
          <ul class="grid grid-cols-2 gap-x-3 gap-y-2">
            <li
              v-for="a in laporan.area_dikerjakan"
              :key="a"
              class="flex items-start gap-1.5 text-[12px] leading-snug"
            >
              <Icon
                name="check"
                class="w-3.5 h-3.5 shrink-0 mt-0.5 text-(--color-on-secondary-container)"
              />
              {{ a }}
            </li>
          </ul>
        </section>

        <!-- Foto sebelum & sesudah -->
        <section
          v-if="laporan.sebelum.length || laporan.sesudah.length"
          class="bg-(--color-surface-0) rounded-2xl p-5"
        >
          <h3 class="text-[14px] font-display font-extrabold mb-3">Foto</h3>

          <div
            v-for="kel in [
              { judul: 'Sebelum', daftar: laporan.sebelum },
              { judul: 'Sesudah', daftar: laporan.sesudah },
            ]"
            :key="kel.judul"
            class="mb-4 last:mb-0"
          >
            <p class="text-[11.5px] font-bold uppercase tracking-wider text-(--color-on-surface-variant) mb-2">
              {{ kel.judul }}
            </p>

            <div v-if="kel.daftar.length" class="grid grid-cols-3 gap-2">
              <figure v-for="f in kel.daftar" :key="f.jalur" class="min-w-0">
                <img
                  :src="f.url"
                  :alt="f.label"
                  loading="lazy"
                  class="aspect-square w-full rounded-xl object-cover bg-(--color-surface-container)"
                />
                <figcaption class="mt-1 text-[10px] leading-tight text-(--color-on-surface-variant)">
                  {{ f.label }}
                </figcaption>
              </figure>
            </div>

            <!--
              Dikatakan apa adanya, bukan disembunyikan. Bagian foto yang
              menghilang begitu saja terbaca seperti tidak pernah dijanjikan.
            -->
            <p v-else class="text-[11.5px] text-(--color-on-surface-variant)">
              Tidak ada foto {{ kel.judul.toLowerCase() }} yang dilampirkan.
            </p>
          </div>
        </section>

        <!--
          Diulang di halaman laporan, bukan hanya di halaman layanan. Laporan
          yang berisi centang hijau saja mudah dibaca sebagai "ruangan sekarang
          steril", dan itu justru kesimpulan yang salah.
        -->
        <section class="bg-(--color-surface-0) rounded-2xl p-5 flex gap-3">
          <Icon name="info" class="w-5 h-5 shrink-0 mt-0.5 text-(--color-azure)" />
          <p class="text-[11.5px] leading-relaxed text-(--color-on-surface-variant)">
            Laporan ini mencatat pekerjaan yang dilakukan, bukan hasil pengujian mikrobiologi.
            Disinfeksi permukaan
            <strong class="text-(--color-on-surface)">bukan sterilisasi</strong> dan tidak menjamin
            ruangan bebas virus. Permukaan bisa terkontaminasi lagi segera setelah kembali
            disentuh.
          </p>
        </section>

        <button
          type="button"
          class="cetak-sembunyi w-full h-12 rounded-full border-[1.5px] border-(--color-outline)/50 text-[14px] font-extrabold flex items-center justify-center gap-2 active:scale-[0.98] transition-transform"
          @click="simpan"
        >
          <Icon name="receipt" class="w-4 h-4" />
          Simpan atau cetak laporan
        </button>
      </template>
    </main>
  </div>
</template>

<style scoped>
/* Yang tidak ikut tercetak: tombol dan navigasi, karena di kertas keduanya
   hanya jadi kotak kosong. */
@media print {
  .cetak-sembunyi {
    display: none !important;
  }
}
</style>
