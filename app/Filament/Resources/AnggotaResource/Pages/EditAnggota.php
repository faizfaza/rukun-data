<?php

namespace App\Filament\Resources\AnggotaResource\Pages;

use App\Filament\Resources\AnggotaResource;
use App\Models\Anggota;
use App\Models\RefHubungan;
use App\Support\SensitiveData;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditAnggota extends EditRecord
{
    protected static string $resource = AnggotaResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        // NIK berubah: pastikan hash baru belum dipakai baris lain (§8a).
        if (SensitiveData::digits($data['nik']) !== SensitiveData::digits($record->nik ?? '')) {
            $duplikat = Anggota::where(
                'nik_hash',
                SensitiveData::hash(SensitiveData::digits($data['nik']))
            )->withTrashed()->whereKeyNot($record->getKey())->first();

            if ($duplikat !== null) {
                throw ValidationException::withMessages([
                    'data.nik' => 'NIK sudah terdaftar pada data lain.',
                ]);
            }
        }

        // Tepat satu kepala: hitung saudara serumah di luar baris ini.
        if ((int) ($data['id_hubungan'] ?? 0) === RefHubungan::KEPALA) {
            $kepalaLain = Anggota::withoutGlobalScopes()
                ->where('id_keluarga', $record->id_keluarga)
                ->whereKeyNot($record->getKey())
                ->where('id_hubungan', RefHubungan::KEPALA)
                ->exists();

            if ($kepalaLain) {
                throw ValidationException::withMessages([
                    'data.id_hubungan' => 'Keluarga ini sudah memiliki kepala keluarga.',
                ]);
            }
        } else {
            $kepalaTersisa = Anggota::withoutGlobalScopes()
                ->where('id_keluarga', $record->id_keluarga)
                ->whereKeyNot($record->getKey())
                ->where('id_hubungan', RefHubungan::KEPALA)
                ->exists();

            if (! $kepalaTersisa) {
                throw ValidationException::withMessages([
                    'data.id_hubungan' => 'Keluarga harus memiliki tepat satu kepala keluarga.',
                ]);
            }
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [ViewAction::make(), DeleteAction::make()];
    }
}
