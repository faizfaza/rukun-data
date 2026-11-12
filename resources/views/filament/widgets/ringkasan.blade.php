<x-filament-widgets::widget>
    <div class="fi-wi-stats-overview">
        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3.5">
            @foreach ($stats as $stat)
                <button
                    type="button"
                    wire:click="bukaDetail('ringkasan', '{{ $stat['key'] }}')"
                    class="rd-stat-card group text-left w-full block p-4 rounded-2xl bg-white border border-gray-100 shadow-sm hover:shadow-md hover:border-teal-300 transition-all duration-200 cursor-pointer"
                    wire:key="stat-{{ $stat['key'] }}"
                >
                    <div class="flex items-center justify-between gap-2 mb-2">
                        <span class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full bg-gray-100 text-gray-600 group-hover:bg-teal-50 group-hover:text-teal-700 transition">
                            {{ $stat['category'] ?? 'Ringkasan' }}
                        </span>
                        <div class="rd-stat-icon text-gray-400 group-hover:text-teal-600 transition">
                            {{ \Filament\Support\generate_icon_html($stat['icon']) }}
                        </div>
                    </div>

                    <div>
                        <span
                            class="rd-stat-value text-2xl font-bold text-gray-900 group-hover:text-teal-700 transition"
                            x-data="{
                                value: 0,
                                target: {{ (int) $stat['value'] }},
                                init() {
                                    if (window.matchMedia('(prefers-reduced-motion: reduce)').matches) {
                                        this.value = this.target
                                        return
                                    }

                                    const start = performance.now()
                                    const duration = 720

                                    const tick = (now) => {
                                        const progress = Math.min(1, (now - start) / duration)
                                        const eased = 1 - Math.pow(1 - progress, 3)
                                        this.value = Math.round(this.target * eased)

                                        if (progress < 1) {
                                            requestAnimationFrame(tick)
                                        }
                                    }

                                    requestAnimationFrame(tick)
                                },
                            }"
                            x-text="new Intl.NumberFormat('id-ID').format(value)"
                        >{{ number_format($stat['value'], 0, ',', '.') }}</span>

                        <div class="rd-stat-label text-xs font-semibold text-gray-700 mt-1">{{ $stat['label'] }}</div>
                        <div class="rd-stat-desc text-[11px] text-gray-500 mt-0.5 line-clamp-1">{{ $stat['description'] }}</div>
                        <div class="mt-2.5 flex items-center gap-1 text-[11px] font-medium text-teal-600 opacity-0 group-hover:opacity-100 transition-opacity">
                            <span>Lihat rincian</span>
                            <span>&rarr;</span>
                        </div>
                    </div>
                </button>
            @endforeach
        </div>

        @if ($detail !== null)
            <div
                class="fixed inset-0 z-[9999] overflow-y-auto"
                role="dialog"
                aria-modal="true"
                x-data="{ openFilterDetail: false }"
                x-on:keydown.escape.window="if (openFilterDetail) { openFilterDetail = false } else { $wire.tutupDetail() }"
                wire:key="modal-detail-{{ $detail['tipe'] }}"
            >
                {{-- Backdrop Utama --}}
                <div
                    class="fixed inset-0 bg-gray-900/60 backdrop-blur-sm transition-opacity"
                    wire:click="tutupDetail"
                ></div>

                {{-- Dialog Modal Rincian --}}
                <div class="fixed inset-0 z-[10000] flex min-h-screen items-center justify-center p-4 sm:p-6 pointer-events-none">
                    <div
                        class="relative w-full max-w-5xl max-h-[88vh] flex flex-col overflow-hidden rounded-2xl bg-white text-left shadow-2xl border border-gray-200 pointer-events-auto"
                        @click.stop
                    >
                        {{-- Header Modal --}}
                        <div class="flex items-center justify-between gap-4 border-b border-gray-200 px-6 py-4 bg-white shrink-0">
                            <div>
                                <h3 class="text-lg font-bold text-gray-950">
                                    {{ $detail['judul'] }}
                                </h3>
                                <p class="text-sm text-gray-500 mt-0.5">
                                    Total {{ $detail['total_keluarga'] }} keluarga &middot; {{ $detail['total_anggota'] }} warga
                                </p>
                            </div>

                            <button
                                type="button"
                                wire:click="tutupDetail"
                                class="rounded-lg p-2 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition cursor-pointer"
                                title="Tutup"
                            >
                                <x-filament::icon icon="heroicon-m-x-mark" class="h-6 w-6" />
                            </button>
                        </div>

                        {{-- Baris Pencarian & Tombol Filter Pop-up (Sama seperti Filter Kelola Warga) --}}
                        <div class="px-6 py-3.5 bg-gray-50 border-b border-gray-200 shrink-0">
                            <div class="flex flex-wrap items-center gap-2.5">
                                <label class="relative block min-w-[240px] flex-1">
                                    <span class="sr-only">Cari warga atau keluarga</span>
                                    <x-filament::icon icon="heroicon-m-magnifying-glass" class="pointer-events-none absolute left-3 top-1/2 h-5 w-5 -translate-y-1/2 text-gray-400" />
                                    <input
                                        type="search"
                                        wire:model.live.debounce.300ms="cariDetail"
                                        placeholder="Cari nama warga, kepala keluarga, atau RT/RW..."
                                        class="rd-search-input text-sm"
                                        autocomplete="off"
                                    />
                                </label>

                                <button
                                    type="button"
                                    @click="openFilterDetail = true"
                                    class="inline-flex items-center gap-2 px-3.5 py-2 rounded-xl border {{ $this->jumlahFilterDetailAktif > 0 ? 'border-teal-300 bg-teal-50 text-teal-700' : 'border-gray-200 bg-white text-gray-700 hover:bg-gray-50' }} text-xs font-semibold shadow-sm transition cursor-pointer"
                                >
                                    <x-filament::icon icon="heroicon-m-funnel" class="h-4 w-4 {{ $this->jumlahFilterDetailAktif > 0 ? 'text-teal-600' : 'text-gray-500' }}" />
                                    <span>Filter</span>
                                    @if ($this->jumlahFilterDetailAktif > 0)
                                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-teal-600 text-[10px] font-bold text-white shadow-sm">
                                            {{ $this->jumlahFilterDetailAktif }}
                                        </span>
                                    @endif
                                </button>

                                @if ($this->jumlahFilterDetailAktif > 0)
                                    <button
                                        type="button"
                                        wire:click="resetFilterDetail"
                                        class="rd-filter-reset cursor-pointer"
                                        title="Atur ulang semua filter"
                                    >
                                        <x-filament::icon icon="heroicon-m-arrow-path" class="h-3.5 w-3.5" />
                                        <span>Atur ulang</span>
                                    </button>
                                @endif
                            </div>

                            {{-- Indikator Filter Aktif --}}
                            @if ($this->jumlahFilterDetailAktif > 0)
                                <div class="mt-2.5 flex flex-wrap items-center gap-1.5">
                                    <span class="text-[11px] font-bold uppercase tracking-wider text-gray-400 mr-1">Filter Aktif:</span>

                                    @if (filled($filterProvinsi))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>Provinsi: <strong>{{ $filterProvinsi }}</strong></span>
                                            <button type="button" wire:click="$set('filterProvinsi', null)" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if (filled($filterKabkota))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>Kab/Kota: <strong>{{ $filterKabkota }}</strong></span>
                                            <button type="button" wire:click="$set('filterKabkota', null)" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if (filled($filterKecamatan))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>Kecamatan: <strong>{{ $filterKecamatan }}</strong></span>
                                            <button type="button" wire:click="$set('filterKecamatan', null)" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if (filled($filterDesa))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>Desa/Dusun: <strong>{{ $filterDesa }}</strong></span>
                                            <button type="button" wire:click="$set('filterDesa', null)" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if (filled($filterRw))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>RW: <strong>{{ $filterRw }}</strong></span>
                                            <button type="button" wire:click="$set('filterRw', null)" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if (filled($filterRt))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>RT: <strong>{{ $filterRt }}</strong></span>
                                            <button type="button" wire:click="$set('filterRt', null)" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if ($filterJk !== '')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>JK: <strong>{{ $filterJk === 'L' ? 'Laki-laki' : 'Perempuan' }}</strong></span>
                                            <button type="button" wire:click="$set('filterJk', '')" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if ($filterUmurKategori !== '')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>Kelompok Umur: <strong>{{ \App\Filament\Widgets\RingkasanWidget::umurBuckets()[$filterUmurKategori]['label'] ?? $filterUmurKategori }}</strong></span>
                                            <button type="button" wire:click="$set('filterUmurKategori', '')" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if (filled($filterUmurMin) || filled($filterUmurMax))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>Rentang Umur: <strong>{{ $filterUmurMin ?: '0' }}–{{ $filterUmurMax ?: '∞' }} thn</strong></span>
                                            <button type="button" wire:click="$set('filterUmurMin', null); $set('filterUmurMax', null)" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if ($filterPendidikan !== '' && isset($opsiPendidikan[(int) $filterPendidikan]))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>Pendidikan: <strong>{{ $opsiPendidikan[(int) $filterPendidikan] }}</strong></span>
                                            <button type="button" wire:click="$set('filterPendidikan', '')" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if ($filterPekerjaan !== '' && isset($opsiPekerjaan[(int) $filterPekerjaan]))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>Pekerjaan: <strong>{{ $opsiPekerjaan[(int) $filterPekerjaan] }}</strong></span>
                                            <button type="button" wire:click="$set('filterPekerjaan', '')" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if ($filterHubungan !== '' && isset($opsiHubungan[(int) $filterHubungan]))
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>Hubungan: <strong>{{ $opsiHubungan[(int) $filterHubungan] }}</strong></span>
                                            <button type="button" wire:click="$set('filterHubungan', '')" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                    @if ($filterStatusWarga !== '')
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-teal-50 text-teal-700 border border-teal-200">
                                            <span>Status: <strong>{{ ucfirst($filterStatusWarga) }}</strong></span>
                                            <button type="button" wire:click="$set('filterStatusWarga', '')" class="text-teal-400 hover:text-teal-700 cursor-pointer">&times;</button>
                                        </span>
                                    @endif
                                </div>
                            @endif
                        </div>

                        {{-- Tabel Data dengan Ukuran Font Lebih Besar & Ringkas --}}
                        <div class="overflow-y-auto flex-1">
                            @if (empty($detail['items']))
                                <div class="px-6 py-12 text-center text-base text-gray-500">
                                    Tidak ada data yang cocok dengan filter atau pencarian.
                                </div>
                            @elseif ($detail['mode'] === 'keluarga')
                                {{-- TABEL DAFTAR KELUARGA --}}
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-gray-200 bg-gray-50 text-sm font-semibold text-gray-600">
                                            <th class="py-3.5 px-6">Kepala Keluarga</th>
                                            <th class="py-3.5 px-4">Daftar Anggota</th>
                                            <th class="py-3.5 px-4">Wilayah (RT / RW)</th>
                                            <th class="py-3.5 px-6 text-right">Pintasan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-base">
                                        @foreach ($detail['items'] as $keluarga)
                                            <tr class="hover:bg-gray-50/80 align-middle" wire:key="detail-kk-{{ $keluarga['id'] }}">
                                                <td class="py-4 px-6">
                                                    <div class="font-semibold text-gray-950 text-base">{{ $keluarga['kepala_nama'] }}</div>
                                                    <div class="text-sm text-gray-500 mt-0.5">{{ $keluarga['jumlah_anggota'] }} anggota keluarga</div>
                                                </td>
                                                <td class="py-4 px-4 text-sm text-gray-700 leading-relaxed">
                                                    {{ collect($keluarga['anggota'])->pluck('nama')->implode(', ') }}
                                                </td>
                                                <td class="py-4 px-4">
                                                    <div class="text-sm font-semibold text-gray-900 whitespace-nowrap">{{ $keluarga['wilayah_singkat'] }}</div>
                                                    @if (! empty($keluarga['wilayah_daerah']))
                                                        <div class="text-xs text-gray-500 mt-0.5">{{ $keluarga['wilayah_daerah'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="py-4 px-6 text-right whitespace-nowrap">
                                                    <a
                                                        href="{{ $keluarga['url_kk'] }}"
                                                        class="inline-flex items-center gap-1 text-sm font-semibold text-teal-600 hover:text-teal-700 hover:underline"
                                                    >
                                                        <span>Lihat KK</span>
                                                        <span>&rarr;</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                {{-- TABEL DAFTAR ANGGOTA WARGA --}}
                                <table class="w-full text-left border-collapse">
                                    <thead>
                                        <tr class="border-b border-gray-200 bg-gray-50 text-sm font-semibold text-gray-600">
                                            <th class="py-3.5 px-6">Nama Anggota</th>
                                            <th class="py-3.5 px-4">Keterangan</th>
                                            <th class="py-3.5 px-4">Kepala Keluarga</th>
                                            <th class="py-3.5 px-4">Wilayah (RT / RW)</th>
                                            <th class="py-3.5 px-6 text-right">Pintasan</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-gray-100 text-base">
                                        @foreach ($detail['items'] as $warga)
                                            <tr class="hover:bg-gray-50/80 align-middle" wire:key="detail-agt-{{ $warga['id'] }}">
                                                <td class="py-4 px-6">
                                                    <div class="font-semibold text-gray-950 text-base">{{ $warga['nama'] }}</div>
                                                    <div class="text-sm text-gray-500 mt-0.5">{{ $warga['hubungan'] }}</div>
                                                </td>
                                                <td class="py-4 px-4 text-sm text-gray-700">
                                                    <div>{{ $warga['jenis_kelamin'] }}{{ $warga['umur'] !== null ? ', '.$warga['umur'].' tahun' : '' }}</div>
                                                    @if ($detail['tipe'] === 'pendidikan')
                                                        <div class="text-gray-500 mt-0.5">{{ $warga['pendidikan'] }}</div>
                                                    @elseif ($detail['tipe'] === 'pekerjaan')
                                                        <div class="text-gray-500 mt-0.5">{{ $warga['pekerjaan'] }}</div>
                                                    @else
                                                        <div class="text-gray-500 mt-0.5">{{ $warga['pendidikan'] }} &middot; {{ $warga['pekerjaan'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="py-4 px-4">
                                                    <div class="font-medium text-gray-900 text-base">{{ $warga['kepala_keluarga'] }}</div>
                                                    <div class="text-sm text-gray-500 mt-0.5">{{ $warga['total_sekeluarga'] }} anggota di KK</div>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <div class="text-sm font-semibold text-gray-900 whitespace-nowrap">{{ $warga['wilayah_singkat'] }}</div>
                                                    @if (! empty($warga['wilayah_daerah']))
                                                        <div class="text-xs text-gray-500 mt-0.5">{{ $warga['wilayah_daerah'] }}</div>
                                                    @endif
                                                </td>
                                                <td class="py-4 px-6 text-right whitespace-nowrap">
                                                    <a
                                                        href="{{ $warga['url_kk'] }}"
                                                        class="inline-flex items-center gap-1 text-sm font-semibold text-teal-600 hover:text-teal-700 hover:underline"
                                                    >
                                                        <span>Lihat KK</span>
                                                        <span>&rarr;</span>
                                                    </a>
                                                </td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- POP-UP MODAL FILTER RINCIAN DATA (DESAIN SAMA PERSIS DENGAN FILTER KELOLA WARGA) --}}
                <div
                    x-show="openFilterDetail"
                    style="display: none;"
                    class="fixed inset-0 z-[10001] overflow-y-auto"
                    role="dialog"
                    aria-modal="true"
                >
                    <div
                        x-show="openFilterDetail"
                        x-transition:enter="ease-out duration-200"
                        x-transition:enter-start="opacity-0"
                        x-transition:enter-end="opacity-100"
                        x-transition:leave="ease-in duration-150"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="fixed inset-0 bg-gray-900/50 backdrop-blur-xs transition-opacity"
                        @click="openFilterDetail = false"
                    ></div>

                    <div class="fixed inset-0 z-[10002] flex min-h-screen items-center justify-center p-4 sm:p-6 pointer-events-none">
                        <div
                            x-show="openFilterDetail"
                            x-transition:enter="ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95 translate-y-3"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="ease-in duration-150"
                            x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                            x-transition:leave-end="opacity-0 scale-95 translate-y-3"
                            class="relative w-full max-w-xl max-h-[88vh] flex flex-col overflow-hidden rounded-2xl bg-white text-left shadow-2xl border border-gray-100 pointer-events-auto"
                            @click.stop
                        >
                            {{-- Modal Header --}}
                            <div class="flex items-center justify-between border-b border-gray-100 px-6 py-4 bg-white shrink-0">
                                <div class="flex items-center gap-3">
                                    <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-teal-50 text-teal-600">
                                        <x-filament::icon icon="heroicon-o-funnel" class="h-5 w-5" />
                                    </div>
                                    <div>
                                        <h3 class="text-base font-bold text-gray-950">Filter Rincian Data</h3>
                                        <p class="text-xs text-gray-500">Saring data warga & keluarga berdasarkan umur, rincian kependudukan, atau wilayah.</p>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    @click="openFilterDetail = false"
                                    class="rounded-lg p-1.5 text-gray-400 hover:bg-gray-100 hover:text-gray-600 transition cursor-pointer"
                                >
                                    <x-filament::icon icon="heroicon-m-x-mark" class="h-5 w-5" />
                                </button>
                            </div>

                            {{-- Modal Body (Scrollable) --}}
                            <div class="min-h-0 flex-1 overflow-y-auto px-6 py-5 space-y-4">
                                @if (count($detail['sub_kategori']) > 1)
                                    <label class="block space-y-1">
                                        <span class="text-xs font-bold text-gray-800">Kategori Statistik</span>
                                        <select wire:model.live="detailKunci" class="rd-select text-xs">
                                            @foreach ($detail['sub_kategori'] as $sub)
                                                <option value="{{ $sub['key'] }}">
                                                    {{ $sub['label'] }} ({{ $sub['count'] }})
                                                </option>
                                            @endforeach
                                        </select>
                                    </label>
                                @endif

                                {{-- Box 1: Filter Umur & Rincian Data Warga --}}
                                <div class="rounded-xl border border-gray-200 bg-gray-50/60 p-4">
                                    <div class="flex items-center gap-2 mb-3">
                                        <span class="flex h-5 w-5 items-center justify-center rounded-full bg-teal-600 text-[10px] font-bold text-white">
                                            <x-filament::icon icon="heroicon-m-user" class="h-3 w-3" />
                                        </span>
                                        <span class="text-xs font-bold text-gray-900 uppercase tracking-wider">Filter Umur & Rincian Warga</span>
                                    </div>

                                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Kelompok Umur</span>
                                            <select wire:model.live="filterUmurKategori" class="rd-select text-xs">
                                                <option value="">Semua Kelompok Umur</option>
                                                @foreach (\App\Filament\Widgets\RingkasanWidget::umurBuckets() as $uKey => $uInfo)
                                                    <option value="{{ $uKey }}">{{ $uInfo['label'] }}</option>
                                                @endforeach
                                            </select>
                                        </label>

                                        <div class="grid grid-cols-2 gap-2">
                                            <label class="block space-y-1">
                                                <span class="text-[11px] font-semibold text-gray-700">Umur Min (thn)</span>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="120"
                                                    wire:model.live.debounce.400ms="filterUmurMin"
                                                    placeholder="Mis. 18"
                                                    class="rd-select text-xs !pr-2"
                                                />
                                            </label>
                                            <label class="block space-y-1">
                                                <span class="text-[11px] font-semibold text-gray-700">Umur Maks (thn)</span>
                                                <input
                                                    type="number"
                                                    min="0"
                                                    max="120"
                                                    wire:model.live.debounce.400ms="filterUmurMax"
                                                    placeholder="Mis. 59"
                                                    class="rd-select text-xs !pr-2"
                                                />
                                            </label>
                                        </div>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Jenis Kelamin</span>
                                            <select wire:model.live="filterJk" class="rd-select text-xs">
                                                <option value="">Semua Jenis Kelamin</option>
                                                <option value="L">Laki-laki</option>
                                                <option value="P">Perempuan</option>
                                            </select>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Hubungan Keluarga</span>
                                            <select wire:model.live="filterHubungan" class="rd-select text-xs">
                                                <option value="">Semua Hubungan</option>
                                                @foreach ($opsiHubungan as $idHub => $namaHub)
                                                    <option value="{{ $idHub }}">{{ $namaHub }}</option>
                                                @endforeach
                                            </select>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Pendidikan</span>
                                            <select wire:model.live="filterPendidikan" class="rd-select text-xs">
                                                <option value="">Semua Pendidikan</option>
                                                @foreach ($opsiPendidikan as $idDidik => $namaDidik)
                                                    <option value="{{ $idDidik }}">{{ $namaDidik }}</option>
                                                @endforeach
                                            </select>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Pekerjaan</span>
                                            <select wire:model.live="filterPekerjaan" class="rd-select text-xs">
                                                <option value="">Semua Pekerjaan</option>
                                                @foreach ($opsiPekerjaan as $idKerja => $namaKerja)
                                                    <option value="{{ $idKerja }}">{{ $namaKerja }}</option>
                                                @endforeach
                                            </select>
                                        </label>

                                        <label class="block space-y-1 sm:col-span-2">
                                            <span class="text-[11px] font-semibold text-gray-700">Status Kependudukan</span>
                                            <select wire:model.live="filterStatusWarga" class="rd-select text-xs">
                                                <option value="">Semua Status</option>
                                                <option value="menetap">Menetap (Aktif)</option>
                                                <option value="pindah">Pindah</option>
                                            </select>
                                        </label>
                                    </div>
                                </div>

                                {{-- Box 2: Filter Wilayah & RT / RW (Sama persis dengan Filter Kelola Warga) --}}
                                <div class="rounded-xl border border-teal-100 bg-teal-50/40 p-4">
                                    <div class="flex items-center justify-between gap-2 mb-2">
                                        <div class="flex items-center gap-2">
                                            <span class="flex h-5 w-5 items-center justify-center rounded-full bg-teal-600 text-[10px] font-bold text-white">
                                                <x-filament::icon icon="heroicon-m-map-pin" class="h-3 w-3" />
                                            </span>
                                            <span class="text-xs font-bold text-gray-900 uppercase tracking-wider">Filter Wilayah & RT / RW</span>
                                        </div>

                                        @if ($this->hasFilterWilayah())
                                            <button
                                                type="button"
                                                wire:click="resetFilterWilayah"
                                                class="text-xs font-semibold text-teal-700 hover:text-teal-900 hover:underline cursor-pointer"
                                            >
                                                Kosongkan Wilayah
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
                                                list="list-detail-provinsi"
                                                wire:model.live.debounce.400ms="filterProvinsi"
                                                placeholder="Ketik mis. Jawa Barat"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-detail-provinsi">
                                                @foreach ($this->daftarProvinsi as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Kabupaten / Kota</span>
                                            <input
                                                type="text"
                                                list="list-detail-kabkota"
                                                wire:model.live.debounce.400ms="filterKabkota"
                                                placeholder="Ketik mis. Garut atau Bandung"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-detail-kabkota">
                                                @foreach ($this->daftarKabkota as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Kecamatan</span>
                                            <input
                                                type="text"
                                                list="list-detail-kecamatan"
                                                wire:model.live.debounce.400ms="filterKecamatan"
                                                placeholder="Ketik mis. Bojongsoang"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-detail-kecamatan">
                                                @foreach ($this->daftarKecamatan as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">Desa / Kelurahan / Dusun</span>
                                            <input
                                                type="text"
                                                list="list-detail-desa"
                                                wire:model.live.debounce.400ms="filterDesa"
                                                placeholder="Ketik mis. Lengkong atau Dusun Krajan"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-detail-desa">
                                                @foreach ($this->daftarDesa as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">RW</span>
                                            <input
                                                type="text"
                                                list="list-detail-rw"
                                                wire:model.live.debounce.400ms="filterRw"
                                                placeholder="Ketik mis. RW 01 atau 01"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-detail-rw">
                                                @foreach ($this->daftarRw as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>

                                        <label class="block space-y-1">
                                            <span class="text-[11px] font-semibold text-gray-700">RT</span>
                                            <input
                                                type="text"
                                                list="list-detail-rt"
                                                wire:model.live.debounce.400ms="filterRt"
                                                placeholder="Ketik mis. RT 01 atau 01"
                                                class="rd-search-input text-xs"
                                            />
                                            <datalist id="list-detail-rt">
                                                @foreach ($this->daftarRt as $nama)
                                                    <option value="{{ $nama }}"></option>
                                                @endforeach
                                            </datalist>
                                        </label>
                                    </div>
                                </div>
                            </div>

                            {{-- Modal Footer --}}
                            <div class="flex items-center justify-between border-t border-gray-100 bg-gray-50/80 px-6 py-3.5 shrink-0">
                                <button
                                    type="button"
                                    wire:click="resetFilterDetail"
                                    class="text-xs font-semibold text-gray-600 hover:text-gray-950 transition cursor-pointer"
                                >
                                    Atur Ulang
                                </button>

                                <button
                                    type="button"
                                    @click="openFilterDetail = false"
                                    class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 px-4 py-2 text-xs font-semibold rounded-lg bg-teal-600 text-white hover:bg-teal-700 shadow-sm transition cursor-pointer"
                                >
                                    <x-filament::icon icon="heroicon-m-check" class="h-4 w-4" />
                                    <span>Terapkan Filter</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
