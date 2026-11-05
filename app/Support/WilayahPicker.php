<?php

namespace App\Support;

use App\Models\Wilayah;

final class WilayahPicker
{
    public static function search(string $search, ?string $level = null): array
    {
        $search = trim($search);
        if ($search === '') {
            return [];
        }

        // 1. Cari langsung pada nama atau kode wilayah
        $query = Wilayah::operasional()->where(function ($q) use ($search) {
            $q->where('nama', 'like', '%'.$search.'%')
              ->orWhere('kode', 'like', '%'.$search.'%');
        });

        if ($level && $level !== '__none') {
            $query->where('level', $level);
        }

        $ids = WilayahAccess::allowedWilayahIds(auth()->user());
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        $results = $query->orderBy('path')->limit(50)->get();

        // 2. Jika pencarian langsung tidak menemukan (misal user mencari nama induk seperti "Jawa Barat", "Bandung", atau "Bojongsoang" untuk mencari RW/RT)
        if ($results->isEmpty() && mb_strlen($search) >= 2) {
            $parentPaths = Wilayah::where('nama', 'like', '%'.$search.'%')
                ->whereIn('level', ['negara', 'provinsi', 'kabupaten_kota', 'kecamatan', 'desa', 'dusun'])
                ->limit(20)
                ->pluck('path')
                ->filter();

            if ($parentPaths->isNotEmpty()) {
                $subQuery = Wilayah::operasional()->where(function ($q) use ($parentPaths) {
                    foreach ($parentPaths as $p) {
                        $q->orWhere('path', 'like', $p.'%');
                    }
                });

                if ($level && $level !== '__none') {
                    $subQuery->where('level', $level);
                }

                if ($ids !== null) {
                    $subQuery->whereIn('id', $ids);
                }

                $results = $subQuery->orderBy('path')->limit(50)->get();
            }
        }

        return WilayahLabels::for($results);
    }
}
