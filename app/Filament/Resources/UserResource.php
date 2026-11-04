<?php

namespace App\Filament\Resources;

use App\Enums\Role;
use App\Filament\Resources\UserResource\Pages;
use App\Filament\Schemas\Profil;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\WilayahAccess;
use App\Support\WilayahPicker;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Fase 2.5: manajemen akun. Superadmin melihat semua akun; admin_rw hanya
// akun admin_rt di RW-nya. Form dibatasi kewenangan pembuat (peran & wilayah).
// Tidak ada hapus, hanya aktif/nonaktif.
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'pengguna';

    protected static ?string $navigationLabel = 'Kelola Admin';

    protected static ?string $modelLabel = 'admin';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun dan Peran')->schema([
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
                    ->label('Akun dan Peran')->default(fn () => match (Wilayah::find(request()->integer('id_wilayah'))?->level) {
                        'rw' => 'admin_rw', 'rt' => 'admin_rt', default => null
                    })
                    ->options(fn (?User $record): array => static::roleOptions($record))
                    ->required()
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('id_wilayah', null))
                    ->disabled(fn (?User $record): bool => $record !== null && $record->is(auth()->user())),

            ])->columns(2)->columnSpanFull(),
            Section::make('Wilayah Penugasan')->schema([
                Select::make('id_wilayah')
                    ->label('Wilayah Penugasan')->default(fn () => request()->integer('id_wilayah') ?: null)
                    ->getSearchResultsUsing(fn (string $search, Get $get) => WilayahPicker::search($search, Role::tryFrom((string) $get('role'))?->levelWilayah() ?? '__none'))
                    ->getOptionLabelUsing(fn ($value) => Wilayah::find($value)?->alamatLengkap())
                    ->searchable()

                    ->required(fn (Get $get): bool => ! in_array($get('role'), [Role::Superadmin->value, Role::Admin->value], true))
                    ->visible(fn (Get $get): bool => ! in_array($get('role'), [Role::Superadmin->value, Role::Admin->value], true)),

            ])->columnSpanFull(),
            Profil::admin(),
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
                    ->searchable(['name', 'username'])
                    ->sortable(),

                TextColumn::make('wilayah.nama')->label('Wilayah penugasan')->placeholder('Admin Global')
                    ->description(fn (User $record) => $record->wilayah?->alamatLengkap())
                    ->url(fn (User $record) => $record->id_wilayah ? WilayahResource::getUrl('index', ['parent' => $record->id_wilayah]) : null),
            ])
            ->persistSearchInSession()->persistFiltersInSession()
            ->filters([
                SelectFilter::make('role')->label('Peran')->options(Role::options()),
                TernaryFilter::make('is_active')->label('Status akun'),
            ])
            ->filtersLayout(FiltersLayout::AboveContent)
            ->striped()
            ->emptyStateHeading('Belum ada akun')
            ->emptyStateDescription('Tambahkan akun admin RT untuk wilayah Anda.')
            ->actions([
                EditAction::make()->url(fn (User $record, $livewire) => static::getUrl('edit', ['record' => $record, 'context' => $livewire->wilayah ?? null])),
                Action::make('toggleAktif')
                    ->label(fn (User $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (User $record): string => $record->is_active ? 'heroicon-m-no-symbol' : 'heroicon-m-check-circle')
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    // Admin tidak boleh menonaktifkan dirinya sendiri,
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

    // Hanya superadmin dan admin yang mengelola akun admin.
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
