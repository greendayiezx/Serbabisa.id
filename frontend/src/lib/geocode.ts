/**
 * Pencarian & penerjemahan koordinat jadi alamat.
 *
 * URUTAN PENYEDIA DITENTUKAN OLEH PENGUKURAN, bukan oleh anggapan bahwa yang
 * berbayar pasti lebih lengkap. Token Mapbox yang dipakai proyek ini diuji
 * langsung pada tiga titik di Jakarta, dan hasilnya:
 *
 *   reverse (Grand Indonesia)  types=poi  → 0 fitur
 *   reverse (Grand Indonesia)  bawaan     → "10310, Jakarta, Indonesia" (kode pos)
 *   cari "Grand Indonesia"     types=poi  → 0 fitur
 *   cari "Grand Indonesia"     tanpa types→ "Indonesia" (negara), "Menanggal" (Mojokerto)
 *
 * Titik yang sama di Nominatim/OpenStreetMap mengembalikan "Uniqlo, Jalan Teluk
 * Betung, Menteng, Jakarta Pusat". Jadi tokennya memang hanya melayani petak
 * peta, bukan data tempat — dan itulah sebab seluruh aplikasi hanya pernah
 * menampilkan nama jalan atau kode pos.
 *
 * Karena itu NOMINATIM DIPAKAI LEBIH DULU, dan Mapbox hanya sebagai cadangan
 * kalau Nominatim tidak menjawab. Kalau kelak tokennya diganti dengan yang
 * mengaktifkan Geocoding API, urutannya tinggal ditukar di satu tempat: SUMBER.
 *
 * CATATAN OPERASIONAL: layanan Nominatim publik dibatasi ±1 permintaan/detik dan
 * tidak ditujukan untuk lalu lintas produksi. Pemanggilan di aplikasi ini sudah
 * diberi jeda (400 ms untuk pencarian, 500 ms untuk geseran pin), tapi begitu
 * pengguna nyata bertambah, layanannya harus dipasang sendiri atau diganti
 * penyedia berbayar.
 */

const MAPBOX_TOKEN = import.meta.env.VITE_MAPBOX_TOKEN as string | undefined

/** Urutan percobaan. Yang pertama menjawab dengan isi, itu yang dipakai. */
const SUMBER = ['nominatim', 'mapbox'] as const

/**
 * Sejauh apa sebuah tempat masih boleh disebut "titik ini", dalam meter.
 *
 * 90 m kira-kira satu blok gedung: cukup untuk menangkap gedung yang pinnya
 * jatuh di halaman parkirnya, tapi tidak sampai mengklaim gedung seberang.
 */
const RADIUS_TEMPAT = 90

export interface HasilLokasi {
  /** Baris utama: nama tempat kalau ada, kalau tidak jalannya. */
  nama: string
  /** Baris kedua: alamat, sudah dirapikan. */
  alamat: string
  /** Gabungan keduanya — untuk tempat yang hanya punya satu baris. */
  label: string
  lat: number
  lng: number
  /** Jenis tempat dari penyedia (mis. "mall", "restaurant"), kalau ada. */
  kategori?: string
}

export interface TempatRinci {
  nama: string | null
  alamat: string
  label: string
}

/** Label darurat saat alamatnya tidak bisa diambil — koordinat tetap benar. */
export function labelKoordinat(lat: number, lng: number): string {
  return `Lokasi pada ${lat.toFixed(5)}, ${lng.toFixed(5)}`
}

/** Jarak dua titik di permukaan bumi, dalam meter. */
function jarakMeter(aLat: number, aLng: number, bLat: number, bLng: number): number {
  const R = 6_371_000
  const rad = (d: number) => (d * Math.PI) / 180
  const dLat = rad(bLat - aLat)
  const dLng = rad(bLng - aLng)
  const s =
    Math.sin(dLat / 2) ** 2 + Math.cos(rad(aLat)) * Math.cos(rad(bLat)) * Math.sin(dLng / 2) ** 2
  return 2 * R * Math.asin(Math.sqrt(s))
}

