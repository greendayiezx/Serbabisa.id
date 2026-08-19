<?php

namespace App\Policies;

use App\Models\Task;
use App\Models\User;

class TaskPolicy
{
    /**
     * Admin melewati seluruh pengecekan kepemilikan.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    /**
     * Boleh melihat detail tugas jika:
     * - dia peserta tugas (customer pemilik atau mitra yang ditugaskan), atau
     * - dia mitra dan tugas masih 'pending' & belum diambil (untuk menawar / mengambil).
     */
    public function view(User $user, Task $task): bool
    {
        return $this->isParticipant($user, $task)
            || ($user->isMitra() && $task->status === 'pending' && $task->mitra_id === null);
    }

    /**
     * Hanya peserta tugas yang boleh memperbarui (mis. mengubah status).
     */
    public function update(User $user, Task $task): bool
    {
        return $this->isParticipant($user, $task);
    }

    /**
     * Hanya customer pemilik yang boleh menghapus, dan hanya selama tugas
     * belum berjalan (mencegah penghapusan setelah ada transaksi/escrow).
     */
    public function delete(User $user, Task $task): bool
    {
        return $user->id === $task->customer_id
            && in_array($task->status, ['pending', 'cancelled'], true);
    }

    /**
     * Peserta tugas = customer pemilik atau mitra yang sudah ditugaskan.
     */
    protected function isParticipant(User $user, Task $task): bool
    {
        return $user->id === $task->customer_id
            || ($task->mitra_id !== null && $user->id === $task->mitra_id);
    }
}
