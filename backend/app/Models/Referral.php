<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    use HasFactory;

    /** Bonus default pengundang (Rp), sesuai promo Traktiran Teman. */
    public const DEFAULT_REWARD = 2000;

    protected $fillable = [
        'referrer_id',
        'referred_id',
        'task_id',
        'status',
        'reward_cashback',
        'rewarded_at',
    ];

    protected function casts(): array
    {
        return [
            'reward_cashback' => 'decimal:2',
            'rewarded_at' => 'datetime',
        ];
    }

    /** Pengundang. */
    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id');
    }

    /** Yang diundang. */
    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }
}
