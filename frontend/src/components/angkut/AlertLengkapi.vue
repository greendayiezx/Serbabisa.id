<script setup lang="ts">
/**
 * Pop-up "Lengkapi dulu" untuk BisaAngkut.
 *
 * Muncul dari bawah layar lalu berhenti di tengah — gerakan naik itu yang
 * menarik perhatian ke pesan yang menghalangi pengguna melanjutkan.
 *
 * Isinya MENYEBUT apa yang benar-benar kurang, bukan kalimat tetap. Pengguna
 * yang cuma lupa mengisi berat tidak perlu membaca daftar semua isian.
 */
import { computed, onBeforeUnmount, ref, useId, watch } from 'vue'

const props = defineProps<{
  tampil: boolean
  /** Nama isian yang masih kosong, mis. ['berat', 'tanggal']. */
  kurang: string[]
}>()

const emit = defineEmits<{ tutup: [] }>()

/**
 * ID gradien & filter bersifat global untuk seluruh dokumen. Tanpa awalan unik,
 * SVG lain dengan id yang sama akan saling menimpa warnanya.
 */
const uid = useId()
const idBg = `${uid}-alert-bg`
const idLime = `${uid}-alert-lime`
const idShadow = `${uid}-alert-shadow`
const idKartu = `${uid}-alert-kartu`

/**
 * Animasi SMIL tidak bisa dimatikan lewat CSS, jadi elemen <animate>-nya yang
 * tidak dirender sama sekali ketika sistem meminta pengurangan gerak.
 */
const kurangiGerak = ref(false)
let mq: MediaQueryList | null = null

if (typeof window !== 'undefined' && window.matchMedia) {
  mq = window.matchMedia('(prefers-reduced-motion: reduce)')
  kurangiGerak.value = mq.matches
  const ubah = (e: MediaQueryListEvent) => (kurangiGerak.value = e.matches)
  mq.addEventListener('change', ubah)
  onBeforeUnmount(() => mq?.removeEventListener('change', ubah))
}

const bergerak = computed(() => !kurangiGerak.value)

/* ---------------- Isi pesan ---------------- */

/** "a, b dan c" — pemisah terakhir memakai "dan", bukan koma. */
function rangkai(daftar: string[]): string {
  if (daftar.length <= 1) return daftar[0] ?? ''
  return daftar.slice(0, -1).join(', ') + ' dan ' + daftar[daftar.length - 1]
}

/**
 * Pecah jadi dua baris teks SVG.
 *
 * <text> tidak membungkus sendiri, jadi pemenggalannya dihitung di sini. Batas
 * 42 karakter diambil dari lebar ruang teks (244px) pada ukuran 10,5px.
 */
function duaBaris(teks: string, batas = 42): [string, string] {
  if (teks.length <= batas) return [teks, '']

  const kata = teks.split(' ')
  let satu = ''
  let i = 0
  while (i < kata.length && (satu + kata[i]).length <= batas) {
    satu += (satu ? ' ' : '') + kata[i]
    i++
  }

  let dua = kata.slice(i).join(' ')
  // Baris kedua yang masih kepanjangan dipotong daripada meluber keluar kartu.
  if (dua.length > batas) dua = dua.slice(0, batas - 1).trimEnd() + '…'

  return [satu || teks.slice(0, batas), dua]
}

const baris = computed(() => {
  const isi = props.kurang.length
    ? `${rangkai(props.kurang)} masih kosong.`
    : 'Masih ada isian yang belum lengkap.'

  return duaBaris(isi.charAt(0).toUpperCase() + isi.slice(1))
})

/* ---------------- Menutup ---------------- */

/**
 * Pop-up ini TIDAK menutup sendiri.
 *
 * Yang menghalangi pengguna melanjutkan adalah isian yang kosong, jadi pesannya
 * harus bertahan sampai dibaca dan ditutup sendiri. Pesan yang lenyap sebelum
 * sempat dibaca sama saja dengan tidak muncul.
 *
 * Tidak ada timer apa pun di sini. Yang ada hanya PENJAGA di bawah — kebalikan
 * dari penutup otomatis.
 */

