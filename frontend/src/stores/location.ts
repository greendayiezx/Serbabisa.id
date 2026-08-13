import { defineStore } from 'pinia'
import { ref } from 'vue'
import apiClient from '@/api/client'

export interface TaskLocation {
  alamat: string
  lat: number
  lng: number
}

export interface PlaceItem {
  id: string
  label: string
  address: string
  lat: number
  lng: number
  last_used_at: string | null
}

export type SavedPlaceKey = 'home' | 'office'

export type SavePlaceResult = 'saved' | 'duplicate'

export function isSameLocation(a: TaskLocation, b: TaskLocation) {
  return (
    a.alamat.trim().toLowerCase() === b.alamat.trim().toLowerCase() ||
    (Number(a.lat).toFixed(5) === Number(b.lat).toFixed(5) &&
      Number(a.lng).toFixed(5) === Number(b.lng).toFixed(5))
  )
}

const SAVED_KEY = 'tugasin_saved_places'
const SEARCH_HISTORY_KEY = 'tugasin_search_history'
const DRAFT_KEY = 'tugasin_location_draft'

function loadDraft(): TaskLocation | null {
  try {
    const raw = localStorage.getItem(DRAFT_KEY)
    if (!raw) return null
    const parsed = JSON.parse(raw)
    if (parsed && typeof parsed === 'object' && typeof parsed.alamat === 'string') {
      return { alamat: parsed.alamat, lat: Number(parsed.lat), lng: Number(parsed.lng) }
    }
    return null
  } catch {
    return null
  }
}

export const useLocationStore = defineStore('location', () => {
  const draft = ref<TaskLocation | null>(loadDraft())
  const recent = ref<PlaceItem[]>([])

  function setDraft(location: TaskLocation) {
    draft.value = location
    localStorage.setItem(DRAFT_KEY, JSON.stringify(location))
  }

  function clearDraft() {
    draft.value = null
    localStorage.removeItem(DRAFT_KEY)
  }

  function loadSearchHistory(): PlaceItem[] {
    try {
      const raw = localStorage.getItem(SEARCH_HISTORY_KEY)
      if (!raw) return []
      const parsed = JSON.parse(raw)
      return Array.isArray(parsed) ? parsed : []
    } catch {
      return []
    }
  }

  function addSearchHistory(location: TaskLocation): PlaceItem[] {
    const list = loadSearchHistory()
    const coordKey = `${location.lat.toFixed(5)},${location.lng.toFixed(5)}`
    const filtered = list.filter((p) => `${p.lat.toFixed(5)},${p.lng.toFixed(5)}` !== coordKey)
    filtered.unshift({
      id: `history:${coordKey}`,
      label: location.alamat,
      address: location.alamat,
      lat: location.lat,
      lng: location.lng,
      last_used_at: new Date().toISOString(),
    })
    const next = filtered.slice(0, 10)
    localStorage.setItem(SEARCH_HISTORY_KEY, JSON.stringify(next))
    return next
  }

  function loadSavedPlaces(): Partial<Record<SavedPlaceKey, TaskLocation>> {
    try {
      const raw = localStorage.getItem(SAVED_KEY)
      if (!raw) return {}
      const parsed = JSON.parse(raw)
      return typeof parsed === 'object' && parsed !== null ? parsed : {}
    } catch {
      return {}
    }
  }

  function savePlace(key: SavedPlaceKey, location: TaskLocation): SavePlaceResult {
    const saved = loadSavedPlaces()
    for (const place of Object.values(saved) as TaskLocation[]) {
      if (place && isSameLocation(place, location)) return 'duplicate'
    }
    saved[key] = location
    localStorage.setItem(SAVED_KEY, JSON.stringify(saved))
    return 'saved'
  }

  async function fetchRecent() {
    const { data } = await apiClient.get<PlaceItem[]>('/locations/recent')
    recent.value = data
    return data
  }

  function clearSavedPlaces() {
    localStorage.removeItem(SAVED_KEY)
  }

  function clearSearchHistory() {
    localStorage.removeItem(SEARCH_HISTORY_KEY)
  }

  function clearAll() {
    clearSavedPlaces()
    clearSearchHistory()
    clearDraft()
  }

  return { draft, recent, setDraft, clearDraft, loadSavedPlaces, savePlace, fetchRecent, loadSearchHistory, addSearchHistory, isSameLocation, clearSavedPlaces, clearSearchHistory, clearAll }
})