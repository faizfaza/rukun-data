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
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
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
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

// Fase 2.5: manajemen akun. Superadmin melihat semua akun; admin_rw hanya
// akun admin_rt di RW-nya. Form dibatasi kewenangan pembuat (peran & wilayah).
// Tidak ada hapus, hanya aktif/nonaktif.
class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'pengguna';

    protected static ?string $navigationLabel = 'Kelola Admin';

    protected static ?string $modelLabel = 'admin';

    protected static ?string $breadcrumb = 'Kelola Admin';

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
                    ->label('Akun dan Peran')
                    ->default(fn () => match (Wilayah::find(request()->integer('id_wilayah'))?->level) {
                        'rw' => 'admin_rw', 'rt' => 'admin_rt', default => null
                    })
                    ->options(fn (?User $record): array => static::roleOptions($record))
                    ->required()
                    ->live()
                    ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                        if (in_array($state, [Role::Superadmin->value, Role::Admin->value], true)) {
                            $set('provinsi_id', null);
                            $set('kabkota_id', null);
                            $set('kecamatan_id', null);
                            $set('kelurahan_id', null);
                            $set('rw_id', null);
                            $set('rt_id', null);
                            $set('id_wilayah', null);
                        } elseif ($state === Role::AdminRw->value) {
                            $set('rt_id', null);
                            $set('id_wilayah', $get('rw_id'));
                        } elseif ($state === Role::AdminRt->value) {
                            $set('id_wilayah', $get('rt_id'));
                        }
                    })
                    ->disabled(fn (?User $record): bool => $record !== null && $record->is(auth()->user())),

            ])->columns(2)->columnSpanFull(),
            Section::make('Wilayah Penugasan')
                ->description(fn (Get $get): string => match ($get('role')) {
                    Role::AdminRw->value => 'Pilih wilayah penugasan secara terurut dari Provinsi hingga RW.',
                    Role::AdminRt->value => 'Pilih wilayah penugasan secara terurut dari Provinsi hingga RT.',
                    default => 'Pilih peran Admin RW atau Admin RT untuk menentukan wilayah penugasan.',
                })
                ->icon('heroicon-o-map-pin')
                ->visible(fn (Get $get): bool => in_array($get('role'), [Role::AdminRw->value, Role::AdminRt->value], true))
                ->schema([
                    Select::make('provinsi_id')
                        ->label('1. Provinsi')
                        ->options(fn (): array => Wilayah::operasional()->where('level', 'provinsi')->orderBy('nama')->pluck('nama', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('kabkota_id', null);
                            $set('kecamatan_id', null);
                            $set('kelurahan_id', null);
                            $set('rw_id', null);
                            $set('rt_id', null);
                            $set('id_wilayah', null);
                        }),

                    Select::make('kabkota_id')
                        ->label('2. Kabupaten/Kota')
                        ->options(fn (Get $get): array => Wilayah::operasional()->where('level', 'kabupaten_kota')
                            ->when($get('provinsi_id'), fn (Builder $q, $pid): Builder => $q->where('parent_id', $pid))
                            ->orderBy('nama')
                            ->pluck('nama', 'id')
                            ->all()
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->disabled(fn (Get $get): bool => blank($get('provinsi_id')))
                        ->afterStateUpdated(function (Set $set): void {
                            $set('kecamatan_id', null);
                            $set('kelurahan_id', null);
                            $set('rw_id', null);
                            $set('rt_id', null);
                            $set('id_wilayah', null);
                        }),

                    Select::make('kecamatan_id')
                        ->label('3. Kecamatan')
                        ->options(fn (Get $get): array => Wilayah::operasional()->where('level', 'kecamatan')
                            ->when($get('kabkota_id'), fn (Builder $q, $kid): Builder => $q->where('parent_id', $kid))
                            ->orderBy('nama')
                            ->pluck('nama', 'id')
                            ->all()
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->disabled(fn (Get $get): bool => blank($get('kabkota_id')))
                        ->afterStateUpdated(function (Set $set): void {
                            $set('kelurahan_id', null);
                            $set('rw_id', null);
                            $set('rt_id', null);
                            $set('id_wilayah', null);
                        }),

                    Select::make('kelurahan_id')
                        ->label('4. Desa/Kelurahan')
                        ->options(fn (Get $get): array => Wilayah::operasional()->where('level', 'desa')
                            ->when($get('kecamatan_id'), fn (Builder $q, $kid): Builder => $q->where('parent_id', $kid))
                            ->orderBy('nama')
                            ->pluck('nama', 'id')
                            ->all()
                        )
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->disabled(fn (Get $get): bool => blank($get('kecamatan_id')))
                        ->afterStateUpdated(function (Set $set): void {
                            $set('rw_id', null);
                            $set('rt_id', null);
                            $set('id_wilayah', null);
                        }),

                    Select::make('rw_id')
                        ->label(fn (Get $get): string => $get('role') === Role::AdminRw->value ? '5. RW (Tujuan Penugasan)' : '5. RW')
                        ->options(function (Get $get): array {
                            $desaId = $get('kelurahan_id');
                            if (! $desaId) {
                                return [];
                            }

                            $dusunIds = Wilayah::operasional()->where('level', 'dusun')
                                ->where('parent_id', $desaId)
                                ->pluck('id')
                                ->all();

                            return Wilayah::operasional()->where('level', 'rw')
                                ->where(function (Builder $q) use ($desaId, $dusunIds): void {
                                    $q->where('parent_id', $desaId);
                                    if ($dusunIds !== []) {
                                        $q->orWhereIn('parent_id', $dusunIds);
                                    }
                                })
                                ->with('parent')
                                ->orderBy('kode')
                                ->get()
                                ->mapWithKeys(function (Wilayah $w): array {
                                    if ($w->parent?->level === 'dusun') {
                                        $dusunNama = str_starts_with(strtolower($w->parent->nama), 'dusun')
                                            ? $w->parent->nama
                                            : "Dusun {$w->parent->nama}";

                                        return [$w->id => "{$w->nama} ({$dusunNama})"];
                                    }

                                    return [$w->id => "{$w->nama} (RW Langsung)"];
                                })
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->disabled(fn (Get $get): bool => blank($get('kelurahan_id')))
                        ->afterStateUpdated(function (?int $state, Set $set, Get $get): void {
                            if ($get('role') === Role::AdminRw->value) {
                                $set('id_wilayah', $state);
                            } else {
                                $set('rt_id', null);
                                $set('id_wilayah', null);
                            }
                        }),

                    Select::make('rt_id')
                        ->label('6. RT (Tujuan Penugasan)')
                        ->options(function (Get $get): array {
                            $rwId = $get('rw_id');
                            if (! $rwId) {
                                return [];
                            }

                            return Wilayah::operasional()->where('level', 'rt')
                                ->where('parent_id', $rwId)
                                ->orderBy('kode')
                                ->get()
                                ->mapWithKeys(fn (Wilayah $w): array => [$w->id => "{$w->kode} · {$w->nama}"])
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => $get('role') === Role::AdminRt->value)
                        ->disabled(fn (Get $get): bool => blank($get('rw_id')))
                        ->afterStateUpdated(function (?int $state, Set $set): void {
                            $set('id_wilayah', $state);
                        }),

                    Hidden::make('id_wilayah')
                        ->default(fn () => request()->integer('id_wilayah') ?: null)
                        ->dehydrated()
                        ->afterStateHydrated(function (Hidden $component, ?int $state, Set $set, ?User $record): void {
                            $targetId = $state ?? $record?->id_wilayah ?? request()->integer('id_wilayah');
                            if (! $targetId) {
                                return;
                            }
                            $target = Wilayah::with('parent')->find($targetId);
                            if (! $target) {
                                return;
                            }
                            $ancestors = $target->ancestors()->keyBy('level');
                            $set('provinsi_id', $ancestors->get('provinsi')?->id);
                            $set('kabkota_id', $ancestors->get('kabupaten_kota')?->id);
                            $set('kecamatan_id', $ancestors->get('kecamatan')?->id);
                            $set('kelurahan_id', $ancestors->get('desa')?->id);

                            if ($target->level === 'rw') {
                                $set('rw_id', $target->id);
                                $set('id_wilayah', $target->id);
                            } elseif ($target->level === 'rt') {
                                $set('rw_id', $ancestors->get('rw')?->id);
                                $set('rt_id', $target->id);
                                $set('id_wilayah', $target->id);
                            }
                        }),

                    Placeholder::make('wilayah_preview')
                        ->label('Status Wilayah Penugasan')
                        ->content(function (Get $get, ?User $record): HtmlString {
                            $id = $get('id_wilayah') ?: $record?->id_wilayah;
                            $role = $get('role') ?: $record?->role?->value;

                            if (! $id) {
                                $targetLevel = $role === Role::AdminRw->value ? 'RW' : ($role === Role::AdminRt->value ? 'RT' : null);
                                if (! $targetLevel) {
                                    return new HtmlString('<span class="text-sm text-gray-500">Pilih peran akun terlebih dahulu.</span>');
                                }

                                return new HtmlString('<div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800 flex items-center gap-2">
                                    <span class="font-semibold">Perhatian:</span> Lengkapi pilihan wilayah di atas secara berurutan hingga tingkat <strong>'.$targetLevel.'</strong> untuk menetapkan penugasan admin.
                                </div>');
                            }

                            $w = Wilayah::find($id);
                            if (! $w) {
                                return new HtmlString('<span class="text-sm text-gray-500">—</span>');
                            }

                            $isOperasional = $w->aktifEfektif();
                            $statusBadge = $isOperasional
                                ? '<span class="inline-flex items-center gap-1 rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-800">Aktif</span>'
                                : '<span class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-xs font-semibold text-rose-800">Nonaktif / Down</span>';

                            return new HtmlString('<div class="rounded-xl bg-blue-50/70 border border-blue-200 p-3 text-sm text-blue-950 flex flex-col gap-1">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-lg bg-blue-600 px-2 py-0.5 text-xs font-bold text-white uppercase tracking-wider">'.e(strtoupper($w->level)).'</span>
                                        <span class="font-bold text-base text-gray-950">'.e($w->nama).'</span>
                                        '.$statusBadge.'
                                    </div>
                                    <span class="font-mono text-xs text-blue-700 font-semibold">Kode: '.e($w->kode).'</span>
                                </div>
                                <p class="text-xs text-gray-600 mt-0.5">'.e($w->alamatLengkap()).'</p>
                            </div>');
                        })
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
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
                    ->sortable()
                    ->wrap(),

                TextColumn::make('wilayah.nama')->label('Wilayah penugasan')->placeholder('Tanpa penugasan')
                    ->description(fn (User $record) => $record->wilayah?->alamatLengkap())
                    ->url(fn (User $record) => $record->id_wilayah ? WilayahResource::getUrl('index', ['parent' => $record->id_wilayah]) : null)
                    ->wrap(),
            ])
            ->persistSearchInSession()
            ->searchable(false)
            ->striped()
            ->emptyStateHeading('Belum ada akun')
            ->emptyStateDescription('Tambahkan akun admin RT untuk wilayah Anda.')
            ->actions([
                EditAction::make()
                    ->iconButton()
                    ->tooltip('Ubah')
                    ->url(fn (User $record, $livewire) => static::getUrl('edit', ['record' => $record, 'context' => $livewire->wilayah ?? null])),
                Action::make('toggleAktif')
                    ->label(fn (User $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                    ->icon(fn (User $record): string => $record->is_active ? 'heroicon-m-no-symbol' : 'heroicon-m-check-circle')
                    ->color(fn (User $record): string => $record->is_active ? 'danger' : 'success')
                    ->iconButton()
                    ->tooltip(fn (User $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
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
        $query = parent::getEloquentQuery()->with(['wilayah.'.Wilayah::RELASI_LELUHUR]);
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

        $query = Wilayah::where('level', $level)
            ->with([Wilayah::RELASI_LELUHUR])
            ->orderBy('path');

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
