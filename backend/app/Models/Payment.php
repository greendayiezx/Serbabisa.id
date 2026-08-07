<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payment extends Model
{
    /** @use HasFactory<\Database\Factories\PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'task_id',
        'jumlah',
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
}