/**
 * Ketukan pada tombol "Lanjut" bisa menghasilkan `click` susulan yang datang
 * terlambat (ghost click, ~300ms di sebagian browser ponsel). Kalau susulan itu
 * jatuh ke lapisan pop-up yang baru muncul, pop-up langsung tertutup lagi —
 * terlihat seolah hilang dengan sendirinya.
 *
 * Karena itu ketukan diabaikan selama jeda pendek setelah pop-up terbuka.
 * Jeda ini hanya menahan penutupan, tidak pernah memicunya.
 */
const JEDA_AMAN_MS = 450
let dibukaPada = 0

function tutup(paksa = false) {
  if (!paksa && performance.now() - dibukaPada < JEDA_AMAN_MS) return
  emit('tutup')
}

// Esc adalah niat yang jelas dari pengguna, bukan sisa peristiwa sentuhan.
function onEsc(e: KeyboardEvent) {
  if (e.key === 'Escape') tutup(true)
}

/* ---------------- Kunci scroll ---------------- */

/**
 * Selama pop-up tampil, halaman di belakangnya tidak boleh ikut bergulir.
 *
 * `overflow: hidden` saja tidak cukup di Safari iOS — halaman tetap bisa
 * digulirkan dengan sentuhan. Karena itu body dibekukan dengan `position:
 * fixed` sambil menyimpan posisi gulirnya, lalu dikembalikan persis ke sana
 * saat ditutup; tanpa itu, halaman melompat ke atas begitu pop-up hilang.
 */
let scrollY = 0
let terkunci = false

function kunciScroll() {
  if (terkunci) return
  scrollY = window.scrollY
  const b = document.body.style
  b.position = 'fixed'
  b.top = `-${scrollY}px`
  b.left = '0'
  b.right = '0'
  b.width = '100%'
  b.overflow = 'hidden'
  terkunci = true
}

function lepasScroll() {
  if (!terkunci) return
  const b = document.body.style
  b.position = ''
  b.top = ''
  b.left = ''
  b.right = ''
  b.width = ''
  b.overflow = ''
  terkunci = false
  window.scrollTo(0, scrollY)
}

watch(
  () => props.tampil,
  (v) => {
    if (v) {
      dibukaPada = performance.now()
      document.addEventListener('keydown', onEsc)
      kunciScroll()
      return
    }

    document.removeEventListener('keydown', onEsc)
    lepasScroll()
  },
)

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onEsc)
  // Komponen bisa hilang saat pop-up masih terbuka (mis. pindah halaman);
  // kalau kuncinya tidak dilepas, seluruh aplikasi jadi tidak bisa di-scroll.
  lepasScroll()
})
</script>

