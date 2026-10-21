<?php

namespace App\Support;

use App\Models\User;
use App\Models\Wilayah;

// Fase 2.2: menerjemahkan peran + wilayah pengguna menjadi daftar id wilayah
// tingkat RT yang boleh dibaca, memakai `path` (LIKE prefix) bukan rekursi.
// null = tanpa batas (superadmin), array kosong = tidak berhak atas RT mana pun.
final class WilayahAccess
{
    /**
     * @return list<int>|null
     */
    public static function allowedRtIds(?User $user): ?array
    {
        // Tanpa pengguna login (seeder, console, queue): tanpa batas.
        if ($user === null) {
            return null;
        }

        if ($user->isSuperadmin()) {
            return null;
        }

        if ($user->id_wilayah === null) {
            return [];
        }

        $root = Wilayah::find($user->id_wilayah);

        if ($root === null) {
            return [];
        }

        if ($root->level === 'rt') {
            return [$root->id];
        }

        // Semua RT di seluruh keturunan wilayah pengguna (prefix path).
        return Wilayah::where('level', 'rt')
            ->where('path', 'like', $root->path.'%')
            ->pluck('id')
            ->all();
    }

    // Semua id wilayah (semua level) di dalam subtree pengguna, termasuk
    // wilayah pengguna sendiri. Dipakai untuk membatasi pilihan wilayah saat
    // membuat akun. null = tanpa batas (superadmin).
    /**
     * @return list<int>|null
     */
    public static function allowedWilayahIds(?User $user): ?array
    {
        if ($user === null || $user->isSuperadmin()) {
            return null;
        }

        if ($user->id_wilayah === null) {
            return [];
        }

        $root = Wilayah::find($user->id_wilayah);

        if ($root === null) {
            return [];
        }

        return Wilayah::where('path', 'like', $root->path.'%')
            ->pluck('id')
            ->all();
    }
}
