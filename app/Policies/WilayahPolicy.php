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
        return $user->isSuperadmin() || $user->isAdmin() || $user->isAdminRw();
    }

    public function view(User $user, Wilayah $wilayah): bool
    {
        if ($user->isSuperadmin() || $user->isAdmin()) {
            return true;
        }

        if ($user->isAdminRw()) {
            return $wilayah->id === $user->id_wilayah || ($wilayah->level === 'rt' && $wilayah->parent_id === $user->id_wilayah);
        }

        return false;
    }

    public function create(User $user): bool
    {
        return $user->isSuperadmin() || $user->isAdmin() || $user->isAdminRw();
    }

    public function update(User $user, Wilayah $wilayah): bool
    {
        if ($user->isSuperadmin() || $user->isAdmin()) {
            return true;
        }

        if ($user->isAdminRw()) {
            return $wilayah->level === 'rt' && $wilayah->parent_id === $user->id_wilayah;
        }

        return false;
    }

    public function delete(User $user, Wilayah $wilayah): bool
    {
        if ($user->isSuperadmin() || $user->isAdmin()) {
            return $this->tanpaTurunan($wilayah);
        }

        if ($user->isAdminRw()) {
            return $wilayah->level === 'rt'
                && $wilayah->parent_id === $user->id_wilayah
                && $this->tanpaTurunan($wilayah);
        }

        return false;
    }

    // Memakai hasil withCount bila tersedia agar daftar tabel tidak menembak
    // query exists per baris.
    private function tanpaTurunan(Wilayah $wilayah): bool
    {
        $punyaAnak = $wilayah->children_count !== null
            ? (int) $wilayah->children_count > 0
            : $wilayah->children()->exists();

        $punyaKeluarga = $wilayah->keluarga_count !== null
            ? (int) $wilayah->keluarga_count > 0
            : $wilayah->keluarga()->exists();

        return ! $punyaAnak && ! $punyaKeluarga;
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
