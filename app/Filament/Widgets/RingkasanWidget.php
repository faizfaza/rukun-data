<?php

namespace App\Filament\Widgets;

use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\RefHubungan;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

// Ringkasan angka mengikuti scope wilayah pengguna secara otomatis (global scope).
// Semua angka dihitung dari kolom yang tidak terenkripsi (PRD §10).
class RingkasanWidget extends StatsOverviewWidget
{
    // Urutan dashboard: kartu ringkasan angka di atas, grafik di bawah.
    protected static ?int $sort = 1;

    protected function getStats(): array
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

        return [
            Stat::make('Keluarga', $keluarga)
                ->description('Tersebar di '.Keluarga::query()->distinct()->count('id_wilayah').' RT')
                ->descriptionIcon('heroicon-m-home-modern')
                ->color('primary'),

            Stat::make('Anggota', $anggota)
                ->description($keluarga > 0
                    ? 'Rata-rata '.number_format($anggota / $keluarga, 1, ',', '.').' jiwa per keluarga'
                    : 'Belum ada data')
                ->descriptionIcon('heroicon-m-users')
                ->color('primary'),

            Stat::make('Kepala keluarga perempuan', $kepalaPerempuan)
                ->description($kepala > 0
                    ? round($kepalaPerempuan / $kepala * 100).'% dari '.$kepala.' kepala keluarga'
                    : 'Belum ada data')
                ->descriptionIcon('heroicon-m-user')
                ->color('primary'),

            Stat::make('Usia produktif (18–59)', $produktif)
                ->description($anggota > 0
                    ? round($produktif / $anggota * 100).'% dari anggota'
                    : 'Belum ada data')
                ->descriptionIcon('heroicon-m-briefcase')
                ->color('primary'),
        ];
    }
}
