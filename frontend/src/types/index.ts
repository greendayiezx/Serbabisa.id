export type Role = 'customer' | 'mitra' | 'admin'

export type StatusVerifikasi = 'unverified' | 'pending' | 'verified' | 'rejected'

export interface User {
  id: number
  name: string
  email: string
  phone: string
  role: Role
  status_verifikasi: StatusVerifikasi
  is_active: boolean
}

export interface Category {
  id: number
  nama: string
  slug: string
  basis_harga: string
  ikon: string | null
  deskripsi: string | null
  is_active: boolean
}

export type TaskTipe = 'fixed' | 'custom'
export type TaskStatus = 'pending' | 'accepted' | 'in_progress' | 'completed' | 'cancelled'

export interface Task {
  id: number
  customer_id: number
  mitra_id: number | null
  category_id: number | null
  tipe: TaskTipe
  judul: string
  deskripsi: string
  status: TaskStatus
  lokasi_alamat: string
  lokasi_lat: number
  lokasi_lng: number
  harga: number | null
  budget: number | null
  catatan: string | null
  created_at: string
  category?: Category | null
  mitra?: Pick<User, 'id' | 'name'> | null
  customer?: Pick<User, 'id' | 'name'>
}

export interface Bid {
  id: number
  task_id: number
  mitra_id: number
  harga_tawaran: number
  pesan: string | null
  status: 'pending' | 'accepted' | 'rejected'
}

export interface ChatMessage {
  id: number
  task_id: number
  sender_id: number
  isi: string
  dibaca_pada: string | null
  created_at: string
}

export interface Wallet {
  id: number
  user_id: number
  saldo: number
}
