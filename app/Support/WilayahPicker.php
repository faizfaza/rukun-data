<?php

namespace App\Support;

use App\Models\Wilayah;

final class WilayahPicker
{
    public static function search(string $search, ?string $level = null): array
    {
        $query = Wilayah::operasional()->where(fn ($q) => $q->where('nama', 'like', '%'.$search.'%')->orWhere('kode', 'like', '%'.$search.'%'));
        if ($level) {
            $query->where('level', $level);
        }
        $ids = WilayahAccess::allowedWilayahIds(auth()->user());
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return WilayahLabels::for($query->orderBy('path')->limit(50)->get());
    }
}
