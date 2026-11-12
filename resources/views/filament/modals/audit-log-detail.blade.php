@php
    /** @var \App\Models\AuditLog $record */
    $info = \App\Support\AuditFormatter::describe($record);
    $kalimatList = $info['kalimat'] ?? [];
    $perubahanList = $info['perubahan'] ?? [];
    $sebelum = $info['sebelum'] ?? [];
    $sesudah = $info['sesudah'] ?? [];
    $shortcuts = $info['shortcuts'] ?? [];
    $konteks = $info['konteks'] ?? [];
    $statusArsip = $info['status_arsip'] ?? 'Data Aktif';
    $labelBerubah = array_column($perubahanList, 'label');
    $adaArsipSebelum = ! empty($sebelum) && ! empty($perubahanList);

    $labelAksi = match ($record->aksi) {
        'login' => 'Login (Masuk)',
        'created' => 'Tambah (Create)',
        'updated' => 'Ubah (Update)',
        'deleted' => 'Hapus / Arsip',
        'restored' => 'Pulihkan',
        default => ucfirst((string) $record->aksi),
    };

    $warnaAksiDot = match ($record->aksi) {
        'created' => 'bg-emerald-600',
        'updated' => 'bg-sky-600',
        'deleted' => 'bg-rose-600',
        'restored' => 'bg-amber-600',
        default => 'bg-teal-600',
    };
@endphp

