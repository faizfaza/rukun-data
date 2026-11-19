@php
    /** @var \App\Models\ValidasiPerubahan $record */
    $payload = $record->payloadData;
    $kalimatList = $payload['kalimat'] ?? (filled($record->ringkasan) ? [$record->ringkasan] : []);
    $perubahan = $payload['perubahan'] ?? [];
    $sebelum = $payload['sebelum'] ?? [];
    $sesudah = $payload['sesudah'] ?? [];
    $dataPindah = $payload['data_pindah'] ?? null;

    $tahapRwSelesai = in_array($record->status, [\App\Models\ValidasiPerubahan::STATUS_MENUNGGU_DESA, \App\Models\ValidasiPerubahan::STATUS_DISETUJUI], true);
    $tahapDesaSelesai = $record->status === \App\Models\ValidasiPerubahan::STATUS_DISETUJUI;
    $isDitolak = $record->status === \App\Models\ValidasiPerubahan::STATUS_DITOLAK;
@endphp

<div class="space-y-5 text-sm text-gray-800">
    {{-- Alur Verifikasi Berjenjang (Stepper 3 Tahap) --}}
    <div class="rounded-xl border border-gray-200 bg-gray-50/70 p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-3">
            Alur Validasi Berjenjang (RT → RW → Desa)
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
            {{-- Tahap 1: Pengajuan --}}
            <div class="rounded-lg border border-emerald-200 bg-white p-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-700">
                        <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                        1. Diajukan
                    </span>
                    <span class="text-[11px] text-gray-500">{{ $record->created_at?->format('d M Y H:i') }}</span>
                </div>
                <div class="mt-1 font-semibold text-gray-900">
                    {{ $record->pengaju?->name ?? 'Petugas' }}
                </div>
                <div class="text-xs text-gray-500">
                    {{ $record->pengaju?->role?->label() ?? $record->peran_pengaju }}
                    @if($record->wilayahRt)
                        · {{ $record->wilayahRt->nama }}
                    @endif
                </div>
                @if(filled($record->catatan_pengaju))
                    <div class="mt-2 rounded bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700 border border-gray-200">
                        <span class="font-medium text-gray-500">Catatan:</span> {{ $record->catatan_pengaju }}
                    </div>
                @endif
            </div>

            {{-- Tahap 2: Verifikasi RW --}}
            <div class="rounded-lg border {{ $tahapRwSelesai ? 'border-emerald-200 bg-white' : ($isDitolak && $record->id_verifikator_rw ? 'border-rose-200 bg-rose-50/40' : 'border-amber-200 bg-amber-50/40') }} p-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold {{ $tahapRwSelesai ? 'text-emerald-700' : ($isDitolak && $record->id_verifikator_rw ? 'text-rose-700' : 'text-amber-700') }}">
                        <span class="h-2 w-2 rounded-full {{ $tahapRwSelesai ? 'bg-emerald-500' : ($isDitolak && $record->id_verifikator_rw ? 'bg-rose-500' : 'bg-amber-500') }}"></span>
                        2. Verifikasi RW
                    </span>
                    <span class="text-[11px] text-gray-500">
                        {{ $record->waktu_verifikasi_rw ? $record->waktu_verifikasi_rw->format('d M Y H:i') : 'Menunggu' }}
                    </span>
                </div>
                <div class="mt-1 font-semibold text-gray-900">
                    @if($record->verifikatorRw)
                        {{ $record->verifikatorRw->name }}
                    @elseif($record->wilayahRw)
                        Admin {{ $record->wilayahRw->nama }}
                    @else
                        Tingkat RW
                    @endif
                </div>
                <div class="text-xs text-gray-500">
                    @if($tahapRwSelesai)
                        Disetujui & diteruskan ke Desa
                    @elseif($isDitolak && $record->id_verifikator_rw)
                        Ditolak pada tingkat RW
                    @else
                        Menunggu pemeriksaan Admin RW
                    @endif
                </div>
                @if(filled($record->catatan_rw))
                    <div class="mt-2 rounded bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700 border border-gray-200">
                        <span class="font-medium text-gray-500">Catatan RW:</span> {{ $record->catatan_rw }}
                    </div>
                @endif
            </div>

            {{-- Tahap 3: Pengesahan Desa --}}
            <div class="rounded-lg border {{ $tahapDesaSelesai ? 'border-emerald-200 bg-white' : ($isDitolak && $record->id_verifikator_desa ? 'border-rose-200 bg-rose-50/40' : 'border-gray-200 bg-white') }} p-3">
                <div class="flex items-center justify-between gap-2">
                    <span class="inline-flex items-center gap-1.5 text-xs font-bold {{ $tahapDesaSelesai ? 'text-emerald-700' : ($isDitolak && $record->id_verifikator_desa ? 'text-rose-700' : 'text-gray-600') }}">
                        <span class="h-2 w-2 rounded-full {{ $tahapDesaSelesai ? 'bg-emerald-500' : ($isDitolak && $record->id_verifikator_desa ? 'bg-rose-500' : 'bg-gray-400') }}"></span>
                        3. Pengesahan Desa
                    </span>
                    <span class="text-[11px] text-gray-500">
                        {{ $record->waktu_verifikasi_desa ? $record->waktu_verifikasi_desa->format('d M Y H:i') : 'Menunggu' }}
                    </span>
                </div>
                <div class="mt-1 font-semibold text-gray-900">
                    @if($record->verifikatorDesa)
                        {{ $record->verifikatorDesa->name }}
                    @elseif($record->wilayahDesa)
                        Admin / Kepala {{ $record->wilayahDesa->nama }}
                    @else
                        Tingkat Desa
                    @endif
                </div>
                <div class="text-xs text-gray-500">
                    @if($tahapDesaSelesai)
                        Disetujui & Terverifikasi Desa
                    @elseif($isDitolak && $record->id_verifikator_desa)
                        Ditolak oleh Desa
                    @else
                        Menunggu pengesahan akhir Desa
                    @endif
                </div>
                @if(filled($record->catatan_desa))
                    <div class="mt-2 rounded bg-gray-50 px-2.5 py-1.5 text-xs text-gray-700 border border-gray-200">
                        <span class="font-medium text-gray-500">Catatan Desa:</span> {{ $record->catatan_desa }}
                    </div>
                @endif
            </div>
        </div>
    </div>

    {{-- Ringkasan Kalimat Perubahan --}}
    <div class="rounded-xl border border-gray-200 bg-white p-4">
        <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-2">
            Rincian Pengajuan / Perubahan
        </div>
        <ul class="space-y-1.5 list-disc list-inside text-gray-900 font-medium">
            @foreach($kalimatList as $kal)
                <li>{{ $kal }}</li>
            @endforeach
        </ul>

        @if(is_array($dataPindah))
            <div class="mt-3 pt-3 border-t border-gray-100 grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                <div>
                    <span class="text-gray-500 block">Jenis Mutasi:</span>
                    <span class="font-semibold text-gray-900">
                        {{ ($dataPindah['jenis_mutasi'] ?? '') === 'keluarga' ? 'Pindah Satu Keluarga (KK)' : 'Pindah Anggota Keluarga' }}
                    </span>
                </div>
                <div>
                    <span class="text-gray-500 block">Tujuan Pindah:</span>
                    <span class="font-semibold text-gray-900">
                        {{ $record->wilayahTujuan?->alamatLengkap() ?? ($dataPindah['alamat_tujuan'] ?? 'Pindah Keluar Desa') }}
                    </span>
                </div>
            </div>
        @endif
    </div>

    {{-- Tabel Perbandingan Sebelum vs Sesudah --}}
    @if(!empty($perubahan))
        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <div class="px-4 py-2.5 bg-gray-50 border-b border-gray-200 text-[11px] font-bold uppercase tracking-wider text-gray-500">
                Perbandingan Nilai Sebelum & Sesudah
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 text-gray-500 bg-gray-50/50">
                            <th class="py-2 px-4 font-semibold">Kolom Data</th>
                            <th class="py-2 px-4 font-semibold">Versi Sebelum</th>
                            <th class="py-2 px-4 font-semibold">Versi Sesudah (Diajukan)</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach($perubahan as $diff)
                            <tr>
                                <td class="py-2.5 px-4 font-medium text-gray-900">{{ $diff['label'] ?? '—' }}</td>
                                <td class="py-2.5 px-4 text-gray-600">{{ $diff['sebelum'] ?? '—' }}</td>
                                <td class="py-2.5 px-4 font-semibold text-teal-800">{{ $diff['sesudah'] ?? '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @elseif(!empty($sesudah))
        <div class="rounded-xl border border-gray-200 bg-white p-4">
            <div class="text-[11px] font-bold uppercase tracking-wider text-gray-500 mb-3">
                Ringkasan Data yang Diajukan
            </div>
            <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2.5 text-xs">
                @foreach($sesudah as $label => $val)
                    <div class="border-b border-gray-100 pb-1.5 flex justify-between gap-2">
                        <dt class="text-gray-500">{{ $label }}</dt>
                        <dd class="font-semibold text-gray-900 text-right">{{ $val }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif
</div>

