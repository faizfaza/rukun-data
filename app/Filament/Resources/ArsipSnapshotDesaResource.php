<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ArsipSnapshotDesaResource\Pages;
use App\Models\ArsipSnapshotDesa;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArsipSnapshotDesaResource extends Resource
{
    protected static ?string $model = ArsipSnapshotDesa::class;

    protected static ?string $slug = 'arsip-snapshot-desa';

    protected static ?string $navigationLabel = 'Arsip Snapshot & Dataset';

    protected static ?string $modelLabel = 'Batch Arsip Snapshot';

    protected static ?string $pluralModelLabel = 'Arsip Snapshot & Dataset';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static ?int $navigationSort = 2;

    public static function canAccess(): bool
    {
        return static::canViewAny();
    }

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminDesa());
    }

    public static function getEloquentQuery(): Builder
    {
        $user = auth()->user();

        if ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminDesa()) {
            ArsipSnapshotDesa::prosesSemuaDesaJatuhTempo();
        }

        $query = parent::getEloquentQuery()
            ->latest('created_at')
            ->latest('id');

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperadmin() || $user->isAdmin() || $user->isAdminDesa()) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction('lihatArsip')
            ->columns([
                TextColumn::make('created_at')
                    ->label('Waktu Arsip')
                    ->dateTime('d F Y H:i')
                    ->sortable()
                    ->description(fn (ArsipSnapshotDesa $record): string => "Interval tiap {$record->interval_jam} jam"),

                TextColumn::make('kode_snapshot')
                    ->label('Batch')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(function (ArsipSnapshotDesa $record): string {
                        $periode = $record->ringkasan_statistik['urutan_periode'] ?? null;
                        if (! $periode && preg_match('/(?:BATCH-|P)(\d+)/', $record->kode_snapshot, $m)) {
                            $periode = (int) $m[1];
                        }

                        return $periode ? "Batch #{$periode}" : $record->kode_snapshot;
                    })
                    ->description(fn (ArsipSnapshotDesa $record): string => $record->kode_snapshot)
                    ->searchable(),

                TextColumn::make('jumlah_keluarga')
                    ->label('Data Kependudukan')
                    ->formatStateUsing(function (ArsipSnapshotDesa $record): string {
                        $info = $record->ringkasanUntuk(auth()->user());

                        return "{$info['jumlah_keluarga']} Keluarga · {$info['jumlah_warga']} Warga";
                    })
                    ->description(function (ArsipSnapshotDesa $record): string {
                        $info = $record->ringkasanUntuk(auth()->user());

                        return "{$info['jumlah_menetap']} menetap · {$info['jumlah_pindah']} pindah";
                    }),

                TextColumn::make('jumlah_perubahan')
                    ->label('Perubahan Tercatat')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (int $state): string => "{$state} perubahan"),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->emptyStateHeading('Belum ada batch arsip berkala')
            ->emptyStateDescription('Batch arsip seluruh keluarga dan warga akan terekam otomatis mengikuti jadwal siklus jam.')
            ->actions([
                Action::make('lihatArsip')
                    ->label('Lihat Batch')
                    ->icon('heroicon-m-eye')
                    ->color('primary')
                    ->button()
                    ->size('sm')
                    ->modalHeading(fn (ArsipSnapshotDesa $record): string => "Rincian Batch Arsip · {$record->kode_snapshot}")
                    ->modalWidth('6xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (ArsipSnapshotDesa $record): View => view('filament.modals.arsip-snapshot-detail', [
                        'record' => $record,
                    ])),

                Action::make('unduhCsv')
                    ->label('Unduh')
                    ->icon('heroicon-m-arrow-down-tray')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->action(fn (ArsipSnapshotDesa $record): StreamedResponse => static::streamCsvDataset($record)),
            ]);
    }

    public static function streamCsvDataset(ArsipSnapshotDesa $record): StreamedResponse
    {
        $user = auth()->user();
        $filterDesaId = ($user?->isAdminDesa() && $user->id_wilayah) ? (int) $user->id_wilayah : null;

        $rows = $record->snapshotData['dataset_latih'] ?? [];
        if ($filterDesaId !== null) {
            $rows = array_values(array_filter(
                $rows,
                fn (array $r): bool => ! isset($r['id_wilayah_desa']) || (int) $r['id_wilayah_desa'] === $filterDesaId
            ));
        }

        $filename = strtolower($record->kode_snapshot).'-dataset-latih.csv';

        $headers = [
            'desa',
            'kode_snapshot',
            'waktu_snapshot',
            'id_keluarga_hash',
            'no_kk_masked',
            'id_warga_hash',
            'nik_masked',
            'wilayah_rt',
            'wilayah_rw',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'umur',
            'agama',
            'status_kawin',
            'hubungan_keluarga',
            'pendidikan',
            'pekerjaan',
            'nama_ayah',
            'nama_ibu',
            'penghasilan_bulanan',
            'jumlah_anggota_kk',
            'jumlah_aset_kk',
            'jenis_aset_kk',
            'status_kependudukan',
            'label_kategori_ekonomi',
        ];

        $namaDesaFallback = $record->wilayahDesa?->nama ?? ($record->snapshotData['desa'] ?? '');

        return response()->streamDownload(function () use ($rows, $headers, $namaDesaFallback): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $headers);
            foreach ($rows as $row) {
                $row['desa'] = $row['desa'] ?? $namaDesaFallback;
                $line = [];
                foreach ($headers as $col) {
                    $line[] = $row[$col] ?? '';
                }
                fputcsv($out, $line);
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  iterable<ArsipSnapshotDesa>  $records
     */
    public static function streamGabunganCsvDataset(iterable $records): StreamedResponse
    {
        $user = auth()->user();
        $filterDesaId = ($user?->isAdminDesa() && $user->id_wilayah) ? (int) $user->id_wilayah : null;
        $filename = 'seluruh-dataset-batch-snapshot-'.now()->format('Ymd-His').'.csv';

        $headers = [
            'desa',
            'kode_snapshot',
            'waktu_snapshot',
            'id_keluarga_hash',
            'no_kk_masked',
            'id_warga_hash',
            'nik_masked',
            'wilayah_rt',
            'wilayah_rw',
            'jenis_kelamin',
            'tempat_lahir',
            'tanggal_lahir',
            'umur',
            'agama',
            'status_kawin',
            'hubungan_keluarga',
            'pendidikan',
            'pekerjaan',
            'nama_ayah',
            'nama_ibu',
            'penghasilan_bulanan',
            'jumlah_anggota_kk',
            'jumlah_aset_kk',
            'jenis_aset_kk',
            'status_kependudukan',
            'label_kategori_ekonomi',
        ];

        return response()->streamDownload(function () use ($records, $headers, $filterDesaId): void {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fputcsv($out, $headers);
            foreach ($records as $record) {
                $namaDesaFallback = $record->wilayahDesa?->nama ?? ($record->snapshotData['desa'] ?? '');
                $rows = $record->snapshotData['dataset_latih'] ?? [];
                foreach ($rows as $row) {
                    if ($filterDesaId !== null && isset($row['id_wilayah_desa']) && (int) $row['id_wilayah_desa'] !== $filterDesaId) {
                        continue;
                    }
                    $row['desa'] = $row['desa'] ?? $namaDesaFallback;
                    $line = [];
                    foreach ($headers as $col) {
                        $line[] = $row[$col] ?? '';
                    }
                    fputcsv($out, $line);
                }
            }
            fclose($out);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    /**
     * @param  iterable<ArsipSnapshotDesa>  $records
     */
    public static function streamGabunganJsonDataset(iterable $records): StreamedResponse
    {
        $user = auth()->user();
        $filterDesaId = ($user?->isAdminDesa() && $user->id_wilayah) ? (int) $user->id_wilayah : null;
        $filename = 'seluruh-dataset-batch-snapshot-'.now()->format('Ymd-His').'.json';

        $gabungan = [];
        foreach ($records as $record) {
            $rows = $record->snapshotData['dataset_latih'] ?? [];
            if ($filterDesaId !== null) {
                $rows = array_values(array_filter(
                    $rows,
                    fn (array $r): bool => ! isset($r['id_wilayah_desa']) || (int) $r['id_wilayah_desa'] === $filterDesaId
                ));
            }
            $gabungan[] = [
                'kode_snapshot' => $record->kode_snapshot,
                'waktu_snapshot' => $record->created_at?->toIso8601String(),
                'interval_jam' => $record->interval_jam,
                'dataset_latih' => $rows,
            ];
        }

        return response()->streamDownload(function () use ($gabungan): void {
            echo json_encode($gabungan, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, [
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListArsipSnapshotDesas::route('/'),
        ];
    }
}
