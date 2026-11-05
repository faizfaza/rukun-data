<?php

namespace App\Filament\Pages;

use App\Filament\Resources\AnggotaResource;
use App\Filament\Resources\KeluargaResource;
use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\Wilayah;
use App\Support\SensitiveData;
use App\Support\WilayahLabels;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Panel;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

// Halaman utama penjelajahan data. Pengguna memilih wilayah bertahap lewat
// daftar + kotak cari: provinsi → kabupaten/kota → kecamatan → desa → turunan
// desa (dusun opsional / RW) → RW → RT. Daftar keluarga baru tampil setelah
// rantai sampai level RT.
//
// Sengaja tidak memakai komponen form Filament yang jumlahnya dinamis: select
// yang ditambahkan lewat morph Livewire tidak meneruskan perubahan ke server.
// Di sini seluruh langkah dikendalikan Livewire (wire:click), jadi andal.
class JelajahWilayah extends Page implements HasTable
{
    use InteractsWithTable;

    protected static ?string $slug = 'jelajah';

    protected static ?string $title = 'Kelola Warga';

    protected static ?string $navigationLabel = 'Kelola Warga';

    protected static string|\UnitEnum|null $navigationGroup = 'Pendataan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static ?int $navigationSort = 1;

    // Menu mandiri di bawah Pendataan untuk penjelajahan dan pengelolaan warga per wilayah.
    protected static bool $shouldRegisterNavigation = true;

    public static function shouldRegisterNavigation(): bool
    {
        return true;
    }

    protected string $view = 'filament.pages.jelajah-wilayah';

    // Saat ditanam di halaman Wilayah, header halaman tidak dirender ulang.
    public bool $tertanam = false;

    // Pilihan bertahap: p0 = pilihan pertama (mis. provinsi), p1 = berikutnya.
    /** @var array<string, int|string|null> */
    public array $pilihan = [];

    // Kata kunci untuk daftar langkah yang sedang aktif.
    public string $cari = '';

    // Kata kunci pencarian lintas tingkat (semua wilayah di bawah titik awal).
    public string $cariGlobal = '';

    protected ?Wilayah $titikAwalCache = null;

    /** @var array<int, Collection<int, Wilayah>> */
    protected array $cacheAnak = [];

    /** @var list<Wilayah>|null */
    protected ?array $cacheTangga = null;

    protected ?string $cacheTanggaKunci = null;

    /** @var array<int, array{jumlah: int, label: string}>|null */
    protected ?array $cacheHitungan = null;

    protected ?string $cacheHitunganKunci = null;

    // Rute memakai parameter opsional agar node tertentu bisa ditautkan langsung.
    public static function getRoutePath(Panel $panel): string
    {
        return '/jelajah/{wilayah?}';
    }

    // Tautan langsung (mis. /admin/jelajah/12) mengisi rantai sampai node itu.
    // Node di luar subtree pengguna → 404.
    public function mount(?Wilayah $wilayah = null, ?int $wilayahId = null, bool $tertanam = false): void
    {
        $this->tertanam = $tertanam;

        if ($wilayah === null && $wilayahId !== null) {
            $wilayah = Wilayah::find($wilayahId);
        }

        $start = $this->titikAwal(auth()->user());

        if ($wilayah !== null) {
            abort_unless($this->diDalamSubtree($wilayah, $start), 404);

            $this->pilihan = $this->pilihanDariNode($wilayah, $start);
        }
    }

    public function getHeading(): string|Htmlable|null
    {
        return $this->tertanam ? null : parent::getHeading();
    }

