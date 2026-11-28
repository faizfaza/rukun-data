<x-filament-panels::page>
    @php
        $user = auth()->user();
        $tiketUtama = $this->tiketList->first();
        $jumlahTiket = $this->tiketList->count();
        $urlDashboard = url('/admin');
        $urlValidasi = \App\Filament\Resources\ValidasiPerubahanResource::getUrl('index');
        $urlWilayah = \App\Filament\Pages\JelajahWilayah::getUrl();

        $sedangMenungguRw = $tiketUtama?->status === \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_RW;
        $sedangMenungguDesa = $tiketUtama?->status === \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_DESA;
        $sudahDisetujui = $tiketUtama?->status === \App\Models\ValidasiPerubahan::STATUS_DISETUJUI;
        $sudahDitolak = $tiketUtama?->status === \App\Models\ValidasiPerubahan::STATUS_DITOLAK;
    @endphp

    <div class="space-y-6" x-data="{ tampilRincian: true }">
        {{-- Kartu Konfirmasi Utama --}}
        <div class="rounded-2xl border {{ $sudahDitolak ? 'border-rose-200' : 'border-emerald-200' }} bg-white shadow-sm overflow-hidden">
            <div class="bg-gradient-to-r {{ $sudahDitolak ? 'from-rose-50 via-red-50/70 to-white border-rose-100' : 'from-emerald-50 via-teal-50/70 to-white border-emerald-100' }} px-6 py-6 border-b">
                <div class="flex flex-col sm:flex-row sm:items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $sudahDitolak ? 'bg-rose-600' : 'bg-emerald-600' }} text-white shadow-sm">
                        @if($sudahDitolak)
                            <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        @else
                            <svg class="h-7 w-7" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                            </svg>
                        @endif
                    </div>

                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            @if($sudahDisetujui)
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                    Terverifikasi &amp; Disahkan Desa
                                </span>
                            @elseif($sudahDitolak)
                                <span class="inline-flex items-center rounded-full bg-rose-100 px-2.5 py-0.5 text-xs font-semibold text-rose-800">
                                    Pengajuan Ditolak
                                </span>
                            @else
                                <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-xs font-semibold text-emerald-800">
                                    Pengajuan Berhasil Dikirim
                                </span>
                                @if($sedangMenungguRw)
                                    <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-xs font-semibold text-amber-800">
                                        Menunggu Verifikasi Admin RW
                                    </span>
                                @elseif($sedangMenungguDesa)
                                    <span class="inline-flex items-center rounded-full bg-sky-100 px-2.5 py-0.5 text-xs font-semibold text-sky-800">
                                        Menunggu Pengesahan Kepala Desa
                                    </span>
                                @endif
                            @endif
                        </div>

                        <h2 class="mt-2 text-lg sm:text-xl font-bold text-gray-900">
                            @if($sudahDisetujui)
                                Verifikasi Berhasil &amp; Perubahan Sudah Masuk Data Resmi
                            @elseif($sudahDitolak)
                                Pengajuan Perubahan Ditolak
                            @else
                                Perubahan Anda Berhasil Diajukan &amp; Menunggu Verifikasi
                            @endif
                        </h2>

                        <p class="mt-1.5 text-sm text-gray-600 leading-relaxed">
                            @if($sudahDisetujui)
                                Seluruh tahapan verifikasi telah selesai dan disahkan oleh <strong>{{ $tiketUtama->verifikatorDesa?->name ?? 'Kepala Desa / Admin Desa' }}</strong>. Perubahan yang diajukan kini telah resmi diterapkan ke database utama.
                            @elseif($sudahDitolak)
                                Pengajuan ini ditolak dengan alasan: <strong>{{ $tiketUtama->catatan_desa ?: $tiketUtama->catatan_rw ?: 'Tidak memenuhi syarat verifikasi' }}</strong>. Data utama di database tetap utuh dan tidak berubah.
                            @elseif($sedangMenungguRw)
                                Pengajuan ini telah dikirim sesuai alur berjenjang: akan diperiksa oleh <strong>Admin RW</strong> terlebih dahulu, lalu diteruskan ke <strong>Admin Desa / Kepala Desa</strong> untuk pengesahan akhir. Data resmi di database <strong>belum berubah</strong> sampai seluruh tahapan verifikasi disetujui.
                            @elseif($sedangMenungguDesa)
                                Pengajuan ini telah dikirim ke <strong>Admin Desa / Kepala Desa</strong> untuk pemeriksaan dan pengesahan akhir. Data resmi di database <strong>belum berubah</strong> sampai disahkan oleh Desa.
                            @else
                                Pengajuan perubahan Anda telah berhasil dicatat dalam sistem validasi berjenjang. Data resmi di database <strong>belum berubah</strong> sampai seluruh tahapan verifikasi disetujui.
                            @endif
                        </p>
                    </div>
                </div>
            </div>

            {{-- Tombol Pintasan (Shortcut) --}}
            <div class="px-6 py-4 bg-white flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap items-center gap-2.5">
                    <a
                        href="{{ $urlValidasi }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 rounded-lg bg-teal-700 px-4 py-2 text-sm font-semibold text-white shadow-xs hover:bg-teal-800 transition"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                        </svg>
                        Buka Halaman Validasi
                    </a>

                    <a
                        href="{{ $urlDashboard }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m2.25 12 8.954-8.955c.44-.439 1.152-.439 1.591 0L21.75 12M4.5 9.75v10.125c0 .621.504 1.125 1.125 1.125H9.75v-4.875c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21h4.125c.621 0 1.125-.504 1.125-1.125V9.75M8.25 21h8.25" />
                        </svg>
                        Kembali ke Dashboard
                    </a>

                    <a
                        href="{{ $urlWilayah }}"
                        wire:navigate
                        class="inline-flex items-center gap-2 rounded-lg border border-gray-300 bg-white px-4 py-2 text-sm font-semibold text-gray-700 shadow-xs hover:bg-gray-50 transition"
                    >
                        <svg class="h-4 w-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
                        </svg>
                        Jelajah Wilayah
                    </a>
                </div>

                @if($jumlahTiket > 0)
                    <button
                        type="button"
                        @click="tampilRincian = !tampilRincian"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-teal-200 bg-teal-50 px-3.5 py-2 text-xs font-semibold text-teal-800 hover:bg-teal-100 transition cursor-pointer"
                    >
                        <span x-text="tampilRincian ? 'Sembunyikan Rincian' : 'Lihat Rincian Pengajuan'"></span>
                        <svg class="h-4 w-4 transition-transform" :class="tampilRincian ? 'rotate-180' : ''" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                        </svg>
                    </button>
                @endif
            </div>
        </div>

        {{-- Rincian Pengajuan & Alur Validasi Berjenjang --}}
        @if($jumlahTiket > 0)
            <div x-show="tampilRincian" x-transition class="space-y-6">
                @foreach($this->tiketList as $index => $tiket)
                    <div class="rounded-2xl border border-gray-200 bg-white shadow-xs overflow-hidden">
                        <div class="px-5 py-3.5 bg-gray-50 border-b border-gray-200 flex flex-wrap items-center justify-between gap-2">
                            <div class="flex items-center gap-2">
                                <span class="text-xs font-bold uppercase tracking-wider text-gray-500">
                                    Rincian Pengajuan {{ $jumlahTiket > 1 ? '#'.($index + 1) : '' }}
                                </span>
                                <span class="inline-flex items-center rounded-md bg-teal-50 px-2 py-0.5 text-xs font-semibold text-teal-800 border border-teal-200">
                                    {{ \App\Models\ValidasiPerubahan::labelAksi($tiket->aksi) }}
                                </span>
                                <span class="text-xs font-semibold text-gray-800">
                                    · {{ $tiket->labelKeluargaTerkait() }}
                                </span>
                            </div>
                            <span class="text-xs text-gray-500">
                                Tiket #{{ $tiket->id }} · {{ $tiket->created_at?->format('d M Y H:i') }}
                            </span>
                        </div>

                        <div class="p-5">
                            @include('filament.modals.validasi-perubahan-detail', ['record' => $tiket])
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="rounded-xl border border-gray-200 bg-white p-6 text-center text-sm text-gray-600">
                Tidak ada rincian tiket spesifik yang dipilih. Silakan buka halaman
                <a href="{{ $urlValidasi }}" wire:navigate class="font-semibold text-teal-700 hover:underline">Validasi &amp; Persetujuan</a>
                untuk memantau seluruh riwayat pengajuan Anda.
            </div>
        @endif
    </div>
</x-filament-panels::page>
