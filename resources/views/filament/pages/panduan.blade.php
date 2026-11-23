<x-filament-panels::page>
    <div class="w-full max-w-2xl">
        <x-filament::section>
            <div class="space-y-6" wire:key="panduan-{{ $langkah }}" x-data="{ contoh: false, hasil: false }"
                x-init="$nextTick(() => $refs.judul.focus())" data-panduan-sorot="{{ $sorot }}">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center justify-between gap-2 text-sm font-medium text-gray-600">
                        <span>{{ $role }}</span>
                        <span>Langkah {{ $langkah + 1 }} dari {{ $jumlahLangkah }}</span>
                    </div>
                    <progress class="panduan-progress" value="{{ $langkah + 1 }}" max="{{ $jumlahLangkah }}"
                        aria-label="Langkah tutorial"></progress>
                </div>

                <div class="space-y-2">
                    <h2 x-ref="judul" tabindex="-1" class="w-fit text-2xl font-bold tracking-tight text-gray-950">{{ $judul }}</h2>
                    <p class="text-sm leading-6 text-gray-700">{{ $isi }}</p>
                    @if ($langkah === 0 && ! $sudahSelesai)
                        <p class="text-sm text-gray-600">Tuntaskan panduan ini untuk mulai memakai panel.</p>
                    @endif
                </div>

                @if ($langkah === 1 || $langkah === 2)
                    <div class="space-y-4 rounded-lg border border-teal-200 bg-teal-50 p-4 text-sm">
                        <p class="font-semibold text-gray-950">Latihan dengan data fiktif</p>
                        @if ($langkah === 1)
                            <div x-show="!contoh">
                                <x-filament::button type="button" color="gray" x-on:click="contoh = true; $nextTick(() => $refs.coba.focus())">
                                    Isi contoh No KK
                                </x-filament::button>
                            </div>
                            <div class="flex flex-wrap items-end gap-3" x-show="contoh" x-cloak>
                                <label class="block space-y-1">
                                    <span>No KK atau NIK persis</span>
                                    <x-filament::input.wrapper>
                                        <x-filament::input type="text" value="0000000000000001" readonly />
                                    </x-filament::input.wrapper>
                                </label>
                                <x-filament::button type="button" x-ref="coba" x-on:click="hasil = true">
                                    Cari No KK / NIK
                                </x-filament::button>
                            </div>
                        @else
                            <div x-show="!contoh">
                                <x-filament::button type="button" color="gray" x-on:click="contoh = true; $nextTick(() => $refs.coba.focus())">
                                    Tambah keluarga
                                </x-filament::button>
                            </div>
                            <form class="space-y-3" x-show="contoh" x-cloak x-on:submit.prevent="hasil = true">
                                <div class="grid gap-3 sm:grid-cols-2">
                                    <label class="block space-y-1">
                                        <span>No KK</span>
                                        <x-filament::input.wrapper>
                                            <x-filament::input type="text" value="0000000000000001" readonly />
                                        </x-filament::input.wrapper>
                                    </label>
                                    <label class="block space-y-1">
                                        <span>Hubungan Ari Contoh</span>
                                        <x-filament::input.wrapper>
                                            <x-filament::input.select x-ref="coba" required>
                                                <option value="">Pilih hubungan</option>
                                                <option value="kepala">Kepala Keluarga</option>
                                            </x-filament::input.select>
                                        </x-filament::input.wrapper>
                                    </label>
                                </div>
                                <x-filament::button type="submit">
                                    Coba simpan keluarga
                                </x-filament::button>
                            </form>
                        @endif
                        <p class="font-medium text-teal-900" role="status"
                            x-text="hasil ? @js($langkah === 1 ? 'Keluarga Ari Contoh ditemukan di RT 01.' : 'Latihan selesai. Data contoh tidak disimpan.') : ''"></p>
                    </div>
                @endif

                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-gray-200 pt-4">
                    <div class="flex flex-wrap items-center gap-3">
                        @if ($langkah > 0)
                            <x-filament::button type="button" color="gray" wire:click="kembali" wire:loading.attr="disabled">
                                Kembali
                            </x-filament::button>
                        @endif
                        @if ($sudahSelesai)
                            <x-filament::link :href="\Filament\Pages\Dashboard::getUrl()">Keluar dari panduan</x-filament::link>
                        @endif
                    </div>
                    <x-filament::button type="button" wire:click="lanjut" wire:loading.attr="disabled">
                        {{ $langkah + 1 === $jumlahLangkah ? 'Selesai' : 'Lanjut' }}
                    </x-filament::button>
                </div>
            </div>
        </x-filament::section>
    </div>
</x-filament-panels::page>
