<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kartu Keluarga - {{ $keluarga->no_kk }}</title>
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
            max-width: 1050px;
            margin: 0 auto;
            background: #fff;
            padding: 32px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0,0,0,0.1);
        }
        .action-bar {
            max-width: 1050px;
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
        .btn-primary {
            background-color: #0f766e;
            color: #fff;
        }
        .btn-primary:hover {
            background-color: #115e59;
        }
        .btn-secondary {
            background-color: #e5e7eb;
            color: #374151;
        }
        .btn-secondary:hover {
            background-color: #d1d5db;
        }
        .header {
            text-align: center;
            border-bottom: 2px solid #000;
            padding-bottom: 12px;
            margin-bottom: 20px;
        }
        .header h1 {
            font-size: 20px;
            font-weight: bold;
            letter-spacing: 1px;
            text-transform: uppercase;
        }
        .header h2 {
            font-size: 16px;
            font-weight: bold;
            margin-top: 4px;
        }
        .header p {
            font-size: 12px;
            color: #4b5563;
            margin-top: 2px;
        }
        .meta-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 12px 32px;
            font-size: 13px;
            margin-bottom: 20px;
        }
        .meta-row {
            display: flex;
        }
        .meta-label {
            width: 170px;
            font-weight: 600;
            color: #4b5563;
        }
        .meta-colon {
            width: 16px;
        }
        .meta-value {
            flex: 1;
            font-weight: 500;
        }
        .section-title {
            font-size: 13px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 16px 0 8px 0;
            border-left: 4px solid #0f766e;
            padding-left: 8px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11px;
            margin-bottom: 16px;
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
            display: grid;
            grid-template-columns: 1fr 1fr;
            margin-top: 36px;
            text-align: center;
            font-size: 12px;
        }
        .signature-space {
            height: 60px;
        }
        @media print {
            body {
                background: none;
                padding: 0;
            }
            .action-bar {
                display: none;
            }
            .container {
                border: none;
                box-shadow: none;
                padding: 0;
                max-width: 100%;
            }
            th {
                background-color: #eee !important;
                -webkit-print-color-adjust: exact;
                print-color-adjust: exact;
            }
        }
    </style>
</head>
<body>

    <div class="action-bar">
        <a href="javascript:history.back()" class="btn btn-secondary">← Kembali</a>
        <button onclick="window.print()" class="btn btn-primary">🖨️ Cetak / Print Dokumen</button>
    </div>

    <div class="container">
        <div class="header">
            <h1>KARTU KELUARGA</h1>
            <h2>No. KK: {{ $keluarga->no_kk }}</h2>
            <p>{{ $keluarga->wilayah?->alamatLengkap() ?? 'Sistem Administrasi Rukun Data' }}</p>
        </div>

        <div class="meta-grid">
            <div>
                <div class="meta-row">
                    <span class="meta-label">Nama Kepala Keluarga</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $keluarga->kepala?->nama_lengkap ?? '—' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Alamat Domisili</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $keluarga->alamat }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Alamat KTP</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $keluarga->alamat_ktp ?: $keluarga->alamat }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">RT / Wilayah</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $keluarga->wilayah?->nama ?? '—' }}</span>
                </div>
            </div>
            <div>
                <div class="meta-row">
                    <span class="meta-label">Kode Pos</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $keluarga->kode_pos }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Kategori Ekonomi</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ $keluarga->ekonomi->first()?->kategori_ekonomi ?? 'Belum ada data' }}</span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Status Kunci Data</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">
                        @if($keluarga->isLocked())
                            <span class="badge badge-danger">Terkunci (Superadmin)</span>
                        @else
                            <span class="badge badge-success">Terbuka</span>
                        @endif
                    </span>
                </div>
                <div class="meta-row">
                    <span class="meta-label">Tanggal Cetak</span>
                    <span class="meta-colon">:</span>
                    <span class="meta-value">{{ now()->translatedFormat('d F Y') }}</span>
                </div>
            </div>
        </div>

        <div class="section-title">I. Daftar Anggota Keluarga</div>
        <table>
            <thead>
                <tr>
                    <th width="3%">No</th>
                    <th>Nama Lengkap</th>
                    <th>NIK</th>
                    <th width="7%">L/P</th>
                    <th>Tempat, Tgl Lahir</th>
                    <th>Agama</th>
                    <th>Pendidikan</th>
                    <th>Pekerjaan</th>
                    <th>Hubungan</th>
                    <th>Status Kawin</th>
                    <th width="9%">Status Warga</th>
                </tr>
            </thead>
            <tbody>
                @forelse($keluarga->anggota as $idx => $a)
                    <tr>
                        <td class="text-center">{{ $idx + 1 }}</td>
                        <td><strong>{{ $a->nama_lengkap }}</strong></td>
                        <td class="text-center">{{ $a->nik }}</td>
                        <td class="text-center">{{ $a->jenis_kelamin }}</td>
                        <td>{{ $a->tempat_lahir }}, {{ $a->tanggal_lahir?->translatedFormat('d-m-Y') }}</td>
                        <td>{{ $a->agama?->nama ?? '—' }}</td>
                        <td>{{ $a->pendidikan?->nama ?? '—' }}</td>
                        <td>{{ $a->pekerjaan?->nama ?? '—' }}</td>
                        <td>{{ $a->hubungan?->nama ?? '—' }}</td>
                        <td>{{ ucwords(str_replace('_', ' ', $a->status_kawin)) }}</td>
                        <td class="text-center">
                            @if($a->status_kependudukan === 'pindah')
                                <span class="badge badge-warning">Pindah</span>
                            @elseif($a->status_kependudukan === 'meninggal')
                                <span class="badge badge-danger">Meninggal</span>
                            @else
                                <span class="badge badge-success">Menetap</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="11" class="text-center">Tidak ada data anggota keluarga.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>

        @if($keluarga->aset->isNotEmpty())
            <div class="section-title">II. Data Aset & Fasilitas Keluarga</div>
            <table>
                <thead>
                    <tr>
                        <th width="4%">No</th>
                        <th width="35%">Jenis Aset / Fasilitas</th>
                        <th>Keterangan / Rincian</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($keluarga->aset as $idx => $ast)
                        <tr>
                            <td class="text-center">{{ $idx + 1 }}</td>
                            <td><strong>{{ $ast->jenis_aset }}</strong></td>
                            <td>{{ $ast->keterangan ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="signature-grid">
            <div>
                <p>Kepala Keluarga</p>
                <div class="signature-space"></div>
                <p><strong>( {{ $keluarga->kepala?->nama_lengkap ?? '..............................' }} )</strong></p>
            </div>
            <div>
                <p>{{ $keluarga->wilayah?->nama ?? 'Ketua RT' }}, {{ now()->translatedFormat('d F Y') }}</p>
                <p>Ketua RT / RW</p>
                <div class="signature-space"></div>
                <p><strong>( .......................................... )</strong></p>
            </div>
        </div>
    </div>

</body>
</html>

