<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\Keluarga;
use App\Models\User;
use App\Models\Wilayah;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected static ?string $title = 'Kelola Admin';

    protected const WILAYAH_PER_HALAMAN = 25;

    protected string $view = 'filament.resources.users.directory';

    #[Url]
    public string $cariWilayah = '';

    #[Url]
    public int $halamanWilayah = 1;

    public function mount(): void
    {
        parent::mount();

        $this->halamanWilayah = max(1, min($this->halamanWilayah, $this->totalHalamanWilayah()));

        if ($this->wilayah !== null) {
            $this->sinkronkanCascadingDariWilayah($this->wilayah);
        }
    }

    public function updatedCariWilayah(): void
    {
        $this->halamanWilayah = 1;
    }

    public function lanjutWilayah(): void
    {
        $this->halamanWilayah = min($this->totalHalamanWilayah(), $this->halamanWilayah + 1);
    }

    public function mundurWilayah(): void
    {
        $this->halamanWilayah = max(1, $this->halamanWilayah - 1);
    }

    public function wilayahPerHalaman(): int
    {
        return self::WILAYAH_PER_HALAMAN;
    }

    public function totalWilayah(): int
    {
        return $this->nodesQuery()->count();
    }

    public function totalHalamanWilayah(): int
    {
        return max(1, (int) ceil($this->totalWilayah() / self::WILAYAH_PER_HALAMAN));
    }

    #[Url]
    public ?int $wilayah = null;

    #[Url]
    public bool $turunan = true;

    #[Url]
    public bool $global = false;

    #[Url]
    public string $roleFilter = '';

    #[Url]
    public string $statusFilter = '';

    public mixed $filterProvinsi = '';
    public mixed $filterKabkota = '';
    public mixed $filterKecamatan = '';
    public mixed $filterDesa = '';
    public mixed $filterRw = '';
    public mixed $filterRt = '';

    public function node(): ?Wilayah
    {
        if (! $this->wilayah) {
            return null;
        }

        $node = Wilayah::findOrFail($this->wilayah);

        // Indonesia adalah satu-satunya negara; perlakukan level negara
        // sebagai akar agar daftar langsung mulai dari provinsi.
        return $node->level === 'negara' ? null : $node;
    }

    protected function nodesQuery(): Builder
    {
        $node = $this->node();

        return Wilayah::query()
            ->when($this->cariWilayah === '', function ($query): void {
                if ($this->wilayah) {
                    $query->where('parent_id', $this->wilayah);

                    return;
                }

                $query->where('level', 'provinsi');
            })
            ->when($this->cariWilayah !== '', function ($query) use ($node): void {
                $query->where('level', '!=', 'negara')
                    ->where(fn ($q) => $q->where('nama', 'like', '%'.$this->cariWilayah.'%')->orWhere('kode', 'like', '%'.$this->cariWilayah.'%'));

                if ($node) {
                    $query->where('path', 'like', $node->path.'%')->whereKeyNot($node->id);
                }
            })
            ->orderBy('kode')
            ->orderBy('nama');
    }

    public function nodes()
    {
        return $this->nodesQuery()
            ->offset(($this->halamanWilayah - 1) * self::WILAYAH_PER_HALAMAN)
            ->limit(self::WILAYAH_PER_HALAMAN)
            ->get();
    }

    public function counters(): array
    {
        $node = $this->node();
        $ids = Wilayah::query()->when($node, fn ($q) => $q->where('path', 'like', $node->path.'%'))->select('id');

        $admins = User::query();
        $families = Keluarga::query();
        if ($this->global) {
            return [$admins->whereNull('id_wilayah')->count(), 0];
        }
        if ($node) {
            if ($this->turunan) {
                $admins->whereIn('id_wilayah', clone $ids);
                $families->whereIn('id_wilayah', $ids);
            } else {
                $admins->where('id_wilayah', $node->id);
                $families->where('id_wilayah', $node->id);
            }
        }

        return [$admins->count(), $families->count()];
    }

    public function sinkronkanCascadingDariWilayah(?int $wilayahId): void
    {
        if ($wilayahId === null) {
            $this->filterProvinsi = null;
            $this->filterKabkota = null;
            $this->filterKecamatan = null;
            $this->filterDesa = null;
            $this->filterRw = null;
            $this->filterRt = null;

            return;
        }

        $target = Wilayah::find($wilayahId);
        if ($target === null) {
            return;
        }

        $chain = $target->ancestors()->push($target);
        $this->filterProvinsi = $chain->firstWhere('level', 'provinsi')?->id;
        $this->filterKabkota = $chain->firstWhere('level', 'kabupaten_kota')?->id;
        $this->filterKecamatan = $chain->firstWhere('level', 'kecamatan')?->id;
        $this->filterDesa = $chain->firstWhere('level', 'desa')?->id;
        $this->filterRw = $chain->firstWhere('level', 'rw')?->id;
        $this->filterRt = $chain->firstWhere('level', 'rt')?->id;
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

    public function updatedFilterProvinsi(mixed $value = null): void
    {
        if (is_numeric($this->filterProvinsi) && (int) $this->filterProvinsi > 0 && Wilayah::where('id', (int) $this->filterProvinsi)->where('level', 'provinsi')->exists()) {
            $this->wilayah = (int) $this->filterProvinsi;
        }
        $this->global = false;
        $this->halamanWilayah = 1;
        $this->resetTable();
    }

    public function updatedFilterKabkota(mixed $value = null): void
    {
        if (is_numeric($this->filterKabkota) && (int) $this->filterKabkota > 0 && Wilayah::where('id', (int) $this->filterKabkota)->where('level', 'kabupaten_kota')->exists()) {
            $this->wilayah = (int) $this->filterKabkota;
        }
        $this->global = false;
        $this->halamanWilayah = 1;
        $this->resetTable();
    }

    public function updatedFilterKecamatan(mixed $value = null): void
    {
        if (is_numeric($this->filterKecamatan) && (int) $this->filterKecamatan > 0 && Wilayah::where('id', (int) $this->filterKecamatan)->where('level', 'kecamatan')->exists()) {
            $this->wilayah = (int) $this->filterKecamatan;
        }
        $this->global = false;
        $this->halamanWilayah = 1;
        $this->resetTable();
    }

    public function updatedFilterDesa(mixed $value = null): void
    {
        if (is_numeric($this->filterDesa) && (int) $this->filterDesa > 0 && Wilayah::where('id', (int) $this->filterDesa)->where('level', 'desa')->exists()) {
            $this->wilayah = (int) $this->filterDesa;
        }
        $this->global = false;
        $this->halamanWilayah = 1;
        $this->resetTable();
    }

    public function updatedFilterRw(mixed $value = null): void
    {
        if (is_numeric($this->filterRw) && (int) $this->filterRw > 0 && Wilayah::where('id', (int) $this->filterRw)->where('level', 'rw')->exists()) {
            $this->wilayah = (int) $this->filterRw;
        }
        $this->global = false;
        $this->halamanWilayah = 1;
        $this->resetTable();
    }

    public function updatedFilterRt(mixed $value = null): void
    {
        if (is_numeric($this->filterRt) && (int) $this->filterRt > 0 && Wilayah::where('id', (int) $this->filterRt)->where('level', 'rt')->exists()) {
            $this->wilayah = (int) $this->filterRt;
        }
        $this->global = false;
        $this->halamanWilayah = 1;
        $this->resetTable();
    }

    public function getDaftarProvinsiProperty(): array
    {
        $q = Wilayah::where('level', 'provinsi');
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

    public function getOpsiProvinsiProperty(): array
    {
        return $this->daftarProvinsi;
    }

    public function getOpsiKabkotaProperty(): array
    {
        return $this->daftarKabkota;
    }

    public function getOpsiKecamatanProperty(): array
    {
        return $this->daftarKecamatan;
    }

    public function getOpsiDesaProperty(): array
    {
        return $this->daftarDesa;
    }

    public function getOpsiRwProperty(): array
    {
        return $this->daftarRw;
    }

    public function getOpsiRtProperty(): array
    {
        return $this->daftarRt;
    }

    public function pilih(int $id): void
    {
        $this->wilayah = $id ?: null;
        $this->halamanWilayah = 1;
        $this->cariWilayah = '';
        $this->global = false;
        $this->sinkronkanCascadingDariWilayah($this->wilayah);
        $this->resetTable();
    }

    public function updatedTurunan(): void
    {
        $this->resetTable();
    }

    public function updatedGlobal(): void
    {
        $this->resetTable();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetTable();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetTable();
    }

    public function resetFilter(): void
    {
        $this->tableSearch = '';
        $this->roleFilter = '';
        $this->statusFilter = '';
        $this->wilayah = null;
        $this->global = false;
        $this->turunan = true;
        $this->cariWilayah = '';
        $this->halamanWilayah = 1;
        $this->resetGranularFilters();
        $this->resetTable();
    }

    public function getJumlahFilterAktifProperty(): int
    {
        return ($this->roleFilter ? 1 : 0)
            + ($this->statusFilter ? 1 : 0)
            + ($this->node() ? 1 : 0)
            + ($this->global ? 1 : 0)
            + (! $this->turunan ? 1 : 0)
            + (filled($this->filterProvinsi) ? 1 : 0)
            + (filled($this->filterKabkota) ? 1 : 0)
            + (filled($this->filterKecamatan) ? 1 : 0)
            + (filled($this->filterDesa) ? 1 : 0)
            + (filled($this->filterRw) ? 1 : 0)
            + (filled($this->filterRt) ? 1 : 0);
    }

    public function getNamaWilayahTerpilihProperty(): ?string
    {
        return $this->node()?->nama;
    }

    public function getNamaProvinsiTerpilihProperty(): ?string
    {
        if (blank($this->filterProvinsi)) {
            return null;
        }

        return is_numeric($this->filterProvinsi) ? Wilayah::find((int) $this->filterProvinsi)?->nama : (string) $this->filterProvinsi;
    }

    public function getNamaFilterProvinsiProperty(): ?string
    {
        return $this->namaProvinsiTerpilih;
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

    public function hapusFilterRole(): void
    {
        $this->roleFilter = '';
        $this->resetTable();
    }

    public function hapusFilterStatus(): void
    {
        $this->statusFilter = '';
        $this->resetTable();
    }

    public function hapusFilterWilayah(): void
    {
        $this->pilih(0);
    }

    public function hapusFilterProvinsi(): void
    {
        $this->filterProvinsi = '';
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
        $this->resetTable();
    }

    public function hasActiveFilter(): bool
    {
        return filled($this->tableSearch)
            || filled($this->roleFilter)
            || filled($this->statusFilter)
            || $this->node() !== null
            || $this->global
            || ! $this->turunan
            || $this->hasGranularFilter();
    }

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();

        if (filled($this->roleFilter)) {
            $query->where('role', $this->roleFilter);
        }

        if ($this->statusFilter === 'aktif') {
            $query->where('is_active', true);
        } elseif ($this->statusFilter === 'nonaktif') {
            $query->where('is_active', false);
        }

        if ($this->global) {
            return $query->whereNull('id_wilayah');
        }

        if ($this->hasGranularFilter()) {
            $query->whereHas('wilayah', function (Builder $wQ) {
                if (filled($this->filterRt)) {
                    if (is_numeric($this->filterRt) && ($rtModel = Wilayah::where('id', (int) $this->filterRt)->where('level', 'rt')->first())) {
                        $wQ->where('id', $rtModel->id);
                    } else {
                        $term = trim((string) $this->filterRt);
                        $digits = preg_replace('/\D/', '', $term);
                        $clean = ltrim($digits, '0');

                        $wQ->where('level', 'rt')
                            ->where(function (Builder $m) use ($term, $digits, $clean) {
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
                }

                if (filled($this->filterRw)) {
                    if (is_numeric($this->filterRw) && ($rwModel = Wilayah::where('id', (int) $this->filterRw)->where('level', 'rw')->first())) {
                        $wQ->where(function (Builder $sub) use ($rwModel) {
                            $sub->where('id', $rwModel->id)
                                ->orWhere('parent_id', $rwModel->id);
                        });
                    } else {
                        $term = trim((string) $this->filterRw);
                        $digits = preg_replace('/\D/', '', $term);
                        $clean = ltrim($digits, '0');

                        $wQ->where(function (Builder $sub) use ($term, $digits, $clean) {
                            $sub->where(function (Builder $rw) use ($term, $digits, $clean) {
                                $rw->where('level', 'rw')
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
                            })->orWhereHas('parent', function (Builder $parentRw) use ($term, $digits, $clean) {
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
                        });
                    }
                }

                if (filled($this->filterDesa)) {
                    $desaIds = $this->cariWilayahIds('desa', $this->filterDesa);
                    $wQ->where(function (Builder $sub) use ($desaIds) {
                        $sub->whereIn('id', $desaIds);
                        foreach ($desaIds as $id) {
                            $sub->orWhere('path', 'like', '%/'.$id.'/%');
                        }
                    });
                }

                if (filled($this->filterKecamatan)) {
                    $kecIds = $this->cariWilayahIds('kecamatan', $this->filterKecamatan);
                    $wQ->where(function (Builder $sub) use ($kecIds) {
                        $sub->whereIn('id', $kecIds);
                        foreach ($kecIds as $id) {
                            $sub->orWhere('path', 'like', '%/'.$id.'/%');
                        }
                    });
                }

                if (filled($this->filterKabkota)) {
                    $kabIds = $this->cariWilayahIds('kabupaten_kota', $this->filterKabkota);
                    $wQ->where(function (Builder $sub) use ($kabIds) {
                        $sub->whereIn('id', $kabIds);
                        foreach ($kabIds as $id) {
                            $sub->orWhere('path', 'like', '%/'.$id.'/%');
                        }
                    });
                }

                if (filled($this->filterProvinsi)) {
                    $provIds = $this->cariWilayahIds('provinsi', $this->filterProvinsi);
                    $wQ->where(function (Builder $sub) use ($provIds) {
                        $sub->whereIn('id', $provIds);
                        foreach ($provIds as $id) {
                            $sub->orWhere('path', 'like', '%/'.$id.'/%');
                        }
                    });
                }
            });
        }

        if ($node = $this->node()) {
            return $this->turunan ? $query->whereHas('wilayah', fn ($q) => $q->where('path', 'like', $node->path.'%')) : $query->where('id_wilayah', $node->id);
        }

        return $query;
    }

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->label('Tambah Admin')->url(fn () => UserResource::getUrl('create', array_filter(['id_wilayah' => ! $this->global && in_array($this->node()?->level, ['rw', 'rt']) ? $this->wilayah : null, 'context' => $this->wilayah])))];
    }
}