<div x-data="{ tabArsip: 'sesudah' }" class="space-y-4 text-sm text-gray-800">
    {{-- Header Meta Ringkas & Bersih --}}
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 rounded-lg border border-gray-200 bg-gray-50/70 p-3.5 text-xs">
        <div>
            <div class="font-medium text-gray-500">WAKTU KEJADIAN</div>
            <div class="mt-0.5 text-sm font-semibold text-gray-900">{{ $record->waktu?->format('d M Y, H:i:s') ?? '—' }}</div>
        </div>
        <div>
            <div class="font-medium text-gray-500">PENGGUNA</div>
            <div class="mt-0.5 text-sm font-semibold text-gray-900">
                {{ $record->pengguna?->name ?? 'Sistem / Tamu' }}
                @if ($record->pengguna?->role)
                    <span class="text-xs font-normal text-gray-500">({{ $record->pengguna->role->label() }})</span>
                @endif
            </div>
            <div class="text-[11px] text-gray-500">{{ $record->pengguna?->wilayah?->nama ? 'Wilayah: '.$record->pengguna->wilayah->nama : 'Seluruh Wilayah' }}</div>
        </div>
        <div>
            <div class="font-medium text-gray-500">AKSI & STATUS</div>
            <div class="mt-1 flex items-center gap-2">
                <span class="flex items-center gap-1.5 font-semibold text-gray-900">
                    <span class="h-2 w-2 rounded-full {{ $warnaAksiDot }}"></span>
                    {{ $labelAksi }}
                </span>
                <span class="text-gray-400">·</span>
                <span class="text-gray-600">{{ $statusArsip }}</span>
            </div>
        </div>
    </div>

    {{-- Rincian Aktivitas (Clean Text Card) --}}
    <div class="rounded-lg border border-gray-200 border-l-4 border-l-teal-600 bg-white p-4">
        <div class="flex items-center justify-between gap-2 mb-2">
            <h4 class="text-xs font-bold uppercase tracking-wider text-teal-900">Rincian Aktivitas</h4>
            @if ($adaArsipSebelum)
                <button
                    type="button"
                    @click="tabArsip = 'sebelum'; $nextTick(() => $refs.kotakArsip?.scrollIntoView({ behavior: 'smooth', block: 'start' }))"
                    class="text-xs font-semibold text-teal-700 hover:text-teal-900 hover:underline"
                >
                    Lihat Arsip Versi Sebelum Diubah →
                </button>
            @endif
        </div>

        <div class="space-y-1.5 text-[14px] leading-relaxed text-gray-800">
            @foreach ($kalimatList as $kalimat)
                <p>{{ $kalimat }}</p>
            @endforeach
        </div>
    </div>

    {{-- Tabel Perbandingan Nilai (Hanya jika ada kolom yang berubah) --}}
    @if (! empty($perubahanList))
        <div class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <div class="border-b border-gray-200 bg-gray-50 px-4 py-2 text-xs font-bold uppercase tracking-wider text-gray-600">
                Perubahan Nilai ({{ count($perubahanList) }} Bagian)
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-100 text-gray-500">
                            <th class="py-2 px-4 font-semibold w-1/3">Bagian / Kolom</th>
                            <th class="py-2 px-4 font-semibold w-1/3">Sebelum Diubah</th>
                            <th class="py-2 px-4 font-semibold w-1/3">Sesudah Diubah</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 text-sm">
                        @foreach ($perubahanList as $item)
                            <tr class="hover:bg-gray-50/50">
                                <td class="py-2.5 px-4 font-medium text-gray-900">{{ $item['label'] }}</td>
                                <td class="py-2.5 px-4 text-gray-500 font-mono text-xs">{{ $item['sebelum'] }}</td>
                                <td class="py-2.5 px-4 font-semibold text-teal-800 font-mono text-xs">{{ $item['sesudah'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif

    {{-- Rekaman Data / Arsip (Clean Table / Two-Column Key-Value) --}}
    @if ($adaArsipSebelum || ! empty($sesudah))
        <div x-ref="kotakArsip" class="overflow-hidden rounded-lg border border-gray-200 bg-white">
            <div class="flex flex-wrap items-center justify-between gap-2 border-b border-gray-200 bg-gray-50 px-4 py-2.5">
                <div class="text-xs font-bold uppercase tracking-wider text-gray-700">
                    Rekaman Data Lengkap
                </div>

                @if ($adaArsipSebelum)
                    <div class="flex items-center gap-1 rounded bg-gray-200/70 p-0.5 text-xs">
                        <button
                            type="button"
                            @click="tabArsip = 'sebelum'"
                            :class="tabArsip === 'sebelum' ? 'bg-white text-gray-900 font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                            class="rounded px-2.5 py-1 transition"
                        >
                            Versi Sebelum Diubah
                        </button>
                        <button
                            type="button"
                            @click="tabArsip = 'sesudah'"
                            :class="tabArsip === 'sesudah' ? 'bg-white text-gray-900 font-bold shadow-xs' : 'text-gray-600 hover:text-gray-900'"
                            class="rounded px-2.5 py-1 transition"
                        >
                            Versi Sesudah Diubah
                        </button>
                    </div>
                @endif
            </div>

            {{-- Versi Sebelum Diubah --}}
            @if ($adaArsipSebelum)
                <div x-show="tabArsip === 'sebelum'" class="p-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-xs">
                        @foreach ($sebelum as $k => $v)
                            @php $berubah = in_array($k, $labelBerubah, true); @endphp
                            <div class="flex justify-between items-baseline py-1 border-b border-gray-100 {{ $berubah ? 'bg-amber-50/70 px-2 rounded -mx-2' : '' }}">
                                <dt class="text-gray-500 font-medium">
                                    {{ $k }}
                                    @if ($berubah)
                                        <span class="text-[10px] font-bold text-amber-700">(Diubah)</span>
                                    @endif
                                </dt>
                                <dd class="font-semibold {{ $berubah ? 'text-amber-900' : 'text-gray-900' }} text-right">{{ $v }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif

            {{-- Versi Sesudah Diubah --}}
            @if (! empty($sesudah))
                <div @if ($adaArsipSebelum) x-show="tabArsip === 'sesudah'" @endif class="p-4">
                    <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-2 text-xs">
                        @foreach ($sesudah as $k => $v)
                            @php $berubah = in_array($k, $labelBerubah, true); @endphp
                            <div class="flex justify-between items-baseline py-1 border-b border-gray-100 {{ $berubah ? 'bg-teal-50/70 px-2 rounded -mx-2' : '' }}">
                                <dt class="text-gray-500 font-medium">
                                    {{ $k }}
                                    @if ($berubah)
                                        <span class="text-[10px] font-bold text-teal-700">(Diubah)</span>
                                    @endif
                                </dt>
                                <dd class="font-semibold {{ $berubah ? 'text-teal-900' : 'text-gray-900' }} text-right">{{ $v }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </div>
            @endif
        </div>
    @endif

    {{-- Pintasan Sederhana (Clean Links) --}}
    @if (! empty($shortcuts))
        <div class="flex flex-wrap items-center gap-2 pt-1 border-t border-gray-100">
            <span class="text-xs font-semibold text-gray-500 mr-1">Pintasan:</span>
            @foreach ($shortcuts as $link)
                <a
                    href="{{ $link['url'] }}"
                    class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2.5 py-1 text-xs font-medium text-gray-700 hover:bg-gray-50 hover:text-gray-900 transition"
                >
                    <x-filament::icon :icon="$link['ikon']" class="h-3.5 w-3.5 text-gray-500" />
                    <span>{{ $link['label'] }}</span>
                    <span class="text-gray-400">→</span>
                </a>
            @endforeach
        </div>
    @endif
</div>
