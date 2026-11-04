@php
    $parent = $this->currentParent();
    $breadcrumbs = $this->breadcrumbsList();
    $mode = $this->mode;
    $user = auth()->user();
    $isRw = $user?->isAdminRw();
    $rootUrl = $isRw && $user->id_wilayah
        ? \App\Filament\Resources\WilayahResource::getUrl('index', ['parent' => $user->id_wilayah])
        : \App\Filament\Resources\WilayahResource::getUrl('index');
    $rootLabel = $isRw ? ($user->wilayah?->nama ?? 'RW Saya') : 'Nasional';
    $isAtRoot = $isRw
        ? ($parent === null || $parent->id === $user->id_wilayah)
        : ($parent === null);
@endphp

<div class="flex flex-col gap-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div class="flex flex-wrap items-center gap-1.5">
            <a
                href="{{ $rootUrl }}"
                wire:navigate
                class="rd-chip {{ $isAtRoot && $mode === 'hirarki' ? 'rd-chip-active' : '' }}"
                title="{{ $rootLabel }}"
            >
                <x-filament::icon icon="heroicon-m-globe-alt" class="h-3.5 w-3.5" />
                <span>{{ $rootLabel }}</span>
            </a>

            @if ($mode === 'hirarki' && count($breadcrumbs) > 0)
                @foreach ($breadcrumbs as $node)
                    @if (! $isRw || $node->id !== $user->id_wilayah)
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-3 w-3 text-gray-300" />

                        @if (! $loop->last)
                            <a
                                href="{{ \App\Filament\Resources\WilayahResource::getUrl('index', ['parent' => $node->id]) }}"
                                wire:navigate
                                class="rd-chip"
                            >
                                <span>{{ $node->nama }}</span>
                            </a>
                        @else
                            <span class="rd-chip rd-chip-active">
                                <span>{{ $node->nama }}</span>
                            </span>
                        @endif
                    @endif
                @endforeach
            @endif
        </div>

        <div class="flex flex-wrap items-center gap-2">
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
                    class="rd-quiet-link"
                    title="Kembali ke {{ $upLabel }}"
                >
                    <x-filament::icon icon="heroicon-m-arrow-left" class="h-3.5 w-3.5" />
                    <span>Naik ke {{ $upLabel }}</span>
                </a>
            @endif

            <nav class="fi-tabs w-fit">
                <a
                    href="{{ \App\Filament\Resources\WilayahResource::getUrl('index', array_filter(['parent' => $this->parentId ?? ($isRw ? $user->id_wilayah : null), 'mode' => 'hirarki'])) }}"
                    wire:navigate
                    class="fi-tabs-item {{ $mode === 'hirarki' ? 'fi-active' : '' }}"
                    title="Tampilan pohon bertahap / hirarki"
                >
                    <x-filament::icon icon="heroicon-m-folder-open" class="h-3.5 w-3.5" />
                    <span class="fi-tabs-item-label">Per tingkat</span>
                </a>
                <a
                    href="{{ \App\Filament\Resources\WilayahResource::getUrl('index', ['mode' => 'semua']) }}"
                    wire:navigate
                    class="fi-tabs-item {{ $mode === 'semua' ? 'fi-active' : '' }}"
                    title="Tampilan daftar semua wilayah (flat)"
                >
                    <x-filament::icon icon="heroicon-m-bars-3-bottom-left" class="h-3.5 w-3.5" />
                    <span class="fi-tabs-item-label">Semua</span>
                </a>
            </nav>
        </div>
    </div>

    @if ($mode === 'hirarki' && $parent !== null && $parent->children()->distinct()->pluck('level')->count() > 1)
        <div class="flex items-start gap-2 rounded-xl border border-amber-200/70 bg-amber-50/60 px-3 py-2.5 text-xs leading-relaxed text-amber-800">
            <x-filament::icon icon="heroicon-m-information-circle" class="mt-0.5 h-4 w-4 shrink-0 text-amber-600" />
            <span>
                <strong>Dusun dan RW langsung:</strong> gunakan tab di atas tabel untuk memfilter.
            </span>
        </div>
    @endif
</div>
