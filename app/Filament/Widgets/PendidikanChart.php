<?php

namespace App\Filament\Widgets;

use App\Models\Anggota;
use App\Support\WilayahScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: grafik dari kolom tidak terenkripsi (tanpa dekripsi).
// DESIGN.md §4: kedua grafik memakai satu warna teal; judul sudah membedakan isi.
class PendidikanChart extends ChartWidget
{
    protected static ?int $sort = 5;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '17rem';

    protected ?string $heading = 'Sebaran Pendidikan';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = Anggota::query()
            ->selectRaw('ref_pendidikan.nama as label, COUNT(*) as total')
            ->join('ref_pendidikan', 'ref_pendidikan.id', '=', 'anggota.id_pendidikan')
            ->groupBy('anggota.id_pendidikan', 'ref_pendidikan.nama')
            ->orderBy('anggota.id_pendidikan')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Anggota',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => ['#0f766e', '#14b8a6', '#2dd4bf', '#5eead4', '#99f6e4', '#cbd6d3', '#8a9996'],
            ]],
            'labels' => $rows->pluck('label')->all(),
        ];
    }

    // DESIGN.md §7.5: judul memuat jumlah total, mengikuti wilayah pengguna.
    public function getHeading(): string|Htmlable|null
    {
        return 'Sebaran Pendidikan ('.Anggota::query()->count().')';
    }

    public function getDescription(): string|Htmlable|null
    {
        return WilayahScope::label(auth()->user());
    }

    // DESIGN.md §7.5: grafik batang horizontal.
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
