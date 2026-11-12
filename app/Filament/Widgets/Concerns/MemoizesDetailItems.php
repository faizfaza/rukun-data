<?php

namespace App\Filament\Widgets\Concerns;

// Rincian grafik dipakai dua kali per render: oleh view (panel detail) dan
// oleh getData() (dataset Chart.js). Hasilnya disimpan selama satu request
// agar query agregat tidak dijalankan dua kali. Properti protected tidak ikut
// disimpan Livewire, jadi setiap request tetap menghitung data terbaru.
trait MemoizesDetailItems
{
    /** @var list<array{key: string, label: string, count: int, color: string}>|null */
    protected ?array $detailItemsMemo = null;

    /**
     * @return list<array{key: string, label: string, count: int, color: string}>
     */
    public function getDetailItems(): array
    {
        return $this->detailItemsMemo ??= $this->hitungDetailItems();
    }

    /**
     * @return list<array{key: string, label: string, count: int, color: string}>
     */
    abstract protected function hitungDetailItems(): array;
}

