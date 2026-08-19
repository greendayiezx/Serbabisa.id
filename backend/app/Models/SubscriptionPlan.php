<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class SubscriptionPlan extends Model
{
    use HasFactory;

    protected $fillable = [
        'kode',
        'nama',
        'harga',
        'diskon_per_transaksi',
        'kuota_diskon',
        'min_ongkir_gratis',
        'kuota_ongkir',
        'benefit',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'harga' => 'decimal:2',
            'diskon_per_transaksi' => 'decimal:2',
            'min_ongkir_gratis' => 'decimal:2',
            'benefit' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
