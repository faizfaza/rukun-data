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
        <section class="rd-panel" x-data="{ openFilterModal: false, openJelajah: false }">
            <header class="flex flex-wrap items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="rd-panel-icon">
                        <x-filament::icon icon="heroicon-o-funnel" class="h-4 w-4" />
                    </span>
                    <div>
                        <h2 class="text-[0.95rem] font-bold tracking-tight text-gray-950">Kelola & Filter Admin</h2>
                        <p class="mt-0.5 text-xs text-gray-500">Cari akun, lalu batasi peran, status, atau wilayah penugasan.</p>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <span class="rd-chip">{{ $scopeLabel }}</span>

                    <button
                        type="button"
                        class="rd-quiet-link"
                        x-on:click="openJelajah = ! openJelajah"
                        x-bind:aria-expanded="openJelajah.toString()"
                        aria-controls="jelajah-wilayah-admin"
                    >
                        <span x-text="openJelajah ? 'Tutup jelajah' : 'Jelajah wilayah'"></span>
                        <span class="transition-transform" x-bind:class="openJelajah ? 'rotate-180' : ''">
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

            <!-- Baris Pencarian & Tombol Filter Pop-up -->
            <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-gray-100 pt-4">
                <label class="relative block min-w-[240px] flex-1">
                    <span class="sr-only">Cari admin</span>
                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                    <input
                        type="search"
                        wire:model.live.debounce.500ms="tableSearch"
                        placeholder="Cari nama, username, atau email admin…"
                        aria-label="Cari nama atau username admin"
                        class="rd-search-input"
                    />
                </label>

                <button
                    type="button"
                    @click="openFilterModal = true"
                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border {{ $this->jumlahFilterAktif > 0 ? 'border-teal-300 bg-teal-50 text-teal-700' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }} text-xs font-semibold shadow-sm transition"
                >
                    <x-filament::icon icon="heroicon-m-funnel" class="h-4 w-4 {{ $this->jumlahFilterAktif > 0 ? 'text-teal-600' : 'text-gray-500' }}" />
                    <span>Filter</span>
                    @if ($this->jumlahFilterAktif > 0)
                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-teal-600 text-[10px] font-bold text-white shadow-sm">
                            {{ $this->jumlahFilterAktif }}
                        </span>
                    @endif
                </button>

                @if ($this->hasActiveFilter())
                    <button
                        type="button"
                        wire:click="resetFilter"
                        class="rd-filter-reset"
                        title="Atur ulang semua filter"
                    >
                        <x-filament::icon icon="heroicon-m-arrow-path" class="h-3.5 w-3.5" />
                        <span>Atur ulang</span>
                    </button>
                @endif
            </div>

            <!-- Bubbles / Chips Filter yang Sedang Aktif -->
            @if ($this->jumlahFilterAktif > 0)
                <div class="mt-3 flex flex-wrap items-center gap-1.5 pt-1">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mr-1">Filter Aktif:</span>

                    @if (filled($roleFilter))
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                            <span>Peran: <strong>{{ \App\Enums\Role::tryFrom($roleFilter)?->label() ?? $roleFilter }}</strong></span>
                            <button type="button" wire:click="hapusFilterRole" class="text-teal-400 hover:text-teal-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    @if (filled($statusFilter))
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                            <span>Status: <strong>{{ $statusFilter === 'aktif' ? 'Aktif' : 'Nonaktif' }}</strong></span>
                            <button type="button" wire:click="hapusFilterStatus" class="text-emerald-400 hover:text-emerald-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    @if ($this->wilayah && $this->namaWilayahTerpilih && ! $this->hasGranularFilter())
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                            <span>Wilayah: <strong>{{ $this->namaWilayahTerpilih }}</strong></span>
                            <button type="button" wire:click="hapusFilterWilayah" class="text-teal-400 hover:text-teal-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    @if ($filterProvinsi && $this->namaFilterProvinsi)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                            <span>Provinsi: <strong>{{ $this->namaFilterProvinsi }}</strong></span>
                            <button type="button" wire:click="hapusFilterProvinsi" class="text-teal-400 hover:text-teal-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    @if ($filterKabkota && $this->namaKabkotaTerpilih)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                            <span>Kab/Kota: <strong>{{ $this->namaKabkotaTerpilih }}</strong></span>
                            <button type="button" wire:click="hapusFilterKabkota" class="text-teal-400 hover:text-teal-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    @if ($filterKecamatan && $this->namaKecamatanTerpilih)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                            <span>Kecamatan: <strong>{{ $this->namaKecamatanTerpilih }}</strong></span>
                            <button type="button" wire:click="hapusFilterKecamatan" class="text-teal-400 hover:text-teal-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    @if ($filterDesa && $this->namaDesaTerpilih)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                            <span>Desa: <strong>{{ $this->namaDesaTerpilih }}</strong></span>
                            <button type="button" wire:click="hapusFilterDesa" class="text-teal-400 hover:text-teal-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    @if ($filterRw && $this->namaRwTerpilih)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                            <span>RW: <strong>{{ $this->namaRwTerpilih }}</strong></span>
                            <button type="button" wire:click="hapusFilterRw" class="text-teal-400 hover:text-teal-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    @if ($filterRt && $this->namaRtTerpilih)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                            <span>RT: <strong>{{ $this->namaRtTerpilih }}</strong></span>
                            <button type="button" wire:click="hapusFilterRt" class="text-teal-400 hover:text-teal-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    @if ($global)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-amber-50 text-amber-700 border border-amber-200">
                            <span>Hanya Tanpa Penugasan</span>
                            <button type="button" wire:click="$set('global', false); resetTable()" class="text-amber-400 hover:text-amber-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    @if (! $turunan && ! $global)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 border border-slate-200">
                            <span>Tanpa Turunan</span>
                            <button type="button" wire:click="$set('turunan', true); resetTable()" class="text-slate-400 hover:text-slate-700">
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                            </button>
                        </span>
                    @endif

                    <button
                        type="button"
                        wire:click="resetFilter"
                        class="text-xs font-semibold text-gray-500 hover:text-rose-600 hover:underline ml-1"
                    >
                        Hapus semua filter
                    </button>
                </div>
            @endif

            <div class="mt-3 flex items-center justify-between text-xs text-gray-500">
                <span>
                    {{ number_format($counters[0], 0, ',', '.') }} admin
                    @if (! $global)
                        · {{ number_format($counters[1], 0, ',', '.') }} keluarga
                    @endif
                </span>
            </div>

            <!-- POP-UP MODAL FILTER ADMIN (TELEPORTED TO BODY) -->
            <template x-teleport="body">
                <div
                    x-show="openFilterModal"
                    style="display: none;"
                    class="fixed inset-0 z-[9999] overflow-y-auto"
                    role="dialog"
                    aria-modal="true"
                    @keydown.escape.window="openFilterModal = false"
                >
                    <!-- Full-screen backdrop -->
                    <div
                        x-show="openFilterModal"
                        x-transition:enter="ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"
                        @click="openFilterModal = false"
                    ></div>

                    <!-- Modal dialog container -->
                    <div class="fixed inset-0 z-[10000] flex min-h-screen items-center justify-center p-4 sm:p-6 pointer-events-none">
                        <div
                            x-show="openFilterModal"
                            x-transition:enter="ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95 translate-y-3"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 scale-95 translate-y-3"
                            class="relative w-full max-w-xl max-h-[88vh] flex flex-col overflow-hidden rounded-2xl bg-white text-left shadow-2xl border border-gray-100 pointer-events-auto"
                            @click.stop
                        >
                            <!-- Modal Header -->
                            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-white shrink-0">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                                        <x-filament::icon icon="heroicon-o-funnel" class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-gray-950" id="modal-filter-admin-title">Filter Admin</h3>
                                        <p class="text-xs text-gray-500">Tentukan kriteria peran, status, dan wilayah penugasan admin.</p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    @click="openFilterModal = false"
                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition"
                                >
                                    <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                                </button>
                            </div>

                            <!-- Modal Body (Scrollable) -->
                            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5 space-y-4">
                                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                    <label class="block space-y-1">
                                        <span class="text-xs font-bold text-gray-800">Peran Admin</span>
                                        <select wire:model.live="roleFilter" class="rd-select text-xs">
                                            <option value="">Semua Peran</option>
                                            @foreach (\App\Enums\Role::options() as $value => $label)
                                                <option value="{{ $value }}">{{ $label }}</option>
                                            @endforeach
                                        </select>
                                    </label>

                                    <label class="block space-y-1">
                                        <span class="text-xs font-bold text-gray-800">Status Akun</span>
                                        <select wire:model.live="statusFilter" class="rd-select text-xs">
                                            <option value="">Semua Status</option>
                                            <option value="aktif">Aktif</option>
                                            <option value="nonaktif">Nonaktif</option>
                                        </select>
                                    </label>
                                </div>

                                <div class="rounded-xl border border-gray-100 bg-gray-50/70 p-3.5 space-y-2">
                                    <span class="text-xs font-bold text-gray-800 block">Cakupan Wilayah</span>
                                    <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:gap-4 text-xs font-medium text-gray-700">
                                        <label class="inline-flex cursor-pointer items-center gap-2">
                                            <input type="checkbox" wire:model.live="turunan" class="rd-checkbox">
                                            <span>Termasuk wilayah turunan</span>
                                        </label>
                                        <label class="inline-flex cursor-pointer items-center gap-2">
                                            <input type="checkbox" wire:model.live="global" class="rd-checkbox">
                                            <span>Hanya admin tanpa penugasan</span>
                                        </label>
                                    </div>
                                </div>

                                <!-- Hierarki Wilayah Penugasan -->
                                <div class="rounded-xl border border-teal-100 bg-teal-50/40 p-4">
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-teal-600 text-[10px] font-bold text-white">
                                                <x-filament::icon icon="heroicon-m-map-pin" class="h-3 w-3" />
                                            </span>
                                            <span class="text-xs font-bold text-gray-900 uppercase tracking-wider">Wilayah Penugasan & RT / RW</span>
                                        </div>

                                        @if ($this->hasGranularFilter())
                                            <button
                                                type="button"
                                                wire:click="resetGranularFilters"
                                                class="text-xs font-semibold text-teal-700 hover:text-teal-900 hover:underline"
                                            >
                                                Kosongkan Wilayah
                                            </button>
                                        @elseif ($this->wilayah)
                                            <button
                                                type="button"
                                                wire:click="pilih(0)"
                                                class="text-xs font-semibold text-teal-700 hover:text-teal-900 hover:underline"
                                            >
                                                Semua Wilayah
                                            </button>
                                        @endif
                                    </div>
                                    <p class="text-[11px] text-gray-500 mb-3">
                                        Ketik nama/nomor wilayah penugasan admin. Tidak wajib berurutan (misal: cukup ketik RW untuk melihat seluruh admin RW 01 di semua daerah).
                                    </p>

                                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Provinsi</span>
                                            <input
                                                type="text"
                                                list="list-opsi-admin-provinsi"
                                                wire:model.live.debounce.500ms="filterProvinsi"
                                                placeholder="Ketik mis. Jawa Barat"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-opsi-admin-provinsi">
                                                @foreach ($this->daftarProvinsi as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Kabupaten / Kota</span>
                                            <input
                                                type="text"
                                                list="list-opsi-admin-kabkota"
                                                wire:model.live.debounce.500ms="filterKabkota"
                                                placeholder="Ketik mis. Bandung"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-opsi-admin-kabkota">
                                                @foreach ($this->daftarKabkota as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Kecamatan</span>
                                            <input
                                                type="text"
                                                list="list-opsi-admin-kecamatan"
                                                wire:model.live.debounce.500ms="filterKecamatan"
                                                placeholder="Ketik mis. Bojongsoang"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-opsi-admin-kecamatan">
                                                @foreach ($this->daftarKecamatan as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Desa / Kelurahan / Dusun</span>
                                            <input
                                                type="text"
                                                list="list-opsi-admin-desa"
                                                wire:model.live.debounce.500ms="filterDesa"
                                                placeholder="Ketik mis. Lengkong atau Dusun Krajan"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-opsi-admin-desa">
                                                @foreach ($this->daftarDesa as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">RW</span>
                                            <input
                                                type="text"
                                                list="list-opsi-admin-rw"
                                                wire:model.live.debounce.500ms="filterRw"
                                                placeholder="Ketik mis. RW 01 atau 01"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-opsi-admin-rw">
                                                @foreach ($this->daftarRw as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">RT</span>
                                            <input
                                                type="text"
                                                list="list-opsi-admin-rt"
                                                wire:model.live.debounce.500ms="filterRt"
                                                placeholder="Ketik mis. RT 01 atau 01"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-opsi-admin-rt">
                                                @foreach ($this->daftarRt as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            <!-- Modal Footer (Always visible at bottom) -->
                            <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50/80 px-6 py-3.5 shrink-0">
                                <button
                                    type="button"
                                    wire:click="resetFilter"
                                    class="text-xs font-semibold text-gray-600 hover:text-gray-950 transition"
                                >
                                    Atur Ulang
                                </button>

                                <button
                                    type="button"
                                    @click="openFilterModal = false"
                                    class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-teal-600 text-white hover:bg-teal-700 shadow-sm transition"
                                >
                                    <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                                    <span>Terapkan Filter</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div id="jelajah-wilayah-admin" class="mt-4 border-t border-gray-100 pt-4" x-show="openJelajah" style="display: none">

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
