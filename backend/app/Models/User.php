<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    /**
     * Kolom sensitif (role, is_active, status_verifikasi) SENGAJA tidak
     * dimasukkan agar tidak bisa di-set lewat mass assignment (mis.
     * ->update($request->all())). Ubah nilainya hanya lewat penetapan
     * eksplisit di alur yang sudah diotorisasi (lihat AuthController,
     * AdminUserController). Mencegah privilege escalation.
     */
    protected $fillable = [
        'name',
        'email',
        'phone',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function mitraProfile(): HasOne
    {
        return $this->hasOne(MitraProfile::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(Wallet::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(Address::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /** Langganan aktif terkini (kalau ada). */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->where('status', 'active')->latestOfMany();
    }

    /** User yang mengundang (pengundang). */
    public function referredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'direferal_oleh_id');
    }

    /** Referral yang dibuat user ini sebagai pengundang. */
    public function referralsMade(): HasMany
    {
        return $this->hasMany(Referral::class, 'referrer_id');
    }

    public function tasksAsCustomer(): HasMany
    {
        return $this->hasMany(Task::class, 'customer_id');
    }

    public function tasksAsMitra(): HasMany
    {
        return $this->hasMany(Task::class, 'mitra_id');
    }

    public function bids(): HasMany
    {
        return $this->hasMany(Bid::class, 'mitra_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class, 'sender_id');
    }

    public function reviewsGiven(): HasMany
    {
        return $this->hasMany(Review::class, 'dari_user_id');
    }

    public function reviewsReceived(): HasMany
    {
        return $this->hasMany(Review::class, 'ke_user_id');
    }

    public function disputesReported(): HasMany
    {
        return $this->hasMany(Dispute::class, 'dilaporkan_oleh');
    }

    public function isCustomer(): bool
    {
        return $this->role === 'customer';
    }

    public function isMitra(): bool
    {
        return $this->role === 'mitra';
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }
}
