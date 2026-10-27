<?php

namespace App\Policies;

use App\Models\Keluarga;
use App\Models\User;
use App\Support\WilayahAccess;

// Keputusan 5: superadmin kelola semua; admin_rt kelola KK/anggota di RT-nya;
// admin_rw hanya melihat (tanpa input/edit/hapus). Baris di luar wilayah sudah
// disaring global scope (404); policy ini pertahanan kedua sekaligus pengatur
// boleh-tidaknya aksi per peran. forceDelete selalu ditolak.
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
        return $user->isSuperadmin() || $user->isAdmin() || $user->isAdminRw() || $user->isAdminRt();
    }

    public function update(User $user, Keluarga $keluarga): bool
    {
        return ($user->isSuperadmin() || $user->isAdmin() || $user->isAdminRw() || $user->isAdminRt())
            && $this->inWilayah($user, $keluarga->id_wilayah);
    }

    public function delete(User $user, Keluarga $keluarga): bool
    {
        return $this->update($user, $keluarga);
    }

    public function restore(User $user, Keluarga $keluarga): bool
    {
        return $this->update($user, $keluarga);
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
