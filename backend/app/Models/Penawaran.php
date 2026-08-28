<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Dokumen penawaran BisaBersih Kantor.
 *
 * Nama tabelnya tunggal ('penawaran') karena bentuk jamak kata Indonesia tidak
 * dibentuk dengan -s; Laravel akan menebak 'penawarans' kalau tidak disebut.
 */
class Penawaran extends Model
{
    use HasFactory;

    protected $table = 'penawaran';

    /** Status yang berarti pelanggan sudah boleh melihat dokumennya. */
    public const TERBUKA = ['dikirim', 'revisi', 'disetujui', 'kedaluwarsa'];

    protected $fillable = [
        'nomor', 'task_id', 'customer_id',
        'nama_perusahaan', 'nama_pic', 'telepon_pic', 'alamat', 'ringkasan',
        'status', 'berlaku_sampai', 'paket_dipilih_id', 'disetujui_pada',
        'scope', 'biaya_tambahan', 'pengecualian',
    ];

    protected function casts(): array
    {
        return [
            'berlaku_sampai' => 'date',
            'disetujui_pada' => 'datetime',
            'scope' => 'array',
            'biaya_tambahan' => 'array',
            'pengecualian' => 'array',
        ];
    }

    public function paket(): HasMany
    {
        return $this->hasMany(PenawaranPaket::class);
    }

    public function revisi(): HasMany
    {
        return $this->hasMany(PenawaranRevisi::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    /**
     * Sudah lewat masa berlakunya?
     *
     * Dihitung dari tanggal, bukan dari kolom status — status baru berubah saat
     * ada yang menyentuh datanya, sedangkan tanggal lewat dengan sendirinya.
     */
    public function kedaluwarsa(): bool
    {
        return $this->berlaku_sampai !== null && $this->berlaku_sampai->isPast();
    }
}