/**
 * Permintaan dengan batas waktu.
 *
 * Layanan geocoding sesekali menggantung. Tanpa batas waktu, label pin bisa
 * tertulis "Membaca alamat…" selamanya dan pengguna tidak tahu harus menunggu
 * atau menyerah.
 */
async function ambilJson(url: string, ms = 6000): Promise<unknown> {
  const pembatal = new AbortController()
  const jam = setTimeout(() => pembatal.abort(), ms)
  try {
    const res = await fetch(url, { signal: pembatal.signal })
    if (!res.ok) return null
    return await res.json()
  } finally {
    clearTimeout(jam)
  }
}

/** Buang "Nama Tempat, " yang terlanjur diulang di depan alamatnya. */
function tanpaAwalan(alamat: string, nama: string | null): string {
  if (!nama) return alamat
  const awalan = `${nama}, `
  return alamat.startsWith(awalan) ? alamat.slice(awalan.length) : alamat
}

function susun(nama: string | null, alamat: string): string {
  if (!nama) return alamat
  const sisa = tanpaAwalan(alamat, nama)
  return sisa && sisa !== nama ? `${nama}, ${sisa}` : nama
}

/**
 * Susun alamat dari komponennya, bukan memakai `display_name` mentah.
 *
 * `display_name` Nominatim menyertakan segalanya sampai "Jawa, Indonesia" dan
 * nomor RT/RW — di layar ponsel itu tiga baris yang sebagian besar tidak
 * membantu orang menemukan tempatnya. Yang disisakan: jalan dan nomor,
 * kelurahan, kecamatan, kota, kode pos.
 */
function rapikanAlamat(a: AlamatNominatim | undefined, cadangan: string): string {
  if (!a) return cadangan

  const jalan = [a.road, a.house_number].filter(Boolean).join(' No. ')
  const bagian = [
    jalan || a.pedestrian || a.hamlet,
    a.village || a.neighbourhood || a.quarter || a.suburb,
    a.city_district || a.subdistrict || a.municipality,
    a.city || a.town || a.county,
    a.postcode,
  ].filter((x): x is string => Boolean(x))

  // Nominatim sering mengulang nama yang sama di dua tingkat administratif.
  const unik = bagian.filter((x, i) => bagian.indexOf(x) === i)

  return unik.length ? unik.join(', ') : cadangan
}

/* ------------------------------------------------------------------ *
 * Koordinat → alamat
 * ------------------------------------------------------------------ */

async function reverseNominatim(lat: number, lng: number): Promise<TempatRinci | null> {
  /*
   * zoom=18 meminta ketelitian setingkat bangunan; tanpa itu Nominatim berhenti
   * di jalan. namedetails membawa nama resmi tempatnya, addressdetails membawa
   * komponen alamat yang dipakai rapikanAlamat.
   */
  const data = (await ambilJson(
    `https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat=${lat}&lon=${lng}` +
      '&zoom=18&addressdetails=1&namedetails=1&accept-language=id',
  )) as NominatimBalikan | null

  if (!data?.display_name) return null

  const nama = data.name || data.namedetails?.name || null
  const alamat = rapikanAlamat(data.address, tanpaAwalan(data.display_name, nama))

  return { nama, alamat, label: susun(nama, alamat) }
}

