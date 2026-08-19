import { defineStore } from 'pinia'
import { ref, watch } from 'vue'
import { ambilLangganan, berhentiLangganan, berlangganan } from '@/api/langganan'

const STORAGE_KEY = 'tugasin_promo'

export type LanggananId = 'basic' | 'premium' | 'family'

interface PromoState {
  /** Promo voucher yang sedang dipakai di checkout. Hanya satu yang aktif. */
  dipilih: string | null
  /** Paket langganan aktif, null kalau tidak berlangganan. */
  langganan: LanggananId | null
  /**
   * Id baris `subscriptions` di server. WAJIB ikut dikirim saat checkout —
   * tanpa ini server tidak tahu ada langganan dan menagih harga penuh, padahal
   * layar sudah menampilkan harga yang sudah dipotong.
   */
  langgananId: number | null
  /** Jatah gratis ongkir langganan yang sudah dipakai bulan ini. */
  ongkirGratisTerpakai: number
  /** Jatah potongan langganan yang sudah dipakai bulan ini. */
  diskonTerpakai: number
  /** Jumlah transaksi selesai — dasar syarat "transaksi pertama". */
  transaksiSelesai: number
  /** Saldo cashback dari transaksi sebelumnya. */
  saldoCashback: number
}

function kosong(): PromoState {
  return {
    dipilih: null,
    langganan: null,
    langgananId: null,
    ongkirGratisTerpakai: 0,
    diskonTerpakai: 0,
    transaksiSelesai: 0,
    saldoCashback: 0,
  }
}

const angka = (v: unknown) => (Number.isFinite(v) ? Math.max(0, Math.floor(v as number)) : 0)

/** Dibaca per field: isi localStorage bisa dari versi lama atau diubah manual. */
function muat(): PromoState {
  try {
    const mentah = localStorage.getItem(STORAGE_KEY)
    if (!mentah) return kosong()
    const d = JSON.parse(mentah) as Partial<PromoState>
    if (!d || typeof d !== 'object') return kosong()
    const paket = d.langganan
    return {
      dipilih: typeof d.dipilih === 'string' ? d.dipilih : null,
      langganan:
        paket === 'basic' || paket === 'premium' || paket === 'family' ? paket : null,
      langgananId: typeof d.langgananId === 'number' ? d.langgananId : null,
      ongkirGratisTerpakai: angka(d.ongkirGratisTerpakai),
      diskonTerpakai: angka(d.diskonTerpakai),
      transaksiSelesai: angka(d.transaksiSelesai),
      saldoCashback: angka(d.saldoCashback),
    }
  } catch {
    return kosong()
  }
}

export const usePromoStore = defineStore('promo', () => {
  const state = ref<PromoState>(muat())

  watch(
    state,
    (nilai) => {
      try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(nilai))
      } catch {
        // Kuota penuh / storage diblokir — state tetap jalan di memori.
      }
    },
    { deep: true },
  )

  function pilih(id: string | null) {
    state.value.dipilih = id
  }

  /**
   * Berlangganan / berhenti. Server yang menentukan hasilnya; state lokal baru
   * diperbarui setelah responsnya tiba, supaya tidak pernah ada kondisi di mana
   * layar mengaku berlangganan sementara server tidak tahu apa-apa.
   */
  async function setLangganan(paket: LanggananId | null): Promise<void> {
    if (paket === null) {
      await berhentiLangganan()
      state.value.langganan = null
      state.value.langgananId = null
      state.value.ongkirGratisTerpakai = 0
      state.value.diskonTerpakai = 0
      return
    }

    const aktif = await berlangganan(paket)
    terapkan(aktif)
  }

  /** Selaraskan state lokal dengan langganan aktif di server. */
  function terapkan(aktif: Awaited<ReturnType<typeof ambilLangganan>>) {
    if (!aktif) {
      state.value.langganan = null
      state.value.langgananId = null
      return
    }
    state.value.langganan = aktif.plan.kode
    state.value.langgananId = aktif.id
    // Kuota terpakai dihitung dari sisa kuota di server, bukan dari penghitung
    // lokal yang bisa melenceng kalau pengguna memakai dua perangkat.
    state.value.diskonTerpakai = Math.max(0, aktif.plan.kuota_diskon - aktif.sisa_kuota_diskon)
    state.value.ongkirGratisTerpakai = Math.max(0, aktif.plan.kuota_ongkir - aktif.sisa_kuota_ongkir)
  }

  /**
   * Tarik status langganan dari server. Kalau server tidak terjangkau, state
   * lokal dibiarkan apa adanya — checkout tetap akan menolak kalau langganannya
   * ternyata tidak ada, jadi tidak ada risiko salah tagih.
   */
  async function muatLangganan(): Promise<void> {
    try {
      terapkan(await ambilLangganan())
    } catch {
      // Diabaikan: bukan alur yang boleh menghalangi pengguna berbelanja.
    }
  }

  function pakaiOngkirGratis() {
    state.value.ongkirGratisTerpakai += 1
  }

  function pakaiDiskon() {
    state.value.diskonTerpakai += 1
  }

  function catatTransaksi(cashback: number) {
    state.value.transaksiSelesai += 1
    state.value.saldoCashback += angka(cashback)
  }

  function reset() {
    state.value = kosong()
  }

  return { state, pilih, setLangganan, muatLangganan, pakaiOngkirGratis, pakaiDiskon, catatTransaksi, reset }
})
