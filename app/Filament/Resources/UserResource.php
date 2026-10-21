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
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
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
                ->required(fn (Get $get): bool => $get('role') !== Role::Superadmin->value)
                ->visible(fn (Get $get): bool => $get('role') !== Role::Superadmin->value),

            Toggle::make('is_active')
                ->label('Aktif')
                ->default(true)
                ->helperText('Akun tidak dihapus; nonaktifkan bila tidak dipakai lagi.')
                ->disabled(fn (?User $record): bool => $record !== null && $record->is(auth()->user())),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('username')->label('Username')->searchable()->sortable(),
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('role')
                    ->label('Peran')
                    ->badge()
                    ->formatStateUsing(fn (Role $state): string => $state->label()),
                TextColumn::make('wilayah.nama')->label('Wilayah')->placeholder('—'),
                TextColumn::make('is_active')
                    ->label('Status')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn (bool $state): string => $state ? 'success' : 'danger')
                    ->icon(fn (bool $state): string => $state ? 'heroicon-m-check-circle' : 'heroicon-m-x-circle'),
            ])
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
                    // Pengguna tidak boleh menonaktifkan dirinya sendiri.
                    ->visible(fn (User $record): bool => ! $record->is(auth()->user()))
                    ->authorize(fn (User $record): bool => auth()->user()->can('update', $record))
                    ->action(function (User $record): void {
                        $record->update(['is_active' => ! $record->is_active]);

                        Notification::make()
                            ->title('Akun diperbarui')
                            ->body($record->is_active ? 'Akun diaktifkan.' : 'Akun dinonaktifkan.')
                            ->success()
                            ->send();
                    }),
            ]);
    }

    // admin_rw hanya melihat akun admin_rt di wilayahnya; superadmin semua.
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if ($user === null || $user->isSuperadmin()) {
            return $query;
        }

        $ids = WilayahAccess::allowedWilayahIds($user);

        return $query->where('role', Role::AdminRt->value)
            ->whereIn('id_wilayah', $ids ?? []);
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
