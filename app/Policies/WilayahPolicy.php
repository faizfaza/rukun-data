<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Wilayah;

// Keputusan 5: hanya superadmin yang mengelola wilayah. Hapus ditolak bila
// wilayah masih punya anak atau keluarga. forceDelete selalu ditolak.
class WilayahPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function view(User $user, Wilayah $wilayah): bool
    {
        return $user->isSuperadmin();
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin();
    }

    public function update(User $user, Wilayah $wilayah): bool
    {
        return $user->isSuperadmin();
    }

    public function delete(User $user, Wilayah $wilayah): bool
    {
        return $user->isSuperadmin()
            && $wilayah->children()->doesntExist()
            && $wilayah->keluarga()->doesntExist();
    }

    public function restore(User $user, Wilayah $wilayah): bool
    {
        return false;
    }

    public function forceDelete(User $user, Wilayah $wilayah): bool
    {
        return false;
    }
}
