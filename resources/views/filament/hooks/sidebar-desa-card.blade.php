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

    $bisaAturDesa = (bool) ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminDesa());
    $urlPengaturan = $bisaAturDesa
        ? \App\Filament\Pages\PengaturanDesa::getUrl(array_filter(['desa' => $desa?->id]))
        : null;
@endphp

@if($user)
    <div class="mx-3 mt-1 mb-3 rounded-xl border border-[#cbe1fa] bg-gradient-to-br from-[#f4f9ff] to-[#ffffff] p-3.5 shadow-[0_2px_8px_rgba(11,74,125,0.06)]">
        <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-1.5">
                <span class="inline-block h-2 w-2 shrink-0 rounded-full bg-[#0b4a7d]"></span>
                <span class="text-[10px] font-bold uppercase tracking-wider text-[#0b4a7d]">
                    {{ $user->isSuperadmin() || $user->isAdmin() ? 'Desa Aktif' : 'Wilayah Desa' }}
                </span>
            </div>

            @if($urlPengaturan)
                <a
                    href="{{ $urlPengaturan }}"
                    wire:navigate
                    title="Pengaturan & Kustomisasi Desa"
                    class="shrink-0 inline-flex items-center gap-1 rounded-md border border-[#cbe1fa] bg-[#ebf4ff] px-2 py-0.5 text-[10px] font-semibold text-[#0b4a7d] hover:bg-[#0b4a7d] hover:text-white transition"
                >
                    <span>Atur</span>
                </a>
            @endif
        </div>

        <div class="mt-1.5 text-sm font-bold text-[#0f2643] leading-snug break-words">
            {{ $namaDesa }}
        </div>

        <div class="mt-0.5 text-[11px] text-[#475569] leading-relaxed break-words">
            {{ $sloganDesa }}
        </div>

        @if($desa && filled($pengaturan['nama_kepala_desa'] ?? null))
            <div class="mt-2 pt-2 border-t border-[#e2edf9] text-[11px] font-medium text-[#334155] leading-snug break-words">
                <span class="text-[#64748b] font-normal">Kepala Desa:</span> {{ $pengaturan['nama_kepala_desa'] }}
            </div>
        @endif
    </div>
@endif
