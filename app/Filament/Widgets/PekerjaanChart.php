<?php

namespace App\Filament\Widgets;

use App\Models\Anggota;
use App\Support\WilayahScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: grafik dari kolom tidak terenkripsi (tanpa dekripsi).
// DESIGN.md §4: kedua grafik memakai satu warna teal; judul sudah membedakan isi.
class PekerjaanChart extends ChartWidget
{
    protected static ?int $sort = 6;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '17rem';

    protected ?string $heading = 'Sebaran Pekerjaan';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = Anggota::query()
            ->selectRaw('ref_pekerjaan.nama as label, COUNT(*) as total')
            ->join('ref_pekerjaan', 'ref_pekerjaan.id', '=', 'anggota.id_pekerjaan')
            ->groupBy('anggota.id_pekerjaan', 'ref_pekerjaan.nama')
            ->orderBy('anggota.id_pekerjaan')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Anggota',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => ['#2563eb', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4', '#ec4899', '#3b82f6'],
            ]],
            'labels' => $rows->pluck('label')->all(),
        ];
    }

    // DESIGN.md §7.5: judul memuat jumlah total, mengikuti wilayah pengguna.
    public function getHeading(): string|Htmlable|null
    {
        return 'Sebaran Pekerjaan ('.Anggota::query()->count().')';
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
                    'grid' => ['color' => '#f1f5f9'],
                    'ticks' => ['color' => '#64748b', 'font' => ['size' => 13, 'weight' => '600'], 'precision' => 0],
                ],
                'y' => [
                    'grid' => ['display' => false],
                    'ticks' => ['color' => '#334155', 'font' => ['size' => 13, 'weight' => '600']],
                ],
            ],
        ];
    }
}
