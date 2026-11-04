<?php

namespace App\Support;

use App\Models\Wilayah;
use Illuminate\Support\Collection;

final class WilayahLabels
{
    /** One ancestor query for an entire page of nodes. */
    public static function for(Collection $nodes): array
    {
        return collect(self::details($nodes))
            ->map(fn (array $detail): string => $detail['label'])
            ->all();
    }

    /**
     * Structured labels so the UI can render the code, name, and status
     * separately instead of truncating one long string.
     *
     * @return array<int, array{kode: string, nama: string, chain: string, label: string, status: string, status_label: string}>
     */
    public static function details(Collection $nodes): array
    {
        $ids = $nodes->flatMap(fn ($w) => [...$w->ancestorIds(), $w->id])->unique()->all();
        $all = Wilayah::whereIn('id', $ids)->get()->keyBy('id');

        return $nodes->mapWithKeys(function ($w) use ($all) {
            $chain = collect([...$w->ancestorIds(), $w->id])->map(fn ($id) => $all->get($id))->filter();
            $active = $chain->every(fn ($node) => $node->is_active);
            $names = $chain->reject(fn ($node) => $node->level === 'negara')->reverse()->pluck('nama')->implode(', ');
            $parentNames = $chain
                ->reject(fn ($node) => $node->id === $w->id || $node->level === 'negara')
                ->reverse()
                ->pluck('nama')
                ->implode(', ');

            $status = match (true) {
                ! $w->is_active => 'inactive',
                ! $active => 'inherited',
                default => 'active',
            };

            $statusLabel = match ($status) {
                'inactive' => 'Nonaktif',
                'inherited' => 'Induk nonaktif',
                default => 'Aktif',
            };

            return [$w->id => [
                'kode' => $w->kode,
                'nama' => $w->nama,
                'chain' => $parentNames,
                'label' => $w->kode.' · '.($names ?: $w->nama).' · '.$statusLabel,
                'status' => $status,
                'status_label' => $statusLabel,
            ]];
        })->all();
    }
}
