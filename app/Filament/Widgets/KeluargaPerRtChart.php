<?php

namespace App\Filament\Widgets;

use App\Models\Keluarga;
use App\Models\Wilayah;
use App\Support\WilayahScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: jumlah keluarga per RT dari kolom tidak terenkripsi.
// Membantu petugas desa melihat RT mana datanya paling tertinggal.
class KeluargaPerRtChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '17rem';

    protected ?string $heading = 'Keluarga per RT';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = Keluarga::query()
            ->selectRaw('keluarga.id_wilayah, COUNT(*) as total')
            ->join('wilayah', 'wilayah.id', '=', 'keluarga.id_wilayah')
            ->groupBy('keluarga.id_wilayah', 'wilayah.path', 'wilayah.nama', 'wilayah.kode')
            ->orderBy('wilayah.path')
            ->get();

        $wilayahs = Wilayah::query()
            ->whereIn('id', $rows->pluck('id_wilayah'))
            ->with([Wilayah::RELASI_LELUHUR])
            ->get()
            ->keyBy('id');

        $items = $rows->map(function ($row) use ($wilayahs): array {
            /** @var Wilayah|null $wilayah */
            $wilayah = $wilayahs->get($row->id_wilayah);

            if ($wilayah === null) {
                return ['label' => 'Wilayah #'.$row->id_wilayah, 'kode' => '', 'id' => $row->id_wilayah];
            }

            $chain = $wilayah->ancestors()
                ->reject(fn (Wilayah $ancestor): bool => $ancestor->level === 'negara')
                ->reverse()
                ->take(2)
                ->pluck('nama')
                ->implode(' · ');

            return [
                'label' => $wilayah->nama.($chain !== '' ? ' · '.$chain : ''),
                'kode' => $wilayah->kode,
                'id' => $wilayah->id,
            ];
        })->all();

        $labels = $this->uniqueLabels($items);

        return [
            'datasets' => [[
                'label' => 'Keluarga',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => ['#0b0f14', '#4755ae', '#8b8fc7', '#c3c7e3', '#6b7280', '#9ca3af', '#d1d5db', '#e5e7eb'],
            ]],
            'labels' => $labels,
        ];
    }

    /**
     * Keep labels short when possible, but append the unique code when two
     * regions share the same short name/chain.
     *
     * @param  list<array{label: string, kode: string, id: int}>  $items
     * @return list<string>
     */
    protected function uniqueLabels(array $items): array
    {
        $counts = array_count_values(array_column($items, 'label'));

        $labels = array_map(
            fn (array $item): string => ($counts[$item['label']] ?? 0) > 1 && filled($item['kode'])
                ? $item['label'].' · '.$item['kode']
                : $item['label'],
            $items,
        );

        $finalCounts = array_count_values($labels);

        return array_map(
            fn (string $label, int $index): string => ($finalCounts[$label] ?? 0) > 1
                ? $label.' · #'.$items[$index]['id']
                : $label,
            $labels,
            array_keys($labels),
        );
    }

    public function getHeading(): string|Htmlable|null
    {
        $rtCount = Keluarga::query()->distinct()->count('id_wilayah');
        $keluargaCount = Keluarga::query()->count();

        return 'Keluarga per RT · '.$rtCount.' RT · '.$keluargaCount.' keluarga';
    }

    public function getDescription(): string|Htmlable|null
    {
        return WilayahScope::label(auth()->user());
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => [
                'legend' => ['display' => false],
                'tooltip' => ['enabled' => true, 'intersect' => false],
            ],
            'scales' => [
                'x' => [
                    'beginAtZero' => true,
                    'grid' => ['color' => '#f1f2f4'],
                    'ticks' => ['color' => '#6b7280', 'font' => ['size' => 11], 'precision' => 0],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#4b5563', 'font' => ['size' => 11]],
                ],
            ],
        ];
    }
}
