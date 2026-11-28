<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard;
use App\Filament\Widgets\JenisKelaminChart;
use App\Filament\Widgets\KelompokUmurChart;
use App\Filament\Widgets\KeluargaPerRtChart;
use App\Filament\Widgets\PekerjaanChart;
use App\Filament\Widgets\PendidikanChart;
use App\Filament\Widgets\RingkasanWidget;
use App\Http\Middleware\WajibPanduan;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
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
            ->brandName('RUKUNDATA')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.75rem')
            ->login(Login::class)
            // Mode SPA: pindah menu hanya menukar konten (wire:navigate) tanpa
            // memuat ulang CSS/JS. Halaman cetak tetap dibuka sebagai halaman penuh.
            ->spa(hasPrefetching: true)
            ->spaUrlExceptions(fn (): array => [
                url('/admin/keluarga/*/cetak'),
                url('/admin/wilayah/*/cetak-warga'),
            ])
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
                // Palet Resmi RUKUNDATA (rukundata.id): Navy Blue utama + aksen hijau daun & netral bersih.
                'primary' => [
                    50 => '#eff6ff',
                    100 => '#dbeafe',
                    200 => '#bfdbfe',
                    300 => '#93c5fd',
                    400 => '#3b82f6',
                    500 => '#135c99',
                    600 => '#0b4a7d',
                    700 => '#093c66',
                    800 => '#0a3154',
                    900 => '#0c2742',
                    950 => '#071a2e',
                ],
                'gray' => Color::Slate,
                'info' => Color::Sky,
                'success' => [
                    50 => '#f0fdf4',
                    100 => '#dcfce7',
                    200 => '#bbf7d0',
                    300 => '#86efac',
                    400 => '#4ade80',
                    500 => '#2e8b22',
                    600 => '#25751b',
                    700 => '#1f5f16',
                    800 => '#194c13',
                    900 => '#143f10',
                    950 => '#092307',
                ],
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
                // Alur jelas dari atas ke bawah:
                // 1. Ringkasan Utama -> 2. Demografi (Umur & Gender) -> 3. Sosial-Ekonomi (Pendidikan & Pekerjaan) -> 4. Sebaran Wilayah (Keluarga per RT)
                RingkasanWidget::class,
                KelompokUmurChart::class,
                JenisKelaminChart::class,
                PendidikanChart::class,
                PekerjaanChart::class,
                KeluargaPerRtChart::class,
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
                PanelsRenderHook::SIDEBAR_NAV_START,
                fn () => view('filament.hooks.sidebar-desa-card'),
            )
            ->renderHook(
                PanelsRenderHook::TOPBAR_END,
                fn () => view('filament.hooks.mobile-view-pill'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_BEFORE,
                fn () => view('filament.hooks.topbar-validasi-notifikasi'),
            )
            ->renderHook(
                PanelsRenderHook::USER_MENU_PROFILE_AFTER,
                fn () => view('filament.hooks.user-menu-jabatan'),
            )
            ->renderHook(
                PanelsRenderHook::BODY_END,
                fn () => view('filament.hooks.filter-badge-zero'),
            )
            ->authMiddleware([
                Authenticate::class,
                WajibPanduan::class,
            ]);
    }
}
