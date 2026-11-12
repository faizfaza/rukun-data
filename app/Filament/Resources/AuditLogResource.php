<?php

namespace App\Filament\Resources;

use App\Enums\Role;
use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use App\Support\AuditFormatter;
use App\Support\WilayahAccess;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static ?string $slug = 'log-aktivitas';

    protected static ?string $navigationLabel = 'Log Aktivitas';

    protected static ?string $modelLabel = 'Log Aktivitas';

    protected static ?string $pluralModelLabel = 'Log Aktivitas';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['pengguna.wilayah'])->latest('waktu');
        $user = auth()->user();

        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperadmin()) {
            return $query;
        }

        if ($user->isAdmin()) {
            return $query->where(function (Builder $q): void {
                $q->whereNull('id_pengguna')
                    ->orWhereHas('pengguna', function (Builder $userQuery): void {
                        $userQuery->where('role', '!=', Role::Superadmin->value);
                    });
            });
        }

        if ($user->isAdminRw() || $user->isAdminRt()) {
            $allowedRtIds = WilayahAccess::allowedRtIds($user) ?? [];
            $allowedWilayahIds = WilayahAccess::allowedWilayahIds($user) ?? [];

            return $query->where(function (Builder $q) use ($user, $allowedRtIds, $allowedWilayahIds): void {
                // 1. Log aktivitas yang dilakukan oleh pengguna itu sendiri
                $q->where('id_pengguna', $user->id);

                // 2. Log aktivitas yang dilakukan oleh Admin RT bawahan (khusus Admin RW)
                if ($user->isAdminRw() && ! empty($allowedWilayahIds)) {
                    $q->orWhereHas('pengguna', function (Builder $userQuery) use ($allowedWilayahIds): void {
                        $userQuery->where('role', Role::AdminRt->value)
                            ->whereIn('id_wilayah', $allowedWilayahIds);
                    });
                }

                // 3. Log aktivitas oleh SIAPAPUN (termasuk Superadmin & Admin Desa) terhadap data di wilayah ini
                if (! empty($allowedRtIds)) {
                    // Data Keluarga
                    $q->orWhere(function (Builder $sub) use ($allowedRtIds): void {
                        $sub->where('nama_tabel', 'keluarga')
                            ->whereIn('id_record', function ($sq) use ($allowedRtIds): void {
                                $sq->select('id')
                                    ->from('keluarga')
                                    ->whereIn('id_wilayah', $allowedRtIds);
                            });
                    });

                    // Data Anggota Warga
                    $q->orWhere(function (Builder $sub) use ($allowedRtIds): void {
                        $sub->where('nama_tabel', 'anggota')
                            ->whereIn('id_record', function ($sq) use ($allowedRtIds): void {
                                $sq->select('id')
                                    ->from('anggota')
                                    ->whereIn('id_keluarga', function ($kSq) use ($allowedRtIds): void {
                                        $kSq->select('id')
                                            ->from('keluarga')
                                            ->whereIn('id_wilayah', $allowedRtIds);
                                    });
                            });
                    });

                    // Data Aset Keluarga
                    $q->orWhere(function (Builder $sub) use ($allowedRtIds): void {
                        $sub->where('nama_tabel', 'aset_keluarga')
                            ->whereIn('id_record', function ($sq) use ($allowedRtIds): void {
                                $sq->select('id')
                                    ->from('aset_keluarga')
                                    ->whereIn('id_keluarga', function ($kSq) use ($allowedRtIds): void {
                                        $kSq->select('id')
                                            ->from('keluarga')
                                            ->whereIn('id_wilayah', $allowedRtIds);
                                    });
                            });
                    });

                    // Data Ekonomi Keluarga
                    $q->orWhere(function (Builder $sub) use ($allowedRtIds): void {
                        $sub->where('nama_tabel', 'ekonomi_keluarga')
                            ->whereIn('id_record', function ($sq) use ($allowedRtIds): void {
                                $sq->select('id')
                                    ->from('ekonomi_keluarga')
                                    ->whereIn('id_keluarga', function ($kSq) use ($allowedRtIds): void {
                                        $kSq->select('id')
                                            ->from('keluarga')
                                            ->whereIn('id_wilayah', $allowedRtIds);
                                    });
                            });
                    });
                }

                if (! empty($allowedWilayahIds)) {
                    // Data Wilayah itu sendiri (RW atau RT terkait)
                    $q->orWhere(function (Builder $sub) use ($allowedWilayahIds): void {
                        $sub->where('nama_tabel', 'wilayah')
                            ->whereIn('id_record', $allowedWilayahIds);
                    });

                    // Akun pengguna yang ditugaskan pada wilayah tersebut
                    $q->orWhere(function (Builder $sub) use ($allowedWilayahIds): void {
                        $sub->where('nama_tabel', 'users')
                            ->whereIn('id_record', function ($sq) use ($allowedWilayahIds): void {
                                $sq->select('id')
                                    ->from('users')
                                    ->whereIn('id_wilayah', $allowedWilayahIds);
                            });
                    });
                }
            });
        }

        return $query->where('id_pengguna', $user->id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordAction('lihatRincian')
            ->columns([
                TextColumn::make('waktu')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('pengguna.name')
                    ->label('Pengguna')
                    ->searchable()
                    ->sortable()
                    ->description(fn (AuditLog $record): ?string => $record->pengguna?->role?->label())
                    ->default('Sistem / Tamu'),
                TextColumn::make('pengguna.wilayah.nama')
                    ->label('Wilayah Penugasan')
                    ->default('—'),
                TextColumn::make('aksi')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'login' => 'primary',
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'login' => 'Login (Masuk)',
                        'created' => 'Tambah (Create)',
                        'updated' => 'Ubah (Update)',
                        'deleted' => 'Hapus / Arsip',
                        'restored' => 'Pulihkan',
                        default => ucfirst($state),
                    }),
                TextColumn::make('nama_tabel')
                    ->label('Objek / Modul')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (string $state): string => AuditFormatter::labelTabel($state)),
                TextColumn::make('ringkasan_aktivitas')
                    ->label('Rincian Aktivitas (Klik baris untuk detail)')
                    ->wrap()
                    ->getStateUsing(fn (AuditLog $record): string => AuditFormatter::describe($record)['ringkasan'])
                    ->description('Klik untuk melihat rincian perubahan & arsip versi sebelum diubah →'),
                TextColumn::make('id_record')
                    ->label('ID Data')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('waktu', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('aksi')
                    ->label('Filter Aksi')
                    ->options([
                        'login' => 'Login (Masuk)',
                        'created' => 'Tambah (Create)',
                        'updated' => 'Ubah (Update)',
                        'deleted' => 'Hapus / Arsip',
                        'restored' => 'Pulihkan',
                    ]),
                SelectFilter::make('nama_tabel')
                    ->label('Filter Objek')
                    ->options([
                        'keluarga' => 'Keluarga',
                        'anggota' => 'Anggota Warga',
                        'users' => 'Akun Pengguna',
                        'wilayah' => 'Wilayah',
                        'aset_keluarga' => 'Aset Keluarga',
                        'ekonomi_keluarga' => 'Ekonomi Keluarga',
                    ]),
            ])
            ->actions([
                Action::make('lihatRincian')
                    ->label('Rincian & Arsip')
                    ->icon('heroicon-o-eye')
                    ->color('primary')
                    ->modalHeading(fn (AuditLog $record): string => 'Rincian Log Aktivitas · '.AuditFormatter::labelTabel((string) $record->nama_tabel))
                    ->modalDescription(fn (AuditLog $record): string => 'Dicatat pada '.($record->waktu?->format('d M Y H:i:s') ?? '—').' oleh '.($record->pengguna?->name ?? 'Sistem / Tamu'))
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup Rincian')
                    ->modalContent(fn (AuditLog $record): View => view('filament.modals.audit-log-detail', ['record' => $record])),
            ])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}
