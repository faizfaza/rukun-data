<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WilayahResource\Pages;
use App\Models\User;
use App\Models\Wilayah;
use App\Rules\IndukWilayah;
use App\Support\WilayahAccess;
use App\Support\WilayahPicker;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
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

// Fase 1.5: pengelolaan wilayah (hanya superadmin lewat WilayahPolicy).
// Form membatasi pilihan induk ke level yang sah untuk level terpilih.
// Memindahkan induk wilayah tidak disediakan (observer menolaknya).
class WilayahResource extends Resource
{
    protected static ?string $model = Wilayah::class;

    protected static ?string $slug = 'wilayah';

    protected static ?string $navigationLabel = 'Kelola Wilayah';

    protected static ?string $modelLabel = 'wilayah';

    protected static ?string $breadcrumb = 'Kelola Wilayah';

    protected static string|\UnitEnum|null $navigationGroup = 'Pengaturan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Detail Wilayah')
                ->description('Isi level, induk, kode, dan nama wilayah.')
                ->schema([
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
                        ->afterStateUpdated(function (Set $set, ?string $state): void {
                            $set('provinsi_id', null);
                            $set('kabkota_id', null);
                            $set('kecamatan_id', null);
                            $set('desa_id', null);
                            $set('dusun_id', null);
                            $set('rw_id', null);

                            if ($state === 'provinsi') {
                                $set('parent_id', Wilayah::where('level', 'negara')->value('id'));
                            } else {
                                $set('parent_id', null);
                            }
                        }),

                    Select::make('provinsi_id')
                        ->label(fn (Get $get): string => $get('level') === 'kabupaten_kota' ? '1. Induk Provinsi' : '1. Provinsi')
                        ->options(fn (): array => Wilayah::operasional()->where('level', 'provinsi')->orderBy('nama')->pluck('nama', 'id')->all())
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => ! auth()->user()?->isAdminRw() && in_array($get('level'), ['kabupaten_kota', 'kecamatan', 'desa', 'dusun', 'rw', 'rt'], true))
                        ->disabled(fn (string $operation): bool => $operation === 'edit')
                        ->afterStateUpdated(function (Set $set, ?int $state, Get $get): void {
                            if ($get('level') === 'kabupaten_kota') {
                                $set('parent_id', $state);
                            } else {
                                $set('kabkota_id', null);
                                $set('kecamatan_id', null);
                                $set('desa_id', null);
                                $set('dusun_id', null);
                                $set('rw_id', null);
                                $set('parent_id', null);
                            }
                        }),

                    Select::make('kabkota_id')
                        ->label(fn (Get $get): string => $get('level') === 'kecamatan' ? '2. Induk Kabupaten/Kota' : '2. Kabupaten/Kota')
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
                        ->visible(fn (Get $get): bool => ! auth()->user()?->isAdminRw() && in_array($get('level'), ['kecamatan', 'desa', 'dusun', 'rw', 'rt'], true))
                        ->disabled(fn (Get $get, string $operation): bool => $operation === 'edit' || blank($get('provinsi_id')))
                        ->afterStateUpdated(function (Set $set, ?int $state, Get $get): void {
                            if ($get('level') === 'kecamatan') {
                                $set('parent_id', $state);
                            } else {
                                $set('kecamatan_id', null);
                                $set('desa_id', null);
                                $set('dusun_id', null);
                                $set('rw_id', null);
                                $set('parent_id', null);
                            }
                        }),

                    Select::make('kecamatan_id')
                        ->label(fn (Get $get): string => $get('level') === 'desa' ? '3. Induk Kecamatan' : '3. Kecamatan')
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
                        ->visible(fn (Get $get): bool => ! auth()->user()?->isAdminRw() && in_array($get('level'), ['desa', 'dusun', 'rw', 'rt'], true))
                        ->disabled(fn (Get $get, string $operation): bool => $operation === 'edit' || blank($get('kabkota_id')))
                        ->afterStateUpdated(function (Set $set, ?int $state, Get $get): void {
                            if ($get('level') === 'desa') {
                                $set('parent_id', $state);
                            } else {
                                $set('desa_id', null);
                                $set('dusun_id', null);
                                $set('rw_id', null);
                                $set('parent_id', null);
                            }
                        }),

                    Select::make('desa_id')
                        ->label(fn (Get $get): string => in_array($get('level'), ['dusun', 'rw'], true) ? '4. Induk Desa/Kelurahan' : '4. Desa/Kelurahan')
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
                        ->visible(fn (Get $get): bool => ! auth()->user()?->isAdminRw() && in_array($get('level'), ['dusun', 'rw', 'rt'], true))
                        ->disabled(fn (Get $get, string $operation): bool => $operation === 'edit' || blank($get('kecamatan_id')))
                        ->afterStateUpdated(function (Set $set, ?int $state, Get $get): void {
                            if ($get('level') === 'dusun') {
                                $set('parent_id', $state);
                            } elseif ($get('level') === 'rw') {
                                $set('dusun_id', $state);
                                $set('parent_id', $state);
                            } else {
                                $set('rw_id', null);
                                $set('parent_id', null);
                            }
                        }),

                    Select::make('dusun_id')
                        ->label('5. Induk Penempatan RW (Desa atau Dusun)')
                        ->options(function (Get $get): array {
                            $desaId = $get('desa_id');
                            if (! $desaId) {
                                return [];
                            }
                            $desa = Wilayah::find($desaId);
                            $res = [];
                            if ($desa) {
                                $res[$desa->id] = "{$desa->nama} (Langsung di bawah Desa)";
                            }
                            $dusuns = Wilayah::operasional()->where('level', 'dusun')->where('parent_id', $desaId)->orderBy('nama')->get();
                            foreach ($dusuns as $d) {
                                $res[$d->id] = "Dusun {$d->nama}";
                            }

                            return $res;
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => ! auth()->user()?->isAdminRw() && $get('level') === 'rw')
                        ->disabled(fn (Get $get, string $operation): bool => $operation === 'edit' || blank($get('desa_id')))
                        ->afterStateUpdated(function (Set $set, ?int $state): void {
                            $set('parent_id', $state);
                        }),

                    Select::make('rw_id')
                        ->label('5. Induk RW')
                        ->options(function (Get $get): array {
                            $desaId = $get('desa_id');
                            if (! $desaId) {
                                return [];
                            }
                            $dusunIds = Wilayah::operasional()->where('level', 'dusun')->where('parent_id', $desaId)->pluck('id')->all();

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
                                ->mapWithKeys(fn (Wilayah $w): array => [
                                    $w->id => $w->parent?->level === 'dusun'
                                        ? "{$w->nama} (Dusun {$w->parent->nama})"
                                        : "{$w->nama} (RW Langsung)",
                                ])
                                ->all();
                        })
                        ->searchable()
                        ->preload()
                        ->live()
                        ->dehydrated(false)
                        ->visible(fn (Get $get): bool => ! auth()->user()?->isAdminRw() && $get('level') === 'rt')
                        ->disabled(fn (Get $get, string $operation): bool => $operation === 'edit' || blank($get('desa_id')))
                        ->afterStateUpdated(function (Set $set, ?int $state): void {
                            $set('parent_id', $state);
                        }),

                    Hidden::make('parent_id')
                        ->default(function (Get $get): ?int {
                            if (auth()->user()?->isAdminRw()) {
                                return auth()->user()->id_wilayah;
                            }

                            if ($req = request()->integer('parent_id')) {
                                return $req;
                            }

                            if ($get('level') === 'provinsi') {
                                return Wilayah::where('level', 'negara')->value('id');
                            }

                            return null;
                        })
                        ->dehydrated()
                        ->required(fn (Get $get): bool => filled($get('level')) && $get('level') !== 'negara')
                        ->rule(fn (Get $get): IndukWilayah => new IndukWilayah(fn (): ?string => $get('level')))
                        ->afterStateHydrated(function (Hidden $component, ?int $state, Set $set, ?Wilayah $record): void {
                            $targetId = $state ?? $record?->parent_id ?? request()->integer('parent_id');
                            if (! $targetId) {
                                return;
                            }
                            $target = Wilayah::with('parent')->find($targetId);
                            if (! $target) {
                                return;
                            }
                            $ancestors = $target->ancestors()->keyBy('level');
                            $set('provinsi_id', $ancestors->get('provinsi')?->id ?? ($target->level === 'provinsi' ? $target->id : null));
                            $set('kabkota_id', $ancestors->get('kabupaten_kota')?->id ?? ($target->level === 'kabupaten_kota' ? $target->id : null));
                            $set('kecamatan_id', $ancestors->get('kecamatan')?->id ?? ($target->level === 'kecamatan' ? $target->id : null));
                            $set('desa_id', $ancestors->get('desa')?->id ?? ($target->level === 'desa' ? $target->id : null));
                            if ($target->level === 'dusun') {
                                $set('dusun_id', $target->id);
                            } elseif ($target->level === 'rw') {
                                $set('rw_id', $target->id);
                            }
                            $set('parent_id', $targetId);
                        }),

                    Placeholder::make('induk_preview')
                        ->label('Status Induk Wilayah')
                        ->content(function (Get $get, ?Wilayah $record): HtmlString {
                            $level = $get('level') ?? $record?->level;
                            if (! $level) {
                                return new HtmlString('<span class="text-sm text-gray-500">Pilih level wilayah terlebih dahulu.</span>');
                            }

                            if ($level === 'negara') {
                                return new HtmlString('<div class="rounded-xl bg-slate-50 border border-slate-200 p-3 text-sm text-slate-700">Negara adalah tingkat tertinggi (akar) dan tidak memiliki induk.</div>');
                            }

                            $id = $get('parent_id') ?? $record?->parent_id;
                            if (! $id && $level === 'provinsi') {
                                $id = Wilayah::where('level', 'negara')->value('id');
                            }

                            if (! $id) {
                                $indukLabel = match ($level) {
                                    'provinsi' => 'Negara',
                                    'kabupaten_kota' => 'Provinsi',
                                    'kecamatan' => 'Kabupaten/Kota',
                                    'desa' => 'Kecamatan',
                                    'dusun' => 'Desa/Kelurahan',
                                    'rw' => 'Desa atau Dusun',
                                    'rt' => 'RW',
                                    default => 'Induk',
                                };

                                return new HtmlString('<div class="rounded-lg bg-amber-50 border border-amber-200 px-3 py-2 text-sm text-amber-800 flex items-center gap-2">
                                    <span class="font-semibold">Perhatian:</span> Lengkapi pilihan urutan induk wilayah di atas hingga tingkat <strong>'.$indukLabel.'</strong>.
                                </div>');
                            }

                            $p = Wilayah::find($id);
                            if (! $p) {
                                return new HtmlString('<span class="text-sm text-gray-500">—</span>');
                            }

                            return new HtmlString('<div class="rounded-xl bg-blue-50/70 border border-blue-200 p-3 text-sm text-blue-950 flex flex-col gap-1">
                                <div class="flex items-center justify-between gap-2">
                                    <div class="flex items-center gap-2">
                                        <span class="inline-flex items-center rounded-lg bg-blue-600 px-2 py-0.5 text-xs font-bold text-white uppercase tracking-wider">INDUK: '.e(strtoupper($p->level)).'</span>
                                        <span class="font-bold text-base text-gray-950">'.e($p->nama).'</span>
                                    </div>
                                    <span class="font-mono text-xs text-blue-700 font-semibold">Kode: '.e($p->kode).'</span>
                                </div>
                                <p class="text-xs text-gray-600 mt-0.5">'.e($p->alamatLengkap()).'</p>
                            </div>');
                        })
                        ->columnSpanFull(),

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

                    Toggle::make('is_active')
                        ->label('Status Aktif')
                        ->helperText(function (?Wilayah $record): string {
                            if ($record === null) {
                                return 'Aktifkan wilayah agar dapat beroperasi dan digunakan untuk pendataan.';
                            }

                            return 'Jika dinonaktifkan, wilayah ini beserta seluruh sub-wilayah di bawahnya (seperti RW dan RT) akan otomatis down / tidak operasional.';
                        })
                        ->default(true)
                        ->disabled(fn (?Wilayah $record): bool => $record !== null && ! (auth()->user()?->can('update', $record) ?? false)),

                    Placeholder::make('status_operasional_info')
                        ->label('Status Operasional (Efektif)')
                        ->content(function (?Wilayah $record): ?HtmlString {
                            if ($record === null) {
                                return null;
                            }

                            if ($record->aktifEfektif()) {
                                return new HtmlString('<div class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200"><span class="h-2 w-2 rounded-full bg-emerald-500"></span> Operasional Aktif (Wilayah dan seluruh induknya aktif)</div>');
                            }

                            if (! $record->is_active) {
                                $turunan = $record->descendants()->count();
                                $keteranganTurunan = $turunan > 0 ? " — Seluruh {$turunan} sub-wilayah di bawahnya ikut down" : '';

                                return new HtmlString("<div class=\"inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-rose-50 text-rose-700 border border-rose-200\"><span class=\"h-2 w-2 rounded-full bg-rose-500\"></span> Nonaktif / Down (Dinonaktifkan langsung{$keteranganTurunan})</div>");
                            }

                            $ancestorNonaktif = $record->ancestors()->where('is_active', false)->first();
                            $namaInduk = $ancestorNonaktif ? "{$ancestorNonaktif->nama} (".Wilayah::labelLevel($ancestorNonaktif->level).")" : 'Induk';

                            return new HtmlString("<div class=\"inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-amber-50 text-amber-700 border border-amber-200\"><span class=\"h-2 w-2 rounded-full bg-amber-500\"></span> Terblokir / Down (karena induk {$namaInduk} nonaktif)</div>");
                        })
                        ->visible(fn (?Wilayah $record): bool => $record !== null),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('kode')->label('Kode')->searchable(),
                TextColumn::make('is_active')
                    ->label('Status lokal')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state ? 'Aktif' : 'Nonaktif')
                    ->color(fn ($state) => $state ? 'success' : 'danger')
                    ->tooltip('Status wilayah ini sendiri, tanpa melihat status induk.'),
                TextColumn::make('efektif')
                    ->label('Operasional')
                    ->getStateUsing(function (Wilayah $record): string {
                        if (! $record->is_active) {
                            return 'Down (Nonaktif)';
                        }
                        if (! $record->aktifEfektif()) {
                            return 'Down (Induk nonaktif)';
                        }

                        return 'Aktif';
                    })
                    ->badge()
                    ->color(fn (string $state) => match ($state) {
                        'Aktif' => 'success',
                        'Down (Induk nonaktif)' => 'warning',
                        default => 'danger',
                    })
                    ->tooltip(function (Wilayah $record): string {
                        if (! $record->is_active) {
                            return 'Wilayah ini dinonaktifkan secara langsung.';
                        }
                        if (! $record->aktifEfektif()) {
                            $ancestors = $record->ancestors()->where('is_active', false)->pluck('nama')->implode(', ');

                            return "Induk wilayah nonaktif: {$ancestors}. Seluruh anak wilayah ikut down.";
                        }

                        return 'Status efektif: wilayah ini dan seluruh induknya aktif.';
                    }),
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
                TextColumn::make('keluarga_count')
                    ->label('Data KK')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->alignCenter()
                    ->formatStateUsing(fn (int $state, Wilayah $record): string => $record->level === 'rt' ? "{$state} KK" : '—')
                    ->tooltip(fn (Wilayah $record): ?string => $record->level === 'rt' ? 'Buka data keluarga di RT ini' : null)
                    ->url(fn (Wilayah $record): ?string => $record->level === 'rt'
                        ? static::getUrl('index', ['parent' => $record->id])
                        : null
                    ),
                TextColumn::make('children_count')
                    ->label('Anak langsung')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'primary' : 'gray')
                    ->alignCenter()
                    ->tooltip(fn (int $state): ?string => $state > 0 ? 'Buka daftar anak wilayah' : null)
                    ->url(fn (Wilayah $record): ?string => $record->children_count > 0
                        ? static::getUrl('index', ['parent' => $record->id])
                        : null
                    ),
            ])
            ->defaultSort('kode')
            ->searchable(false)
            ->striped()
            ->stackedOnMobile()
            ->emptyStateHeading('Belum ada wilayah')
            ->emptyStateDescription('Tambahkan wilayah mulai dari negara sebagai akar.')
            ->actions([
                Action::make('tambahKk')
                    ->label('+ KK')
                    ->icon('heroicon-m-plus')
                    ->color('success')
                    ->button()
                    ->size('sm')
                    ->tooltip('Tambah KK baru di RT ini')
                    ->visible(fn (Wilayah $record): bool => $record->level === 'rt' && $record->aktifEfektif() && (bool) auth()->user()?->can('create', \App\Models\Keluarga::class))
                    ->url(fn (Wilayah $record): string => \App\Filament\Resources\KeluargaResource::getUrl('create', ['id_wilayah' => $record->id])),

                Action::make('buka')
                    ->label(fn (Wilayah $record): string => $record->level === 'rt' ? 'Buka KK' : 'Buka')
                    ->icon(fn (Wilayah $record): string => $record->level === 'rt' ? 'heroicon-m-users' : 'heroicon-m-arrow-right-circle')
                    ->color(fn (Wilayah $record): string => $record->level === 'rt' ? 'info' : 'primary')
                    ->iconButton()
                    ->tooltip(fn (Wilayah $record): string => $record->level === 'rt' ? 'Buka daftar keluarga di RT ini' : 'Buka wilayah')
                    ->visible(fn (Wilayah $record): bool => $record->children_count > 0 || $record->level === 'rt')
                    ->url(fn (Wilayah $record): string => static::getUrl('index', ['parent' => $record->id])),

                ActionGroup::make([
                    Action::make('aktivasi')
                        ->label(fn (Wilayah $record): string => $record->is_active ? 'Nonaktifkan' : 'Aktifkan')
                        ->icon('heroicon-m-power')
                        ->color(fn (Wilayah $record): string => $record->is_active ? 'danger' : 'success')
                        ->requiresConfirmation()
                        ->modalHeading(fn (Wilayah $record): string => $record->is_active ? "Nonaktifkan {$record->nama}?" : "Aktifkan {$record->nama}?")
                        ->modalDescription(function (Wilayah $record): string {
                            $levelLabel = Wilayah::labelLevel($record->level);
                            if ($record->is_active) {
                                $turunan = $record->descendants()->count();
                                if ($turunan > 0) {
                                    return "Menonaktifkan {$record->nama} ({$levelLabel}) akan menyebabkan wilayah ini beserta seluruh {$turunan} sub-wilayah di bawahnya (termasuk RT dan RW di dalamnya) menjadi DOWN / tidak operasional.";
                                }

                                return "Menonaktifkan {$record->nama} ({$levelLabel}) hanya akan menyebabkan {$levelLabel} ini saja yang DOWN / tidak operasional.";
                            }

                            return "Mengaktifkan {$record->nama} ({$levelLabel}). Seluruh sub-wilayah di bawahnya yang aktif akan kembali operasional.";
                        })
                        ->modalSubmitActionLabel(fn (Wilayah $record): string => $record->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan')
                        ->visible(fn (Wilayah $record): bool => auth()->user()?->can('update', $record) ?? false)
                        ->action(function (Wilayah $record): void {
                            $record->update(['is_active' => ! $record->is_active]);

                            Notification::make()
                                ->title($record->is_active ? 'Wilayah Diaktifkan' : 'Wilayah Dinonaktifkan')
                                ->body($record->is_active
                                    ? "{$record->nama} telah diaktifkan kembali."
                                    : "{$record->nama} beserta seluruh turunannya kini berstatus down."
                                )
                                ->success()
                                ->send();
                        }),

                    Action::make('admin')
                        ->label('Admin di wilayah ini')
                        ->icon('heroicon-m-user-group')
                        ->color('info')
                        ->visible(fn () => auth()->user()->can('viewAny', User::class))
                        ->url(fn (Wilayah $record) => UserResource::getUrl('index', ['wilayah' => $record->id])),

                    Action::make('keluarga')
                        ->label('Buka di Kelola Warga')
                        ->icon('heroicon-m-users')
                        ->color('success')
                        ->visible(fn (Wilayah $record): bool => $record->level === 'rt')
                        ->url(fn (Wilayah $record): string => \App\Filament\Pages\JelajahWilayah::getUrl(['wilayah' => $record->id])),

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
                ])
                    ->icon('heroicon-m-ellipsis-horizontal')
                    ->iconButton()
                    ->tooltip('Aksi lain'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery()
            ->with([Wilayah::RELASI_LELUHUR])
            ->withCount(['children', 'keluarga'])
            ->orderBy('kode')
            ->orderBy('nama');
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

        if ($user->isAdminRt() && $user->id_wilayah) {
            return $query->whereKey($user->id_wilayah);
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
            ->with([Wilayah::RELASI_LELUHUR])
            ->orderBy('kode')
            ->orderBy('nama')
            ->get()
            ->mapWithKeys(fn (Wilayah $w): array => [$w->id => "{$w->nama} ({$w->rantaiIndukNama()})"])
            ->all();
    }
}
