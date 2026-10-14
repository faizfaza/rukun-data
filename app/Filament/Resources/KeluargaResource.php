<?php

namespace App\Filament\Resources;

use App\Filament\Resources\KeluargaResource\Pages;
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

    protected static string|\UnitEnum|null $navigationGroup = 'Pendataan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            // DESIGN.md §7.3: dua bagian pendek (data keluarga + data ekonomi)
            // berbagi satu baris, sedangkan bagian anggota dibuat lebar penuh
            // supaya daftar anggota yang panjang tidak menyisakan ruang kosong.
            Grid::make(2)->schema([
                Section::make('Data Keluarga')->schema([
                    Select::make('id_wilayah')
                        ->label('RT')
                        ->options(static::rtOptions())
                        ->required()
                        ->searchable()
                        ->preload(),
                    TextInput::make('no_kk')
                        ->label('No KK')
                        ->required()
                        ->length(16)
                        ->rule('regex:/^[0-9]{16}$/')
                        ->validationMessages(['regex' => 'No KK harus 16 digit angka.']),
                    Textarea::make('alamat')
                        ->label('Alamat')
                        ->required()
                        ->maxLength(500)
                        ->columnSpanFull(),
                    TextInput::make('kode_pos')
                        ->label('Kode Pos')
                        ->required()
                        ->length(5)
                        ->rule('regex:/^[0-9]{5}$/'),
                ])->columns(2),

                Section::make('Data Ekonomi')->schema([
                    Repeater::make('ekonomi')
                        ->relationship()
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
                        Select::make('status_kawin')
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

    // Pilihan RT dibatasi ke wilayah pengguna (PRD F2); admin melihat semua.
    /** @return array<int, string> */
    public static function rtOptions(): array
    {
        $ids = WilayahAccess::allowedRtIds(auth()->user());

        $query = Wilayah::where('level', 'rt')->orderBy('kode');

        if ($ids !== null) {
            $query->whereIn('id', $ids);
        }

        return $query->pluck('nama', 'id')->all();
    }
}
