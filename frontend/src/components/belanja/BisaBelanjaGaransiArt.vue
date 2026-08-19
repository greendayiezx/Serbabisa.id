<script setup lang="ts">
/**
 * Banner "Jaminan Garansi" di halaman checkout.
 *
 * Kanvas 1000x169 (~5,9:1) mengikuti proporsi banner Astro — bukan 1100x580
 * seperti rancangan aslinya. Alasannya ukuran huruf efektif: banner ini dirender
 * selebar ~398px di HP, jadi tiap unit viewBox = 0,398px. Pada kanvas 1280 lebar,
 * subtitle 27 unit hanya jadi 8,4px di layar alias tak terbaca. Di kanvas ini
 * judul 38 unit = 15px dan subtitle 29 unit = 11,5px.
 */
</script>

<template>
  <svg viewBox="0 0 1000 169" xmlns="http://www.w3.org/2000/svg" class="w-full h-auto block">
    <defs>
      <linearGradient id="garansiBg" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#2E9DF7" />
        <stop offset="55%" stop-color="#1E7FE0" />
        <stop offset="100%" stop-color="#0F5FD1" />
      </linearGradient>
      <linearGradient id="garansiGreen" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#8FDD3A" />
        <stop offset="100%" stop-color="#5CC22A" />
      </linearGradient>
      <linearGradient id="garansiStar" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#FFD948" />
        <stop offset="100%" stop-color="#F5A623" />
      </linearGradient>
      <linearGradient id="garansiBadge" x1="0%" y1="0%" x2="0%" y2="100%">
        <stop offset="0%" stop-color="#F44336" />
        <stop offset="100%" stop-color="#C62828" />
      </linearGradient>
      <clipPath id="garansiClip">
        <rect x="0" y="0" width="1000" height="169" rx="20" ry="20" />
      </clipPath>
    </defs>

    <g clip-path="url(#garansiClip)">
      <rect x="0" y="0" width="1000" height="169" fill="url(#garansiBg)" />

      <!-- Gelombang hijau: path asli, diskala agar pas di kanvas pendek ini -->
      <g transform="scale(0.63, 0.291)">
        <path
          d="M -50,-50 C 80,60 -20,180 90,280 C 190,370 60,460 130,630 L -50,630 Z"
          fill="url(#garansiGreen)"
          opacity="0.95"
        />
        <path
          d="M -50,-50 C 40,90 -60,220 40,320 C 130,410 20,470 70,630 L -50,630 Z"
          fill="#3FB43A"
          opacity="0.55"
        />
      </g>

      <!-- ===== Lencana: gambar asli, diskala 0,32 agar muat tinggi banner ===== -->
      <g transform="translate(82,86) scale(0.32)">
        <g fill="url(#garansiStar)" stroke="#D98A0F" stroke-width="1">
          <path
            transform="translate(0,-195) scale(1.55)"
            d="M0,-24 L6.9,-9 L23.5,-7.4 L11,3.7 L14.8,20 L0,11.5 L-14.8,20 L-11,3.7 L-23.5,-7.4 L-6.9,-9 Z"
          />
          <path
            transform="translate(-95,-160) scale(1.25)"
            d="M0,-24 L6.9,-9 L23.5,-7.4 L11,3.7 L14.8,20 L0,11.5 L-14.8,20 L-11,3.7 L-23.5,-7.4 L-6.9,-9 Z"
          />
          <path
            transform="translate(95,-160) scale(1.25)"
            d="M0,-24 L6.9,-9 L23.5,-7.4 L11,3.7 L14.8,20 L0,11.5 L-14.8,20 L-11,3.7 L-23.5,-7.4 L-6.9,-9 Z"
          />
          <path
            transform="translate(-175,-90) scale(1.05)"
            d="M0,-24 L6.9,-9 L23.5,-7.4 L11,3.7 L14.8,20 L0,11.5 L-14.8,20 L-11,3.7 L-23.5,-7.4 L-6.9,-9 Z"
          />
          <path
            transform="translate(175,-90) scale(1.05)"
            d="M0,-24 L6.9,-9 L23.5,-7.4 L11,3.7 L14.8,20 L0,11.5 L-14.8,20 L-11,3.7 L-23.5,-7.4 L-6.9,-9 Z"
          />
        </g>

        <path d="M -70,60 L -100,220 L -35,190 L -10,150 Z" fill="#8E1B1B" />
        <path d="M 70,60 L 100,220 L 35,190 L 10,150 Z" fill="#B02525" />

        <circle cx="0" cy="0" r="120" fill="url(#garansiBadge)" stroke="#8E1B1B" stroke-width="2" />
        <circle
          cx="0"
          cy="0"
          r="128"
          fill="none"
          stroke="url(#garansiBadge)"
          stroke-width="18"
          stroke-dasharray="18 10"
          opacity="0.9"
        />
        <circle cx="0" cy="0" r="92" fill="none" stroke="#FFFFFF" stroke-width="7" />
        <path
          d="M -48,2 L -14,38 L 55,-42"
          fill="none"
          stroke="#FFFFFF"
          stroke-width="20"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </g>

      <!-- ===== Teks ===== -->
      <g font-family="Arial, Helvetica, sans-serif">
        <text x="175" y="55" font-size="38" font-weight="800" fill="#FFFFFF">Jaminan Garansi</text>

        <rect x="177" y="65" width="90" height="4" rx="2" fill="#8FDD3A" />
        <circle cx="277" cy="67" r="4.5" fill="#8FDD3A" />

        <text x="175" y="107" font-size="29" fill="#FFFFFF">Layanan terpercaya dengan jaminan</text>
        <text x="175" y="140" font-size="29" fill="#FFFFFF">kualitas dan kepuasan pelanggan</text>
      </g>

      <!-- ===== Panah ===== -->
      <g transform="translate(930,84)">
        <circle cx="0" cy="0" r="26" fill="#FFFFFF" />
        <path
          d="M -7,-12 L 7,0 L -7,12"
          fill="none"
          stroke="#1E7FE0"
          stroke-width="6"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </g>
    </g>
  </svg>
</template>
