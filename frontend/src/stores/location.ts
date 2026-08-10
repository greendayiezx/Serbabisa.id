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

const SAVED_KEY = 'tugasin_saved_places'
const SEARCH_HISTORY_KEY = 'tugasin_search_history'

export const useLocationStore = defineStore('location', () => {
  const draft = ref<TaskLocation | null>(null)
  const recent = ref<PlaceItem[]>([])

  function setDraft(location: TaskLocation) {
    draft.value = location
  }

  function clearDraft() {
    draft.value = null
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
    const key = `${location.lat.toFixed(5)},${location.lng.toFixed(5)}`
    const filtered = list.filter((p) => `${p.lat.toFixed(5)},${p.lng.toFixed(5)}` !== key)
    filtered.unshift({
      id: `history:${key}`,
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

  function savePlace(key: SavedPlaceKey, location: TaskLocation) {
    const saved = loadSavedPlaces()
    saved[key] = location
    localStorage.setItem(SAVED_KEY, JSON.stringify(saved))
  }

  async function fetchRecent() {
    const { data } = await apiClient.get<PlaceItem[]>('/locations/recent')
    recent.value = data
    return data
  }

  return { draft, recent, setDraft, clearDraft, loadSavedPlaces, savePlace, fetchRecent, loadSearchHistory, addSearchHistory }
})