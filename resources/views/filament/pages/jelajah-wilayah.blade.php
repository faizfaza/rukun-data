<x-filament-panels::page>
    @php
        $user = auth()->user();
        $nodeAktif = $this->nodeAktif();
        $rt = $this->nodeTerpilihRt();
        $leluhurAwal = $this->leluhurTitikAwal();
        $langkah = $this->langkahSaatIni();
        $semuaOpsi = $langkah !== null ? $this->opsiSaatIni() : collect();
        $hitungan = $langkah !== null ? $this->hitunganOpsi() : [];
        $campurLevel = $semuaOpsi->pluck('level')->unique()->count() > 1;
        $namaNode = str_starts_with(strtolower($nodeAktif->nama), strtolower(\App\Models\Wilayah::labelLevel($nodeAktif->level)))
            ? $nodeAktif->nama
            : \App\Models\Wilayah::labelLevel($nodeAktif->level).' '.$nodeAktif->nama;
        $aksiTertanam = $tertanam ? $this->aksiHeader() : [];

        $grup = $campurLevel
            ? collect([
                ['judul' => 'Dusun', 'opsi' => $semuaOpsi->where('level', 'dusun')],
                ['judul' => 'RW langsung', 'opsi' => $semuaOpsi->where('level', 'rw')],
                [
                    'judul' => 'Wilayah lain',
                    'opsi' => $semuaOpsi->reject(fn ($opsi): bool => in_array($opsi->level, ['dusun', 'rw'], true)),
                ],
            ])->filter(fn (array $g): bool => $g['opsi']->isNotEmpty())
            : collect([['judul' => null, 'opsi' => $semuaOpsi]]);
    @endphp

    <div class="flex flex-col gap-4">
        <section class="rd-panel">
            @if ($tertanam)
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                    <nav class="fi-tabs w-fit">
                        <a
                            href="{{ \App\Filament\Resources\WilayahResource::getUrl('index', ['tab' => 'jelajah']) }}"
                            wire:navigate
                            class="fi-tabs-item fi-active"
                            title="Cari wilayah dan kelola data keluarga"
                        >
                            <x-filament::icon icon="heroicon-m-map" class="h-3.5 w-3.5" />
                            <span class="fi-tabs-item-label">Jelajah</span>
                        </a>

                        @if ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminRw())
                            <a
                                href="{{ \App\Filament\Resources\WilayahResource::getUrl('index', ['tab' => 'master']) }}"
                                wire:navigate
                                class="fi-tabs-item"
                                title="Kelola data master wilayah"
                            >
                                <x-filament::icon icon="heroicon-m-building-library" class="h-3.5 w-3.5" />
                                <span class="fi-tabs-item-label">Master Wilayah</span>
                            </a>
                        @endif
                    </nav>

                    @if (filled($aksiTertanam))
                        <div class="flex flex-wrap items-center justify-end gap-2">
                            <x-filament::actions :actions="$aksiTertanam" />
                        </div>
                    @endif
                </div>
            @endif

            <div @class(['flex flex-wrap items-center gap-x-2 gap-y-1.5', 'pt-4' => $tertanam])>
                <span class="rd-panel-icon h-7 w-7">
                    <x-filament::icon icon="heroicon-o-map-pin" class="h-3.5 w-3.5" />
                </span>
                <span class="text-xs font-bold uppercase tracking-[0.08em] text-gray-400">
                    {{ $user?->isAdminRt() ? 'Wilayah tugas' : 'Lokasi aktif' }}
                </span>
                <span class="rd-chip rd-chip-active">{{ $namaNode }}</span>

                @if ($user?->isSuperadmin() || $user?->isAdmin())
                    <span class="rd-chip ml-auto">
                        <x-filament::icon icon="heroicon-m-globe-alt" class="h-3.5 w-3.5" />
                        <span>Nasional</span>
                    </span>
                @endif
            </div>

            <p class="mt-2 pl-9 text-xs text-gray-500">{{ $this->lokasiLengkap($nodeAktif) }}</p>

            <div x-data="{ openFilterModal: false }">
                <!-- Baris Pencarian Lintas Tingkat & Tombol Filter Pop-up -->
                <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-gray-100 pt-4">
                    <label class="relative block min-w-[240px] flex-1">
                        <span class="sr-only">Cari wilayah di semua tingkat</span>
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                        <input
                            type="search"
                            wire:model.live.debounce.500ms="cariGlobal"
                            placeholder="Cari wilayah di semua tingkat (nama atau kode, mis. Bojongsoang)…"
                            class="rd-search-input"
                            autocomplete="off"
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
                            wire:click="resetSemuaFilter"
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

                        @if (filled($statusFilter))
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                <span>Status: <strong>{{ $statusFilter === 'aktif' ? 'Wilayah Aktif' : ($statusFilter === 'nonaktif' ? 'Nonaktif' : 'Induk Down') }}</strong></span>
                                <button type="button" wire:click="hapusFilterStatus" class="text-emerald-400 hover:text-emerald-700">
                                    <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                                </button>
                            </span>
                        @endif

                        @if ($this->namaWilayahTerpilih && ! $this->hasGranularFilter())
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                <span>Wilayah: <strong>{{ $this->namaWilayahTerpilih }}</strong></span>
                                <button type="button" wire:click="hapusFilterLompat" class="text-teal-400 hover:text-teal-700">
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

                        <button
                            type="button"
                            wire:click="resetSemuaFilter"
                            class="text-xs font-semibold text-gray-500 hover:text-rose-600 hover:underline ml-1"
                        >
                            Hapus semua filter
                        </button>
                    </div>
                @endif

                @php $hasilGlobal = $this->hasilCariGlobal(); @endphp

                @if (mb_strlen(trim($cariGlobal)) >= 2 || $this->hasGranularFilter())
                    <div class="mt-4 mb-2 flex items-center justify-between">
                        <span class="text-xs font-bold text-gray-700 uppercase tracking-wider">
                            {{ $this->hasGranularFilter() ? 'Hasil Filter Wilayah (' . count($hasilGlobal) . ')' : 'Hasil Pencarian (' . count($hasilGlobal) . ')' }}
                        </span>
                        @if ($this->hasGranularFilter())
                            <button
                                type="button"
                                wire:click="resetGranularFilters"
                                class="text-xs font-semibold text-rose-600 hover:underline"
                            >
                                Kosongkan filter wilayah
                            </button>
                        @endif
                    </div>
                    <div class="mt-2 overflow-hidden rounded-xl border border-gray-200 bg-white shadow-sm">
                        @forelse ($hasilGlobal as $hasil)
                            <button
                                type="button"
                                wire:key="global-{{ $hasil['id'] }}"
                                wire:click="lompatKe({{ $hasil['id'] }})"
                                class="group flex w-full items-center justify-between gap-3 border-b border-gray-100 px-4 py-3 text-left transition last:border-b-0 hover:bg-gray-50"
                            >
                                <span class="min-w-0">
                                    <span class="block truncate text-sm font-semibold text-gray-900">{{ $hasil['nama'] }}</span>
                                    <span class="block truncate text-xs text-gray-500">{{ $hasil['chain'] ?: '—' }}</span>
                                </span>
                                <span class="flex shrink-0 items-center gap-2">
                                    @if ($hasil['status'] !== 'active')
                                        <span class="rd-chip border-rose-200 bg-rose-50 text-rose-700">{{ $hasil['status_label'] }}</span>
                                    @endif
                                    <span class="rd-chip">{{ $hasil['level'] }}</span>
                                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-300 group-hover:text-gray-500" />
                                </span>
                            </button>
                        @empty
                            <div class="px-4 py-5 text-center text-sm text-gray-500">
                                Tidak ada wilayah yang cocok dengan kriteria filter.
                            </div>
                        @endforelse
                    </div>
                @endif

                <!-- POP-UP MODAL FILTER KELOLA WARGA (TELEPORTED TO BODY) -->
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
                                            <h3 class="text-base font-bold text-gray-950" id="modal-filter-warga-title">Filter Kelola Warga</h3>
                                            <p class="text-xs text-gray-500">Saring wilayah berdasarkan status operasional atau lompat ke hierarki wilayah tertentu.</p>
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
                                    <label class="block space-y-1">
                                        <span class="text-xs font-bold text-gray-800">Status Operasional Wilayah</span>
                                        <select wire:model.live="statusFilter" class="rd-select text-xs">
                                            <option value="">Semua Status</option>
                                            <option value="aktif">Hanya Wilayah Aktif</option>
                                            <option value="nonaktif">Hanya Wilayah Nonaktif</option>
                                            <option value="down">Hanya Down (Induk Nonaktif)</option>
                                        </select>
                                    </label>

                                    <!-- Filter Wilayah Fleksibel (Input Ketik + Datalist) -->
                                    <div class="rounded-xl border border-teal-100 bg-teal-50/40 p-4">
                                        <div class="flex items-center justify-between gap-2 mb-2">
                                            <div class="flex items-center gap-2">
                                                <span class="flex h-5 w-5 items-center justify-center rounded-full bg-teal-600 text-[10px] font-bold text-white">
                                                    <x-filament::icon icon="heroicon-m-map-pin" class="h-3 w-3" />
                                                </span>
                                                <span class="text-xs font-bold text-gray-900 uppercase tracking-wider">Filter Wilayah & RT / RW</span>
                                            </div>

                                            @if ($this->hasGranularFilter())
                                                <button
                                                    type="button"
                                                    wire:click="resetGranularFilters"
                                                    class="text-xs font-semibold text-teal-700 hover:text-teal-900 hover:underline"
                                                >
                                                    Kosongkan Wilayah
                                                </button>
                                            @elseif ($this->namaWilayahTerpilih)
                                                <button
                                                    type="button"
                                                    wire:click="hapusFilterLompat"
                                                    class="text-xs font-semibold text-teal-700 hover:text-teal-900 hover:underline"
                                                >
                                                    Kembali ke Nasional
                                                </button>
                                            @endif
                                        </div>
                                        <p class="text-[11px] text-gray-500 mb-3">
                                            Ketik nama/nomor wilayah yang ingin disaring. Tidak wajib berurutan (misal: cukup ketik RW untuk melihat seluruh RW 01 di semua daerah).
                                        </p>

                                        <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                            <label class="block space-y-1">
                                                <span class="text-[11px] font-semibold text-gray-700">Provinsi</span>
                                                <input
                                                    type="text"
                                                    list="list-opsi-warga-provinsi"
                                                    wire:model.live.debounce.500ms="filterProvinsi"
                                                    placeholder="Ketik mis. Jawa Barat"
                                                    class="rd-search-input text-xs"
                                                />
                                                <datalist id="list-opsi-warga-provinsi">
                                                    @foreach ($this->daftarProvinsi as $nama)
                                                        <option value="{{ $nama }}"></option>
                                                    @endforeach
                                                </datalist>
                                            </label>

                                            <label class="block space-y-1">
                                                <span class="text-[11px] font-semibold text-gray-700">Kabupaten / Kota</span>
                                                <input
                                                    type="text"
                                                    list="list-opsi-warga-kabkota"
                                                    wire:model.live.debounce.500ms="filterKabkota"
                                                    placeholder="Ketik mis. Garut atau Bandung"
                                                    class="rd-search-input text-xs"
                                                />
                                                <datalist id="list-opsi-warga-kabkota">
                                                    @foreach ($this->daftarKabkota as $nama)
                                                        <option value="{{ $nama }}"></option>
                                                    @endforeach
                                                </datalist>
                                            </label>

                                            <label class="block space-y-1">
                                                <span class="text-[11px] font-semibold text-gray-700">Kecamatan</span>
                                                <input
                                                    type="text"
                                                    list="list-opsi-warga-kecamatan"
                                                    wire:model.live.debounce.500ms="filterKecamatan"
                                                    placeholder="Ketik mis. Bojongsoang"
                                                    class="rd-search-input text-xs"
                                                />
                                                <datalist id="list-opsi-warga-kecamatan">
                                                    @foreach ($this->daftarKecamatan as $nama)
                                                        <option value="{{ $nama }}"></option>
                                                    @endforeach
                                                </datalist>
                                            </label>

                                            <label class="block space-y-1">
                                                <span class="text-[11px] font-semibold text-gray-700">Desa / Kelurahan / Dusun</span>
                                                <input
                                                    type="text"
                                                    list="list-opsi-warga-desa"
                                                    wire:model.live.debounce.500ms="filterDesa"
                                                    placeholder="Ketik mis. Lengkong atau Dusun Krajan"
                                                    class="rd-search-input text-xs"
                                                />
                                                <datalist id="list-opsi-warga-desa">
                                                    @foreach ($this->daftarDesa as $nama)
                                                        <option value="{{ $nama }}"></option>
                                                    @endforeach
                                                </datalist>
                                            </label>

                                            <label class="block space-y-1">
                                                <span class="text-[11px] font-semibold text-gray-700">RW</span>
                                                <input
                                                    type="text"
                                                    list="list-opsi-warga-rw"
                                                    wire:model.live.debounce.500ms="filterRw"
                                                    placeholder="Ketik mis. RW 01 atau 01"
                                                    class="rd-search-input text-xs"
                                                />
                                                <datalist id="list-opsi-warga-rw">
                                                    @foreach ($this->daftarRw as $nama)
                                                        <option value="{{ $nama }}"></option>
                                                    @endforeach
                                                </datalist>
                                            </label>

                                            <label class="block space-y-1">
                                                <span class="text-[11px] font-semibold text-gray-700">RT</span>
                                                <input
                                                    type="text"
                                                    list="list-opsi-warga-rt"
                                                    wire:model.live.debounce.500ms="filterRt"
                                                    placeholder="Ketik mis. RT 01 atau 01"
                                                    class="rd-search-input text-xs"
                                                />
                                                <datalist id="list-opsi-warga-rt">
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
                                        wire:click="resetSemuaFilter"
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
            </div>


            @if ($langkah !== null)
                <div class="mt-4 border-t border-gray-100 pt-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <h2 class="text-sm font-bold tracking-tight text-gray-950">Pilih {{ $langkah['label'] }}</h2>
                            <p class="mt-0.5 text-xs text-gray-500">{{ $semuaOpsi->count() }} pilihan</p>
                        </div>

                        @if ($campurLevel)
                            <span class="rd-chip">Dusun & RW langsung</span>
                        @endif
                    </div>

                    <div class="mt-3 flex flex-wrap items-center gap-1.5 text-xs">
                        @foreach ($leluhurAwal as $leluhur)
                            @continue($leluhur->level === 'negara')
                            <span wire:key="leluhur-{{ $leluhur->id }}" class="inline-flex items-center gap-1.5">
                                <span class="rd-chip">{{ $leluhur->nama }}</span>
                                <span class="text-gray-300">›</span>
                            </span>
                        @endforeach

                        @foreach ($this->rantai() as $i => $node)
                            @php $nama = $node->level === 'negara' ? 'Nasional' : $node->nama; @endphp
                            <span wire:key="chip-{{ $i }}-{{ $node->id }}" class="inline-flex items-center gap-1.5">
                                @if (! $loop->last)
                                    <button
                                        type="button"
                                        wire:click="kembaliKe({{ $i }})"
                                        title="Kembali ke {{ $nama }}"
                                        class="rd-chip"
                                    >
                                        {{ $nama }}
                                    </button>
                                    <span class="text-gray-300">›</span>
                                @else
                                    <span class="rd-chip rd-chip-active">{{ $nama }}</span>
                                @endif
                            </span>
                        @endforeach
                    </div>

                    <label class="relative mt-3 block">
                        <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                        <input
                            type="search"
                            wire:model.live.debounce.300ms="cari"
                            placeholder="Cari {{ strtolower($langkah['label']) }}…"
                            class="rd-search-input"
                        />
                    </label>

                    @if ($campurLevel)
                        <p class="mt-2 text-xs text-gray-500">
                            Desa ini punya dusun dan RW langsung. Pilih salah satu.
                        </p>
                    @endif

                    <div
                        wire:loading.class="opacity-60"
                        wire:target="pilih,cari,kembaliKe"
                        class="mt-3 space-y-4 transition-opacity"
                    >
                        @foreach ($grup as $g)
                            <div>
                                @if ($g['judul'] !== null)
                                    <div class="mb-2 flex items-center justify-between px-1 text-xs font-bold uppercase tracking-[0.08em] text-gray-500">
                                        <span>{{ $g['judul'] }}</span>
                                        <span>{{ $g['opsi']->count() }}</span>
                                    </div>
                                @endif

                                <div class="grid grid-cols-1 gap-2 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4">
                                    @forelse ($g['opsi'] as $opsi)
                                        @php
                                            $h = $hitungan[$opsi->id] ?? null;
                                            $ikon = match ($opsi->level) {
                                                'dusun' => 'heroicon-m-folder',
                                                'rw' => 'heroicon-m-home-modern',
                                                'rt' => 'heroicon-m-home',
                                                default => 'heroicon-m-map-pin',
                                            };
                                        @endphp

                                        <button
                                            type="button"
                                            wire:key="opsi-{{ $opsi->id }}"
                                            wire:click="pilih({{ $langkah['indeks'] }}, {{ $opsi->id }})"
                                            class="group flex w-full items-center justify-between gap-3 rounded-xl border border-gray-200 bg-white px-4 py-3 text-left transition hover:border-gray-300 hover:bg-gray-50"
                                        >
                                            <span class="flex min-w-0 items-center gap-2.5">
                                                <span class="rd-list-icon h-7 w-7">
                                                    <x-filament::icon :icon="$ikon" class="h-3.5 w-3.5" />
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-gray-900">{{ $opsi->nama }}</span>
                                                    <span class="block text-xs text-gray-400">{{ $this->labelUntuk($opsi) }}</span>
                                                </span>
                                            </span>

                                            <span class="flex shrink-0 items-center gap-2">
                                                @if (! $opsi->is_active)
                                                    <span class="rd-chip border-rose-200 bg-rose-50 text-rose-700">Nonaktif</span>
                                                @elseif (! $opsi->aktifEfektif())
                                                    <span class="rd-chip border-amber-200 bg-amber-50 text-amber-700">Induk Down</span>
                                                @endif
                                                @if ($h !== null)
                                                    <span class="rd-chip">{{ $h['label'] }}</span>
                                                @endif
                                                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-gray-500" />
                                            </span>
                                        </button>
                                    @empty
                                        <div class="col-span-full rounded-xl border border-dashed border-gray-200 px-4 py-6 text-center text-sm text-gray-500">
                                            {{ $cari !== '' ? 'Tidak ada hasil untuk "'.$cari.'".' : 'Belum ada '.strtolower($langkah['label']).' di wilayah ini.' }}
                                        </div>
                                    @endforelse
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
        </section>

        @if ($rt !== null)
            @if (! $rt->aktifEfektif())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 flex items-center gap-3">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 text-rose-600 shrink-0" />
                    <div>
                        <p class="font-semibold">Wilayah ini sedang DOWN / Nonaktif</p>
                        <p class="text-xs text-rose-700 mt-0.5">
                            {{ ! $rt->is_active ? 'RT ini dinonaktifkan secara langsung.' : 'RT ini tidak operasional karena induk wilayahnya sedang dinonaktifkan.' }}
                            Penambahan data keluarga di wilayah ini tidak dapat dilakukan sampai wilayah diaktifkan kembali.
                        </p>
                    </div>
                </div>
            @endif

            <x-filament::section class="rd-table-section">
                <x-slot name="heading">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-[0.95rem] font-bold tracking-tight text-gray-950">Keluarga di {{ $rt->nama }}</span>
                        <span class="rd-chip">RT</span>
                    </div>
                </x-slot>

                <x-slot name="headerEnd">
                    @if ($this->bolehTambahKeluarga())
                        <x-filament::button
                            :href="\App\Filament\Resources\KeluargaResource::getUrl('create', ['id_wilayah' => $rt->id])"
                            icon="heroicon-m-plus"
                            size="sm"
                        >
                            Tambah keluarga
                        </x-filament::button>
                    @endif
                </x-slot>

                <x-slot name="description">
                    {{ $this->ringkasanKk() }}
                </x-slot>

                {{ $this->table }}
            </x-filament::section>
        @elseif ($this->buntu())
            <x-filament::section heading="{{ $this->nodeAktif()->nama }}">
                <div class="text-sm text-gray-500">
                    Belum ada wilayah di bawah {{ $this->nodeAktif()->nama }}.
                </div>
            </x-filament::section>
        @endif

        {{-- Tabel hanya dirender di level RT dan membawa wadah modalnya sendiri; di level
             lain wadah modal harus ada supaya aksi "Cari No KK / NIK" bisa tampil. --}}
        @if ($rt === null)
            <x-filament-actions::modals />
        @endif
    </div>
</x-filament-panels::page>
