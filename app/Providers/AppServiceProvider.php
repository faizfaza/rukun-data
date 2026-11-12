<?php

namespace App\Providers;

use App\Models\Anggota;
use App\Models\AsetKeluarga;
use App\Models\AuditLog;
use App\Models\EkonomiKeluarga;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\Wilayah;
use App\Observers\AuditObserver;
use App\Observers\WilayahObserver;
use App\Support\OperationalGuard;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
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
        foreach ([Keluarga::class, Anggota::class, User::class] as $model) {
            $model::observe(OperationalGuard::class);
        }

        Keluarga::observe(AuditObserver::class);
        Anggota::observe(AuditObserver::class);
        EkonomiKeluarga::observe(AuditObserver::class);
        AsetKeluarga::observe(AuditObserver::class);

        // Fase 2.8: audit juga mencatat wilayah dan akun (buat/ubah/nonaktifkan),
        // tetap tanpa menyimpan nilai sensitif (password tidak pernah dicatat).
        Wilayah::observe(AuditObserver::class);
        User::observe(AuditObserver::class);

        Event::listen(Login::class, function (Login $event): void {
            if ($event->user) {
                $userId = $event->user->getAuthIdentifier();
                AuditLog::create([
                    'id_pengguna' => $userId,
                    'aksi' => 'login',
                    'nama_tabel' => 'users',
                    'id_record' => (int) $userId,
                ]);
            }
        });
    }
}
