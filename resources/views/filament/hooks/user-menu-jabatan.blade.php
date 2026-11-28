@php
    $user = auth()->user();
    if (! $user) {
        return;
    }

    $jabatan = $user->role?->label() ?? 'Pengguna';
    $wilayahTugas = $user->wilayah?->nama;
    $indukWilayah = $user->wilayah?->parent?->nama;
@endphp

<div class="px-3 py-2 border-b border-[#e5ecf4] bg-[#f8fbff]">
    <div class="text-[11px] font-medium text-[#64748b] truncate">
        {{ '@' . $user->username }}
    </div>
    <div class="mt-1 flex flex-wrap items-center gap-1.5">
        <span class="inline-flex items-center rounded-md bg-[#ebf4ff] px-2 py-0.5 text-[11px] font-semibold text-[#0b4a7d] border border-[#cbe1fa]">
            {{ $jabatan }}
        </span>
        @if($wilayahTugas)
            <span class="text-[11px] font-medium text-[#334155] truncate">
                {{ $wilayahTugas }}@if($indukWilayah) · {{ $indukWilayah }}@endif
            </span>
        @endif
    </div>
</div>
