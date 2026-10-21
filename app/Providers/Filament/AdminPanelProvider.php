<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Widgets\JenisKelaminChart;
use App\Filament\Widgets\KelompokUmurChart;
use App\Filament\Widgets\KeluargaPerRtChart;
use App\Filament\Widgets\PekerjaanChart;
use App\Filament\Widgets\PendidikanChart;
use App\Filament\Widgets\RingkasanWidget;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->brandName('Rukun Data')
            ->login(Login::class)
            // DESIGN.md §5: Plus Jakarta Sans sebagai satu-satunya keluarga huruf.
            ->font('Plus Jakarta Sans')
            // DESIGN.md §11.1: tema kustom menyalakan sidebar navy dan token warna lain.
            ->viteTheme('resources/css/filament/admin/theme.css')
            // DESIGN.md §6: sidebar navy 240px, brand di sidebar, user + wilayah di
            // kaki sidebar (topbar dimatikan).
            ->topbar(false)
            ->sidebarWidth('15rem')
            // DESIGN.md hanya mendefinisikan satu tema (terang), jadi mode gelap
            // dimatikan agar tampilan konsisten di semua perangkat.
            ->darkMode(false)
            ->colors([
                // DESIGN.md §4: satu warna aksen, teal, untuk tindakan utama dan menu
                // aktif. Nilai 600 = #0F766E (tombol & tautan), 400 = #2DD4BF (penanda).
                'primary' => [
                    50 => '#f0fdfa',
                    100 => '#ccfbf1',
                    200 => '#99f6e4',
                    300 => '#5eead4',
                    400 => '#2dd4bf',
                    500 => '#14b8a6',
                    600 => '#0f766e',
                    700 => '#115e59',
                    800 => '#134e4a',
                    900 => '#0e3a37',
                    950 => '#042f2e',
                ],
                // Netral dingin (slate) agar latar, garis, dan teks terasa tertib
                // dan resmi sesuai panduan, bukan abu netral bawaan.
                'gray' => Color::Slate,
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([
                // Kartu ringkasan angka di atas, lalu grafik komposisi dan sebaran.
                RingkasanWidget::class,
                KelompokUmurChart::class,
                KeluargaPerRtChart::class,
                JenisKelaminChart::class,
                PendidikanChart::class,
                PekerjaanChart::class,
            ])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
