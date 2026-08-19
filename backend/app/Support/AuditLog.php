<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Pencatat jejak audit terpusat untuk aksi sensitif (VULN-009).
 *
 * Pemakaian:
 *   AuditLog::record('login.success', $request, ['user_id' => $user->id]);
 *
 * Selalu mencatat: aksi, IP, user-agent, waktu, dan konteks tambahan.
 * JANGAN pernah memasukkan password/token mentah ke dalam $context.
 */
class AuditLog
{
    public static function record(string $action, ?Request $request = null, array $context = []): void
    {
        $request ??= request();

        Log::channel('audit')->info($action, array_merge([
            'ip' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'actor_id' => optional($request?->user())->id,
        ], $context));
    }
}
