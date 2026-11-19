@php
    /** @var \App\Models\ArsipSnapshotDesa $record */
    $user = auth()->user();
    $snap = $record->snapshotData;
    $stat = $record->ringkasan_statistik ?? [];
    $keluargaList = $snap['keluarga_list'] ?? [];
    $perDesaMap = $stat['per_desa'] ?? [];
    $bolehLihatPenuh = (bool) $user?->bolehLihatDataPenuh();

    // Jika yang login adalah Kepala Desa, langsung arahkan ke desanya sendiri
    $hanyaDesaId = ($user?->isAdminDesa() && $user->id_wilayah) ? (int) $user->id_wilayah : null;

    // Susun daftar desa di dalam Batch ini
    $daftarDesaBatch = [];
    if (!empty($perDesaMap)) {
        foreach ($perDesaMap as $dId => $dInfo) {
            if ($hanyaDesaId !== null && (int) $dId !== $hanyaDesaId) {
                continue;
            }
            $daftarDesaBatch[] = [
                'id_desa' => (int) $dId,
                'nama_desa' => $dInfo['nama_desa'] ?? 'Desa #'.$dId,
                'kecamatan_kab' => $dInfo['kecamatan_kab'] ?? '—',
                'jumlah_keluarga' => (int) ($dInfo['jumlah_keluarga'] ?? 0),
                'jumlah_warga' => (int) ($dInfo['jumlah_warga'] ?? 0),
                'jumlah_menetap' => (int) ($dInfo['jumlah_menetap'] ?? 0),
                'jumlah_pindah' => (int) ($dInfo['jumlah_pindah'] ?? 0),
            ];
        }
    } else {
        $fallbackId = (int) $record->id_wilayah_desa;
        $daftarDesaBatch[] = [
            'id_desa' => $fallbackId,
            'nama_desa' => $record->wilayahDesa?->nama ?? ($snap['desa'] ?? 'Desa'),
            'kecamatan_kab' => $record->wilayahDesa?->rantaiIndukNama() ?? '—',
            'jumlah_keluarga' => (int) $record->jumlah_keluarga,
            'jumlah_warga' => (int) $record->jumlah_warga,
            'jumlah_menetap' => (int) $record->jumlah_menetap,
            'jumlah_pindah' => (int) $record->jumlah_pindah,
        ];
    }

    $initialDesaId = count($daftarDesaBatch) === 1 ? $daftarDesaBatch[0]['id_desa'] : null;
@endphp

<div
    x-data="{
        selectedDesaId: {{ $initialDesaId !== null ? $initialDesaId : 'null' }},
        selectedDesaNama: {{ $initialDesaId !== null ? "'".addslashes($daftarDesaBatch[0]['nama_desa'])."'" : "''" }},
        selectedKk: null,
        cariDesa: '',
        cariKk: ''
    }"
    class="space-y-4 text-sm text-gray-800"
