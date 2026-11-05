<?php

namespace App\Filament\Widgets;

use App\Models\Anggota;
use App\Support\WilayahScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: grafik dari kolom tidak terenkripsi (tanggal_lahir), tanpa dekripsi.
// DESIGN.md §4: satu warna aksen teal; judul sudah membedakan isi.
class KelompokUmurChart extends ChartWidget
{
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '17rem';

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

        $now = now();
        $labels = [];
        $data = [];

        foreach ($buckets as $label => [$min, $max]) {
            $query = Anggota::query()->where('tanggal_lahir', '<=', $now->copy()->subYears($min));

            if ($max !== null) {
                $query->where('tanggal_lahir', '>', $now->copy()->subYears($max + 1));
            }

            $count = $query->count();

            // Nilai ikut ditampilkan pada label agar kategori 0 tetap terbaca,
            // tanpa menambah dependensi data-labels.
            $labels[] = $label.' · '.$count;
            $data[] = $count;
        }

        return [
            'datasets' => [[
                'label' => 'Anggota',
                'data' => $data,
                'backgroundColor' => ['#2563eb', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6', '#ec4899', '#6366f1'],
            ]],
            'labels' => $labels,
        ];
    }

    // DESIGN.md §7.5: judul memuat jumlah total, mengikuti wilayah pengguna.
    public function getHeading(): string|Htmlable|null
    {
        return 'Sebaran Kelompok Umur ('.Anggota::query()->count().')';
    }

    public function getDescription(): string|Htmlable|null
    {
        return WilayahScope::label(auth()->user()).' · Nilai 0 tetap ditampilkan pada label.';
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
