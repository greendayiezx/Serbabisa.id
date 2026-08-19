<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PromoRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'promo_id',
        'user_id',
        'task_id',
        'potongan_barang',
        'potongan_ongkir',
        'cashback',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'potongan_barang' => 'decimal:2',
            'potongan_ongkir' => 'decimal:2',
            'cashback' => 'decimal:2',
            'redeemed_at' => 'datetime',
        ];
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
