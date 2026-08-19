<?php

namespace App\Support;

use App\Models\AuditLog as AuditLogModel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pencatat jejak audit terpusat untuk aksi sensitif (VULN-009).
 *
 * Sumber kebenaran = tabel `audit_logs` (App\Models\AuditLog) agar bisa
 * di-query & ditampilkan di admin panel. Jika model/tabel belum tersedia
 * (mis. migrasi belum dijalankan), otomatis jatuh ke channel log file
 * sehingga jejak tidak pernah hilang dan request tidak pernah gagal karena
 * proses audit.
 *
 * Pemakaian:
 *   AuditLog::record('auth.login.success', $request, ['user_id' => $user->id]);
 *   AuditLog::record('task.status.update', $request, ['from' => $a, 'to' => $b], $task);
 *
 * JANGAN pernah memasukkan password/token mentah ke dalam $context.
 */
class AuditLog
{
    public static function record(
        string $aksi,
        ?Request $request = null,
        array $context = [],
        ?Model $auditable = null,
    ): void {
        $request ??= request();

        // Aktor: user terautentikasi pada request, atau user_id eksplisit dari
        // $context (berguna saat login/registrasi, di mana guard belum terisi).
        $actorId = optional($request?->user())->id ?? ($context['user_id'] ?? null);

        if (class_exists(AuditLogModel::class)) {
            try {
                AuditLogModel::create([
                    'user_id' => $actorId,
                    'aksi' => $aksi,
                    'auditable_type' => $auditable?->getMorphClass(),
                    'auditable_id' => $auditable?->getKey(),
                    'perubahan' => $context ?: null,
                    'ip_address' => $request?->ip(),
                    'user_agent' => $request?->userAgent(),
                ]);

                return;
            } catch (\Throwable $e) {
                // Tabel belum ada / DB bermasalah — jangan gagalkan request,
                // catat ke file log sebagai cadangan.
                Log::channel('audit')->warning("audit_db_gagal: {$aksi}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::channel('audit')->info($aksi, array_merge([
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'actor_id' => $actorId,
        ], $context));
    }
}