>
    {{-- Breadcrumb Hierarki: Batch -> Desa -> Keluarga --}}
    <div class="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-gray-200 bg-gray-50 px-4 py-2.5 text-xs">
        <div class="flex flex-wrap items-center gap-1.5 font-medium">
            <button
                type="button"
                @click="selectedDesaId = {{ $hanyaDesaId !== null ? $hanyaDesaId : 'null' }}; selectedKk = null"
                class="text-teal-700 hover:underline font-bold cursor-pointer"
            >
                {{ $record->kode_snapshot }} ({{ $record->created_at?->format('d M Y H:i') }})
            </button>

            <template x-if="selectedDesaId !== null">
                <span class="inline-flex items-center gap-1.5">
                    <span class="text-gray-400">/</span>
                    <button
                        type="button"
                        @click="selectedKk = null"
                        class="text-teal-700 hover:underline font-bold cursor-pointer"
                        x-text="'Desa ' + selectedDesaNama"
                    ></button>
                </span>
            </template>

            <template x-if="selectedKk !== null">
                <span class="inline-flex items-center gap-1.5">
                    <span class="text-gray-400">/</span>
                    <span class="font-bold text-gray-900">Rincian Kartu Keluarga</span>
                </span>
            </template>
        </div>

        <div class="text-gray-500">
            Interval Batch: tiap <strong>{{ $record->interval_jam }} jam</strong>
        </div>
    </div>

    {{-- LEVEL 1 (Di dalam Batch): DAFTAR DESA YANG TEREKAM PADA BATCH INI --}}
    <div x-show="selectedDesaId === null" class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="text-xs text-gray-600">
                Pilih <strong>Desa / Kelurahan</strong> untuk melihat data keluarga dan perubahan yang terekam pada Batch ini:
            </div>
            <div class="w-full sm:w-64">
                <input
                    type="search"
                    x-model="cariDesa"
                    placeholder="Cari nama desa..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-900 focus:border-teal-600 focus:outline-none"
                />
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-gray-600">
                        <th class="py-2.5 px-4 font-semibold">Desa / Kelurahan</th>
                        <th class="py-2.5 px-4 font-semibold">Kecamatan & Kabupaten</th>
                        <th class="py-2.5 px-4 font-semibold">Keluarga Terekam</th>
                        <th class="py-2.5 px-4 font-semibold">Warga Terekam</th>
                        <th class="py-2.5 px-4 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($daftarDesaBatch as $dItem)
                        <tr
                            x-show="!cariDesa || @js(strtolower($dItem['nama_desa'].' '.$dItem['kecamatan_kab'])).includes(cariDesa.toLowerCase())"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <td class="py-3 px-4 font-bold text-gray-950">
                                Desa {{ $dItem['nama_desa'] }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ $dItem['kecamatan_kab'] }}
                            </td>
                            <td class="py-3 px-4 font-semibold text-gray-800">
                                {{ $dItem['jumlah_keluarga'] }} Keluarga (KK)
                            </td>
                            <td class="py-3 px-4 text-gray-700">
                                {{ $dItem['jumlah_warga'] }} Warga
                                <span class="text-gray-400">({{ $dItem['jumlah_menetap'] }} menetap · {{ $dItem['jumlah_pindah'] }} pindah)</span>
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button
                                    type="button"
                                    @click="selectedDesaId = {{ $dItem['id_desa'] }}; selectedDesaNama = '{{ addslashes($dItem['nama_desa']) }}'; selectedKk = null"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-teal-800 hover:bg-teal-50 hover:border-teal-300 transition cursor-pointer"
                                >
                                    Buka Data Desa →
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- LEVEL 2 (Di dalam Desa): DAFTAR KELUARGA & PERUBAHAN DI DESA TERSEBUT --}}
    <div x-show="selectedDesaId !== null && selectedKk === null" style="display: none;" class="space-y-3">
        <div class="flex flex-wrap items-center justify-between gap-2">
            <div class="flex items-center gap-2">
                @if($hanyaDesaId === null && count($daftarDesaBatch) > 1)
                    <button
                        type="button"
                        @click="selectedDesaId = null; selectedKk = null"
                        class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 cursor-pointer"
                    >
                        ← Semua Desa di Batch Ini
                    </button>
                @endif
                <span class="text-xs text-gray-600">
                    Daftar Kartu Keluarga di <strong class="text-gray-900" x-text="'Desa ' + selectedDesaNama"></strong> pada saat Batch ini direkam:
                </span>
            </div>

            <div class="w-full sm:w-64">
                <input
                    type="search"
                    x-model="cariKk"
                    placeholder="Cari Kepala KK, nama warga, RT..."
                    class="w-full rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs text-gray-900 focus:border-teal-600 focus:outline-none"
                />
            </div>
        </div>

        <div class="rounded-xl border border-gray-200 bg-white overflow-hidden">
            <table class="w-full text-left border-collapse text-xs">
                <thead>
                    <tr class="border-b border-gray-200 bg-gray-50 text-gray-600">
                        <th class="py-2.5 px-4 font-semibold">No. KK</th>
                        <th class="py-2.5 px-4 font-semibold">Kepala Keluarga</th>
                        <th class="py-2.5 px-4 font-semibold">RT / RW</th>
                        <th class="py-2.5 px-4 font-semibold">Jumlah Anggota</th>
                        <th class="py-2.5 px-4 font-semibold">Ekonomi & Aset Saat Itu</th>
                        <th class="py-2.5 px-4 text-right font-semibold">Aksi</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                    @foreach($keluargaList as $idx => $kk)
                        @php
                            $kkDesaId = (int) ($kk['id_wilayah_desa'] ?? $record->id_wilayah_desa);
                            $searchBlob = strtolower(
                                ($kk['kepala_keluarga'] ?? '') . ' ' .
                                ($kk['no_kk_masked'] ?? '') . ' ' .
                                ($kk['rt'] ?? '') . ' ' .
                                ($kk['rw'] ?? '') . ' ' .
                                collect($kk['anggota'] ?? [])->pluck('nama_lengkap')->implode(' ')
                            );
                        @endphp
                        <tr
                            x-show="selectedDesaId === {{ $kkDesaId }} && (!cariKk || @js($searchBlob).includes(cariKk.toLowerCase()))"
                            class="hover:bg-gray-50/80 transition"
                        >
                            <td class="py-3 px-4 font-mono text-gray-700">
                                {{ $bolehLihatPenuh && !empty($kk['no_kk']) ? $kk['no_kk'] : ($kk['no_kk_masked'] ?? '—') }}
                            </td>
                            <td class="py-3 px-4 font-bold text-gray-950">
                                {{ $kk['kepala_keluarga'] ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-gray-600">
                                {{ $kk['rt'] ?? '—' }} / {{ $kk['rw'] ?? '—' }}
                            </td>
                            <td class="py-3 px-4 text-gray-700">
                                {{ count($kk['anggota'] ?? []) }} jiwa
                            </td>
                            <td class="py-3 px-4 text-gray-700">
                                <span class="font-semibold text-teal-800">{{ $kk['kategori_ekonomi'] ?? '—' }}</span>
                                @if(!empty($kk['aset']))
                                    <span class="text-gray-400">·</span>
                                    <span class="text-gray-600">{{ implode(', ', $kk['aset']) }}</span>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-right">
                                <button
                                    type="button"
                                    @click="selectedKk = {{ $idx }}"
                                    class="inline-flex items-center gap-1 rounded-lg border border-gray-300 bg-white px-2.5 py-1 text-xs font-semibold text-teal-800 hover:bg-teal-50 hover:border-teal-300 transition cursor-pointer"
                                >
                                    Lihat Rincian KK →
                                </button>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- LEVEL 3 (Di dalam KK): RINCIAN PENUH KELUARGA & ANGGOTA PADA SAAT BATCH TERSEBUT --}}
    @foreach($keluargaList as $idx => $kk)
        <div x-show="selectedKk === {{ $idx }}" style="display: none;" class="space-y-4 max-h-[34rem] overflow-y-auto pr-1">
            <div class="flex items-center justify-between">
                <button
                    type="button"
                    @click="selectedKk = null"
                    class="inline-flex items-center gap-1.5 rounded-lg border border-gray-300 bg-white px-3 py-1.5 text-xs font-semibold text-gray-700 hover:bg-gray-50 cursor-pointer"
                >
                    ← Kembali ke Daftar KK Desa {{ $kk['desa'] ?? '' }}
                </button>
                <div class="text-xs text-gray-500">
                    Rekaman KK <strong>{{ $kk['kepala_keluarga'] ?? '—' }}</strong> pada <strong>{{ $record->created_at?->format('d M Y H:i') }}</strong>
                </div>
            </div>

            {{-- Kotak 1: Data Keluarga --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4">
                <div class="text-sm font-bold text-gray-950 mb-3 pb-2 border-b border-gray-100">
                    Data Keluarga
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4 text-xs">
                    <div>
                        <span class="text-gray-500 block">No KK</span>
                        <span class="font-mono font-bold text-gray-950 text-sm">
                            {{ $bolehLihatPenuh && !empty($kk['no_kk']) ? $kk['no_kk'] : ($kk['no_kk_masked'] ?? '—') }}
                        </span>
                    </div>
                    <div>
                        <span class="text-gray-500 block">RT & Wilayah</span>
                        <span class="font-semibold text-gray-900">{{ $kk['wilayah_lengkap'] ?? (($kk['rt'] ?? '').', '.($kk['rw'] ?? '')) }}</span>
                    </div>
                    <div>
                        <span class="text-gray-500 block">Kode Pos</span>
                        <span class="font-semibold text-gray-900">{{ $kk['kode_pos'] ?? '—' }}</span>
                    </div>
                    <div class="sm:col-span-3">
                        <span class="text-gray-500 block">Alamat Domisili</span>
                        <span class="font-semibold text-gray-900">{{ $kk['alamat'] ?? '—' }}</span>
                    </div>
                    <div class="sm:col-span-3">
                        <span class="text-gray-500 block">Alamat KTP</span>
                        <span class="font-semibold text-gray-900">{{ $kk['alamat_ktp'] ?? ($kk['alamat'] ?? '—') }}</span>
                    </div>
                </div>
            </div>

            {{-- Kotak 2: Ekonomi & Aset Berdampingan --}}
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="text-sm font-bold text-gray-950 mb-2 pb-1.5 border-b border-gray-100">
                        Ekonomi
                    </div>
                    <div class="grid grid-cols-2 gap-2 text-xs">
                        <div>
                            <span class="text-gray-500 block">Tahun</span>
                            <span class="font-semibold text-gray-900">{{ $kk['tahun_ekonomi'] ?? '—' }}</span>
                        </div>
                        <div>
                            <span class="text-gray-500 block">Kategori</span>
                            <span class="font-semibold text-gray-900">{{ $kk['kategori_ekonomi'] ?? '—' }}</span>
                        </div>
                    </div>
                </div>

                <div class="rounded-xl border border-gray-200 bg-white p-4">
                    <div class="text-sm font-bold text-gray-950 mb-2 pb-1.5 border-b border-gray-100">
                        Aset & Fasilitas
                    </div>
                    @if(!empty($kk['aset_lengkap']))
                        <div class="space-y-1.5 text-xs">
                            @foreach($kk['aset_lengkap'] as $asetItem)
                                <div class="flex justify-between border-b border-gray-50 pb-1">
                                    <span class="font-semibold text-gray-900">{{ $asetItem['jenis_aset'] ?? '—' }}</span>
                                    <span class="text-gray-600">{{ $asetItem['keterangan'] ?? '—' }}</span>
                                </div>
                            @endforeach
                        </div>
                    @elseif(!empty($kk['aset']))
                        <div class="text-xs font-semibold text-gray-900">{{ implode(', ', $kk['aset']) }}</div>
                    @else
                        <div class="text-xs text-gray-500">Tidak ada aset tercatat</div>
                    @endif
                </div>
            </div>

            {{-- Kotak 3: Anggota Keluarga --}}
            <div class="rounded-xl border border-gray-200 bg-white p-4 space-y-3">
                <div class="text-sm font-bold text-gray-950 pb-2 border-b border-gray-100">
                    Anggota Keluarga ({{ count($kk['anggota'] ?? []) }} Jiwa)
                </div>

                @foreach($kk['anggota'] ?? [] as $warga)
                    <div class="rounded-xl border border-gray-200 p-3.5">
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div>
                                <span class="text-gray-500 block">NIK</span>
                                <span class="font-mono font-bold text-gray-900">
                                    {{ $bolehLihatPenuh && !empty($warga['nik']) ? $warga['nik'] : ($warga['nik_masked'] ?? '—') }}
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Nama Lengkap</span>
                                <span class="font-bold text-gray-950">{{ $warga['nama_lengkap'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Status Kependudukan</span>
                                <span class="inline-flex items-center rounded px-2 py-0.5 text-[11px] font-semibold {{ ($warga['status_kependudukan'] ?? 'menetap') === 'pindah' ? 'bg-amber-100 text-amber-800' : 'bg-emerald-100 text-emerald-800' }}">
                                    {{ ucfirst($warga['status_kependudukan'] ?? 'Menetap') }}
                                </span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Hubungan</span>
                                <span class="font-semibold text-gray-900">{{ $warga['hubungan'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Jenis Kelamin</span>
                                <span class="font-semibold text-gray-900">{{ ($warga['jenis_kelamin'] ?? '') === 'P' ? 'Perempuan (P)' : 'Laki-laki (L)' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Tempat, Tanggal Lahir</span>
                                <span class="font-semibold text-gray-900">{{ $warga['tempat_lahir'] ?? '—' }}, {{ $warga['tanggal_lahir'] ?? '—' }} ({{ $warga['umur'] ?? 0 }} thn)</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Agama & Status Kawin</span>
                                <span class="font-semibold text-gray-900">{{ $warga['agama'] ?? '—' }} · {{ ucfirst(str_replace('_', ' ', (string) ($warga['status_kawin'] ?? '—'))) }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Pendidikan & Pekerjaan</span>
                                <span class="font-semibold text-gray-900">{{ $warga['pendidikan'] ?? '—' }} · {{ $warga['pekerjaan'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Nama Ayah</span>
                                <span class="font-semibold text-gray-900">{{ $warga['nama_ayah'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Nama Ibu</span>
                                <span class="font-semibold text-gray-900">{{ $warga['nama_ibu'] ?? '—' }}</span>
                            </div>
                            <div>
                                <span class="text-gray-500 block">Penghasilan / Bulan</span>
                                <span class="font-semibold text-gray-900">
                                    {{ ($warga['penghasilan_bulanan'] ?? 0) > 0 ? 'Rp '.number_format((float) $warga['penghasilan_bulanan'], 0, ',', '.') : '—' }}
                                </span>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endforeach
</div>