<template>
  <Teleport to="body">
    <Transition name="naik">
      <!--
        Ketukan di mana pun menutup pop-up — kartunya, latarnya, atau ruang
        kosong di sekitarnya. `touchmove` ditahan supaya gerakan menggulir di
        atas lapisan ini tidak diteruskan ke halaman di belakangnya.
      -->
      <div
        v-if="tampil"
        class="fixed inset-0 z-[70] flex items-center justify-center px-5 overscroll-contain"
        @click="tutup()"
        @touchmove.prevent
      >
        <!-- Latar peredup: menegaskan halaman di belakang sedang tidak aktif. -->
        <div class="tirai absolute inset-0 bg-black/45"></div>

        <div
          class="kartu relative w-full max-w-[360px]"
          role="alertdialog"
          aria-modal="true"
          :aria-label="`Lengkapi dulu. ${baris[0]} ${baris[1]}`"
          @click="tutup()"
        >
          <svg viewBox="0 0 320 104" class="w-full h-auto block" xmlns="http://www.w3.org/2000/svg">
            <defs>
              <linearGradient :id="idBg" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#0A326B" />
                <stop offset="55%" stop-color="#167FE8" />
                <stop offset="100%" stop-color="#3BBEB8" />
              </linearGradient>

              <linearGradient :id="idLime" x1="0" y1="0" x2="1" y2="1">
                <stop offset="0%" stop-color="#DFFF72" />
                <stop offset="100%" stop-color="#8BC53F" />
              </linearGradient>

              <filter :id="idShadow" x="-20%" y="-30%" width="140%" height="170%">
                <feDropShadow dx="0" dy="4" stdDeviation="5" flood-color="#062A5A" flood-opacity=".25" />
              </filter>

              <!--
                Lingkaran hias di pojok kanan atas lebih besar daripada kartunya,
                jadi tanpa klip ia meluber melewati sudut membulat dan sudutnya
                terlihat patah.
              -->
              <clipPath :id="idKartu">
                <rect x="3" y="3" width="314" height="98" rx="16" />
              </clipPath>
            </defs>

            <!-- Badan kartu -->
            <rect
              x="3" y="3" width="314" height="98" rx="16"
              :fill="`url(#${idBg})`"
              :filter="`url(#${idShadow})`"
            />

            <!-- Cahaya hias -->
            <circle cx="300" cy="12" r="38" fill="#C8FF00" opacity=".08" :clip-path="`url(#${idKartu})`">
              <template v-if="bergerak">
                <animate attributeName="r" values="34;42;34" dur="3s" repeatCount="indefinite" />
                <animate attributeName="opacity" values=".05;.14;.05" dur="3s" repeatCount="indefinite" />
              </template>
            </circle>

            <!-- Ikon peringatan -->
            <g transform="translate(18 30)">
              <circle cx="20" cy="20" r="20" fill="#FFFFFF" opacity=".16" />
              <path d="M20 8 L34 32 H6 Z" fill="#F4C542" />
              <path d="M20 14 V23" stroke="#FFFFFF" stroke-width="3" stroke-linecap="round" />
              <circle cx="20" cy="28" r="2" fill="#FFFFFF" />

              <circle cx="20" cy="20" r="20" fill="none" stroke="#C8FF00" stroke-width="2" opacity="0">
                <template v-if="bergerak">
                  <animate attributeName="r" values="20;27;20" dur="2s" repeatCount="indefinite" />
                  <animate attributeName="opacity" values=".5;0;.5" dur="2s" repeatCount="indefinite" />
                </template>
              </circle>
            </g>

            <!-- Judul & pesan: memakai huruf brand, bukan Arial bawaan SVG -->
            <text x="66" y="40" class="judul" fill="#FFFFFF">Lengkapi dulu</text>
            <text x="66" y="61" class="pesan" fill="#F4FBFF">{{ baris[0] }}</text>
            <text v-if="baris[1]" x="66" y="76" class="pesan" fill="#F4FBFF">{{ baris[1] }}</text>

            <!-- Bilah kemajuan: penanda bahwa pesan ini menutup sendiri -->
            <g transform="translate(66 88)">
              <rect x="0" y="0" width="72" height="4" rx="2" fill="#FFFFFF" opacity=".2" />
              <rect x="0" y="0" width="18" height="4" rx="2" fill="#C8FF00">
                <animate
                  v-if="bergerak"
                  attributeName="width"
                  values="18;36;18"
                  dur="2s"
                  repeatCount="indefinite"
                />
              </rect>
            </g>

            <!-- Aksen lime -->
            <rect x="292" y="72" width="5" height="18" rx="2.5" :fill="`url(#${idLime})`">
              <animate
                v-if="bergerak"
                attributeName="height"
                values="12;20;12"
                dur="1.6s"
                repeatCount="indefinite"
              />
            </rect>
          </svg>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<style scoped>
.judul {
  font-family: var(--font-display, inherit);
  font-size: 14px;
  font-weight: 800;
}

.pesan {
  font-family: inherit;
  font-size: 10.5px;
  font-weight: 500;
}

/* Naik dari bawah layar lalu berhenti di tengah. */
.naik-enter-active .kartu {
  transition: transform 0.42s cubic-bezier(0.16, 1, 0.3, 1), opacity 0.24s ease-out;
}
.naik-leave-active .kartu {
  transition: transform 0.24s ease-in, opacity 0.2s ease-in;
}
.naik-enter-from .kartu,
.naik-leave-to .kartu {
  transform: translateY(60vh);
  opacity: 0;
}

.naik-enter-active .tirai,
.naik-leave-active .tirai {
  transition: opacity 0.28s ease;
}
.naik-enter-from .tirai,
.naik-leave-to .tirai {
  opacity: 0;
}

/* Tanpa gerak: muncul dan hilang begitu saja, tanpa meluncur. */
@media (prefers-reduced-motion: reduce) {
  .naik-enter-active .kartu,
  .naik-leave-active .kartu {
    transition: opacity 0.15s ease;
  }
  .naik-enter-from .kartu,
  .naik-leave-to .kartu {
    transform: none;
  }
}
</style>
