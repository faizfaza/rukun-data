<?php

namespace App\Providers;

use App\Models\Anggota;
use App\Models\EkonomiKeluarga;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\Wilayah;
use App\Observers\AuditObserver;
use App\Observers\WilayahObserver;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Aturan hierarki wilayah ditegakkan di model (seeder/console/filament).
        Wilayah::observe(WilayahObserver::class);

        Keluarga::observe(AuditObserver::class);
        Anggota::observe(AuditObserver::class);
        EkonomiKeluarga::observe(AuditObserver::class);

        // Fase 2.8: audit juga mencatat wilayah dan akun (buat/ubah/nonaktifkan),
        // tetap tanpa menyimpan nilai sensitif (password tidak pernah dicatat).
        Wilayah::observe(AuditObserver::class);
        User::observe(AuditObserver::class);
    }
}