    public function table(Table $table): Table
    {
        return $table
            // Query dievaluasi saat render, supaya RT terpilih terbaru dipakai.
            ->query(fn (): Builder => Keluarga::query()->where('id_wilayah', $this->nodeTerpilihRt()?->id ?? -1))
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
            ->emptyStateDescription('Pilih Tambah keluarga untuk memulai.')
            ->emptyStateActions([
                Action::make('tambahKeluargaEmpty')
                    ->label(fn (): string => 'Tambah keluarga di '.($this->nodeTerpilihRt()?->nama ?? 'RT ini'))
                    ->icon('heroicon-o-plus')
                    ->button()
                    ->color('primary')
                    ->url(fn (): string => KeluargaResource::getUrl('create', array_filter(['id_wilayah' => $this->nodeTerpilihRt()?->id])))
                    ->visible(fn (): bool => $this->bolehTambahKeluarga()),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return $this->tertanam ? [] : $this->aksiHeader();
    }

    /** @return array<int, Action> */
    public function aksiHeader(): array
    {
        return [
            Action::make('cari')
                ->label('Cari No KK / NIK')
                ->icon('heroicon-o-magnifying-glass')
                ->color('gray')
                ->outlined()
                ->button()
                ->form([
                    TextInput::make('nomor')
                        ->label('No KK atau NIK persis')
                        ->required()
                        ->length(16)
                        ->rule('regex:/^[0-9]{16}$/')
                        ->helperText('Masukkan nomor lengkap 16 digit.'),
                ])
                ->action(fn (array $data) => $this->cariPersis($data['nomor'])),

            Action::make('tambahKeluarga')
                ->label('Tambah keluarga')
                ->icon('heroicon-o-plus')
                ->color('primary')
                ->button()
                ->url(function (): string {
                    $rt = $this->nodeTerpilihRt();

                    return KeluargaResource::getUrl('create', array_filter(['id_wilayah' => $rt?->id]));
                })
                ->visible(function (): bool {
                    $rt = $this->nodeTerpilihRt();
                    if ($rt !== null && ! $rt->aktifEfektif()) {
                        return false;
                    }

                    $user = auth()->user();
                    if ($user?->isSuperadmin() || $user?->isAdmin()) {
                        return true;
                    }

                    return $this->bolehTambahKeluarga();
                }),
        ];
    }

    // ---- Aksi pemilih -------------------------------------------------------

    // Memilih satu opsi pada langkah aktif. Klik yang basi (daftar sudah berganti
    // karena klik ganda atau respons lambat) diabaikan, bukan menghasilkan 404.
    public function pilih(int $indeks, int $id): void
    {
        $langkah = $this->langkahSaatIni();

        if ($langkah === null) {
            return;
        }

        $opsi = $this->anakDari($langkah['induk'])->firstWhere('id', $id);

        if ($opsi === null) {
            return;
        }

        $indeksAktif = $langkah['indeks'];

        $this->pilihan["p{$indeksAktif}"] = $opsi->id;
        $this->pangkas($indeksAktif + 1);
        $this->cari = '';
        $this->cariGlobal = '';
        $this->resetTable();
    }

    // Kembali ke node pada posisi rantai $posisi (0 = titik awal).
    public function kembaliKe(int $posisi): void
    {
        $this->pangkas($posisi);
        $this->cari = '';
        $this->cariGlobal = '';
        $this->resetTable();
    }

    // Lompat langsung ke wilayah mana pun (semua tingkat) dari hasil pencarian.
    public function lompatKe(int $id): void
    {
        $start = $this->titikAwal(auth()->user());
        $node = Wilayah::find($id);

        if ($node === null || ! $this->diDalamSubtree($node, $start) || $node->is($start)) {
            Notification::make()->warning()->title('Wilayah tidak ditemukan')->send();

            return;
        }

        $this->pilihan = $this->pilihanDariNode($node, $start);
        $this->cacheTangga = null;
        $this->cacheTanggaKunci = null;
        $this->cari = '';
        $this->cariGlobal = '';
        $this->resetTable();
    }

    /**
     * Pencarian lintas tingkat di dalam wilayah yang boleh diakses pengguna
     * (provinsi sampai RT), dengan rantai induk supaya nama kembar terbedakan.
     *
     * @return list<array{id: int, nama: string, level: string, chain: string, status_label: string, status: string}>
     */
    public function hasilCariGlobal(): array
    {
        $kata = trim($this->cariGlobal);

        if (mb_strlen($kata) < 2) {
            return [];
        }

        $start = $this->titikAwal(auth()->user());

        $hasil = Wilayah::query()
            ->where('path', 'like', $start->path.'%')
            ->whereKeyNot($start->getKey())
            ->where(fn (Builder $q): Builder => $q
                ->where('nama', 'like', '%'.$kata.'%')
                ->orWhere('kode', 'like', '%'.$kata.'%'))
            ->orderByRaw('CHAR_LENGTH(path)')
            ->orderBy('nama')
            ->limit(30)
            ->get();

        $detail = WilayahLabels::details($hasil);

        return $hasil->map(fn (Wilayah $w): array => [
            'id' => $w->id,
            'nama' => $w->nama,
            'level' => Wilayah::labelLevel((string) $w->level),
            'chain' => $detail[$w->id]['chain'] ?? '',
            'status_label' => $detail[$w->id]['status_label'] ?? '',
            'status' => $detail[$w->id]['status'] ?? 'active',
        ])->all();
    }

    // ---- Data untuk view ----------------------------------------------------

    /** @return list<Wilayah> */
    public function rantai(): array
    {
        return $this->tangga();
    }

    // Langkah yang sedang menunggu dipilih; null bila sudah selesai (RT/buntu).
    /** @return array{indeks: int, induk: Wilayah, label: string}|null */
    public function langkahSaatIni(): ?array
    {
        $tangga = $this->tangga();
        $indeks = count($tangga) - 1;
        $node = $tangga[$indeks];
        $anak = $this->anakDari($node);

        if ($node->level === 'rt' || $anak->isEmpty()) {
            return null;
        }

        return [
            'indeks' => $indeks,
            'induk' => $node,
            'label' => $this->labelLangkah($anak),
        ];
    }

    // Opsi langkah aktif, disaring oleh kotak cari (nama atau kode).
    /** @return Collection<int, Wilayah> */
    public function opsiSaatIni(): Collection
    {
        $langkah = $this->langkahSaatIni();

        if ($langkah === null) {
            return new Collection;
        }

        $kata = mb_strtolower(trim($this->cari));

        return $this->anakDari($langkah['induk'])
            ->when($kata !== '', fn (Collection $anak): Collection => $anak->filter(
                fn (Wilayah $w): bool => str_contains(mb_strtolower($w->nama.' '.$w->kode), $kata),
            ))
            ->values();
    }

    // Jumlah turunan (atau KK untuk RT) per opsi. Dua query agregat untuk
    // seluruh daftar, bukan satu query per baris.
    /** @return array<int, array{jumlah: int, label: string}> */
    public function hitunganOpsi(): array
    {
        $opsi = $this->opsiSaatIni();
        $kunci = $opsi->pluck('id')->implode(',');

        if ($this->cacheHitungan !== null && $this->cacheHitunganKunci === $kunci) {
            return $this->cacheHitungan;
        }

        $ids = $opsi->pluck('id')->all();

        if ($ids === []) {
            $this->cacheHitunganKunci = $kunci;

            return $this->cacheHitungan = [];
        }

        $anak = Wilayah::query()
            ->whereIn('parent_id', $ids)
            ->selectRaw('parent_id, count(*) as jumlah')
            ->groupBy('parent_id')
            ->pluck('jumlah', 'parent_id');

        $rtIds = $opsi->where('level', 'rt')->pluck('id')->all();

        $keluarga = $rtIds === []
            ? collect()
            : Keluarga::query()
                ->whereIn('id_wilayah', $rtIds)
                ->selectRaw('id_wilayah, count(*) as jumlah')
                ->groupBy('id_wilayah')
                ->pluck('jumlah', 'id_wilayah');

        $hasil = [];

        foreach ($opsi as $w) {
            $jumlah = $w->level === 'rt'
                ? (int) ($keluarga[$w->id] ?? 0)
                : (int) ($anak[$w->id] ?? 0);

            $hasil[$w->id] = [
                'jumlah' => $jumlah,
                'label' => $this->labelHitungan($w->level, $jumlah),
            ];
        }

        $this->cacheHitunganKunci = $kunci;

        return $this->cacheHitungan = $hasil;
    }

    protected function labelHitungan(string $level, int $jumlah): string
    {
        return match ($level) {
            'rt' => "{$jumlah} KK",
            'rw' => "{$jumlah} RT",
            'dusun' => "{$jumlah} RW",
            'desa' => "{$jumlah} wilayah",
            'kecamatan' => "{$jumlah} desa",
            'kabupaten_kota' => "{$jumlah} kecamatan",
            'provinsi' => "{$jumlah} kab/kota",
            'negara' => "{$jumlah} provinsi",
            default => "{$jumlah} turunan",
        };
    }

    public function nodeTerpilihRt(): ?Wilayah
    {
        $node = $this->nodeAktif();

        return $node->level === 'rt' ? $node : null;
    }

    public function buntu(): bool
    {
        $node = $this->nodeAktif();

        return $node->level !== 'rt' && $this->anakDari($node)->isEmpty();
    }

    public function nodeAktif(): Wilayah
    {
        $tangga = $this->tangga();

        return $tangga[count($tangga) - 1];
    }

    public function bolehTambahKeluarga(): bool
    {
        return $this->nodeTerpilihRt() !== null && $this->nodeTerpilihRt()->aktifEfektif()
            && (bool) auth()->user()?->can('create', Keluarga::class);
    }

    public function ringkasanKk(): string
    {
        $rt = $this->nodeTerpilihRt();

        if ($rt === null) {
            return '';
        }

        $keluarga = Keluarga::where('id_wilayah', $rt->id)->count();
        $anggota = Anggota::whereIn(
            'id_keluarga',
            Keluarga::where('id_wilayah', $rt->id)->select('id'),
        )->count();

        return $keluarga.' keluarga, '.$anggota.' anggota aktif.';
    }

    public function labelUntuk(Wilayah $wilayah): string
    {
        if ($wilayah->level === 'rw' && $wilayah->parent?->level === 'desa') {
            return 'RW Langsung';
        }

        return Wilayah::labelLevel((string) $wilayah->level);
    }

    /** @return list<Wilayah> */
    public function leluhurTitikAwal(): array
    {
        $start = $this->titikAwal(auth()->user());

        return $start->ancestors()->all();
    }

    public function lokasiLengkap(?Wilayah $wilayah = null): string
    {
        $node = $wilayah ?? $this->nodeAktif();

        return $node->alamatLengkap();
    }

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

    // Rantai node terpilih dari titik awal; nilai pilihan yang sudah tidak sah
    // (mis. sisa pilihan sebelumnya) otomatis diabaikan.
    /** @return list<Wilayah> */
    protected function tangga(): array
    {
        $kunci = json_encode($this->pilihan) ?: '[]';

        if ($this->cacheTangga !== null && $this->cacheTanggaKunci === $kunci) {
            return $this->cacheTangga;
        }

        $tangga = [$this->titikAwal(auth()->user())];

        for ($indeks = 0; ; $indeks++) {
            $dipilih = $this->pilihan["p{$indeks}"] ?? null;

            if (blank($dipilih)) {
                break;
            }

            $node = $this->anakDari($tangga[$indeks])->firstWhere('id', (int) $dipilih);

            if ($node === null) {
                break;
            }

            $tangga[] = $node;

            if ($node->level === 'rt' || $this->anakDari($node)->isEmpty()) {
                break;
            }
        }

        $this->cacheTanggaKunci = $kunci;

        return $this->cacheTangga = $tangga;
    }

    /** @return Collection<int, Wilayah> */
    protected function anakDari(Wilayah $node): Collection
    {
        return $this->cacheAnak[$node->id] ??= $node->children()->with(['parent'])->orderBy('nama')->get();
    }

    // Label langkah mengikuti level anak bila seragam; kalau campur (mis. desa
    // punya dusun dan RW), pakai sebutan jelas.
    /** @param Collection<int, Wilayah> $anak */
    protected function labelLangkah(Collection $anak): string
    {
        $level = $anak->pluck('level')->unique();

        if ($level->count() === 1) {
            return Wilayah::labelLevel((string) $level->first());
        }

        if ($level->contains('dusun') && $level->contains('rw')) {
            return 'Dusun atau RW Langsung';
        }

        return 'Wilayah turunan';
    }

    // Menghapus pilihan mulai indeks $dari (dan semua yang lebih dalam).
    protected function pangkas(int $dari): void
    {
        foreach (array_keys($this->pilihan) as $kunci) {
            if (preg_match('/^p(\d+)$/', (string) $kunci, $cocok) && (int) $cocok[1] >= $dari) {
                unset($this->pilihan[$kunci]);
            }
        }

        $this->cacheTangga = null;
        $this->cacheTanggaKunci = null;
    }

    /** @return array<string, int> */
    protected function pilihanDariNode(Wilayah $node, Wilayah $start): array
    {
        $rantai = $node->ancestors()
            ->push($node)
            ->filter(fn (Wilayah $w): bool => str_starts_with((string) $w->path, (string) $start->path) && ! $w->is($start))
            ->values();

        $pilihan = [];

        foreach ($rantai as $indeks => $w) {
            $pilihan["p{$indeks}"] = $w->id;
        }

        return $pilihan;
    }

    protected function titikAwal(?User $user): Wilayah
    {
        if ($this->titikAwalCache !== null) {
            return $this->titikAwalCache;
        }

        if ($user !== null && ($user->isSuperadmin() || $user->isAdmin())) {
            $negara = Wilayah::where('level', 'negara')->orderBy('path')->first();

            abort_if($negara === null, 404, 'Wilayah akar (negara) belum ada.');

            return $this->titikAwalCache = $negara;
        }

        $wilayah = $user?->wilayah;

        abort_if($wilayah === null, 403);

        return $this->titikAwalCache = $wilayah;
    }

    protected function diDalamSubtree(?Wilayah $node, Wilayah $start): bool
    {
        return $node !== null && str_starts_with((string) $node->path, (string) $start->path);
    }
}
