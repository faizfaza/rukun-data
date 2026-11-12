@php
    /** @var \App\Models\Keluarga|null $keluarga */
    $keluarga = $getRecord() ?? ($record ?? null);
    $logs = $keluarga ? $keluarga->riwayatAktivitas()->get() : collect();
@endphp

<div x-data="{ expandedLog: null }" class="space-y-3 text-sm text-gray-800">
    @if ($logs->isEmpty())
        <div class="rounded-lg border border-dashed border-gray-200 bg-gray-50/60 p-6 text-center text-sm text-gray-500">
            <x-filament::icon icon="heroicon-o-clock" class="mx-auto h-7 w-7 text-gray-400 mb-1" />
            <p class="font-medium text-gray-700">Belum Ada Riwayat Aktivitas</p>
            <p class="text-xs text-gray-500 mt-0.5">Seluruh perubahan data keluarga, status anggota, penambahan, dan penghapusan akan tercatat otomatis di sini.</p>
        </div>
    @else
        <div class="overflow-hidden rounded-xl border border-gray-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="w-full text-left border-collapse text-xs">
                    <thead>
                        <tr class="border-b border-gray-200 bg-gray-50/80 text-gray-600 font-semibold uppercase tracking-wider">
                            <th class="py-2.5 px-3.5 w-36">Waktu</th>
                            <th class="py-2.5 px-3.5 w-36">Pengguna</th>
                            <th class="py-2.5 px-3 w-28">Aksi</th>
                            <th class="py-2.5 px-3 w-28">Objek</th>
                            <th class="py-2.5 px-3.5">Rincian Perubahan</th>
                            <th class="py-2.5 px-3 text-center w-20">Rincian</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100">
                        @foreach ($logs as $log)
                            @php
                                $info = \App\Support\AuditFormatter::describe($log);
                                $perubahanList = $info['perubahan'] ?? [];
                                $hasDiff = ! empty($perubahanList);

                                $labelAksi = match ($log->aksi) {
                                    'login' => 'Login',
                                    'created' => 'Tambah',
                                    'updated' => 'Ubah',
                                    'deleted' => 'Hapus / Arsip',
                                    'restored' => 'Pulihkan',
                                    default => ucfirst((string) $log->aksi),
                                };

                                $warnaDot = match ($log->aksi) {
                                    'created' => 'bg-emerald-600',
                                    'updated' => 'bg-sky-600',
                                    'deleted' => 'bg-rose-600',
                                    'restored' => 'bg-amber-600',
                                    default => 'bg-gray-600',
                                };

                                $labelModul = match ($log->nama_tabel) {
                                    'keluarga' => 'Kartu Keluarga',
                                    'anggota' => 'Anggota Warga',
                                    'aset_keluarga' => 'Aset',
                                    'ekonomi_keluarga' => 'Ekonomi',
                                    default => ucfirst((string) $log->nama_tabel),
                                };
                            @endphp

                            <tr class="hover:bg-gray-50/70 transition">
                                <td class="py-2.5 px-3.5 font-medium text-gray-900 whitespace-nowrap">
                                    {{ $log->waktu?->format('d M Y H:i:s') ?? '—' }}
                                </td>
                                <td class="py-2.5 px-3.5 text-gray-700 whitespace-nowrap">
                                    <span class="font-medium text-gray-900">{{ $log->pengguna?->name ?? 'Sistem / Tamu' }}</span>
                                    @if ($log->pengguna?->role)
                                        <span class="block text-[10px] text-gray-500">{{ $log->pengguna->role->label() }}</span>
                                    @endif
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="inline-flex items-center gap-1.5 font-semibold text-gray-800">
                                        <span class="h-2 w-2 rounded-full {{ $warnaDot }}"></span>
                                        {{ $labelAksi }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3 whitespace-nowrap">
                                    <span class="rounded bg-gray-100 px-2 py-0.5 text-[11px] font-medium text-gray-700">
                                        {{ $labelModul }}
                                    </span>
                                </td>
                                <td class="py-2.5 px-3.5 text-gray-800 leading-relaxed text-[13px]">
                                    {{ $info['ringkasan'] ?? '—' }}
                                </td>
                                <td class="py-2.5 px-3 text-center whitespace-nowrap">
                                    @if ($hasDiff)
                                        <button
                                            type="button"
                                            @click="expandedLog = (expandedLog === {{ $log->id }} ? null : {{ $log->id }})"
                                            class="inline-flex items-center gap-1 rounded border border-gray-300 bg-white px-2 py-0.5 text-[11px] font-medium text-teal-700 hover:bg-teal-50 hover:border-teal-300 transition"
                                        >
                                            <span x-text="expandedLog === {{ $log->id }} ? 'Tutup' : 'Lihat'"></span>
                                            <x-filament::icon icon="heroicon-m-chevron-down" class="h-3 w-3 transition" ::class="expandedLog === {{ $log->id }} ? 'rotate-180' : ''" />
                                        </button>
                                    @else
                                        <span class="text-gray-300">—</span>
                                    @endif
                                </td>
                            </tr>

                            {{-- Row Inline Diff (Expandable) --}}
                            @if ($hasDiff)
                                <tr x-show="expandedLog === {{ $log->id }}" x-cloak class="bg-teal-50/30">
                                    <td colspan="6" class="p-3 border-t border-teal-100">
                                        <div class="rounded-lg border border-teal-200/80 bg-white p-3">
                                            <div class="mb-2 flex items-center justify-between">
                                                <span class="text-xs font-bold uppercase tracking-wider text-teal-950">
                                                    Perubahan Nilai pada Aksi Ini ({{ count($perubahanList) }} Bagian)
                                                </span>
                                                <button
                                                    type="button"
                                                    @click="expandedLog = null"
                                                    class="text-[11px] text-gray-400 hover:text-gray-600"
                                                >
                                                    Tutup ✕
                                                </button>
                                            </div>

                                            <div class="overflow-x-auto">
                                                <table class="w-full text-left text-xs border-collapse">
                                                    <thead>
                                                        <tr class="border-b border-gray-100 text-gray-500">
                                                            <th class="py-1.5 px-3 font-semibold w-1/3">Bagian / Kolom</th>
                                                            <th class="py-1.5 px-3 font-semibold w-1/3">Sebelum Diubah</th>
                                                            <th class="py-1.5 px-3 font-semibold w-1/3">Sesudah Diubah</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody class="divide-y divide-gray-100 text-[12px]">
                                                        @foreach ($perubahanList as $item)
                                                            <tr class="hover:bg-gray-50/50">
                                                                <td class="py-1.5 px-3 font-medium text-gray-900">{{ $item['label'] }}</td>
                                                                <td class="py-1.5 px-3 text-gray-500 font-mono">{{ $item['sebelum'] }}</td>
                                                                <td class="py-1.5 px-3 font-semibold text-teal-800 font-mono">{{ $item['sesudah'] }}</td>
                                                            </tr>
                                                        @endforeach
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                            @endif
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>

