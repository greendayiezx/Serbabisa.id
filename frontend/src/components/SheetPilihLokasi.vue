<script setup lang="ts">
/**
 * Lembar "Pilih lokasi" — empat jalan menuju satu titik.
 *
 * 1. Cari alamat        — mengetik nama tempat atau jalan.
 * 2. Lokasimu saat ini  — GPS perangkat, dibaca saat itu juga.
 * 3. Pilih lewat peta   — membuka PemilihLokasi, peta yang sama dengan menu
 *                         lain: pin biru yang bisa diseret dan pencariannya.
 * 4. Alamat favorit     — yang sudah pernah disimpan pengguna.
 *
 * Lembar ini TIDAK menyimpan apa pun sendiri: titik yang dipilih dikirim lewat
 * `pilih`, dan halaman pemanggil yang memutuskan mau diapakan. Dengan begitu ia
 * bisa dipakai di menu mana pun tanpa tahu alur pemesanannya.
 */
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import Icon from '@/components/icons/Icon.vue'
import PemilihLokasi from '@/components/PemilihLokasi.vue'
import { useLocationStore } from '@/stores/location'
import { cariLokasi, labelKoordinat, reverseGeocode, type HasilLokasi } from '@/lib/geocode'

const props = withDefaults(
  defineProps<{
    tampil: boolean
    /** Titik awal saat peta dibuka. */
    lat?: number
    lng?: number
    alamat?: string
    judulPeta?: string
  }>(),
  {
    lat: -6.2088,
    lng: 106.8456,
    alamat: '',
    judulPeta: 'Set lokasi servis',
  },
)

const emit = defineEmits<{
  tutup: []
  pilih: [{ alamat: string; lat: number; lng: number }]
}>()

const router = useRouter()
const locationStore = useLocationStore()

/* ────────── Alamat favorit ────────── */
const LABEL_FAVORIT: Record<string, { nama: string; ikon: string }> = {
  home: { nama: 'Rumah', ikon: 'home' },
  office: { nama: 'Kantor', ikon: 'business' },
}

const favorit = computed(() => {
  const tersimpan = locationStore.loadSavedPlaces()
  return Object.entries(tersimpan)
    .filter(([, v]) => !!v)
    .map(([kunci, v]) => ({
      kunci,
      nama: LABEL_FAVORIT[kunci]?.nama ?? kunci,
      ikon: LABEL_FAVORIT[kunci]?.ikon ?? 'pin',
      alamat: v!.alamat,
      lat: v!.lat,
      lng: v!.lng,
    }))
})

/* ────────── Cari alamat ────────── */
const kata = ref('')
const hasil = ref<HasilLokasi[]>([])
const mencari = ref(false)
let jeda: ReturnType<typeof setTimeout> | null = null

/*
 * Diberi jeda: memanggil geocoder tiap ketikan akan diblokir penyedianya dan
 * menghabiskan kuota. 400 ms cukup untuk menunggu satu kata selesai diketik.
 */
watch(kata, (nilai) => {
  if (jeda) clearTimeout(jeda)
  const q = nilai.trim()

  if (q.length < 3) {
    hasil.value = []
    mencari.value = false
    return
  }

  mencari.value = true
  jeda = setTimeout(async () => {
    try {
      hasil.value = await cariLokasi(q, { lat: props.lat, lng: props.lng })
    } catch {
      hasil.value = []
    } finally {
      mencari.value = false
    }
  }, 400)
})

/* ────────── Lokasi saat ini ────────── */
const mencariGps = ref(false)
const galatGps = ref<string | null>(null)

