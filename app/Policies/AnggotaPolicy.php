<?php

namespace App\Policies;

use App\Models\Anggota;
use App\Models\User;
use App\Support\WilayahAccess;

// Keputusan 5: aturan sama dengan KeluargaPolicy, diterapkan pada anggota
// lewat relasi ke keluarga. forceDelete selalu ditolak.
class AnggotaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Anggota $anggota): bool
    {
        return $this->inWilayah($user, $anggota->keluarga->id_wilayah);
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin() || $user->isAdminRt();
    }

    public function update(User $user, Anggota $anggota): bool
    {
        return ($user->isSuperadmin() || $user->isAdminRt())
            && $this->inWilayah($user, $anggota->keluarga->id_wilayah);
    }

    public function delete(User $user, Anggota $anggota): bool
    {
        return $this->update($user, $anggota);
    }

    public function restore(User $user, Anggota $anggota): bool
    {
        return $this->update($user, $anggota);
    }

    public function forceDelete(User $user, Anggota $anggota): bool
    {
        return false;
    }

    private function inWilayah(User $user, int $idWilayah): bool
    {
        $ids = WilayahAccess::allowedRtIds($user);

        if ($ids === null) {
            return true;
        }

        return in_array($idWilayah, $ids, true);
    }
}
