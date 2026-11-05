<?php

namespace App\Filament\Resources\WilayahResource\Pages;

use App\Filament\Resources\WilayahResource;
use App\Models\Wilayah;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditWilayah extends EditRecord
{
    protected static string $resource = WilayahResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('aktivasi')
                ->label(fn (): string => $this->record->is_active ? 'Nonaktifkan Wilayah' : 'Aktifkan Wilayah')
                ->icon('heroicon-m-power')
                ->color(fn (): string => $this->record->is_active ? 'danger' : 'success')
                ->requiresConfirmation()
                ->modalHeading(fn (): string => $this->record->is_active ? "Nonaktifkan {$this->record->nama}?" : "Aktifkan {$this->record->nama}?")
                ->modalDescription(function (): string {
                    $levelLabel = Wilayah::labelLevel($this->record->level);
                    if ($this->record->is_active) {
                        $turunan = $this->record->descendants()->count();
                        if ($turunan > 0) {
                            return "Menonaktifkan {$this->record->nama} ({$levelLabel}) akan menyebabkan wilayah ini beserta seluruh {$turunan} sub-wilayah di bawahnya (termasuk RT dan RW di dalamnya) menjadi DOWN / tidak operasional.";
                        }

                        return "Menonaktifkan {$this->record->nama} ({$levelLabel}) hanya akan menyebabkan {$levelLabel} ini saja yang DOWN / tidak operasional.";
                    }

                    return "Mengaktifkan {$this->record->nama} ({$levelLabel}). Seluruh sub-wilayah di bawahnya yang aktif akan kembali operasional.";
                })
                ->modalSubmitActionLabel(fn (): string => $this->record->is_active ? 'Ya, Nonaktifkan' : 'Ya, Aktifkan')
                ->visible(fn (): bool => auth()->user()?->can('update', $this->record) ?? false)
                ->action(function (): void {
                    $this->record->update(['is_active' => ! $this->record->is_active]);
                    $this->fillForm();

                    Notification::make()
                        ->title($this->record->is_active ? 'Wilayah Diaktifkan' : 'Wilayah Dinonaktifkan')
                        ->body($this->record->is_active
                            ? "{$this->record->nama} telah diaktifkan kembali."
                            : "{$this->record->nama} beserta seluruh turunannya kini berstatus down."
                        )
                        ->success()
                        ->send();
                }),
            DeleteAction::make(),
        ];
    }

    protected function getRedirectUrl(): string
    {
        $parent = $this->record->parent_id;

        return WilayahResource::getUrl('index', array_filter(['parent' => $parent]));
    }
}
