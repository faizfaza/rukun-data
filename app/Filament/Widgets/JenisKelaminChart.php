<?php

namespace App\Filament\Widgets;

use App\Models\Anggota;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: komposisi jenis kelamin dari kolom enum (tanpa dekripsi).
// Dua rona pada keluarga warna teal yang sama (DESIGN.md §4, satu warna aksen).
class JenisKelaminChart extends ChartWidget
{
    protected static ?int $sort = 4;

    protected ?string $heading = 'Komposisi Jenis Kelamin';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $rows = Anggota::query()
            ->selectRaw('jenis_kelamin, COUNT(*) as total')
            ->groupBy('jenis_kelamin')
            ->pluck('total', 'jenis_kelamin');

        return [
            'datasets' => [[
                'label' => 'Anggota',
                'data' => [(int) ($rows['L'] ?? 0), (int) ($rows['P'] ?? 0)],
                'backgroundColor' => ['#2563eb', '#a21caf'],
            ]],
            'labels' => ['Laki-laki', 'Perempuan'],
        ];
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Komposisi Jenis Kelamin ('.Anggota::query()->count().')';
    }
}
