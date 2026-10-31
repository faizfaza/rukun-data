<?php

namespace App\Filament\Resources;

use App\Filament\Pages\JelajahWilayah;
use App\Filament\Resources\AnggotaResource\Pages;
use App\Filament\Schemas\Profil;
use App\Models\Anggota;
use App\Models\RefAgama;
use App\Models\RefHubungan;
use App\Models\RefPekerjaan;
use App\Models\RefPendidikan;
use App\Support\SensitiveData;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// PRD F5/F7: profil individu + pencarian NIK persis. Entri anggota hanya
// lewat halaman Keluarga (F4), sehingga resource ini tanpa halaman create.
class AnggotaResource extends Resource
{
    protected static ?string $model = Anggota::class;

    // Slug tanpa "s": /admin/anggota, bukan /admin/anggotas.
    protected static ?string $slug = 'anggota';

    protected static ?string $navigationLabel = 'Anggota';

    // Fase 3.6: daftar datar anggota tidak boleh ada; halaman view/edit tetap
    // dipakai dari tree (Keluarga → anggota).
    protected static bool $shouldRegisterNavigation = false;

    protected static string|\UnitEnum|null $navigationGroup = 'Pendataan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas')->schema([
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
                    ->required(),
                ...Profil::anggota(),
                TextInput::make('nama_ayah')->label('Nama ayah'),
                TextInput::make('nama_ibu')->label('Nama ibu'),
                TextInput::make('penghasilan_bulanan')
                    ->label('Penghasilan/bulan (Rp)')
                    ->numeric()
                    ->minValue(0),
            ])->columns(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nama_lengkap')
                    ->label('Nama'),
                TextColumn::make('nik_masked')
                    ->label('NIK')
                    // DESIGN.md §7.2: ikon gembok menandai kolom tersamarkan.
                    ->icon('heroicon-m-lock-closed')
                    ->iconColor('gray')
                    ->getStateUsing(fn (Anggota $record): ?string => $record->nik_masked),
                TextColumn::make('hubungan.nama')
                    ->label('Hubungan'),
                TextColumn::make('pendidikan.nama')
                    ->label('Pendidikan'),
                TextColumn::make('pekerjaan.nama')
                    ->label('Pekerjaan'),
                TextColumn::make('keluarga.wilayah.nama')
                    ->label('RT'),
                TextColumn::make('umur')
                    ->label('Umur')
                    ->getStateUsing(fn (Anggota $record): ?int => $record->tanggal_lahir?->diffInYears(now())),
            ])
            // DESIGN.md §8: baris selang seling sangat tipis.
            ->striped()
            // Kotak cari: nama (indeks butir) atau NIK persis.
            ->searchable()
            ->searchPlaceholder('Cari nama atau NIK')
            ->searchUsing(fn (Builder $query, string $search): Builder => static::applyPencarian($query, $search))
            // DESIGN.md §9.4: keadaan kosong mengajak bertindak.
            ->emptyStateHeading('Belum ada data anggota')
            ->emptyStateDescription('Anggota ditambahkan lewat halaman Keluarga.')
            ->filters([
                Filter::make('nik')
                    ->form([TextInput::make('nik')->label('NIK persis')->length(16)->helperText('Masukkan nomor lengkap 16 digit.')])
                    ->query(fn (Builder $query, array $data): Builder => $query->when(
                        filled($data['nik'] ?? null),
                        fn (Builder $query): Builder => $query->where(
                            'nik_hash',
                            SensitiveData::hash(SensitiveData::digits($data['nik']))
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
            'view' => Pages\ViewAnggota::route('/{record}'),
            'edit' => Pages\EditAnggota::route('/{record}/edit'),
        ];
    }

    // Tidak ada halaman index (daftar datar anggota dihapus); tautan "kembali"
    // pada tata letak halaman diarahkan ke Jelajah Wilayah.
    public static function getIndexUrl(array $parameters = [], bool $isAbsolute = true, ?string $panel = null, ?Model $tenant = null, bool $shouldGuessMissingParameters = false): string
    {
        return JelajahWilayah::getUrl();
    }

    // Nama dicari lewat indeks butir (semua kata harus cocok), NIK lewat hash
    // persis. Nama tidak pernah dibandingkan dalam bentuk terbaca.
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
                $query->orWhere('nik_hash', SensitiveData::hash($digits));
            }

            if ($tokens !== []) {
                $query->orWhere(function (Builder $query) use ($tokens): void {
                    foreach ($tokens as $token) {
                        $query->where('nama_tokens', 'like', '%'.$token.'%');
                    }
                });
            }
        });
    }
}
