<?php

namespace App\Support;

use App\Models\User;
use App\Models\Wilayah;

// PRD F2: menerjemahkan peran + wilayah pengguna menjadi daftar id wilayah
// tingkat RT yang boleh dibaca. null berarti tanpa batas (admin). Array
// kosong berarti tidak berhak atas wilayah mana pun.
final class WilayahAccess
{
    /**
     * @return list<int>|null
     */
    public static function allowedRtIds(?User $user): ?array
    {
        if ($user === null) {
            return null;
        }

        if ($user->role === 'admin') {
            return null;
        }

        if ($user->id_wilayah === null) {
            return [];
        }

        $root = Wilayah::find($user->id_wilayah);

        if ($root === null) {
            return [];
        }

        return self::rtIdsBelow($root);
    }

    /**
     * @return list<int>
     */
    private static function rtIdsBelow(Wilayah $root): array
    {
        if ($root->level === 'rt') {
            return [$root->id];
        }

        $ids = [];

        $children = Wilayah::where('parent_id', $root->id)->get();

        foreach ($children as $child) {
            foreach (self::rtIdsBelow($child) as $id) {
                $ids[] = $id;
            }
        }

        return $ids;
    }
}
