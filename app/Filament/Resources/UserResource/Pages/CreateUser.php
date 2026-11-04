<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

class CreateUser extends CreateRecord
{
    protected function getRedirectUrl(): string
    {
        return UserResource::getUrl('index', array_filter(['wilayah' => $this->asalKonteks ?: $this->record?->id_wilayah]));
    }

    #[Locked]
    public ?int $wilayahKonteks = null;

    #[Locked]
    public ?int $asalKonteks = null;

    public function mount(): void
    {
        $this->wilayahKonteks = request()->integer('id_wilayah') ?: null;
        $this->asalKonteks = request()->integer('context') ?: null;
        parent::mount();
    }

    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if ($target = $this->wilayahKonteks) {
            if ((int) ($data['id_wilayah'] ?? 0) !== $target) {
                throw ValidationException::withMessages(['data.id_wilayah' => 'Wilayah harus sesuai konteks pembuatan admin.']);
            }
        }
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