async function reverseMapbox(lat: number, lng: number): Promise<TempatRinci | null> {
  if (!MAPBOX_TOKEN) return null

  const dasar = `https://api.mapbox.com/geocoding/v5/mapbox.places/${lng},${lat}.json?access_token=${MAPBOX_TOKEN}&language=id&limit=1`

  // Dua permintaan berbarengan: satu khusus tempat, satu untuk alamat — Mapbox
  // tidak mengembalikan keduanya sekaligus.
  const [tempat, alamat] = await Promise.all([
    ambilJson(`${dasar}&types=poi`).catch(() => null),
    ambilJson(dasar).catch(() => null),
  ])

  const fTempat = (tempat as MapboxJawaban)?.features?.[0]
  const fAlamat = (alamat as MapboxJawaban)?.features?.[0]
  if (!fTempat && !fAlamat) return null

  let nama: string | null = null
  if (fTempat?.center) {
    const jarak = jarakMeter(lat, lng, fTempat.center[1], fTempat.center[0])
    if (jarak <= RADIUS_TEMPAT) nama = fTempat.text ?? null
  }

  const teks = fAlamat?.place_name ?? fTempat?.place_name
  if (!teks) return null

  return { nama, alamat: tanpaAwalan(teks, nama), label: susun(nama, teks) }
}

/**
 * Alamat sebuah titik, terpisah antara nama tempat dan alamatnya.
 *
 * Dipakai tampilan yang ingin menebalkan nama tempat di baris pertama; yang
 * hanya butuh satu baris memakai `reverseGeocode` di bawahnya.
 */
export async function reverseGeocodeRinci(lat: number, lng: number): Promise<TempatRinci> {
  for (const sumber of SUMBER) {
    try {
      const hasil =
        sumber === 'nominatim' ? await reverseNominatim(lat, lng) : await reverseMapbox(lat, lng)
      if (hasil) return hasil
    } catch {
      // Penyedia ini gagal; coba yang berikutnya.
    }
  }

  // Jaringan mati atau semua layanan menolak: koordinatnya tetap sah dipakai,
  // hanya namanya yang belum diketahui.
  const l = labelKoordinat(lat, lng)
  return { nama: null, alamat: l, label: l }
}

export async function reverseGeocode(lat: number, lng: number): Promise<string> {
  return (await reverseGeocodeRinci(lat, lng)).label
}

/* ------------------------------------------------------------------ *
 * Kata kunci → daftar tempat
 * ------------------------------------------------------------------ */

/**
 * Cari lewat Nominatim, mengutamakan sekitar `dekat`.
 *
 * Bias jarak di Nominatim BUKAN sekadar pengurut: `bounded=0` hanya memberi
 * viewbox sebagai saran, dan hasilnya sering tetap melenceng jauh. Diuji pada
 * kata "Grand Indonesia" dari titik di Sudirman:
 *
 *   bounded=0 → "Indonesia Convention Exhibition" (BSD), "Kereta Cepat…" (Bekasi)
 *   bounded=1 → "Grand Indonesia Shopping Town", "Grand Indonesia Sky Bridge"
 *
 * Jadi pencarian dikurung dulu ke sekitar pengguna. Kalau hasilnya sedikit —
 * pertanda yang dicari memang di luar kota — barulah pencarian diulang tanpa
 * kurungan, dan hasilnya ditambahkan di belakang. Dua permintaan itu hanya
 * terjadi pada kasus jarang; pemakaian biasa tetap satu permintaan.
 */
async function cariNominatim(q: string, dekat?: Titik): Promise<HasilLokasi[]> {
  const mentah = dekat ? await cariOsm(q, dekat, true) : []

  if (!dekat || mentah.length < 3) {
    const luas = await cariOsm(q, dekat, false)
    for (const item of luas) {
      const kembar = mentah.some(
        (m) => Math.abs(Number(m.lat) - Number(item.lat)) < 1e-5 && Math.abs(Number(m.lon) - Number(item.lon)) < 1e-5,
      )
      if (!kembar) mentah.push(item)
    }
  }

  return mentah.slice(0, 8).map((d) => {
    const penuh = d.display_name ?? ''
    const nama = d.name || d.namedetails?.name || penuh.split(',')[0] || ''
    const alamat = rapikanAlamat(d.address, tanpaAwalan(penuh, nama))

    return {
      nama,
      alamat,
      label: susun(nama, alamat),
      lat: parseFloat(String(d.lat)),
      lng: parseFloat(String(d.lon)),
      kategori: d.type,
    }
  })
}

