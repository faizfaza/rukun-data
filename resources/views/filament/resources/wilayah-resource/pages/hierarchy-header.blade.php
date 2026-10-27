@php
    $parent = $this->currentParent();
    $breadcrumbs = $this->breadcrumbsList();
    $mode = $this->mode;
    $user = auth()->user();
    $isRw = $user?->isAdminRw();
    $rootUrl = $isRw && $user->id_wilayah
        ? \App\Filament\Resources\WilayahResource::getUrl('index', ['parent' => $user->id_wilayah])
        : \App\Filament\Resources\WilayahResource::getUrl('index');
    $rootLabel = $isRw ? ($user->wilayah?->nama ?? 'RW Saya') : 'Akar Wilayah';
    $isAtRoot = $isRw 
        ? ($parent === null || $parent->id === $user->id_wilayah)
        : ($parent === null);
@endphp

<div class="mb-5 flex flex-col gap-3">
    {{-- Bar Navigasi Hirarki & Switcher Tampilan --}}
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white p-3 shadow-sm dark:border-white/10 dark:bg-gray-900">
        
        {{-- Sisi Kiri: Breadcrumb Trail --}}
        <div class="flex flex-wrap items-center gap-1.5 text-sm">
            {{-- Tombol / Tautan Akar --}}
            <a
                href="{{ $rootUrl }}"
                wire:navigate
                class="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1 text-xs font-medium transition {{ $isAtRoot && $mode === 'hirarki' ? 'bg-primary-50 text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-950/50 dark:text-primary-300 font-semibold' : 'text-gray-600 hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white' }}"
                title="{{ $rootLabel }}"
            >
                <x-filament::icon icon="heroicon-m-globe-alt" class="h-4 w-4" />
                <span>{{ $rootLabel }}</span>
            </a>

            @if ($mode === 'hirarki' && count($breadcrumbs) > 0)
                @foreach ($breadcrumbs as $node)
                    @if (! $isRw || $node->id !== $user->id_wilayah)
                        <span class="text-gray-300 dark:text-gray-600 font-bold text-xs">/</span>
                        
                        @if (! $loop->last)
                            <a
                                href="{{ \App\Filament\Resources\WilayahResource::getUrl('index', ['parent' => $node->id]) }}"
                                wire:navigate
                                class="inline-flex items-center gap-1 rounded-lg px-2 py-1 text-xs font-medium text-gray-600 transition hover:bg-gray-100 hover:text-gray-900 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-white"
                            >
                                <span>{{ $node->nama }}</span>
                            </a>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-lg bg-primary-50 px-2.5 py-1 text-xs font-bold text-primary-900 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-950/50 dark:text-primary-300">
                                <span>{{ $node->nama }}</span>
                            </span>
                        @endif
                    @endif
                @endforeach
            @endif
        </div>

        {{-- Sisi Kanan: Tombol Naik Level & Mode Switcher --}}
        <div class="flex items-center gap-2">
            @if ($mode === 'hirarki' && ! $isAtRoot && $parent !== null)
                @php
                    $upUrl = $parent->parent_id && (! $isRw || $parent->parent_id !== $user->id_wilayah)
                        ? \App\Filament\Resources\WilayahResource::getUrl('index', ['parent' => $parent->parent_id])
                        : $rootUrl;
                    $upTarget = $parent->parent;
                    $upLabel = $upTarget ? $upTarget->nama : $rootLabel;
                @endphp
                <a
                    href="{{ $upUrl }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-medium text-gray-700 shadow-sm transition hover:bg-gray-50 hover:text-gray-900 dark:border-white/10 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-white/5"
                    title="Kembali ke {{ $upLabel }}"
                >
                    <x-filament::icon icon="heroicon-m-arrow-left" class="h-3.5 w-3.5" />
                    <span>Naik ke {{ $upLabel }}</span>
                </a>
            @endif

            {{-- Mode Switcher: Hirarki vs Semua (Flat) --}}
            <div class="inline-flex rounded-lg border border-gray-200 bg-gray-100 p-0.5 text-xs dark:border-white/10 dark:bg-gray-800">
                <a
                    href="{{ \App\Filament\Resources\WilayahResource::getUrl('index', array_filter(['parent' => $this->parentId ?? ($isRw ? $user->id_wilayah : null), 'mode' => 'hirarki'])) }}"
                    wire:navigate
                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 font-medium transition {{ $mode === 'hirarki' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
                    title="Tampilan pohon bertahap / hirarki"
                >
                    <x-filament::icon icon="heroicon-m-folder-open" class="h-3.5 w-3.5" />
                    <span>Hirarki</span>
                </a>
                <a
                    href="{{ \App\Filament\Resources\WilayahResource::getUrl('index', ['mode' => 'semua']) }}"
                    wire:navigate
                    class="inline-flex items-center gap-1 rounded-md px-2.5 py-1 font-medium transition {{ $mode === 'semua' ? 'bg-white text-gray-900 shadow-sm dark:bg-gray-700 dark:text-white' : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200' }}"
                    title="Tampilan daftar semua wilayah (flat)"
                >
                    <x-filament::icon icon="heroicon-m-bars-3-bottom-left" class="h-3.5 w-3.5" />
                    <span>Semua (Flat)</span>
                </a>
            </div>
        </div>
    </div>

    {{-- Keterangan Level / Informasi Konteks --}}
    @if ($mode === 'hirarki')
        <div class="flex items-center justify-between text-xs text-gray-500 dark:text-gray-400 px-1">
            <div class="flex items-center gap-2">
                @if ($parent !== null)
                    <span>Menampilkan wilayah di dalam:</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $parent->nama }}</span>
                    <span class="rounded bg-gray-100 px-1.5 py-0.5 text-[11px] font-medium text-gray-700 dark:bg-gray-800 dark:text-gray-300">
                        Level {{ \App\Models\Wilayah::labelLevel($parent->level) }}
                    </span>
                    @if ($parent->rantaiIndukNama())
                        <span class="hidden sm:inline text-gray-400">({{ $parent->rantaiIndukNama() }})</span>
                    @endif
                @else
                    <span>Menampilkan tingkat akar:</span>
                    <span class="font-semibold text-gray-800 dark:text-gray-200">{{ $isRw ? ($user?->wilayah?->nama ?? 'RW') : 'Negara' }}</span>
                    @if (! $isRw)
                        <span class="text-gray-400">— Klik nama atau tombol "Buka" untuk menjelajah turun ke provinsi, kabupaten, dan seterusnya.</span>
                    @endif
                @endif
            </div>
            @if ($parent !== null)
                <div class="font-medium text-gray-600 dark:text-gray-400">
                    {{ $parent->children()->count() }} turunan langsung
                </div>
            @endif
        </div>

        @if ($parent !== null && $parent->children()->distinct()->pluck('level')->count() > 1)
            <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50/70 px-3 py-2 text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/20 dark:text-amber-300">
                <x-filament::icon icon="heroicon-m-information-circle" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                <span>
                    <strong>Pemisahan Struktur:</strong> Wilayah {{ $parent->nama }} membawahi wilayah <strong>Dusun</strong> sekaligus <strong>RW Langsung</strong> (non-dusun). Gunakan tab di atas tabel untuk melihat masing-masing kategori.
                </span>
            </div>
        @endif
    @endif
</div>
