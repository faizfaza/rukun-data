<?php

namespace App\Filament\Resources\WilayahResource\Pages;

use App\Filament\Resources\AnggotaResource;
use App\Filament\Resources\KeluargaResource;
use App\Filament\Resources\WilayahResource;
use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\Wilayah;
use App\Support\SensitiveData;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;

class ListWilayah extends ListRecords
{
    protected static string $resource = WilayahResource::class;

    protected string $view = 'filament.resources.wilayah-resource.pages.list-wilayah';

    #[Url(as: 'parent')]
    public ?int $parentId = null;

    #[Url(as: 'mode')]
    public string $mode = 'hirarki';

    // Tab parameter dipertahankan untuk kompatibilitas tautan lama
    #[Url(as: 'tab')]
    public string $tab = 'master';

    #[Url(as: 'level')]
    public string $levelFilter = '';

    #[Url(as: 'status')]
    public string $statusFilter = '';

    // Node yang dipilih saat membuka lewat tautan langsung.
    #[Url(as: 'wilayah')]
    public ?int $wilayahId = null;

    public string $cariKk = '';

    #[Url(as: 'prov')]
    public mixed $filterProvinsi = '';

    #[Url(as: 'kab')]
    public mixed $filterKabkota = '';

    #[Url(as: 'kec')]
    public mixed $filterKecamatan = '';

    #[Url(as: 'desa')]
    public mixed $filterDesa = '';

    #[Url(as: 'rw')]
    public mixed $filterRw = '';

    #[Url(as: 'rt')]
    public mixed $filterRt = '';

    public bool $tampilkanCascading = false;

    public function mount(): void
    {
        if (request()->query('tab') === 'jelajah') {
            $wilayah = request()->query('wilayah');

            $this->redirect(\App\Filament\Pages\JelajahWilayah::getUrl(array_filter(['wilayah' => $wilayah])));

            return;
        }

        parent::mount();

        $user = auth()->user();

        if ($this->wilayahId !== null && $this->parentId === null) {
            $this->parentId = $this->wilayahId;
        }

        if ($user?->isAdminRt() && $user->id_wilayah && $this->parentId === null) {
            $this->parentId = $user->id_wilayah;
        }

        if ($user?->isAdminRw() && $user->id_wilayah && $this->parentId === null && $this->mode === 'hirarki') {
            $this->parentId = $user->id_wilayah;
        }

        if ($this->parentId !== null) {
            $this->sinkronkanCascadingDariParent($this->parentId);
        }
    }

    public function getTitle(): string
    {
        $parent = $this->currentParent();
        if ($parent !== null) {
            return 'Kelola Wilayah · '.$parent->nama;
        }

        return 'Kelola Wilayah';
    }

    public function getBreadcrumb(): string
    {
        return 'Kelola Wilayah';
    }