/** Satu permintaan ke Nominatim; `kurung` menahan hasil di dalam viewbox. */
async function cariOsm(q: string, dekat: Titik | undefined, kurung: boolean): Promise<NominatimBalikan[]> {
  const params = new URLSearchParams({
    format: 'jsonv2',
    q,
    countrycodes: 'id',
    limit: '8',
    addressdetails: '1',
    namedetails: '1',
    'accept-language': 'id',
  })

  if (dekat) {
    // Kotak ±0,15° ≈ 16 km — seluas jangkauan wajar satu pesanan.
    const d = 0.15
    params.set('viewbox', `${dekat.lng - d},${dekat.lat - d},${dekat.lng + d},${dekat.lat + d}`)
    params.set('bounded', kurung ? '1' : '0')
  }

  const data = await ambilJson(`https://nominatim.openstreetmap.org/search?${params}`)
  return Array.isArray(data) ? (data as NominatimBalikan[]) : []
}

async function cariMapbox(q: string, dekat?: Titik): Promise<HasilLokasi[]> {
  if (!MAPBOX_TOKEN) return []

  const params = new URLSearchParams({
    access_token: MAPBOX_TOKEN,
    autocomplete: 'true',
    limit: '8',
    language: 'id',
    country: 'id',
    types: 'poi,address,place,locality,neighborhood,district',
  })
  if (dekat) params.set('proximity', `${dekat.lng},${dekat.lat}`)

  const data = (await ambilJson(
    `https://api.mapbox.com/geocoding/v5/mapbox.places/${encodeURIComponent(q)}.json?${params}`,
  )) as MapboxJawaban | null

  return (data?.features ?? []).map((f) => {
    const nama = f.text ?? f.place_name ?? ''
    return {
      nama,
      alamat: tanpaAwalan(f.place_name ?? '', nama),
      label: f.place_name ?? nama,
      lat: f.center[1],
      lng: f.center[0],
      kategori: f.properties?.category?.split(',')[0],
    }
  })
}

/**
 * Cari tempat berdasarkan kata kunci.
 *
 * `dekat` membiaskan hasil ke sekitar titik itu. Ini yang membedakan pencarian
 * "Indomaret" yang berguna dari yang tidak.
 */
export async function cariLokasi(query: string, dekat?: Titik): Promise<HasilLokasi[]> {
  const q = query.trim()
  if (q.length < 3) return []

  for (const sumber of SUMBER) {
    try {
      const hasil = sumber === 'nominatim' ? await cariNominatim(q, dekat) : await cariMapbox(q, dekat)
      if (hasil.length) return hasil
    } catch {
      // Penyedia ini gagal; coba yang berikutnya.
    }
  }

  return []
}

/* ------------------------------------------------------------------ *
 * Bentuk jawaban penyedia — hanya bidang yang benar-benar dibaca
 * ------------------------------------------------------------------ */

interface Titik {
  lat: number
  lng: number
}

interface MapboxFitur {
  text?: string
  place_name?: string
  center: [number, number]
  properties?: { category?: string }
}

interface MapboxJawaban {
  features?: MapboxFitur[]
}

interface AlamatNominatim {
  house_number?: string
  road?: string
  pedestrian?: string
  hamlet?: string
  neighbourhood?: string
  quarter?: string
  suburb?: string
  village?: string
  subdistrict?: string
  city_district?: string
  municipality?: string
  city?: string
  town?: string
  county?: string
  postcode?: string
}

interface NominatimBalikan {
  name?: string | null
  display_name?: string
  namedetails?: { name?: string }
  address?: AlamatNominatim
  lat?: string | number
  lon?: string | number
  type?: string
}
