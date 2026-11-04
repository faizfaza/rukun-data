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

    public function pilih(int $id): void
    {
        $this->wilayah = $id ?: null;
        $this->halamanWilayah = 1;
        $this->cariWilayah = '';
        $this->global = false;
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
        $this->resetTable();
    }

    public function hasActiveFilter(): bool
    {
        return filled($this->tableSearch)
            || filled($this->roleFilter)
            || filled($this->statusFilter)
            || $this->wilayah !== null
            || $this->global
            || ! $this->turunan;
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
