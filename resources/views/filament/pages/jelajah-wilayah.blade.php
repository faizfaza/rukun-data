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
                <span class="text-[0.68rem] font-bold uppercase tracking-[0.08em] text-gray-400">
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
                                    <div class="mb-2 flex items-center justify-between px-1 text-[0.68rem] font-bold uppercase tracking-[0.08em] text-gray-500">
                                        <span>{{ $g['judul'] }}</span>
                                        <span>{{ $g['opsi']->count() }}</span>
                                    </div>
                                @endif

                                <div class="overflow-hidden rounded-xl border border-gray-200 bg-white">
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
                                            class="group flex w-full items-center justify-between gap-3 border-b border-gray-100 px-3.5 py-3 text-left transition last:border-b-0 hover:bg-gray-50"
                                        >
                                            <span class="flex min-w-0 items-center gap-2.5">
                                                <span class="rd-list-icon h-7 w-7">
                                                    <x-filament::icon :icon="$ikon" class="h-3.5 w-3.5" />
                                                </span>
                                                <span class="min-w-0">
                                                    <span class="block truncate text-sm font-semibold text-gray-900">{{ $opsi->nama }}</span>
                                                    <span class="block text-[0.68rem] text-gray-400">{{ $this->labelUntuk($opsi) }}</span>
                                                </span>
                                            </span>

                                            <span class="flex shrink-0 items-center gap-2">
                                                @if ($h !== null)
                                                    <span class="rd-chip">{{ $h['label'] }}</span>
                                                @endif
                                                <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-300 transition group-hover:translate-x-0.5 group-hover:text-gray-500" />
                                            </span>
                                        </button>
                                    @empty
                                        <div class="px-4 py-6 text-center text-sm text-gray-500">
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
    </div>
</x-filament-panels::page>
