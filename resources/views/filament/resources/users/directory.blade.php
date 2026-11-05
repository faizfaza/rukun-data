<x-filament-panels::page>
    @php
        $node = $this->node();
        $counters = $this->counters();
        $nodeOptions = $this->nodes();
        $nodeLabels = \App\Support\WilayahLabels::details($nodeOptions);
        $totalWilayah = $this->totalWilayah();
        $totalHalaman = $this->totalHalamanWilayah();
        $perHalaman = $this->wilayahPerHalaman();
        $awal = $totalWilayah === 0 ? 0 : (($halamanWilayah - 1) * $perHalaman) + 1;
        $akhir = min($halamanWilayah * $perHalaman, $totalWilayah);
        $scopeLabel = $global ? 'Tanpa penugasan' : ($node?->nama ?? 'Semua wilayah');
    @endphp

    <div class="flex flex-col gap-4">
        <section class="rd-panel" x-data="{ open: false }">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="rd-panel-icon">
                        <x-filament::icon icon="heroicon-o-funnel" class="h-4 w-4" />
                    </span>
                    <div>
                        <h2 class="text-[0.95rem] font-bold tracking-tight text-gray-950">Cari & filter admin</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Cari akun, lalu batasi peran, status, atau wilayah.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rd-chip">{{ $scopeLabel }}</span>

                    <button
                        type="button"
                        class="rd-quiet-link"
                        x-on:click="open = ! open"
                        x-bind:aria-expanded="open.toString()"
                        aria-controls="filter-wilayah-admin"
                    >
                        <span x-text="open ? 'Tutup wilayah' : 'Pilih wilayah'"></span>
                        <span class="transition-transform" x-bind:class="open ? 'rotate-180' : ''">
                            <x-filament::icon icon="heroicon-m-chevron-down" class="h-3.5 w-3.5" />
                        </span>
                    </button>

                    @if ($this->hasActiveFilter())
                        <button type="button" wire:click="resetFilter" class="rd-quiet-link">
                            <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            <span>Reset</span>
                        </button>
                    @endif
                </div>
            </header>

            <div class="mt-4 grid grid-cols-1 gap-2.5 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_11rem_11rem]">
                <label class="block space-y-1 sm:col-span-2 xl:col-span-1">
                    <span class="rd-filter-label">Cari admin</span>
                    <span class="relative block">
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="tableSearch"
                            placeholder="Nama atau username…"
                            aria-label="Cari nama atau username admin"
                            class="rd-search-input"
                        />
                    </span>
                </label>

                <label class="block space-y-1">
                    <span class="rd-filter-label">Peran</span>
                    <select wire:model.live="roleFilter" class="rd-select">
                        <option value="">Semua peran</option>
                        @foreach (\App\Enums\Role::options() as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block space-y-1">
                    <span class="rd-filter-label">Status</span>
                    <select wire:model.live="statusFilter" class="rd-select">
                        <option value="">Semua status</option>
                        <option value="aktif">Aktif</option>
                        <option value="nonaktif">Nonaktif</option>
                    </select>
                </label>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-gray-100 pt-3 text-xs font-medium text-gray-600">
                <label class="inline-flex cursor-pointer items-center gap-2">
                    <input type="checkbox" wire:model.live="turunan" class="rd-checkbox">
                    <span>Termasuk wilayah turunan</span>
                </label>
                <label class="inline-flex cursor-pointer items-center gap-2">
                    <input type="checkbox" wire:model.live="global" class="rd-checkbox">
                    <span>Hanya admin tanpa penugasan</span>
                </label>

                <span class="ml-auto text-xs text-gray-500">
                    {{ number_format($counters[0], 0, ',', '.') }} admin
                    @if (! $global)
                        · {{ number_format($counters[1], 0, ',', '.') }} keluarga
                    @endif
                </span>
            </div>

            <div id="filter-wilayah-admin" class="mt-4 border-t border-gray-100 pt-4" x-show="open" style="display: none">
                @if ($node)
                    <div class="mb-3 flex justify-end">
                        <a
                            href="{{ \App\Filament\Resources\WilayahResource::getUrl('index', ['parent' => $node->id]) }}"
                            class="rd-quiet-link"
                        >
                            <span>Kelola wilayah ini</span>
                            <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5" />
                        </a>
                    </div>
                @endif

                <div class="flex flex-wrap items-center gap-1.5">
                    <button
                        type="button"
                        wire:click="pilih(0)"
                        wire:key="wilayah-akar"
                        class="rd-chip {{ $node === null ? 'rd-chip-active' : '' }}"
                    >
                        <x-filament::icon icon="heroicon-m-globe-alt" class="h-3.5 w-3.5" />
                        <span>Semua wilayah</span>
                    </button>

                    @if ($node)
                        @foreach ($node->ancestors()->push($node) as $ancestor)
                            @continue($ancestor->level === 'negara')
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-3 w-3 text-gray-300" />
                            <button
                                type="button"
                                wire:click="pilih({{ $ancestor->id }})"
                                wire:key="wilayah-{{ $ancestor->id }}"
                                class="rd-chip {{ $ancestor->id === $node->id ? 'rd-chip-active' : '' }}"
                            >
                                {{ $ancestor->nama }}
                            </button>
                        @endforeach
                    @endif
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2.5">
                    <label class="relative block min-w-64 flex-1">
                        <span class="sr-only">Cari wilayah</span>
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="cariWilayah"
                            placeholder="Cari nama atau kode wilayah…"
                            aria-label="Cari wilayah"
                            class="rd-search-input"
                        />
                    </label>

                    <div class="flex flex-wrap items-center gap-1.5">
                        <x-filament::button wire:click="mundurWilayah" color="gray" size="sm" icon="heroicon-m-chevron-left" :disabled="$halamanWilayah <= 1">
                            Sebelumnya
                        </x-filament::button>
                        <span class="rounded-lg bg-gray-50 px-2.5 py-1.5 text-xs font-semibold text-gray-600 ring-1 ring-inset ring-gray-200">
                            {{ $halamanWilayah }} / {{ $totalHalaman }}
                        </span>
                        <x-filament::button wire:click="lanjutWilayah" color="gray" size="sm" icon="heroicon-m-chevron-right" icon-position="after" :disabled="$halamanWilayah >= $totalHalaman">
                            Berikutnya
                        </x-filament::button>
                    </div>
                </div>

                <div class="mt-3 grid gap-2 sm:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                    @forelse ($nodeOptions as $child)
                        @php
                            $detail = $nodeLabels[$child->id];
                        @endphp
                        <button
                            type="button"
                            wire:click="pilih({{ $child->id }})"
                            wire:key="anak-{{ $child->id }}"
                            class="rd-list-btn group"
                            title="{{ $detail['label'] }}"
                        >
                            <span class="rd-list-icon">
                                <x-filament::icon
                                    :icon="$child->children_count > 0 ? 'heroicon-m-folder' : 'heroicon-m-map-pin'"
                                    class="h-4 w-4"
                                />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-sm font-semibold text-gray-900 group-hover:text-gray-950">{{ $detail['nama'] }}</span>
                                <span class="mt-0.5 flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-1 text-xs font-medium text-gray-500">
                                    <span class="font-mono">{{ $detail['kode'] }}</span>
                                    @if ($detail['chain'] !== '')
                                        <span class="truncate" title="{{ $detail['chain'] }}">{{ $detail['chain'] }}</span>
                                    @endif
                                    <span @class([
                                        'fi-badge',
                                        'fi-color-success' => $detail['status'] === 'active',
                                        'fi-color-warning' => $detail['status'] === 'inherited',
                                        'fi-color-gray' => $detail['status'] === 'inactive',
                                    ])>{{ $detail['status_label'] }}</span>
                                </span>
                                <span class="mt-0.5 block text-xs font-medium uppercase tracking-wider text-gray-500">{{ \App\Models\Wilayah::labelLevel($child->level) }}</span>
                            </span>
                            <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 shrink-0 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-gray-500" />
                        </button>
                    @empty
                        <div class="col-span-full rounded-xl border border-dashed border-gray-200 bg-gray-50/60 px-4 py-6 text-center text-sm text-gray-500">
                            Tidak ada wilayah yang cocok dengan pencarian.
                        </div>
                    @endforelse
                </div>

                <footer class="mt-3 flex flex-wrap items-center justify-between gap-3 border-t border-gray-100 pt-3">
                    <span class="text-xs text-gray-500">
                        @if ($totalWilayah > 0)
                            Menampilkan {{ $awal }}–{{ $akhir }} dari {{ number_format($totalWilayah, 0, ',', '.') }} wilayah · Halaman {{ $halamanWilayah }} / {{ $totalHalaman }}
                        @else
                            Tidak ada wilayah pada cakupan ini.
                        @endif
                    </span>
                </footer>
            </div>
        </section>

        {{ $this->table }}
    </div>
</x-filament-panels::page>
