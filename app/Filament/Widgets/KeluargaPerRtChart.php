<?php

namespace App\Filament\Widgets;

use App\Models\Keluarga;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: jumlah keluarga per RT dari kolom tidak terenkripsi.
// Membantu petugas desa melihat RT mana yang datanya paling tertinggal.
class KeluargaPerRtChart extends ChartWidget
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Keluarga per RT';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $rows = Keluarga::query()
            ->selectRaw('wilayah.nama as label, COUNT(*) as total')
            ->join('wilayah', 'wilayah.id', '=', 'keluarga.id_wilayah')
            ->groupBy('keluarga.id_wilayah', 'wilayah.nama')
            ->orderBy('keluarga.id_wilayah')
            ->get();

        return [
            'datasets' => [[
                'label' => 'Keluarga',
                'data' => $rows->pluck('total')->all(),
                'backgroundColor' => ['#0f766e', '#2563eb', '#7c3aed', '#d97706', '#db2777', '#0891b2', '#16a34a', '#64748b'],
            ]],
            'labels' => $rows->pluck('label')->all(),
        ];
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Keluarga per RT ('.Keluarga::query()->count().')';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y'];
    }
}
