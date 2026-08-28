import L from 'leaflet'

// Mapbox's "streets" style when a token is configured (free tier, no billing needed);
// falls back to the key-less CARTO tiles otherwise so maps still work either way.
const MAPBOX_TOKEN = import.meta.env.VITE_MAPBOX_TOKEN as string | undefined

/**
 * Pin biru yang dipakai semua peta pemilih lokasi.
 *
 * Dibuat sebagai fungsi, bukan konstanta: satu objek L.DivIcon yang sama tidak
 * boleh dipakai dua marker sekaligus - Leaflet menyimpan elemen DOM-nya di
 * dalam ikon itu, jadi marker kedua akan mencuri elemen milik marker pertama.
 *
 * iconAnchor menunjuk ke UJUNG BAWAH pin, bukan tengahnya, supaya titik yang
 * ditunjuk benar-benar koordinat yang dipilih.
 */
export function pinIcon(): L.DivIcon {
  return L.divIcon({
    className: '',
    html:
      '<svg viewBox="0 0 24 24" width="40" height="40" stroke="#1e9bf0" stroke-width="2" fill="rgba(255,255,255,0.95)" stroke-linecap="round" stroke-linejoin="round" style="filter: drop-shadow(0 4px 10px rgba(0,0,0,0.2))">' +
      '<path d="M12 21s7-6.1 7-11a7 7 0 1 0-14 0c0 4.9 7 11 7 11Z"/>' +
      '<circle cx="12" cy="10" r="2.5" fill="#1e9bf0" stroke="none"/></svg>',
    iconSize: [40, 40],
    iconAnchor: [20, 40],
  })
}

export const TILE_URL = MAPBOX_TOKEN
  ? 'https://api.mapbox.com/styles/v1/mapbox/streets-v12/tiles/{z}/{x}/{y}{r}?access_token={accessToken}'
  : 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}{r}.png'

export const TILE_OPTIONS: L.TileLayerOptions & { accessToken?: string } = MAPBOX_TOKEN
  ? {
      maxZoom: 20,
      tileSize: 512,
      zoomOffset: -1,
      accessToken: MAPBOX_TOKEN,
      attribution: '© <a href="https://www.mapbox.com/about/maps/">Mapbox</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }
  : {
      subdomains: 'abcd',
      maxZoom: 20,
      attribution: '© <a href="https://carto.com/">CARTO</a> © <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
    }
