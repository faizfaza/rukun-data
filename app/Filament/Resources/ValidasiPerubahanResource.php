<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ValidasiPerubahanResource\Pages;
use App\Models\ValidasiPerubahan;
use App\Support\AuditFormatter;
use App\Support\WilayahAccess;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

class ValidasiPerubahanResource extends Resource
{
    protected static ?string $model = ValidasiPerubahan::class;

    protected static ?string $slug = 'validasi-perubahan';

    protected static ?string $navigationLabel = 'Validasi & Persetujuan';

    protected static ?string $modelLabel = 'Validasi Perubahan';

    protected static ?string $pluralModelLabel = 'Validasi & Persetujuan Berjenjang';

    protected static string|\UnitEnum|null $navigationGroup = 'Pendataan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-shield-check';

    protected static ?int $navigationSort = 2;

    public static function getNavigationBadge(): ?string
    {
        try {
            if (! Schema::hasTable('validasi_perubahan')) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $query = static::getEloquentQuery();

        if ($user->isAdminRw()) {
            $count = (clone $query)->where('status', ValidasiPerubahan::STATUS_MENUNGGU_RW)->count();
        } elseif ($user->isAdminDesa() || $user->isSuperadmin() || $user->isAdmin()) {
            $count = (clone $query)->where('status', ValidasiPerubahan::STATUS_MENUNGGU_DESA)->count();
        } else {
            $count = (clone $query)->whereIn('status', [
                ValidasiPerubahan::STATUS_MENUNGGU_RW,
                ValidasiPerubahan::STATUS_MENUNGGU_DESA,
            ])->count();
        }

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'primary';
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with(['pengaju', 'wilayahRt', 'wilayahRw', 'wilayahDesa', 'wilayahTujuan', 'verifikatorRw', 'verifikatorDesa'])
            ->latest('created_at');

        $user = auth()->user();
        if (! $user) {
            return $query->whereRaw('1 = 0');
        }

        if ($user->isSuperadmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isAdminDesa()) {
            $allowedRtIds = WilayahAccess::allowedRtIds($user) ?? [];

            return $query->where(function (Builder $q) use ($user, $allowedRtIds): void {
                $q->where('id_wilayah_desa', $user->id_wilayah);
                if ($allowedRtIds !== []) {
                    $q->orWhereIn('id_wilayah_rt', $allowedRtIds);
                }
            });
        }

        if ($user->isAdminRw()) {
            $allowedRtIds = WilayahAccess::allowedRtIds($user) ?? [];

            return $query->where(function (Builder $q) use ($user, $allowedRtIds): void {
                $q->where('id_wilayah_rw', $user->id_wilayah)
                    ->orWhere('id_pengguna', $user->id);
                if ($allowedRtIds !== []) {
                    $q->orWhereIn('id_wilayah_rt', $allowedRtIds);
                }
            });
        }

        if ($user->isAdminRt()) {
            return $query->where(function (Builder $q) use ($user): void {
                $q->where('id_pengguna', $user->id);
                if ($user->id_wilayah) {
                    $q->orWhere('id_wilayah_rt', $user->id_wilayah);
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
                TextColumn::make('created_at')
                    ->label('Waktu Pengajuan')
                    ->dateTime('d M Y H:i')
                    ->sortable(),

                TextColumn::make('pengaju.name')
                    ->label('Pengaju')
                    ->default('Petugas')
                    ->description(fn (ValidasiPerubahan $record): string => trim(
                        ($record->pengaju?->role?->label() ?? $record->peran_pengaju ?? '')
                        .($record->wilayahRt ? ' · '.$record->wilayahRt->nama : '')
                        .($record->wilayahRw ? ' ('.$record->wilayahRw->nama.')' : '')
                    ))
                    ->searchable(),

                TextColumn::make('aksi')
                    ->label('Jenis')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ValidasiPerubahan::labelAksi($state))
                    ->color(fn (string $state): string => match ($state) {
                        'pindah' => 'warning',
                        'created' => 'success',
                        'updated' => 'info',
                        'deleted' => 'danger',
                        default => 'gray',
                    }),

                TextColumn::make('nama_tabel')
                    ->label('Data KK Terkait')
                    ->weight('bold')
                    ->formatStateUsing(fn (ValidasiPerubahan $record): string => $record->labelKeluargaTerkait())
                    ->description(fn (ValidasiPerubahan $record): string => AuditFormatter::labelTabel($record->nama_tabel)
                        .($record->wilayahRt ? ' · '.$record->wilayahRt->nama : '')
                        .($record->wilayahRw ? ' / '.$record->wilayahRw->nama : '')
                    ),

                TextColumn::make('ringkasan')
                    ->label('Rincian Perubahan / Mutasi')
                    ->wrap()
                    ->searchable()
                    ->description(fn (ValidasiPerubahan $record): ?string => $record->catatan_pengaju
                        ? 'Catatan pengaju: '.$record->catatan_pengaju
                        : 'Klik baris untuk melihat rincian & alur verifikasi →'
                    ),

                TextColumn::make('status')
                    ->label('Status Validasi')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ValidasiPerubahan::labelStatus($state))
                    ->color(fn (string $state): string => ValidasiPerubahan::warnaStatus($state))
                    ->description(function (ValidasiPerubahan $record): ?string {
                        if ($record->status === ValidasiPerubahan::STATUS_MENUNGGU_DESA && $record->verifikatorRw) {
                            return 'RW: Disetujui oleh '.$record->verifikatorRw->name;
                        }
                        if ($record->status === ValidasiPerubahan::STATUS_DISETUJUI && $record->verifikatorDesa) {
                            return 'Desa: Disahkan oleh '.$record->verifikatorDesa->name;
                        }
                        if ($record->status === ValidasiPerubahan::STATUS_DITOLAK) {
                            return 'Alasan: '.($record->catatan_desa ?: $record->catatan_rw ?: 'Ditolak');
                        }

                        return null;
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->filters([
                SelectFilter::make('status')
                    ->label('Status Validasi')
                    ->options([
                        ValidasiPerubahan::STATUS_MENUNGGU_RW => 'Menunggu Verifikasi RW',
                        ValidasiPerubahan::STATUS_MENUNGGU_DESA => 'Menunggu Verifikasi Desa',
                        ValidasiPerubahan::STATUS_DISETUJUI => 'Disetujui (Terverifikasi Desa)',
                        ValidasiPerubahan::STATUS_DITOLAK => 'Ditolak (Dikembalikan)',
                    ]),
                SelectFilter::make('aksi')
                    ->label('Jenis Pengajuan')
                    ->options([
                        'pindah' => 'Mutasi / Pindah',
                        'created' => 'Penambahan Data',
                        'updated' => 'Perubahan Data',
                        'deleted' => 'Penghapusan / Arsip',
                    ]),
            ])
            ->actions([
                Action::make('setujuiRw')
                    ->label('Setujui & Teruskan ke Desa')
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('info')
                    ->button()
                    ->size('sm')
                    ->visible(fn (ValidasiPerubahan $record): bool => auth()->user() ? $record->bolehDiverifikasiRwOleh(auth()->user()) && auth()->user()->isAdminRw() : false)
                    ->form([
                        Textarea::make('catatan_rw')
                            ->label('Catatan Verifikasi RW (Opsional)')
                            ->placeholder('Misal: Data warga pindah sudah sesuai surat pengantar RT.')
                            ->rows(2),
                    ])
                    ->modalHeading('Setujui & Teruskan ke Admin Desa / Kepala Desa')
                    ->modalDescription('Pengajuan ini akan ditandai telah diverifikasi oleh RW dan diteruskan ke tingkat Desa untuk pengesahan akhir.')
                    ->modalSubmitActionLabel('Setujui & Kirim ke Desa')
                    ->action(function (ValidasiPerubahan $record, array $data): void {
                        $user = auth()->user();
                        abort_unless($user && $record->bolehDiverifikasiRwOleh($user), 403);

                        $record->setujuiOlehRw($user, $data['catatan_rw'] ?? null);

                        Notification::make()
                            ->title('Diverifikasi oleh RW & Diteruskan ke Desa')
                            ->body('Pengajuan kini menunggu pengesahan akhir dari Admin Desa / Kepala Desa.')
                            ->success()
                            ->send();
                    }),

                Action::make('setujuiDesa')
                    ->label('Setujui & Sahkan (Desa)')
                    ->icon('heroicon-m-check-badge')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->visible(fn (ValidasiPerubahan $record): bool => auth()->user() ? $record->bolehDiverifikasiDesaOleh(auth()->user()) : false)
                    ->form([
                        Textarea::make('catatan_desa')
                            ->label('Catatan Pengesahan Desa (Opsional)')
                            ->placeholder('Misal: Disetujui oleh Pemerintah Desa.')
                            ->rows(2),
                    ])
                    ->modalHeading('Pengesahan Akhir Tingkat Desa')
                    ->modalDescription('Menyetujui pengajuan ini akan mengesahkan validitas data pada tingkat Pemerintah Desa.')
                    ->modalSubmitActionLabel('Sahkan Perubahan Data')
                    ->action(function (ValidasiPerubahan $record, array $data): void {
                        $user = auth()->user();
                        abort_unless($user && $record->bolehDiverifikasiDesaOleh($user), 403);

                        $record->setujuiOlehDesa($user, $data['catatan_desa'] ?? null);

                        Notification::make()
                            ->title('Data Resmi Disahkan Desa')
                            ->body('Pengajuan perubahan/mutasi warga telah terverifikasi penuh.')
                            ->success()
                            ->send();
                    }),

                Action::make('tolak')
                    ->label('Tolak')
                    ->icon('heroicon-m-x-circle')
                    ->color('danger')
                    ->button()
                    ->size('sm')
                    ->visible(fn (ValidasiPerubahan $record): bool => auth()->user() ? $record->bolehDitolakOleh(auth()->user()) : false)
                    ->form([
                        Textarea::make('alasan')
                            ->label('Alasan Penolakan')
                            ->required()
                            ->placeholder('Jelaskan alasan penolakan agar petugas pengaju dapat memperbaikinya.')
                            ->rows(3),
                    ])
                    ->modalHeading('Tolak Pengajuan & Kembalikan Data')
                    ->modalDescription('Menolak pengajuan ini akan membatalkan perubahan dan mengembalikan data ke kondisi sebelum diajukan.')
                    ->modalSubmitActionLabel('Tolak & Kembalikan Data')
                    ->action(function (ValidasiPerubahan $record, array $data): void {
                        $user = auth()->user();
                        abort_unless($user && $record->bolehDitolakOleh($user), 403);

                        $record->tolak($user, (string) $data['alasan']);

                        Notification::make()
                            ->title('Pengajuan Ditolak & Data Dikembalikan')
                            ->body('Perubahan telah dibatalkan dan dikembalikan ke versi sebelumnya.')
                            ->warning()
                            ->send();
                    }),

                Action::make('lihatRincian')
                    ->label('Rincian')
                    ->icon('heroicon-m-eye')
                    ->color('gray')
                    ->modalHeading('Rincian Validasi & Alur Persetujuan Berjenjang')
                    ->modalWidth('4xl')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->modalContent(fn (ValidasiPerubahan $record): View => view('filament.modals.validasi-perubahan-detail', [
                        'record' => $record,
                    ])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListValidasiPerubahans::route('/'),
        ];
    }
}

