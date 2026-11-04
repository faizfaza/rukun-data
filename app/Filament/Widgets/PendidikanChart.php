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
                'backgroundColor' => ['#0b0f14', '#4755ae', '#8b8fc7', '#c3c7e3', '#6b7280', '#9ca3af', '#d1d5db', '#e5e7eb'],
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
