<?php

namespace App\Support;

use App\Models\Wilayah;
use Illuminate\Support\Collection;

final class WilayahLabels
{
    /** One ancestor query for an entire page of nodes. */
    public static function for(Collection $nodes): array
    {
        $ids = $nodes->flatMap(fn ($w) => [...$w->ancestorIds(), $w->id])->unique()->all();
        $all = Wilayah::whereIn('id', $ids)->get()->keyBy('id');

        return $nodes->mapWithKeys(function ($w) use ($all) {
            $chain = collect([...$w->ancestorIds(), $w->id])->map(fn ($id) => $all->get($id))->filter();
            $active = $chain->every(fn ($node) => $node->is_active);
            $names = $chain->reject(fn ($node) => $node->level === 'negara')->reverse()->pluck('nama')->implode(', ');

            return [$w->id => $w->kode.' · '.($names ?: $w->nama).' · '.($active ? 'Aktif' : 'Nonaktif / induk nonaktif')];
        })->all();
    }
}
