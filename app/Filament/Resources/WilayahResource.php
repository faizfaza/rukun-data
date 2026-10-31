<?php

namespace App\Filament\Resources;

use App\Filament\Pages\JelajahWilayah;
use App\Filament\Resources\WilayahResource\Pages;
use App\Models\User;
use App\Models\Wilayah;
use App\Rules\IndukWilayah;
use App\Support\WilayahPicker;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Fase 1.5: pengelolaan wilayah (hanya superadmin lewat WilayahPolicy).
// Form membatasi pilihan induk ke level yang sah untuk level terpilih.
// Memindahkan induk wilayah tidak disediakan (observer menolaknya).
class WilayahResource extends Resource
{
    protected static ?string $model = Wilayah::class;

    protected static ?string $slug = 'wilayah';

    protected static ?string $navigationLabel = 'Wilayah';

    protected static ?string $modelLabel = 'wilayah';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('level')
                ->label('Level')
                ->options(function (): array {
                    if (auth()->user()?->isAdminRw()) {
                        return ['rt' => Wilayah::labelLevel('rt')];
                    }

                    return Wilayah::levelOptions();
                })
                ->default(fn (): ?string => auth()->user()?->isAdminRw() ? 'rt' : null)
                ->disabled(fn (string $operation): bool => $operation === 'edit' || (bool) auth()->user()?->isAdminRw())
                ->dehydrated()
                ->required()
                // Level tidak boleh berubah setelah dibuat (observer menolak).
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('parent_id', null)),

            Select::make('parent_id')
                ->label('Induk')
                ->getSearchResultsUsing(function (string $search, Get $get): array {
                    $ids = array_keys(WilayahPicker::search($search));

                    return Wilayah::whereIn('id', $ids)->whereIn('level', Wilayah::indukValid($get('level') ?? ''))->get()->mapWithKeys(fn ($w) => [$w->id => $w->kode.' · '.$w->alamatLengkap()])->all();
                })
                ->getOptionLabelUsing(fn ($value) => Wilayah::find($value)?->alamatLengkap())
                ->default(fn (): ?int => auth()->user()?->isAdminRw() ? auth()->user()->id_wilayah : null)
                ->disabled(fn (string $operation): bool => $operation === 'edit' || (bool) auth()->user()?->isAdminRw())
                ->dehydrated()
                ->searchable()

                ->required(fn (Get $get): bool => filled($get('level')) && $get('level') !== 'negara')
                ->visible(fn (Get $get): bool => filled($get('level')) && $get('level') !== 'negara')
                ->rule(fn (Get $get): IndukWilayah => new IndukWilayah(fn (): ?string => $get('level'))),

            TextInput::make('kode')
                ->label('Kode')
                ->required()
                ->maxLength(32)
                ->helperText('Kode sumber wilayah; RT/RW/dusun lokal tetap dapat menggunakan kode lokal.'),

            TextInput::make('nama')
                ->label('Nama')
                ->required()
                ->maxLength(100),

            Select::make('tipe')
                ->label('Tipe')
                ->options(['desa' => 'Desa', 'kelurahan' => 'Kelurahan'])
                ->visible(fn (Get $get): bool => $get('level') === 'desa'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable(),
                TextColumn::make('is_active')->label('Status lokal')->badge()->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Nonaktif')->color(fn ($state) => $state ? 'success' : 'gray'),
                TextColumn::make('efektif')->label('Operasional')->getStateUsing(fn (Wilayah $record) => $record->aktifEfektif() ? 'Aktif' : 'Terblokir / nonaktif')->badge()->color(fn (string $state) => $state === 'Aktif' ? 'success' : 'warning'),
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable()
                    ->weight(fn (Wilayah $record): string => $record->children_count > 0 ? 'semibold' : 'normal')
                    ->color(fn (Wilayah $record): string => $record->children_count > 0 ? 'primary' : 'gray')
                    ->icon(fn (Wilayah $record): string => $record->children_count > 0 ? 'heroicon-m-folder' : 'heroicon-m-map-pin')
                    ->iconColor(fn (Wilayah $record): string => $record->children_count > 0 ? 'primary' : 'gray')
                    ->description(function (Wilayah $record): ?string {
                        if ($record->level === 'dusun') {
                            return 'Wilayah dusun (membawahi RW di dalamnya)';
                        }
                        if ($record->level === 'rw' && $record->parent?->level === 'desa') {
                            return 'RW langsung — menginduk ke desa tanpa dusun';
                        }

                        return null;
                    })
                    ->url(fn (Wilayah $record): ?string => $record->children_count > 0
                        ? static::getUrl('index', ['parent' => $record->id])
                        : null
                    ),
                TextColumn::make('level')
                    ->label('Level')
                    ->badge()
                    ->color(fn (string $state, Wilayah $record): string => match ($state) {
                        'negara' => 'info',
                        'provinsi' => 'info',
                        'kabupaten_kota' => 'warning',
                        'kecamatan' => 'success',
                        'desa' => 'primary',
                        'dusun' => 'info',
                        'rw' => $record->parent?->level === 'desa' ? 'warning' : 'gray',
                        'rt' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(function (string $state, Wilayah $record): string {
                        if ($state === 'rw' && $record->parent?->level === 'desa') {
                            return 'RW Langsung';
                        }

                        return Wilayah::labelLevel($state);
                    }),
                TextColumn::make('rantai_induk')
                    ->label('Rantai induk')
                    ->getStateUsing(fn (Wilayah $record): string => $record->rantaiIndukNama() ?: '—')
                    ->wrap(),
                TextColumn::make('children_count')
                    ->label('Anak langsung')
                    ->counts('children')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'primary' : 'gray')
                    ->alignCenter()
                    ->url(fn (Wilayah $record): ?string => $record->children_count > 0
                        ? static::getUrl('index', ['parent' => $record->id])
                        : null
                    ),
            ])
            ->defaultSort('path')
            ->striped()
            ->emptyStateHeading('Belum ada wilayah')
            ->emptyStateDescription('Tambahkan wilayah mulai dari negara sebagai akar.')
            ->filters([
                SelectFilter::make('level')->label('Level')->options(Wilayah::levelOptions()),
            ])
            ->actions([
                Action::make('aktivasi')->label(fn (Wilayah $record) => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')->requiresConfirmation()
                    ->visible(fn (Wilayah $record) => auth()->user()->can('update', $record))
                    ->action(fn (Wilayah $record) => $record->update(['is_active' => ! $record->is_active])),
                Action::make('admin')->label('Admin di wilayah ini')->color('info')
                    ->visible(fn () => auth()->user()->can('viewAny', User::class))
                    ->url(fn (Wilayah $record) => UserResource::getUrl('index', ['wilayah' => $record->id])),
                Action::make('buka')
                    ->label('Buka')
                    ->icon('heroicon-m-arrow-right-circle')
                    ->color('primary')
                    ->button()
                    ->size('xs')
                    ->visible(fn (Wilayah $record): bool => $record->children_count > 0)
                    ->url(fn (Wilayah $record): string => static::getUrl('index', ['parent' => $record->id])),
                Action::make('keluarga')
                    ->label('Keluarga')
                    ->icon('heroicon-m-users')
                    ->color('success')
                    ->button()
                    ->size('xs')
                    ->visible(fn (Wilayah $record): bool => $record->level === 'rt')
                    ->url(fn (Wilayah $record): string => JelajahWilayah::getUrl(['wilayah' => $record->id])),
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Wilayah $record): void {
                        if ($record->children_count > 0 || $record->keluarga()->exists()) {
                            Notification::make()
                                ->danger()
                                ->title('Wilayah tidak dapat dihapus')
                                ->body('Masih ada anak wilayah atau keluarga di bawahnya.')
                                ->send();

                            $action->cancel();
                        }
                    }),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()->with(['parent'])->withCount('children')->orderBy('path');
        $user = auth()->user();

        if ($user === null || $user->isSuperadmin() || $user->isAdmin()) {
            return $query;
        }

        if ($user->isAdminRw() && $user->id_wilayah) {
            $rw = $user->wilayah;
            if ($rw) {
                return $query->where('path', 'like', $rw->path.'%');
            }
        }

        return $query->whereRaw('1 = 0');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWilayah::route('/'),
            'create' => Pages\CreateWilayah::route('/create'),
            'edit' => Pages\EditWilayah::route('/{record}/edit'),
        ];
    }

    // Pilihan induk: hanya wilayah berlevel yang sah untuk level terpilih.
    /** @return array<int, string> */
    protected static function indukOptions(?string $level): array
    {
        if (blank($level)) {
            return [];
        }

        $levels = Wilayah::indukValid($level);

        if ($levels === []) {
            return [];
        }

        return Wilayah::whereIn('level', $levels)
            ->orderBy('path')
            ->get()
            ->mapWithKeys(fn (Wilayah $w): array => [$w->id => "{$w->nama} ({$w->rantaiIndukNama()})"])
            ->all();
    }
}
