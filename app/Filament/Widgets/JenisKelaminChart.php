<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\MemoizesDetailItems;
use App\Models\Anggota;
use App\Support\WilayahScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: komposisi jenis kelamin dari kolom enum (tanpa dekripsi).
// Dua rona pada keluarga warna teal yang sama (DESIGN.md §4, satu warna aksen).
class JenisKelaminChart extends ChartWidget
{
    use MemoizesDetailItems;

    protected static ?int $sort = 4;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '18rem';

    protected ?string $heading = 'Komposisi Jenis Kelamin';

    protected string $view = 'filament.widgets.interactive-chart';

    public function getDetailType(): string
    {
        return 'jenis_kelamin';
    }

    public function getDetailUnit(): string
    {
        return 'warga';
    }

    /**
     * @return list<array{key: string, label: string, count: int, color: string}>
     */
    protected function hitungDetailItems(): array
    {
        $rows = Anggota::query()
            ->selectRaw('jenis_kelamin, COUNT(*) as total')
            ->groupBy('jenis_kelamin')
            ->pluck('total', 'jenis_kelamin');

        return [
            ['key' => 'L', 'label' => 'Laki-laki', 'count' => (int) ($rows['L'] ?? 0), 'color' => '#0f766e'],
            ['key' => 'P', 'label' => 'Perempuan', 'count' => (int) ($rows['P'] ?? 0), 'color' => '#d97706'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $items = $this->getDetailItems();

        return [
            'datasets' => [[
                'label' => 'Anggota',
                'data' => array_column($items, 'count'),
                'backgroundColor' => array_column($items, 'color'),
            ]],
            'labels' => array_column($items, 'label'),
        ];
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Komposisi Jenis Kelamin ('.Anggota::query()->count().')';
    }

    public function getDescription(): string|Htmlable|null
    {
        return WilayahScope::label(auth()->user());
    }

    protected function getOptions(): array
    {
        return [
            'cutout' => '68%',
            'plugins' => [
                'legend' => [
                    'position' => 'bottom',
                    'labels' => [
                        'color' => '#445451',
                        'boxWidth' => 10,
                        'boxHeight' => 10,
                        'usePointStyle' => true,
                        'padding' => 16,
                        'font' => ['size' => 12, 'weight' => '600'],
                    ],
                ],
            ],
        ];
    }
}
