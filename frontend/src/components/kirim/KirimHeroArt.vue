<script setup lang="ts">
/**
 * Spanduk BisaKirim — kurir membawa paket.
 *
 * Kalimatnya SENGAJA sama dengan yang dijanjikan katalog tarif: "1–2 jam".
 * Spanduk yang berbunyi "1 jam sampai" sementara katalognya menulis 1–2 jam
 * adalah janji yang dibantah oleh layar berikutnya, dan yang dipercaya orang
 * adalah spanduknya.
 *
 * Id di <defs> diberi awalan useId(): id SVG berlaku sedokumen, dan nama polos
 * akan diambil dari definisi pertama yang kebetulan dirender lebih dulu — diam-
 * diam, tanpa galat, dan yang hilang justru elemennya tidak digambar.
 */
import { useId } from 'vue'

const uid = useId()
const id = (n: string) => `${uid}-${n}`
const url = (n: string) => `url(#${uid}-${n})`
</script>

<template>
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 400 230"
    preserveAspectRatio="xMidYMid slice"
    role="img"
    aria-label="Ilustrasi kurir BisaKirim membawa paket"
    class="block w-full h-auto"
  >
    <defs>
      <linearGradient :id="id('langit')" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#1E9BF0" />
        <stop offset="100%" stop-color="#0B62A8" />
      </linearGradient>
      <linearGradient :id="id('lime')" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#B8EF63" />
        <stop offset="100%" stop-color="#8BC53F" />
      </linearGradient>
      <linearGradient :id="id('kardus')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#D9A967" />
        <stop offset="100%" stop-color="#B8853F" />
      </linearGradient>
      <linearGradient :id="id('seragam')" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#2A4585" />
        <stop offset="100%" stop-color="#1B2C5E" />
      </linearGradient>
    </defs>

    <rect width="400" height="230" :fill="url('langit')" />

    <!-- Lengkung terang di kanan, ruang untuk sosok kurir -->
    <path d="M250,0 C330,40 340,170 300,230 L400,230 L400,0 Z" fill="#FFFFFF" opacity=".08" />
    <circle cx="330" cy="70" r="86" fill="#FFFFFF" opacity=".07" />

    <!-- Kalimat spanduk -->
    <g>
      <!--
        Digeser ke kanan tombol kembali. Di x=22 lencananya tepat berada di
        bawah tombol bulat itu dan praktis tidak terbaca.
      -->
      <rect x="66" y="30" width="86" height="22" rx="11" :fill="url('lime')" />
      <text x="80" y="45" font-family="system-ui, sans-serif" font-size="12" font-weight="800" fill="#12224E">
        BisaKirim
      </text>

      <text x="22" y="92" font-family="system-ui, sans-serif" font-size="26" font-weight="800" fill="#FFFFFF">
        Paket dijemput,
      </text>
      <text x="22" y="122" font-family="system-ui, sans-serif" font-size="26" font-weight="800" fill="#FFFFFF">
        sampai 1–2 jam
      </text>
      <text
        x="22"
        y="148"
        font-family="system-ui, sans-serif"
        font-size="12"
        font-weight="600"
        fill="#FFFFFF"
        opacity=".85"
      >
        Motor sampai 20 kg · Mobil sampai 100 kg
      </text>
    </g>

    <!-- Kurir dengan kardus -->
    <g transform="translate(268,26)">
      <!-- Badan -->
      <path d="M22,172 C14,120 22,86 46,86 C70,86 78,120 70,172 Z" :fill="url('seragam')" />
      <!-- Kepala + helm -->
      <circle cx="46" cy="66" r="21" fill="#E8B98C" />
      <path d="M25,66 a21,21 0 0 1 42,0 z" fill="#16244F" />
      <path d="M25,66 h42 q9,0 10.5,6 h-53 z" :fill="url('lime')" />
      <!-- Kardus dipeluk -->
      <rect x="6" y="112" width="80" height="56" rx="6" :fill="url('kardus')" />
      <rect x="6" y="132" width="80" height="9" fill="#9C6C2E" opacity=".75" />
      <rect x="40" y="112" width="12" height="56" fill="#9C6C2E" opacity=".45" />
      <!-- Lengan memeluk kardus -->
      <path
        d="M18,116 q-12,18 4,44"
        fill="none"
        stroke="#2A4585"
        stroke-width="11"
        stroke-linecap="round"
      />
      <path
        d="M74,116 q12,18 -4,44"
        fill="none"
        stroke="#2A4585"
        stroke-width="11"
        stroke-linecap="round"
      />
    </g>

    <!-- Garis rute kecil sebagai tanda antar titik -->
    <g opacity=".8">
      <path
        d="M24,182 C70,206 130,158 186,186"
        fill="none"
        stroke="#B8EF63"
        stroke-width="2.4"
        stroke-linecap="round"
        stroke-dasharray="7 7"
        opacity=".7"
      />
      <circle cx="24" cy="182" r="5" fill="#B8EF63" />
      <circle cx="24" cy="182" r="2" fill="#0B62A8" />
      <g transform="translate(178,162)">
        <path d="M8,26 C8,26 16,15 16,9 A8,8 0 1 0 0,9 C0,15 8,26 8,26 Z" fill="#F97316" />
        <circle cx="8" cy="9" r="3.2" fill="#FFFFFF" />
      </g>
    </g>
  </svg>
</template>
