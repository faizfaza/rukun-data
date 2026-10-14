<?php

namespace App\Filament\Resources\KeluargaResource\Pages;

use App\Filament\Resources\KeluargaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListKeluarga extends ListRecords
{
    protected static string $resource = KeluargaResource::class;

    // DESIGN.md §6.3/§7.2: satu tindakan utama di kanan atas halaman.
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah keluarga'),
        ];
    }
}
