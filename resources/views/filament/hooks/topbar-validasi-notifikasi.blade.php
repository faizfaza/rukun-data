@php
    $user = auth()->user();
    if (! $user) {
        return;
    }

    $pendingCount = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('validasi_perubahan')) {
            $baseQuery = \App\Filament\Resources\ValidasiPerubahanResource::getEloquentQuery();

            if ($user->isAdminRw()) {
                $pendingCount = (clone $baseQuery)
                    ->where('status', \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_RW)
                    ->count();
            } elseif ($user->isAdminDesa() || $user->isSuperadmin() || $user->isAdmin()) {
                $pendingCount = (clone $baseQuery)
                    ->where('status', \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_DESA)
                    ->count();
            } else {
                $pendingCount = (clone $baseQuery)
                    ->whereIn('status', [
                        \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_RW,
                        \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_DESA,
                    ])
                    ->count();
            }
        }
    } catch (\Throwable) {
        $pendingCount = 0;
    }

    $unreadHasilCount = 0;
    try {
        if (\Illuminate\Support\Facades\Schema::hasTable('notifikasi_validasi')) {
            $unreadHasilCount = \App\Models\NotifikasiValidasi::query()
                ->where('id_pengguna', $user->id)
                ->belumDibaca()
                ->count();
        }
    } catch (\Throwable) {
        $unreadHasilCount = 0;
    }

    $badgeTotal = $user->isAdminRt()
        ? ($unreadHasilCount > 0 ? $unreadHasilCount : $pendingCount)
        : ($pendingCount + $unreadHasilCount);

    $urlNotifikasi = \App\Filament\Pages\Notifikasi::getUrl();
@endphp

<div class="flex items-center mr-1">
    <a
        href="{{ $urlNotifikasi }}"
        wire:navigate
        title="Buka Pusat Notifikasi"
        class="relative inline-flex items-center justify-center h-9 w-9 rounded-lg border border-[#e5ecf4] bg-white text-[#334155] hover:bg-[#f0f7ff] hover:border-[#93c5fd] hover:text-[#0b4a7d] transition"
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>

        @if($badgeTotal > 0)
            <span class="absolute -top-1.5 -right-1.5 inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-[#0b4a7d] text-white text-[10px] font-bold shadow-xs">
                {{ $badgeTotal > 99 ? '99+' : $badgeTotal }}
            </span>
        @endif
    </a>
</div>
