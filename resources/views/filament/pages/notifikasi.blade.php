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
        {{-- Banner Biru Tua RUKUNDATA & Aksi Cepat --}}
        <div
            class="rounded-2xl px-6 py-5 text-white shadow-[0_8px_24px_-10px_rgba(11,74,125,0.45)] flex flex-col md:flex-row md:items-center md:justify-between gap-4"
            style="background: linear-gradient(135deg, #0b4a7d 0%, #073257 100%);"
        >
            <div class="min-w-0">
                <div class="text-base font-bold text-white">
                    Pantau Hasil Verifikasi, Antrean Persetujuan, &amp; Perubahan Data KK
                </div>
                <div class="text-xs sm:text-sm text-[#dbeafe] mt-1">
                    @if($unreadHasilCount > 0)
                        <span class="font-semibold text-white">{{ $unreadHasilCount }} notifikasi hasil verifikasi belum dibaca</span> ·
                    @endif
                    {{ $pendingAntreanCount }} antrean menunggu proses
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5 shrink-0">
                @if($unreadHasilCount > 0)
                    <button
                        type="button"
                        wire:click="tandaiSemuaDibaca"
                        class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg border border-white/30 bg-white/15 px-3.5 text-xs font-semibold text-white shadow-sm hover:bg-white/25 transition whitespace-nowrap cursor-pointer"
                    >
                        Tandai Semua Dibaca
                    </button>
                @endif

                <a
                    href="{{ $urlValidasi }}"
                    wire:navigate
                    class="inline-flex h-9 items-center justify-center gap-1.5 rounded-lg bg-white px-4 text-xs font-semibold text-[#0b4a7d] shadow-md hover:bg-[#ebf4ff] transition whitespace-nowrap"
                >
                    <span>Halaman Validasi &amp; Persetujuan</span>
                    <span>&rarr;</span>
                </a>
            </div>
        </div>

        {{-- Navigasi Tab --}}
        <div class="rounded-xl border border-[#d3dfec] bg-white shadow-[0_3px_10px_-2px_rgba(15,38,67,0.08)] overflow-hidden">
            <div class="border-b border-[#d3dfec] bg-[#e6eef7] px-4 py-3 flex flex-wrap items-center gap-2.5">
                <button
                    type="button"
                    wire:click="setTab('hasil')"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition cursor-pointer {{ $this->tab === 'hasil' ? 'bg-[#0b4a7d] text-white shadow-md' : 'bg-white text-[#0f2643] border border-[#b4c7dc] shadow-sm hover:bg-[#f0f7ff] hover:text-[#0b4a7d]' }}"
                >
                    <span>Hasil &amp; Status Verifikasi</span>
                    @if($unreadHasilCount > 0)
                        <span class="inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $this->tab === 'hasil' ? 'bg-white/20 text-white' : 'bg-[#0b4a7d] text-white' }}">
                            {{ $unreadHasilCount }} baru
                        </span>
                    @endif
                </button>

                <button
                    type="button"
                    wire:click="setTab('antrean')"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition cursor-pointer {{ $this->tab === 'antrean' ? 'bg-[#0b4a7d] text-white shadow-md' : 'bg-white text-[#0f2643] border border-[#b4c7dc] shadow-sm hover:bg-[#f0f7ff] hover:text-[#0b4a7d]' }}"
                >
                    <span>Antrean Approval</span>
                    <span class="inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $this->tab === 'antrean' ? 'bg-white/20 text-white' : 'bg-[#ebf4ff] text-[#0b4a7d]' }}">
                        {{ $pendingAntreanCount }}
                    </span>
                </button>

                <button
                    type="button"
                    wire:click="setTab('log_kk')"
                    class="inline-flex items-center gap-2 rounded-lg px-4 py-2 text-xs font-semibold transition cursor-pointer {{ $this->tab === 'log_kk' ? 'bg-[#0b4a7d] text-white shadow-md' : 'bg-white text-[#0f2643] border border-[#b4c7dc] shadow-sm hover:bg-[#f0f7ff] hover:text-[#0b4a7d]' }}"
                >
                    <span>Perubahan Data KK</span>
                    <span class="inline-flex items-center justify-center rounded-full px-1.5 py-0.5 text-[10px] font-bold {{ $this->tab === 'log_kk' ? 'bg-white/20 text-white' : 'bg-[#ebf4ff] text-[#0b4a7d]' }}">
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
                                \App\Models\NotifikasiValidasi::JENIS_DISETUJUI_DESA => 'bg-[#ebf4ff] text-[#0b4a7d] border-[#cbe1fa]',
                                \App\Models\NotifikasiValidasi::JENIS_DISETUJUI_RW => 'bg-sky-50 text-sky-800 border-sky-200',
                                \App\Models\NotifikasiValidasi::JENIS_SIAP_DISAHKAN_DESA => 'bg-[#f0f7ff] text-[#0b4a7d] border-[#93c5fd]',
                                \App\Models\NotifikasiValidasi::JENIS_DITOLAK_RW,
                                \App\Models\NotifikasiValidasi::JENIS_DITOLAK_DESA => 'bg-rose-50 text-rose-800 border-rose-200',
                                default => 'bg-slate-100 text-slate-700 border-slate-200',
                            };
                        @endphp
                        <a
                            href="{{ route('notifikasi-validasi.buka', $notif) }}"
                            class="block px-5 py-4 hover:bg-[#f0f7ff] transition {{ $isUnread ? 'bg-[#f4f9ff]' : 'bg-white' }}"
                        >
                            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    @if($isUnread)
                                        <span class="h-2.5 w-2.5 rounded-full bg-[#0b4a7d] shrink-0" title="Belum dibaca"></span>
                                    @endif
                                    <span class="inline-flex items-center rounded-md border px-2.5 py-0.5 text-xs font-semibold {{ $badgeClass }}">
                                        {{ $notif->judul }}
                                    </span>
                                    @if($isUnread)
                                        <span class="text-[11px] font-semibold text-[#0b4a7d]">· Baru</span>
                                    @endif
                                </div>
                                <span class="text-xs text-[#64748b] shrink-0">
                                    {{ $notif->created_at?->format('d M Y H:i') }} ({{ $notif->created_at?->diffForHumans() }})
                                </span>
                            </div>

                            <p class="mt-2 text-sm text-[#334155] leading-relaxed">
                                {{ $notif->pesan }}
                            </p>

                            <div class="mt-2.5 flex items-center justify-end">
                                <span class="text-xs font-semibold text-[#0b4a7d]">Lihat Rincian &amp; Status →</span>
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
                                    class="text-xs font-semibold text-[#0b4a7d] hover:text-[#093c66]"
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
