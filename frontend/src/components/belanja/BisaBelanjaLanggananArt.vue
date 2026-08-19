<script setup lang="ts">
/**
 * Banner "Langganan" di halaman checkout, tepat di atas Jaminan Garansi.
 *
 * Kanvas dipadatkan dari 1280x768 (1,67:1) jadi 1000x169 (5,9:1) — sama dengan
 * banner Garansi supaya keduanya terlihat sebaris. Alasan yang sama seperti
 * banner itu: pada lebar render ~398px, subtitle 34 unit di kanvas 1280 hanya
 * jadi 10,6px di layar. Di kanvas ini subtitle 29 unit = 11,5px dan judul
 * 38 unit = 15px. Ilustrasi aslinya dipakai utuh, hanya diskala.
 */
</script>

<template>
  <svg viewBox="0 0 1000 169" xmlns="http://www.w3.org/2000/svg" class="w-full h-auto block">
    <defs>
      <linearGradient id="lgBg" x1="0%" y1="0%" x2="100%" y2="100%">
        <stop offset="0%" stop-color="#2E9DF7" />
        <stop offset="55%" stop-color="#1E7FE0" />
        <stop offset="100%" stop-color="#0F5FD1" />
      </linearGradient>
      <linearGradient id="lgGreenWave" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#8FDD3A" />
        <stop offset="100%" stop-color="#5CC22A" />
      </linearGradient>
      <linearGradient id="lgCalBody" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FFFFFF" />
        <stop offset="100%" stop-color="#DCE3EC" />
      </linearGradient>
      <linearGradient id="lgCalHeader" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#3A6FE8" />
        <stop offset="100%" stop-color="#1E44C4" />
      </linearGradient>
      <linearGradient id="lgRing" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FFFFFF" />
        <stop offset="100%" stop-color="#C7D2E0" />
      </linearGradient>
      <linearGradient id="lgCart" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#3A6FE8" />
        <stop offset="100%" stop-color="#1E44C4" />
      </linearGradient>
      <linearGradient id="lgBadge" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#4A7CF0" />
        <stop offset="100%" stop-color="#1E44C4" />
      </linearGradient>
      <linearGradient id="lgLime" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#8FDD3A" />
        <stop offset="100%" stop-color="#5CC22A" />
      </linearGradient>
      <linearGradient id="lgYellow" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FFDA47" />
        <stop offset="100%" stop-color="#FBBA07" />
      </linearGradient>
      <linearGradient id="lgApple" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FF6B4A" />
        <stop offset="100%" stop-color="#E02418" />
      </linearGradient>
      <linearGradient id="lgCarrot" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#FFB24A" />
        <stop offset="100%" stop-color="#F07A10" />
      </linearGradient>
      <linearGradient id="lgLeaf" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#5FD13A" />
        <stop offset="100%" stop-color="#2C8E18" />
      </linearGradient>
      <clipPath id="lgClip">
        <rect x="0" y="0" width="1000" height="169" rx="20" ry="20" />
      </clipPath>
    </defs>

    <g clip-path="url(#lgClip)">
      <rect width="1000" height="169" fill="url(#lgBg)" />

      <!-- Gelombang hijau: path asli, diskala agar pas di kanvas pendek -->
      <g transform="scale(0.5, 0.2)">
        <path
          d="M -60,-60 C 90,80 20,220 100,340 C 175,455 40,560 120,828 L -60,828 Z"
          fill="url(#lgGreenWave)"
        />
        <path
          d="M -60,-60 C 40,110 -30,250 40,370 C 105,480 -10,600 60,828 L -60,828 Z"
          fill="#3FB43A"
          opacity="0.55"
        />
      </g>

      <!-- Ilustrasi asli (kalender + troli + lencana mahkota), diskala 0,225.
           Titik tengah gambar asli ada di (342,409), digeser ke (78,84). -->
      <g transform="translate(78,84) scale(0.225) translate(-342,-409)">
        <!-- kilau kuning -->
        <g fill="url(#lgYellow)">
          <path d="M96,146 l58,20 -46,44 -22,-30 z" />
          <path d="M84,204 l46,10 -22,30 -26,-16 z" />
        </g>

        <!-- ring kalender -->
        <ellipse cx="244" cy="158" rx="34" ry="44" fill="none" stroke="url(#lgRing)" stroke-width="20" />
        <ellipse cx="438" cy="158" rx="34" ry="44" fill="none" stroke="url(#lgRing)" stroke-width="20" />

        <!-- kalender -->
        <rect x="146" y="132" width="392" height="470" rx="46" fill="url(#lgCalBody)" />
        <path
          d="M146,258 v-80 a46,46 0 0 1 46,-46 h300 a46,46 0 0 1 46,46 v80 z"
          fill="url(#lgCalHeader)"
        />

        <!-- belanjaan -->
        <g fill="url(#lgLeaf)">
          <path d="M262,300 c-22,10 -34,34 -30,58 26,4 50,-10 60,-32 z" />
          <path d="M292,278 c-16,20 -18,48 -4,68 24,-10 36,-36 30,-60 z" />
          <path d="M236,330 c-14,16 -16,40 -4,56 20,-8 30,-30 26,-50 z" />
        </g>
        <path d="M320,306 l24,4 -6,86 c-1,12 -18,13 -21,1 z" fill="url(#lgCarrot)" />
        <path d="M318,304 c-8,-18 4,-32 16,-30 -2,10 -4,20 -2,30 z" fill="url(#lgLeaf)" />
        <path d="M340,304 c10,-16 26,-14 30,-4 -10,2 -20,8 -26,16 z" fill="url(#lgLeaf)" />
        <circle cx="380" cy="356" r="42" fill="url(#lgApple)" />
        <path d="M380,318 c-4,-16 4,-28 18,-30 -2,14 -6,24 -14,30 z" fill="url(#lgLeaf)" />
        <path
          d="M418,318 h72 v96 a10,10 0 0 1 -10,10 h-52 a10,10 0 0 1 -10,-10 z"
          fill="url(#lgYellow)"
        />
        <path d="M418,318 l36,-24 36,24 z" fill="#FFE884" />

        <!-- troli -->
        <path
          d="M178,362 h34 l16,44"
          fill="none"
          stroke="url(#lgCart)"
          stroke-width="26"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
        <path
          d="M228,398 h250 l-32,116 a16,16 0 0 1 -15,11 h-166 a16,16 0 0 1 -15,-11 z"
          fill="url(#lgCart)"
        />
        <g fill="#FFFFFF" opacity="0.95">
          <rect x="252" y="424" width="42" height="30" rx="6" />
          <rect x="306" y="424" width="42" height="30" rx="6" />
          <rect x="360" y="424" width="42" height="30" rx="6" />
          <rect x="414" y="424" width="42" height="30" rx="6" />
          <rect x="262" y="466" width="42" height="30" rx="6" />
          <rect x="316" y="466" width="42" height="30" rx="6" />
          <rect x="370" y="466" width="42" height="30" rx="6" />
        </g>
        <path d="M246,540 h206" stroke="url(#lgCart)" stroke-width="20" stroke-linecap="round" />
        <circle cx="266" cy="576" r="24" fill="url(#lgCart)" stroke="#FFFFFF" stroke-width="9" />
        <circle cx="416" cy="576" r="24" fill="url(#lgCart)" stroke="#FFFFFF" stroke-width="9" />

        <!-- lencana mahkota -->
        <path d="M448,600 l-38,116 58,-30 32,-52 z" fill="url(#lgYellow)" />
        <path d="M552,600 l38,116 -58,-30 -32,-52 z" fill="url(#lgYellow)" />
        <circle cx="500" cy="574" r="90" fill="#FFFFFF" />
        <circle cx="500" cy="574" r="78" fill="url(#lgBadge)" />
        <path d="M452,606 l-8,-62 32,24 24,-42 24,42 32,-24 -8,62 z" fill="url(#lgLime)" />
        <g fill="url(#lgLime)">
          <circle cx="444" cy="538" r="10" />
          <circle cx="500" cy="518" r="10" />
          <circle cx="556" cy="538" r="10" />
        </g>
      </g>

      <!-- ===== Teks ===== -->
      <g font-family="Arial, Helvetica, sans-serif">
        <text x="175" y="55" font-size="38" font-weight="800" fill="#FFFFFF">Langganan</text>

        <rect x="177" y="65" width="90" height="4" rx="2" fill="#8FDD3A" />
        <circle cx="277" cy="67" r="4.5" fill="#8FDD3A" />

        <text x="175" y="107" font-size="29" fill="#FFFFFF">Belanja lebih hemat, praktis,</text>
        <text x="175" y="140" font-size="29" fill="#FFFFFF">dan banyak untung tiap hari.</text>
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
