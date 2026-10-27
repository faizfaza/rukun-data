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

                $this->form->fill([
                    'level' => $childLevel,
                    'parent_id' => $parent->id,
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

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        $parent = $this->record->parent_id;

        return WilayahResource::getUrl('index', array_filter(['parent' => $parent]));
    }
}
