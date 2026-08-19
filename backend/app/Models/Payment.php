<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'task_id',
        'promo_id',
        'jumlah',
        'subtotal_barang',
        'ongkir',
        'ongkir_normal',
        'potongan',
        'cashback',
        'komisi_platform',
        'service_fee',
        'status',
        'metode',
        'referensi_midtrans',
        'paid_at',
        'released_at',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'subtotal_barang' => 'decimal:2',
            'ongkir' => 'decimal:2',
            'ongkir_normal' => 'decimal:2',
            'potongan' => 'decimal:2',
            'cashback' => 'decimal:2',
            'komisi_platform' => 'decimal:2',
            'service_fee' => 'decimal:2',
            'paid_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }
}
