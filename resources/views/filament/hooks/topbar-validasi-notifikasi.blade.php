@php
    $user = auth()->user();
    if (! $user) {
        return;
    }

    try {
        if (! \Illuminate\Support\Facades\Schema::hasTable('validasi_perubahan')) {
            return;
        }
    } catch (\Throwable) {
        return;
    }

    $baseQuery = \App\Filament\Resources\ValidasiPerubahanResource::getEloquentQuery();

    if ($user->isAdminRw()) {
        $pendingRwCount = (clone $baseQuery)
            ->where('status', \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_RW)
            ->count();
        $pendingDesaCount = (clone $baseQuery)
            ->where('status', \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_DESA)
            ->count();
        $pendingCount = $pendingRwCount + $pendingDesaCount;
        $judulNotif = 'Notifikasi Validasi & Perubahan Data KK (RW)';
        $subJudulNotif = "{$pendingRwCount} menunggu verifikasi RW · {$pendingDesaCount} menunggu Desa";
        $items = (clone $baseQuery)->limit(6)->get();
    } elseif ($user->isAdminDesa() || $user->isSuperadmin() || $user->isAdmin()) {
        $pendingCount = (clone $baseQuery)
            ->where('status', \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_DESA)
            ->count();
        $menungguRwCount = (clone $baseQuery)
            ->where('status', \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_RW)
            ->count();
        $judulNotif = 'Notifikasi Validasi & Perubahan Data KK (Desa)';
        $subJudulNotif = $pendingCount > 0
            ? "{$pendingCount} pengajuan siap disahkan Desa".($menungguRwCount > 0 ? " · {$menungguRwCount} masih di RW" : '')
            : ($menungguRwCount > 0 ? "{$menungguRwCount} pengajuan masih diperiksa Admin RW" : 'Semua pengajuan perubahan KK telah terverifikasi');
        $items = (clone $baseQuery)->limit(6)->get();
    } else {
        $pendingCount = (clone $baseQuery)
            ->whereIn('status', [
                \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_RW,
                \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_DESA,
            ])
            ->count();
        $judulNotif = 'Notifikasi Validasi & Perubahan Data KK (RT)';
        $subJudulNotif = $pendingCount > 0 ? "{$pendingCount} pengajuan perubahan KK sedang diproses" : 'Belum ada pengajuan yang tertunda';
        $items = (clone $baseQuery)->limit(6)->get();
    }

    // Ambil juga log perubahan data KK terbaru di wilayah pengguna (termasuk yang dilakukan oleh Admin RW, Admin Desa, atau Superadmin)
    $logPerubahanKk = \App\Filament\Resources\AuditLogResource::getEloquentQuery()
        ->whereIn('nama_tabel', ['keluarga', 'anggota', 'aset_keluarga', 'ekonomi_keluarga'])
        ->limit(5)
        ->get();

    $urlValidasi = \App\Filament\Resources\ValidasiPerubahanResource::getUrl('index');
    $urlLog = \App\Filament\Resources\AuditLogResource::getUrl('index');
@endphp