function lokasiSaatIni() {
  if (mencariGps.value) return

  if (!navigator.geolocation) {
    galatGps.value = 'Perangkat ini tidak mendukung pencarian lokasi.'
    return
  }

  mencariGps.value = true
  galatGps.value = null

  navigator.geolocation.getCurrentPosition(
    async (pos) => {
      const { latitude, longitude } = pos.coords
      /*
       * Koordinatnya sudah benar walau pembacaan alamatnya gagal — jadi
       * kegagalan geocoding tidak boleh membatalkan pilihan, cukup diganti
       * label koordinat.
       */
      let alamat: string
      try {
        alamat = await reverseGeocode(latitude, longitude)
      } catch {
        alamat = labelKoordinat(latitude, longitude)
      }

      mencariGps.value = false
      pilihTitik({ alamat, lat: latitude, lng: longitude })
    },
    (e) => {
      mencariGps.value = false
      galatGps.value =
        e.code === e.PERMISSION_DENIED
          ? 'Izin lokasi ditolak. Aktifkan dulu di pengaturan peramban.'
          : 'Lokasi belum bisa dibaca. Coba lagi atau pilih lewat peta.'
    },
    { enableHighAccuracy: true, timeout: 10_000 },
  )
}

/* ────────── Peta ────────── */
const petaTampil = ref(false)

function terimaDariPeta(l: { alamat: string; lat: number; lng: number }) {
  petaTampil.value = false
  pilihTitik(l)
}

function pilihTitik(l: { alamat: string; lat: number; lng: number }) {
  kata.value = ''
  hasil.value = []
  galatGps.value = null
  emit('pilih', l)
}

function lihatSemua() {
  emit('tutup')
  router.push({ name: 'task-location' })
}

// Sisa ketikan dan galat dari sesi sebelumnya tidak dibawa saat lembar dibuka
// lagi — yang terlihat harus menggambarkan keadaan sekarang.
watch(
  () => props.tampil,
  (buka) => {
    if (buka) return
    kata.value = ''
    hasil.value = []
    galatGps.value = null
    petaTampil.value = false
  },
)
</script>

