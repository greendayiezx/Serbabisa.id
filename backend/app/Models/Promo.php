<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Promo extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Nilai bawaan agar instance baru konsisten dengan default kolom DB — tanpa
     * ini, Promo::create() tanpa is_active menghasilkan instance is_active=null
     * yang keliru dianggap nonaktif sebelum di-reload.
     */
    protected $attributes = [
        'is_active' => true,
        'kuota_per_user' => 1,
    ];

    protected $fillable = [
        'kode',
        'slug',
        'judul',
        'ringkas',
        'detail',
        'emoji',
        'grup',
        'min_belanja',
        'potongan_tetap',
        'gratis_ongkir',
        'cashback_persen',
        'cashback_maks',
        'kuota_total',
        'kuota_harian',
        'kuota_per_user',
        'kuota_terpakai',
        'kategori_bundle',
        'syarat',
        'berlaku_mulai',
        'berlaku_sampai',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'detail' => 'array',
            'kategori_bundle' => 'array',
            'syarat' => 'array',
            'min_belanja' => 'decimal:2',
            'potongan_tetap' => 'decimal:2',
            'cashback_persen' => 'decimal:2',
            'cashback_maks' => 'decimal:2',
            'gratis_ongkir' => 'boolean',
            'is_active' => 'boolean',
            'berlaku_mulai' => 'datetime',
            'berlaku_sampai' => 'datetime',
        ];
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromoRedemption::class);
    }

    /** Berapa kali user tertentu sudah memakai promo ini. */
    public function redemptionCountFor(int $userId): int
    {
        return $this->redemptions()->where('user_id', $userId)->count();
    }
}
