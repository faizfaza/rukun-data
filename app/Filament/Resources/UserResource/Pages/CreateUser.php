<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $pesanRole = UserResource::pesanRoleTidakBoleh($data['role'] ?? null);

        if ($pesanRole !== null) {
            throw ValidationException::withMessages(['data.role' => $pesanRole]);
        }

        $pesan = UserResource::pesanWilayahTidakValid($data['role'] ?? null, $data['id_wilayah'] ?? null);

        if ($pesan !== null) {
            throw ValidationException::withMessages(['data.id_wilayah' => $pesan]);
        }

        // Email bawaan Laravel tetap diisi otomatis dari username (tidak dipakai login).
        $data['email'] = $data['username'].'@rukundata.local';
        // Keputusan 6: pembuat akun dicatat otomatis.
        $data['created_by'] = auth()->id();

        return $data;
    }
}
