<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WilayahResource\Pages;
use App\Models\Wilayah;
use App\Rules\IndukWilayah;
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
                ->options(Wilayah::levelOptions())
                ->required()
                // Level tidak boleh berubah setelah dibuat (observer menolak).
                ->disabledOn('edit')
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('parent_id', null)),

            Select::make('parent_id')
                ->label('Induk')
                ->options(fn (Get $get): array => static::indukOptions($get('level')))
                ->searchable()
                ->preload()
                ->required(fn (Get $get): bool => filled($get('level')) && $get('level') !== 'negara')
                ->visible(fn (Get $get): bool => filled($get('level')) && $get('level') !== 'negara')
                ->rule(fn (Get $get): IndukWilayah => new IndukWilayah(fn (): ?string => $get('level'))),

            TextInput::make('kode')
                ->label('Kode')
                ->required()
                ->maxLength(32)
                ->helperText('Kode contoh untuk provinsi sampai desa; akan diganti data resmi Kemendagri.'),

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
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('level')
                    ->label('Level')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Wilayah::labelLevel($state)),
                TextColumn::make('rantai_induk')
                    ->label('Rantai induk')
                    ->getStateUsing(fn (Wilayah $record): string => $record->rantaiIndukNama() ?: '—')
                    ->wrap(),
                TextColumn::make('children_count')
                    ->label('Anak langsung')
                    ->counts('children')
                    ->alignCenter(),
            ])
            ->defaultSort('path')
            ->striped()
            ->emptyStateHeading('Belum ada wilayah')
            ->emptyStateDescription('Tambahkan wilayah mulai dari negara sebagai akar.')
            ->filters([
                SelectFilter::make('level')->label('Level')->options(Wilayah::levelOptions()),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make()
                    ->before(function (DeleteAction $action, Wilayah $record): void {
                        if ($record->children()->exists() || $record->keluarga()->exists()) {
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
        return parent::getEloquentQuery()->orderBy('path');
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
