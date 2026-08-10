import { defineStore } from 'pinia'
import { ref } from 'vue'

export interface Driver {
  id: number
  name: string
  lat: number
  lng: number
  status: 'available' | 'busy'
  rating: number
}

// Fase prototype: data mitra belum disimpan di DB.
// TODO: ganti dengan endpoint backend (mis. GET /drivers/nearby?lat=..&lng=..)
// agar nama, posisi (lat/lng), status & rating dimuat dari server tiap kali peta dibuka.
const MOCK_DRIVERS: Omit<Driver, 'lat' | 'lng'>[] = [
  { id: 1, name: 'Asep', status: 'available', rating: 4.8 },
  { id: 2, name: 'Cecep', status: 'available', rating: 4.7 },
  { id: 3, name: 'Ujang', status: 'busy', rating: 4.9 },
]

const OFFSETS: [number, number][] = [
  [-0.0016, 0.0014],
  [0.0018, 0.0006],
  [-0.0007, -0.0018],
]

export const useDriverStore = defineStore('driver', () => {
  const drivers = ref<Driver[]>([])

  async function loadNearby(lat: number, lng: number) {
    drivers.value = MOCK_DRIVERS.map((d, i) => ({
      ...d,
      lat: lat + OFFSETS[i][0],
      lng: lng + OFFSETS[i][1],
    }))
    return drivers.value
  }

  function clear() {
    drivers.value = []
  }

  return { drivers, loadNearby, clear }
})