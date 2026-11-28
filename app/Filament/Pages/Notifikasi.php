<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AuditLogResource;
use App\Filament\Resources\ValidasiPerubahanResource;
use App\Models\NotifikasiValidasi;
use App\Models\ValidasiPerubahan;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;

class Notifikasi extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'notifikasi';

    protected static ?string $title = 'Pusat Notifikasi';

    protected string $view = 'filament.pages.notifikasi';

    public string $tab = 'hasil';

    public function mount(): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $unreadCount = $this->getUnreadHasilCount();
        $pendingCount = $this->getPendingAntreanCount();

        if ($unreadCount > 0 || $user->isAdminRt()) {
            $this->tab = 'hasil';
        } elseif ($pendingCount > 0) {
            $this->tab = 'antrean';
        } else {
            $this->tab = 'hasil';
        }
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, ['hasil', 'antrean', 'log_kk'], true)) {
            $this->tab = $tab;
        }
    }

    public function tandaiSemuaDibaca(): void
    {
        if (! Schema::hasTable('notifikasi_validasi')) {
            return;
        }

        NotifikasiValidasi::query()
            ->where('id_pengguna', auth()->id())
            ->whereNull('dibaca_pada')
            ->update(['dibaca_pada' => now()]);

        Notification::make()
            ->title('Semua notifikasi telah ditandai sudah dibaca')
            ->success()
            ->send();
    }

    public function getUnreadHasilCount(): int
    {
        if (! Schema::hasTable('notifikasi_validasi')) {
            return 0;
        }

        return NotifikasiValidasi::query()
            ->where('id_pengguna', auth()->id())
            ->belumDibaca()
            ->count();
    }

    public function getPendingAntreanCount(): int
    {
        $user = auth()->user();
        if (! $user || ! Schema::hasTable('validasi_perubahan')) {
            return 0;
        }

        $baseQuery = ValidasiPerubahanResource::getEloquentQuery();

        if ($user->isAdminRw()) {
            return (clone $baseQuery)->where('status', ValidasiPerubahan::STATUS_MENUNGGU_RW)->count();
        }

        if ($user->isAdminDesa() || $user->isSuperadmin() || $user->isAdmin()) {
            return (clone $baseQuery)->where('status', ValidasiPerubahan::STATUS_MENUNGGU_DESA)->count();
        }

        return (clone $baseQuery)->whereIn('status', [
            ValidasiPerubahan::STATUS_MENUNGGU_RW,
            ValidasiPerubahan::STATUS_MENUNGGU_DESA,
        ])->count();
    }

    /**
     * @return Collection<int, NotifikasiValidasi>
     */
    public function getHasilNotifList(): Collection
    {
        if (! Schema::hasTable('notifikasi_validasi')) {
            return collect();
        }

        return NotifikasiValidasi::query()
            ->where('id_pengguna', auth()->id())
            ->latest('id')
            ->limit(30)
            ->get();
    }

    /**
     * @return Collection<int, ValidasiPerubahan>
     */
    public function getAntreanList(): Collection
    {
        if (! Schema::hasTable('validasi_perubahan')) {
            return collect();
        }

        return ValidasiPerubahanResource::getEloquentQuery()
            ->limit(25)
            ->get();
    }

    /**
     * @return Collection<int, \App\Models\AuditLog>
     */
    public function getLogPerubahanKk(): Collection
    {
        return AuditLogResource::getEloquentQuery()
            ->whereIn('nama_tabel', ['keluarga', 'anggota', 'aset_keluarga', 'ekonomi_keluarga'])
            ->limit(20)
            ->get();
    }
}
