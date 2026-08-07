<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MitraProfile extends Model
{
    /** @use HasFactory<\Database\Factories\MitraProfileFactory> */
    use HasFactory;

    protected $fillable = [
        'user_id',
        'no_ktp',
        'foto_ktp',
        'foto_selfie',
        'skill',
        'rating_avg',
        'rating_count',
        'lokasi_lat',
        'lokasi_lng',
    ];

    protected function casts(): array
    {
        return [
            'skill' => 'array',
            'rating_avg' => 'decimal:2',
            'lokasi_lat' => 'decimal:7',
            'lokasi_lng' => 'decimal:7',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
