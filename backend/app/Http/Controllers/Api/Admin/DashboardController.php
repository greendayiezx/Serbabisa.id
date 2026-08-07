<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Task;
use App\Models\User;

class DashboardController extends Controller
{
    /**
     * Ringkasan metrik operasional untuk admin (lihat PRD bab 11 — KPI).
     */
    public function index()
    {
        return response()->json([
            'total_users' => User::count(),
            'total_mitra_pending_verifikasi' => User::where('role', 'mitra')->where('status_verifikasi', 'pending')->count(),
            'tasks_posted_this_week' => Task::where('created_at', '>=', now()->subWeek())->count(),
            'tasks_completed_this_week' => Task::where('status', 'completed')->where('completed_at', '>=', now()->subWeek())->count(),
            'disputes_open' => Dispute::where('status', 'open')->count(),
        ]);
    }
}
