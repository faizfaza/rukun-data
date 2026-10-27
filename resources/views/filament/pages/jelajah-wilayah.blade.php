<x-filament-panels::page>
    @php
        $user = auth()->user();
        $nodeAktif = $this->nodeAktif();
        $rt = $this->nodeTerpilihRt();
        $leluhurAwal = $this->leluhurTitikAwal();
    @endphp

    <div class="flex flex-col gap-5">
        {{-- Banner Lokasi Wilayah Lengkap & Status Akun --}}
        <div class="rounded-xl border border-primary-200 bg-primary-50/60 p-4 shadow-sm dark:border-primary-900/50 dark:bg-primary-950/20">
            <div class="flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-start gap-3">
                    <div class="rounded-lg bg-primary-100 p-2 text-primary-700 dark:bg-primary-900 dark:text-primary-300">
                        <x-filament::icon icon="heroicon-o-map-pin" class="h-5 w-5" />
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-primary-800 dark:text-primary-300">
                                @if ($user?->isAdminRt())
                                    Wilayah Penugasan RT Anda:
                                @elseif ($user?->isAdminRw())
                                    Wilayah Penugasan RW Anda:
                                @else
                                    Lokasi Wilayah Aktif:
                                @endif
                            </span>
                            <span class="rounded bg-primary-200/80 px-2 py-0.5 text-[11px] font-bold text-primary-950 dark:bg-primary-900 dark:text-primary-100">
                                {{ str_starts_with(strtolower($nodeAktif->nama), strtolower(\App\Models\Wilayah::labelLevel($nodeAktif->level))) ? $nodeAktif->nama : \App\Models\Wilayah::labelLevel($nodeAktif->level).' '.$nodeAktif->nama }}
                            </span>
                        </div>
                        <div class="mt-1 text-sm font-semibold text-gray-900 dark:text-white">
                            📍 {{ $this->lokasiLengkap($nodeAktif) }}
                        </div>

                        {{-- Jejak Hirarki Lengkap (Rapi dalam pill badges, tanpa simbol panah '>') --}}
                        @if ($user?->isAdminRt())
                            <div class="mt-2.5 flex flex-wrap items-center gap-1.5 text-xs">
                                @foreach ($leluhurAwal as $leluhur)
                                    <span class="inline-flex items-center gap-1 rounded-md border border-primary-200/80 bg-white/90 px-2 py-0.5 text-[11px] text-gray-700 shadow-2xs dark:border-white/10 dark:bg-gray-800 dark:text-gray-300">
                                        <span class="text-[10px] text-gray-400 font-semibold">{{ \App\Models\Wilayah::labelLevel($leluhur->level) }}:</span>
                                        <span class="font-bold text-gray-800 dark:text-gray-100">{{ $leluhur->nama }}</span>
                                    </span>
                                @endforeach
                                <span class="inline-flex items-center gap-1 rounded-md border border-primary-300 bg-primary-100 px-2 py-0.5 text-[11px] font-bold text-primary-950 dark:bg-primary-900 dark:text-primary-100">
                                    <span class="text-[10px] text-primary-700 dark:text-primary-300">RT:</span>
                                    <span>{{ $nodeAktif->nama }}</span>
                                </span>
                            </div>
                        @endif
                    </div>
                </div>

                @if ($user?->isSuperadmin() || $user?->isAdmin())
                    <div class="text-xs text-gray-500 dark:text-gray-400">
                        Cakupan: <span class="font-medium text-gray-700 dark:text-gray-200">Seluruh Wilayah (Nasional)</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Navigasi Wilayah Interaktif untuk Superadmin, Admin, dan Admin RW --}}
        @if (! $user?->isAdminRt())
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex items-center justify-between">
                        <div class="flex items-center gap-2">
                            <x-filament::icon icon="heroicon-o-map" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                            <span class="text-sm font-bold text-gray-900 dark:text-white">Navigasi Wilayah</span>
                        </div>
                        @php
                            $langkah = $this->langkahSaatIni();
                        @endphp
                        @if ($langkah !== null)
                            <span class="text-xs text-primary-700 dark:text-primary-300 font-medium">
                                Langkah aktif: <strong>Pilih {{ $langkah['label'] }}</strong>
                            </span>
                        @endif
                    </div>
                </x-slot>

                <div class="flex flex-wrap items-center gap-2 text-xs">
                    {{-- 1. Leluhur tetap (untuk akun RW, mulai dari negara s/d desa) --}}
                    @foreach ($leluhurAwal as $leluhur)
                        <span
                            wire:key="leluhur-{{ $leluhur->id }}"
                            class="inline-flex items-center gap-1 rounded-md border border-gray-200 bg-gray-100 px-2.5 py-1 font-medium text-gray-600 dark:border-white/10 dark:bg-gray-800 dark:text-gray-400"
                        >
                            <x-filament::icon icon="heroicon-m-lock-closed" class="h-3 w-3 text-gray-400" />
                            <span>{{ $leluhur->nama }}</span>
                        </span>
                        <span class="text-gray-300 dark:text-gray-600 font-bold">/</span>
                    @endforeach

                    {{-- 2. Langkah interaktif pilihan --}}
                    @foreach ($this->rantai() as $i => $node)
                        @if (! $loop->last)
                            <button
                                type="button"
                                wire:key="chip-{{ $i }}-{{ $node->id }}"
                                wire:click="kembaliKe({{ $i }})"
                                title="Kembali ke {{ $node->nama }}"
                                class="inline-flex items-center gap-1 rounded-md bg-gray-100 px-2.5 py-1 font-medium text-gray-700 hover:bg-primary-50 hover:text-primary-700 dark:bg-gray-800 dark:text-gray-200 dark:hover:bg-primary-950/40 transition"
                            >
                                <span>{{ $node->nama }}</span>
                            </button>
                            <span class="text-gray-300 dark:text-gray-600 font-bold">/</span>
                        @else
                            <span
                                wire:key="chip-{{ $i }}-{{ $node->id }}"
                                class="inline-flex items-center gap-1 rounded-md bg-primary-100/80 px-2.5 py-1 font-bold text-primary-900 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-900/60 dark:text-primary-100"
                            >
                                <span>{{ $node->nama }}</span>
                            </span>
                        @endif
                    @endforeach
                </div>

            @if ($langkah !== null)
                @php
                    $semuaOpsi = $this->opsiSaatIni();
                    $levels = $semuaOpsi->pluck('level')->unique();
                    $adaMultiLevel = $levels->count() > 1;
                    $dusunOpsi = $semuaOpsi->filter(fn ($o) => $o->level === 'dusun');
                    $rwOpsi = $semuaOpsi->filter(fn ($o) => $o->level === 'rw');
                @endphp

                <div class="mt-5 flex flex-col gap-3">
                    <div class="flex items-center justify-between">
                        <div class="text-sm font-semibold text-gray-900 dark:text-white">
                            Pilih {{ $langkah['label'] }}
                        </div>
                        @if ($adaMultiLevel)
                            <span class="rounded bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                Memiliki Dusun & RW Langsung
                            </span>
                        @endif
                    </div>

                    @if ($adaMultiLevel)
                        <div class="flex items-center gap-2 rounded-lg border border-amber-200 bg-amber-50/80 p-2.5 text-xs text-amber-800 dark:border-amber-900/40 dark:bg-amber-950/30 dark:text-amber-300">
                            <x-filament::icon icon="heroicon-m-information-circle" class="h-4 w-4 shrink-0 text-amber-600 dark:text-amber-400" />
                            <span>
                                Desa ini membawahi <strong>Dusun</strong> (yang membawahi RW di dalamnya) dan juga <strong>RW Langsung</strong> (tanpa dusun). Silakan pilih kategori yang dituju:
                            </span>
                        </div>
                    @endif

                    {{-- Kotak cari untuk daftar langkah aktif. --}}
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="cari"
                        placeholder="Cari {{ $langkah['label'] }}…"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200 dark:border-white/10 dark:bg-gray-800 dark:text-white"
                    />

                    {{-- Daftar opsi langkah aktif. --}}
                    <div
                        wire:loading.class="opacity-60"
                        wire:target="pilih,cari,kembaliKe"
                        class="space-y-4"
                    >
                        @if ($adaMultiLevel)
                            {{-- Grup Dusun --}}
                            @if ($dusunOpsi->isNotEmpty())
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between px-1">
                                        <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-primary-800 dark:text-primary-300">
                                            <x-filament::icon icon="heroicon-m-folder" class="h-4 w-4 text-primary-600 dark:text-primary-400" />
                                            Wilayah Dusun (Membawahi RW)
                                        </span>
                                        <span class="text-xs text-gray-400">{{ $dusunOpsi->count() }} dusun</span>
                                    </div>
                                    <div class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-gray-800">
                                        @foreach ($dusunOpsi as $opsi)
                                            <button
                                                type="button"
                                                wire:key="opsi-{{ $opsi->id }}"
                                                wire:click="pilih({{ $langkah['indeks'] }}, {{ $opsi->id }})"
                                                class="group flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm text-gray-900 hover:bg-primary-50/50 dark:text-white dark:hover:bg-white/5 transition"
                                            >
                                                <div class="flex flex-col">
                                                    <span class="font-semibold text-gray-900 group-hover:text-primary-600 dark:text-white transition">{{ $opsi->nama }}</span>
                                                    <span class="text-xs text-gray-500">Wilayah dusun — membawahi RW di dalamnya</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="rounded bg-primary-100 px-2 py-0.5 text-xs font-semibold text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                                        Dusun
                                                    </span>
                                                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400 group-hover:text-primary-600 group-hover:translate-x-0.5 transition" />
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Grup RW Langsung --}}
                            @if ($rwOpsi->isNotEmpty())
                                <div class="space-y-1.5">
                                    <div class="flex items-center justify-between px-1">
                                        <span class="flex items-center gap-1.5 text-xs font-bold uppercase tracking-wider text-amber-800 dark:text-amber-300">
                                            <x-filament::icon icon="heroicon-m-home-modern" class="h-4 w-4 text-amber-600 dark:text-amber-400" />
                                            RW Langsung (Non-Dusun)
                                        </span>
                                        <span class="text-xs text-gray-400">{{ $rwOpsi->count() }} RW</span>
                                    </div>
                                    <div class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-gray-800">
                                        @foreach ($rwOpsi as $opsi)
                                            <button
                                                type="button"
                                                wire:key="opsi-{{ $opsi->id }}"
                                                wire:click="pilih({{ $langkah['indeks'] }}, {{ $opsi->id }})"
                                                class="group flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm text-gray-900 hover:bg-amber-50/50 dark:text-white dark:hover:bg-white/5 transition"
                                            >
                                                <div class="flex flex-col">
                                                    <span class="font-semibold text-gray-900 group-hover:text-amber-600 dark:text-white transition">{{ $opsi->nama }}</span>
                                                    <span class="text-xs text-gray-500">RW langsung di bawah desa (tanpa dusun)</span>
                                                </div>
                                                <div class="flex items-center gap-2">
                                                    <span class="rounded bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                                                        RW Langsung
                                                    </span>
                                                    <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400 group-hover:text-amber-600 group-hover:translate-x-0.5 transition" />
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            @endif

                            {{-- Grup Level Lainnya (jika ada) --}}
                            @php
                                $sisaOpsi = $semuaOpsi->reject(fn ($o) => in_array($o->level, ['dusun', 'rw'], true));
                            @endphp
                            @if ($sisaOpsi->isNotEmpty())
                                <div class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-gray-800">
                                    @foreach ($sisaOpsi as $opsi)
                                        <button
                                            type="button"
                                            wire:key="opsi-{{ $opsi->id }}"
                                            wire:click="pilih({{ $langkah['indeks'] }}, {{ $opsi->id }})"
                                            class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                                        >
                                            <span class="font-medium">{{ $opsi->nama }}</span>
                                            <span class="text-xs text-gray-500">{{ $this->labelUntuk($opsi) }}</span>
                                        </button>
                                    @endforeach
                                </div>
                            @endif

                            @if ($semuaOpsi->isEmpty())
                                <div class="rounded-lg border border-gray-200 bg-white px-3 py-4 text-sm text-gray-500 dark:border-white/10 dark:bg-gray-800">
                                    Tidak ada hasil untuk "{{ $cari }}".
                                </div>
                            @endif
                        @else
                            <div class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 bg-white dark:divide-white/5 dark:border-white/10 dark:bg-gray-800">
                                @forelse ($semuaOpsi as $opsi)
                                    <button
                                        type="button"
                                        wire:key="opsi-{{ $opsi->id }}"
                                        wire:click="pilih({{ $langkah['indeks'] }}, {{ $opsi->id }})"
                                        class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm text-gray-900 hover:bg-gray-50 dark:text-white dark:hover:bg-white/5"
                                    >
                                        <span class="font-medium">{{ $opsi->nama }}</span>
                                        <span class="text-xs text-gray-500">{{ $this->labelUntuk($opsi) }}</span>
                                    </button>
                                @empty
                                    <div class="px-3 py-4 text-sm text-gray-500">
                                        Tidak ada hasil untuk "{{ $cari }}".
                                    </div>
                                @endforelse
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </x-filament::section>
        @endif

        @if ($rt !== null)
            {{-- Data keluarga baru tampil setelah rantai pilihan sampai RT. --}}
            <x-filament::section>
                <x-slot name="heading">
                    <div class="flex flex-col gap-1">
                        <div class="flex items-center gap-2">
                            <span class="text-base font-semibold text-gray-900 dark:text-white">
                                Keluarga di {{ $rt->nama }}
                            </span>
                            <span class="rounded bg-primary-50 px-2 py-0.5 text-xs font-semibold text-primary-700 ring-1 ring-inset ring-primary-600/20 dark:bg-primary-950/50 dark:text-primary-300">
                                Tingkat RT
                            </span>
                        </div>
                        <div class="text-xs font-normal text-gray-500 dark:text-gray-400">
                            📍 {{ $this->lokasiLengkap($rt) }}
                        </div>
                    </div>
                </x-slot>

                <x-slot name="headerEnd">
                    @if ($this->bolehTambahKeluarga())
                        <a
                            href="{{ \App\Filament\Resources\KeluargaResource::getUrl('create', ['id_wilayah' => $rt->id]) }}"
                            class="inline-flex items-center gap-1.5 rounded-lg bg-primary-600 px-3.5 py-1.5 text-xs font-semibold text-white shadow-sm hover:bg-primary-500 transition"
                        >
                            <x-filament::icon icon="heroicon-m-plus" class="h-4 w-4" />
                            <span>Tambah keluarga di {{ $rt->nama }}</span>
                        </a>
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
                    Wilayah ini belum punya turunan (belum ada RW/RT yang didaftarkan di bawahnya).
                </div>
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
