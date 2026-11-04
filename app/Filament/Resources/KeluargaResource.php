<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KeluargaResource\Pages;
use App\Filament\Schemas\Profil;
use App\Models\Keluarga;
use App\Models\RefAgama;
use App\Models\RefHubungan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Models\Wilayah;
use App\Support\SensitiveData;
use App\Support\WilayahAccess;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// PRD §6/§7: entri keluarga + anggota dalam satu halaman dan satu transaksi;
// daftar hanya menampilkan No KK tersamar, pencarian via hash (§9.4).
class KeluargaResource extends Resource
{
    protected static ?string $model = Keluarga::class;

    // Slug tanpa "s": /admin/keluarga, bukan /admin/keluargas.
    protected static ?string $slug = 'keluarga';

    protected static ?string $navigationLabel = 'Keluarga';

    // Fase 3.6: daftar datar keluarga tidak masuk menu; halaman buat, edit, dan
    // view tetap dipakai dari tree (Jelajah Wilayah).
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Pendataan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Wilayah Penugasan RT')
                ->description(fn (): string => auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin()
                    ? 'Pilih wilayah secara bertahap (terurut) mulai dari Provinsi hingga RT tujuan.'
                    : 'Wilayah penugasan RT tempat keluarga ini didaftarkan.'
                )
                ->icon('heroicon-o-map-pin')
                ->schema([
                    Select::make('provinsi_id')
                        ->label('1. Provinsi')
                        ->options(fn (): array => Wilayah::operasional()->where('level', 'provinsi')->orderBy('nama')->pluck('nama', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->visible(fn (): bool => (bool) (auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin()))
                        ->disabled(fn (): bool => (bool) request()->integer('id_wilayah'))
                        ->afterStateUpdated(function (Set $set): void {
                            $set('kabkota_id', null);
                            $set('kecamatan_id', null);
                            $set('kelurahan_id', null);
                            $set('rw_id', null);
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
                        ->visible(fn (): bool => (bool) (auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin()))
                        ->disabled(fn (Get $get): bool => (bool) request()->integer('id_wilayah') || blank($get('provinsi_id')))
                        ->afterStateUpdated(function (Set $set): void {
                            $set('kecamatan_id', null);
                            $set('kelurahan_id', null);
                            $set('rw_id', null);
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
                        ->visible(fn (): bool => (bool) (auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin()))
                        ->disabled(fn (Get $get): bool => (bool) request()->integer('id_wilayah') || blank($get('kabkota_id')))
                        ->afterStateUpdated(function (Set $set): void {
                            $set('kelurahan_id', null);
                            $set('rw_id', null);
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
                        ->visible(fn (): bool => (bool) (auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin()))
                        ->disabled(fn (Get $get): bool => (bool) request()->integer('id_wilayah') || blank($get('kecamatan_id')))
                        ->afterStateUpdated(function (Set $set): void {
                            $set('rw_id', null);
                            $set('id_wilayah', null);
                        }),

                    Select::make('rw_id')
                        ->label('5. RW')
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
                        ->visible(fn (): bool => (bool) (auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin()))
                        ->disabled(fn (Get $get): bool => (bool) request()->integer('id_wilayah') || blank($get('kelurahan_id')))
                        ->afterStateUpdated(function (Set $set): void {
                            $set('id_wilayah', null);
                        }),

                    Select::make('id_wilayah')
                        ->label(fn (): string => auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin() ? '6. RT (Tujuan)' : 'RT')
                        ->options(fn (Get $get, ?Keluarga $record): array => static::rtOptions($get('rw_id'), $get('id_wilayah') ?: $record?->id_wilayah ?: request()->integer('id_wilayah')) + ($record ? [$record->id_wilayah => $record->wilayah->alamatLengkap()] : []))
                        ->searchable()
                        ->preload()
                        ->live()
                        ->required()
                        ->default(fn (): ?int => auth()->user()?->isAdminRt()
                            ? auth()->user()->id_wilayah
                            : (request()->integer('id_wilayah') ?: null)
                        )
                        ->disabled(fn (Get $get): bool => (bool) request()->integer('id_wilayah')
                            || (bool) auth()->user()?->isAdminRt()
                            || ((auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin()) && blank($get('rw_id')) && blank($get('id_wilayah')))
                        )
                        ->dehydrated()
                        ->afterStateHydrated(function (Select $component, ?int $state, Set $set, ?Keluarga $record): void {
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
                            $set('rw_id', $ancestors->get('rw')?->id);
                        })
                        ->afterStateUpdated(function (?int $state, Set $set): void {
                            if (! $state) {
                                return;
                            }
                            $target = Wilayah::with('parent')->find($state);
                            if (! $target) {
                                return;
                            }
                            $ancestors = $target->ancestors()->keyBy('level');
                            $set('provinsi_id', $ancestors->get('provinsi')?->id);
                            $set('kabkota_id', $ancestors->get('kabupaten_kota')?->id);
                            $set('kecamatan_id', $ancestors->get('kecamatan')?->id);
                            $set('kelurahan_id', $ancestors->get('desa')?->id);
                            $set('rw_id', $ancestors->get('rw')?->id);
                        })
                        ->helperText(function (Get $get, ?Keluarga $record): ?string {
                            $user = auth()->user();
                            if ($user?->isAdminRt() && $user->wilayah) {
                                return "Wilayah penugasan RT Anda: {$user->wilayah->alamatLengkap()}";
                            }
                            if ($user?->isAdminRw() && $user->wilayah) {
                                return "Pilih RT di bawah RW penugasan Anda: {$user->wilayah->alamatLengkap()}";
                            }

                            $rtId = $get('id_wilayah') ?: $record?->id_wilayah ?: request()->integer('id_wilayah');
                            if (! $rtId) {
                                return 'Pilih langkah 1 sampai 5 untuk memfilter RT tujuan.';
                            }
                            $rt = Wilayah::find($rtId);
                            if (! $rt) {
                                return null;
                            }

                            return '📍 Lokasi Terpilih: '.$rt->alamatLengkap();
                        })
                        ->columnSpan(fn (): int => auth()->user()?->isSuperadmin() || auth()->user()?->isAdmin() ? 1 : 3),
                ])
                ->columns(3),

            // DESIGN.md §7.3: dua bagian pendek (data keluarga + data ekonomi)
            // berbagi satu baris, sedangkan bagian anggota dibuat lebar penuh
            // supaya daftar anggota yang panjang tidak menyisakan ruang kosong.
            Grid::make(2)->schema([
                Section::make('Data Keluarga')->description(fn (?Keluarga $record) => $record && ! $record->wilayah->aktifEfektif() ? 'Wilayah nonaktif: koreksi data lama diperbolehkan; penambahan data baru diblokir sampai wilayah dan induknya aktif.' : null)->schema([
                    TextInput::make('no_kk')
                        ->label('No KK')
                        ->required()
                        ->length(16)
                        ->rule('regex:/^[0-9]{16}$/')
                        ->validationMessages(['regex' => 'No KK harus 16 digit angka.']),
                    TextInput::make('kode_pos')
                        ->label('Kode Pos')
                        ->required()
                        ->length(5)
                        ->rule('regex:/^[0-9]{5}$/'),
                    Textarea::make('alamat')
                        ->label('Alamat')
                        ->required()
                        ->maxLength(500)
                        ->columnSpanFull(),
                ])->columns(2),

                Section::make('Data Ekonomi')->schema([
                    Repeater::make('ekonomi')
                        ->relationship()
                        ->mutateRelationshipDataBeforeFillUsing(function (array $data, ?Keluarga $record): array {
                            $item = $record?->ekonomi->firstWhere('id', $data['id'] ?? null);
                            if ($item) {
                                $data['kategori_ekonomi'] = $item->kategori_ekonomi;
                            }

                            return $data;
                        })
                        ->label('')
                        ->maxItems(1)
                        ->defaultItems(0)
                        ->addActionLabel('Tambah data ekonomi')
                        ->columns(2)
                        ->schema([
                            TextInput::make('tahun_data')
                                ->label('Tahun')
                                ->required()
                                ->numeric()
                                ->minValue(2000)
                                ->maxValue((int) now()->format('Y'))
                                ->default((int) now()->format('Y')),
                            Select::make('kategori_ekonomi')
                                ->label('Kategori')
                                ->options([
                                    'Miskin' => 'Miskin',
                                    'Rentan Miskin' => 'Rentan Miskin',
                                    'Menengah' => 'Menengah',
                                    'Mampu' => 'Mampu',
                                ])
                                ->required(),
                        ]),
                ])->collapsible(),
            ])->columnSpanFull(),

            Section::make('Anggota Keluarga')->schema([
                Repeater::make('anggota')
                    ->relationship()
                    ->mutateRelationshipDataBeforeFillUsing(function (array $data, ?Keluarga $record): array {
                        $item = $record?->anggota->firstWhere('id', $data['id'] ?? null);
                        if ($item) {
                            $data['nik'] = $item->nik;
                            $data['nama_lengkap'] = $item->nama_lengkap;
                            $data['nama_ayah'] = $item->nama_ayah;
                            $data['nama_ibu'] = $item->nama_ibu;
                            $data['penghasilan_bulanan'] = $item->penghasilan_bulanan;
                            $data['profil'] = $item->profil;
                        }

                        return $data;
                    })
                    ->label('')
                    ->minItems(1)
                    ->addActionLabel('Tambah anggota')
                    // DESIGN.md §7.3.2: kartu anggota bisa dibuka/tutup dan kartu
                    // pertama otomatis berhubungan "Kepala keluarga".
                    ->collapsible()
                    ->default(fn (): array => [['id_hubungan' => RefHubungan::KEPALA]])
                    ->columns(3)
                    ->schema([
                        TextInput::make('nik')
                            ->label('NIK')
                            ->required()
                            ->length(16)
                            ->rule('regex:/^[0-9]{16}$/'),
                        TextInput::make('nama_lengkap')
                            ->label('Nama lengkap')
                            ->required()
                            ->maxLength(100),
                        TextInput::make('tempat_lahir')
                            ->label('Tempat lahir')
                            ->required()
                            ->maxLength(50),
                        DatePicker::make('tanggal_lahir')
                            ->label('Tanggal lahir')
                            ->required()
                            ->maxDate(now()),
                        Select::make('jenis_kelamin')
                            ->label('Jenis kelamin')
                            ->options(['L' => 'Laki-laki', 'P' => 'Perempuan'])
                            ->required(),
                        Select::make('status_kawin')->live()
                            ->label('Status kawin')
                            ->options([
                                'belum_kawin' => 'Belum kawin',
                                'kawin' => 'Kawin',
                                'cerai_hidup' => 'Cerai hidup',
                                'cerai_mati' => 'Cerai mati',
                            ])
                            ->required(),
                        Select::make('id_agama')
                            ->label('Agama')
                            ->options(RefAgama::pluck('nama', 'id'))
                            ->required(),
                        Select::make('id_pendidikan')
                            ->label('Pendidikan')
                            ->options(RefPendidikan::pluck('nama', 'id'))
                            ->required(),
                        Select::make('id_pekerjaan')
                            ->label('Pekerjaan')
                            ->options(RefPekerjaan::pluck('nama', 'id'))
                            ->required(),
                        Select::make('id_hubungan')
                            ->label('Hubungan')
                            ->options(RefHubungan::pluck('nama', 'id'))
                            ->required()
                            ->live(),
                        ...Profil::anggota(),
                        TextInput::make('nama_ayah')
                            ->label('Nama ayah'),
                        TextInput::make('nama_ibu')
                            ->label('Nama ibu'),
                        TextInput::make('penghasilan_bulanan')
                            ->label('Penghasilan/bulan (Rp)')
                            ->numeric()
                            ->minValue(0),
                    ]),
            ]),
        ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                // §7.4: daftar menampilkan No KK tersamar; mask dihitung di
                // server, nilai utuh tidak pernah dirender di daftar (§7.5).
                TextColumn::make('no_kk_masked')
                    ->label('No KK')
                    // DESIGN.md §7.2: ikon gembok menandai kolom tersamarkan.
                    ->icon('heroicon-m-lock-closed')
                    ->iconColor('gray')
                    ->getStateUsing(fn (Keluarga $record): ?string => $record->no_kk_masked),
                TextColumn::make('kepala.nama_lengkap')
                    ->label('Kepala keluarga'),
                TextColumn::make('wilayah.nama')
                    ->label('RT'),
                TextColumn::make('anggota_count')
                    ->label('Anggota')
                    ->counts('anggota'),
                TextColumn::make('kode_pos')
                    ->label('Kode pos'),
                TextColumn::make('created_at')
                    ->label('Dibuat')
                    ->dateTime('d M Y H:i')
                    ->sortable(),
            ])
            // DESIGN.md §8: baris selang seling sangat tipis.
            ->striped()
            // Kotak cari: No KK / NIK persis, atau nama anggota (indeks butir).
            ->searchable()
            ->searchPlaceholder('Cari nama, No KK, atau NIK')
            ->searchUsing(fn (Builder $query, string $search): Builder => static::applyPencarian($query, $search))
            // DESIGN.md §9.4: keadaan kosong mengajak bertindak.
            ->emptyStateHeading('Belum ada data keluarga')
            ->emptyStateDescription('Pilih Tambah keluarga untuk memulai.')
            ->filters([
                Filter::make('no_kk')
                    ->form([TextInput::make('no_kk')->label('No KK persis')->length(16)->helperText('Masukkan nomor lengkap 16 digit.')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['no_kk'] ?? null),
                        fn (Builder $query): Builder => $query->where(
                            'no_kk_hash',
                            SensitiveData::hash(SensitiveData::digits($data['no_kk']))
                        ),
                    )),
                Filter::make('nik')
                    ->form([TextInput::make('nik')->label('NIK anggota persis')->length(16)->helperText('Masukkan nomor lengkap 16 digit.')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['nik'] ?? null),
                        fn (Builder $query): Builder => $query->whereHas(
                            'anggota',
                            fn (Builder $query): Builder => $query->where(
                                'nik_hash',
                                SensitiveData::hash(SensitiveData::digits($data['nik']))
                            ),
                        ),
                    )),
                TrashedFilter::make(),
            ])
            ->actions([ViewAction::make(), EditAction::make(), DeleteAction::make(), RestoreAction::make()])
            ->bulkActions([DeleteBulkAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListKeluarga::route('/'),
            'create' => Pages\CreateKeluarga::route('/create'),
            'view' => Pages\ViewKeluarga::route('/{record}'),
            'edit' => Pages\EditKeluarga::route('/{record}/edit'),
        ];
    }

    // Pencarian: No KK / NIK persis (hash) atau nama anggota (indeks butir).
    protected static function applyPencarian(Builder $query, string $search): Builder
    {
        $digits = SensitiveData::digits($search);
        $tokens = SensitiveData::nameTokens($search);
        $isNik = strlen($digits) === 16;

        if (! $isNik && ($tokens === [])) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(function (Builder $query) use ($digits, $tokens, $isNik): void {
            if ($isNik) {
                $hash = SensitiveData::hash($digits);

                $query->orWhere('no_kk_hash', $hash)
                    ->orWhereHas('anggota', fn (Builder $query): Builder => $query->where('nik_hash', $hash));
            }

            if ($tokens !== []) {
                $query->orWhereHas('anggota', function (Builder $query) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $query->where('nama_tokens', 'like', '%'.$token.'%');
                    }
                });
            }
        });
    }

    // Pilihan RT dibatasi ke wilayah pengguna (PRD F2); mendukung filter bertingkat rwId.
    /** @return array<int, string> */
    public static function rtOptions(?int $rwId = null, ?int $selectedId = null): array
    {
        $user = auth()->user();

        // 1. Petugas RT: hanya RT penugasan
        if ($user?->isAdminRt() && $user->id_wilayah) {
            $rt = $user->wilayah;

            return $rt ? [$rt->id => "{$rt->nama} ({$rt->rantaiIndukNama()})"] : [];
        }

        // 2. Petugas RW: hanya RT di bawah RW penugasan
        if ($user?->isAdminRw() && $user->id_wilayah) {
            return Wilayah::operasional()->where('level', 'rt')
                ->where('parent_id', $user->id_wilayah)
                ->orderBy('kode')
                ->pluck('nama', 'id')
                ->all();
        }

        // 3. Jika ada RW terpilih pada cascading dropdown
        if ($rwId) {
            return Wilayah::operasional()->where('level', 'rt')
                ->where('parent_id', $rwId)
                ->orderBy('kode')
                ->pluck('nama', 'id')
                ->all();
        }

        // 4. Jika ada selectedId (misal edit atau dipanggil via query/test)
        if ($selectedId) {
            $rt = Wilayah::find($selectedId);
            if ($rt) {
                return [$rt->id => "{$rt->nama} ({$rt->rantaiIndukNama()})"];
            }
        }

        // 5. Fallback semua RT yang diizinkan
        $ids = WilayahAccess::allowedRtIds($user);
        $query = Wilayah::operasional()->where('level', 'rt')->orderBy('path');
        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->get()
            ->mapWithKeys(fn (Wilayah $w): array => [
                $w->id => "{$w->nama} ({$w->rantaiIndukNama()})",
            ])
            ->all();
    }
}
