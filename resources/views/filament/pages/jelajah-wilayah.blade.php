<x-filament-panels::page>
    <div class="flex flex-col gap-6">
        {{-- Rantai pilihan: klik chip untuk kembali ke level itu. --}}
        <x-filament::section heading="Pilih wilayah">
            <div class="flex flex-wrap items-center gap-1.5">
                @foreach ($this->rantai() as $i => $node)
                    @if (! $loop->last)
                        <button
                            type="button"
                            wire:key="chip-{{ $i }}-{{ $node->id }}"
                            wire:click="kembaliKe({{ $i }})"
                            title="Kembali ke {{ $node->nama }}"
                            class="inline-flex items-center rounded-md bg-gray-100 px-2 py-1 text-sm text-gray-700 hover:bg-gray-200"
                        >
                            {{ $node->nama }}
                        </button>
                        <x-filament::icon icon="heroicon-m-chevron-right" class="h-4 w-4 text-gray-400" />
                    @else
                        <span
                            wire:key="chip-{{ $i }}-{{ $node->id }}"
                            class="inline-flex items-center rounded-md bg-primary-50 px-2 py-1 text-sm font-semibold text-primary-700"
                        >
                            {{ $node->nama }}
                        </span>
                    @endif
                @endforeach
            </div>

            @php($langkah = $this->langkahSaatIni())

            @if ($langkah !== null)
                <div class="mt-5 flex flex-col gap-3">
                    <div class="text-sm font-semibold text-gray-900">
                        Pilih {{ $langkah['label'] }}
                    </div>

                    {{-- Kotak cari untuk daftar langkah aktif. --}}
                    <input
                        type="search"
                        wire:model.live.debounce.300ms="cari"
                        placeholder="Cari {{ $langkah['label'] }}…"
                        class="w-full rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm text-gray-900 placeholder:text-gray-400 focus:border-primary-500 focus:outline-none focus:ring-2 focus:ring-primary-200"
                    />

                    {{-- Daftar opsi langkah aktif. --}}
                    <div
                        wire:loading.class="opacity-60"
                        wire:target="pilih,cari,kembaliKe"
                        class="divide-y divide-gray-100 overflow-hidden rounded-lg border border-gray-200 bg-white"
                    >
                        @forelse ($this->opsiSaatIni() as $opsi)
                            <button
                                type="button"
                                wire:key="opsi-{{ $opsi->id }}"
                                wire:click="pilih({{ $langkah['indeks'] }}, {{ $opsi->id }})"
                                class="flex w-full items-center justify-between gap-3 px-3 py-2.5 text-left text-sm text-gray-900 hover:bg-gray-50"
                            >
                                <span>{{ $opsi->nama }}</span>
                                <span class="text-xs text-gray-500">{{ $this->labelUntuk($opsi) }}</span>
                            </button>
                        @empty
                            <div class="px-3 py-4 text-sm text-gray-500">
                                Tidak ada hasil untuk "{{ $cari }}".
                            </div>
                        @endforelse
                    </div>
                </div>
            @endif
        </x-filament::section>

        @php($rt = $this->nodeTerpilihRt())

        @if ($rt !== null)
            {{-- Data keluarga baru tampil setelah rantai pilihan sampai RT. --}}
            <x-filament::section
                heading="Keluarga di {{ $rt->nama }}"
                description="{{ $this->ringkasanKk() }}"
            >
                {{ $this->table }}
            </x-filament::section>
        @elseif ($this->buntu())
            <x-filament::section heading="{{ $this->nodeAktif()->nama }}">
                Wilayah ini belum punya turunan (belum ada RW/RT).
            </x-filament::section>
        @endif
    </div>
</x-filament-panels::page>
