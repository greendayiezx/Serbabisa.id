<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    /** @use HasFactory<\Database\Factories\TaskFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'nomor_invoice',
        'customer_id',
        'mitra_id',
        'address_id',
        'category_id',
        'promo_id',
        'subscription_id',
        'tipe',
        'judul',
        'deskripsi',
        'toko',
        'status',
        'fulfillment_status',
        'lokasi_alamat',
        'lokasi_lat',
        'lokasi_lng',
        'harga',
        'budget',
        'foto',
        'catatan',
        'kendaraan',
        'jumlah_helper',
        'berat_total',
        'proteksi_label',
        'proteksi_harga',
        'dijadwalkan_pada',
        'nama_penerima',
        'telepon_penerima',
        'accepted_at',
        'completed_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'lokasi_lat' => 'decimal:7',
            'lokasi_lng' => 'decimal:7',
            'harga' => 'decimal:2',
            'budget' => 'decimal:2',
            'berat_total' => 'decimal:2',
            'proteksi_harga' => 'decimal:2',
            'foto' => 'array',
            'dijadwalkan_pada' => 'datetime',
            'accepted_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function mitra(): BelongsTo
    {
        return $this->belongsTo(User::class, 'mitra_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function address(): BelongsTo
    {
        return $this->belongsTo(Address::class);
    }

    public function promo(): BelongsTo
    {
        return $this->belongsTo(Promo::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(TaskItem::class);
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function dispute(): HasOne
    {
        return $this->hasOne(Dispute::class);
    }

    public function isFixed(): bool
    {
        return $this->tipe === 'fixed';
    }

    public function isCustom(): bool
    {
        return $this->tipe === 'custom';
    }
}
