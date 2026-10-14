<?php

namespace App\Policies;

use App\Models\Anggota;
use App\Models\User;
use App\Support\WilayahAccess;

class AnggotaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Anggota $anggota): bool
    {
        return $this->inWilayah($user, $anggota);
    }

    public function create(User $user): bool
    {
        return $user->role !== 'desa';
    }

    public function update(User $user, Anggota $anggota): bool
    {
        return $user->role !== 'desa' && $this->inWilayah($user, $anggota);
    }

    public function delete(User $user, Anggota $anggota): bool
    {
        return $user->role !== 'desa' && $this->inWilayah($user, $anggota);
    }

    public function restore(User $user, Anggota $anggota): bool
    {
        return $user->role !== 'desa' && $this->inWilayah($user, $anggota);
    }

    public function forceDelete(User $user, Anggota $anggota): bool
    {
        return false;
    }

    private function inWilayah(User $user, Anggota $anggota): bool
    {
        $ids = WilayahAccess::allowedRtIds($user);

        if ($ids === null) {
            return true;
        }

        return in_array($anggota->keluarga->id_wilayah, $ids, true);
    }
}
