@php
    $user = auth()->user();
    $desa = $user?->desaWilayah();
    $namaBrand = $desa
        ? ($desa->pengaturanDesa('nama_tampilan') ?: $desa->nama)
        : 'Rukun Data';
@endphp
<span class="flex items-center gap-2.5 min-w-0">
    <img
        src="{{ asset('images/brand-mark.svg') }}"
        alt=""
        class="h-8 w-8 shrink-0 rounded-lg"
    >
    <span class="flex flex-col min-w-0 leading-tight">
        <span class="text-sm font-semibold tracking-[-0.02em] truncate">{{ $namaBrand }}</span>
        @if($desa)
            <span class="text-[10px] font-medium opacity-65 truncate">Rukun Data · Layanan Desa</span>
        @endif
    </span>
</span>
