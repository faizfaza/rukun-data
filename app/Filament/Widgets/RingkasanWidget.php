<?php

namespace App\Filament\Widgets;

use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\RefHubungan;
use App\Support\WilayahScope;
use Filament\Widgets\Widget;
use Illuminate\Contracts\View\View;

// Ringkasan angka mengikuti scope wilayah pengguna secara otomatis (global scope).
// Semua angka dihitung dari kolom yang tidak terenkripsi (PRD A10).
class RingkasanWidget extends Widget
{
    // Urutan dashboard: kartu ringkasan angka di atas, grafik di bawah.
    protected static ?int $sort = 1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.ringkasan';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $keluarga = Keluarga::count();
        $anggota = Anggota::count();

        $kepala = Anggota::where('id_hubungan', RefHubungan::KEPALA)->count();
        $kepalaPerempuan = Anggota::where('id_hubungan', RefHubungan::KEPALA)
            ->where('jenis_kelamin', 'P')
            ->count();

        $produktif = Anggota::where('tanggal_lahir', '<=', now()->subYears(18))
            ->where('tanggal_lahir', '>', now()->subYears(60))
            ->count();

        $user = auth()->user();
        $wilayahDesc = WilayahScope::label($user);

        return [
            'stats' => [
                [
                    'label' => 'Keluarga',
                    'value' => $keluarga,
                    'description' => $wilayahDesc,
                    'icon' => 'heroicon-o-home-modern',
                ],
                [
                    'label' => 'Anggota',
                    'value' => $anggota,
                    'description' => $keluarga > 0
                        ? 'Rata-rata '.number_format($anggota / $keluarga, 1, ',', '.').' jiwa per keluarga'
                        : 'Belum ada data',
                    'icon' => 'heroicon-o-users',
                ],
                [
                    'label' => 'Kepala keluarga perempuan',
                    'value' => $kepalaPerempuan,
                    'description' => $kepala > 0
                        ? round($kepalaPerempuan / $kepala * 100).'% dari '.$kepala.' kepala keluarga'
                        : 'Belum ada data',
                    'icon' => 'heroicon-o-user',
                ],
                [
                    'label' => 'Usia produktif (18–59)',
                    'value' => $produktif,
                    'description' => $anggota > 0
                        ? round($produktif / $anggota * 100).'% dari anggota'
                        : 'Belum ada data',
                    'icon' => 'heroicon-o-briefcase',
                ],
            ],
        ];
    }

    public function render(): View
    {
        return view($this->view, $this->getViewData());
    }
}
