@php
    $user = auth()->user();
    $desa = $user?->desaWilayah();
    $namaDesa = $desa
        ? ($desa->pengaturanDesa('nama_tampilan') ?: $desa->nama)
        : null;
@endphp

@if(! $user)
    {{-- Halaman Login: tampilkan logo resmi RUKUNDATA secara penuh & bersih --}}
    <div class="flex flex-col items-center justify-center py-1">
        <img
            src="{{ asset('images/logo-rukundata.png') }}"
            alt="RUKUNDATA — Data Warga, Untuk Pembangunan."
            class="h-12 w-auto object-contain"
        >
    </div>
@else
    {{-- Header Panel: logo resmi RUKUNDATA langsung di atas latar putih bersih --}}
    <span class="flex items-center gap-2.5 min-w-0">
        <img
            src="{{ asset('images/logo-rukundata.png') }}"
            alt="RUKUNDATA"
            class="h-8 w-auto shrink-0 object-contain"
        >
        @if($namaDesa)
            <span class="hidden sm:inline-flex items-center rounded-full bg-[#ebf4ff] px-2.5 py-0.5 text-[11px] font-semibold text-[#0b4a7d] border border-[#cbe1fa] truncate max-w-[11rem]">
                {{ $namaDesa }}
            </span>
        @endif
    </span>
@endif
