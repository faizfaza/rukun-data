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
use Filament\Support\Enums\Width;
use Filament\View\PanelsRenderHook;
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
            ->databaseTransactions()
            ->path('admin')
            ->brandName('Rukun Data')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2rem')
            ->login(Login::class)
            // Inter Variable bawaan Filament dipakai agar tidak ada font eksternal.
            ->viteTheme('resources/css/filament/admin/theme.css')
            // Sidebar gelap + topbar putih: pemisahan jelas antara navigasi,
            // konteks halaman, dan area kerja.
            ->topbar(true)
            ->sidebarWidth('15rem')
            // Konten memakai seluruh lebar layar, tidak lagi terpusat dengan margin kosong.
            ->maxContentWidth(Width::Full)
            ->darkMode(false)
            ->colors([
                // Palet Civic Teal — satu aksen utama, netral hangat, status ringkas.
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
                    900 => '#0f2e2b',
                    950 => '#08201e',
                ],
                'gray' => Color::Stone,
                'info' => Color::Cyan,
                'success' => Color::Green,
                'warning' => Color::Amber,
                'danger' => Color::Red,
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
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => view('filament.hooks.mobile-view-pill'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.hooks.filter-badge-zero'),
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
