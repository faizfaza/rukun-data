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
                            class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg text-xs font-semibold bg-indigo-50 text-indigo-700 border border-indigo-200 hover:bg-indigo-100 transition"
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
                                wire:model.live.debounce.300ms="cariKk"
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
                                                class="rd-quiet-link text-xs font-semibold text-indigo-600 hover:text-indigo-800"
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
                                                    class="mt-1 text-xs font-semibold text-indigo-600 hover:underline"
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

                <p class="mt-2 text-xs text-gray-500">
                    Pencarian tabel mencakup seluruh wilayah yang dapat Anda akses, termasuk turunan.
                </p>
            </section>

            {{ $this->content }}
        @endif
    </div>
</x-filament-panels::page>
