import type L from 'leaflet'

// Mapbox's "streets" style when a token is configured (free tier, no billing needed);
// falls back to the key-less CARTO tiles otherwise so maps still work either way.
const MAPBOX_TOKEN = import.meta.env.VITE_MAPBOX_TOKEN as string | undefined

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