<template>
  <Teleport to="body">
    <div v-if="tampil" class="fixed inset-0 z-[70] flex items-end md:items-center md:justify-center">
      <div class="absolute inset-0 bg-black/45" @click="emit('tutup')"></div>

      <div
        class="relative w-full md:w-[430px] max-h-[88dvh] bg-(--color-surface-0) rounded-t-[28px] md:rounded-[28px] flex flex-col shadow-(--shadow-float)"
      >
        <div
          class="w-10 h-1.5 bg-(--color-outline) rounded-full mx-auto mt-3 mb-1 shrink-0 md:hidden"
        ></div>

        <div class="px-5 pt-3 pb-4 shrink-0">
          <h2 class="text-[19px] font-display font-extrabold mb-4">Pilih lokasi</h2>

          <!-- Cari alamat -->
          <div
            class="flex items-center gap-3 rounded-2xl bg-(--color-surface-container) px-3.5 py-3"
          >
            <span
              class="w-6 h-6 rounded-full bg-(--color-warning, #f97316) flex items-center justify-center shrink-0"
              style="background: #f2711c"
            >
              <span class="w-2 h-2 rounded-full bg-white"></span>
            </span>
            <input
              v-model="kata"
              type="text"
              placeholder="Cari alamat"
              class="flex-1 min-w-0 bg-transparent text-[13.5px] outline-none placeholder:text-(--color-on-surface-variant)"
            />
            <button
              v-if="kata"
              type="button"
              aria-label="Kosongkan"
              class="shrink-0 w-6 h-6 rounded-full flex items-center justify-center active:scale-90 transition-transform"
              @click="kata = ''"
            >
              <Icon name="x" class="w-3.5 h-3.5 text-(--color-on-surface-variant)" />
            </button>
          </div>

          <!-- Dua jalan pintas -->
          <div class="mt-3 flex items-center gap-3">
            <button
              type="button"
              class="flex-1 min-w-0 flex items-center gap-2 px-3.5 py-2.5 rounded-full border border-(--color-outline)/50 active:scale-95 transition-transform disabled:opacity-50"
              :disabled="mencariGps"
              @click="lokasiSaatIni"
            >
              <Icon name="crosshair" class="w-4 h-4 shrink-0 text-(--color-azure)" />
              <span class="text-[12.5px] font-bold truncate">
                {{ mencariGps ? 'Membaca lokasi…' : 'Lokasimu saat ini' }}
              </span>
            </button>

            <button
              type="button"
              class="flex-1 min-w-0 flex items-center gap-2 px-3.5 py-2.5 rounded-full border border-(--color-outline)/50 active:scale-95 transition-transform"
              @click="petaTampil = true"
            >
              <Icon name="map" class="w-4 h-4 shrink-0 text-(--color-azure)" />
              <span class="text-[12.5px] font-bold truncate">Pilih lewat peta</span>
            </button>
          </div>

          <p v-if="galatGps" role="alert" class="mt-2 text-[11.5px] font-semibold text-(--color-error)">
            {{ galatGps }}
          </p>
        </div>

        <div class="flex-1 min-h-0 overflow-y-auto border-t border-(--color-outline)/15">
          <!-- Hasil pencarian menggantikan daftar favorit selama ada ketikan -->
          <div v-if="kata.trim().length >= 3" class="px-5 py-4">
            <p v-if="mencari" class="text-[12.5px] text-(--color-on-surface-variant)">
              Mencari&hellip;
            </p>

            <p
              v-else-if="!hasil.length"
              class="text-[12.5px] text-(--color-on-surface-variant) leading-snug"
            >
              Tidak ada yang cocok. Coba kata lain, atau tandai titiknya lewat peta.
            </p>

            <div v-else class="flex flex-col">
              <button
                v-for="(h, i) in hasil"
                :key="`${h.lat}-${h.lng}-${i}`"
                type="button"
                class="flex items-start gap-3 py-3 text-left border-b border-(--color-outline)/10 last:border-b-0 active:opacity-70"
                @click="pilihTitik({ alamat: h.label, lat: h.lat, lng: h.lng })"
              >
                <Icon name="pin" class="w-4.5 h-4.5 shrink-0 mt-0.5 text-(--color-on-surface-variant)" />
                <span class="min-w-0">
                  <span class="block text-[13.5px] font-bold truncate">{{ h.nama }}</span>
                  <span class="block text-[11.5px] text-(--color-on-surface-variant) leading-snug">
                    {{ h.alamat }}
                  </span>
                </span>
              </button>
            </div>
          </div>

          <!-- Alamat favorit -->
          <div v-else class="px-5 py-4">
            <div class="flex items-center justify-between gap-3 mb-3">
              <h3 class="text-[15px] font-display font-extrabold">Alamat favorit</h3>
              <button
                type="button"
                class="shrink-0 px-4 py-2 rounded-full bg-(--color-secondary-container) text-(--color-on-secondary-container) text-[12.5px] font-extrabold active:scale-95 transition-transform"
                @click="lihatSemua"
              >
                Lihat semua
              </button>
            </div>

            <p
              v-if="!favorit.length"
              class="text-[12.5px] text-(--color-on-surface-variant) leading-snug"
            >
              Belum ada alamat tersimpan. Tandai titiknya lewat peta, lalu simpan dari halaman
              lokasi supaya lain kali tinggal ketuk.
            </p>

            <div v-else class="flex flex-col gap-2.5">
              <button
                v-for="f in favorit"
                :key="f.kunci"
                type="button"
                class="w-full text-left rounded-2xl border border-(--color-outline)/30 p-4 active:scale-[0.99] transition-transform"
                @click="pilihTitik({ alamat: f.alamat, lat: f.lat, lng: f.lng })"
              >
                <span class="flex items-center gap-2 mb-1.5">
                  <Icon :name="f.ikon" class="w-4.5 h-4.5 text-(--color-on-surface-variant)" />
                  <span class="text-[15px] font-display font-extrabold">{{ f.nama }}</span>
                </span>
                <span class="block text-[12px] text-(--color-on-surface-variant) leading-snug">
                  {{ f.alamat }}
                </span>
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </Teleport>

  <!--
    Peta yang sama dengan menu lain, bukan salinannya: satu gerakan yang
    dipelajari pengguna berlaku di seluruh aplikasi, dan pencariannya hanya
    perlu diperbaiki di satu tempat.
  -->
  <PemilihLokasi
    :tampil="petaTampil"
    :alamat="alamat"
    :lat="lat || -6.2088"
    :lng="lng || 106.8456"
    :judul="judulPeta"
    label-cari="Cari nama gedung atau alamat"
    @tutup="petaTampil = false"
    @pilih="terimaDariPeta"
  />
</template>
