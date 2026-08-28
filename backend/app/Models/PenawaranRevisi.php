<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Permintaan perubahan dari pelanggan atas sebuah penawaran. */
class PenawaranRevisi extends Model
{
    protected $table = 'penawaran_revisi';

    protected $fillable = ['penawaran_id', 'permintaan', 'catatan'];

    protected function casts(): array
    {
        return ['permintaan' => 'array'];
    }

    public function penawaran(): BelongsTo
    {
        return $this->belongsTo(Penawaran::class);
    }
}
