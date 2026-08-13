<template>
  <!--
    Hero ilustrasi BisaBelanja — flat vector, tema malam: minimarket dengan
    signboard & awning lime, kurir membawa kantong belanja, troli di depan
    toko, dan skuter antar. Dipakai penuh di header halaman BisaBelanja.
  -->
  <svg
    viewBox="0 0 400 620"
    xmlns="http://www.w3.org/2000/svg"
    preserveAspectRatio="xMidYMax slice"
    role="img"
    aria-label="Ilustrasi minimarket BisaBelanja, kurir membawa kantong belanja dan skuter antar di malam hari"
    class="block w-full h-auto"
  >
    <defs>
      <linearGradient id="bb-sky" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#070E26"/>
        <stop offset="38%" stop-color="#0D1839"/>
        <stop offset="72%" stop-color="#152859"/>
        <stop offset="100%" stop-color="#1F3A76"/>
      </linearGradient>
      <radialGradient id="bb-moonGlow" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#F6E9BE" stop-opacity="0.30"/>
        <stop offset="45%" stop-color="#F6E9BE" stop-opacity="0.10"/>
        <stop offset="100%" stop-color="#F6E9BE" stop-opacity="0"/>
      </radialGradient>
      <radialGradient id="bb-horizonHaze" cx="50%" cy="100%" r="70%">
        <stop offset="0%" stop-color="#4A7FD6" stop-opacity="0.28"/>
        <stop offset="60%" stop-color="#2B4E9E" stop-opacity="0.10"/>
        <stop offset="100%" stop-color="#2B4E9E" stop-opacity="0"/>
      </radialGradient>
      <linearGradient id="bb-bldgFar" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#22376E"/><stop offset="100%" stop-color="#182A57"/>
      </linearGradient>
      <linearGradient id="bb-bldgMid" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#182B5C"/><stop offset="100%" stop-color="#101E45"/>
      </linearGradient>
      <linearGradient id="bb-storeBody" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#243C79"/><stop offset="100%" stop-color="#15254E"/>
      </linearGradient>
      <linearGradient id="bb-storeGlass" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#FFE9A8"/><stop offset="100%" stop-color="#E9B85F"/>
      </linearGradient>
      <radialGradient id="bb-storeSpill" cx="50%" cy="0%" r="70%">
        <stop offset="0%" stop-color="#FFD98A" stop-opacity="0.30"/>
        <stop offset="100%" stop-color="#FFD98A" stop-opacity="0"/>
      </radialGradient>
      <linearGradient id="bb-uniform" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#2A4585"/><stop offset="100%" stop-color="#1B2C5E"/>
      </linearGradient>
      <linearGradient id="bb-bagGrad" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#D9A967"/><stop offset="100%" stop-color="#B8853F"/>
      </linearGradient>
      <linearGradient id="bb-limeBox" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#A6DD5C"/><stop offset="100%" stop-color="#7CB232"/>
      </linearGradient>
      <linearGradient id="bb-waveMain" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#A6DD5C"/><stop offset="100%" stop-color="#7CB232"/>
      </linearGradient>
      <radialGradient id="bb-lampGlow" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#B7E36B" stop-opacity="0.42"/>
        <stop offset="100%" stop-color="#B7E36B" stop-opacity="0"/>
      </radialGradient>
      <radialGradient id="bb-groundShade" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#03071A" stop-opacity="0.6"/>
        <stop offset="100%" stop-color="#03071A" stop-opacity="0"/>
      </radialGradient>
    </defs>

    <!-- ============ SKY ============ -->
    <rect width="400" height="620" fill="url(#bb-sky)"/>
    <rect y="200" width="400" height="200" fill="url(#bb-horizonHaze)"/>

    <g fill="#FFFFFF">
      <circle cx="30" cy="48" r="1.5" opacity=".85"/><circle cx="72" cy="92" r="1" opacity=".55"/>
      <circle cx="112" cy="36" r="1.3" opacity=".75"/><circle cx="158" cy="74" r=".9" opacity=".5"/>
      <circle cx="196" cy="30" r="1.5" opacity=".8"/><circle cx="236" cy="62" r="1" opacity=".6"/>
      <circle cx="20" cy="126" r="1" opacity=".45"/><circle cx="146" cy="116" r="1.2" opacity=".6"/>
      <circle cx="266" cy="26" r="1.1" opacity=".7"/><circle cx="378" cy="146" r="1.3" opacity=".55"/>
      <circle cx="12" cy="192" r="1.1" opacity=".45"/><circle cx="92" cy="148" r=".9" opacity=".45"/>
      <circle cx="212" cy="104" r="1" opacity=".5"/><circle cx="308" cy="160" r="1" opacity=".4"/>
      <circle cx="52" cy="20" r="1" opacity=".6"/>
    </g>

    <g fill="#FFFFFF" opacity=".9">
      <path d="M132,62 l1.6,4.4 4.4,1.6 -4.4,1.6 -1.6,4.4 -1.6,-4.4 -4.4,-1.6 4.4,-1.6z"/>
      <path d="M258,120 l1.2,3.4 3.4,1.2 -3.4,1.2 -1.2,3.4 -1.2,-3.4 -3.4,-1.2 3.4,-1.2z" opacity=".7"/>
      <path d="M60,68 l1,2.8 2.8,1 -2.8,1 -1,2.8 -1,-2.8 -2.8,-1 2.8,-1z" opacity=".6"/>
    </g>

    <!-- Moon -->
    <circle cx="322" cy="80" r="78" fill="url(#bb-moonGlow)"/>
    <circle cx="322" cy="80" r="30" fill="#F8EEC8"/>
    <circle cx="334" cy="70" r="27" fill="#0A1330"/>

    <g fill="#4A6BB0" opacity=".16">
      <ellipse cx="100" cy="176" rx="62" ry="7"/>
      <ellipse cx="150" cy="188" rx="40" ry="5"/>
      <ellipse cx="300" cy="164" rx="52" ry="6"/>
    </g>

    <!-- ============ CITY far ============ -->
    <g fill="url(#bb-bldgFar)" opacity=".75">
      <rect x="-8" y="252" width="42" height="150"/>
      <rect x="36" y="228" width="30" height="174"/>
      <rect x="106" y="240" width="34" height="162"/>
      <rect x="176" y="222" width="26" height="180"/>
      <rect x="246" y="244" width="32" height="158"/>
      <rect x="300" y="230" width="28" height="172"/>
      <rect x="356" y="240" width="52" height="162"/>
    </g>
    <g fill="#F7DE84" opacity=".30">
      <rect x="2" y="266" width="5" height="7"/><rect x="16" y="266" width="5" height="7"/>
      <rect x="2" y="286" width="5" height="7"/><rect x="44" y="244" width="5" height="7"/>
      <rect x="56" y="244" width="5" height="7"/><rect x="44" y="268" width="5" height="7"/>
      <rect x="114" y="254" width="5" height="7"/><rect x="128" y="254" width="5" height="7"/>
      <rect x="182" y="236" width="5" height="7"/><rect x="182" y="260" width="5" height="7"/>
      <rect x="254" y="258" width="5" height="7"/><rect x="266" y="280" width="5" height="7"/>
      <rect x="308" y="244" width="5" height="7"/><rect x="308" y="268" width="5" height="7"/>
      <rect x="366" y="254" width="5" height="7"/><rect x="382" y="274" width="5" height="7"/>
    </g>

    <!-- ============ CITY mid ============ -->
    <g fill="url(#bb-bldgMid)">
      <rect x="-10" y="282" width="46" height="120"/>
      <rect x="40" y="262" width="34" height="140"/>
      <rect x="292" y="268" width="38" height="134"/>
      <rect x="336" y="288" width="30" height="114"/>
      <rect x="370" y="264" width="38" height="138"/>
    </g>
    <g fill="#101E45">
      <rect x="48" y="252" width="6" height="10"/>
      <rect x="56" y="256" width="12" height="6"/>
      <rect x="378" y="252" width="5" height="12"/>
    </g>
    <line x1="51" y1="236" x2="51" y2="252" stroke="#101E45" stroke-width="2"/>
    <circle cx="51" cy="234" r="2.4" fill="#FF6B6B" opacity=".9"/>
    <line x1="380" y1="238" x2="380" y2="252" stroke="#101E45" stroke-width="2"/>
    <circle cx="380" cy="236" r="2.2" fill="#FF6B6B" opacity=".8"/>

    <g fill="#F7DE84" opacity=".5">
      <rect x="0" y="296" width="6" height="8"/><rect x="14" y="296" width="6" height="8"/>
      <rect x="0" y="318" width="6" height="8"/><rect x="14" y="340" width="6" height="8"/>
      <rect x="48" y="276" width="6" height="8"/><rect x="62" y="276" width="6" height="8"/>
      <rect x="48" y="298" width="6" height="8"/><rect x="62" y="320" width="6" height="8"/>
      <rect x="300" y="282" width="6" height="8"/><rect x="314" y="282" width="6" height="8"/>
      <rect x="300" y="304" width="6" height="8"/><rect x="314" y="326" width="6" height="8"/>
      <rect x="344" y="302" width="6" height="8"/><rect x="344" y="324" width="6" height="8"/>
      <rect x="378" y="278" width="6" height="8"/><rect x="392" y="278" width="6" height="8"/>
      <rect x="378" y="300" width="6" height="8"/><rect x="392" y="322" width="6" height="8"/>
    </g>

    <!-- Street lamp (right) -->
    <g>
      <circle cx="352" cy="318" r="34" fill="url(#bb-lampGlow)"/>
      <rect x="350" y="318" width="3.5" height="80" fill="#0C1734"/>
      <path d="M351.5,318 q0,-14 -16,-14" fill="none" stroke="#0C1734" stroke-width="3.5"/>
      <ellipse cx="335" cy="306" rx="7" ry="4" fill="#CDEB8E"/>
    </g>

    <!-- ============ FLOATING SHOPPING BADGE (route motif) ============ -->
    <g opacity=".95">
      <path d="M96,224 C132,202 176,202 210,216" fill="none" stroke="#8BC53F" stroke-width="2.4"
            stroke-linecap="round" stroke-dasharray="7 7" opacity=".55"/>
      <circle cx="96" cy="224" r="4" fill="#8BC53F" opacity=".7"/>
      <g transform="translate(206,186)">
        <path d="M4,10 h24 l3,26 a3,3 0 0 1 -3,3 h-24 a3,3 0 0 1 -3,-3 z" fill="#8BC53F"/>
        <path d="M10,12 v-3 a6,6 0 0 1 12,0 v3" fill="none" stroke="#12224E" stroke-width="2.4" stroke-linecap="round"/>
        <circle cx="16" cy="24" r="3.4" fill="#12224E"/>
      </g>
    </g>

    <!-- ============ MINIMARKET STOREFRONT ============ -->
    <g>
      <!-- warm light spilling onto pavement -->
      <path d="M40,396 L246,396 L262,440 L24,440 Z" fill="url(#bb-storeSpill)"/>

      <!-- building shell -->
      <rect x="40" y="252" width="206" height="144" rx="4" fill="url(#bb-storeBody)"/>
      <rect x="36" y="248" width="214" height="10" rx="3" fill="#2C4784"/>

      <!-- signboard -->
      <rect x="52" y="262" width="182" height="30" rx="6" fill="#12224E"/>
      <rect x="52" y="262" width="182" height="30" rx="6" fill="none" stroke="#2C4784" stroke-width="1.4"/>
      <g transform="translate(62,268)">
        <rect width="19" height="19" rx="5.5" fill="#8BC53F"/>
        <path d="M4.5,6.5 h10 l1.4,9 a1.6,1.6 0 0 1 -1.6,1.6 h-9.6 a1.6,1.6 0 0 1 -1.6,-1.6 z" fill="#12224E"/>
        <path d="M7,7 v-1.6 a2.5,2.5 0 0 1 5,0 v1.6" fill="none" stroke="#12224E" stroke-width="1.5" stroke-linecap="round"/>
      </g>
      <text x="88" y="283" font-family="Arial, Helvetica, sans-serif" font-weight="800" font-size="17" fill="#FFFFFF" letter-spacing=".2">Bisa<tspan fill="#8BC53F">Belanja</tspan></text>

      <!-- awning: lime & white stripes with scalloped edge -->
      <path d="M42,296 L244,296 L252,318 L34,318 Z" fill="#8BC53F"/>
      <g fill="#F2FAE6" opacity=".92">
        <path d="M66,296 L86,296 L88,318 L64,318 Z"/>
        <path d="M110,296 L130,296 L130,318 L108,318 Z"/>
        <path d="M154,296 L174,296 L172,318 L152,318 Z"/>
        <path d="M198,296 L218,296 L214,318 L196,318 Z"/>
      </g>
      <path d="M34,318 q6,7 12,0 q6,7 12,0 q6,7 12,0 q6,7 12,0 q6,7 12,0 q6,7 12,0 q6,7 12,0
               q6,7 12,0 q6,7 12,0 q6,7 12,0 q6,7 12,0 q6,7 12,0 q6,7 12,0 q6,7 12,0 q6,7 12,0
               q6,7 12,0 q6,7 12,0 q6,7 12,0 L252,318 Z" fill="#7CB232"/>

      <!-- glass shopfront -->
      <rect x="52" y="326" width="80" height="70" rx="3" fill="url(#bb-storeGlass)" opacity=".92"/>
      <rect x="154" y="326" width="80" height="70" rx="3" fill="url(#bb-storeGlass)" opacity=".92"/>
      <!-- window frames -->
      <g stroke="#12224E" stroke-width="3" fill="none">
        <rect x="52" y="326" width="80" height="70" rx="3"/>
        <rect x="154" y="326" width="80" height="70" rx="3"/>
      </g>
      <!-- shelves with goods (silhouette) -->
      <g fill="#12224E" opacity=".78">
        <rect x="58" y="348" width="68" height="3"/>
        <rect x="58" y="372" width="68" height="3"/>
        <rect x="62" y="336" width="6" height="12" rx="1.5"/>
        <rect x="72" y="338" width="8" height="10" rx="1.5"/>
        <rect x="84" y="334" width="5" height="14" rx="1.5"/>
        <rect x="94" y="338" width="9" height="10" rx="1.5"/>
        <rect x="108" y="335" width="6" height="13" rx="1.5"/>
        <rect x="62" y="360" width="9" height="12" rx="1.5"/>
        <rect x="76" y="362" width="6" height="10" rx="1.5"/>
        <rect x="88" y="359" width="8" height="13" rx="1.5"/>
        <rect x="102" y="362" width="7" height="10" rx="1.5"/>
        <rect x="114" y="358" width="6" height="14" rx="1.5"/>
        <rect x="160" y="348" width="68" height="3"/>
        <rect x="160" y="372" width="68" height="3"/>
        <rect x="164" y="336" width="7" height="12" rx="1.5"/>
        <rect x="176" y="334" width="6" height="14" rx="1.5"/>
        <rect x="188" y="338" width="9" height="10" rx="1.5"/>
        <rect x="202" y="335" width="6" height="13" rx="1.5"/>
        <rect x="213" y="338" width="8" height="10" rx="1.5"/>
        <rect x="164" y="360" width="8" height="12" rx="1.5"/>
        <rect x="178" y="362" width="7" height="10" rx="1.5"/>
        <rect x="190" y="358" width="6" height="14" rx="1.5"/>
        <rect x="203" y="361" width="9" height="11" rx="1.5"/>
        <rect x="218" y="359" width="6" height="13" rx="1.5"/>
      </g>
      <!-- glass reflection streaks -->
      <g fill="#FFFFFF" opacity=".22">
        <path d="M60,396 L86,326 L96,326 L70,396 Z"/>
        <path d="M162,396 L188,326 L196,326 L170,396 Z"/>
      </g>

      <!-- entrance door -->
      <rect x="136" y="322" width="16" height="74" rx="2" fill="#0F1D42"/>
      <rect x="139" y="330" width="10" height="46" rx="1.5" fill="#FFE9A8" opacity=".55"/>
      <circle cx="148" cy="360" r="1.6" fill="#8BC53F"/>
      <!-- OPEN 24 JAM tag -->
      <rect x="196" y="332" width="34" height="13" rx="3" fill="#8BC53F"/>
      <text x="200" y="341.5" font-family="Arial, Helvetica, sans-serif" font-weight="700" font-size="7" fill="#12224E" letter-spacing=".3">24 JAM</text>

      <!-- kerb -->
      <rect x="34" y="392" width="218" height="5" fill="#0C1734"/>
    </g>

    <!-- ============ ROAD ============ -->
    <rect x="0" y="396" width="400" height="66" fill="#0A1330"/>
    <rect x="0" y="396" width="400" height="3" fill="#1B2C5E"/>
    <g fill="#3A5296" opacity=".45">
      <rect x="18" y="426" width="26" height="3.5" rx="1.5"/>
      <rect x="70" y="426" width="26" height="3.5" rx="1.5"/>
      <rect x="122" y="426" width="26" height="3.5" rx="1.5"/>
      <rect x="174" y="426" width="26" height="3.5" rx="1.5"/>
      <rect x="226" y="426" width="26" height="3.5" rx="1.5"/>
      <rect x="278" y="426" width="26" height="3.5" rx="1.5"/>
      <rect x="330" y="426" width="26" height="3.5" rx="1.5"/>
    </g>

    <!-- ============ SHOPPING CART (left, outside store) ============ -->
    <g transform="translate(14,352)">
      <ellipse cx="20" cy="46" rx="20" ry="4.5" fill="#04081A" opacity=".4"/>
      <path d="M2,6 h6 l6,24 h22" fill="none" stroke="#2C4784" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
      <path d="M9,12 h30 l-4,16 h-22 z" fill="#1B2C5E" stroke="#2C4784" stroke-width="2" stroke-linejoin="round"/>
      <!-- goods poking out -->
      <circle cx="17" cy="10" r="4" fill="#8BC53F"/>
      <circle cx="26" cy="9" r="3.4" fill="#E8804F"/>
      <rect x="30" y="5" width="6" height="8" rx="1.5" fill="#D9A967"/>
      <circle cx="16" cy="38" r="4" fill="#0A1330"/>
      <circle cx="16" cy="38" r="1.6" fill="#8BC53F"/>
      <circle cx="32" cy="38" r="4" fill="#0A1330"/>
      <circle cx="32" cy="38" r="1.6" fill="#8BC53F"/>
    </g>

    <!-- ============ COURIER — carrying two grocery bags ============ -->
    <g transform="translate(204,300)">
      <ellipse cx="22" cy="96" rx="27" ry="6" fill="#04081A" opacity=".45"/>
      <!-- legs -->
      <rect x="10" y="68" width="10" height="28" rx="5" fill="#16244F"/>
      <rect x="25" y="68" width="10" height="28" rx="5" fill="#16244F"/>
      <path d="M8,92 h14 a3,3 0 0 1 3,3 v2 h-17 z" fill="#0A1330"/>
      <path d="M23,92 h14 a3,3 0 0 1 3,3 v2 h-17 z" fill="#0A1330"/>
      <!-- torso -->
      <path d="M4,34 q0,-8 8,-8 h21 q8,0 8,8 v30 q0,6 -6,6 h-25 q-6,0 -6,-6 z" fill="url(#bb-uniform)"/>
      <circle cx="22" cy="48" r="6" fill="#8BC53F"/>
      <path d="M18.4,44.8 h7.2 l.9,7 a1,1 0 0 1 -1,1 h-7 a1,1 0 0 1 -1,-1 z" fill="#16244F"/>
      <!-- arms hanging, holding bags -->
      <rect x="-3" y="36" width="10" height="26" rx="5" fill="#2A4585"/>
      <rect x="38" y="36" width="10" height="26" rx="5" fill="#2A4585"/>
      <circle cx="2" cy="62" r="4.6" fill="#E8BC8E"/>
      <circle cx="43" cy="62" r="4.6" fill="#E8BC8E"/>
      <!-- left grocery bag -->
      <g transform="translate(-13,64)">
        <path d="M0,4 h22 l2,26 a2.5,2.5 0 0 1 -2.5,2.5 h-21 a2.5,2.5 0 0 1 -2.5,-2.5 z" fill="url(#bb-bagGrad)"/>
        <path d="M6,4 v-3 a5,5 0 0 1 10,0 v3" fill="none" stroke="#9C6C2E" stroke-width="2"/>
        <circle cx="7" cy="2" r="3.6" fill="#8BC53F"/>
        <circle cx="15" cy="1" r="3" fill="#E8804F"/>
        <rect x="17" y="-2" width="5" height="7" rx="1.4" fill="#EFE3C8"/>
      </g>
      <!-- right grocery bag (lime branded) -->
      <g transform="translate(32,64)">
        <path d="M0,4 h22 l2,26 a2.5,2.5 0 0 1 -2.5,2.5 h-21 a2.5,2.5 0 0 1 -2.5,-2.5 z" fill="url(#bb-limeBox)"/>
        <path d="M6,4 v-3 a5,5 0 0 1 10,0 v3" fill="none" stroke="#5F8F24" stroke-width="2"/>
        <path d="M6,14 h12 l1,10 h-14 z" fill="#12224E" opacity=".85"/>
        <path d="M9,14 v-2.4 a3,3 0 0 1 6,0 v2.4" fill="none" stroke="#12224E" stroke-width="1.6" stroke-linecap="round"/>
      </g>
      <!-- neck + head -->
      <rect x="18" y="24" width="9" height="6" fill="#D3A377"/>
      <circle cx="22" cy="16" r="12.5" fill="#E8BC8E"/>
      <circle cx="10.5" cy="17" r="2.6" fill="#D3A377"/>
      <circle cx="17.5" cy="15.5" r="1.7" fill="#16244F"/>
      <circle cx="26.5" cy="15.5" r="1.7" fill="#16244F"/>
      <path d="M15.6,11.4 q2,-1.4 4,0" fill="none" stroke="#16244F" stroke-width="1.3" stroke-linecap="round"/>
      <path d="M24.4,11.4 q2,-1.4 4,0" fill="none" stroke="#16244F" stroke-width="1.3" stroke-linecap="round"/>
      <path d="M17.5,20.4 q4.5,3.6 9,0" fill="none" stroke="#16244F" stroke-width="1.6" stroke-linecap="round"/>
      <!-- cap -->
      <path d="M9.6,10.5 a12.5,12 0 0 1 25,0 z" fill="#16244F"/>
      <path d="M9.6,10.5 h25 q6,0 7,4 h-32 z" fill="#8BC53F"/>
      <circle cx="22" cy="4.6" r="2" fill="#8BC53F"/>
    </g>

    <!-- ============ DELIVERY SCOOTER + RIDER ============ -->
    <ellipse cx="336" cy="398" rx="66" ry="10" fill="url(#bb-groundShade)"/>
    <g transform="translate(292,325)">
      <!-- rear delivery box on rack -->
      <rect x="-2" y="0" width="27" height="26" rx="5" fill="url(#bb-limeBox)"/>
      <rect x="-2" y="0" width="27" height="26" rx="5" fill="none" stroke="#5F8F24" stroke-width="1.2"/>
      <path d="M5,11 h13 l1.4,10 h-15.8 z" fill="#12224E" opacity=".85"/>
      <path d="M8,11 v-2.6 a3.5,3.5 0 0 1 7,0 v2.6" fill="none" stroke="#12224E" stroke-width="1.8" stroke-linecap="round"/>
      <rect x="-1" y="26" width="25" height="3.5" rx="1.8" fill="#12224E"/>
      <line x1="4" y1="29" x2="7" y2="34" stroke="#12224E" stroke-width="2.4" stroke-linecap="round"/>
      <line x1="19" y1="29" x2="16" y2="34" stroke="#12224E" stroke-width="2.4" stroke-linecap="round"/>

      <!-- rear body panel -->
      <path d="M4,30 q0,-6 8,-6 h24 q7,0 9,7 l3,15 q1,6 -6,6 h-30 q-8,0 -8,-8 z" fill="#1B2C5E"/>
      <!-- seat -->
      <path d="M12,24 h32 q5,0 5,4 v3 h-42 v-3 q0,-4 5,-4 z" fill="#0F1D42"/>
      <!-- floorboard -->
      <rect x="44" y="46" width="24" height="7" rx="2" fill="#1B2C5E"/>
      <!-- leg shield / front mask -->
      <path d="M66,53 q4,-22 8,-32 q2,-5 7,-4 q4,1 3,6 l-6,30 z" fill="#243C79"/>
      <!-- handlebar -->
      <path d="M78,20 q6,-3 11,-1" fill="none" stroke="#0F1D42" stroke-width="4" stroke-linecap="round"/>
      <!-- mirror -->
      <line x1="86" y1="18" x2="88" y2="9" stroke="#0F1D42" stroke-width="2"/>
      <ellipse cx="88" cy="7" rx="4" ry="3" fill="#0F1D42"/>
      <!-- headlight -->
      <ellipse cx="80" cy="27" rx="6" ry="5" fill="#FFF0C4"/>
      <circle cx="80" cy="26" r="2" fill="#FFFFFF"/>
      <!-- fork -->
      <line x1="79" y1="32" x2="86" y2="56" stroke="#0F1D42" stroke-width="4" stroke-linecap="round"/>
      <!-- wheels -->
      <circle cx="18" cy="56" r="15" fill="#0A1330"/>
      <circle cx="18" cy="56" r="14" fill="none" stroke="#1B2C5E" stroke-width="2"/>
      <circle cx="18" cy="56" r="6.5" fill="#8BC53F"/>
      <circle cx="18" cy="56" r="2.2" fill="#12224E"/>
      <circle cx="86" cy="56" r="15" fill="#0A1330"/>
      <circle cx="86" cy="56" r="14" fill="none" stroke="#1B2C5E" stroke-width="2"/>
      <circle cx="86" cy="56" r="6.5" fill="#8BC53F"/>
      <circle cx="86" cy="56" r="2.2" fill="#12224E"/>

      <!-- ===== RIDER ===== -->
      <!-- rear leg (behind body) -->
      <path d="M40,26 q10,10 14,20" fill="none" stroke="#16244F" stroke-width="10" stroke-linecap="round"/>
      <!-- torso leaning forward -->
      <path d="M32,22 q-2,-16 8,-22 q10,-6 16,3 q4,6 -2,10 l-14,10 z" fill="url(#bb-uniform)"/>
      <!-- chest badge -->
      <circle cx="44" cy="6" r="5" fill="#8BC53F"/>
      <path d="M41,3.4 h6 l.8,5.6 a.9,.9 0 0 1 -.9,.9 h-5.8 a.9,.9 0 0 1 -.9,-.9 z" fill="#16244F"/>
      <!-- front leg to floorboard -->
      <path d="M44,20 q12,8 16,24" fill="none" stroke="#1B2C5E" stroke-width="10" stroke-linecap="round"/>
      <path d="M56,44 h12 a3,3 0 0 1 3,3 v2 h-15 z" fill="#0A1330"/>
      <!-- arm to handlebar -->
      <path d="M50,0 q16,6 26,17" fill="none" stroke="#2A4585" stroke-width="8" stroke-linecap="round"/>
      <circle cx="78" cy="19" r="4.6" fill="#E8BC8E"/>
      <!-- head + helmet -->
      <circle cx="50" cy="-13" r="11.5" fill="#E8BC8E"/>
      <!-- face (three-quarter, facing right) -->
      <circle cx="55.6" cy="-11" r="1.7" fill="#16244F"/>
      <circle cx="47.4" cy="-11" r="1.7" fill="#16244F"/>
      <path d="M45.5,-15 q1.9,-1.3 3.8,0" fill="none" stroke="#16244F" stroke-width="1.2" stroke-linecap="round"/>
      <path d="M53.7,-15 q1.9,-1.3 3.8,0" fill="none" stroke="#16244F" stroke-width="1.2" stroke-linecap="round"/>
      <path d="M47.2,-6.4 q4,3.2 8,0" fill="none" stroke="#16244F" stroke-width="1.5" stroke-linecap="round"/>
      <!-- helmet shell sits above the brow -->
      <path d="M38.4,-16.5 a11.8,12 0 0 1 23.6,0 z" fill="#8BC53F"/>
      <path d="M38.4,-16.5 h23.6 q6,0 7,3.4 h-30.6 z" fill="#7CB232"/>
      <path d="M38.4,-16.5 a11.8,12 0 0 1 23.6,0" fill="none" stroke="#6FA22B" stroke-width="1.3"/>
      <!-- chin strap -->
      <path d="M41.5,-9 q1.5,7 8.5,7.6" fill="none" stroke="#12224E" stroke-width="1.6" stroke-linecap="round"/>
      <!-- speed lines -->
      <g stroke="#8BC53F" stroke-linecap="round" opacity=".5">
        <line x1="-22" y1="16" x2="-6" y2="16" stroke-width="2.6"/>
        <line x1="-30" y1="28" x2="-10" y2="28" stroke-width="2.2"/>
        <line x1="-18" y1="40" x2="-4" y2="40" stroke-width="2"/>
      </g>
    </g>

    <!-- ============ WAVE ============ -->
    <path d="M0,452 C64,420 128,492 208,472 C282,454 332,420 400,444 L400,620 L0,620 Z"
          fill="#8BC53F" opacity=".22"/>
    <path d="M0,470 C70,438 130,508 210,490 C280,474 332,436 400,462 L400,620 L0,620 Z"
          fill="url(#bb-waveMain)"/>
  </svg>
</template>
