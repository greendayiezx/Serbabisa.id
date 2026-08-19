/**
 * Palet warna langit untuk ilustrasi hero (BisaAngkut & BisaBelanja) mengikuti
 * waktu nyata: pagi, siang, sore, malam.
 *
 * Dipakai bersama oleh kedua komponen SVG supaya nuansa langit, siluet kota,
 * dan pencahayaannya selalu konsisten di antara keduanya.
 */
export type HeroTimeOfDay = 'pagi' | 'siang' | 'sore' | 'malam'

/** Jam -> fase hari. Ambang batasnya sama dengan sapaan "Selamat pagi/siang/sore/malam". */
export function heroTimeOfDayFromHour(hour: number): HeroTimeOfDay {
  if (hour >= 4 && hour < 11) return 'pagi'
  if (hour >= 11 && hour < 15) return 'siang'
  if (hour >= 15 && hour < 18) return 'sore'
  return 'malam'
}

export interface HeroSkyPalette {
  /** 4 stop gradient langit, dari atas ke bawah. */
  sky: [string, string, string, string]
  /** Kabut/pendar di garis horizon. */
  haze: { color: string; inner: number; mid: number }
  /** Gradient siluet gedung: jauh, tengah, dekat. */
  bldgFar: [string, string]
  bldgMid: [string, string]
  bldgNear: [string, string]
  /** Jendela gedung yang menyala. */
  window: { color: string; far: number; mid: number }
  /** Awan tipis. */
  cloud: { color: string; opacity: number }
  /** 0 = bintang disembunyikan sepenuhnya (siang). */
  starOpacity: number
  /** Bulan (sabit) atau matahari (bulat penuh). */
  celestial: 'moon' | 'sun'
  celestialCore: string
  celestialGlow: string
  /** Warna "gigitan" sabit bulan — harus menyatu dengan langit di titik itu. */
  crescentCut: string
  /** Pendar lampu jalan; 0 = lampu mati (siang). */
  lampOpacity: number
  /** Aspal jalan, tepi trotoar, dan marka. */
  road: { asphalt: string; kerb: string; lane: string }
  /** Bayangan di aspal. */
  groundShade: string
  /** Cahaya hangat yang tumpah dari kaca toko (khusus BisaBelanja). */
  spillOpacity: number
}

export const HERO_SKY: Record<HeroTimeOfDay, HeroSkyPalette> = {
  // Fajar: biru lembut di atas, hangat di garis horizon.
  pagi: {
    sky: ['#5AA6DC', '#8CC7EA', '#C4E3F5', '#FBE0BC'],
    haze: { color: '#FFC276', inner: 0.34, mid: 0.14 },
    bldgFar: ['#9DBBD8', '#84A4C6'],
    bldgMid: ['#7797BC', '#6280A6'],
    bldgNear: ['#5B7C9F', '#496787'],
    window: { color: '#FFF0BE', far: 0.16, mid: 0.24 },
    cloud: { color: '#FFFFFF', opacity: 0.5 },
    starOpacity: 0,
    celestial: 'sun',
    celestialCore: '#FFDF9C',
    celestialGlow: '#FFC46B',
    crescentCut: '#8CC7EA',
    lampOpacity: 0.1,
    road: { asphalt: '#5E7791', kerb: '#7C90A9', lane: '#C3D0DF' },
    groundShade: '#2C4262',
    spillOpacity: 0.12,
  },

  // Siang terang: biru pekat, matahari tinggi, tanpa bintang.
  siang: {
    sky: ['#2A82C6', '#54A4DB', '#8DC7EC', '#C9E6F7'],
    haze: { color: '#FFFFFF', inner: 0.24, mid: 0.09 },
    bldgFar: ['#AAC5DD', '#91AFCC'],
    bldgMid: ['#7F9EC0', '#6787AD'],
    bldgNear: ['#6284A9', '#4E6E92'],
    window: { color: '#E4F2FC', far: 0.14, mid: 0.18 },
    cloud: { color: '#FFFFFF', opacity: 0.68 },
    starOpacity: 0,
    celestial: 'sun',
    celestialCore: '#FFF7D4',
    celestialGlow: '#FFE79A',
    crescentCut: '#54A4DB',
    lampOpacity: 0,
    road: { asphalt: '#6C7E95', kerb: '#8A9BB0', lane: '#D2DCE7' },
    groundShade: '#354C6E',
    spillOpacity: 0.08,
  },

  // Senja: jingga keunguan, lampu mulai menyala.
  sore: {
    sky: ['#3B4788', '#82589B', '#DE855E', '#FFC287'],
    haze: { color: '#FF9A55', inner: 0.38, mid: 0.15 },
    bldgFar: ['#5E4C7E', '#4A386C'],
    bldgMid: ['#41305F', '#30234B'],
    bldgNear: ['#2C2045', '#1E1635'],
    window: { color: '#FFD98A', far: 0.4, mid: 0.6 },
    cloud: { color: '#FFB183', opacity: 0.32 },
    starOpacity: 0.25,
    celestial: 'sun',
    celestialCore: '#FFD27E',
    celestialGlow: '#FF9C58',
    crescentCut: '#82589B',
    lampOpacity: 0.5,
    road: { asphalt: '#3A2E4E', kerb: '#503F66', lane: '#836C97' },
    groundShade: '#241730',
    spillOpacity: 0.22,
  },

  // Malam: navy pekat, bulan sabit, bintang penuh (tampilan asli).
  malam: {
    sky: ['#070E26', '#0D1839', '#152859', '#1F3A76'],
    haze: { color: '#4A7FD6', inner: 0.28, mid: 0.1 },
    bldgFar: ['#22376E', '#182A57'],
    bldgMid: ['#182B5C', '#101E45'],
    bldgNear: ['#101D42', '#08122E'],
    window: { color: '#F7DE84', far: 0.3, mid: 0.5 },
    cloud: { color: '#4A6BB0', opacity: 0.16 },
    starOpacity: 1,
    celestial: 'moon',
    celestialCore: '#F8EEC8',
    celestialGlow: '#F6E9BE',
    crescentCut: '#0A1330',
    lampOpacity: 0.42,
    road: { asphalt: '#0A1330', kerb: '#1B2C5E', lane: '#3A5296' },
    groundShade: '#03071A',
    spillOpacity: 0.3,
  },
}
