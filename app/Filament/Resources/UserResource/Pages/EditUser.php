<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Validation\ValidationException;

class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    // Tidak ada hapus akun: baris tidak disediakan.
    protected function getHeaderActions(): array
    {
        return [];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $record = $this->getRecord();

        // Pengguna tidak boleh mengubah peran atau menonaktifkan dirinya sendiri.
        if ($record->is(auth()->user())) {
            $data['role'] = $record->role->value;
            $data['is_active'] = $record->is_active;

            if (($data['role'] ?? null) !== $record->role->value) {
                throw ValidationException::withMessages(['data.role' => 'Anda tidak dapat mengubah peran sendiri.']);
            }
        }

        // Pengguna tidak berwenang menonaktifkan akun jika tidak memiliki izin deactivate (misal: admin menonaktifkan admin lain atau superadmin).
        $currentUser = auth()->user();
        if ($currentUser && ! $currentUser->can('deactivate', $record)) {
            $data['is_active'] = $record->is_active;
        }

        $pesanRole = UserResource::pesanRoleTidakBoleh($data['role'] ?? null, $record);

        if ($pesanRole !== null) {
            throw ValidationException::withMessages(['data.role' => $pesanRole]);
        }

        $pesan = UserResource::pesanWilayahTidakValid($data['role'] ?? null, $data['id_wilayah'] ?? null);

        if ($pesan !== null) {
            throw ValidationException::withMessages(['data.id_wilayah' => $pesan]);
        }

        return $data;
    }
}
