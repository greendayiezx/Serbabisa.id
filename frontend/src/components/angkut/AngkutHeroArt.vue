<template>
  <!--
    Hero ilustrasi BisaAngkut — flat vector / 2D, tema malam:
    langit navy gradient, bulan, bintang, siluet kota, truk box navy
    dengan logo & brand BisaAngkut, kurir di samping kanan.
  -->
  <svg
    xmlns="http://www.w3.org/2000/svg"
    viewBox="0 0 400 620"
    preserveAspectRatio="xMidYMax slice"
    role="img"
    aria-label="Ilustrasi truk BisaAngkut dan dua kurir di jalan kota pada malam hari"
    class="block w-full h-auto"
  >
    <defs>
      <linearGradient id="sky" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#070E26"/>
        <stop offset="38%" stop-color="#0D1839"/>
        <stop offset="72%" stop-color="#152859"/>
        <stop offset="100%" stop-color="#1F3A76"/>
      </linearGradient>

      <radialGradient id="moonGlow" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#F6E9BE" stop-opacity="0.30"/>
        <stop offset="45%" stop-color="#F6E9BE" stop-opacity="0.10"/>
        <stop offset="100%" stop-color="#F6E9BE" stop-opacity="0"/>
      </radialGradient>

      <radialGradient id="horizonHaze" cx="50%" cy="100%" r="70%">
        <stop offset="0%" stop-color="#4A7FD6" stop-opacity="0.28"/>
        <stop offset="60%" stop-color="#2B4E9E" stop-opacity="0.10"/>
        <stop offset="100%" stop-color="#2B4E9E" stop-opacity="0"/>
      </radialGradient>

      <linearGradient id="bldgFar" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#22376E"/>
        <stop offset="100%" stop-color="#182A57"/>
      </linearGradient>
      <linearGradient id="bldgMid" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#182B5C"/>
        <stop offset="100%" stop-color="#101E45"/>
      </linearGradient>
      <linearGradient id="bldgNear" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#101D42"/>
        <stop offset="100%" stop-color="#08122E"/>
      </linearGradient>

      <linearGradient id="boxBody" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#263D7C"/>
        <stop offset="55%" stop-color="#1B2C5E"/>
        <stop offset="100%" stop-color="#152449"/>
      </linearGradient>
      <linearGradient id="cabBody" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#28407F"/>
        <stop offset="60%" stop-color="#1B2C5E"/>
        <stop offset="100%" stop-color="#152449"/>
      </linearGradient>
      <linearGradient id="glassGrad" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0%" stop-color="#BFE4F5"/>
        <stop offset="55%" stop-color="#7FC0E4"/>
        <stop offset="100%" stop-color="#4E8FC4"/>
      </linearGradient>
      <linearGradient id="uniform" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#2A4585"/>
        <stop offset="100%" stop-color="#1B2C5E"/>
      </linearGradient>
      <linearGradient id="parcel" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#D9A967"/>
        <stop offset="100%" stop-color="#B8853F"/>
      </linearGradient>

      <linearGradient id="waveMain" x1="0" y1="0" x2="0" y2="1">
        <stop offset="0%" stop-color="#A6DD5C"/>
        <stop offset="100%" stop-color="#7CB232"/>
      </linearGradient>

      <linearGradient id="beam" x1="0" y1="0" x2="1" y2="0">
        <stop offset="0%" stop-color="#FFF0C4" stop-opacity="0.42"/>
        <stop offset="100%" stop-color="#FFF0C4" stop-opacity="0"/>
      </linearGradient>

      <radialGradient id="lampGlow" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#B7E36B" stop-opacity="0.42"/>
        <stop offset="100%" stop-color="#B7E36B" stop-opacity="0"/>
      </radialGradient>

      <radialGradient id="groundShade" cx="50%" cy="50%" r="50%">
        <stop offset="0%" stop-color="#03071A" stop-opacity="0.65"/>
        <stop offset="100%" stop-color="#03071A" stop-opacity="0"/>
      </radialGradient>
    </defs>

    <!-- ============ SKY ============ -->
    <rect width="400" height="620" fill="url(#sky)"/>
    <rect y="200" width="400" height="200" fill="url(#horizonHaze)"/>

    <!-- Stars -->
    <g fill="#FFFFFF">
      <circle cx="30" cy="48" r="1.5" opacity=".85"/>
      <circle cx="72" cy="92" r="1" opacity=".55"/>
      <circle cx="112" cy="36" r="1.3" opacity=".75"/>
      <circle cx="158" cy="74" r=".9" opacity=".5"/>
      <circle cx="196" cy="30" r="1.5" opacity=".8"/>
      <circle cx="236" cy="62" r="1" opacity=".6"/>
      <circle cx="20" cy="126" r="1" opacity=".45"/>
      <circle cx="146" cy="116" r="1.2" opacity=".6"/>
      <circle cx="266" cy="26" r="1.1" opacity=".7"/>
      <circle cx="378" cy="146" r="1.3" opacity=".55"/>
      <circle cx="12" cy="192" r="1.1" opacity=".45"/>
      <circle cx="92" cy="148" r=".9" opacity=".45"/>
      <circle cx="212" cy="104" r="1" opacity=".5"/>
      <circle cx="308" cy="160" r="1" opacity=".4"/>
      <circle cx="52" cy="20" r="1" opacity=".6"/>
    </g>
    <!-- Sparkle stars -->
    <g fill="#FFFFFF" opacity=".9">
      <path d="M132,62 l1.6,4.4 4.4,1.6 -4.4,1.6 -1.6,4.4 -1.6,-4.4 -4.4,-1.6 4.4,-1.6z"/>
      <path d="M258,120 l1.2,3.4 3.4,1.2 -3.4,1.2 -1.2,3.4 -1.2,-3.4 -3.4,-1.2 3.4,-1.2z" opacity=".7"/>
      <path d="M60,68 l1,2.8 2.8,1 -2.8,1 -1,2.8 -1,-2.8 -2.8,-1 2.8,-1z" opacity=".6"/>
    </g>

    <!-- Moon with glow -->
    <circle cx="322" cy="80" r="78" fill="url(#moonGlow)"/>
    <g>
      <circle cx="322" cy="80" r="30" fill="#F8EEC8"/>
      <circle cx="334" cy="70" r="27" fill="#0A1330"/>
    </g>

    <!-- Thin cloud wisps -->
    <g fill="#4A6BB0" opacity=".16">
      <ellipse cx="100" cy="176" rx="62" ry="7"/>
      <ellipse cx="150" cy="188" rx="40" ry="5"/>
      <ellipse cx="300" cy="164" rx="52" ry="6"/>
    </g>

    <!-- ============ CITY: far layer ============ -->
    <g fill="url(#bldgFar)" opacity=".75">
      <rect x="-8" y="252" width="42" height="150"/>
      <rect x="36" y="232" width="30" height="170"/>
      <rect x="106" y="244" width="34" height="158"/>
      <rect x="176" y="226" width="26" height="176"/>
      <rect x="246" y="248" width="32" height="154"/>
      <rect x="300" y="234" width="28" height="168"/>
      <rect x="356" y="242" width="52" height="160"/>
    </g>
    <g fill="#F7DE84" opacity=".30">
      <rect x="2" y="266" width="5" height="7"/><rect x="16" y="266" width="5" height="7"/>
      <rect x="2" y="286" width="5" height="7"/><rect x="44" y="248" width="5" height="7"/>
      <rect x="56" y="248" width="5" height="7"/><rect x="44" y="272" width="5" height="7"/>
      <rect x="114" y="258" width="5" height="7"/><rect x="128" y="258" width="5" height="7"/>
      <rect x="114" y="280" width="5" height="7"/><rect x="182" y="240" width="5" height="7"/>
      <rect x="182" y="264" width="5" height="7"/><rect x="254" y="262" width="5" height="7"/>
      <rect x="266" y="284" width="5" height="7"/><rect x="308" y="248" width="5" height="7"/>
      <rect x="308" y="272" width="5" height="7"/><rect x="366" y="256" width="5" height="7"/>
      <rect x="382" y="276" width="5" height="7"/>
    </g>

    <!-- ============ CITY: mid layer ============ -->
    <g fill="url(#bldgMid)">
      <!-- left cluster -->
      <rect x="-10" y="286" width="50" height="116"/>
      <rect x="44" y="266" width="36" height="136"/>
      <rect x="84" y="298" width="26" height="104"/>
      <!-- right cluster -->
      <rect x="286" y="272" width="40" height="130"/>
      <rect x="330" y="292" width="30" height="110"/>
      <rect x="364" y="268" width="44" height="134"/>
    </g>
    <!-- rooftop details -->
    <g fill="#101E45">
      <rect x="52" y="256" width="6" height="10"/>
      <rect x="60" y="260" width="12" height="6"/>
      <rect x="374" y="256" width="5" height="12"/>
      <rect x="296" y="264" width="14" height="8"/>
    </g>
    <!-- antenna with red beacon -->
    <line x1="55" y1="240" x2="55" y2="256" stroke="#101E45" stroke-width="2"/>
    <circle cx="55" cy="238" r="2.4" fill="#FF6B6B" opacity=".9"/>
    <line x1="376" y1="242" x2="376" y2="256" stroke="#101E45" stroke-width="2"/>
    <circle cx="376" cy="240" r="2.2" fill="#FF6B6B" opacity=".8"/>

    <g fill="#F7DE84" opacity=".5">
      <rect x="0" y="300" width="6" height="8"/><rect x="14" y="300" width="6" height="8"/>
      <rect x="28" y="300" width="6" height="8"/><rect x="0" y="322" width="6" height="8"/>
      <rect x="28" y="322" width="6" height="8"/><rect x="14" y="344" width="6" height="8"/>
      <rect x="52" y="280" width="6" height="8"/><rect x="66" y="280" width="6" height="8"/>
      <rect x="52" y="302" width="6" height="8"/><rect x="66" y="324" width="6" height="8"/>
      <rect x="92" y="312" width="6" height="8"/><rect x="92" y="334" width="6" height="8"/>
      <rect x="294" y="286" width="6" height="8"/><rect x="308" y="286" width="6" height="8"/>
      <rect x="294" y="308" width="6" height="8"/><rect x="308" y="330" width="6" height="8"/>
      <rect x="338" y="306" width="6" height="8"/><rect x="338" y="328" width="6" height="8"/>
      <rect x="372" y="282" width="6" height="8"/><rect x="386" y="282" width="6" height="8"/>
      <rect x="372" y="304" width="6" height="8"/><rect x="386" y="326" width="6" height="8"/>
    </g>

    <!-- ============ CITY: near layer (small houses, silhouette) ============ -->
    <g fill="url(#bldgNear)">
      <!-- left house with pitched roof -->
      <path d="M-6,346 L28,346 L28,396 L-6,396 Z"/>
      <path d="M-10,348 L11,330 L32,348 Z"/>
      <!-- small shop -->
      <rect x="34" y="356" width="30" height="40"/>
      <path d="M30,358 L49,344 L68,358 Z"/>
      <!-- right house -->
      <path d="M352,344 L386,344 L386,396 L352,396 Z"/>
      <path d="M348,346 L369,328 L390,346 Z"/>
      <rect x="322" y="358" width="28" height="38"/>
    </g>
    <g fill="#B7E36B" opacity=".55">
      <rect x="2" y="360" width="8" height="9" rx="1"/>
      <rect x="16" y="360" width="8" height="9" rx="1"/>
      <rect x="42" y="368" width="8" height="9" rx="1"/>
      <rect x="360" y="358" width="8" height="9" rx="1"/>
      <rect x="374" y="358" width="8" height="9" rx="1"/>
      <rect x="330" y="370" width="8" height="9" rx="1"/>
    </g>

    <!-- Street lamp (left) -->
    <g>
      <circle cx="76" cy="322" r="34" fill="url(#lampGlow)"/>
      <rect x="74" y="322" width="3.5" height="76" fill="#0C1734"/>
      <path d="M75.5,322 q0,-14 16,-14" fill="none" stroke="#0C1734" stroke-width="3.5"/>
      <ellipse cx="92" cy="310" rx="7" ry="4" fill="#CDEB8E"/>
    </g>

    <!-- ============ ROAD ============ -->
    <rect x="0" y="396" width="400" height="66" fill="#0A1330"/>
    <rect x="0" y="396" width="400" height="3" fill="#1B2C5E"/>
    <!-- lane dashes with perspective -->
    <g fill="#3A5296" opacity=".45">
      <rect x="18" y="424" width="26" height="3.5" rx="1.5"/>
      <rect x="70" y="424" width="26" height="3.5" rx="1.5"/>
      <rect x="122" y="424" width="26" height="3.5" rx="1.5"/>
      <rect x="174" y="424" width="26" height="3.5" rx="1.5"/>
      <rect x="226" y="424" width="26" height="3.5" rx="1.5"/>
      <rect x="278" y="424" width="26" height="3.5" rx="1.5"/>
      <rect x="330" y="424" width="26" height="3.5" rx="1.5"/>
    </g>

    <!-- Headlight beam across road -->
    <path d="M300,384 L400,372 L400,400 L300,390 Z" fill="url(#beam)" opacity=".55"/>

    <!-- ============ ROUTE PIN (app context) ============ -->
    <g opacity=".95">
      <path d="M92,232 C130,208 176,208 214,224" fill="none" stroke="#8BC53F" stroke-width="2.4"
            stroke-linecap="round" stroke-dasharray="7 7" opacity=".6"/>
      <circle cx="92" cy="232" r="4" fill="#8BC53F" opacity=".7"/>
      <g transform="translate(214,196)">
        <path d="M12,34 C12,34 24,20 24,12 A12,12 0 1 0 0,12 C0,20 12,34 12,34 Z" fill="#8BC53F"/>
        <circle cx="12" cy="12" r="4.6" fill="#12224E"/>
      </g>
    </g>

    <!-- ============ TRUCK ============ -->
    <ellipse cx="196" cy="398" rx="130" ry="14" fill="url(#groundShade)"/>
    <g transform="translate(88,266)">
      <!-- cargo box -->
      <rect x="0" y="20" width="146" height="82" rx="7" fill="url(#boxBody)"/>
      <rect x="0" y="20" width="146" height="6" rx="3" fill="#33508F" opacity=".8"/>
      <!-- panel seams -->
      <line x1="0" y1="52" x2="146" y2="52" stroke="#16264F" stroke-width="1" opacity=".8"/>
      <line x1="0" y1="80" x2="146" y2="80" stroke="#16264F" stroke-width="1" opacity=".8"/>
      <line x1="46" y1="26" x2="46" y2="96" stroke="#16264F" stroke-width="1" opacity=".5"/>
      <!-- rear door edge -->
      <rect x="0" y="26" width="4" height="70" fill="#101D42"/>
      <!-- brand lockup -->
      <g transform="translate(14,32)">
        <rect width="30" height="30" rx="8.5" fill="#8BC53F"/>
        <path d="M6.5,13.5 L15,8.5 L23.5,13.5 L23.5,22 L6.5,22 Z" fill="none" stroke="#16244F" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>
        <path d="M6.5,13.5 L15,18.5 L23.5,13.5 L23.5,22 L6.5,22 Z" fill="none" stroke="#16244F" stroke-width="2.2" stroke-linejoin="round" stroke-linecap="round"/>
      </g>
      <text x="52" y="52" font-family="Arial, Helvetica, sans-serif" font-weight="800" font-size="15" fill="#FFFFFF" letter-spacing=".2">Bisa<tspan fill="#8BC53F">Angkut</tspan></text>
      <text x="52" y="65" font-family="Arial, Helvetica, sans-serif" font-weight="400" font-size="8" fill="#93A7D6" letter-spacing=".6">CEPAT &amp; AMAN</text>
      <!-- lime skirt stripe -->
      <rect x="0" y="88" width="146" height="8" fill="#8BC53F"/>
      <rect x="0" y="96" width="146" height="6" fill="#12224E"/>

      <!-- cab -->
      <path d="M146,44 L172,44 C180,44 186,47 190,54 L202,74 C205,79 206,83 206,88 L206,96 C206,100 203,102 199,102 L146,102 Z" fill="url(#cabBody)"/>
      <!-- cab roof highlight -->
      <path d="M146,44 L172,44 C178,44 183,46 186,50 L146,50 Z" fill="#33508F" opacity=".75"/>
      <!-- windshield -->
      <path d="M152,52 L171,52 C176,52 179,54 182,59 L189,72 L152,72 Z" fill="url(#glassGrad)"/>
      <!-- glass shine -->
      <path d="M158,52 L165,52 L154,72 L152,72 L152,66 Z" fill="#FFFFFF" opacity=".28"/>
      <!-- cab side seam + handle -->
      <line x1="146" y1="52" x2="146" y2="102" stroke="#16264F" stroke-width="1.4"/>
      <rect x="156" y="80" width="9" height="2.6" rx="1.3" fill="#3E5896"/>
      <!-- mirror -->
      <rect x="186" y="52" width="8" height="7" rx="2" fill="#0C1734"/>
      <line x1="182" y1="55" x2="186" y2="55" stroke="#0C1734" stroke-width="2"/>
      <!-- grille -->
      <g stroke="#0C1734" stroke-width="2" stroke-linecap="round">
        <line x1="194" y1="79" x2="203" y2="79"/>
        <line x1="195" y1="84" x2="204" y2="84"/>
      </g>
      <!-- headlight -->
      <rect x="194" y="88" width="12" height="9" rx="3" fill="#FFF0C4"/>
      <circle cx="200" cy="92" r="2.4" fill="#FFFFFF"/>
      <!-- lime bumper -->
      <path d="M146,96 L199,96 C203,96 206,98 206,101 L206,102 L146,102 Z" fill="#8BC53F"/>

      <!-- chassis -->
      <rect x="4" y="102" width="202" height="8" fill="#08102A"/>
      <!-- mudflap -->
      <rect x="6" y="106" width="10" height="14" rx="2" fill="#0C1734"/>

      <!-- wheel wells -->
      <path d="M18,102 A22,22 0 0 1 62,102 Z" fill="#070D22"/>
      <path d="M156,102 A22,22 0 0 1 200,102 Z" fill="#070D22"/>

      <!-- wheels -->
      <g>
        <circle cx="40" cy="110" r="19" fill="#0A1330"/>
        <circle cx="40" cy="110" r="18" fill="none" stroke="#1B2C5E" stroke-width="2"/>
        <circle cx="40" cy="110" r="9" fill="#8BC53F"/>
        <circle cx="40" cy="110" r="3" fill="#12224E"/>
        <circle cx="178" cy="110" r="19" fill="#0A1330"/>
        <circle cx="178" cy="110" r="18" fill="none" stroke="#1B2C5E" stroke-width="2"/>
        <circle cx="178" cy="110" r="9" fill="#8BC53F"/>
        <circle cx="178" cy="110" r="3" fill="#12224E"/>
      </g>
    </g>

    <!-- ============ COURIER 1 — carrying parcel ============ -->
    <g transform="translate(34,300)">
      <ellipse cx="22" cy="96" rx="26" ry="6" fill="#04081A" opacity=".45"/>
      <!-- legs -->
      <rect x="10" y="68" width="10" height="28" rx="5" fill="#16244F"/>
      <rect x="25" y="68" width="10" height="28" rx="5" fill="#16244F"/>
      <path d="M8,92 h14 a3,3 0 0 1 3,3 v2 h-17 z" fill="#0A1330"/>
      <path d="M23,92 h14 a3,3 0 0 1 3,3 v2 h-17 z" fill="#0A1330"/>
      <!-- torso -->
      <path d="M4,34 q0,-8 8,-8 h21 q8,0 8,8 v30 q0,6 -6,6 h-25 q-6,0 -6,-6 z" fill="url(#uniform)"/>
      <!-- lime chest badge -->
      <circle cx="22" cy="48" r="6" fill="#8BC53F"/>
      <path d="M18.6,47 L22,44.8 L25.4,47 L25.4,51 L18.6,51 Z" fill="#16244F"/>
      <!-- arms under parcel -->
      <rect x="-4" y="38" width="11" height="24" rx="5.5" fill="#2A4585"/>
      <rect x="38" y="38" width="11" height="24" rx="5.5" fill="#2A4585"/>
      <!-- parcel -->
      <rect x="-2" y="26" width="49" height="26" rx="3" fill="url(#parcel)"/>
      <rect x="-2" y="35" width="49" height="5" fill="#9C6C2E" opacity=".8"/>
      <rect x="18" y="26" width="5" height="26" fill="#9C6C2E" opacity=".8"/>
      <!-- hands on parcel -->
      <circle cx="1" cy="46" r="5" fill="#E8BC8E"/>
      <circle cx="44" cy="46" r="5" fill="#E8BC8E"/>
      <!-- neck -->
      <rect x="18" y="24" width="9" height="6" fill="#D3A377"/>
      <!-- head -->
      <circle cx="22" cy="16" r="12.5" fill="#E8BC8E"/>
      <!-- ear -->
      <circle cx="10.5" cy="17" r="2.6" fill="#D3A377"/>
      <!-- face -->
      <circle cx="17.5" cy="15.5" r="1.7" fill="#16244F"/>
      <circle cx="26.5" cy="15.5" r="1.7" fill="#16244F"/>
      <path d="M15.6,11.4 q2,-1.4 4,0" fill="none" stroke="#16244F" stroke-width="1.3" stroke-linecap="round"/>
      <path d="M24.4,11.4 q2,-1.4 4,0" fill="none" stroke="#16244F" stroke-width="1.3" stroke-linecap="round"/>
      <path d="M17.5,20.4 q4.5,3.6 9,0" fill="none" stroke="#16244F" stroke-width="1.6" stroke-linecap="round"/>
      <!-- cap: crown + brim -->
      <path d="M9.6,10.5 a12.5,12 0 0 1 25,0 z" fill="#16244F"/>
      <path d="M9.6,10.5 h25 q6,0 7,4 h-32 z" fill="#8BC53F"/>
      <circle cx="22" cy="4.6" r="2" fill="#8BC53F"/>
    </g>

    <!-- ============ COURIER 2 — waving ============ -->
    <g transform="translate(318,306)">
      <ellipse cx="18" cy="90" rx="23" ry="5.5" fill="#04081A" opacity=".45"/>
      <!-- legs -->
      <rect x="8" y="64" width="9" height="26" rx="4.5" fill="#16244F"/>
      <rect x="21" y="64" width="9" height="26" rx="4.5" fill="#16244F"/>
      <path d="M6,86 h13 a3,3 0 0 1 3,3 v2 h-16 z" fill="#0A1330"/>
      <path d="M19,86 h13 a3,3 0 0 1 3,3 v2 h-16 z" fill="#0A1330"/>
      <!-- torso -->
      <path d="M3,32 q0,-7 7,-7 h18 q7,0 7,7 v27 q0,5 -5,5 h-22 q-5,0 -5,-5 z" fill="url(#uniform)"/>
      <circle cx="19" cy="45" r="5.4" fill="#8BC53F"/>
      <path d="M15.9,44.2 L19,42.2 L22.1,44.2 L22.1,47.8 L15.9,47.8 Z" fill="#16244F"/>
      <!-- left arm down -->
      <rect x="-4" y="36" width="10" height="23" rx="5" fill="#2A4585"/>
      <circle cx="1" cy="60" r="4.4" fill="#E8BC8E"/>
      <!-- right arm waving -->
      <path d="M31,38 q10,-6 12,-18" fill="none" stroke="#2A4585" stroke-width="10" stroke-linecap="round"/>
      <circle cx="44" cy="17" r="5.6" fill="#E8BC8E"/>
      <!-- motion arcs -->
      <path d="M52,12 q3,3 3,7" fill="none" stroke="#8BC53F" stroke-width="1.8" stroke-linecap="round" opacity=".8"/>
      <path d="M56,8 q5,5 5,12" fill="none" stroke="#8BC53F" stroke-width="1.8" stroke-linecap="round" opacity=".5"/>
      <!-- neck -->
      <rect x="15" y="23" width="8" height="6" fill="#D3A377"/>
      <!-- head -->
      <circle cx="19" cy="15" r="11.5" fill="#E8BC8E"/>
      <circle cx="8" cy="16" r="2.4" fill="#D3A377"/>
      <circle cx="15" cy="14.5" r="1.6" fill="#16244F"/>
      <circle cx="23.4" cy="14.5" r="1.6" fill="#16244F"/>
      <path d="M13.2,10.6 q1.9,-1.3 3.8,0" fill="none" stroke="#16244F" stroke-width="1.2" stroke-linecap="round"/>
      <path d="M21.5,10.6 q1.9,-1.3 3.8,0" fill="none" stroke="#16244F" stroke-width="1.2" stroke-linecap="round"/>
      <path d="M14.8,19.2 q4.2,3.4 8.4,0" fill="none" stroke="#16244F" stroke-width="1.5" stroke-linecap="round"/>
      <!-- cap -->
      <path d="M7.4,9.6 a11.6,11 0 0 1 23.2,0 z" fill="#16244F"/>
      <path d="M7.4,9.6 h23.2 q5.5,0 6.4,3.7 h-29.6 z" fill="#8BC53F"/>
      <circle cx="19" cy="4.2" r="1.9" fill="#8BC53F"/>
    </g>

    <!-- Stacked parcels on the kerb, far right -->
    <g transform="translate(364,368)">
      <ellipse cx="13" cy="34" rx="17" ry="4" fill="#04081A" opacity=".4"/>
      <rect x="0" y="15" width="26" height="17" rx="2.5" fill="url(#parcel)"/>
      <rect x="0" y="21" width="26" height="3.6" fill="#9C6C2E" opacity=".8"/>
      <rect x="4" y="1" width="19" height="14" rx="2.5" fill="#C79A5A"/>
      <rect x="4" y="6" width="19" height="3.2" fill="#8B6636" opacity=".8"/>
    </g>

    <!-- ============ WAVE (layered, seamless full-width) ============ -->
    <path d="M0,452 C64,420 128,492 208,472 C282,454 332,420 400,444 L400,620 L0,620 Z"
          fill="#8BC53F" opacity=".22"/>
    <path d="M0,470 C70,438 130,508 210,490 C280,474 332,436 400,462 L400,620 L0,620 Z"
          fill="url(#waveMain)"/>
  </svg>
</template>
