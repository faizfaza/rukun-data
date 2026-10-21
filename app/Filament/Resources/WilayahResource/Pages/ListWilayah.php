<?php

namespace App\Filament\Resources\WilayahResource\Pages;

use App\Filament\Resources\WilayahResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListWilayah extends ListRecords
{
    protected static string $resource = WilayahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Tambah wilayah'),
        ];
    }
}
