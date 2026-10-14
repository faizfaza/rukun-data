<?php

namespace App\Providers;

use App\Models\Anggota;
use App\Models\EkonomiKeluarga;
use App\Models\Keluarga;
use App\Observers\AuditObserver;
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
        Keluarga::observe(AuditObserver::class);
        Anggota::observe(AuditObserver::class);
        EkonomiKeluarga::observe(AuditObserver::class);
    }
}
