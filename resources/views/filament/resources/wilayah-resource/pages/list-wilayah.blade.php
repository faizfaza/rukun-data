<x-filament-panels::page>
    <div class="flex flex-col gap-4">
        @if ($this->isViewingRt())
            @php
                $rt = $this->currentParent();
                $stats = $this->statistikRt;
                $keluargaList = $this->daftarKeluarga;
            @endphp

            <section class="rd-panel">
                @include('filament.resources.wilayah-resource.pages.hierarchy-header')
            </section>

            @if ($rt && ! $rt->aktifEfektif())
                <div class="rounded-xl border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800 flex items-center gap-3">
                    <x-filament::icon icon="heroicon-m-exclamation-triangle" class="h-5 w-5 text-rose-600 shrink-0" />
                    <div>
                        <p class="font-semibold">Wilayah RT ini sedang DOWN / Nonaktif</p>
                        <p class="text-xs text-rose-700 mt-0.5">
                            {{ ! $rt->is_active ? 'RT ini dinonaktifkan secara langsung.' : 'RT ini tidak operasional karena induk wilayahnya sedang dinonaktifkan.' }}
                            Penambahan data keluarga di RT ini tidak dapat dilakukan sampai wilayah diaktifkan kembali.
                        </p>
                    </div>
                </div>
            @endif

            <section class="rd-panel">
                <div class="flex flex-wrap items-center justify-between gap-3 border-b border-gray-100 pb-4">
                    <div class="flex items-center gap-3">
                        <span class="rd-panel-icon h-9 w-9 bg-emerald-50 text-emerald-600">
                            <x-filament::icon icon="heroicon-o-home" class="h-5 w-5" />
                        </span>
                        <div>
                            <div class="flex items-center gap-2">
                                <h2 class="text-base font-bold text-gray-950">{{ $rt->nama }}</h2>
                                <span class="rd-chip rd-chip-active">RT</span>
                                @if ($rt->aktifEfektif())
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span> Aktif
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200">
                                        <span class="h-1.5 w-1.5 rounded-full bg-rose-500"></span> Down
                                    </span>
                                @endif
                            </div>
                            <p class="text-xs text-gray-500 mt-0.5">{{ $rt->alamatLengkap() }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3 text-xs text-gray-600">
                        <div class="flex items-center gap-1.5 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
                            <x-filament::icon icon="heroicon-m-home-modern" class="h-4 w-4 text-gray-400" />
                            <span class="font-bold text-gray-900">{{ $stats['keluarga'] }}</span> Keluarga (KK)
                        </div>
                        <div class="flex items-center gap-1.5 bg-gray-50 px-3 py-1.5 rounded-lg border border-gray-100">
                            <x-filament::icon icon="heroicon-m-users" class="h-4 w-4 text-gray-400" />
                            <span class="font-bold text-gray-900">{{ $stats['anggota'] }}</span> Warga / Anggota
                        </div>
                        <a
                            href="{{ \App\Filament\Pages\JelajahWilayah::getUrl(['wilayah' => $rt->id]) }}"
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-teal-50 text-teal-700 border border-teal-200 hover:bg-teal-100 transition"
                            title="Buka data warga di Kelola Warga"
                        >
                            <x-filament::icon icon="heroicon-m-arrow-top-right-on-square" class="h-3.5 w-3.5" />
                            <span>Buka di Kelola Warga</span>
                        </a>
                    </div>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="w-full sm:w-72">
                        <span class="relative block">
                            <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                type="search"
                                wire:model.live.debounce.500ms="cariKk"
                                placeholder="Cari No KK atau Kode Pos…"
                                class="rd-search-input"
                            />
                        </span>
                    </div>

                    @if ($rt->aktifEfektif() && (auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin() || auth()->user()?->isAdminRw() || auth()->user()?->isAdminRt()))
                        <a
                            href="{{ \App\Filament\Resources\KeluargaResource::getUrl('create', ['id_wilayah' => $rt->id]) }}"
                            class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-semibold rounded-lg bg-gray-900 text-white hover:bg-gray-800 transition"
                        >
                            <x-filament::icon icon="heroicon-m-plus" class="h-3.5 w-3.5" />
                            <span>Tambah KK di {{ $rt->nama }}</span>
                        </a>
                    @endif
                </div>

                <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white">
                    <table class="w-full text-left text-sm text-gray-700">
                        <thead class="border-b border-gray-100 bg-gray-50/75 text-xs font-bold uppercase tracking-wider text-gray-500">
                            <tr>
                                <th class="px-4 py-3">No KK</th>
                                <th class="px-4 py-3">Kepala Keluarga</th>
                                <th class="px-4 py-3 text-center">Anggota</th>
                                <th class="px-4 py-3">Alamat</th>
                                <th class="px-4 py-3 text-right">Aksi</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            @forelse ($keluargaList as $k)
                                <tr class="hover:bg-gray-50 transition">
                                    <td class="px-4 py-3 font-mono font-medium text-gray-900">
                                        <div class="flex items-center gap-1.5">
                                            <x-filament::icon icon="heroicon-m-lock-closed" class="h-3.5 w-3.5 text-gray-400" />
                                            <span>{{ $k->no_kk_masked }}</span>
                                        </div>
                                    </td>
                                    <td class="px-4 py-3 font-medium text-gray-900">
                                        {{ $k->kepala?->nama_lengkap ?? '—' }}
                                    </td>
                                    <td class="px-4 py-3 text-center">
                                        <span class="rd-chip">{{ $k->anggota_count }} orang</span>
                                    </td>
                                    <td class="px-4 py-3 text-xs text-gray-500 max-w-xs truncate">
                                        {{ $k->alamat ?: '—' }} {{ $k->kode_pos ? "({$k->kode_pos})" : '' }}
                                    </td>
                                    <td class="px-4 py-3 text-right">
                                        <div class="inline-flex items-center gap-2">
                                            <a
                                                href="{{ \App\Filament\Resources\KeluargaResource::getUrl('view', ['record' => $k->id]) }}"
                                                class="rd-quiet-link text-xs font-semibold text-gray-700 hover:text-gray-950"
                                            >
                                                Lihat
                                            </a>
                                            <span class="text-gray-300">·</span>
                                            <a
                                                href="{{ \App\Filament\Resources\KeluargaResource::getUrl('edit', ['record' => $k->id]) }}"
                                                class="rd-quiet-link text-xs font-semibold text-teal-600 hover:text-teal-800"
                                            >
                                                Ubah
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="5" class="px-4 py-8 text-center text-gray-500">
                                        <div class="flex flex-col items-center justify-center gap-2">
                                            <x-filament::icon icon="heroicon-o-users" class="h-8 w-8 text-gray-300" />
                                            <p class="font-medium text-gray-700">Belum ada data keluarga di RT ini.</p>
                                            @if ($rt->aktifEfektif() && (auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin() || auth()->user()?->isAdminRw() || auth()->user()?->isAdminRt()))
                                                <a
                                                    href="{{ \App\Filament\Resources\KeluargaResource::getUrl('create', ['id_wilayah' => $rt->id]) }}"
                                                    class="mt-1 text-xs font-semibold text-teal-600 hover:underline"
                                                >
                                                    + Tambah keluarga sekarang
                                                </a>
                                            @endif
                                        </div>
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Tabel wilayah tidak dirender di tampilan RT, jadi wadah modal aksi (mis. Cari No KK / NIK) dipasang di sini. --}}
            <x-filament-actions::modals />
        @else
            <section class="rd-panel">
                @include('filament.resources.wilayah-resource.pages.hierarchy-header')

                <div x-data="{ openFilterModal: false }">
                    <!-- Baris Pencarian & Tombol Filter Pop-up -->
                    <div class="mt-4 flex flex-wrap items-center gap-2.5 border-t border-gray-100 pt-4">
                        <label class="relative block min-w-[240px] flex-1">
                            <span class="sr-only">Cari nama atau kode wilayah</span>
                            <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-gray-400" />
                            <input
                                type="search"
                                wire:model.live.debounce.500ms="tableSearch"
                                placeholder="Cari nama atau kode wilayah…"
                                aria-label="Cari nama atau kode wilayah"
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

                        @if ($this->hasActiveWilayahFilter())
                            <button
                                type="button"
                                wire:click="resetWilayahFilter"
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

                            @if (filled($levelFilter))
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                    <span>Level: <strong>{{ \App\Models\Wilayah::labelLevel($levelFilter) }}</strong></span>
                                    <button type="button" wire:click="hapusFilterLevel" class="text-teal-400 hover:text-teal-700">
                                        <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                                    </button>
                                </span>
                            @endif

                            @if (filled($statusFilter))
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200">
                                    <span>Status: <strong>{{ $statusFilter === 'aktif' ? 'Aktif' : ($statusFilter === 'nonaktif' ? 'Nonaktif' : 'Down (Induk nonaktif)') }}</strong></span>
                                    <button type="button" wire:click="hapusFilterStatus" class="text-emerald-400 hover:text-emerald-700">
                                        <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                                    </button>
                                </span>
                            @endif

                            @if ($filterProvinsi && $this->namaProvinsiTerpilih)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                    <span>Provinsi: <strong>{{ $this->namaProvinsiTerpilih }}</strong></span>
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

                            @if ($this->namaDesaTerpilih)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                    <span>Desa: <strong>{{ $this->namaDesaTerpilih }}</strong></span>
                                    <button type="button" wire:click="hapusFilterDesa" class="text-teal-400 hover:text-teal-700">
                                        <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                                    </button>
                                </span>
                            @endif

                            @if ($this->namaRwTerpilih)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                    <span>RW: <strong>{{ $this->namaRwTerpilih }}</strong></span>
                                    <button type="button" wire:click="hapusFilterRw" class="text-teal-400 hover:text-teal-700">
                                        <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                                    </button>
                                </span>
                            @endif

                            @if ($this->namaRtTerpilih)
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                    <span>RT: <strong>{{ $this->namaRtTerpilih }}</strong></span>
                                    <button type="button" wire:click="hapusFilterRt" class="text-teal-400 hover:text-teal-700">
                                        <x-filament::icon icon="heroicon-m-x-mark" class="h-3.5 w-3.5" />
                                    </button>
                                </span>
                            @endif

                            <button
                                type="button"
                                wire:click="resetWilayahFilter"
                                class="text-xs font-semibold text-gray-500 hover:text-rose-600 hover:underline ml-1"
                            >
                                Hapus semua filter
                            </button>
                        </div>
                    @endif

                    <!-- POP-UP MODAL FILTER WILAYAH (TELEPORTED TO BODY) -->
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
                                                <h3 class="text-base font-bold text-gray-950" id="modal-filter-title">Filter Wilayah</h3>
                                                <p class="text-xs text-gray-500">Tentukan kriteria status, level, dan urutan hierarki wilayah.</p>
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
                                                <span class="text-xs font-bold text-gray-800">Status Operasional</span>
                                                <select wire:model.live="statusFilter" class="rd-select text-xs">
                                                    <option value="">Semua Status</option>
                                                    <option value="aktif">Aktif</option>
                                                    <option value="nonaktif">Nonaktif</option>
                                                    <option value="down">Down (Induk Nonaktif)</option>
                                                </select>
                                            </label>

                                            <label class="block space-y-1">
                                                <span class="text-xs font-bold text-gray-800">Level Wilayah</span>
                                                <select wire:model.live="levelFilter" class="rd-select text-xs">
                                                    <option value="">Semua Level</option>
                                                    @foreach (\App\Models\Wilayah::levelOptions() as $value => $label)
                                                        <option value="{{ $value }}">{{ $label }}</option>
                                                    @endforeach
                                                </select>
                                            </label>
                                        </div>

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
                                                @endif
                                            </div>
                                            <p class="text-[11px] text-gray-500 mb-3">
                                                Ketik nama/nomor wilayah yang ingin disaring. Tidak wajib berurutan (misal: cukup ketik RW untuk melihat seluruh RW 01 di semua daerah).
                                            </p>

                                            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                                                <label class="block space-y-1">
                                                    <span class="text-[11px] font-semibold text-gray-700">Provinsi</span>
                                                    <input
                                                        type="text"
                                                        list="list-opsi-provinsi"
                                                        wire:model.live.debounce.500ms="filterProvinsi"
                                                        placeholder="Ketik mis. Jawa Barat"
                                                        class="rd-search-input text-xs"
                                                    />
                                                    <datalist id="list-opsi-provinsi">
                                                        @foreach ($this->daftarProvinsi as $nama)
                                                            <option value="{{ $nama }}"></option>
                                                        @endforeach
                                                    </datalist>
                                                </label>

                                                <label class="block space-y-1">
                                                    <span class="text-[11px] font-semibold text-gray-700">Kabupaten / Kota</span>
                                                    <input
                                                        type="text"
                                                        list="list-opsi-kabkota"
                                                        wire:model.live.debounce.500ms="filterKabkota"
                                                        placeholder="Ketik mis. Garut atau Bandung"
                                                        class="rd-search-input text-xs"
                                                    />
                                                    <datalist id="list-opsi-kabkota">
                                                        @foreach ($this->daftarKabkota as $nama)
                                                            <option value="{{ $nama }}"></option>
                                                        @endforeach
                                                    </datalist>
                                                </label>

                                                <label class="block space-y-1">
                                                    <span class="text-[11px] font-semibold text-gray-700">Kecamatan</span>
                                                    <input
                                                        type="text"
                                                        list="list-opsi-kecamatan"
                                                        wire:model.live.debounce.500ms="filterKecamatan"
                                                        placeholder="Ketik mis. Bojongsoang"
                                                        class="rd-search-input text-xs"
                                                    />
                                                    <datalist id="list-opsi-kecamatan">
                                                        @foreach ($this->daftarKecamatan as $nama)
                                                            <option value="{{ $nama }}"></option>
                                                        @endforeach
                                                    </datalist>
                                                </label>

                                                <label class="block space-y-1">
                                                    <span class="text-[11px] font-semibold text-gray-700">Desa / Kelurahan / Dusun</span>
                                                    <input
                                                        type="text"
                                                        list="list-opsi-desa"
                                                        wire:model.live.debounce.500ms="filterDesa"
                                                        placeholder="Ketik mis. Lengkong atau Dusun Krajan"
                                                        class="rd-search-input text-xs"
                                                    />
                                                    <datalist id="list-opsi-desa">
                                                        @foreach ($this->daftarDesa as $nama)
                                                            <option value="{{ $nama }}"></option>
                                                        @endforeach
                                                    </datalist>
                                                </label>

                                                <label class="block space-y-1">
                                                    <span class="text-[11px] font-semibold text-gray-700">RW</span>
                                                    <input
                                                        type="text"
                                                        list="list-opsi-rw"
                                                        wire:model.live.debounce.500ms="filterRw"
                                                        placeholder="Ketik mis. RW 01 atau 01"
                                                        class="rd-search-input text-xs"
                                                    />
                                                    <datalist id="list-opsi-rw">
                                                        @foreach ($this->daftarRw as $nama)
                                                            <option value="{{ $nama }}"></option>
                                                        @endforeach
                                                    </datalist>
                                                </label>

                                                <label class="block space-y-1">
                                                    <span class="text-[11px] font-semibold text-gray-700">RT</span>
                                                    <input
                                                        type="text"
                                                        list="list-opsi-rt"
                                                        wire:model.live.debounce.500ms="filterRt"
                                                        placeholder="Ketik mis. RT 01 atau 01"
                                                        class="rd-search-input text-xs"
                                                    />
                                                    <datalist id="list-opsi-rt">
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
                                            wire:click="resetWilayahFilter"
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

                <p class="mt-2 text-xs text-gray-500">
                    Pencarian tabel mencakup seluruh wilayah yang dapat Anda akses, termasuk turunan.
                </p>
            </section>

            {{ $this->content }}
        @endif
    </div>
</x-filament-panels::page>
