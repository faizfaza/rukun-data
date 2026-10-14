<?php

namespace App\Filament\Widgets;

use App\Models\Anggota;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: grafik dari kolom tidak terenkripsi (tanggal_lahir), tanpa dekripsi.
// DESIGN.md §4: satu warna aksen teal; judul sudah membedakan isi.
class KelompokUmurChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Sebaran Kelompok Umur';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $buckets = [
            'Balita (0–5)' => [0, 5],
            'Anak (6–12)' => [6, 12],
            'Remaja (13–17)' => [13, 17],
            'Dewasa (18–59)' => [18, 59],
            'Lansia (60+)' => [60, null],
        ];

        $labels = [];
        $data = [];

        foreach ($buckets as $label => [$min, $max]) {
            $query = Anggota::query()->where('tanggal_lahir', '<=', now()->subYears($min));

            if ($max !== null) {
                $query->where('tanggal_lahir', '>', now()->subYears($max + 1));
            }

            $labels[] = $label;
            $data[] = $query->count();
        }

        return [
            'datasets' => [[
                'label' => 'Anggota',
                'data' => $data,
                'backgroundColor' => '#0f766e',
            ]],
            'labels' => $labels,
        ];
    }

    // DESIGN.md §7.5: judul memuat jumlah total, mengikuti wilayah pengguna.
    public function getHeading(): string|Htmlable|null
    {
        return 'Sebaran Kelompok Umur ('.Anggota::query()->count().')';
    }

    protected function getOptions(): array
    {
        return ['indexAxis' => 'y'];
    }
}
