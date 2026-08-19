/**
 * Tarif biaya layanan & ongkir BisaBelanja.
 *
 * Dipisah dari komponen supaya angkanya punya satu sumber kebenaran dan bisa
 * diuji tanpa merender halaman.
 */

/** Biaya layanan, flat per pesanan (bukan per item). */
export const BIAYA_LAYANAN = 2500

/**
 * Tarif ongkir per jarak. `sampaiKm` bersifat inklusif — jarak tepat 5 km masuk
 * tier pertama, bukan tier kedua.
 */
export const ONGKIR_TARIF = [
  { sampaiKm: 5, tarif: 3500, label: '0–5 km' },
  { sampaiKm: 10, tarif: 6500, label: '5–10 km' },
  { sampaiKm: 15, tarif: 9500, label: '10–15 km' },
] as const

/**
 * Kenaikan tiap 5 km setelah 15 km. Tarif di atas 15 km belum ditentukan, jadi
 * polanya diteruskan (tiap tier naik Rp3.000) alih-alih dipatok di 9.500 —
 * mematok berarti antar 50 km dihargai sama dengan 15 km.
 */
const KENAIKAN_PER_BAND = 3000
const BAND_KM = 5

/** Ongkir untuk sebuah jarak dalam km. */
export function ongkirUntuk(km: number): number {
  if (!Number.isFinite(km) || km <= 0) return ONGKIR_TARIF[0].tarif
  for (const t of ONGKIR_TARIF) {
    if (km <= t.sampaiKm) return t.tarif
  }
  const bandEkstra = Math.ceil((km - 15) / BAND_KM)
  return ONGKIR_TARIF[ONGKIR_TARIF.length - 1].tarif + bandEkstra * KENAIKAN_PER_BAND
}

/** Label tier untuk ditampilkan di rincian biaya, mis. "5–10 km". */
export function labelJarak(km: number): string {
  if (!Number.isFinite(km) || km <= 0) return ONGKIR_TARIF[0].label
  for (const t of ONGKIR_TARIF) {
    if (km <= t.sampaiKm) return t.label
  }
  const bandEkstra = Math.ceil((km - 15) / BAND_KM)
  const dari = 15 + (bandEkstra - 1) * BAND_KM
  return `${dari}–${dari + BAND_KM} km`
}

/** Jarak garis lurus antara dua koordinat, dalam km. */
export function jarakKm(
  a: { lat: number; lng: number },
  b: { lat: number; lng: number },
): number {
  const R = 6371
  const rad = (d: number) => (d * Math.PI) / 180
  const dLat = rad(b.lat - a.lat)
  const dLng = rad(b.lng - a.lng)
  const h =
    Math.sin(dLat / 2) ** 2 +
    Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(dLng / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(h))
}
