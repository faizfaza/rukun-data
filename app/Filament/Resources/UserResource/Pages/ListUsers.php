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

    protected string $view = 'filament.resources.users.directory';

    #[Url]
    public string $cariWilayah = '';

    #[Url]
    public int $halamanWilayah = 1;

    public function updatedCariWilayah(): void
    {
        $this->halamanWilayah = 1;
    }

    public function lanjutWilayah(): void
    {
        $this->halamanWilayah++;
    }

    public function mundurWilayah(): void
    {
        $this->halamanWilayah = max(1, $this->halamanWilayah - 1);
    }

    #[Url]
    public ?int $wilayah = null;

    #[Url]
    public bool $turunan = true;

    #[Url]
    public bool $global = false;

    public function node(): ?Wilayah
    {
        return $this->wilayah ? Wilayah::findOrFail($this->wilayah) : null;
    }

    public function nodes()
    {
        return Wilayah::query()->when($this->cariWilayah === '', fn ($q) => $q->where('parent_id', $this->wilayah))->when($this->cariWilayah !== '', fn ($q) => $q->where(fn ($q) => $q->where('nama', 'like', '%'.$this->cariWilayah.'%')->orWhere('kode', 'like', '%'.$this->cariWilayah.'%')))->orderBy('path')->offset(($this->halamanWilayah - 1) * 25)->limit(25)->get();
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

    protected function getTableQuery(): Builder
    {
        $query = parent::getTableQuery();
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
