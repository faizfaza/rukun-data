<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Rekapitulasi Warga - {{ $wilayah->nama }}</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            color: #1a1a1a;
        }
        body {
            background-color: #f4f6f8;
            padding: 24px;
        }
        .container {
            max-width: 1100px;
            margin: 0 auto;
            background: #fff;
            padding: 32px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .action-bar {
            max-width: 1100px;
            margin: 0 auto 16px auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            cursor: pointer;
            border: none;
        }
        .btn-primary { background-color: #0f766e; color: #fff; }
        .btn-primary:hover { background-color: #115e59; }
        .btn-secondary { background-color: #e5e7eb; color: #374151; }
        .btn-secondary:hover { background-color: #d1d5db; }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 18px;
            font-weight: bold;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 15px;
            font-weight: 600;
            margin-top: 4px;
        }
        .header p {
            font-size: 12px;
            color: #4b5563;
        }
        .stats-boxes {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 12px;
            margin-bottom: 24px;
        }
        .stat-card {
            border: 1px solid #e5e7eb;
            background: #f9fafb;
            padding: 12px;
            border-radius: 6px;
            text-align: center;
        }
        .stat-card .num {
            font-size: 20px;
            font-weight: bold;
            color: #0f766e;
        }
        .stat-card .lbl {
            font-size: 11px;
            color: #4b5563;
            text-transform: uppercase;
            font-weight: 600;
            margin-top: 2px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #9ca3af;
            padding: 6px 8px;
            text-align: left;
        }
        th {
            background-color: #f3f4f6;
            font-weight: bold;
            text-align: center;
        }
        .text-center { text-align: center; }
        .badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
        }
        .badge-success { background: #dcfce7; color: #166534; }
        .badge-warning { background: #fef3c7; color: #92400e; }
        .badge-danger { background: #fee2e2; color: #991b1b; }
        .signature-grid {
            display: flex;
            justify-content: flex-end;
            margin-top: 36px;
            text-align: center;
            font-size: 12px;
        }
        .signature-box {
            width: 250px;
        }
        .signature-space {
            height: 60px;
        }
        @media print {
            body { background: none; padding: 0; }
            .action-bar { display: none; }
            .container { border: none; box-shadow: none; padding: 0; max-width: 100%; }
            th { background-color: #eee !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <a href="javascript:history.back()" class="btn btn-secondary">← Kembali</a>
        <button onclick="window.print()" class="btn btn-primary">🖨️ Cetak / Print Rekapitulasi</button>
    </div>

    <div class="container">
        <div class="header">
            <h1>DAFTAR REKAPITULASI DATA PENDUDUK / WARGA</h1>
            <h2>{{ strtoupper($wilayah->nama) }} ({{ strtoupper($wilayah->level) }})</h2>
            <p>{{ $wilayah->alamatLengkap() }}</p>
        </div>

        <div class="stats-boxes">
            <div class="stat-card">
                <div class="num">{{ $statistik['total_keluarga'] }}</div>
                <div class="lbl">Total Keluarga (KK)</div>
            </div>
            <div class="stat-card">
                <div class="num">{{ $statistik['total_warga'] }}</div>
                <div class="lbl">Total Jiwa</div>
            </div>
            <div class="stat-card">
                <div class="num">{{ $statistik['total_menetap'] }}</div>
                <div class="lbl">Warga Menetap (Aktif)</div>
            </div>
            <div class="stat-card">
                <div class="num">{{ $statistik['total_pindah'] }}</div>
                <div class="lbl">Warga Pindah</div>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th width="3%">No</th>
                    <th width="14%">No KK</th>
                    <th>Kepala Keluarga</th>
                    <th>Alamat Domisili</th>
                    <th>Alamat KTP</th>
                    <th width="7%">Jml Jiwa</th>
                    <th width="20%">Rincian Anggota & Status</th>
                    <th width="8%">Kunci Data</th>
                </tr>
            </thead>
            <tbody>
                @forelse($keluargaList as $idx => $k)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td class="text-center"><strong>{{ $k->no_kk }}</strong></td>
                        <td><strong>{{ $k->kepala?->nama_lengkap ?? '—' }}</strong></td>
                        <td>{{ $k->alamat }}</td>
                        <td>{{ $k->alamat_ktp ?: $k->alamat }}</td>
                        <td class="text-center">{{ $k->anggota->count() }}</td>
                        <td>
                            @foreach($k->anggota as $ag)
                                <div style="margin-bottom: 2px;">
                                    • {{ $ag->nama_lengkap }}
                                    @if($ag->status_kependudukan === 'pindah')
                                        <span class="badge badge-warning">Pindah</span>
                                    @elseif($ag->status_kependudukan === 'meninggal')
                                        <span class="badge badge-danger">Wafat</span>
                                    @endif
                                </div>
                            @endforeach
                        </td>
                        <td class="text-center">
                            @if($k->isLocked())
                                <span class="badge badge-danger">🔒 Kunci</span>
                            @else
                                <span class="badge badge-success">Bebas</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center">Belum ada data warga di wilayah ini.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        <div class="signature-grid">
            <div class="signature-box">
                <p>{{ $wilayah->nama }}, {{ now()->translatedFormat('d F Y') }}</p>
                <p>Pengurus {{ strtoupper($wilayah->level) }}</p>
                <div class="signature-space"></div>
                <p><strong>( .................................................... )</strong></p>
            </div>
        </div>
    </div>

</body>
</html>

