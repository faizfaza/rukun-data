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

        $wargaMenetap = Anggota::where(function ($q): void {
            $q->where('status_kependudukan', 'menetap')->orWhereNull('status_kependudukan');
        })->count();

        $wargaPindah = Anggota::where('status_kependudukan', 'pindah')->count();

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
                    'category' => 'Kependudukan',
                    'label' => 'Total Keluarga',
                    'value' => $keluarga,
                    'description' => $wilayahDesc,
                    'icon' => 'heroicon-o-home-modern',
                    'url' => route('filament.admin.pages.jelajah'),
                ],
                [
                    'category' => 'Kependudukan',
                    'label' => 'Warga Menetap (Aktif)',
                    'value' => $wargaMenetap,
                    'description' => $anggota > 0
                        ? round($wargaMenetap / $anggota * 100).'% dari total '.$anggota.' jiwa'
                        : 'Belum ada data',
                    'icon' => 'heroicon-o-users',
                    'url' => route('filament.admin.pages.jelajah'),
                ],
                [
                    'category' => 'Mutasi Warga',
                    'label' => 'Warga Pindah',
                    'value' => $wargaPindah,
                    'description' => $wargaPindah > 0
                        ? $wargaPindah.' jiwa tercatat pindah domisili'
                        : 'Tidak ada mutasi pindah',
                    'icon' => 'heroicon-o-arrow-right-start-on-rectangle',
                    'url' => route('filament.admin.pages.jelajah'),
                ],
                [
                    'category' => 'Sosial & Usia',
                    'label' => 'Kepala Keluarga Perempuan',
                    'value' => $kepalaPerempuan,
                    'description' => $kepala > 0
                        ? round($kepalaPerempuan / $kepala * 100).'% dari '.$kepala.' kepala keluarga'
                        : 'Belum ada data',
                    'icon' => 'heroicon-o-user',
                    'url' => route('filament.admin.pages.jelajah'),
                ],
                [
                    'category' => 'Sosial & Usia',
                    'label' => 'Usia Produktif (18–59)',
                    'value' => $produktif,
                    'description' => $anggota > 0
                        ? round($produktif / $anggota * 100).'% dari total anggota'
                        : 'Belum ada data',
                    'icon' => 'heroicon-o-briefcase',
                    'url' => route('filament.admin.pages.jelajah'),
                ],
            ],
        ];
    }

    public function render(): View
    {
        return view($this->view, $this->getViewData());
    }
}
