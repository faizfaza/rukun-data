<?php

namespace App\Filament\Resources\WilayahResource\Pages;

use App\Filament\Resources\WilayahResource;
use App\Models\Wilayah;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Schemas\Components\Tabs\Tab;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ListWilayah extends ListRecords
{
    protected static string $resource = WilayahResource::class;

    protected string $view = 'filament.resources.wilayah-resource.pages.list-wilayah';

    #[Url(as: 'parent')]
    public ?int $parentId = null;

    #[Url(as: 'mode')]
    public string $mode = 'hirarki';

    // Tab gabungan: Jelajah (operasional) dan Master (pengelolaan wilayah).
    #[Url(as: 'tab')]
    public string $tab = 'master';

    #[Url(as: 'level')]
    public string $levelFilter = '';

    // Node yang dipilih saat membuka tab Jelajah lewat tautan langsung.
    #[Url(as: 'wilayah')]
    public ?int $wilayahId = null;

    public function mount(): void
    {
        parent::mount();

        $user = auth()->user();

        if ($user?->isAdminRt() || ! $this->bolehMaster()) {
            $this->tab = 'jelajah';
        }

        if ($user?->isAdminRw() && $user->id_wilayah && $this->parentId === null && $this->mode === 'hirarki') {
            $this->parentId = $user->id_wilayah;
        }
    }

    public function getTitle(): string
    {
        return $this->tab === 'jelajah' ? 'Jelajah Wilayah' : 'Master Wilayah';
    }

    public function getBreadcrumb(): string
    {
        return $this->tab === 'jelajah' ? 'Jelajah' : 'Master';
    }

    public function bolehMaster(): bool
    {
        $user = auth()->user();

        return (bool) ($user?->isSuperadmin() || $user?->isAdmin() || $user?->isAdminRw());
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
        if ($this->tab === 'jelajah') {
            return [];
        }

        return [
            CreateAction::make()
                ->label('Tambah wilayah')
                ->url(fn (): string => WilayahResource::getUrl('create', array_filter([
                    'parent_id' => $this->parentId,
                ]))),
        ];
    }

    protected function getTableQuery(): Builder
    {
        // Tabel master tidak dirender saat tab Jelajah aktif.
        if ($this->tab === 'jelajah') {
            return parent::getTableQuery()->whereRaw('1 = 0');
        }

        $query = parent::getTableQuery()->with([Wilayah::RELASI_LELUHUR]);
        $user = auth()->user();

        if (filled($this->levelFilter)) {
            $parent = $this->currentParent();

            if ($parent !== null) {
                $query->where('path', 'like', $parent->path.'%')->whereKeyNot($parent->getKey());
            }

            return $query->where('level', $this->levelFilter);
        }

        // Mode flat / semua: tampilkan seluruh wilayah sesuai hak akses pengguna
        if ($this->mode === 'semua') {
            return $this->applyLevelFilter($query);
        }

        // Jika ada pencarian di kotak cari tabel
        if (filled($this->getTableSearch())) {
            if ($this->parentId !== null) {
                $parent = $this->currentParent();
                if ($parent) {
                    return $this->applyLevelFilter($query->where('path', 'like', $parent->path.'%')->whereKeyNot($parent->getKey()));
                }
            }

            return $this->applyLevelFilter($query);
        }

        // Khusus petugas RW:
        if ($user?->isAdminRw()) {
            $parent = $this->currentParent();
            if ($parent) {
                return $this->applyLevelFilter($query->where('parent_id', $parent->id));
            }

            return $this->applyLevelFilter($query->where('id', $user->id_wilayah));
        }

        // Superadmin & Admin di level akar:
        if ($this->parentId === null) {
            return $this->applyLevelFilter($query->whereNull('parent_id'));
        }

        // Superadmin & Admin di bawah induk terpilih:
        return $this->applyLevelFilter($query->where('parent_id', $this->parentId));
    }

    protected function applyLevelFilter(Builder $query): Builder
    {
        return filled($this->levelFilter)
            ? $query->where('level', $this->levelFilter)
            : $query;
    }

    public function currentParent(): ?Wilayah
    {
        $user = auth()->user();

        if ($this->parentId === null) {
            if ($user?->isAdminRw() && $user->id_wilayah) {
                return $user->wilayah;
            }

            return null;
        }

        $parent = Wilayah::find($this->parentId);
        if ($parent === null) {
            $this->parentId = $user?->isAdminRw() ? $user->id_wilayah : null;

            return $user?->isAdminRw() ? $user->wilayah : null;
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

    public function resetWilayahFilter(): void
    {
        $this->tableSearch = '';
        $this->levelFilter = '';
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
        $this->activeTab = null;
        unset($this->cachedTabs);
        $this->resetTable();
    }

    public function keAkar(): void
    {
        $user = auth()->user();
        $this->parentId = ($user?->isAdminRw() && $user->id_wilayah) ? $user->id_wilayah : null;
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
