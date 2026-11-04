<?php

namespace App\Filament\Resources\KeluargaResource\Pages;

use App\Filament\Resources\KeluargaResource;
use App\Models\RefHubungan;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditKeluarga extends EditRecord
{
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['no_kk'] = $this->record->no_kk;
        $data['alamat'] = $this->record->alamat;

        return $data;
    }

    protected static string $resource = KeluargaResource::class;

    // DESIGN.md §9.2: nama tindakan konsisten, "Simpan keluarga".
    protected function getSaveFormAction(): Action
    {
        return parent::getSaveFormAction()->label('Simpan keluarga');
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $kepala = 0;

        foreach ($this->form->getRawState()['anggota'] ?? [] as $row) {
            if ((int) ($row['id_hubungan'] ?? 0) === RefHubungan::KEPALA) {
                $kepala++;
            }
        }

        if ($kepala !== 1) {
            throw ValidationException::withMessages([
                'data.anggota' => 'Setiap keluarga harus memiliki tepat satu kepala keluarga.',
            ]);
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}
