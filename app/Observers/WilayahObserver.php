<?php

namespace App\Observers;

use App\Models\Wilayah;
use App\Support\OperationalGuard;
use Illuminate\Validation\ValidationException;

// Menegakkan aturan hierarki di level model, sehingga seeder, console, dan
// kode apa pun tidak bisa membuat wilayah dengan induk yang salah level.
// Memindahkan induk dan mengubah level wilayah tidak diizinkan di prototype.
class WilayahObserver
{
    public function creating(Wilayah $wilayah): void
    {
        if ($wilayah->parent_id && $wilayah->sumber !== 'kemendagri') {
            OperationalGuard::wilayah($wilayah->parent_id);
        }
        $this->validasi($wilayah);
    }

    public function updating(Wilayah $wilayah): void
    {
        if ($wilayah->isDirty('parent_id')) {
            throw ValidationException::withMessages([
                'parent_id' => 'Induk wilayah tidak dapat dipindahkan pada prototype ini.',
            ]);
        }

        if ($wilayah->isDirty('level')) {
            throw ValidationException::withMessages([
                'level' => 'Level wilayah tidak dapat diubah.',
            ]);
        }

        $this->validasi($wilayah);
    }

    private function validasi(Wilayah $wilayah): void
    {
        $pesan = Wilayah::pesanIndukTidakValid(
            (string) $wilayah->level,
            $wilayah->parent_id === null ? null : (int) $wilayah->parent_id,
        );

        if ($pesan !== null) {
            throw ValidationException::withMessages(['parent_id' => $pesan]);
        }

        // Kolom tipe hanya untuk level desa.
        if ($wilayah->level === 'desa') {
            if (filled($wilayah->tipe) && ! in_array($wilayah->tipe, ['desa', 'kelurahan'], true)) {
                throw ValidationException::withMessages([
                    'tipe' => 'Tipe desa harus "desa" atau "kelurahan".',
                ]);
            }
        } elseif (filled($wilayah->tipe)) {
            throw ValidationException::withMessages([
                'tipe' => 'Tipe hanya untuk level desa.',
            ]);
        }
    }
}
