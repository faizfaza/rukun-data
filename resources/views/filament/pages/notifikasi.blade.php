<x-filament-panels::page>
    @php
        $user = auth()->user();
        $unreadHasilCount = $this->getUnreadHasilCount();
        $pendingAntreanCount = $this->getPendingAntreanCount();
        $hasilNotifList = $this->getHasilNotifList();
        $antreanList = $this->getAntreanList();
        $logPerubahanKk = $this->getLogPerubahanKk();

        $urlValidasi = \App\Filament\Resources\ValidasiPerubahanResource::getUrl('index');
        $urlLog = \App\Filament\Resources\AuditLogResource::getUrl('index');
    @endphp

    <div class="space-y-5">
        {{-- Bar Ringkasan & Aksi Cepat --}}
        <div class="rounded-xl border border-gray-200 bg-white p-4 shadow-xs flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="text-sm font-semibold text-gray-900">
                    Pantau Hasil Verifikasi, Antrean Persetujuan, &amp; Perubahan Data KK
                </div>
                <div class="text-xs text-gray-500 mt-0.5">
                    @if($unreadHasilCount > 0)
                        <span class="font-semibold text-emerald-700">{{ $unreadHasilCount }} notifikasi hasil verifikasi belum dibaca</span> ·
                    @endif
                    {{ $pendingAntreanCount }} antrean menunggu proses
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2">
                @if($unreadHasilCount > 0)
                    <button
                        type="button"
                        wire:click="tandaiSemuaDibaca"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-emerald-200 bg-emerald-50 px-3 py-1.5 text-xs font-semibold text-emerald-800 hover:bg-emerald-100 transition cursor-pointer"
                    >
                        Tandai Semua Dibaca
                    </button>
                @endif

                <a
                    href="{{ $urlValidasi }}"
                    wire:navigate
                    class="inline-flex items-center gap-1.5 rounded-lg bg-teal-700 px-3.5 py-1.5 text-xs font-semibold text-white hover:bg-teal-800 transition"
                >
                    Halaman Validasi &amp; Persetujuan →
                </a>
            </div>
        </div>

        {{-- Navigasi Tab --}}
        <div class="rounded-xl border border-gray-200 bg-white shadow-xs overflow-hidden">
            <div class="border-b border-gray-200 bg-gray-50/80 px-4 py-3 flex flex-wrap items-center gap-2">
                <button
                    type="button"
                    wire:click="setTab('hasil')"
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition cursor-pointer {{ $this->tab === 'hasil' ? 'bg-teal-700 text-white shadow-xs' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50' }}"
                >
                    <span>Hasil &amp; Status Verifikasi</span>
                    @if($unreadHasilCount > 0)
                        <span class="inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $this->tab === 'hasil' ? 'bg-emerald-400 text-teal-950' : 'bg-emerald-600 text-white' }}">
                            {{ $unreadHasilCount }} baru
                        </span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="setTab('antrean')"
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition cursor-pointer {{ $this->tab === 'antrean' ? 'bg-teal-700 text-white shadow-xs' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50' }}"
                >
                    <span>Antrean Approval</span>
                    <span class="inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $this->tab === 'antrean' ? 'bg-white/20 text-white' : 'bg-amber-100 text-amber-800' }}">
                        {{ $pendingAntreanCount }}
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('log_kk')"
                    class="inline-flex items-center gap-2 rounded-lg px-3.5 py-2 text-xs font-semibold transition cursor-pointer {{ $this->tab === 'log_kk' ? 'bg-teal-700 text-white shadow-xs' : 'bg-white text-gray-700 border border-gray-200 hover:bg-gray-50' }}"
                >
                    <span>Perubahan Data KK</span>
                    <span class="inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $this->tab === 'log_kk' ? 'bg-white/20 text-white' : 'bg-gray-200 text-gray-700' }}">
                        {{ $logPerubahanKk->count() }}
                    </span>
                </button>
            </div>

            {{-- TAB 1: HASIL & STATUS VERIFIKASI --}}
            @if($this->tab === 'hasil')
                <div class="divide-y divide-gray-100">
                    @forelse($hasilNotifList as $notif)
                        @php
                            $isUnread = ! $notif->sudahDibaca();
                            $badgeClass = match ($notif->jenis) {
                                \App\Models\NotifikasiValidasi::JENIS_DISETUJUI_DESA => 'bg-emerald-100 text-emerald-800 border-emerald-200',
                                \App\Models\NotifikasiValidasi::JENIS_DISETUJUI_RW => 'bg-sky-100 text-sky-800 border-sky-200',
                                \App\Models\NotifikasiValidasi::JENIS_SIAP_DISAHKAN_DESA => 'bg-amber-100 text-amber-800 border-amber-200',
                                \App\Models\NotifikasiValidasi::JENIS_DITOLAK_RW,
                                \App\Models\NotifikasiValidasi::JENIS_DITOLAK_DESA => 'bg-rose-100 text-rose-800 border-rose-200',
                                default => 'bg-gray-100 text-gray-700 border-gray-200',
                            };
                        @endphp
                        <a
                            href="{{ route('notifikasi-validasi.buka', $notif) }}"
                            class="block px-5 py-4 hover:bg-teal-50/40 transition {{ $isUnread ? 'bg-emerald-50/40' : 'bg-white' }}"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($isUnread)
                                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500 shrink-0" title="Belum dibaca"></span>
                                    @endif
                                    <span class="inline-flex items-center rounded-md border px-2 py-0.5 text-xs font-bold {{ $badgeClass }}">
                                        {{ $notif->judul }}
                                    </span>
                                    @if($isUnread)
                                        <span class="text-[11px] font-semibold text-emerald-700">· Baru</span>
                                    @endif
                                </div>
                                <span class="text-xs text-gray-400">
                                    {{ $notif->created_at?->format('d M Y H:i') }} ({{ $notif->created_at?->diffForHumans() }})
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-gray-700 leading-relaxed">
                                {{ $notif->pesan }}
                            </p>

                            <div class="mt-2.5 flex items-center justify-end">
                                <span class="text-xs font-semibold text-teal-700">Lihat Rincian &amp; Status →</span>
                            </div>
                        </a>
                    @empty
                        <div class="px-6 py-12 text-center text-sm text-gray-500">
                            Belum ada notifikasi hasil verifikasi dari Admin RW atau Kepala Desa.
                        </div>
                    @endforelse
                </div>
            @endif

            {{-- TAB 2: ANTREAN APPROVAL --}}
            @if($this->tab === 'antrean')
                <div class="divide-y divide-gray-100">
                    @forelse($antreanList as $item)
                        @php
                            $statusBadgeClass = match ($item->status) {
                                \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_RW => 'bg-amber-100 text-amber-800',
                                \App\Models\ValidasiPerubahan::STATUS_MENUNGGU_DESA => 'bg-sky-100 text-sky-800',
                                \App\Models\ValidasiPerubahan::STATUS_DISETUJUI => 'bg-emerald-100 text-emerald-800',
                                \App\Models\ValidasiPerubahan::STATUS_DITOLAK => 'bg-rose-100 text-rose-800',
                                default => 'bg-gray-100 text-gray-700',
                            };
                        @endphp
                        <div class="px-5 py-4 hover:bg-gray-50/70 transition">
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-bold text-gray-900">
                                        {{ $item->labelKeluargaTerkait() }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        · {{ \App\Support\AuditFormatter::labelTabel($item->nama_tabel) }}
                                    </span>
                                    <span class="inline-flex items-center rounded-md px-2 py-0.5 text-xs font-semibold {{ $statusBadgeClass }}">
                                        {{ \App\Models\ValidasiPerubahan::labelStatus($item->status) }}
                                    </span>
                                </div>
                                <span class="text-xs text-gray-400">
                                    {{ $item->created_at?->format('d M Y H:i') }} ({{ $item->created_at?->diffForHumans() }})
                                </span>
                            </div>

                            <div class="mt-1 text-xs text-gray-500">
                                Pengaju: <strong class="text-gray-700">{{ $item->pengaju?->name ?? 'Petugas' }}</strong>
                                ({{ $item->pengaju?->role?->label() ?? $item->peran_pengaju }})
                                @if($item->wilayahRt)
                                    · {{ $item->wilayahRt->nama }}
                                @endif
                                @if($item->wilayahRw)
                                    / {{ $item->wilayahRw->nama }}
                                @endif
                            </div>

                            <p class="mt-2 text-sm text-gray-700 leading-relaxed">
                                {{ $item->ringkasan }}
                            </p>

                            <div class="mt-3 flex flex-wrap items-center justify-end gap-3">
                                <a
                                    href="{{ \App\Filament\Pages\KonfirmasiPengajuan::urlForTickets([$item->id]) }}"
                                    wire:navigate
                                    class="text-xs font-semibold text-gray-600 hover:text-gray-900"
                                >
                                    Lihat Rincian
                                </a>
                                <a
                                    href="{{ $urlValidasi }}"
                                    wire:navigate
                                    class="text-xs font-semibold text-teal-700 hover:text-teal-900"
                                >
                                    Proses di Halaman Validasi →
                                </a>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-12 text-center text-sm text-gray-500">
                            Belum ada antrean validasi perubahan data KK.
                        </div>
                    @endforelse
                </div>
            @endif

            {{-- TAB 3: LOG PERUBAHAN DATA KK --}}
            @if($this->tab === 'log_kk')
                <div class="divide-y divide-gray-100">
                    @forelse($logPerubahanKk as $log)
                        @php
                            $desc = \App\Support\AuditFormatter::describe($log);
                        @endphp
                        <a
                            href="{{ $urlLog }}"
                            wire:navigate
                            class="block px-5 py-4 hover:bg-gray-50/70 transition"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-bold text-gray-900">
                                        {{ $log->pengguna?->name ?? 'Sistem' }}
                                    </span>
                                    <span class="text-xs text-gray-500">
                                        ({{ $log->pengguna?->role?->label() ?? '—' }})
                                    </span>
                                    <span class="inline-flex items-center rounded bg-gray-100 px-2 py-0.5 text-xs font-medium text-gray-700">
                                        {{ \App\Support\AuditFormatter::labelTabel($log->nama_tabel) }}
                                    </span>
                                </div>
                                <span class="text-xs text-gray-400">
                                    {{ $log->waktu?->format('d M Y H:i') }} ({{ $log->waktu?->diffForHumans() }})
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-gray-700 leading-relaxed">
                                {{ $desc['ringkasan'] ?? 'Perubahan data keluarga' }}
                            </p>
                        </a>
                    @empty
                        <div class="px-6 py-12 text-center text-sm text-gray-500">
                            Belum ada riwayat perubahan data KK di wilayah Anda.
                        </div>
                    @endforelse
                </div>
            @endif
        </div>
    </div>
</x-filament-panels::page>
