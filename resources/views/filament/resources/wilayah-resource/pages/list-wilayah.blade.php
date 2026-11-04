<x-filament-panels::page>
    @php
        $tabJelajah = \App\Filament\Resources\WilayahResource::getUrl('index', array_filter([
            'tab' => 'jelajah',
            'wilayah' => $this->wilayahId,
        ]));
        $tabMaster = \App\Filament\Resources\WilayahResource::getUrl('index', array_filter([
            'tab' => 'master',
            'parent' => $this->parentId,
            'mode' => $this->mode,
        ]));
    @endphp

    <div class="flex flex-col gap-4">
        @if ($tab === 'master' && $this->bolehMaster())
            <section class="rd-panel">
                <nav class="fi-tabs w-fit">
                    <a
                        href="{{ $tabJelajah }}"
                        wire:navigate
                        class="fi-tabs-item"
                        title="Cari wilayah dan kelola data keluarga"
                    >
                        <x-filament::icon icon="heroicon-m-map" class="h-3.5 w-3.5" />
                        <span class="fi-tabs-item-label">Jelajah</span>
                    </a>

                    <a
                        href="{{ $tabMaster }}"
                        wire:navigate
                        class="fi-tabs-item fi-active"
                        title="Kelola data master wilayah"
                    >
                        <x-filament::icon icon="heroicon-m-building-library" class="h-3.5 w-3.5" />
                        <span class="fi-tabs-item-label">Master Wilayah</span>
                    </a>
                </nav>

                <div class="mt-4 border-t border-gray-100 pt-4">
                    @include('filament.resources.wilayah-resource.pages.hierarchy-header')
                </div>

                <div class="mt-4 grid grid-cols-1 gap-2.5 border-t border-gray-100 pt-4 md:grid-cols-[minmax(0,1fr)_12rem_auto]">
                    <label class="block space-y-1">
                        <span class="rd-filter-label">Cari wilayah</span>
                        <span class="relative block">
                            <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                type="search"
                                wire:model.live.debounce.300ms="tableSearch"
                                placeholder="Nama atau kode wilayah…"
                                aria-label="Cari nama atau kode wilayah"
                                class="rd-search-input"
                            />
                        </span>
                    </label>

                    <label class="block space-y-1">
                        <span class="rd-filter-label">Level</span>
                        <select wire:model.live="levelFilter" class="rd-select">
                            <option value="">Semua level</option>
                            @foreach (\App\Models\Wilayah::levelOptions() as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </label>

                    <div class="flex items-end">
                        @if (filled($this->tableSearch) || filled($levelFilter))
                            <button
                                type="button"
                                wire:click="resetWilayahFilter"
                                class="rd-filter-reset"
                            >
                                <x-filament::icon icon="heroicon-m-arrow-path" class="h-3.5 w-3.5" />
                                <span>Atur ulang</span>
                            </button>
                        @endif
                    </div>
                </div>

                <p class="mt-2 text-[0.7rem] text-gray-500">
                    Pencarian tabel mencakup seluruh wilayah yang dapat Anda akses, termasuk turunan.
                </p>
            </section>

            {{ $this->content }}
        @else
            <livewire:app.filament.pages.jelajah-wilayah
                :tertanam="true"
                :wilayah="$this->titikAwalJelajah()"
                :key="'jelajah-'.$this->wilayahId"
            />
        @endif
    </div>
</x-filament-panels::page>
