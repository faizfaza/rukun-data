<?php

namespace App\Filament\Resources\KeluargaResource\Pages;

use App\Filament\Resources\KeluargaResource;
use App\Models\Anggota;
use App\Models\Keluarga;
use App\Models\RefHubungan;
use App\Support\SensitiveData;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

// PRD §6: validasi No KK/NIK di awal (format, duplikat aktif, arsip untuk
// pemulihan), tepat satu kepala, lalu simpan satu transaksi. Filament
// membungkus pembuatan record + relasi dalam transaksi database.
class CreateKeluarga extends CreateRecord
{
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

    protected static string $resource = KeluargaResource::class;

    // DESIGN.md §7.3.5/§9.2: tombol simpan bernama "Simpan keluarga".
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()->label('Simpan keluarga');
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $target = $this->wilayahKonteks;
        if ($target && ! empty($data['id_wilayah']) && (int) $data['id_wilayah'] !== $target) {
            throw ValidationException::withMessages(['data.id_wilayah' => 'Wilayah harus sesuai konteks pembuatan keluarga.']);
        }
        $user = auth()->user();
        if (empty($data['id_wilayah'])) {
            if ($user?->isAdminRt() && $user->id_wilayah) {
                $data['id_wilayah'] = $user->id_wilayah;
            } elseif ($this->wilayahKonteks) {
                $data['id_wilayah'] = $this->wilayahKonteks;
            }
        }

        // Data repeater relasi tidak ikut getState(); dibaca dari raw state.
        $raw = $this->form->getRawState();

        $this->assertNoDuplicates($data['no_kk'], $raw['anggota'] ?? []);

        return $data;
    }

    /**
     * @param  array<string, array<string, mixed>>  $anggotaRows
     */
    private function assertNoDuplicates(string $noKk, array $anggotaRows): void
    {
        $existing = Keluarga::where(
            'no_kk_hash',
            SensitiveData::hash(SensitiveData::digits($noKk))
        )->withTrashed()->first();

        if ($existing !== null) {
            throw ValidationException::withMessages([
                'data.no_kk' => $existing->trashed()
                    ? 'No KK ini ada di arsip (terhapus). Pulihkan dulu lewat filter Sampah di daftar, lalu edit.'
                    : 'No KK sudah terdaftar. Buka data keluarga yang ada untuk melihat atau mengubahnya.',
            ]);
        }

        $kepala = 0;

        foreach ($anggotaRows as $row) {
            if ((int) ($row['id_hubungan'] ?? 0) === RefHubungan::KEPALA) {
                $kepala++;
            }

            $nik = (string) ($row['nik'] ?? '');

            if ($nik === '' || ! preg_match('/^[0-9]{16}$/', SensitiveData::digits($nik))) {
                continue;
            }

            $duplikat = Anggota::where(
                'nik_hash',
                SensitiveData::hash(SensitiveData::digits($nik))
            )->withTrashed()->first();

            if ($duplikat !== null) {
                throw ValidationException::withMessages([
                    'data.anggota' => "NIK {$nik} sudah terdaftar pada keluarga lain.",
                ]);
            }
        }

        if ($kepala !== 1) {
            throw ValidationException::withMessages([
                'data.anggota' => 'Setiap keluarga harus memiliki tepat satu kepala keluarga.',
            ]);
        }
    }
}
