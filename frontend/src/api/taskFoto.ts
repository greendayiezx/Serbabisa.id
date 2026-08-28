import apiClient from './client'

/**
 * Unggah foto pendukung sebuah tugas.
 *
 * Terpisah dari pembuatan tugas: tugasnya dibuat lebih dulu, fotonya menyusul.
 * Kalau unggahannya gagal, permintaan penawaran yang sudah tercatat tidak ikut
 * batal — dan halaman menyebutkan kegagalannya apa adanya, bukan diam-diam
 * menganggap fotonya terkirim.
 */
export interface HasilUnggahFoto {
  foto: string[]
  url: string[]
}

export async function unggahFotoTugas(taskId: number, berkas: File[]): Promise<HasilUnggahFoto> {
  const form = new FormData()
  for (const f of berkas) form.append('foto[]', f)

  const { data } = await apiClient.post<HasilUnggahFoto>(`/tasks/${taskId}/foto`, form)
  return data
}
