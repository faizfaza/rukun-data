<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AuditLogResource\Pages;
use App\Models\AuditLog;
use App\Support\WilayahAccess;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
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

        if ($user->isSuperadmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isAdminRt()) {
            return $query->where('id_pengguna', $user->id);
        }

        if ($user->isAdminRw()) {
            $allowedWilayahIds = WilayahAccess::allowedWilayahIds($user) ?? [];

            return $query->where(function (Builder $q) use ($user, $allowedWilayahIds): void {
                $q->where('id_pengguna', $user->id)
                    ->orWhereHas('pengguna', function (Builder $userQuery) use ($allowedWilayahIds): void {
                        $userQuery->whereIn('id_wilayah', $allowedWilayahIds);
                    });
            });
        }

        return $query->where('id_pengguna', $user->id);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('waktu')
                    ->label('Waktu')
                    ->dateTime('d M Y H:i:s')
                    ->sortable(),
                TextColumn::make('pengguna.name')
                    ->label('Pengguna')
                    ->searchable()
                    ->sortable()
                    ->default('Sistem / Tamu'),
                TextColumn::make('pengguna.wilayah.nama')
                    ->label('Wilayah Penugasan')
                    ->default('—'),
                TextColumn::make('aksi')
                    ->label('Aksi')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        'restored' => 'warning',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
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
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'keluarga' => 'Keluarga',
                        'anggota' => 'Anggota Warga',
                        'users' => 'Akun Pengguna',
                        'wilayah' => 'Wilayah',
                        'aset_keluarga' => 'Aset Keluarga',
                        'ekonomi_keluarga' => 'Ekonomi Keluarga',
                        default => ucfirst($state),
                    }),
                TextColumn::make('id_record')
                    ->label('ID Data')
                    ->sortable(),
            ])
            ->defaultSort('waktu', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('aksi')
                    ->label('Filter Aksi')
                    ->options([
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
                    ]),
            ])
            ->actions([])
            ->bulkActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListAuditLogs::route('/'),
        ];
    }
}