<div x-data="{ openNotif: false, tabNotif: 'validasi' }" class="relative flex items-center mr-1">
    <button
        type="button"
        @click="openNotif = !openNotif"
        @click.outside="openNotif = false"
        title="Notifikasi Validasi & Perubahan Data KK"
        class="relative inline-flex items-center justify-center h-9 w-9 rounded-lg border border-gray-200 bg-white text-gray-600 hover:bg-gray-50 hover:text-teal-700 transition cursor-pointer"
    >
        <svg class="h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" d="M14.857 17.082a23.848 23.848 0 0 0 5.454-1.31A8.967 8.967 0 0 1 18 9.75V9A6 6 0 0 0 6 9v.75a8.967 8.967 0 0 1-2.312 6.022c1.733.64 3.56 1.085 5.455 1.31m5.714 0a24.255 24.255 0 0 1-5.714 0m5.714 0a3 3 0 1 1-5.714 0" />
        </svg>

        @if($pendingCount > 0)
            <span class="absolute -top-1.5 -right-1.5 inline-flex items-center justify-center min-w-[1.15rem] h-[1.15rem] px-1 rounded-full bg-amber-500 text-white text-[10px] font-bold shadow-xs">
                {{ $pendingCount > 99 ? '99+' : $pendingCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown Daftar Notifikasi Validasi & Perubahan KK --}}
    <div
        x-show="openNotif"
        x-transition
        style="display: none;"
        class="absolute right-0 top-11 z-50 w-80 sm:w-[26rem] rounded-xl border border-gray-200 bg-white shadow-xl overflow-hidden text-xs text-gray-800"
    >
        <div class="px-4 py-3 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center justify-between gap-2">
                <div>
                    <div class="font-bold text-gray-900 text-xs">{{ $judulNotif }}</div>
                    <div class="text-[11px] text-gray-500">{{ $subJudulNotif }}</div>
                </div>
                <a
                    href="{{ $urlValidasi }}"
                    wire:navigate
                    class="shrink-0 rounded-lg bg-teal-700 px-2.5 py-1 text-[11px] font-semibold text-white hover:bg-teal-800 transition"
                >
                    Semua Validasi →
                </a>
            </div>

            <div class="mt-2.5 grid grid-cols-2 gap-1.5">
                <button
                    type="button"
                    @click="tabNotif = 'validasi'"
                    :class="tabNotif === 'validasi' ? 'bg-teal-700 text-white font-semibold' : 'bg-white text-gray-700 border border-gray-200'"
                    class="rounded-md px-2.5 py-1 text-[11px] transition cursor-pointer"
                >
                    Antrean Approval ({{ $pendingCount }})
                </button>
                <button
                    type="button"
                    @click="tabNotif = 'log_kk'"
                    :class="tabNotif === 'log_kk' ? 'bg-teal-700 text-white font-semibold' : 'bg-white text-gray-700 border border-gray-200'"
                    class="rounded-md px-2.5 py-1 text-[11px] transition cursor-pointer"
                >
                    Perubahan Data KK ({{ $logPerubahanKk->count() }})
                </button>
            </div>
        </div>

        {{-- Tab 1: Antrean Validasi / Approval (RT -> RW -> Desa) --}}
        <div x-show="tabNotif === 'validasi'" class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
            @forelse($items as $item)
                <a
                    href="{{ $urlValidasi }}"
                    wire:navigate
                    class="block px-4 py-3 hover:bg-teal-50/40 transition"
                >
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="font-bold text-gray-950">
                            {{ $item->labelKeluargaTerkait() }}
                            <span class="font-normal text-gray-500">
                                · {{ \App\Support\AuditFormatter::labelTabel($item->nama_tabel) }}
                            </span>
                        </span>
                        <span class="text-[10px] text-gray-400">{{ $item->created_at?->diffForHumans() }}</span>
                    </div>
                    <div class="text-[11px] text-gray-500 mb-1">
                        Oleh: <strong>{{ $item->pengaju?->name ?? 'Petugas' }}</strong>
                        ({{ $item->pengaju?->role?->label() ?? $item->peran_pengaju }})
                        @if($item->wilayahRt)
                            · {{ $item->wilayahRt->nama }}
                        @endif
                    </div>
                    <div class="text-gray-700 line-clamp-2 mb-1.5">
                        {{ $item->ringkasan }}
                    </div>
                    <div class="flex items-center justify-between gap-2">
                        <span class="inline-flex items-center rounded px-1.5 py-0.5 text-[10px] font-semibold {{
                            match ($item->status) {
                                \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_RW => 'bg-amber-100 text-amber-800',
                                \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_DESA => 'bg-blue-100 text-blue-800',
                                \App\Models\ValidasiPerubahan::STATUS_DISETUJUI => 'bg-emerald-100 text-emerald-800',
                                \App\Models\ValidasiPerubahan::STATUS_DITOLAK => 'bg-rose-100 text-rose-800',
                                default => 'bg-gray-100 text-gray-700',
                            }
                        }}">
                            {{ \App\Models\ValidasiPerubahan::labelStatus($item->status) }}
                        </span>
                        <span class="text-[11px] font-semibold text-teal-700">Buka Validasi →</span>
                    </div>
                </a>
            @empty
                <div class="px-4 py-6 text-center text-gray-500">
                    Belum ada antrean validasi perubahan KK.
                </div>
            @endforelse
        </div>

        {{-- Tab 2: Semua Perubahan Data KK di Wilayah Ini (Termasuk oleh Admin RW, Desa, & Superadmin) --}}
        <div x-show="tabNotif === 'log_kk'" style="display: none;" class="divide-y divide-gray-100 max-h-80 overflow-y-auto">
            @forelse($logPerubahanKk as $log)
                @php
                    $desc = \App\Support\AuditFormatter::describe($log);
                @endphp
                <a
                    href="{{ $urlLog }}"
                    wire:navigate
                    class="block px-4 py-3 hover:bg-teal-50/40 transition"
                >
                    <div class="flex items-center justify-between gap-2 mb-1">
                        <span class="font-bold text-gray-900">
                            {{ $log->pengguna?->name ?? 'Sistem' }}
                            <span class="font-normal text-gray-500">({{ $log->pengguna?->role?->label() ?? '—' }})</span>
                        </span>
                        <span class="text-[10px] text-gray-400">{{ $log->waktu?->diffForHumans() }}</span>
                    </div>
                    <div class="text-gray-700 line-clamp-2 mb-1">
                        {{ $desc['ringkasan'] ?? 'Perubahan data keluarga' }}
                    </div>
                    <div class="flex items-center justify-between text-[11px]">
                        <span class="rounded bg-gray-100 px-1.5 py-0.5 font-medium text-gray-700">
                            {{ \App\Support\AuditFormatter::labelTabel($log->nama_tabel) }}
                        </span>
                        <span class="font-semibold text-teal-700">Lihat di Log →</span>
                    </div>
                </a>
            @empty
                <div class="px-4 py-6 text-center text-gray-500">
                    Belum ada riwayat perubahan data KK di wilayah Anda.
                </div>
            @endforelse
        </div>
    </div>
</div>
