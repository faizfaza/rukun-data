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

    // Node yang dipilih saat membuka lewat tautan langsung.
    #[Url(as: 'wilayah')]
    public ?int $wilayahId = null;

    public string $cariKk = '';

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

        // Khusus petugas RT:
        if ($user?->isAdminRt()) {
            return $this->applyLevelFilter($query->where('id', $user->id_wilayah));
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
