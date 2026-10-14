<?php

namespace App\Policies;

use App\Models\Keluarga;
use App\Models\User;
use App\Support\WilayahAccess;

// PRD §3/F2: admin penuh; desa baca gabungan; rt_rw kelola wilayahnya.
// Baris di luar wilayah sudah disaring global scope (404); policy ini
// pertahanan kedua sekaligus pengatur boleh-tidaknya aksi per peran.
class KeluargaPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Keluarga $keluarga): bool
    {
        return $this->inWilayah($user, $keluarga->id_wilayah);
    }

    public function create(User $user): bool
    {
        return $user->role !== 'desa';
    }

    public function update(User $user, Keluarga $keluarga): bool
    {
        return $user->role !== 'desa' && $this->inWilayah($user, $keluarga->id_wilayah);
    }

    public function delete(User $user, Keluarga $keluarga): bool
    {
        return $user->role !== 'desa' && $this->inWilayah($user, $keluarga->id_wilayah);
    }

    public function restore(User $user, Keluarga $keluarga): bool
    {
        return $user->role !== 'desa' && $this->inWilayah($user, $keluarga->id_wilayah);
    }

    public function forceDelete(User $user, Keluarga $keluarga): bool
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
