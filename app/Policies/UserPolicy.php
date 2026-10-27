<?php

namespace App\Policies;

use App\Models\User;
use App\Support\WilayahAccess;

// Keputusan 5/6: akun hanya dibuat oleh peran di atasnya. Tidak ada hapus
// (hanya nonaktifkan). Pengguna tidak boleh mengubah peran atau menonaktifkan
// dirinya sendiri. forceDelete selalu ditolak.
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin() || $user->isAdmin();
    }

    public function view(User $user, User $target): bool
    {
        return $user->isSuperadmin() || $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin() || $user->isAdmin();
    }

    public function update(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return true; // boleh mengubah datanya sendiri (kecuali peran/status).
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        if ($user->isAdmin()) {
            return ! $target->isSuperadmin();
        }

        return false;
    }

    public function deactivate(User $user, User $target): bool
    {
        // Pengguna tidak boleh menonaktifkan dirinya sendiri.
        if ($user->is($target)) {
            return false;
        }

        // Superadmin tidak boleh dinonaktifkan oleh siapa pun.
        if ($target->isSuperadmin()) {
            return false;
        }

        // Superadmin boleh menonaktifkan akun admin, admin_rw, dan admin_rt.
        if ($user->isSuperadmin()) {
            return true;
        }

        // Admin tidak dapat menonaktifkan admin lain dan tidak dapat menonaktifkan superadmin.
        if ($user->isAdmin()) {
            return ! $target->isAdmin() && ! $target->isSuperadmin();
        }

        return false;
    }

    public function delete(User $user, User $target): bool
    {
        return false;
    }

    public function restore(User $user, User $target): bool
    {
        return false;
    }

    public function forceDelete(User $user, User $target): bool
    {
        return false;
    }
}
