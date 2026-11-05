<?php

namespace App\Filament\Resources\WilayahResource\Pages;

use App\Filament\Resources\WilayahResource;
use App\Models\Wilayah;
use Filament\Resources\Pages\CreateRecord;
use Livewire\Attributes\Url;

class CreateWilayah extends CreateRecord
{
    protected static string $resource = WilayahResource::class;

    #[Url(as: 'parent_id')]
    public ?int $parentId = null;

    protected function fillForm(): void
    {
        parent::fillForm();

        $parentId = $this->parentId ?? request('parent_id');
        if ($parentId && ! auth()->user()?->isAdminRw()) {
            $parent = Wilayah::find($parentId);
            if ($parent) {
                $childLevel = match ($parent->level) {
                    'negara' => 'provinsi',
                    'provinsi' => 'kabupaten_kota',
                    'kabupaten_kota' => 'kecamatan',
                    'kecamatan' => 'desa',
                    'desa' => 'rw',
                    'dusun' => 'rw',
                    'rw' => 'rt',
                    default => null,
                };

                $ancestors = $parent->ancestors()->keyBy('level');

                $this->form->fill([
                    'level' => $childLevel,
                    'parent_id' => $parent->id,
                    'provinsi_id' => $ancestors->get('provinsi')?->id ?? ($parent->level === 'provinsi' ? $parent->id : null),
                    'kabkota_id' => $ancestors->get('kabupaten_kota')?->id ?? ($parent->level === 'kabupaten_kota' ? $parent->id : null),
                    'kecamatan_id' => $ancestors->get('kecamatan')?->id ?? ($parent->level === 'kecamatan' ? $parent->id : null),
                    'desa_id' => $ancestors->get('desa')?->id ?? ($parent->level === 'desa' ? $parent->id : null),
                    'dusun_id' => $parent->level === 'dusun' ? $parent->id : ($parent->level === 'desa' ? $parent->id : null),
                    'rw_id' => $ancestors->get('rw')?->id ?? ($parent->level === 'rw' ? $parent->id : null),
                ]);
            }
        }
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = auth()->user();
        if ($user?->isAdminRw()) {
            $data['level'] = 'rt';
            $data['parent_id'] = $user->id_wilayah;
        }

        if (! ($data['parent_id'] ?? null)) {
            $data['parent_id'] = match ($data['level'] ?? null) {
                'provinsi' => Wilayah::where('level', 'negara')->value('id'),
                'kabupaten_kota' => $data['provinsi_id'] ?? null,
                'kecamatan' => $data['kabkota_id'] ?? null,
                'desa' => $data['kecamatan_id'] ?? null,
                'dusun' => $data['desa_id'] ?? null,
                'rw' => $data['dusun_id'] ?? $data['desa_id'] ?? null,
                'rt' => $data['rw_id'] ?? null,
                default => null,
            };
        }

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        $parent = $this->record->parent_id;

        return WilayahResource::getUrl('index', array_filter(['parent' => $parent]));
    }
}
