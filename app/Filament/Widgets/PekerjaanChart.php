<?php

namespace App\Filament\Widgets;

use App\Models\Anggota;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: grafik dari kolom tidak terenkripsi (tanpa dekripsi).
// DESIGN.md §4: kedua grafik memakai satu warna teal; judul sudah membedakan isi.
class PekerjaanChart extends ChartWidget
{
    protected static ?int $sort = 6;

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
                'backgroundColor' => ['#0f766e', '#2563eb', '#7c3aed', '#d97706', '#db2777', '#0891b2', '#16a34a', '#64748b'],
            ]],
            'labels' => $rows->pluck('label')->all(),
        ];
    }

    // DESIGN.md §7.5: judul memuat jumlah total, mengikuti wilayah pengguna.
    public function getHeading(): string|Htmlable|null
    {
        return 'Sebaran Pekerjaan ('.Anggota::query()->count().')';
    }

    // DESIGN.md §7.5: grafik batang horizontal.
    protected function getOptions(): array
    {
        return ['indexAxis' => 'y'];
    }
}
