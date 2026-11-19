@php
    $user = auth()->user();
    $desa = $user?->desaWilayah();
    if (! $desa && ($user?->isSuperadmin() || $user?->isAdmin())) {
        $reqDesaId = request()->integer('desa');
        $aktifIds = \App\Models\Wilayah::idDesaKlienAktif();
        if ($reqDesaId && in_array($reqDesaId, $aktifIds, true)) {
            $desa = \App\Models\Wilayah::where('level', 'desa')->find($reqDesaId);
        }
        $desa ??= \App\Models\Wilayah::desaKlienDefault();
    }

    $pengaturan = $desa ? $desa->pengaturanDesa() : \App\Models\Wilayah::DEFAULT_PENGATURAN_DESA;
    $namaDesa = filled($pengaturan['nama_tampilan'] ?? null)
        ? $pengaturan['nama_tampilan']
        : ($desa?->nama ?? 'Belum Ada Desa');
    $sloganDesa = $pengaturan['slogan'] ?: 'Sistem Informasi Kependudukan Desa';
    $warnaKey = (string) ($pengaturan['warna_tema'] ?? 'teal');
    $palet = \App\Models\Wilayah::PALET_WARNA_DESA[$warnaKey] ?? \App\Models\Wilayah::PALET_WARNA_DESA['teal'];
    $shades = $palet['shades'];

    $sidebarColors = match ($warnaKey) {
        'emerald' => ['bg' => '#062c21', 'active' => 'rgb(52 211 153 / 0.16)'],
        'blue' => ['bg' => '#132347', 'active' => 'rgb(96 165 250 / 0.18)'],
        'indigo' => ['bg' => '#1b1845', 'active' => 'rgb(129 140 248 / 0.18)'],
        'amber' => ['bg' => '#361c06', 'active' => 'rgb(251 191 36 / 0.16)'],
        'rose' => ['bg' => '#3b0a1c', 'active' => 'rgb(251 113 133 / 0.16)'],
        default => ['bg' => '#0b2e2b', 'active' => 'rgb(45 212 191 / 0.14)'],
    };

    $bisaAturDesa = (bool) ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminDesa());
    $urlPengaturan = $bisaAturDesa
        ? \App\Filament\Pages\PengaturanDesa::getUrl(array_filter(['desa' => $desa?->id]))
        : null;
@endphp

<style>
    :root {
        --rd-sidebar: {{ $sidebarColors['bg'] }};
        --rd-sidebar-active: {{ $sidebarColors['active'] }};
        --rd-primary: {{ $shades[600] }};
        --rd-primary-hover: {{ $shades[700] }};
        --rd-primary-soft: {{ $shades[50] }};
        --rd-primary-border: {{ $shades[200] }};
    }
</style>

@if($user)
    <div class="mx-3 mb-3 rounded-xl border border-white/10 bg-white/[0.06] p-3 text-white">
        <div class="flex items-start justify-between gap-2">
            <div class="min-w-0">
                <div class="flex items-center gap-1.5">
                    <span class="inline-block h-2 w-2 rounded-full" style="background-color: {{ $shades[400] }}"></span>
                    <span class="text-[10px] font-bold uppercase tracking-wider text-white/60">
                        {{ $user->isSuperadmin() || $user->isAdmin() ? 'Panel Penyedia Jasa · Desa Aktif' : 'Identitas Wilayah Desa' }}
                    </span>
                </div>
                <div class="mt-1 truncate text-sm font-bold text-white" title="{{ $namaDesa }}">
                    {{ $namaDesa }}
                </div>
                <div class="truncate text-[11px] text-white/65" title="{{ $sloganDesa }}">
                    {{ $sloganDesa }}
                </div>
                @if($desa && filled($pengaturan['nama_kepala_desa'] ?? null))
                    <div class="mt-1 truncate text-[11px] text-white/55">
                        Kades: {{ $pengaturan['nama_kepala_desa'] }}
                    </div>
                @endif
            </div>

            @if($urlPengaturan)
                <a
                    href="{{ $urlPengaturan }}"
                    wire:navigate
                    title="Pengaturan & Kustomisasi Desa"
                    class="shrink-0 inline-flex items-center gap-1 rounded-lg border border-white/15 bg-white/10 px-2 py-1 text-[11px] font-semibold text-white hover:bg-white/20 transition"
                >
                    <svg class="h-3.5 w-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                    </svg>
                    <span>Setting</span>
                </a>
            @endif
        </div>
    </div>
@endif

