<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AnggotaResource;
use App\Filament\Resources\KeluargaResource;
use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\SensitiveData;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

// Fase 3: halaman utama penjelajahan wilayah. Titik awal mengikuti peran
// (superadmin: negara, admin_rw: RW-nya, admin_rt: RT-nya). Node selain RT
// menampilkan tabel anak; node RT menampilkan daftar KK di RT itu.
class JelajahWilayah extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'jelajah';

    protected static ?string $title = 'Jelajah Wilayah';

    protected static ?string $navigationLabel = 'Jelajah Wilayah';

    protected static string|\UnitEnum|null $navigationGroup = 'Pendataan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.jelajah-wilayah';

    public ?Wilayah $wilayah = null;

    // Rute memakai parameter opsional agar bisa diklik antar node.
    public static function getRoutePath(Panel $panel): string
    {
        return '/jelajah/{wilayah?}';
    }

    public function mount(?Wilayah $wilayah = null): void
    {
        $this->wilayah = $this->resolveNode($wilayah);
    }

    // Pertahanan kedua saat Livewire menghidrasi ulang: node harus tetap di
    // dalam subtree pengguna walau payload diubah.
    public function hydrate(): void
    {
        abort_unless($this->diDalamSubtree($this->wilayah, $this->titikAwal(auth()->user())), 404);
    }

    // Breadcrumb bawaan tata letak dimatikan; rantai digambar sendiri di view.
    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function table(Table $table): Table
    {
        if ($this->wilayah?->level === 'rt') {
            return $this->tabelKeluarga($table);
        }

        return $this->tabelAnak($table);
    }

    protected function getHeaderActions(): array
    {
        $actions = [
            Action::make('cari')
                ->label('Cari No KK / NIK')
                ->icon('heroicon-o-magnifying-glass')
                ->form([
                    TextInput::make('nomor')
                        ->label('No KK atau NIK persis')
                        ->required()
                        ->length(16)
                        ->rule('regex:/^[0-9]{16}$/')
                        ->helperText('Masukkan nomor lengkap 16 digit.'),
                ])
                ->action(fn (array $data) => $this->cariPersis($data['nomor'])),
        ];

        // Fase 3.3: tombol tambah hanya bila policy mengizinkan, membawa RT
        // ini sebagai id_wilayah terpilih (dikunci di form).
        if ($this->bolehTambahKeluarga()) {
            $actions[] = Action::make('tambahKeluarga')
                ->label('Tambah keluarga')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => KeluargaResource::getUrl('create', ['id_wilayah' => $this->wilayah->id]));
        }

        return $actions;
    }

    public function bolehTambahKeluarga(): bool
    {
        return $this->wilayah?->level === 'rt'
            && (bool) auth()->user()?->can('create', Keluarga::class);
    }

    // ---- Breadcrumb & ringkasan untuk view ----------------------------------

    /** @return list<array{nama: string, url: string, aktif: bool}> */
    public function breadcrumbsRantai(): array
    {
        $start = $this->titikAwal(auth()->user());

        return $this->wilayah->ancestors()
            ->push($this->wilayah)
            ->filter(fn (Wilayah $w): bool => str_starts_with((string) $w->path, (string) $start->path))
            ->map(fn (Wilayah $w): array => [
                'nama' => $w->nama,
                'url' => static::getUrl(['wilayah' => $w->id]),
                'aktif' => $w->is($this->wilayah),
            ])
            ->values()
            ->all();
    }

    public function labelLevel(): string
    {
        return Wilayah::labelLevel((string) $this->wilayah?->level);
    }

    /** @return array{anak: int, kk: int, anggota: int} */
    public function ringkasan(): array
    {
        $rtIds = $this->rtDalamSubtreeNode();

        $kk = $rtIds === [] ? 0 : Keluarga::whereIn('id_wilayah', $rtIds)->count();
        $anggota = $kk === 0 ? 0 : Anggota::whereIn(
            'id_keluarga',
            Keluarga::whereIn('id_wilayah', $rtIds)->select('id'),
        )->count();

        return [
            'anak' => $this->wilayah?->children()->count() ?? 0,
            'kk' => $kk,
            'anggota' => $anggota,
        ];
    }

    // ---- Tabel anak (node selain RT) ----------------------------------------

    protected function tabelAnak(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Wilayah::query()->where('parent_id', $this->wilayah->id))
            ->columns([
                TextColumn::make('nama')
                    ->label('Nama')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('level')
                    ->label('Level')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => Wilayah::labelLevel($state)),
                TextColumn::make('children_count')
                    ->label('Anak langsung')
                    ->counts('children')
                    ->alignCenter(),
                TextColumn::make('kk_keturunan')
                    ->label('KK aktif (keturunan)')
                    ->getStateUsing(fn (Wilayah $record): int => $this->kkKeturunan()[$record->id] ?? 0)
                    ->alignCenter(),
            ])
            ->defaultSort('path')
            // Fase 3.2: klik baris masuk ke node anak.
            ->recordUrl(fn (Wilayah $record): string => static::getUrl(['wilayah' => $record->id]))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateHeading('Tidak ada wilayah turunan')
            ->emptyStateDescription('Node ini adalah ujung rantai wilayah.');
    }

    // ---- Tabel KK (node RT) -------------------------------------------------

    protected function tabelKeluarga(Table $table): Table
    {
        return $table
            ->query(fn (): Builder => Keluarga::query()->where('id_wilayah', $this->wilayah->id))
            ->columns([
                TextColumn::make('no_kk_masked')
                    ->label('No KK')
                    ->icon('heroicon-m-lock-closed')
                    ->iconColor('gray')
                    ->getStateUsing(fn (Keluarga $record): ?string => $record->no_kk_masked),
                TextColumn::make('anggota_count')
                    ->label('Anggota aktif')
                    ->counts('anggota')
                    ->alignCenter(),
                TextColumn::make('kode_pos')
                    ->label('Kode pos'),
            ])
            ->recordUrl(fn (Keluarga $record): string => KeluargaResource::getUrl('view', ['record' => $record]))
            ->paginated([25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->striped()
            ->emptyStateHeading('Belum ada keluarga di RT ini')
            ->emptyStateDescription('Pilih Tambah keluarga untuk memulai.');
    }

    // ---- Pencarian persis ---------------------------------------------------

    public function cariPersis(string $nomor): void
    {
        $hash = SensitiveData::hash(SensitiveData::digits($nomor));

        // Scope wilayah berlaku otomatis, jadi lintas wilayah tidak ketemu.
        $keluarga = Keluarga::where('no_kk_hash', $hash)->first();

        if ($keluarga !== null) {
            $this->redirect(KeluargaResource::getUrl('view', ['record' => $keluarga]));

            return;
        }

        $anggota = Anggota::where('nik_hash', $hash)->first();

        if ($anggota !== null) {
            $this->redirect(AnggotaResource::getUrl('view', ['record' => $anggota]));

            return;
        }

        Notification::make()
            ->warning()
            ->title('Data tidak ditemukan')
            ->body('Tidak ada No KK atau NIK tersebut di wilayah Anda.')
            ->send();
    }

    // ---- Pembantu -----------------------------------------------------------

    private function resolveNode(?Wilayah $wilayah): Wilayah
    {
        $start = $this->titikAwal(auth()->user());

        if ($wilayah === null) {
            return $start;
        }

        // Fase 3.7: node di luar subtree pengguna → 404.
        abort_unless($this->diDalamSubtree($wilayah, $start), 404);

        return $wilayah;
    }

    private function titikAwal(?User $user): Wilayah
    {
        if ($user !== null && $user->isSuperadmin()) {
            $negara = Wilayah::where('level', 'negara')->orderBy('path')->first();

            abort_if($negara === null, 404, 'Wilayah akar (negara) belum ada.');

            return $negara;
        }

        $wilayah = $user?->wilayah;

        abort_if($wilayah === null, 403);

        return $wilayah;
    }

    private function diDalamSubtree(?Wilayah $node, Wilayah $start): bool
    {
        return $node !== null && str_starts_with((string) $node->path, (string) $start->path);
    }

    /** @return list<int> */
    private function rtDalamSubtreeNode(): array
    {
        if ($this->wilayah === null) {
            return [];
        }

        return Wilayah::where('level', 'rt')
            ->where('path', 'like', $this->wilayah->path.'%')
            ->pluck('id')
            ->all();
    }

    // Jumlah KK aktif per anak, dihitung sekali (bukan per baris) agar tidak N+1.
    /** @return array<int, int> */
    public function kkKeturunan(): array
    {
        if ($this->cacheKk !== null) {
            return $this->cacheKk;
        }

        $rt = Wilayah::where('level', 'rt')
            ->where('path', 'like', $this->wilayah->path.'%')
            ->get(['id', 'path']);

        $perRt = Keluarga::whereIn('id_wilayah', $rt->pluck('id'))
            ->selectRaw('id_wilayah, count(*) as jumlah')
            ->groupBy('id_wilayah')
            ->pluck('jumlah', 'id_wilayah');

        $peta = [];

        foreach ($this->wilayah->children()->get() as $anak) {
            $peta[$anak->id] = $rt
                ->filter(fn (Wilayah $r): bool => str_starts_with((string) $r->path, (string) $anak->path))
                ->sum(fn (Wilayah $r): int => (int) ($perRt[$r->id] ?? 0));
        }

        return $this->cacheKk = $peta;
    }

    /** @var array<int, int>|null */
    protected ?array $cacheKk = null;
}
