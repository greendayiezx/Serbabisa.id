<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu pilihan paket di dalam sebuah penawaran. */
class PenawaranPaket extends Model
{
    protected $table = 'penawaran_paket';

    protected $fillable = [
        'penawaran_id', 'kode', 'nama', 'ringkas', 'isi',
        'harga_per_kunjungan', 'kunjungan_per_bulan', 'harga_bulanan', 'disarankan',
    ];

    protected function casts(): array
    {
        return ['isi' => 'array', 'disarankan' => 'boolean'];
    }

    public function penawaran(): BelongsTo
    {
        return $this->belongsTo(Penawaran::class);
    }
}
