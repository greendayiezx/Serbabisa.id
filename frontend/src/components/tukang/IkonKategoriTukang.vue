<script setup lang="ts">
/**
 * Ikon kategori BisaTukang — digambar sendiri, dua warna.
 *
 * Sebelumnya kategorinya memakai nama glyph Material Symbols. Satu di antaranya,
 * `hammer`, TIDAK ADA di kumpulan itu — dan ligatur yang tidak ketemu jatuh
 * kembali menjadi teksnya sendiri, jadi kartu "Bangunan & Renovasi" menampilkan
 * tulisan HAMMER melintang selebar kartu. Tidak ada galat, tidak ada kotak
 * kosong; hurufnya memang tergambar rapi. Yang seperti itu cuma ketahuan kalau
 * layarnya dilihat.
 *
 * Digambar sebagai SVG, bukan diganti nama glyph lain, karena ikon di sini
 * adalah wajah menunya: bentuk yang konsisten dan dua warna merek memberi kesan
 * satu keluarga, sementara glyph font selalu satu warna dan berbeda bobot dari
 * ikon aplikasi lainnya.
 */
withDefaults(defineProps<{ id: string; aktif?: boolean }>(), { aktif: false })
</script>

<template>
  <svg
    viewBox="0 0 32 32"
    class="w-full h-full"
    fill="none"
    :aria-hidden="true"
    stroke-linecap="round"
    stroke-linejoin="round"
  >
    <!-- Listrik: petir di dalam soket -->
    <g v-if="id === 'listrik'">
      <rect x="6" y="4" width="20" height="24" rx="5" :fill="aktif ? '#ffffff26' : '#1e9bf01f'" />
      <path
        d="M17.5 9 L11.5 18 h4 l-1 5 6-9 h-4 z"
        :fill="aktif ? '#FFD54A' : '#1e9bf0'"
      />
    </g>

    <!-- Pipa: tetesan dan sambungan pipa -->
    <g v-else-if="id === 'pipa'">
      <path
        d="M16 5c4 5.5 6.5 8.7 6.5 12a6.5 6.5 0 0 1-13 0C9.5 13.7 12 10.5 16 5z"
        :fill="aktif ? '#ffffff26' : '#1e9bf01f'"
      />
      <path
        d="M16 22.5a5 5 0 0 1-3.4-4"
        :stroke="aktif ? '#ffffff' : '#1e9bf0'"
        stroke-width="2"
      />
      <circle cx="16" cy="14" r="2" :fill="aktif ? '#FFD54A' : '#8BC53F'" />
    </g>

    <!-- AC: unit indoor dengan embusan angin -->
    <g v-else-if="id === 'ac'">
      <rect x="4" y="7" width="24" height="9" rx="3" :fill="aktif ? '#ffffff26' : '#1e9bf01f'" />
      <path d="M7 13.5h18" :stroke="aktif ? '#ffffff' : '#1e9bf0'" stroke-width="1.8" />
      <g :stroke="aktif ? '#FFD54A' : '#8BC53F'" stroke-width="2">
        <path d="M10 20c0 2.5 2 2.5 2 5" />
        <path d="M16 20c0 2.5 2 2.5 2 5" />
        <path d="M22 20c0 2.5 2 2.5 2 5" />
      </g>
    </g>

    <!-- Bangunan: palu, ikon yang dulu jadi tulisan HAMMER -->
    <g v-else-if="id === 'bangunan'">
      <path
        d="M7 22.5 18 11.5l3 3L10 25.5a2.1 2.1 0 0 1-3-3z"
        :fill="aktif ? '#ffffff26' : '#1e9bf01f'"
        :stroke="aktif ? '#ffffff' : '#1e9bf0'"
        stroke-width="1.8"
      />
      <path
        d="M17.5 6.5c2.6-1.7 5.6-1.3 7.6.7l2.4 2.4-3 3-1.6-1.6-3 3-3.4-3.4 3-3-1.6-1.6z"
        :fill="aktif ? '#FFD54A' : '#8BC53F'"
      />
    </g>

    <!-- Pintu & kunci -->
    <g v-else-if="id === 'pintu'">
      <rect x="7" y="4" width="14" height="24" rx="2.5" :fill="aktif ? '#ffffff26' : '#1e9bf01f'" />
      <path d="M7 28h18" :stroke="aktif ? '#ffffff' : '#1e9bf0'" stroke-width="2" />
      <circle cx="17.5" cy="16" r="1.6" :fill="aktif ? '#FFD54A' : '#8BC53F'" />
      <path
        d="M24 14v-2a2.5 2.5 0 0 1 5 0v2"
        :stroke="aktif ? '#FFD54A' : '#8BC53F'"
        stroke-width="1.8"
      />
      <rect x="22.5" y="14" width="8" height="6.5" rx="1.8" :fill="aktif ? '#FFD54A' : '#8BC53F'" />
    </g>

    <!-- Cat & dinding: rol cat -->
    <g v-else-if="id === 'cat'">
      <rect x="5" y="5" width="17" height="7" rx="2.2" :fill="aktif ? '#ffffff26' : '#1e9bf01f'" />
      <path
        d="M22 8.5h3a2 2 0 0 1 2 2v2a2 2 0 0 1-2 2h-8v3"
        :stroke="aktif ? '#ffffff' : '#1e9bf0'"
        stroke-width="1.8"
      />
      <rect x="13" y="17.5" width="4" height="10" rx="2" :fill="aktif ? '#FFD54A' : '#8BC53F'" />
    </g>

    <!-- Elektronik: televisi -->
    <g v-else-if="id === 'elektronik'">
      <rect x="4" y="7" width="24" height="15" rx="3" :fill="aktif ? '#ffffff26' : '#1e9bf01f'" />
      <path d="M12 26h8" :stroke="aktif ? '#ffffff' : '#1e9bf0'" stroke-width="2" />
      <path d="M16 22v4" :stroke="aktif ? '#ffffff' : '#1e9bf0'" stroke-width="2" />
      <path
        d="M11 11.5l4 3-4 3z"
        :fill="aktif ? '#FFD54A' : '#8BC53F'"
      />
    </g>

    <!-- Renovasi: rumah dengan tanda tambah -->
    <g v-else-if="id === 'renovasi'">
      <path
        d="M6 15 16 6.5 26 15v11a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2z"
        :fill="aktif ? '#ffffff26' : '#1e9bf01f'"
        :stroke="aktif ? '#ffffff' : '#1e9bf0'"
        stroke-width="1.8"
      />
      <path
        d="M16 15.5v7M12.5 19h7"
        :stroke="aktif ? '#FFD54A' : '#8BC53F'"
        stroke-width="2.2"
      />
    </g>

    <!-- Lainnya: kunci inggris -->
    <g v-else>
      <path
        d="M20.5 5a7 7 0 0 0-6.2 10.2L6 23.5a2.5 2.5 0 0 0 3.5 3.5l8.3-8.3A7 7 0 1 0 20.5 5z"
        :fill="aktif ? '#ffffff26' : '#1e9bf01f'"
        :stroke="aktif ? '#ffffff' : '#1e9bf0'"
        stroke-width="1.8"
      />
      <circle cx="21" cy="12" r="3" :fill="aktif ? '#FFD54A' : '#8BC53F'" />
    </g>
  </svg>
</template>
