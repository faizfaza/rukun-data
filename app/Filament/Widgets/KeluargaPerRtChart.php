<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\MemoizesDetailItems;
use App\Models\Keluarga;
use App\Models\Wilayah;
use App\Support\WilayahScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: jumlah keluarga per RT dari kolom tidak terenkripsi.
// Membantu petugas desa melihat RT mana datanya paling tertinggal.
class KeluargaPerRtChart extends ChartWidget
{
    use MemoizesDetailItems;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '14rem';

    protected ?string $heading = 'Keluarga per RT';

    protected string $view = 'filament.widgets.interactive-chart';

    public function getDetailType(): string
    {
        return 'keluarga_per_rt';
    }

    public function getDetailUnit(): string
    {
        return 'keluarga';
    }

    /**
     * @return list<array{key: string, label: string, count: int, color: string}>
     */
    protected function hitungDetailItems(): array
    {
        $palette = ['#0b4a7d', '#1d6fa5', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#475569', '#94a3b8'];

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
                return ['label' => 'Wilayah #'.$row->id_wilayah, 'kode' => '', 'id' => (int) $row->id_wilayah, 'count' => (int) $row->total];
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
                'count' => (int) $row->total,
            ];
        })->all();

        $labels = $this->uniqueLabels($items);

        $result = [];
        foreach ($items as $i => $item) {
            $result[] = [
                'key' => (string) $item['id'],
                'label' => $labels[$i] ?? $item['label'],
                'count' => $item['count'],
                'color' => $palette[$i % count($palette)],
            ];
        }

        return $result;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $items = $this->getDetailItems();

        return [
            'datasets' => [[
                'label' => 'Keluarga',
                'data' => array_column($items, 'count'),
                'backgroundColor' => ['#0b4a7d', '#1d6fa5', '#2563eb', '#3b82f6', '#60a5fa', '#93c5fd', '#475569', '#94a3b8'],
                'borderRadius' => 6,
                'maxBarThickness' => 30,
            ]],
            'labels' => array_column($items, 'label'),
        ];
    }

    /**
     * Keep labels short when possible, but append the unique code when two
     * regions share the same short name/chain.
     *
     * @param  list<array{label: string, kode: string, id: int, count?: int}>  $items
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
        return 'Sebaran Keluarga per RT';
    }

    public function getDescription(): string|Htmlable|null
    {
        return 'Jumlah Kartu Keluarga terdaftar di setiap RT';
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
                    'grid' => ['color' => '#eef2f1'],
                    'ticks' => ['color' => '#5f6f6d', 'font' => ['size' => 12, 'weight' => '600'], 'precision' => 0],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#445451', 'font' => ['size' => 12, 'weight' => '600']],
                ],
            ],
        ];
    }
}
