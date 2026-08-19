<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Subscription extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'subscription_plan_id',
        'status',
        'harga_dibayar',
        'mulai_pada',
        'berakhir_pada',
        'sisa_kuota_diskon',
        'sisa_kuota_ongkir',
        'auto_renew',
    ];

    protected function casts(): array
    {
        return [
            'harga_dibayar' => 'decimal:2',
            'mulai_pada' => 'datetime',
            'berakhir_pada' => 'datetime',
            'auto_renew' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }

    public function isActive(): bool
    {
        return $this->status === 'active'
            && (! $this->berakhir_pada || $this->berakhir_pada->isFuture());
    }
}
