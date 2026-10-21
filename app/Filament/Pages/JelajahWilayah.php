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

    protected static ?string $title = 'Jelajah Wilayah';

    protected static ?string $navigationLabel = 'Jelajah Wilayah';

    protected static string|\UnitEnum|null $navigationGroup = 'Pendataan';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.jelajah-wilayah';

    // Pilihan bertahap: p0 = pilihan pertama (mis. provinsi), p1 = berikutnya.
    /** @var array<string, int|string|null> */
    public array $pilihan = [];

    // Kata kunci untuk daftar langkah yang sedang aktif.
    public string $cari = '';

    protected ?Wilayah $titikAwalCache = null;

    /** @var array<int, Collection<int, Wilayah>> */
    protected array $cacheAnak = [];

    /** @var list<Wilayah>|null */
    protected ?array $cacheTangga = null;

    protected ?string $cacheTanggaKunci = null;

    // Rute memakai parameter opsional agar node tertentu bisa ditautkan langsung.
    public static function getRoutePath(Panel $panel): string
    {
        return '/jelajah/{wilayah?}';
    }

    // Tautan langsung (mis. /admin/jelajah/12) mengisi rantai sampai node itu.
    // Node di luar subtree pengguna → 404.
    public function mount(?Wilayah $wilayah = null): void
    {
        $start = $this->titikAwal(auth()->user());

        if ($wilayah !== null) {
            abort_unless($this->diDalamSubtree($wilayah, $start), 404);

            $this->pilihan = $this->pilihanDariNode($wilayah, $start);
        }
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
            ->emptyStateDescription('Pilih Tambah keluarga untuk memulai.');
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

        if ($this->bolehTambahKeluarga()) {
            $actions[] = Action::make('tambahKeluarga')
                ->label('Tambah keluarga')
                ->icon('heroicon-o-plus')
                ->url(fn (): string => KeluargaResource::getUrl('create', ['id_wilayah' => $this->nodeTerpilihRt()->id]));
        }

        return $actions;
    }

    // ---- Aksi pemilih -------------------------------------------------------

    // Memilih satu opsi pada langkah ke-$indeks (indeks langkah aktif).
    public function pilih(int $indeks, int $id): void
    {
        $langkah = $this->langkahSaatIni();

        abort_if($langkah === null || $langkah['indeks'] !== $indeks, 404);

        $opsi = $this->anakDari($langkah['induk'])->firstWhere('id', $id);

        abort_if($opsi === null, 404);

        $this->pilihan["p{$indeks}"] = $opsi->id;
        $this->pangkas($indeks + 1);
        $this->cari = '';
        $this->resetTable();
    }

    // Kembali ke node pada posisi rantai $posisi (0 = titik awal).
    public function kembaliKe(int $posisi): void
    {
        $this->pangkas($posisi);
        $this->cari = '';
        $this->resetTable();
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
        return $this->nodeTerpilihRt() !== null
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
        return Wilayah::labelLevel((string) $wilayah->level);
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
        return $this->cacheAnak[$node->id] ??= $node->children()->orderBy('nama')->get();
    }

    // Label langkah mengikuti level anak bila seragam; kalau campur (mis. desa
    // punya dusun dan RW), pakai sebutan umum.
    /** @param Collection<int, Wilayah> $anak */
    protected function labelLangkah(Collection $anak): string
    {
        $level = $anak->pluck('level')->unique();

        return $level->count() === 1
            ? Wilayah::labelLevel((string) $level->first())
            : 'Wilayah turunan';
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

        if ($user !== null && $user->isSuperadmin()) {
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