    public function bolehMaster(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminRw());
    }

    public function isViewingRt(): bool
    {
        return $this->mode === 'hirarki' && $this->currentParent()?->level === 'rt';
    }

    public function getDaftarKeluargaProperty()
    {
        $parent = $this->currentParent();
        if (! $parent || $parent->level !== 'rt') {
            return collect();
        }

        $query = Keluarga::where('id_wilayah', $parent->id)
            ->with(['kepala'])
            ->withCount('anggota')
            ->latest();

        if (filled($this->cariKk)) {
            $digits = SensitiveData::digits($this->cariKk);
            if (strlen($digits) === 16) {
                $hash = SensitiveData::hash($digits);
                $query->where('no_kk_hash', $hash);
            } else {
                $query->where('kode_pos', 'like', '%'.$this->cariKk.'%');
            }
        }

        return $query->get();
    }

    public function getStatistikRtProperty(): array
    {
        $parent = $this->currentParent();
        if (! $parent || $parent->level !== 'rt') {
            return ['keluarga' => 0, 'anggota' => 0];
        }

        $keluargaCount = Keluarga::where('id_wilayah', $parent->id)->count();
        $anggotaCount = Anggota::whereIn(
            'id_keluarga',
            Keluarga::where('id_wilayah', $parent->id)->select('id')
        )->count();

        return [
            'keluarga' => $keluargaCount,
            'anggota' => $anggotaCount,
        ];
    }

    public function titikAwalJelajah(): ?Wilayah
    {
        if ($this->wilayahId !== null) {
            return Wilayah::find($this->wilayahId);
        }

        $user = auth()->user();

        if ($user?->isSuperadmin() || $user?->isAdmin()) {
            return Wilayah::where('level', 'negara')->orderBy('path')->first();
        }

        return $user?->wilayah;
    }

    protected function getHeaderActions(): array
    {
        $parent = $this->currentParent();
        $user = auth()->user();
        $actions = [];

        if ($parent !== null && $parent->level === 'rt') {
            if ($parent->aktifEfektif() && ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminRw() || $user?->isAdminRt())) {
                $actions[] = Action::make('tambahKeluarga')
                    ->label('Tambah KK')
                    ->icon('heroicon-o-plus')
                    ->color('primary')
                    ->button()
                    ->url(KeluargaResource::getUrl('create', ['id_wilayah' => $parent->id]));
            }

            if ($user?->can('update', $parent)) {
                $actions[] = Action::make('ubahRt')
                    ->label('Ubah Data RT')
                    ->icon('heroicon-o-pencil-square')
                    ->color('gray')
                    ->outlined()
                    ->button()
                    ->url(WilayahResource::getUrl('edit', ['record' => $parent->id]));
            }
        } else {
            if ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminRw()) {
                $actions[] = CreateAction::make()
                    ->label('Tambah wilayah')
                    ->url(fn (): string => WilayahResource::getUrl('create', array_filter([
                        'parent_id' => $this->parentId,
                    ])));
            }
        }

        $actions[] = Action::make('cariKkNik')
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
                    ->helperText('Masukkan 16 digit lengkap.'),
            ])
            ->action(function (array $data): void {
                $hash = SensitiveData::hash(SensitiveData::digits($data['nomor']));
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
            });

        return $actions;
    }

    protected function getTableQuery(): Builder
    {
        if ($this->isViewingRt()) {
            return parent::getTableQuery()->whereRaw('1 = 0');
        }

        $query = parent::getTableQuery()->with([Wilayah::RELASI_LELUHUR]);
        $user = auth()->user();

        if ($this->hasGranularFilter()) {
            $parent = $this->currentParent();
            if ($parent !== null && $parent->level !== 'negara') {
                $query->where('path', 'like', $parent->path.'%');
            }

            return $this->applyGranularWilayahFilters($query);
        }

        if (filled($this->levelFilter)) {
            $parent = $this->currentParent();

            if ($parent !== null) {
                $query->where('path', 'like', $parent->path.'%')->whereKeyNot($parent->getKey());
            }

            return $this->applyCustomFilters($query);
        }

        // Mode flat / semua: tampilkan seluruh wilayah sesuai hak akses pengguna
        if ($this->mode === 'semua') {
            return $this->applyCustomFilters($query);
        }

        // Jika ada pencarian di kotak cari tabel
        if (filled($this->getTableSearch())) {
            if ($this->parentId !== null) {
                $parent = $this->currentParent();
                if ($parent) {
                    return $this->applyCustomFilters($query->where('path', 'like', $parent->path.'%')->whereKeyNot($parent->getKey()));
                }
            }

            return $this->applyCustomFilters($query);
        }

        // Filter status khusus nonaktif atau down: jelajah turunan di bawah induk terpilih
        if (filled($this->statusFilter) && in_array($this->statusFilter, ['nonaktif', 'down'], true) && empty($this->levelFilter)) {
            $parent = $this->currentParent();
            if ($parent !== null) {
                return $this->applyCustomFilters($query->where('path', 'like', $parent->path.'%')->whereKeyNot($parent->getKey()));
            }

            return $this->applyCustomFilters($query);
        }

        // Khusus petugas RT:
        if ($user?->isAdminRt()) {
            return $this->applyCustomFilters($query->where('id', $user->id_wilayah));
        }

        // Khusus petugas RW:
        if ($user?->isAdminRw()) {
            $parent = $this->currentParent();
            if ($parent) {
                return $this->applyCustomFilters($query->where('parent_id', $parent->id));
            }

            return $this->applyCustomFilters($query->where('id', $user->id_wilayah));
        }

        // Superadmin & Admin di level akar:
        if ($this->parentId === null) {
            return $this->applyCustomFilters($query->whereNull('parent_id'));
        }

        // Superadmin & Admin di bawah induk terpilih:
        return $this->applyCustomFilters($query->where('parent_id', $this->parentId));
    }

    protected function applyLevelFilter(Builder $query): Builder
    {
        return $this->applyCustomFilters($query);
    }

    protected function applyCustomFilters(Builder $query): Builder
    {
        if (filled($this->levelFilter)) {
            $query->where('level', $this->levelFilter);
        }

        if ($this->statusFilter === 'aktif') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'nonaktif') {
            $query->where('is_active', false);
        } elseif ($this->statusFilter === 'down') {
            $driver = DB::connection()->getDriverName();
            $query->where(function (Builder $q) use ($driver) {
                $q->where('is_active', false)
                    ->orWhereExists(function ($sub) use ($driver) {
                        $sub->selectRaw('1')->from('wilayah as induk_nonaktif')
                            ->where('induk_nonaktif.is_active', false)
                            ->whereRaw('wilayah.path LIKE ' . ($driver === 'sqlite'
                                ? "induk_nonaktif.path || '%'"
                                : "CONCAT(induk_nonaktif.path, '%')"));
                    });
            });
        }

        return $query;
    }

    public function currentParent(): ?Wilayah
    {
        $user = auth()->user();

        if ($this->parentId === null) {
            if ($user?->isAdminRt() && $user->id_wilayah) {
                return $user->wilayah;
            }

            if ($user?->isAdminRw() && $user->id_wilayah) {
                return $user->wilayah;
            }

            return null;
        }

        $parent = Wilayah::find($this->parentId);
        if ($parent === null) {
            $this->parentId = $user?->isAdminRt()
                ? $user->id_wilayah
                : ($user?->isAdminRw() ? $user->id_wilayah : null);

            return $user?->isAdminRt() ? $user->wilayah : ($user?->isAdminRw() ? $user->wilayah : null);
        }

        if ($user?->isAdminRt()) {
            if ($parent->id !== $user->id_wilayah) {
                $this->parentId = $user->id_wilayah;

                return $user->wilayah;
            }
        }

        if ($user?->isAdminRw()) {
            if ($parent->id !== $user->id_wilayah && ! str_starts_with((string) $parent->path, (string) $user->wilayah?->path)) {
                $this->parentId = $user->id_wilayah;

                return $user->wilayah;
            }
        }

        return $parent;
    }

    /** @return list<Wilayah> */
    public function breadcrumbsList(): array
    {
        $parent = $this->currentParent();
        if ($parent === null) {
            return [];
        }

        $user = auth()->user();
        if ($user?->isAdminRt() && $user->wilayah !== null) {
            return [$user->wilayah];
        }

        if ($user?->isAdminRw() && $user->wilayah !== null) {
            $rw = $user->wilayah;
            if ($parent->id === $rw->id) {
                return [$rw];
            }

            $descendantAncestors = $parent->ancestors()
                ->filter(fn (Wilayah $w): bool => str_starts_with((string) $w->path, (string) $rw->path) && $w->id !== $rw->id)
                ->values()
                ->all();

            return [$rw, ...$descendantAncestors, $parent];
        }

        return [...$parent->ancestors()->all(), $parent];
    }

    public function updatedParentId(): void
    {
        $this->activeTab = null;
        unset($this->cachedTabs);
        $this->resetTable();
    }

    public function updatedMode(): void
    {
        $this->activeTab = null;
        unset($this->cachedTabs);
        $this->resetTable();
    }

    public function updatedLevelFilter(): void
    {
        $this->resetTable();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetTable();
    }

    public function sinkronkanCascadingDariParent(?int $parentId): void
    {
        // Granular filters are independent and only populated when user explicitly filters.
    }

    public function cariWilayahIds(string $level, mixed $input): array
    {
        if (blank($input)) {
            return [];
        }

        if (is_numeric($input)) {
            $found = Wilayah::where('id', (int) $input)->where(function ($q) use ($level) {
                if ($level === 'desa') {
                    $q->whereIn('level', ['desa', 'dusun']);
                } else {
                    $q->where('level', $level);
                }
            })->first();
            if ($found) {
                return [$found->id];
            }
        }

        $term = trim((string) $input);

        if (in_array($level, ['rw', 'rt'], true)) {
            $digits = preg_replace('/\D/', '', $term);

            return Wilayah::where('level', $level)
                ->where(function (Builder $q) use ($term, $digits, $level) {
                    $q->where('nama', 'like', '%'.$term.'%');
                    if ($digits !== '') {
                        $prefix = strtoupper($level);
                        $clean = ltrim($digits, '0');
                        $q->orWhere('nama', 'like', '%'.$prefix.'%'.$digits.'%')
                            ->orWhere('nama', 'like', '%'.$prefix.' '.$digits.'%')
                            ->orWhere('nama', 'like', '%'.$digits.'%');
                        if ($clean !== '') {
                            $q->orWhere('nama', 'like', '%'.$prefix.' '.$clean.'%')
                                ->orWhere('nama', 'like', '%'.$prefix.'%'.$clean.'%');
                        }
                    }
                })
                ->limit(50)
                ->pluck('id')
                ->all();
        }

        if ($level === 'desa') {
            return Wilayah::whereIn('level', ['desa', 'dusun'])
                ->where('nama', 'like', '%'.$term.'%')
                ->limit(50)
                ->pluck('id')
                ->all();
        }

        return Wilayah::where('level', $level)
            ->where('nama', 'like', '%'.$term.'%')
            ->limit(50)
            ->pluck('id')
            ->all();
    }

    public function hasGranularFilter(): bool
    {
        return filled($this->filterProvinsi)
            || filled($this->filterKabkota)
            || filled($this->filterKecamatan)
            || filled($this->filterDesa)
            || filled($this->filterRw)
            || filled($this->filterRt);
    }

    protected function applyGranularWilayahFilters(Builder $query): Builder
    {
        $targetLevel = filled($this->levelFilter) ? $this->levelFilter : null;

        if ($targetLevel === null) {
            if (filled($this->filterRt)) {
                $targetLevel = 'rt';
            } elseif (filled($this->filterRw)) {
                $targetLevel = 'rw';
            }
        }

        if ($targetLevel !== null) {
            $query->where('level', $targetLevel);
        }

        if (filled($this->filterRt)) {
            $term = trim((string) $this->filterRt);
            $digits = preg_replace('/\D/', '', $term);
            $clean = ltrim($digits, '0');

            $query->where(function (Builder $m) use ($term, $digits, $clean) {
                $m->where('nama', 'like', '%'.$term.'%');
                if ($digits !== '') {
                    $m->orWhere('nama', 'like', '%RT%'.$digits.'%')
                        ->orWhere('nama', 'like', '%RT '.$digits.'%')
                        ->orWhere('nama', 'like', '%'.$digits.'%');
                    if ($clean !== '') {
                        $m->orWhere('nama', 'like', '%RT '.$clean.'%')
                            ->orWhere('nama', 'like', '%RT%'.$clean.'%');
                    }
                }
            });
        }

        if (filled($this->filterRw)) {
            $term = trim((string) $this->filterRw);
            $digits = preg_replace('/\D/', '', $term);
            $clean = ltrim($digits, '0');

            if ($targetLevel === 'rw') {
                $query->where(function (Builder $m) use ($term, $digits, $clean) {
                    $m->where('nama', 'like', '%'.$term.'%');
                    if ($digits !== '') {
                        $m->orWhere('nama', 'like', '%RW%'.$digits.'%')
                            ->orWhere('nama', 'like', '%RW '.$digits.'%')
                            ->orWhere('nama', 'like', '%'.$digits.'%');
                        if ($clean !== '') {
                            $m->orWhere('nama', 'like', '%RW '.$clean.'%')
                                ->orWhere('nama', 'like', '%RW%'.$clean.'%');
                        }
                    }
                });
            } else {
                $query->whereHas('parent', function (Builder $parentRw) use ($term, $digits, $clean) {
                    $parentRw->where('level', 'rw')
                        ->where(function (Builder $m) use ($term, $digits, $clean) {
                            $m->where('nama', 'like', '%'.$term.'%');
                            if ($digits !== '') {
                                $m->orWhere('nama', 'like', '%RW%'.$digits.'%')
                                    ->orWhere('nama', 'like', '%RW '.$digits.'%')
                                    ->orWhere('nama', 'like', '%'.$digits.'%');
                                if ($clean !== '') {
                                    $m->orWhere('nama', 'like', '%RW '.$clean.'%')
                                        ->orWhere('nama', 'like', '%RW%'.$clean.'%');
                                }
                            }
                        });
                });
            }
        }

        if (filled($this->filterDesa)) {
            $desaIds = $this->cariWilayahIds('desa', $this->filterDesa);
            $query->where(function (Builder $sub) use ($desaIds) {
                if (empty($this->filterRw) && empty($this->filterRt)) {
                    $sub->whereIn('id', $desaIds);
                }
                foreach ($desaIds as $id) {
                    $sub->orWhere('path', 'like', '%/'.$id.'/%');
                }
            });
        }

        if (filled($this->filterKecamatan)) {
            $kecIds = $this->cariWilayahIds('kecamatan', $this->filterKecamatan);
            $query->where(function (Builder $sub) use ($kecIds) {
                if (empty($this->filterDesa) && empty($this->filterRw) && empty($this->filterRt)) {
                    $sub->whereIn('id', $kecIds);
                }
                foreach ($kecIds as $id) {
                    $sub->orWhere('path', 'like', '%/'.$id.'/%');
                }
            });
        }

        if (filled($this->filterKabkota)) {
            $kabIds = $this->cariWilayahIds('kabupaten_kota', $this->filterKabkota);
            $query->where(function (Builder $sub) use ($kabIds) {
                if (empty($this->filterKecamatan) && empty($this->filterDesa) && empty($this->filterRw) && empty($this->filterRt)) {
                    $sub->whereIn('id', $kabIds);
                }
                foreach ($kabIds as $id) {
                    $sub->orWhere('path', 'like', '%/'.$id.'/%');
                }
            });
        }

        if (filled($this->filterProvinsi)) {
            $provIds = $this->cariWilayahIds('provinsi', $this->filterProvinsi);
            $query->where(function (Builder $sub) use ($provIds) {
                if (empty($this->filterKabkota) && empty($this->filterKecamatan) && empty($this->filterDesa) && empty($this->filterRw) && empty($this->filterRt)) {
                    $sub->whereIn('id', $provIds);
                }
                foreach ($provIds as $id) {
                    $sub->orWhere('path', 'like', '%/'.$id.'/%');
                }
            });
        }

        return $this->applyCustomFilters($query);
    }

    public function updatedFilterProvinsi(mixed $value = null): void
    {
        if (is_numeric($this->filterProvinsi) && (int) $this->filterProvinsi > 0) {
            $this->parentId = (int) $this->filterProvinsi;
        }
        $this->resetTable();
    }

    public function updatedFilterKabkota(mixed $value = null): void
    {
        if (is_numeric($this->filterKabkota) && (int) $this->filterKabkota > 0) {
            $this->parentId = (int) $this->filterKabkota;
        }
        $this->resetTable();
    }

    public function updatedFilterKecamatan(mixed $value = null): void
    {
        if (is_numeric($this->filterKecamatan) && (int) $this->filterKecamatan > 0) {
            $this->parentId = (int) $this->filterKecamatan;
        }
        $this->resetTable();
    }

    public function updatedFilterDesa(mixed $value = null): void
    {
        if (is_numeric($this->filterDesa) && (int) $this->filterDesa > 0) {
            $this->parentId = (int) $this->filterDesa;
        }
        $this->resetTable();
    }

    public function updatedFilterRw(mixed $value = null): void
    {
        $this->resetTable();
    }

    public function updatedFilterRt(mixed $value = null): void
    {
        $this->resetTable();
    }

    protected function scopeDaftarUntukPengguna(Builder $q): Builder
    {
        $user = auth()->user();
        if ($user?->isAdminRt() && $user->wilayah) {
            $q->where('id', $user->id_wilayah);
        } elseif ($user?->isAdminRw() && $user->wilayah) {
            $rwPath = $user->wilayah->path;
            $q->where(function (Builder $sub) use ($user, $rwPath) {
                $sub->where('id', $user->id_wilayah)
                    ->orWhere('path', 'like', $rwPath.'%');
            });
        }

        return $q;
    }

    public function getDaftarProvinsiProperty(): array
    {
        $q = Wilayah::where('level', 'provinsi');
        $this->scopeDaftarUntukPengguna($q);
        if ($this->statusFilter === 'aktif') {
            $q->where('is_active', true);
        } elseif ($this->statusFilter === 'nonaktif') {
            $q->where('is_active', false);
        }

        return $q->orderBy('nama')->pluck('nama')->all();
    }

    public function getDaftarKabkotaProperty(): array
    {
        $q = Wilayah::where('level', 'kabupaten_kota');
        $this->scopeDaftarUntukPengguna($q);
        if ($this->statusFilter === 'aktif') {
            $q->where('is_active', true);
        } elseif ($this->statusFilter === 'nonaktif') {
            $q->where('is_active', false);
        }

        if (filled($this->filterProvinsi)) {
            $provIds = $this->cariWilayahIds('provinsi', $this->filterProvinsi);
            if ($provIds) {
                $q->whereIn('parent_id', $provIds);
            }
        }

        return $q->orderBy('nama')->limit(50)->pluck('nama')->all();
    }

    public function getDaftarKecamatanProperty(): array
    {
        $q = Wilayah::where('level', 'kecamatan');
        $this->scopeDaftarUntukPengguna($q);
        if ($this->statusFilter === 'aktif') {
            $q->where('is_active', true);
        } elseif ($this->statusFilter === 'nonaktif') {
            $q->where('is_active', false);
        }

        if (filled($this->filterKabkota)) {
            $kabIds = $this->cariWilayahIds('kabupaten_kota', $this->filterKabkota);
            if ($kabIds) {
                $q->whereIn('parent_id', $kabIds);
            }
        }

        return $q->orderBy('nama')->limit(50)->pluck('nama')->all();
    }

    public function getDaftarDesaProperty(): array
    {
        $q = Wilayah::whereIn('level', ['desa', 'dusun']);
        $this->scopeDaftarUntukPengguna($q);
        if ($this->statusFilter === 'aktif') {
            $q->where('is_active', true);
        } elseif ($this->statusFilter === 'nonaktif') {
            $q->where('is_active', false);
        }

        if (filled($this->filterKecamatan)) {
            $kecIds = $this->cariWilayahIds('kecamatan', $this->filterKecamatan);
            if ($kecIds) {
                $q->where(function (Builder $sub) use ($kecIds) {
                    $sub->whereIn('parent_id', $kecIds)
                        ->orWhereHas('parent', fn ($p) => $p->whereIn('parent_id', $kecIds));
                });
            }
        }

        return $q->orderBy('nama')->limit(50)->pluck('nama')->all();
    }

    public function getDaftarRwProperty(): array
    {
        $user = auth()->user();
        if ($user?->isAdminRw() && $user->wilayah) {
            return [$user->wilayah->nama];
        }

        $options = [];
        for ($i = 1; $i <= 30; $i++) {
            $options[] = sprintf('RW %02d', $i);
        }

        return $options;
    }

    public function getDaftarRtProperty(): array
    {
        $user = auth()->user();
        if ($user?->isAdminRw() && $user->wilayah) {
            return $user->wilayah->children()->where('level', 'rt')->orderBy('nama')->pluck('nama')->all();
        }
        if ($user?->isAdminRt() && $user->wilayah) {
            return [$user->wilayah->nama];
        }

        $options = [];
        for ($i = 1; $i <= 30; $i++) {
            $options[] = sprintf('RT %02d', $i);
        }

        return $options;
    }

    public function getJumlahFilterAktifProperty(): int
    {
        return ($this->levelFilter ? 1 : 0)
            + ($this->statusFilter ? 1 : 0)
            + (filled($this->filterProvinsi) ? 1 : 0)
            + (filled($this->filterKabkota) ? 1 : 0)
            + (filled($this->filterKecamatan) ? 1 : 0)
            + (filled($this->filterDesa) ? 1 : 0)
            + (filled($this->filterRw) ? 1 : 0)
            + (filled($this->filterRt) ? 1 : 0);
    }

    public function getNamaProvinsiTerpilihProperty(): ?string
    {
        if (blank($this->filterProvinsi)) {
            return null;
        }

        return is_numeric($this->filterProvinsi) ? Wilayah::find((int) $this->filterProvinsi)?->nama : (string) $this->filterProvinsi;
    }

    public function getNamaKabkotaTerpilihProperty(): ?string
    {
        if (blank($this->filterKabkota)) {
            return null;
        }

        return is_numeric($this->filterKabkota) ? Wilayah::find((int) $this->filterKabkota)?->nama : (string) $this->filterKabkota;
    }

    public function getNamaKecamatanTerpilihProperty(): ?string
    {
        if (blank($this->filterKecamatan)) {
            return null;
        }

        return is_numeric($this->filterKecamatan) ? Wilayah::find((int) $this->filterKecamatan)?->nama : (string) $this->filterKecamatan;
    }

    public function getNamaDesaTerpilihProperty(): ?string
    {
        if (blank($this->filterDesa)) {
            return null;
        }

        return is_numeric($this->filterDesa) ? Wilayah::find((int) $this->filterDesa)?->nama : (string) $this->filterDesa;
    }

    public function getNamaRwTerpilihProperty(): ?string
    {
        if (blank($this->filterRw)) {
            return null;
        }

        return is_numeric($this->filterRw) ? Wilayah::find((int) $this->filterRw)?->nama : (string) $this->filterRw;
    }

    public function getNamaRtTerpilihProperty(): ?string
    {
        if (blank($this->filterRt)) {
            return null;
        }

        return is_numeric($this->filterRt) ? Wilayah::find((int) $this->filterRt)?->nama : (string) $this->filterRt;
    }

    public function hapusFilterLevel(): void
    {
        $this->levelFilter = '';
        $this->resetTable();
    }

    public function hapusFilterStatus(): void
    {
        $this->statusFilter = '';
        $this->resetTable();
    }

    public function hapusFilterProvinsi(): void
    {
        $this->filterProvinsi = '';
        $this->parentId = Wilayah::where('level', 'negara')->value('id');
        $this->resetTable();
    }

    public function hapusFilterKabkota(): void
    {
        $this->filterKabkota = '';
        $this->resetTable();
    }

    public function hapusFilterKecamatan(): void
    {
        $this->filterKecamatan = '';
        $this->resetTable();
    }

    public function hapusFilterDesa(): void
    {
        $this->filterDesa = '';
        $this->resetTable();
    }

    public function hapusFilterRw(): void
    {
        $this->filterRw = '';
        $this->resetTable();
    }

    public function hapusFilterRt(): void
    {
        $this->filterRt = '';
        $this->resetTable();
    }

    public function resetGranularFilters(): void
    {
        $this->filterProvinsi = '';
        $this->filterKabkota = '';
        $this->filterKecamatan = '';
        $this->filterDesa = '';
        $this->filterRw = '';
        $this->filterRt = '';
        $this->parentId = Wilayah::where('level', 'negara')->value('id');
        $this->resetTable();
    }

    public function hasActiveWilayahFilter(): bool
    {
        return filled($this->tableSearch)
            || filled($this->levelFilter)
            || filled($this->statusFilter)
            || $this->hasGranularFilter();
    }

    public function resetWilayahFilter(): void
    {
        $this->tableSearch = '';
        $this->levelFilter = '';
        $this->statusFilter = '';
        $this->filterProvinsi = '';
        $this->filterKabkota = '';
        $this->filterKecamatan = '';
        $this->filterDesa = '';
        $this->filterRw = '';
        $this->filterRt = '';

        $user = auth()->user();
        if ($user?->isAdminRt() && $user->id_wilayah) {
            $this->parentId = $user->id_wilayah;
        } elseif ($user?->isAdminRw() && $user->id_wilayah) {
            $this->parentId = $user->id_wilayah;
        } else {
            $this->parentId = Wilayah::where('level', 'negara')->value('id');
        }

        $this->resetTable();
    }

    public function updatedTab(): void
    {
        $this->activeTab = null;
        unset($this->cachedTabs);
        $this->resetTable();
    }

    public function updatedWilayahId(): void
    {
        $this->resetTable();
    }

    public function getTabs(): array
    {
        if ($this->mode !== 'hirarki' || $this->parentId === null) {
            return [];
        }

        $parent = $this->currentParent();
        if ($parent === null) {
            return [];
        }

        $anak = $parent->children();
        $jumlahPerLevel = (clone $anak)
            ->selectRaw('level, COUNT(*) as total')
            ->groupBy('level')
            ->pluck('total', 'level');

        $levels = $jumlahPerLevel->keys()->all();

        // Jika anak seragam (misal hanya provinsi, hanya RW, atau hanya RT), tidak perlu tab.
        if (count($levels) <= 1) {
            return [];
        }

        $total = (int) $jumlahPerLevel->sum();
        $dusunCount = (int) ($jumlahPerLevel['dusun'] ?? 0);
        $rwCount = (int) ($jumlahPerLevel['rw'] ?? 0);

        $tabs = [
            'semua' => Tab::make('Semua Turunan')
                ->badge($total),
        ];

        if ($dusunCount > 0) {
            $tabs['dusun'] = Tab::make('Dusun')
                ->icon('heroicon-m-folder')
                ->badge($dusunCount)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('level', 'dusun'));
        }

        if ($rwCount > 0) {
            $tabs['rw'] = Tab::make('RW Langsung (Non-Dusun)')
                ->icon('heroicon-m-home-modern')
                ->badge($rwCount)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('level', 'rw'));
        }

        $otherLevels = array_diff($levels, ['dusun', 'rw']);
        foreach ($otherLevels as $lvl) {
            $cnt = (int) ($jumlahPerLevel[$lvl] ?? 0);
            $tabs[$lvl] = Tab::make(Wilayah::labelLevel($lvl))
                ->badge($cnt)
                ->modifyQueryUsing(fn (Builder $query): Builder => $query->where('level', $lvl));
        }

        return $tabs;
    }

    public function bukaWilayah(int $id): void
    {
        $target = Wilayah::find($id);
        if ($target === null) {
            return;
        }

        $user = auth()->user();
        if ($user?->isAdminRw()) {
            if ($target->id !== $user->id_wilayah && ! str_starts_with((string) $target->path, (string) $user->wilayah?->path)) {
                return;
            }
        }

        $this->parentId = $target->id;
        $this->sinkronkanCascadingDariParent($target->id);
        $this->activeTab = null;
        unset($this->cachedTabs);
        $this->resetTable();
    }

    public function keAkar(): void
    {
        $user = auth()->user();
        $this->parentId = ($user?->isAdminRw() && $user->id_wilayah) ? $user->id_wilayah : null;
        $this->sinkronkanCascadingDariParent($this->parentId);
        $this->activeTab = null;
        unset($this->cachedTabs);
        $this->resetTable();
    }

    public function kembaliKeInduk(): void
    {
        $parent = $this->currentParent();
        if ($parent === null) {
            return;
        }

        $user = auth()->user();
        if ($user?->isAdminRw() && $parent->id === $user->id_wilayah) {
            return;
        }

        $this->parentId = $parent->parent_id;
        $this->activeTab = null;
        unset($this->cachedTabs);
        $this->resetTable();
    }
}
