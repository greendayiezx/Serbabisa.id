<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PayoutRequest extends Model
{
    /** @use HasFactory<\Database\Factories\PayoutRequestFactory> */
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'jumlah',
        'status',
        'diproses_oleh',
        'bank_tujuan',
        'no_rekening',
        'catatan',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'processed_at' => 'datetime',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'diproses_oleh');
    }
}
