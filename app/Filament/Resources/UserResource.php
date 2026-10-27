<?php

namespace App\Filament\Resources;

use App\Enums\Role;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\WilayahAccess;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Select as FormSelect;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Fase 2.5: manajemen akun. Superadmin melihat semua akun; admin_rw hanya
// akun admin_rt di RW-nya. Form dibatasi kewenangan pembuat (peran & wilayah).
// Tidak ada hapus, hanya aktif/nonaktif.
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'pengguna';

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'pengguna';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('username')
                ->label('Username')
                ->required()
                ->maxLength(50)
                ->unique(ignoreRecord: true)
                ->disabledOn('edit'),

            TextInput::make('name')
                ->label('Nama')
                ->required()
                ->maxLength(100),

            TextInput::make('password')
                ->label('Password')
                ->password()
                ->revealable()
                ->minLength(6)
                ->required(fn (string $operation): bool => $operation === 'create')
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Wajib saat membuat akun. Kosongkan bila tidak ingin mengubah.'),

            Select::make('role')
                ->label('Peran')
                ->options(fn (?User $record): array => static::roleOptions($record))
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('id_wilayah', null))
                ->disabled(fn (?User $record): bool => $record !== null && $record->is(auth()->user())),

            Select::make('id_wilayah')
                ->label('Wilayah')
                ->options(fn (Get $get, ?User $record): array => static::wilayahOptions($get('role'), $record))
                ->searchable()
                ->preload()
                ->required(fn (Get $get): bool => ! in_array($get('role'), [Role::Superadmin->value, Role::Admin->value], true))
                ->visible(fn (Get $get): bool => ! in_array($get('role'), [Role::Superadmin->value, Role::Admin->value], true)),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->helperText('Akun tidak dihapus; nonaktifkan bila tidak dipakai lagi.')
                ->disabled(fn (?User $record): bool => $record !== null && ! (auth()->user()?->can('deactivate', $record) ?? false)),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->heading('Data Admin')
            ->columns([
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'),

                TextColumn::make('role')
                    ->label('Level Admin')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state instanceof Role ? $state : Role::tryFrom((string) $state)) {
                        Role::Superadmin => 'Superadmin',
                        Role::Admin => 'Admin',
                        Role::AdminRw => 'Admin RW',
                        Role::AdminRt => 'Admin RT',
                        default => (string) $state,
                    })
                    ->color(fn ($state): string => match ($state instanceof Role ? $state : Role::tryFrom((string) $state)) {
                        Role::Superadmin => 'danger',
                        Role::Admin => 'warning',
                        Role::AdminRw => 'info',
                        Role::AdminRt => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nama Admin')
                    ->description(fn (User $record): ?string => $record->username ? "@{$record->username}" : null)
                    ->searchable()
                    ->sortable(),

                TextColumn::make('provinsi')
                    ->label('Provinsi')
                    ->getStateUsing(function (User $record): string {
                        if (! $record->wilayah) {
                            return '—';
                        }

                        return $record->wilayah->ancestors()
                            ->firstWhere('level', 'provinsi')
                            ?->nama ?? '—';
                    })
                    ->sortable(false),

                TextColumn::make('kab_kota')
                    ->label('Kab/Kota')
                    ->getStateUsing(function (User $record): string {
                        if (! $record->wilayah) {
                            return '—';
                        }

                        return $record->wilayah->ancestors()
                            ->firstWhere('level', 'kabupaten_kota')
                            ?->nama ?? '—';
                    })
                    ->sortable(false),

                TextColumn::make('kecamatan')
                    ->label('Kecamatan')
                    ->getStateUsing(function (User $record): string {
                        if (! $record->wilayah) {
                            return '—';
                        }

                        return $record->wilayah->ancestors()
                            ->firstWhere('level', 'kecamatan')
                            ?->nama ?? '—';
                    })
                    ->sortable(false),

                TextColumn::make('kelurahan')
                    ->label('Kelurahan/Desa')
                    ->getStateUsing(function (User $record): string {
                        if (! $record->wilayah) {
                            return '—';
                        }

                        return $record->wilayah->ancestors()
                            ->firstWhere('level', 'desa')
                            ?->nama ?? '—';
                    })
                    ->sortable(false),

                TextColumn::make('rw')
                    ->label('RW')
                    ->getStateUsing(function (User $record): string {
                        if (! $record->wilayah) {
                            return '—';
                        }

                        // Jika admin_rw, wilayahnya adalah RW langsung
                        if ($record->role === Role::AdminRw) {
                            return $record->wilayah->kode ?? $record->wilayah->nama;
                        }

                        // Jika admin_rt, cari ancestor RW
                        return $record->wilayah->ancestors()
                            ->firstWhere('level', 'rw')
                            ?->kode ?? '—';
                    })
                    ->sortable(false),

                TextColumn::make('rt')
                    ->label('RT')
                    ->getStateUsing(function (User $record): string {
                        if (! $record->wilayah) {
                            return '—';
                        }

                        // Hanya admin_rt yang punya RT
                        if ($record->role !== Role::AdminRt) {
                            return '—';
                        }

                        return $record->wilayah->kode ?? $record->wilayah->nama;
                    })
                    ->sortable(false),
            ])
            ->filters([
                Filter::make('filter_wilayah')
                    ->label('Filter Pengguna')
                    ->columnSpanFull()
                    ->columns(3)
                    ->form([
                        // Baris 1: Provinsi, Kab/Kota, Kecamatan
                        FormSelect::make('provinsi_id')
                            ->label('Provinsi')
                            ->options(fn (): array => Wilayah::where('level', 'provinsi')
                                ->orderBy('nama')
                                ->pluck('nama', 'id')
                                ->all()
                            )
                            ->placeholder('— Semua Provinsi —')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('kabkota_id', null);
                                $set('kecamatan_id', null);
                                $set('kelurahan_id', null);
                                $set('rw_id', null);
                                $set('rt_id', null);
                            }),

                        FormSelect::make('kabkota_id')
                            ->label('Kabupaten/Kota')
                            ->options(fn (Get $get): array => Wilayah::where('level', 'kabupaten_kota')
                                ->when(
                                    $get('provinsi_id'),
                                    fn (Builder $q, $pid): Builder => $q->where('parent_id', $pid)
                                )
                                ->orderBy('nama')
                                ->pluck('nama', 'id')
                                ->all()
                            )
                            ->placeholder('— Semua Kab/Kota —')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('kecamatan_id', null);
                                $set('kelurahan_id', null);
                                $set('rw_id', null);
                                $set('rt_id', null);
                            }),

                        FormSelect::make('kecamatan_id')
                            ->label('Kecamatan')
                            ->options(fn (Get $get): array => Wilayah::where('level', 'kecamatan')
                                ->when(
                                    $get('kabkota_id'),
                                    fn (Builder $q, $kid): Builder => $q->where('parent_id', $kid)
                                )
                                ->orderBy('nama')
                                ->pluck('nama', 'id')
                                ->all()
                            )
                            ->placeholder('— Semua Kecamatan —')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('kelurahan_id', null);
                                $set('rw_id', null);
                                $set('rt_id', null);
                            }),

                        // Baris 2: Kelurahan, RW, RT
                        FormSelect::make('kelurahan_id')
                            ->label('Kelurahan/Desa')
                            ->options(fn (Get $get): array => Wilayah::where('level', 'desa')
                                ->when(
                                    $get('kecamatan_id'),
                                    fn (Builder $q, $kid): Builder => $q->where('parent_id', $kid)
                                )
                                ->orderBy('nama')
                                ->pluck('nama', 'id')
                                ->all()
                            )
                            ->placeholder('— Semua Kelurahan —')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('rw_id', null);
                                $set('rt_id', null);
                            }),

                        FormSelect::make('rw_id')
                            ->label('RW')
                            ->options(fn (Get $get): array => Wilayah::where('level', 'rw')
                                ->when(
                                    $get('kelurahan_id'),
                                    fn (Builder $q, $did): Builder => $q->where(function (Builder $q2) use ($did): void {
                                        // RW bisa berinduk desa atau dusun
                                        $dusunIds = Wilayah::where('level', 'dusun')
                                            ->where('parent_id', $did)
                                            ->pluck('id')
                                            ->all();
                                        $q2->where('parent_id', $did)
                                           ->orWhereIn('parent_id', $dusunIds);
                                    })
                                )
                                ->orderBy('kode')
                                ->get()
                                ->mapWithKeys(fn (Wilayah $w): array => [$w->id => "RW {$w->kode}"])
                                ->all()
                            )
                            ->placeholder('— Semua RW —')
                            ->live()
                            ->afterStateUpdated(function (Set $set): void {
                                $set('rt_id', null);
                            }),

                        FormSelect::make('rt_id')
                            ->label('RT')
                            ->options(fn (Get $get): array => Wilayah::where('level', 'rt')
                                ->when(
                                    $get('rw_id'),
                                    fn (Builder $q, $rwid): Builder => $q->where('parent_id', $rwid)
                                )
                                ->orderBy('kode')
                                ->get()
                                ->mapWithKeys(fn (Wilayah $w): array => [$w->id => "RT {$w->kode}"])
                                ->all()
                            )
                            ->placeholder('— Semua RT —'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        // Filter dari yang paling spesifik ke paling umum

                        if (! empty($data['rt_id'])) {
                            return $query->where('id_wilayah', $data['rt_id']);
                        }

                        if (! empty($data['rw_id'])) {
                            $rtIds = Wilayah::where('level', 'rt')
                                ->where('parent_id', $data['rw_id'])
                                ->pluck('id');

                            return $query->where(function (Builder $q) use ($data, $rtIds): void {
                                $q->where('id_wilayah', $data['rw_id'])
                                  ->orWhereIn('id_wilayah', $rtIds);
                            });
                        }

                        if (! empty($data['kelurahan_id'])) {
                            $dusunIds = Wilayah::where('level', 'dusun')
                                ->where('parent_id', $data['kelurahan_id'])
                                ->pluck('id')->all();

                            $rwIds = Wilayah::where('level', 'rw')
                                ->where(function (Builder $q) use ($data, $dusunIds): void {
                                    $q->where('parent_id', $data['kelurahan_id'])
                                      ->orWhereIn('parent_id', $dusunIds);
                                })->pluck('id')->all();

                            $rtIds = Wilayah::where('level', 'rt')
                                ->whereIn('parent_id', $rwIds)->pluck('id')->all();

                            return $query->whereIn('id_wilayah', array_merge($rwIds, $rtIds));
                        }

                        if (! empty($data['kecamatan_id'])) {
                            $desaIds = Wilayah::where('level', 'desa')
                                ->where('parent_id', $data['kecamatan_id'])->pluck('id')->all();

                            $dusunIds = Wilayah::where('level', 'dusun')
                                ->whereIn('parent_id', $desaIds)->pluck('id')->all();

                            $rwIds = Wilayah::where('level', 'rw')
                                ->where(function (Builder $q) use ($desaIds, $dusunIds): void {
                                    $q->whereIn('parent_id', $desaIds)
                                      ->orWhereIn('parent_id', $dusunIds);
                                })->pluck('id')->all();

                            $rtIds = Wilayah::where('level', 'rt')
                                ->whereIn('parent_id', $rwIds)->pluck('id')->all();

                            return $query->whereIn('id_wilayah', array_merge($rwIds, $rtIds));
                        }

                        if (! empty($data['kabkota_id'])) {
                            $kecIds = Wilayah::where('level', 'kecamatan')
                                ->where('parent_id', $data['kabkota_id'])->pluck('id')->all();

                            $desaIds = Wilayah::where('level', 'desa')
                                ->whereIn('parent_id', $kecIds)->pluck('id')->all();

                            $dusunIds = Wilayah::where('level', 'dusun')
                                ->whereIn('parent_id', $desaIds)->pluck('id')->all();

                            $rwIds = Wilayah::where('level', 'rw')
                                ->where(function (Builder $q) use ($desaIds, $dusunIds): void {
                                    $q->whereIn('parent_id', $desaIds)
                                      ->orWhereIn('parent_id', $dusunIds);
                                })->pluck('id')->all();

                            $rtIds = Wilayah::where('level', 'rt')
                                ->whereIn('parent_id', $rwIds)->pluck('id')->all();

                            return $query->whereIn('id_wilayah', array_merge($rwIds, $rtIds));
                        }

                        if (! empty($data['provinsi_id'])) {
                            $kabIds = Wilayah::where('level', 'kabupaten_kota')
                                ->where('parent_id', $data['provinsi_id'])->pluck('id')->all();

                            $kecIds = Wilayah::where('level', 'kecamatan')
                                ->whereIn('parent_id', $kabIds)->pluck('id')->all();

                            $desaIds = Wilayah::where('level', 'desa')
                                ->whereIn('parent_id', $kecIds)->pluck('id')->all();

                            $dusunIds = Wilayah::where('level', 'dusun')
                                ->whereIn('parent_id', $desaIds)->pluck('id')->all();

                            $rwIds = Wilayah::where('level', 'rw')
                                ->where(function (Builder $q) use ($desaIds, $dusunIds): void {
                                    $q->whereIn('parent_id', $desaIds)
                                      ->orWhereIn('parent_id', $dusunIds);
                                })->pluck('id')->all();

                            $rtIds = Wilayah::where('level', 'rt')
                                ->whereIn('parent_id', $rwIds)->pluck('id')->all();

                            return $query->whereIn('id_wilayah', array_merge($rwIds, $rtIds));
                        }

                        return $query;
                    })
                    ->indicateUsing(fn (): array => []),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->striped()
            ->emptyStateHeading('Belum ada akun')
            ->emptyStateDescription('Tambahkan akun admin RT untuk wilayah Anda.')
            ->actions([
                EditAction::make(),
                Action::make('toggleAktif')
                    ->label(fn (User $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (User $record): string => $record->is_active ? 'heroicon-m-no-symbol' : 'heroicon-m-check-circle')
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    // Pengguna tidak boleh menonaktifkan dirinya sendiri,
                    // dan admin tidak boleh menonaktifkan admin lain atau superadmin.
                    ->visible(fn (User $record): bool => auth()->user()?->can('deactivate', $record) ?? false)
                    ->action(function (User $record): void {
                        abort_unless(auth()->user()?->can('deactivate', $record), 403);

                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title('Akun diperbarui')
                            ->body($record->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    // Hanya superadmin dan admin yang mengelola akun pengguna.
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null || $user->isSuperadmin() || $user->isAdmin()) {
            return $query;
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }

    // Peran yang boleh dipilih: kewenangan pembuat, ditambah peran record saat
    // mengedit agar nilainya tetap tampil.
    /** @return array<string, string> */
    protected static function roleOptions(?User $record = null): array
    {
        $creator = auth()->user();
        $roles = $creator?->role->bolehMembuat() ?? [];

        if ($record !== null && ! in_array($record->role, $roles, true)) {
            $roles[] = $record->role;
        }

        return collect($roles)
            ->mapWithKeys(fn (Role $role): array => [$role->value => $role->label()])
            ->all();
    }

    // Pilihan wilayah: level harus cocok dengan peran, dan berada di dalam
    // subtree wilayah pembuat.
    /** @return array<int, string> */
    protected static function wilayahOptions(?string $role, ?User $record = null): array
    {
        $level = Role::tryFrom((string) $role)?->levelWilayah();

        if ($level === null) {
            return [];
        }

        $query = Wilayah::where('level', $level)->orderBy('path');

        $allowed = WilayahAccess::allowedWilayahIds(auth()->user());

        if ($allowed !== null) {
            $query->whereIn('id', $allowed);
        }

        return $query->get()
            ->mapWithKeys(fn (Wilayah $w): array => [$w->id => "{$w->nama} ({$w->rantaiIndukNama()})"])
            ->all();
    }

    // Benar bila pembuat berwenang memakai peran ini (Keputusan 6). Saat
    // mengedit, peran lama selalu diizinkan agar nilainya tidak berubah paksa.
    public static function pesanRoleTidakBoleh(?string $role, ?User $record = null): ?string
    {
        $peran = Role::tryFrom((string) $role);

        if ($peran === null) {
            return 'Peran tidak dikenal.';
        }

        if ($record !== null && $peran === $record->role) {
            return null;
        }

        $creator = auth()->user();

        if ($creator === null) {
            return null;
        }

        if (! in_array($peran, $creator->role->bolehMembuat(), true)) {
            return 'Anda tidak berwenang membuat atau memakai peran ini.';
        }

        return null;
    }

    // Dipakai halaman create/edit untuk memastikan wilayah cocok peran dan
    // berasal dari subtree pembuat (pertahanan kedua setelah pilihan form).
    public static function pesanWilayahTidakValid(?string $role, mixed $idWilayah): ?string
    {
        $peran = Role::tryFrom((string) $role);

        if ($peran === null) {
            return 'Peran tidak dikenal.';
        }

        $level = $peran->levelWilayah();

        if ($level === null) {
            return null;
        }

        $wilayah = Wilayah::find($idWilayah);

        if ($wilayah === null) {
            return "Wilayah untuk peran {$peran->label()} wajib dipilih.";
        }

        if ($wilayah->level !== $level) {
            return "Wilayah untuk peran {$peran->label()} harus berlevel {$level}.";
        }

        $allowed = WilayahAccess::allowedWilayahIds(auth()->user());

        if ($allowed !== null && ! in_array($wilayah->id, $allowed, true)) {
            return 'Wilayah berada di luar kewenangan Anda.';
        }

        return null;
    }
}
