<?php

namespace App\Filament\Resources\ArsipSnapshotDesaResource\Pages;

use App\Filament\Resources\ArsipSnapshotDesaResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ListArsipSnapshotDesas extends ListRecords
{
    protected static string $resource = ArsipSnapshotDesaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('unduhSemuaCsv')
                ->label('Unduh Seluruh Dataset (CSV)')
                ->icon('heroicon-m-arrow-down-tray')
                ->color('primary')
                ->tooltip('Mengunduh gabungan seluruh periode dataset (mengikuti filter wilayah yang sedang dipilih)')
                ->action(fn (): StreamedResponse => ArsipSnapshotDesaResource::streamGabunganCsvDataset(
                    $this->getFilteredTableQuery()->orderBy('created_at')->get()
                )),

            Action::make('unduhSemuaJson')
                ->label('Unduh Seluruh Dataset (JSON)')
                ->icon('heroicon-m-code-bracket')
                ->color('gray')
                ->tooltip('Mengunduh gabungan seluruh periode dalam format JSON')
                ->action(fn (): StreamedResponse => ArsipSnapshotDesaResource::streamGabunganJsonDataset(
                    $this->getFilteredTableQuery()->orderBy('created_at')->get()
                )),
        ];
    }
}
