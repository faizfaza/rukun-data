<?php

namespace App\Filament\Resources\WilayahResource\Pages;

use App\Filament\Resources\WilayahResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditWilayah extends EditRecord
{
    protected static string $resource = WilayahResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }

    protected function getRedirectUrl(): string
    {
        $parent = $this->record->parent_id;

        return WilayahResource::getUrl('index', array_filter(['parent' => $parent]));
    }
}
