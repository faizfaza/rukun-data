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
        return $user->isSuperadmin() || $user->isAdminRw();
    }

    public function view(User $user, User $target): bool
    {
        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->isAdminRw() && $target->isAdminRt() && $this->inSubtree($user, $target->id_wilayah);
    }

    public function create(User $user): bool
    {
        return $user->bolehMembuatAkun();
    }

    public function update(User $user, User $target): bool
    {
        if ($user->is($target)) {
            return true; // boleh mengubah datanya sendiri (kecuali peran/status).
        }

        if ($user->isSuperadmin()) {
            return true;
        }

        return $user->isAdminRw() && $target->isAdminRt() && $this->inSubtree($user, $target->id_wilayah);
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

    private function inSubtree(User $user, ?int $idWilayah): bool
    {
        if ($idWilayah === null) {
            return false;
        }

        $ids = WilayahAccess::allowedWilayahIds($user);

        return $ids !== null && in_array($idWilayah, $ids, true);
    }
}
