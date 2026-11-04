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
            // Sidebar terang ala HRISELINK, topbar dimatikan.
            ->topbar(false)
            ->sidebarWidth('16rem')
            ->darkMode(false)
            ->colors([
                // Aksen utama near-black; biru dipakai sebagai aksen sekunder.
                'primary' => [
                    50 => '#f7f8f9',
                    100 => '#eceef1',
                    200 => '#d9dde4',
                    300 => '#b9c0cb',
                    400 => '#8b95a5',
                    500 => '#4b5563',
                    600 => '#0b0f14',
                    700 => '#0b0f14',
                    800 => '#080b0e',
                    900 => '#050709',
                    950 => '#000000',
                ],
                'gray' => Color::Zinc,
                'info' => Color::Indigo,
                'success' => Color::Emerald,
                'warning' => Color::Amber,
                'danger' => Color::Rose,
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
                PanelsRenderHook::BODY_END,
                fn () => view('filament.hooks.filter-badge-zero'),
            )
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
