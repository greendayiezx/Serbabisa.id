<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Satu baris buku besar dompet. Bersifat append-only: jangan pernah diubah atau
 * dihapus setelah dibuat — koreksi dilakukan dengan mutasi baru bertipe
 * 'adjustment' atau 'refund'/'payout_reversal'.
 */
class WalletTransaction extends Model
{
    use HasFactory;

    protected $fillable = [
        'wallet_id',
        'tipe',
        'arah',
        'jumlah',
        'saldo_sebelum',
        'saldo_sesudah',
        'referensi_type',
        'referensi_id',
        'keterangan',
        'idempotency_key',
        'dibuat_oleh',
    ];

    protected function casts(): array
    {
        return [
            'jumlah' => 'decimal:2',
            'saldo_sebelum' => 'decimal:2',
            'saldo_sesudah' => 'decimal:2',
        ];
    }

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function referensi(): MorphTo
    {
        return $this->morphTo();
    }

    public function pembuat(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dibuat_oleh');
    }
}
