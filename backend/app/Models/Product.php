<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Product extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'product_category_id',
        'sku',
        'nama',
        'slug',
        'deskripsi',
        'satuan',
        'harga_dasar',
        'harga_jual',
        'gambar',
        'berat_gram',
        'stok',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'harga_dasar' => 'decimal:2',
            'harga_jual' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /** Markup platform per unit (harga_jual - harga_dasar). */
    public function markup(): float
    {
        return (float) $this->harga_jual - (float) $this->harga_dasar;
    }
}
