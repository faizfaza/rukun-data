<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\MemoizesDetailItems;
use App\Models\Anggota;
use App\Support\WilayahScope;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

// PRD F11/§10: grafik dari kolom tidak terenkripsi (tanggal_lahir), tanpa dekripsi.
// DESIGN.md §4: satu warna aksen teal; judul sudah membedakan isi.
class KelompokUmurChart extends ChartWidget
{
    use MemoizesDetailItems;
    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected ?string $maxHeight = '17rem';

    protected ?string $heading = 'Sebaran Kelompok Umur';

    protected string $view = 'filament.widgets.interactive-chart';

    public function getDetailType(): string
    {
        return 'kelompok_umur';
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
        $buckets = RingkasanWidget::umurBuckets();
        $palette = ['#0f766e', '#14b8a6', '#5eead4', '#99f6e4', '#cbd6d3'];
        $now = now();

        // Satu query agregat untuk semua kelompok umur (sebelumnya satu COUNT per kelompok).
        $selects = [];
        $bindings = [];
        foreach (array_keys($buckets) as $i => $key) {
            $info = $buckets[$key];
            $kondisi = 'tanggal_lahir <= ?';
            $bindings[] = $now->copy()->subYears($info['min'])->toDateTimeString();
            if ($info['max'] !== null) {
                $kondisi .= ' AND tanggal_lahir > ?';
                $bindings[] = $now->copy()->subYears($info['max'] + 1)->toDateTimeString();
            }
            $selects[] = "SUM(CASE WHEN {$kondisi} THEN 1 ELSE 0 END) as b{$i}";
        }

        $row = Anggota::query()->selectRaw(implode(', ', $selects), $bindings)->toBase()->first();

        $items = [];
        foreach (array_keys($buckets) as $i => $key) {
            $items[] = [
                'key' => $key,
                'label' => $buckets[$key]['label'],
                'count' => (int) ($row->{"b{$i}"} ?? 0),
                'color' => $palette[$i % count($palette)],
            ];
        }

        return $items;
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $items = $this->getDetailItems();
        $labels = [];
        $data = [];

        foreach ($items as $item) {
            // Nilai ikut ditampilkan pada label agar kategori 0 tetap terbaca,
            // tanpa menambah dependensi data-labels.
            $labels[] = $item['label'].' · '.$item['count'];
            $data[] = $item['count'];
        }

        return [
            'datasets' => [[
                'label' => 'Anggota',
                'data' => $data,
                'backgroundColor' => ['#0f766e', '#14b8a6', '#5eead4', '#99f6e4', '#cbd6d3'],
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
